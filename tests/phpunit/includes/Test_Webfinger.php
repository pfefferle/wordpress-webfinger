<?php
/**
 * Tests for the Webfinger class.
 *
 * @package Webfinger
 */

use Webfinger\Webfinger;

/**
 * Test class for Webfinger.
 *
 * @coversDefaultClass \Webfinger\Webfinger
 */
class Test_Webfinger extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up test fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory The factory instance.
	 */
	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_login'    => 'webfingeruser',
				'user_email'    => 'webfingeruser@example.org',
				'user_nicename' => 'webfingeruser',
				'display_name'  => 'WebFinger User',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
				'post_title'  => 'Test Post',
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		if ( self::$post_id ) {
			\wp_delete_post( self::$post_id, true );
		}
		if ( self::$user_id ) {
			\wp_delete_user( self::$user_id );
		}
	}

	/**
	 * Test query_vars adds required variables.
	 *
	 * @covers ::query_vars
	 */
	public function test_query_vars_adds_required_vars() {
		$vars = Webfinger::query_vars( array() );

		$this->assertContains( 'well-known', $vars );
		$this->assertContains( 'resource', $vars );
		$this->assertContains( 'rel', $vars );
	}

	/**
	 * Test query_vars preserves existing variables.
	 *
	 * @covers ::query_vars
	 */
	public function test_query_vars_preserves_existing_vars() {
		$existing = array( 'existing_var' );
		$vars     = Webfinger::query_vars( $existing );

		$this->assertContains( 'existing_var', $vars );
		$this->assertContains( 'well-known', $vars );
	}

	/**
	 * Test generate_user_data returns user data for valid resource.
	 *
	 * @covers ::generate_user_data
	 */
	public function test_generate_user_data_returns_user_data() {
		$host     = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$resource = 'acct:webfingeruser@' . $host;

		$webfinger = Webfinger::generate_user_data( array(), $resource );

		$this->assertIsArray( $webfinger );
		$this->assertArrayHasKey( 'subject', $webfinger );
		$this->assertArrayHasKey( 'aliases', $webfinger );
		$this->assertArrayHasKey( 'links', $webfinger );
	}

	/**
	 * Test generate_user_data includes profile page link.
	 *
	 * @covers ::generate_user_data
	 */
	public function test_generate_user_data_includes_profile_link() {
		$host     = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$resource = 'acct:webfingeruser@' . $host;

		$webfinger   = Webfinger::generate_user_data( array(), $resource );
		$has_profile = false;
		$profile_rel = 'http://webfinger.net/rel/profile-page';

		foreach ( $webfinger['links'] as $link ) {
			if ( isset( $link['rel'] ) && $link['rel'] === $profile_rel ) {
				$has_profile = true;
				break;
			}
		}

		$this->assertTrue( $has_profile );
	}

	/**
	 * Test generate_user_data includes avatar link.
	 *
	 * @covers ::generate_user_data
	 */
	public function test_generate_user_data_includes_avatar_link() {
		$host     = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$resource = 'acct:webfingeruser@' . $host;

		$webfinger  = Webfinger::generate_user_data( array(), $resource );
		$has_avatar = false;
		$avatar_rel = 'http://webfinger.net/rel/avatar';

		foreach ( $webfinger['links'] as $link ) {
			if ( isset( $link['rel'] ) && $link['rel'] === $avatar_rel ) {
				$has_avatar = true;
				break;
			}
		}

		$this->assertTrue( $has_avatar );
	}

	/**
	 * Test generate_user_data returns empty array for invalid resource.
	 *
	 * @covers ::generate_user_data
	 */
	public function test_generate_user_data_returns_empty_for_invalid_resource() {
		$resource = 'acct:nonexistent@invalid-domain.com';

		$webfinger = Webfinger::generate_user_data( array(), $resource );

		$this->assertIsArray( $webfinger );
		$this->assertEmpty( $webfinger );
	}

	/**
	 * Test generate_post_data returns post data for valid resource.
	 *
	 * @covers ::generate_post_data
	 */
	public function test_generate_post_data_returns_post_data() {
		$resource = \get_permalink( self::$post_id );

		$webfinger = Webfinger::generate_post_data( array(), $resource );

		$this->assertIsArray( $webfinger );
		$this->assertArrayHasKey( 'subject', $webfinger );
		$this->assertArrayHasKey( 'aliases', $webfinger );
		$this->assertArrayHasKey( 'links', $webfinger );
	}

	/**
	 * Test generate_post_data includes shortlink.
	 *
	 * @covers ::generate_post_data
	 */
	public function test_generate_post_data_includes_shortlink() {
		$resource = \get_permalink( self::$post_id );

		$webfinger     = Webfinger::generate_post_data( array(), $resource );
		$has_shortlink = false;

		foreach ( $webfinger['links'] as $link ) {
			if ( isset( $link['rel'] ) && 'shortlink' === $link['rel'] ) {
				$has_shortlink = true;
				break;
			}
		}

		$this->assertTrue( $has_shortlink );
	}

	/**
	 * Test generate_post_data includes canonical link.
	 *
	 * @covers ::generate_post_data
	 */
	public function test_generate_post_data_includes_canonical() {
		$resource = \get_permalink( self::$post_id );

		$webfinger     = Webfinger::generate_post_data( array(), $resource );
		$has_canonical = false;

		foreach ( $webfinger['links'] as $link ) {
			if ( isset( $link['rel'] ) && 'canonical' === $link['rel'] ) {
				$has_canonical = true;
				break;
			}
		}

		$this->assertTrue( $has_canonical );
	}

	/**
	 * Test generate_post_data includes author link.
	 *
	 * @covers ::generate_post_data
	 */
	public function test_generate_post_data_includes_author() {
		$resource = \get_permalink( self::$post_id );

		$webfinger  = Webfinger::generate_post_data( array(), $resource );
		$has_author = false;

		foreach ( $webfinger['links'] as $link ) {
			if ( isset( $link['rel'] ) && 'author' === $link['rel'] ) {
				$has_author = true;
				break;
			}
		}

		$this->assertTrue( $has_author );
	}

	/**
	 * Test generate_post_data returns empty for invalid resource.
	 *
	 * @covers ::generate_post_data
	 */
	public function test_generate_post_data_returns_empty_for_invalid_resource() {
		$resource = 'https://example.com/invalid-post/';

		$webfinger = Webfinger::generate_post_data( array(), $resource );

		$this->assertIsArray( $webfinger );
		$this->assertEmpty( $webfinger );
	}

	/**
	 * Test filter_by_rel returns webfinger unchanged when no rel param.
	 *
	 * @covers ::filter_by_rel
	 */
	public function test_filter_by_rel_returns_unchanged_without_rel() {
		$webfinger = array(
			'subject' => 'acct:test@example.org',
			'links'   => array(
				array(
					'rel'  => 'self',
					'href' => 'https://example.org/test',
				),
			),
		);

		// Ensure no rel is set.
		unset( $_GET['rel'] );

		$result = Webfinger::filter_by_rel( $webfinger );

		$this->assertEquals( $webfinger, $result );
	}

	/**
	 * Test filter_by_rel returns empty webfinger unchanged.
	 *
	 * @covers ::filter_by_rel
	 */
	public function test_filter_by_rel_returns_empty_unchanged() {
		$webfinger = array();

		$result = Webfinger::filter_by_rel( $webfinger );

		$this->assertEmpty( $result );
	}
}
