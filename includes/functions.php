<?php
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
			$user = get_user_by( 'slug', $match[2] );
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
	 * @param mixed $id_or_name_or_object the username, ID or object. If not provided, the current user will be used.
	 *
	 * @return bool|object False on failure, User DB row object
	 *
	 * @author Will Norris
	 *
	 * @see get_user_by_various() # DiSo OpenID-Plugin
	 */
	function get_user_by_various( $id_or_name_or_object = null ) {
		if ( null === $id_or_name_or_object ) {
			$user = wp_get_current_user();
			if ( null == $user ) {
				return false;
			}
			return $user;
		} elseif ( is_object( $id_or_name_or_object ) ) {
			return $id_or_name_or_object;
		} elseif ( is_numeric( $id_or_name_or_object ) ) {
			return get_user_by( 'id', $id_or_name_or_object );
		} else {
			return get_user_by( 'login', $id_or_name_or_object );
		}
	}
endif;

/**
 * Build WebFinger endpoint
 *
 * @return string The WebFinger URL
 */
function get_webfinger_endpoint() {
	global $wp_rewrite;

	$permalink = $wp_rewrite->get_feed_permastruct();
	if ( '' != $permalink ) {
		$url = home_url( '/.well-known/webfinger' );
	} else {
		$url = add_query_arg( 'well-known', 'webfinger', home_url( '/' ) );
	}

	return $url;
}

/**
 * Returns all WebFinger "resources"
 *
 * @param mixed $id_or_name_or_object
 *
 * @return string The user-resource
 */
function get_webfinger_resource( $id_or_name_or_object, $with_protocol = true ) {
	return Webfinger::get_user_resource( $id_or_name_or_object, $with_protocol );
}
