<?php
/**
 * WebFinger
 *
 * @author Matthias Pfefferle
 * @author Will Norris
 */
class Webfinger {

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
	public static function generate_rewrite_rules() {
		add_rewrite_rule( '^.well-known/webfinger', 'index.php?well-known=webfinger', 'top' );
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
		if (
			! array_key_exists( 'well-known', $wp->query_vars ) ||
			'webfinger' != $wp->query_vars['well-known']
		) {
			return;
		}

		header( 'Access-Control-Allow-Origin: *' );

		// check if "resource" param exists
		if (
			! array_key_exists( 'resource', $wp->query_vars ) ||
			empty( $wp->query_vars['resource'] )
		) {
			status_header( 400 );
			header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ), true );

			echo 'missing "resource" parameter';

			exit;
		}

		$resource = esc_html( $wp->query_vars['resource'] );

		// filter WebFinger array
		$webfinger = apply_filters( 'webfinger_data', array(), $resource );

		// check if "user" exists
		if ( empty( $webfinger ) ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ), true );

			printf( 'no data for resource "%s" found', $resource );

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

		echo wp_json_encode( $webfinger );
		exit;
	}

	/**
	 * Generates the WebFinger base array
	 *
	 * @param array    $webfinger   the WebFinger data-array
	 * @param stdClass $user        the WordPress user
	 * @param string   $resource    the resource param
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
				array(
					'rel' => 'http://webfinger.net/rel/profile-page',
					'href' => esc_url( $url ),
					'type' => 'text/html',
				),
				array(
					'rel' => 'http://webfinger.net/rel/avatar',
					'href' => esc_url( $photo ),
				),
			),
		);

		// add user_url if set
		if ( isset( $user->user_url ) && ! empty( $user->user_url ) ) {
			$webfinger['links'][] = array(
				'rel' => 'http://webfinger.net/rel/profile-page',
				'href' => esc_url( $user->user_url ),
				'type' => 'text/html',
			);
		}

		return apply_filters( 'webfinger_user_data', $webfinger, $resource, $user );
	}

	/**
	 * generates the webfinger base array
	 *
	 * @param array  $webfinger the webfinger data-array
	 * @param string $resource the resource param
	 *
	 * @return array the enriched webfinger data-array
	 */
	public static function generate_post_data( $webfinger, $resource ) {
		// find matching post
		$post_id = url_to_postid( $resource );

		// check if there is a matching post-id
		if ( ! $post_id ) {
			return $webfinger;
		}

		// get post by id
		$post = get_post( $post_id );

		// check if there is a matching post
		if ( ! $post ) {
			return $webfinger;
		}

		$author = get_user_by( 'id', $post->post_author );

		// default webfinger array for posts
		$webfinger = array(
			'subject' => get_permalink( $post->ID ),
			'aliases' => apply_filters( 'webfinger_post_resource', array( home_url( '?p=' . $post->ID ), get_permalink( $post->ID ) ), $post ),
			'links' => array(
				array(
					'rel'  => 'shortlink',
					'type' => 'text/html',
					'href' => wp_get_shortlink( $post ),
				),
				array(
					'rel'  => 'canonical',
					'type' => 'text/html',
					'href' => get_permalink( $post->ID ),
				),
				array(
					'rel'  => 'author',
					'type' => 'text/html',
					'href' => get_author_posts_url( $author->ID, $author->nicename ),
				),
				array(
					'rel'  => 'alternate',
					'type' => 'application/rss+xml',
					'href' => get_post_comments_feed_link( $post->ID, 'rss2' ),
				),
				array(
					'rel'  => 'alternate',
					'type' => 'application/atom+xml',
					'href' => get_post_comments_feed_link( $post->ID, 'atom' ),
				),
			),
		);

		return apply_filters( 'webfinger_post_data', $webfinger, $resource, $post );
	}

	/**
	 * Filters the WebFinger array by request params like "rel"
	 *
	 * @link http://tools.ietf.org/html/rfc7033#section-4.3
	 *
	 * @param array     $array
	 * @param stdClass  $user
	 * @param array     $queries
	 *
	 * @return array
	 */
	public static function filter_by_rel( $webfinger ) {
		// check if WebFinger is empty or if "rel"
		// is set or if array has any "links"
		if (
			empty( $webfinger ) ||
			! array_key_exists( 'rel', $_GET ) ||
			! isset( $webfinger['links'] )
		) {
			return $webfinger;
		}

		// explode the query-string by hand because php does not
		// support multiple queries with the same name
		$query = explode( '&', $_SERVER['QUERY_STRING'] );
		$rels = array();

		foreach ( $query as $param ) {
			$param = explode( '=', $param );

			// check if query-string is valid and if it is a 'rel'
			if (
				isset( $param[0], $param[1] ) &&
				'rel' == $param[0] &&
				! empty( $param[1] )
			) {
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
		$uri   = urldecode( $uri );
		$uri   = str_replace( array( '*', '%' ), '', $uri );
		$match = array();

		// try to extract the scheme and the host
		if ( preg_match( '/^([a-zA-Z^:]+):(.*)$/i', $uri, $match ) ) {
			// extract the scheme
			$scheme = esc_attr( $match[1] );
			// extract the "host"
			$host = sanitize_text_field( $match[2] );
		} else { // fallback to 'acct' as default theme
			$scheme = 'acct';
			// extract the "host"
			$host = $uri;
		}

		// check if $host and $uri are set
		if ( ! $host || ! $uri ) {
			return null;
		}

		switch ( $scheme ) {
			case 'http': // check urls
			case 'https':
				// check if is the author url
				$author_id = url_to_authorid( $uri );
				if ( $author_id ) {
					$args = array(
						'search' => $author_id,
						'search_columns' => array( 'ID' ),
					);
				} else { // check other urls
					// search url in user_url
					$args = array(
						'search' => $uri,
						'search_columns' => array( 'user_url' ),
					);
				}

				break;
			case 'acct': // check acct scheme
				// get the identifier at the left of the '@'
				$parts = explode( '@', $host );

				if ( ! $parts[0] ) {
					return null;
				}

				// check domain
				if (
					! isset( $parts[1] ) ||
					parse_url( home_url(), PHP_URL_HOST ) !== $parts[1]
				) {
					return null;
				}

				$args = array(
					'search' => $parts[0],
					'search_columns' => array(
						'user_nicename',
						'user_login',
					),
				);
				break;
			case 'mailto': // check mailto scheme
				$args = array(
					'search' => $host,
					'search_columns' => array( 'user_email' ),
				);
				break;
			case 'xmpp': // check xmpp/jabber schemes
			case 'urn:xmpp':
			case 'im':
				$args = array(
					'meta_key' => 'jabber',
					'meta_value' => $host,
					'meta_compare' => '=',
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
	public static function get_user_resource( $id_or_name_or_object, $with_protocol = true ) {
		$user = get_user_by_various( $id_or_name_or_object );
		$resource = null;

		if ( $user ) {
			$resource = $user->user_login . '@' . parse_url( home_url(), PHP_URL_HOST );

			if ( $with_protocol ) {
				$resource = 'acct:' . $resource;
			}
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
		$user = get_user_by_various( $id_or_name_or_object );

		if ( ! $user ) {
			return array();
		}

		// generate account idenitfier (acct: uri)
		$resources[] = self::get_user_resource( $user );
		$resources[] = get_author_posts_url( $user->ID, $user->user_nicename );

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
}
