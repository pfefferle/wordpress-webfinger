<?php
/**
 * Plugin Name: WebFinger
 * Plugin URI: http://wordpress.org/extend/plugins/webfinger/
 * Description: WebFinger for WordPress
 * Version: 3.0.2
 * Author: pfefferle
 * Author URI: http://notizblog.org/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// flush rewrite rules
register_activation_hook( __FILE__, array( 'WebFingerPlugin', 'flush_rewrite_rules' ) );
register_deactivation_hook( __FILE__, array( 'WebFingerPlugin', 'flush_rewrite_rules' ) );

// initialize plugin
add_action( 'init', array( 'WebFingerPlugin', 'init' ) );

/**
 * WebFinger
 *
 * @author Matthias Pfefferle
 * @author Will Norris
 */
class WebFingerPlugin {

	/**
	 * Initialize the plugin, registering WordPress hooks.
	 */
	public static function init() {
		add_action( 'query_vars', array( 'WebFingerPlugin', 'query_vars' ) );
		add_action( 'parse_request', array( 'WebFingerPlugin', 'parse_request' ) );
		add_action( 'generate_rewrite_rules', array( 'WebFingerPlugin', 'rewrite_rules' ) );

		add_filter( 'webfinger_data', array( 'WebFingerPlugin', 'generate_user_data' ), 10, 3 );
		add_filter( 'webfinger_data', array( 'WebFingerPlugin', 'filter_by_rel' ), 99, 1 );

		// default output
		add_action( 'webfinger_render', array( 'WebFingerPlugin', 'render_jrd' ) );

		// support plugins pre 3.0.0
		add_filter( 'webfinger_user_data', array( 'WebFingerPlugin', 'legacy_filter' ), 10, 3 );
	}

	/**
	 * Add query vars
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'well-known';
		$vars[] = 'resource';
		$vars[] = 'rel';

		return $vars;
	}

	/**
	 * Add rewrite rules
	 *
	 * @param WP_Rewrite
	 */
	public static function rewrite_rules( $wp_rewrite ) {
		$webfinger_rules = array(
			'.well-known/webfinger' => 'index.php?well-known=webfinger',
		);

		$wp_rewrite->rules = $webfinger_rules + $wp_rewrite->rules;
	}

	/**
	 * Parse the WebFinger request and render the document.
	 *
	 * @param WP $wp WordPress request context
	 *
	 * @uses apply_filters() Calls 'webfinger' on webfinger data array
	 * @uses do_action() Calls 'webfinger_render' to render webfinger data
	 */
	public static function parse_request( $wp ) {
		// check if it is a webfinger request or not
		if ( ! array_key_exists( 'well-known', $wp->query_vars ) ||
				'webfinger' != $wp->query_vars['well-known'] ) {
			return;
		}

		header( 'Access-Control-Allow-Origin: *' );

		// check if "resource" param exists
		if ( ! array_key_exists( 'resource', $wp->query_vars ) ||
				empty( $wp->query_vars['resource'] ) ) {
			status_header( 400 );
			header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ), true );

			echo 'missing "resource" parameter';

			exit;
		}

		// filter WebFinger array
		$webfinger = apply_filters( 'webfinger_data', array(), $wp->query_vars['resource'] );

		// check if "user" exists
		if ( empty( $webfinger ) ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ), true );

			echo 'no data for resource "' . $wp->query_vars['resource'] . '" found';

			exit;
		}

		do_action( 'webfinger_render', $webfinger );

		// stop exactly here!
		exit;
	}

	/**
	 * Render the JRD representation of the webfinger resource.
	 *
	 * @param array $webfinger the WebFinger data-array
	 */
	public static function render_jrd( $webfinger ) {
		header( 'Content-Type: application/jrd+json; charset=' . get_bloginfo( 'charset' ), true );

		echo json_encode( $webfinger );
		exit;
	}

	/**
	 * Generates the WebFinger base array
	 *
	 * @param array		$webfinger	the WebFinger data-array
	 * @param stdClass	$user		the WordPress user
	 * @param string	$resource	the resource param
	 *
	 * @return array the enriched webfinger data-array
	 */
	public static function generate_user_data( $webfinger, $resource ) {
		// find matching user
		$user = self::get_user_by_uri( $resource );

		if ( ! $user ) {
			return $webfinger;
		}

		// generate "profile" url
		$url = get_author_posts_url( $user->ID, $user->user_nicename );

		// generate default photo-url
		$photo = get_avatar_url( $user->ID );

		// generate default array
		$webfinger = array(
			'subject' => self::get_user_resource( $user->ID ),
			'aliases' => self::get_user_resources( $user->ID ),
			'links' => array(
				array( 'rel' => 'http://webfinger.net/rel/profile-page', 'href' => $url, 'type' => 'text/html' ),
				array( 'rel' => 'http://webfinger.net/rel/avatar', 'href' => $photo ),
			),
		);

		// add user_url if set
		if ( isset( $user->user_url ) && ! empty( $user->user_url ) ) {
			$webfinger['links'][] = array( 'rel' => 'http://webfinger.net/rel/profile-page', 'href' => $user->user_url, 'type' => 'text/html' );
		}

		return apply_filters( 'webfinger_user_data', $webfinger, $resource, $user );
	}

	/**
	 * Filters the WebFinger array by request params like "rel"
	 *
	 * @link http://tools.ietf.org/html/rfc7033#section-4.3
	 *
	 * @param array		$array
	 * @param stdClass	$user
	 * @param array		$queries
	 *
	 * @return array
	 */
	public static function filter_by_rel( $webfinger ) {
		// check if WebFinger is empty or if "rel"
		// is set or if array has any "links"
		if ( empty( $webfinger ) ||
				! array_key_exists( 'rel', $_GET ) ||
				! isset( $webfinger['links'] ) ) {
			return $webfinger;
		}

		// explode the query-string by hand because php does not
		// support multiple queries with the same name
		$query = explode( '&', $_SERVER['QUERY_STRING'] );
		$rels = array();

		foreach ( $query as $param ) {
			$param = explode( '=', $param );

			// check if query-string is valid and if it is a 'rel'
			if ( isset( $param[0], $param[1] ) &&
					'rel' == $param[0] &&
					! empty( $param[1] ) ) {
				$rels[] = urldecode( trim( $param[1] ) );
			}
		}

		// check if there is something to filter
		if ( empty( $rels ) ) {
			return $webfinger;
		}

		// filter WebFinger-array
		$links = array();
		foreach ( $webfinger['links'] as $link ) {
			if ( in_array( $link['rel'], $rels ) ) {
				$links[] = $link;
			}
		}
		$webfinger['links'] = $links;

		// return only "links" with the matching rel-values
		return $webfinger;
	}

	/**
	 * Returns a Userobject
	 *
	 * @param string $uri
	 *
	 * @return WP_User
	 *
	 * @uses apply_filters() uses 'webfinger_user' to filter the
	 *       user and 'webfinger_user_query' to add custom query-params
	 */
	private static function get_user_by_uri( $uri ) {
		$uri = urldecode( $uri );

		if ( ! preg_match( '/^([a-zA-Z^:]+):(.*)$/i', $uri, $match ) ) {
			// no valid scheme provided
			return null;
		}

		// extract the scheme
		$scheme = $match[1];
		// extract the "host"
		$host = $match[2];

		switch ( $scheme ) {
			case 'http': // check urls
			case 'https':
				// check if is the author url
				if ( $author_id = url_to_authorid( $uri ) ) {
					$args = array(
						'search' => $author_id,
						'search_columns' => array( 'ID' ),
						'meta_compare' => '=',
					);
				} else { // check other urls
					// search url in user_url
					$args = array(
						'search' => $uri,
						'search_columns' => array( 'user_url' ),
						'meta_compare' => '=',
					);
				}

				break;
			case 'acct': // check acct scheme
				// get the identifier at the left of the '@'
				$parts = explode( '@', $host );

				// check domain
				if ( ! isset( $parts[1] ) || parse_url( home_url(), PHP_URL_HOST ) !== $parts[1] ) {
					return null;
				}

				$args = array(
					'search' => $parts[0],
					'search_columns' => array( 'user_name', 'display_name', 'user_login' ),
					'meta_compare' => '=',
				);
				break;
			case 'mailto': // check mailto scheme
				$args = array(
					'search' => $host,
					'search_columns' => array( 'user_email' ),
					'meta_compare' => '=',
				);
				break;
			case 'xmpp': // check xmpp/jabber schemes
			case 'urn:xmpp':
				$args = array(
					'meta_key' => 'jabber',
					'meta_value' => $host,
					'meta_compare' => '=',
				);
				break;
			case 'ymsgr': // check Yahoo messenger schemes
				$args = array(
					'meta_key' => 'yim',
					'meta_value' => $host,
					'meta_compare' => '=',
				);
				break;
			case 'aim': // check AOL messenger schemes
				$args = array(
					'meta_key'  => 'aim',
					'meta_value' => $host,
					'meta_compare' => '=',
				);
				break;
			case 'im': // check instant messaging schemes
				$args = array(
					'meta_query' => array(
						'relation' => 'OR',
						array(
							'key' => 'jabber',
							'value' => $host,
							'compare' => '=',
						),
						array(
							'key' => 'yim',
							'value' => $host,
							'compare' => '=',
						),
						array(
							'key' => 'aim',
							'value' => $host,
							'compare' => '=',
						),
					),
				);
				break;
			default:
				$args = array();
				break;
		}

		$args = apply_filters( 'webfinger_user_query', $args, $uri, $scheme );

		// get user query
		$user_query = new WP_User_Query( $args );

		// check result
		if ( ! empty( $user_query->results ) ) {
			$user = $user_query->results[0];
		} else {
			$user = null;
		}

		return $user;
	}

	/**
	 * Returns a users default WebFinger
	 *
	 * @param mixed $id_or_name_or_object
	 *
	 * @return string|null
	 */
	public static function get_user_resource( $id_or_name_or_object ) {
		$user = self::get_user_by_various( $id_or_name_or_object );

		$resource = null;

		if ( $user ) {
			$resource = 'acct:' . $user->user_login . '@' . parse_url( home_url(), PHP_URL_HOST );
		}

		return apply_filters( 'webfinger_user_resource', $resource, $user );
	}

	/**
	 * Returns all WebFinger "resources"
	 *
	 * @param mixed $id_or_name_or_object
	 *
	 * @return array
	 */
	public static function get_user_resources( $id_or_name_or_object ) {
		$user = self::get_user_by_various( $id_or_name_or_object );

		if ( ! $user ) {
			return array();
		}

		// generate account idenitfier (acct: uri)
		$resources[] = self::get_user_resource( $user, true );
		$resources[] = get_author_posts_url( $user->ID, $user->user_nicename );

		if ( $user->user_email ) {
			$resources[] = 'mailto:' . $user->user_email;
		}

		/*
		 * the IM schemes are based on the "vCard Extensions for Instant Messaging (IM)".
		 * that means that the YahooID for example is represented by ymsgr:identifier
		 * and not by the ymsgr:SendIM?identifier pseudo uri
		 *
		 * @link http://tools.ietf.org/html/rfc4770#section-1
		 */
		if ( get_user_meta( $user->ID, 'yim', true ) ) {
			$resources[] = 'ymsgr:' . get_user_meta( $user->ID, 'yim', true );
		}

		// aim:identifier instead of aim:goim?screenname=identifier
		if ( get_user_meta( $user->ID, 'aim', true ) ) {
			$resources[] = 'aim:' . get_user_meta( $user->ID, 'aim', true );
		}

		if ( get_user_meta( $user->ID, 'jabber', true ) ) {
			$resources[] = 'xmpp:' . get_user_meta( $user->ID, 'jabber', true );
		}

		return array_unique( apply_filters( 'webfinger_user_resources', $resources, $user ) );
	}

	/**
	 * Convenience method to get user data by ID, username, object or from current user.
	 *
	 * @param mixed $id_or_name_or_object the username, ID or object. If not provided, the current user will be used.
	 *
	 * @return bool|object False on failure, User DB row object
	 *
	 * @author Will Norris
	 *
	 * @see get_userdata_by_various() # DiSo OpenID-Plugin
	 */
	public static function get_user_by_various( $id_or_name_or_object = null ) {
		if ( null === $id_or_name_or_object ) {
			$user = wp_get_current_user();
			if ( null == $user ) {
				return false;
			}
			return $user->data;
		} elseif ( is_object( $id_or_name_or_object ) ) {
			return $id_or_name_or_object;
		} elseif ( is_numeric( $id_or_name_or_object ) ) {
			return get_userdata( $id_or_name_or_object );
		} else {
			return get_userdatabylogin( $id_or_name_or_object );
		}
	}

	/**
	 * Backwards compatibility for old versions. please don't use!
	 *
	 * @deprecated
	 *
	 * @param array		$webfinger
	 * @param string	$resource
	 * @param WP_User	$user
	 *
	 * @return array
	 */
	public static function legacy_filter( $webfinger, $resource, $user ) {
		// filter WebFinger array
		return apply_filters( 'webfinger', $webfinger, $user, $resource, $_GET );
	}
}

if ( ! function_exists( 'url_to_authorid' ) ) {
	/**
	 * Examine a url and try to determine the author ID it represents.
	 *
	 * Checks are supposedly from the hosted site blog.
	 *
	 * @param string $url Permalink to check.
	 *
	 * @return int User ID, or 0 on failure.
	 */
	function url_to_authorid( $url ) {
		global $wp_rewrite;

		// check if url hase the same host
		if ( parse_url( site_url(), PHP_URL_HOST ) != parse_url( $url, PHP_URL_HOST ) ) {
			return 0;
		}

		// first, check to see if there is a 'author=N' to match against
		if ( preg_match( '/[?&]author=(\d+)/i', $url, $values ) ) {
			$id = absint( $values[1] );
			if ( $id ) {
				return $id;
			}
		}

		// check to see if we are using rewrite rules
		$rewrite = $wp_rewrite->wp_rewrite_rules();

		// not using rewrite rules, and 'author=N' method failed, so we're out of options
		if ( empty( $rewrite ) ) {
			return 0;
		}

		// generate rewrite rule for the author url
		$author_rewrite = $wp_rewrite->get_author_permastruct();
		$author_regexp = str_replace( '%author%', '', $author_rewrite );

		// match the rewrite rule with the passed url
		if ( preg_match( '/https?:\/\/(.+)' . preg_quote( $author_regexp, '/' ) . '([^\/]+)/i', $url, $match ) ) {
			if ( $user = get_user_by( 'slug', $match[2] ) ) {
				return $user->ID;
			}
		}

		return 0;
	}
}
