<?php
/**
 * Tests for the deprecated pre-4.0.0 global class names.
 *
 * @package Webfinger
 */

/**
 * Test class for the backward-compatibility layer in includes/deprecated.php.
 */
class Test_Deprecated extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create a test user.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_login' => 'legacyuser',
			)
		);
	}

	/**
	 * Delete the test user.
	 */
	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user_id );
	}

	/**
	 * Webfinger_Admin must be a real alias of Webfinger\Admin, not an empty stub,
	 * and referencing it must trigger a deprecation notice.
	 *
	 * @expectedDeprecated Webfinger_Admin
	 */
	public function test_webfinger_admin_is_alias_of_replacement() {
		$this->assertTrue( class_exists( 'Webfinger_Admin' ) );

		$reflection = new ReflectionClass( 'Webfinger_Admin' );
		$this->assertSame( \Webfinger\Admin::class, $reflection->getName() );
	}

	/**
	 * Webfinger_Legacy must be a real alias of Webfinger\Legacy, not an empty stub,
	 * and referencing it must trigger a deprecation notice.
	 *
	 * @expectedDeprecated Webfinger_Legacy
	 */
	public function test_webfinger_legacy_is_alias_of_replacement() {
		$this->assertTrue( class_exists( 'Webfinger_Legacy' ) );

		$reflection = new ReflectionClass( 'Webfinger_Legacy' );
		$this->assertSame( \Webfinger\Legacy::class, $reflection->getName() );
	}

	/**
	 * The global Webfinger class must provide the full pre-4.0.0 static API.
	 *
	 * The ActivityPub plugin gates its own WebFinger handling on
	 * `class_exists( 'Webfinger' )`, and third parties called the static
	 * methods directly.
	 */
	public function test_webfinger_class_is_replacement() {
		$this->assertTrue( class_exists( 'Webfinger' ) );
		$this->assertTrue( is_a( 'Webfinger', \Webfinger\Webfinger::class, true ) );
		$this->assertTrue( is_callable( array( 'Webfinger', 'render_jrd' ) ) );
		$this->assertTrue( is_callable( array( 'Webfinger', 'get_user_resource' ) ) );
		$this->assertTrue( is_callable( array( 'Webfinger', 'get_user_resources' ) ) );
	}

	/**
	 * Webfinger::get_user_resource() moved to Webfinger\User::get_resource().
	 *
	 * @expectedDeprecated Webfinger::get_user_resource
	 */
	public function test_get_user_resource_wrapper() {
		$this->assertSame(
			\Webfinger\User::get_resource( self::$user_id ),
			\Webfinger::get_user_resource( self::$user_id )
		);
	}

	/**
	 * Webfinger::get_user_resources() moved to Webfinger\User::get_resources().
	 *
	 * @expectedDeprecated Webfinger::get_user_resources
	 */
	public function test_get_user_resources_wrapper() {
		$this->assertSame(
			\Webfinger\User::get_resources( self::$user_id ),
			\Webfinger::get_user_resources( self::$user_id )
		);
	}
}
