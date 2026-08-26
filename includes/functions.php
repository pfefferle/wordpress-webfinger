<?php
/**
 * Helper functions for WebFinger.
 *
 * @package Webfinger
 */

if ( ! function_exists( 'url_to_authorid' ) ) :
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

		// Check if url has the same host.
		if ( \wp_parse_url( \site_url(), \PHP_URL_HOST ) !== \wp_parse_url( $url, \PHP_URL_HOST ) ) {
			return 0;
		}

		// First, check to see if there is a 'author=N' to match against.
		if ( \preg_match( '/[?&]author=(\d+)/i', $url, $values ) ) {
			$id = \absint( $values[1] );
			if ( $id ) {
				return $id;
			}
		}

		// Check to see if we are using rewrite rules.
		$rewrite = $wp_rewrite->wp_rewrite_rules();

		// Not using rewrite rules, and 'author=N' method failed, so we're out of options.
		if ( empty( $rewrite ) ) {
			return 0;
		}

		// Generate rewrite rule for the author url.
		$author_rewrite = $wp_rewrite->get_author_permastruct();
		$author_regexp  = \str_replace( '%author%', '', $author_rewrite );

		// Match the rewrite rule with the passed url.
		if ( \preg_match( '/https?:\/\/(.+)' . \preg_quote( $author_regexp, '/' ) . '([^\/]+)/i', $url, $match ) ) {
			$user = \get_user_by( 'slug', $match[2] );
			if ( $user ) {
				return $user->ID;
			}
		}

		return 0;
	}
endif;

if ( ! function_exists( 'get_user_by_various' ) ) :
	/**
	 * Convenience method to get user data by ID, username, object or from current user.
	 *
	 * @param mixed $id_or_name_or_object The username, ID or object. If not provided, the current user will be used.
	 *
	 * @return \WP_User|false WP_User on success, false on failure.
	 *
	 * @author Will Norris
	 *
	 * @see get_user_by_various() # DiSo OpenID-Plugin
	 */
	function get_user_by_various( $id_or_name_or_object = null ) {
		if ( null === $id_or_name_or_object ) {
			$user = \wp_get_current_user();
			return $user->exists() ? $user : false;
		}

		if ( $id_or_name_or_object instanceof \WP_User ) {
			return $id_or_name_or_object;
		}

		if ( \is_numeric( $id_or_name_or_object ) ) {
			return \get_user_by( 'id', $id_or_name_or_object );
		}

		return \get_user_by( 'login', $id_or_name_or_object );
	}
endif;

/**
 * Build WebFinger endpoint.
 *
 * @return string The WebFinger URL.
 */
function get_webfinger_endpoint() {
	global $wp_rewrite;

	if ( $wp_rewrite->using_permalinks() ) {
		return \home_url( '/.well-known/webfinger' );
	}

	return \add_query_arg( 'well-known', 'webfinger', \home_url( '/' ) );
}

/**
 * Returns all WebFinger "resources".
 *
 * @param mixed $id_or_name_or_object The username, ID or object.
 * @param bool  $with_protocol        Whether to include the protocol prefix.
 *
 * @return string The user-resource.
 */
function get_webfinger_resource( $id_or_name_or_object, $with_protocol = true ) {
	return \Webfinger\User::get_resource( $id_or_name_or_object, $with_protocol );
}

/**
 * Returns a WebFinger "username" (the part before the "@").
 *
 * @param mixed $id_or_name_or_object The username, ID or object.
 *
 * @return string The username.
 */
function get_webfinger_username( $id_or_name_or_object ) {
	return \Webfinger\User::get_username( $id_or_name_or_object );
}

/**
 * Check if a passed URI has the same domain as the blog.
 *
 * @param string $uri The URI to check.
 *
 * @return bool True if the URI has the same host as the blog, false otherwise.
 */
function is_same_host( $uri ) {
	if ( ! $uri || ! \is_string( $uri ) ) {
		return false;
	}

	$blog_host = \wp_parse_url( \home_url(), \PHP_URL_HOST );
	$host      = \wp_parse_url( $uri, \PHP_URL_HOST );

	/*
	 * Schemes without an authority component -- `acct:` and `mailto:` -- carry
	 * the host in the path, so `wp_parse_url()` reports no host for them. Fall
	 * back to the part after the last "@", which also covers bare e-mail
	 * addresses and bare `user@host` identifiers.
	 */
	if ( ! $host && \str_contains( $uri, '@' ) ) {
		$host = \substr( \strrchr( $uri, '@' ), 1 );
	}

	return (bool) $host && $host === $blog_host;
}
