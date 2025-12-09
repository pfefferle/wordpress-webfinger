<?php
/**
 * Tests for the User class.
 *
 * @package Webfinger
 */

use Webfinger\User;

/**
 * Test class for User.
 *
 * @coversDefaultClass \Webfinger\User
 */
class Test_User extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Set up test fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory The factory instance.
	 */
	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_login'    => 'testuser',
				'user_email'    => 'testuser@example.org',
				'user_nicename' => 'testuser',
				'display_name'  => 'Test User',
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		if ( self::$user_id ) {
			\wp_delete_user( self::$user_id );
		}
	}

	/**
	 * Test get_username returns the user_login by default.
	 *
	 * @covers ::get_username
	 */
	public function test_get_username_returns_user_login() {
		$username = User::get_username( self::$user_id );

		$this->assertEquals( 'testuser', $username );
	}

	/**
	 * Test get_username returns custom webfinger_resource meta if set.
	 *
	 * @covers ::get_username
	 */
	public function test_get_username_returns_custom_resource() {
		\update_user_meta( self::$user_id, 'webfinger_resource', 'customuser' );

		$username = User::get_username( self::$user_id );

		$this->assertEquals( 'customuser', $username );

		\delete_user_meta( self::$user_id, 'webfinger_resource' );
	}

	/**
	 * Test get_username returns null for invalid user.
	 *
	 * @covers ::get_username
	 */
	public function test_get_username_returns_null_for_invalid_user() {
		$username = User::get_username( 999999 );

		$this->assertNull( $username );
	}

	/**
	 * Test get_resource returns acct: URI.
	 *
	 * @covers ::get_resource
	 */
	public function test_get_resource_returns_acct_uri() {
		$resource = User::get_resource( self::$user_id );
		$host     = \wp_parse_url( \home_url(), \PHP_URL_HOST );

		$this->assertStringStartsWith( 'acct:', $resource );
		$this->assertStringContainsString( 'testuser@', $resource );
		$this->assertStringEndsWith( '@' . $host, $resource );
	}

	/**
	 * Test get_resource without protocol.
	 *
	 * @covers ::get_resource
	 */
	public function test_get_resource_without_protocol() {
		$resource = User::get_resource( self::$user_id, false );

		$this->assertStringNotContainsString( 'acct:', $resource );
		$this->assertStringContainsString( 'testuser@', $resource );
	}

	/**
	 * Test get_resources returns array of resources.
	 *
	 * @covers ::get_resources
	 */
	public function test_get_resources_returns_array() {
		$resources = User::get_resources( self::$user_id );

		$this->assertIsArray( $resources );
		$this->assertNotEmpty( $resources );
	}

	/**
	 * Test get_resources includes acct: URI.
	 *
	 * @covers ::get_resources
	 */
	public function test_get_resources_includes_acct_uri() {
		$resources = User::get_resources( self::$user_id );
		$has_acct  = false;

		foreach ( $resources as $resource ) {
			if ( \strpos( $resource, 'acct:' ) === 0 ) {
				$has_acct = true;
				break;
			}
		}

		$this->assertTrue( $has_acct );
	}

	/**
	 * Test get_resources includes author URL.
	 *
	 * @covers ::get_resources
	 */
	public function test_get_resources_includes_author_url() {
		$resources  = User::get_resources( self::$user_id );
		$author_url = \get_author_posts_url( self::$user_id );

		$this->assertContains( $author_url, $resources );
	}

	/**
	 * Test get_resources returns empty array for invalid user.
	 *
	 * @covers ::get_resources
	 */
	public function test_get_resources_returns_empty_for_invalid_user() {
		$resources = User::get_resources( 999999 );

		$this->assertIsArray( $resources );
		$this->assertEmpty( $resources );
	}

	/**
	 * Test get_user_by_uri with acct: scheme.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_with_acct_scheme() {
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$uri  = 'acct:testuser@' . $host;

		$user = User::get_user_by_uri( $uri );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( self::$user_id, $user->ID );
	}

	/**
	 * Test get_user_by_uri with invalid domain returns null.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_with_invalid_domain() {
		$uri = 'acct:testuser@invalid-domain.com';

		$user = User::get_user_by_uri( $uri );

		$this->assertNull( $user );
	}

	/**
	 * Test get_user_by_uri with author URL.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_with_author_url() {
		$author_url = \get_author_posts_url( self::$user_id );

		$user = User::get_user_by_uri( $author_url );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( self::$user_id, $user->ID );
	}

	/**
	 * Test get_user_by_uri with custom webfinger_resource meta.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_with_custom_resource() {
		\update_user_meta( self::$user_id, 'webfinger_resource', 'customidentifier' );

		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$uri  = 'acct:customidentifier@' . $host;

		$user = User::get_user_by_uri( $uri );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( self::$user_id, $user->ID );

		\delete_user_meta( self::$user_id, 'webfinger_resource' );
	}

	/**
	 * Test get_user_by_uri strips SQL wildcard percent character.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_strips_percent_wildcard() {
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );

		// Attempt SQL LIKE injection with % wildcard.
		$uri = 'acct:%@' . $host;

		$user = User::get_user_by_uri( $uri );

		// Should return null, not match any user via LIKE query.
		$this->assertNull( $user );
	}

	/**
	 * Test get_user_by_uri strips SQL wildcard asterisk character.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_strips_asterisk_wildcard() {
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );

		// Attempt injection with * wildcard.
		$uri = 'acct:*@' . $host;

		$user = User::get_user_by_uri( $uri );

		// Should return null, not match any user.
		$this->assertNull( $user );
	}

	/**
	 * Test get_user_by_uri with wildcard in username does not match all users.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_wildcard_does_not_match_all() {
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );

		// Attempt to match all users with wildcards.
		$uri = 'acct:test%user@' . $host;

		$user = User::get_user_by_uri( $uri );

		// Should return null because % is stripped, leaving "testuser" which should match.
		// Actually after stripping %, it becomes "testuser" which is a valid user.
		// Let's test with a pattern that won't match after stripping.
		$uri2 = 'acct:%test%@' . $host;

		$user2 = User::get_user_by_uri( $uri2 );

		// After stripping %, becomes "test" which is not our user.
		$this->assertNull( $user2 );
	}

	/**
	 * Test get_user_by_uri with malicious scheme is sanitized.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_sanitizes_scheme() {
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );

		// Attempt XSS in scheme (should be sanitized by esc_attr).
		$uri = '<script>alert(1)</script>:testuser@' . $host;

		$user = User::get_user_by_uri( $uri );

		// Should return null due to invalid scheme/host mismatch.
		$this->assertNull( $user );
	}

	/**
	 * Test get_user_by_uri with malicious host is sanitized.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_sanitizes_host() {
		// Attempt injection in host part.
		$uri = 'acct:testuser@<script>alert(1)</script>';

		$user = User::get_user_by_uri( $uri );

		// Should return null due to host not matching blog host.
		$this->assertNull( $user );
	}

	/**
	 * Test get_user_by_uri with empty URI after sanitization.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_empty_after_sanitization() {
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );

		// URI that becomes empty after stripping wildcards.
		$uri = 'acct:%%%@' . $host;

		$user = User::get_user_by_uri( $uri );

		// Should return null.
		$this->assertNull( $user );
	}

	/**
	 * Test get_user_by_uri with URL-encoded wildcards.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_strips_encoded_wildcards() {
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );

		// URL-encoded % is %25, after urldecode becomes %.
		$uri = 'acct:%25@' . $host;

		$user = User::get_user_by_uri( $uri );

		// Should return null after decoding and stripping.
		$this->assertNull( $user );
	}

	/**
	 * Test get_user_by_uri handles null/empty input gracefully.
	 *
	 * @covers ::get_user_by_uri
	 */
	public function test_get_user_by_uri_handles_empty_input() {
		$this->assertNull( User::get_user_by_uri( '' ) );
		$this->assertNull( User::get_user_by_uri( null ) );
	}
}
