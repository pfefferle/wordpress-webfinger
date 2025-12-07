<?php

namespace Webfinger;

/**
 * WebFinger
 *
 * @author Matthias Pfefferle
 * @author Will Norris
 */
class Webfinger {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		add_action( 'query_vars', array( static::class, 'query_vars' ) );
		add_action( 'parse_request', array( static::class, 'parse_request' ) );

		add_action( 'init', array( static::class, 'generate_rewrite_rules' ) );

		add_filter( 'webfinger_data', array( static::class, 'generate_user_data' ), 10, 3 );
		add_filter( 'webfinger_data', array( static::class, 'generate_post_data' ), 10, 3 );
		add_filter( 'webfinger_data', array( static::class, 'filter_by_rel' ), 99, 1 );

		// default output
		add_action( 'webfinger_render', array( static::class, 'render_jrd' ) );
	}

	/**
	 * Add query vars.
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
	 * Add rewrite rules.
	 *
	 * @param WP_Rewrite
	 */
	public static function generate_rewrite_rules() {
		add_rewrite_rule( '^.well-known/webfinger', 'index.php?well-known=webfinger', 'top' );
	}

	/**
	 * Parse the WebFinger request and render the document.
	 *
	 * @param WP $wp WordPress request context.
	 *
	 * @uses apply_filters() Calls 'webfinger' on webfinger data array.
	 * @uses do_action()     Calls 'webfinger_render' to render webfinger data.
	 */
	public static function parse_request( $wp ) {
		// check if it is a webfinger request or not
		if (
			! array_key_exists( 'well-known', $wp->query_vars ) ||
			'webfinger' !== $wp->query_vars['well-known']
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
	 * Render the JRD representation of the WebFinger resource.
	 *
	 * @param array $webfinger The WebFinger data-array.
	 */
	public static function render_jrd( $webfinger ) {
		header( 'Content-Type: application/jrd+json; charset=' . get_bloginfo( 'charset' ), true );

		echo wp_json_encode( $webfinger );
		exit;
	}

	/**
	 * Generates the WebFinger base array.
	 *
	 * @param array    $webfinger The WebFinger data-array.
	 * @param stdClass $user      The WordPress user.
	 * @param string   $resource  The resource param.
	 *
	 * @return array The enriched WebFinger data-array.
	 */
	public static function generate_user_data( $webfinger, $resource ) {
		// find matching user
		$user = User::get_user_by_uri( $resource );

		if ( ! $user ) {
			return $webfinger;
		}

		// generate "profile" url
		$url = get_author_posts_url( $user->ID, $user->user_nicename );

		// generate default photo-url
		$photo = get_avatar_url( $user->ID );

		// generate default array
		$webfinger = array(
			'subject' => User::get_resource( $user->ID ),
			'aliases' => User::get_resources( $user->ID ),
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
		if (
			isset( $user->user_url ) &&
			! empty( $user->user_url ) // &&
			//self::is_same_host( $user->user_url )
		) {
			$webfinger['links'][] = array(
				'rel' => 'http://webfinger.net/rel/profile-page',
				'href' => esc_url( $user->user_url ),
				'type' => 'text/html',
			);
		}

		return apply_filters( 'webfinger_user_data', $webfinger, $resource, $user );
	}

	/**
	 * Generates the WebFinger base array.
	 *
	 * @param array  $webfinger The WebFinger data-array.
	 * @param string $resource  The resource param.
	 *
	 * @return array The enriched WebFinger data-array.
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
			'aliases' => apply_filters(
				'webfinger_post_resource',
				array(
					home_url( '?p=' . $post->ID ),
					get_permalink( $post->ID ),
				),
				$post
			),
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
	 * Filters the WebFinger array by request params like "rel".
	 *
	 * @link http://tools.ietf.org/html/rfc7033#section-4.3
	 *
	 * @param array    $array
	 * @param stdClass $user
	 * @param array    $queries
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
				'rel' === $param[0] &&
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
			if ( in_array( $link['rel'], $rels, true ) ) {
				$links[] = $link;
			}
		}
		$webfinger['links'] = $links;

		// return only "links" with the matching rel-values
		return $webfinger;
	}

}
