<?php
/**
 * Autoloader for WebFinger plugin classes.
 *
 * @package Webfinger
 */

namespace Webfinger;

/**
 * Autoloader class.
 *
 * Handles autoloading of plugin classes following WordPress naming conventions.
 */
class Autoloader {
	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Path to the classes directory.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Constructor.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $path   The path to the classes directory.
	 */
	public function __construct( $prefix, $path ) {
		$this->prefix = $prefix;
		$this->path   = $path;
	}

	/**
	 * Register the autoloader.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $path   The path to the classes directory.
	 */
	public static function register_path( $prefix, $path ) {
		$autoloader = new self( $prefix, $path );
		\spl_autoload_register( array( $autoloader, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class_name The fully-qualified class name.
	 *
	 * @return bool True if the file was loaded, false otherwise.
	 */
	public function load( $class_name ) {
		// Check if the class is in our namespace.
		if ( 0 !== \strpos( $class_name, $this->prefix ) ) {
			return false;
		}

		// Remove the namespace prefix.
		$relative_class = \substr( $class_name, \strlen( $this->prefix ) );

		// Convert namespace separators to directory separators.
		$relative_class = \str_replace( '\\', '/', $relative_class );

		// Convert to lowercase and replace underscores with hyphens.
		$relative_class = \strtolower( $relative_class );
		$relative_class = \str_replace( '_', '-', $relative_class );

		// Get the class name without the path.
		$parts      = \explode( '/', $relative_class );
		$class_name = \array_pop( $parts );
		$sub_path   = \implode( '/', $parts );

		// Build the file path with different prefixes.
		$prefixes = array( 'class', 'interface', 'trait' );

		foreach ( $prefixes as $prefix ) {
			$file = $this->path . $sub_path . '/' . $prefix . '-' . $class_name . '.php';
			$file = \str_replace( '//', '/', $file );

			if ( \file_exists( $file ) ) {
				require_once $file;
				return true;
			}
		}

		return false;
	}
}
