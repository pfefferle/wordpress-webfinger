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
	 * Host used for the `mailto:` tests.
	 *
	 * The default test host (`localhost`) has no dot, so WordPress rejects it
	 * as an e-mail domain. Force a real domain instead.
	 *
	 * @var string
	 */
	const HOME_HOST = 'example.org';

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
	 * Test user ID for a user whose e-mail is on the blog host.
	 *
	 * @var int
	 */
	protected static $local_email_user_id;

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

		self::$local_email_user_id = $factory->user->create(
			array(
				'user_login'    => 'webfingermailuser',
				'user_email'    => 'hello@' . self::HOME_HOST,
				'user_nicename' => 'webfingermailuser',
				'display_name'  => 'WebFinger Mail User',
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
		if ( self::$local_email_user_id ) {
			\wp_delete_user( self::$local_email_user_id );
		}
	}

	/**
	 * Pin `home_url()` and `site_url()` to self::HOME_HOST for the current test.
	 *
	 * WP_UnitTestCase restores the hooks after every test.
	 */
	private function force_home_host() {
		$url = 'http://' . self::HOME_HOST;

		\add_filter(
			'option_home',
			function () use ( $url ) {
				return $url;
			}
		);
		\add_filter(
			'option_siteurl',
			function () use ( $url ) {
				return $url;
			}
		);
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

	/**
	 * Test a WP_Error returned by a `webfinger_data` filter is discarded.
	 *
	 * A WP_Error is not `empty()`, so without this it would be JSON-encoded
	 * and served as the JRD document with a 200 status.
	 * See https://github.com/pfefferle/wordpress-webfinger/issues/59.
	 *
	 * @covers ::discard_errors
	 */
	public function test_wp_error_from_filter_is_discarded() {
		$callback = function () {
			return new \WP_Error( 'third_party_error', 'Wrong scheme', array( 'status' => 404 ) );
		};

		\add_filter( 'webfinger_data', $callback, 1 );
		$webfinger = \apply_filters( 'webfinger_data', array(), 'mailto:nobody@different-domain.com' );
		\remove_filter( 'webfinger_data', $callback, 1 );

		$this->assertSame( array(), $webfinger );
	}

	/**
	 * Test discard_errors reduces anything that is not a data-array to an empty array.
	 *
	 * @covers ::discard_errors
	 *
	 * @dataProvider data_discard_errors
	 *
	 * @param mixed $webfinger The value handed to the filter.
	 * @param array $expected  The expected result.
	 */
	public function test_discard_errors( $webfinger, $expected ) {
		$this->assertSame( $expected, Webfinger::discard_errors( $webfinger ) );
	}

	/**
	 * Data provider for test_discard_errors.
	 *
	 * @return array[] Test data.
	 */
	public function data_discard_errors() {
		return array(
			'WP_Error'    => array( new \WP_Error( 'activitypub_wrong_scheme', 'Wrong scheme', array( 'status' => 404 ) ), array() ),
			'string'      => array( 'nonsense', array() ),
			'null'        => array( null, array() ),
			'false'       => array( false, array() ),
			'empty array' => array( array(), array() ),
			'data array'  => array(
				array( 'subject' => 'acct:user@example.org' ),
				array( 'subject' => 'acct:user@example.org' ),
			),
		);
	}

	/**
	 * Test a mailto: resource still resolves when an early filter errors out.
	 *
	 * This mirrors the ActivityPub plugin, which hooks `webfinger_data` at
	 * priority 1 and returns a WP_Error for schemes it does not know.
	 * See https://github.com/pfefferle/wordpress-webfinger/issues/59.
	 *
	 * @covers ::generate_user_data
	 * @covers ::discard_errors
	 */
	public function test_mailto_resource_resolves_despite_early_filter_error() {
		$this->force_home_host();

		$resource = 'mailto:hello@' . self::HOME_HOST;

		$callback = function () {
			return new \WP_Error( 'activitypub_wrong_scheme', 'Wrong scheme', array( 'status' => 404 ) );
		};

		\add_filter( 'webfinger_data', $callback, 1 );
		$webfinger = \apply_filters( 'webfinger_data', array(), $resource );
		\remove_filter( 'webfinger_data', $callback, 1 );

		$this->assertIsArray( $webfinger );
		$this->assertArrayNotHasKey( 'errors', $webfinger );
		$this->assertEquals( 'acct:webfingermailuser@' . self::HOME_HOST, $webfinger['subject'] );
		$this->assertContains( $resource, $webfinger['aliases'] );
	}
}
