<?php
/**
 * Tests for the functions.php helper functions.
 *
 * @package Webfinger
 */

/**
 * Test class for helper functions.
 */
class Test_Functions extends \WP_UnitTestCase {
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
				'user_login'    => 'functiontestuser',
				'user_email'    => 'functiontestuser@example.org',
				'user_nicename' => 'functiontestuser',
				'display_name'  => 'Function Test User',
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
	 * Test get_webfinger_endpoint returns valid URL.
	 *
	 * @covers ::get_webfinger_endpoint
	 */
	public function test_get_webfinger_endpoint_returns_url() {
		$endpoint = \get_webfinger_endpoint();

		$this->assertNotEmpty( $endpoint );
		$this->assertStringContainsString( 'webfinger', $endpoint );
	}

	/**
	 * Test get_webfinger_resource returns resource for user.
	 *
	 * @covers ::get_webfinger_resource
	 */
	public function test_get_webfinger_resource_returns_resource() {
		$resource = \get_webfinger_resource( self::$user_id );
		$host     = \wp_parse_url( \home_url(), \PHP_URL_HOST );

		$this->assertStringStartsWith( 'acct:', $resource );
		$this->assertStringContainsString( '@' . $host, $resource );
	}

	/**
	 * Test get_webfinger_resource without protocol.
	 *
	 * @covers ::get_webfinger_resource
	 */
	public function test_get_webfinger_resource_without_protocol() {
		$resource = \get_webfinger_resource( self::$user_id, false );

		$this->assertStringNotContainsString( 'acct:', $resource );
	}

	/**
	 * Test get_webfinger_username returns username.
	 *
	 * @covers ::get_webfinger_username
	 */
	public function test_get_webfinger_username_returns_username() {
		$username = \get_webfinger_username( self::$user_id );

		$this->assertEquals( 'functiontestuser', $username );
	}

	/**
	 * Test is_same_host.
	 *
	 * @covers ::is_same_host
	 *
	 * @dataProvider data_is_same_host
	 *
	 * @param string $uri      The URI to check, with `%HOST%` standing in for the blog host.
	 * @param bool   $expected The expected result.
	 */
	public function test_is_same_host( $uri, $expected ) {
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$uri  = \str_replace( '%HOST%', $host, $uri );

		$this->assertSame( $expected, \is_same_host( $uri ) );
	}

	/**
	 * Data provider for test_is_same_host.
	 *
	 * @return array[] Test data.
	 */
	public function data_is_same_host() {
		return array(
			'same host URL'                  => array( 'https://%HOST%/test-path', true ),
			'same host URL, no path'         => array( 'https://%HOST%', true ),
			'foreign URL'                    => array( 'https://different-domain.com/test-path', false ),
			'same host e-mail'               => array( 'user@%HOST%', true ),
			'foreign e-mail'                 => array( 'user@different-domain.com', false ),
			'same host acct: URI'            => array( 'acct:user@%HOST%', true ),
			'foreign acct: URI'              => array( 'acct:user@different-domain.com', false ),
			'same host mailto: URI'          => array( 'mailto:user@%HOST%', true ),
			'foreign mailto: URI'            => array( 'mailto:user@different-domain.com', false ),
			'blog host only in query'        => array( 'https://different-domain.com/?to=@%HOST%', false ),
			'mailto: with a subject'         => array( 'mailto:user@%HOST%?subject=hi', true ),
			'mailto: with a fragment'        => array( 'mailto:user@%HOST%#frag', true ),
			'foreign mailto: with a subject' => array( 'mailto:user@different-domain.com?subject=hi', false ),
			'not a URI'                      => array( 'not a uri', false ),
			'empty string'                   => array( '', false ),
		);
	}

	/**
	 * Test url_to_authorid returns user ID for author URL.
	 *
	 * @covers ::url_to_authorid
	 */
	public function test_url_to_authorid_returns_user_id() {
		$author_url = \get_author_posts_url( self::$user_id );
		$result     = \url_to_authorid( $author_url );

		$this->assertEquals( self::$user_id, $result );
	}

	/**
	 * Test url_to_authorid returns 0 for different host.
	 *
	 * @covers ::url_to_authorid
	 */
	public function test_url_to_authorid_returns_zero_for_different_host() {
		$result = \url_to_authorid( 'https://different-domain.com/author/test/' );

		$this->assertEquals( 0, $result );
	}

	/**
	 * Test url_to_authorid with author query parameter.
	 *
	 * @covers ::url_to_authorid
	 */
	public function test_url_to_authorid_with_query_param() {
		$url    = \add_query_arg( 'author', self::$user_id, \home_url() );
		$result = \url_to_authorid( $url );

		$this->assertEquals( self::$user_id, $result );
	}

	/**
	 * Test get_user_by_various with user ID.
	 *
	 * @covers ::get_user_by_various
	 */
	public function test_get_user_by_various_with_id() {
		$user = \get_user_by_various( self::$user_id );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( self::$user_id, $user->ID );
	}

	/**
	 * Test get_user_by_various with username.
	 *
	 * @covers ::get_user_by_various
	 */
	public function test_get_user_by_various_with_username() {
		$user = \get_user_by_various( 'functiontestuser' );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( self::$user_id, $user->ID );
	}

	/**
	 * Test get_user_by_various with user object.
	 *
	 * @covers ::get_user_by_various
	 */
	public function test_get_user_by_various_with_object() {
		$user_object = \get_user_by( 'id', self::$user_id );
		$user        = \get_user_by_various( $user_object );

		$this->assertSame( $user_object, $user );
	}

	/**
	 * Test get_user_by_various returns false for invalid user.
	 *
	 * @covers ::get_user_by_various
	 */
	public function test_get_user_by_various_returns_false_for_invalid() {
		$user = \get_user_by_various( 'nonexistent_user_xyz' );

		$this->assertFalse( $user );
	}
}
