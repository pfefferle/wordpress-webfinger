<?php
/**
 * Plugin Name: WebFinger
 * Plugin URI: https://github.com/pfefferle/wordpress-webfinger
 * Description: WebFinger for WordPress
 * Version: 4.0.0
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * @package Webfinger
 */

namespace Webfinger;

\defined( 'WEBFINGER_LEGACY' ) || \define( 'WEBFINGER_LEGACY', false );

// Plugin related constants.
\define( 'WEBFINGER_PLUGIN_DIR', \plugin_dir_path( __FILE__ ) );
\define( 'WEBFINGER_PLUGIN_BASENAME', \plugin_basename( __FILE__ ) );
\define( 'WEBFINGER_PLUGIN_FILE', WEBFINGER_PLUGIN_DIR . \basename( __FILE__ ) );
\define( 'WEBFINGER_PLUGIN_URL', \plugin_dir_url( __FILE__ ) );

// Require the autoloader.
require_once WEBFINGER_PLUGIN_DIR . 'includes/class-autoloader.php';
Autoloader::register_path( 'Webfinger\\', WEBFINGER_PLUGIN_DIR . 'includes/' );

/**
 * Initialize plugin.
 */
function init() {
	// List of various public helper functions.
	require_once WEBFINGER_PLUGIN_DIR . 'includes/functions.php';
	require_once WEBFINGER_PLUGIN_DIR . 'includes/deprecated.php';

	Webfinger::init();
	Admin::init();

	// Add legacy WebFinger class.
	if ( WEBFINGER_LEGACY && ! \class_exists( '\WebFingerLegacy_Plugin' ) ) {
		Legacy::init();
	}
}
\add_action( 'plugins_loaded', '\Webfinger\init' );

/**
 * Flush rewrite rules.
 */
function flush_rewrite_rules() {
	Webfinger::generate_rewrite_rules();
	\flush_rewrite_rules();
}
\register_activation_hook( __FILE__, '\Webfinger\flush_rewrite_rules' );
\register_deactivation_hook( __FILE__, '\flush_rewrite_rules' );
