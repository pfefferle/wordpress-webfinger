<?php

namespace Webfinger;

class Admin {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		add_action( 'show_user_profile', array( static::class, 'add_profile' ) );
	}

	public static function add_profile( $user ) {
		load_template( dirname( __FILE__ ) . '/../templates/profile-settings.php', true, array( 'user' => $user ) );
	}
}
