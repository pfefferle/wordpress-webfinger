<?php
/**
 * Plugin Name: WebFinger
 * Plugin URI: https://github.com/pfefferle/wordpress-webfinger
 * Description: WebFinger for WordPress
 * Version: 3.2.7
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

defined( 'WEBFINGER_LEGACY' ) || define( 'WEBFINGER_LEGACY', false );

/**
 * Initialize plugin
 */
function webfinger_init() {
	// list of various public helper functions
	require_once( dirname( __FILE__ ) . '/includes/functions.php' );

	require_once( dirname( __FILE__ ) . '/includes/class-webfinger.php' );

	add_action( 'query_vars', array( 'Webfinger', 'query_vars' ) );
	add_action( 'parse_request', array( 'Webfinger', 'parse_request' ) );

	add_action( 'init', array( 'Webfinger', 'generate_rewrite_rules' ) );

	add_filter( 'webfinger_data', array( 'Webfinger', 'generate_user_data' ), 10, 3 );
	add_filter( 'webfinger_data', array( 'Webfinger', 'generate_post_data' ), 10, 3 );
	add_filter( 'webfinger_data', array( 'Webfinger', 'filter_by_rel' ), 99, 1 );

	// default output
	add_action( 'webfinger_render', array( 'Webfinger', 'render_jrd' ) );

	// add legacy WebFinger class
	if ( WEBFINGER_LEGACY && ! class_exists( 'WebFingerLegacy_Plugin' ) ) {
		require_once( dirname( __FILE__ ) . '/includes/class-webfinger-legacy.php' );

		add_action( 'query_vars', array( 'Webfinger_Legacy', 'query_vars' ) );
		add_filter( 'host_meta', array( 'Webfinger_Legacy', 'host_meta_discovery' ) );

		// host-meta recource
		add_action( 'host_meta_render', array( 'Webfinger_Legacy', 'render_host_meta' ), -1, 3 );

		// XRD output
		add_action( 'webfinger_render', array( 'Webfinger_Legacy', 'render_xrd' ), 5 );

		// support plugins pre 3.0.0
		add_filter( 'webfinger_user_data', array( 'Webfinger_Legacy', 'legacy_filter' ), 10, 3 );
	}
}
add_action( 'plugins_loaded', 'webfinger_init' );

/**
 * Flush rewrite rules
 */
function webfinger_flush_rewrite_rules() {
	require_once( dirname( __FILE__ ) . '/includes/class-webfinger.php' );
	Webfinger::generate_rewrite_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'webfinger_flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
