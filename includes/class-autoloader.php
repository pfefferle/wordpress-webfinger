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
	 * File type prefixes to search.
	 */
	private const TYPE_PREFIXES = array( 'class', 'interface', 'trait' );

	/**
	 * Constructor.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $path   The path to the classes directory.
	 */
	public function __construct( string $prefix, string $path ) {
		$this->prefix = $prefix;
		$this->path   = \rtrim( $path, '/' ) . '/';
	}

	/**
	 * Register the autoloader.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $path   The path to the classes directory.
	 */
	public static function register_path( string $prefix, string $path ): void {
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
	public function load( string $class_name ): bool {
		// Check if the class is in our namespace.
		if ( 0 !== \strpos( $class_name, $this->prefix ) ) {
			return false;
		}

		// Remove the namespace prefix and convert to file path format.
		$relative_class = \substr( $class_name, \strlen( $this->prefix ) );
		$relative_class = \strtolower( \str_replace( array( '\\', '_' ), array( '/', '-' ), $relative_class ) );

		// Split into path and class name.
		$last_slash = \strrpos( $relative_class, '/' );
		if ( false !== $last_slash ) {
			$sub_path   = \substr( $relative_class, 0, $last_slash + 1 );
			$class_file = \substr( $relative_class, $last_slash + 1 );
		} else {
			$sub_path   = '';
			$class_file = $relative_class;
		}

		// Try each type prefix.
		foreach ( self::TYPE_PREFIXES as $type ) {
			$file = $this->path . $sub_path . $type . '-' . $class_file . '.php';

			if ( \file_exists( $file ) ) {
				require_once $file;
				return true;
			}
		}

		return false;
	}
}
