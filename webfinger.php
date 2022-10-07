<?php
/**
 * Plugin Name: WebFinger
 * Plugin URI: https://github.com/pfefferle/wordpress-webfinger
 * Description: WebFinger for WordPress
 * Version: 3.2.5
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

namespace Webfinger;

defined( 'WEBFINGER_LEGACY' ) || define( 'WEBFINGER_LEGACY', false );

/**
 * Initialize plugin.
 */
function init() {
	// list of various public helper functions
	require_once( dirname( __FILE__ ) . '/includes/functions.php' );
	require_once( dirname( __FILE__ ) . '/includes/deprecated.php' );

	require_once( dirname( __FILE__ ) . '/includes/class-webfinger.php' );
	Webfinger::init();

	require_once( dirname( __FILE__ ) . '/includes/class-admin.php' );
	Admin::init();

	// add legacy WebFinger class
	if ( WEBFINGER_LEGACY && ! class_exists( '\WebFingerLegacy_Plugin' ) ) {
		require_once( dirname( __FILE__ ) . '/includes/class-legacy.php' );
		Legacy::init();
	}
}
add_action( 'plugins_loaded', '\Webfinger\init' );

/**
 * Flush rewrite rules.
 */
function flush_rewrite_rules() {
	require_once( dirname( __FILE__ ) . '/includes/class-webfinger.php' );
	Webfinger::generate_rewrite_rules();
	\flush_rewrite_rules();
}
register_activation_hook( __FILE__, '\Webfinger\flush_rewrite_rules' );
register_deactivation_hook( __FILE__, '\flush_rewrite_rules' );
