<?php

namespace Webfinger;

use WP_User_Query;

/**
 * User
 *
 * @author Will Norris
 */
class User {
	/**
	 * Returns a Userobject.
	 *
	 * @param string $uri
	 *
	 * @return WP_User
	 *
	 * @uses apply_filters() uses 'webfinger_user' to filter the
	 *       user and 'webfinger_user_query' to add custom query-params
	 */
	public static function get_user_by_uri( $uri ) {
		$uri    = urldecode( $uri );
		$match  = array();
		$scheme = 'acct';
		$host   = $uri;

		if ( ! is_same_host( $uri ) ) {
			return;
		}

		// try to extract the scheme and the host
		if ( preg_match( '/^([a-zA-Z^:]+):(.*)$/i', $uri, $match ) ) {
			// extract the scheme
			$scheme = sanitize_key( $match[1] );
			// extract the "host"
			$host = $match[2];
		}

		if ( ! $scheme || ! $host || ! $uri ) {
			return;
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
				$id = substr( $host, 0, strrpos( $host, '@' ) );
				$id = sanitize_title( $id );

				if ( ! $id ) {
					return;
				}

				// First check for custom webfinger_resource meta
				$meta_args = array(
					'meta_key'     => 'webfinger_resource',
					'meta_value'   => $id,
					'meta_compare' => '=',
				);

				$meta_query = new WP_User_Query( $meta_args );
				if ( ! empty( $meta_query->get_results() ) ) {
					return $meta_query->results[0];
				}

				// Fall back to searching user_nicename and user_login
				$args = array(
					'search' => $id,
					'search_columns' => array(
						'user_nicename',
						'user_login',
					),
					'meta_compare' => '=',
				);

				break;
			case 'mailto': // check mailto scheme
				$email = sanitize_email( $host );

				if ( ! $email ) {
					return;
				}

				$args = array(
					'search' => $email,
					'search_columns' => array( 'user_email' ),
					'meta_compare' => '=',
				);
				break;
			case 'xmpp': // check xmpp/jabber schemes
			case 'urn:xmpp':
				_deprecated_function( 'xmpp:user@host.tld', '4.0.0' );
				$args = array(
					'meta_key' => 'jabber',
					'meta_value' => $host,
					'meta_compare' => '=',
				);
				break;
			case 'ymsgr': // check Yahoo messenger schemes
				_deprecated_function( 'ymsgr:user@host.tld', '4.0.0' );
				$args = array(
					'meta_key' => 'yim',
					'meta_value' => $host,
					'meta_compare' => '=',
				);
				break;
			case 'aim': // check AOL messenger schemes
				_deprecated_function( 'aim:user@host.tld', '4.0.0' );
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
		if ( ! empty( $user_query->get_results() ) ) {
			$user = $user_query->results[0];
		} else {
			$user = null;
		}

		return $user;
	}

	/**
	 * Returns a users default user specific part of the WebFinger resource.
	 *
	 * @param mixed $id_or_name_or_object
	 *
	 * @return string|null
	 */
	public static function get_username( $id_or_name_or_object ) {
		$user = get_user_by_various( $id_or_name_or_object );

		if ( ! $user ) {
			return null;
		}

		$resource = $user->user_login;

		$custom_resource = get_user_meta( $user->ID, 'webfinger_resource', true );

		if ( $custom_resource ) {
			$resource = $custom_resource;
		}

		return $resource;
	}

	/**
	 * Returns a users default WebFinger resource.
	 *
	 * @param mixed $id_or_name_or_object
	 *
	 * @return string|null
	 */
	public static function get_resource( $id_or_name_or_object, $with_protocol = true ) {
		$user = get_user_by_various( $id_or_name_or_object );
		$resource = null;

		if ( ! $user ) {
			return apply_filters( 'webfinger_user_resource', $resource, $user );
		}

		$resource = $user->user_login;

		$custom_resource = get_user_meta( $user->ID, 'webfinger_resource', true );

		if ( $custom_resource ) {
			$resource = $custom_resource;
		}

		$resource = $resource . '@' . parse_url( home_url(), PHP_URL_HOST );

		if ( $with_protocol ) {
			$resource = 'acct:' . $resource;
		}

		return apply_filters( 'webfinger_user_resource', $resource, $user );
	}

	/**
	 * Returns all WebFinger "resources".
	 *
	 * @param mixed $id_or_name_or_object
	 *
	 * @return array
	 */
	public static function get_resources( $id_or_name_or_object ) {
		$user = get_user_by_various( $id_or_name_or_object );

		if ( ! $user ) {
			return array();
		}

		// generate account idenitfier (acct: uri)
		$resources[] = self::get_resource( $user );
		$resources[] = 'acct:' . $user->user_login . '@' . parse_url( home_url(), PHP_URL_HOST );
		$resources[] = get_author_posts_url( $user->ID, $user->user_nicename );

		if ( $user->user_email && is_same_host( $user->user_email ) ) {
			$resources[] = 'mailto:' . $user->user_email;
		}

		$xmpp = get_user_meta( $user->ID, 'jabber', true );
		if ( $xmpp && is_same_host( $xmpp ) ) {
			$resources[] = 'xmpp:' . $xmpp;
		}

		return array_values( array_unique( apply_filters( 'webfinger_user_resources', $resources, $user ) ) );
	}
}
