<?php
/**
 * PHPUnit bootstrap file for WebFinger tests.
 *
 * @package Webfinger
 */

$_tests_dir = \getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = \rtrim( \sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = \getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	\define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! \file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . \PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require \dirname( \dirname( __DIR__ ) ) . '/webfinger.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Filter to prevent live HTTP requests during testing.
 *
 * @param bool   $allow   Whether to allow the request.
 * @param array  $args    HTTP request arguments.
 * @param string $url     The request URL.
 *
 * @return bool|WP_Error
 */
function _block_http_requests( $allow, $args, $url ) {
	return new \WP_Error(
		'http_request_blocked',
		\sprintf( 'HTTP request blocked: %s', $url )
	);
}
tests_add_filter( 'pre_http_request', '_block_http_requests', 10, 3 );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
