<?php
/**
 * WebFinger class file.
 *
 * @package Webfinger
 */

namespace Webfinger;

/**
 * WebFinger class.
 *
 * Handles WebFinger requests and responses.
 *
 * @author Matthias Pfefferle
 * @author Will Norris
 */
class Webfinger {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'query_vars', array( static::class, 'query_vars' ) );
		\add_action( 'parse_request', array( static::class, 'parse_request' ) );

		\add_action( 'init', array( static::class, 'generate_rewrite_rules' ) );

		\add_filter( 'webfinger_data', array( static::class, 'generate_user_data' ), 10, 3 );
		\add_filter( 'webfinger_data', array( static::class, 'generate_post_data' ), 10, 3 );
		\add_filter( 'webfinger_data', array( static::class, 'filter_by_rel' ), 99, 1 );

		// Default output.
		\add_action( 'webfinger_render', array( static::class, 'render_jrd' ) );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars The query vars.
	 *
	 * @return array The modified query vars.
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'well-known';
		$vars[] = 'resource';
		$vars[] = 'rel';

		return $vars;
	}

	/**
	 * Add rewrite rules.
	 */
	public static function generate_rewrite_rules() {
		\add_rewrite_rule( '^.well-known/webfinger', 'index.php?well-known=webfinger', 'top' );
	}

	/**
	 * Parse the WebFinger request and render the document.
	 *
	 * @param \WP $wp WordPress request context.
	 *
	 * @uses apply_filters() Calls 'webfinger' on webfinger data array.
	 * @uses do_action()     Calls 'webfinger_render' to render webfinger data.
	 */
	public static function parse_request( $wp ) {
		// Check if it is a webfinger request or not.
		if (
			! \array_key_exists( 'well-known', $wp->query_vars ) ||
			'webfinger' !== $wp->query_vars['well-known']
		) {
			return;
		}

		\header( 'Access-Control-Allow-Origin: *' );

		// Check if "resource" param exists.
		if (
			! \array_key_exists( 'resource', $wp->query_vars ) ||
			empty( $wp->query_vars['resource'] )
		) {
			\status_header( 400 );
			\header( 'Content-Type: text/plain; charset=' . \get_bloginfo( 'charset' ), true );

			echo 'missing "resource" parameter';

			exit;
		}

		$resource = \esc_html( $wp->query_vars['resource'] );

		// Filter WebFinger array.
		$webfinger = \apply_filters( 'webfinger_data', array(), $resource );

		// Check if "user" exists.
		if ( empty( $webfinger ) ) {
			\status_header( 404 );
			\header( 'Content-Type: text/plain; charset=' . \get_bloginfo( 'charset' ), true );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $resource is already escaped above.
			\printf( 'no data for resource "%s" found', $resource );

			exit;
		}

		\do_action( 'webfinger_render', $webfinger );

		// Stop exactly here!
		exit;
	}

	/**
	 * Render the JRD representation of the WebFinger resource.
	 *
	 * @param array $webfinger The WebFinger data-array.
	 */
	public static function render_jrd( $webfinger ) {
		\header( 'Content-Type: application/jrd+json; charset=' . \get_bloginfo( 'charset' ), true );

		echo \wp_json_encode( $webfinger );
		exit;
	}

	/**
	 * Generates the WebFinger base array for users.
	 *
	 * @param array  $webfinger    The WebFinger data-array.
	 * @param string $resource_uri The resource param.
	 *
	 * @return array The enriched WebFinger data-array.
	 */
	public static function generate_user_data( $webfinger, $resource_uri ) {
		// Find matching user.
		$user = User::get_user_by_uri( $resource_uri );

		if ( ! $user ) {
			return $webfinger;
		}

		// Generate "profile" url.
		$url = \get_author_posts_url( $user->ID, $user->user_nicename );

		// Generate default photo-url.
		$photo = \get_avatar_url( $user->ID );

		// Generate default array.
		$webfinger = array(
			'subject' => User::get_resource( $user->ID ),
			'aliases' => User::get_resources( $user->ID ),
			'links'   => array(
				array(
					'rel'  => 'http://webfinger.net/rel/profile-page',
					'href' => \esc_url( $url ),
					'type' => 'text/html',
				),
				array(
					'rel'  => 'http://webfinger.net/rel/avatar',
					'href' => \esc_url( $photo ),
				),
			),
		);

		// Add user_url if set.
		if (
			isset( $user->user_url ) &&
			! empty( $user->user_url ) &&
			is_same_host( $user->user_url )
		) {
			$webfinger['links'][] = array(
				'rel'  => 'http://webfinger.net/rel/profile-page',
				'href' => \esc_url( $user->user_url ),
				'type' => 'text/html',
			);
		}

		return \apply_filters( 'webfinger_user_data', $webfinger, $resource_uri, $user );
	}

	/**
	 * Generates the WebFinger base array for posts.
	 *
	 * @param array  $webfinger    The WebFinger data-array.
	 * @param string $resource_uri The resource param.
	 *
	 * @return array The enriched WebFinger data-array.
	 */
	public static function generate_post_data( $webfinger, $resource_uri ) {
		// Find matching post.
		$post_id = \url_to_postid( $resource_uri );

		// Check if there is a matching post-id.
		if ( ! $post_id ) {
			return $webfinger;
		}

		// Get post by id.
		$post = \get_post( $post_id );

		// Check if there is a matching post.
		if ( ! $post ) {
			return $webfinger;
		}

		$author = \get_user_by( 'id', $post->post_author );

		// Default webfinger array for posts.
		$webfinger = array(
			'subject' => \get_permalink( $post->ID ),
			'aliases' => \apply_filters(
				'webfinger_post_resource',
				array(
					\home_url( '?p=' . $post->ID ),
					\get_permalink( $post->ID ),
				),
				$post
			),
			'links'   => array(
				array(
					'rel'  => 'shortlink',
					'type' => 'text/html',
					'href' => \wp_get_shortlink( $post ),
				),
				array(
					'rel'  => 'canonical',
					'type' => 'text/html',
					'href' => \get_permalink( $post->ID ),
				),
				array(
					'rel'  => 'author',
					'type' => 'text/html',
					'href' => \get_author_posts_url( $author->ID, $author->nicename ),
				),
				array(
					'rel'  => 'alternate',
					'type' => 'application/rss+xml',
					'href' => \get_post_comments_feed_link( $post->ID, 'rss2' ),
				),
				array(
					'rel'  => 'alternate',
					'type' => 'application/atom+xml',
					'href' => \get_post_comments_feed_link( $post->ID, 'atom' ),
				),
			),
		);

		return \apply_filters( 'webfinger_post_data', $webfinger, $resource_uri, $post );
	}

	/**
	 * Filters the WebFinger array by request params like "rel".
	 *
	 * @link http://tools.ietf.org/html/rfc7033#section-4.3
	 *
	 * @param array $webfinger The WebFinger data-array.
	 *
	 * @return array The filtered WebFinger data-array.
	 */
	public static function filter_by_rel( $webfinger ) {
		// Check if WebFinger is empty or if "rel" is set or if array has any "links".
		if (
			empty( $webfinger ) ||
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a public WebFinger endpoint.
			! \array_key_exists( 'rel', $_GET ) ||
			! isset( $webfinger['links'] )
		) {
			return $webfinger;
		}

		// Explode the query-string by hand because PHP does not support multiple queries with the same name.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- We sanitize below.
		$query = isset( $_SERVER['QUERY_STRING'] ) ? \explode( '&', $_SERVER['QUERY_STRING'] ) : array();
		$rels  = array();

		foreach ( $query as $param ) {
			$param = \explode( '=', $param );

			// Check if query-string is valid and if it is a 'rel'.
			if (
				isset( $param[0], $param[1] ) &&
				'rel' === $param[0] &&
				! empty( $param[1] )
			) {
				$rels[] = \sanitize_text_field( \wp_unslash( \urldecode( \trim( $param[1] ) ) ) );
			}
		}

		// Check if there is something to filter.
		if ( empty( $rels ) ) {
			return $webfinger;
		}

		// Filter WebFinger-array.
		$links = array();
		foreach ( $webfinger['links'] as $link ) {
			if ( \in_array( $link['rel'], $rels, true ) ) {
				$links[] = $link;
			}
		}
		$webfinger['links'] = $links;

		// Return only "links" with the matching rel-values.
		return $webfinger;
	}
}
