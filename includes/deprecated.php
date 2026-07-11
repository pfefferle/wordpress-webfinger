<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Deprecated classes for backwards compatibility.
 *
 * Version 4.0.0 moved all classes into the `Webfinger` namespace. The
 * pre-4.0.0 global class names are kept working here, so third-party
 * checks like `class_exists( 'Webfinger' )` (e.g. in the ActivityPub
 * plugin) and calls to the old static methods keep working.
 *
 * @package Webfinger
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/*
 * The aliases are registered lazily, so a deprecation notice is triggered
 * the moment a legacy class name is actually referenced.
 */
\spl_autoload_register(
	function ( $class_name ) {
		$deprecated = array(
			'Webfinger_Admin'  => \Webfinger\Admin::class,
			'Webfinger_Legacy' => \Webfinger\Legacy::class,
		);

		if ( ! isset( $deprecated[ $class_name ] ) ) {
			return;
		}

		// `_deprecated_class()` requires WordPress 6.4.
		if ( \function_exists( '_deprecated_class' ) ) {
			\_deprecated_class( $class_name, '4.0.0', $deprecated[ $class_name ] );
		}

		\class_alias( $deprecated[ $class_name ], $class_name );
	}
);

/**
 * Legacy Webfinger class (deprecated).
 *
 * A subclass instead of a plain alias, because two static methods moved
 * to `Webfinger\User` and would otherwise be lost.
 */
class Webfinger extends \Webfinger\Webfinger {

	/**
	 * Returns a users default WebFinger resource.
	 *
	 * @deprecated 4.0.0 Use `Webfinger\User::get_resource()` instead.
	 *
	 * @param mixed   $id_or_name_or_object User ID, login name or object.
	 * @param boolean $with_protocol        Whether to prepend the `acct:` scheme.
	 *
	 * @return string|null The users default WebFinger resource.
	 */
	public static function get_user_resource( $id_or_name_or_object, $with_protocol = true ) {
		\_deprecated_function( __METHOD__, '4.0.0', '\Webfinger\User::get_resource()' );

		return \Webfinger\User::get_resource( $id_or_name_or_object, $with_protocol );
	}

	/**
	 * Returns all WebFinger resources of a user.
	 *
	 * @deprecated 4.0.0 Use `Webfinger\User::get_resources()` instead.
	 *
	 * @param mixed $id_or_name_or_object User ID, login name or object.
	 *
	 * @return string[] The users WebFinger resources.
	 */
	public static function get_user_resources( $id_or_name_or_object ) {
		\_deprecated_function( __METHOD__, '4.0.0', '\Webfinger\User::get_resources()' );

		return \Webfinger\User::get_resources( $id_or_name_or_object );
	}
}
