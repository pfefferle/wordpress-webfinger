<?php

namespace Webfinger;

use WP_User_Query;

class Admin {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		add_action( 'show_user_profile', array( static::class, 'add_profile' ) );

		// Add the save action to user's own profile editing screen update.
		add_action(
			'personal_options_update',
			array( static::class, 'update_user_meta' )
		);

		// Add the save action to user profile editing screen update.
		add_action(
			'edit_user_profile_update',
			array( static::class, 'update_user_meta' )
		);

		add_filter(
			'user_profile_update_errors',
			array( static::class, 'maybe_show_errors' ),
			10,
			3
		);
	}

	/**
	 * Load settings template
	 *
	 * @param stdClass $user The WordPress user
	 *
	 * @return void
	 */
	public static function add_profile( $user ) {
		load_template( dirname( __FILE__ ) . '/../templates/profile-settings.php', true, array( 'user' => $user ) );
	}

	/**
	 * The save action.
	 *
	 * @param int $user_id the ID of the current user.
	 *
	 * @return bool Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function update_user_meta( $user_id ) {
		// check that the current user have the capability to edit the $user_id
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( ! isset( $_POST ) || ! isset( $_POST['webfinger_resource'] ) ) {
			return false;
		}

		if ( empty( $_POST['webfinger_resource'] ) ) {
			delete_user_meta( $user_id, 'webfinger_resource' );
			return false;
		}

		$valid = self::is_valid_webfinger_resource( $_POST['webfinger_resource'], $user_id );

		if ( ! $valid ) {
			return;
		}

		$webfinger = sanitize_title( $_POST['webfinger_resource'], true );

		// create/update user meta for the $user_id
		update_user_meta(
			$user_id,
			'webfinger_resource',
			$webfinger
		);

		return $webfinger;
	}

	/**
	 * Check if an error should be shown
	 *
	 * @param WP_Error $errors WP_Error object (passed by reference).
	 * @param bool     $update Whether this is a user update.
	 * @param stdClass $user   User object (passed by reference).
	 *
	 * @return array Updated list of errors
	 */
	public static function maybe_show_errors( $errors, $update, $user ) {
		if ( ! isset( $_POST ) || ! isset( $_POST['webfinger_resource'] ) ) {
			return $errors;
		}

		$valid = self::is_valid_webfinger_resource( $_POST['webfinger_resource'], $user->ID );

		if ( ! $valid ) {
			$errors->add( 'webfinger_resource', __( 'WebFinger resource is already in use by a different user', 'webfinger' ) );
		}

		return $errors;
	}

	/**
	 * Check if the WebFinger resource is valid
	 *
	 * @param string $resource The WebFinger resource
	 * @param int    $user_id  The user ID
	 *
	 * @return boolean
	 */
	public static function is_valid_webfinger_resource( $resource, $user_id ) {
		$webfinger = sanitize_title( $resource, true );

		$args = array(
			'meta_key'     => 'webfinger_resource',
			'meta_value'   => $webfinger,
			'meta_compare' => '=',
			'exclude'      => $user_id,
		);

		// check if already exists
		$user_query = new WP_User_Query( $args );
		$results    = $user_query->get_results();

		return empty( $result );
	}
}
