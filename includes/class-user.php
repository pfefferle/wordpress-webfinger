<?php
/**
 * User class file.
 *
 * @package Webfinger
 */

namespace Webfinger;

/**
 * User class.
 *
 * Handles user-related WebFinger functionality.
 *
 * @author Will Norris
 */
class User {
	/**
	 * Returns a User object by URI.
	 *
	 * @param string $uri The URI to search for.
	 *
	 * @return \WP_User|null The user object or null if not found.
	 *
	 * @uses apply_filters() Uses 'webfinger_user' to filter the user and 'webfinger_user_query' to add custom query-params.
	 */
	public static function get_user_by_uri( $uri ) {
		$uri = \urldecode( $uri );

		if ( ! $uri || ! is_same_host( $uri ) ) {
			return null;
		}

		// Default scheme is acct.
		$scheme = 'acct';
		$host   = $uri;

		// Try to extract the scheme and the host.
		if ( \preg_match( '/^([a-zA-Z][a-zA-Z0-9+.-]*):(.+)$/i', $uri, $match ) ) {
			$scheme = \strtolower( $match[1] );
			$host   = $match[2];
		}

		if ( ! $host ) {
			return null;
		}

		$args = self::get_user_query_args( $scheme, $host, $uri );

		if ( null === $args ) {
			return null;
		}

		$args = \apply_filters( 'webfinger_user_query', $args, $uri, $scheme );

		if ( empty( $args ) ) {
			return null;
		}

		$user_query = new \WP_User_Query( $args );
		$results    = $user_query->get_results();

		return ! empty( $results ) ? $results[0] : null;
	}

	/**
	 * Get user query arguments based on scheme.
	 *
	 * @param string $scheme The URI scheme.
	 * @param string $host   The host/identifier part.
	 * @param string $uri    The full URI.
	 *
	 * @return array|null Query arguments or null if user found/invalid.
	 */
	private static function get_user_query_args( $scheme, $host, $uri ) {
		switch ( $scheme ) {
			case 'http':
			case 'https':
				$author_id = \url_to_authorid( $uri );
				if ( $author_id ) {
					return array(
						'search'         => $author_id,
						'search_columns' => array( 'ID' ),
					);
				}
				return array(
					'search'         => $uri,
					'search_columns' => array( 'user_url' ),
				);

			case 'acct':
				$pos = \strrpos( $host, '@' );
				if ( false === $pos ) {
					return null;
				}
				$id = \sanitize_title( \substr( $host, 0, $pos ) );
				if ( ! $id ) {
					return null;
				}

				// First check for custom webfinger_resource meta.
				$meta_query = new \WP_User_Query(
					array(
						'meta_key'     => 'webfinger_resource', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'   => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'meta_compare' => '=',
						'number'       => 1,
					)
				);
				$results    = $meta_query->get_results();
				if ( ! empty( $results ) ) {
					// Return null to signal user was found (handled in caller).
					return array(
						'include' => array( $results[0]->ID ),
					);
				}

				return array(
					'search'         => $id,
					'search_columns' => array( 'user_nicename', 'user_login' ),
				);

			case 'mailto':
				$email = \sanitize_email( $host );
				if ( ! $email ) {
					return null;
				}
				return array(
					'search'         => $email,
					'search_columns' => array( 'user_email' ),
				);

			default:
				return array();
		}
	}

	/**
	 * Returns a users default user specific part of the WebFinger resource.
	 *
	 * @param mixed $id_or_name_or_object The username, ID or object.
	 *
	 * @return string|null The username or null if not found.
	 */
	public static function get_username( $id_or_name_or_object ) {
		$user = \get_user_by_various( $id_or_name_or_object );

		if ( ! $user ) {
			return null;
		}

		$custom_resource = \get_user_meta( $user->ID, 'webfinger_resource', true );

		return $custom_resource ?: $user->user_login;
	}

	/**
	 * Returns a users default WebFinger resource.
	 *
	 * @param mixed $id_or_name_or_object The username, ID or object.
	 * @param bool  $with_protocol        Whether to include the protocol prefix.
	 *
	 * @return string|null The resource or null if not found.
	 */
	public static function get_resource( $id_or_name_or_object, $with_protocol = true ) {
		$user = \get_user_by_various( $id_or_name_or_object );

		if ( ! $user ) {
			return \apply_filters( 'webfinger_user_resource', null, null );
		}

		$username = self::get_username( $user );
		$host     = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$resource = $username . '@' . $host;

		if ( $with_protocol ) {
			$resource = 'acct:' . $resource;
		}

		return \apply_filters( 'webfinger_user_resource', $resource, $user );
	}

	/**
	 * Returns all WebFinger "resources".
	 *
	 * @param mixed $id_or_name_or_object The username, ID or object.
	 *
	 * @return array The array of resources.
	 */
	public static function get_resources( $id_or_name_or_object ) {
		$user = \get_user_by_various( $id_or_name_or_object );

		if ( ! $user ) {
			return array();
		}

		$host      = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$resources = array(
			self::get_resource( $user ),
			\get_author_posts_url( $user->ID, $user->user_nicename ),
		);

		// Add user_login as alias if different from custom resource.
		$custom_resource = \get_user_meta( $user->ID, 'webfinger_resource', true );
		if ( $custom_resource && $custom_resource !== $user->user_login ) {
			$resources[] = 'acct:' . $user->user_login . '@' . $host;
		}

		if ( $user->user_email && is_same_host( $user->user_email ) ) {
			$resources[] = 'mailto:' . $user->user_email;
		}

		return \array_values( \array_unique( \apply_filters( 'webfinger_user_resources', $resources, $user ) ) );
	}
}
