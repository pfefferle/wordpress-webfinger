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
		if ( ! isset( $wp->query_vars['well-known'] ) || 'webfinger' !== $wp->query_vars['well-known'] ) {
			return;
		}

		\header( 'Access-Control-Allow-Origin: *' );

		// Check if "resource" param exists.
		if ( empty( $wp->query_vars['resource'] ) ) {
			self::send_error( 400, 'missing "resource" parameter' );
		}

		$resource = $wp->query_vars['resource'];

		// Filter WebFinger array.
		$webfinger = \apply_filters( 'webfinger_data', array(), $resource );

		// Check if data exists.
		if ( empty( $webfinger ) ) {
			self::send_error( 404, \sprintf( 'no data for resource "%s" found', \esc_html( $resource ) ) );
		}

		\do_action( 'webfinger_render', $webfinger );

		exit;
	}

	/**
	 * Send an error response and exit.
	 *
	 * @param int    $status  HTTP status code.
	 * @param string $message Error message.
	 */
	private static function send_error( $status, $message ) {
		\status_header( $status );
		\header( 'Content-Type: text/plain; charset=' . \get_bloginfo( 'charset' ), true );
		echo \esc_html( $message );
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

		if ( ! $author ) {
			return $webfinger;
		}

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
					'href' => \get_author_posts_url( $author->ID, $author->user_nicename ),
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
		// Check if WebFinger is empty or has no links.
		if ( empty( $webfinger ) || ! isset( $webfinger['links'] ) ) {
			return $webfinger;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public WebFinger endpoint.
		if ( ! isset( $_GET['rel'] ) ) {
			return $webfinger;
		}

		$rels = self::get_rel_params();

		if ( empty( $rels ) ) {
			return $webfinger;
		}

		$webfinger['links'] = \array_values(
			\array_filter(
				$webfinger['links'],
				function ( $link ) use ( $rels ) {
					return isset( $link['rel'] ) && \in_array( $link['rel'], $rels, true );
				}
			)
		);

		return $webfinger;
	}

	/**
	 * Parse rel parameters from query string.
	 *
	 * PHP does not support multiple query params with the same name,
	 * so we parse the query string manually.
	 *
	 * @return array List of rel values.
	 */
	private static function get_rel_params() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- We sanitize below.
		$query_string = $_SERVER['QUERY_STRING'] ?? '';
		$rels         = array();

		foreach ( \explode( '&', $query_string ) as $param ) {
			$parts = \explode( '=', $param, 2 );

			if ( 2 === \count( $parts ) && 'rel' === $parts[0] && '' !== $parts[1] ) {
				$rels[] = \sanitize_text_field( \urldecode( $parts[1] ) );
			}
		}

		return $rels;
	}
}
