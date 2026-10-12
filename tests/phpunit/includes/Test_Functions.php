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
	 * @param string $uri      The URI to check, with `%HOST%` (`%UPPERHOST%`) standing in for the (uppercased) blog host.
	 * @param bool   $expected The expected result.
	 */
	public function test_is_same_host( $uri, $expected ) {
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		$uri  = \str_replace( array( '%HOST%', '%UPPERHOST%' ), array( $host, \strtoupper( $host ) ), $uri );

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
			'uppercase same host URL'        => array( 'https://%UPPERHOST%/test-path', true ),
			'uppercase same host acct: URI'  => array( 'acct:user@%UPPERHOST%', true ),
			'not a URI'                      => array( 'not a uri', false ),
			'empty string'                   => array( '', false ),
		);
	}

	/**
	 * Test is_same_host on a blog with an internationalized domain name.
	 *
	 * @covers ::is_same_host
	 *
	 * @dataProvider data_is_same_host_idn
	 *
	 * @param string $home     The home URL of the blog.
	 * @param string $uri      The URI to check.
	 * @param bool   $expected The expected result.
	 */
	public function test_is_same_host_idn( $home, $uri, $expected ) {
		\add_filter(
			'option_home',
			function () use ( $home ) {
				return $home;
			}
		);

		$this->assertSame( $expected, \is_same_host( $uri ) );
	}

	/**
	 * Data provider for test_is_same_host_idn.
	 *
	 * @return array[] Test data.
	 */
	public function data_is_same_host_idn() {
		return array(
			'U-label home, A-label acct: URI'   => array( 'https://café.example', 'acct:user@xn--caf-dma.example', true ),
			'U-label home, U-label acct: URI'   => array( 'https://café.example', 'acct:user@café.example', true ),
			'A-label home, U-label acct: URI'   => array( 'https://xn--caf-dma.example', 'acct:user@café.example', true ),
			'A-label home, uppercase A-label'   => array( 'https://xn--caf-dma.example', 'https://XN--CAF-DMA.EXAMPLE/', true ),
			'A-label home, IDN TLD acct: URI'   => array( 'https://example.xn--fiqs8s', 'acct:user@example.中国', true ),
			'U-label home, ASCII lookalike URI' => array( 'https://café.example', 'acct:user@cafe.example', false ),
		);
	}

	/**
	 * Test normalize_webfinger_host.
	 *
	 * @covers ::normalize_webfinger_host
	 *
	 * @dataProvider data_normalize_webfinger_host
	 *
	 * @param string $host     The host to normalize.
	 * @param string $expected The expected result.
	 */
	public function test_normalize_webfinger_host( $host, $expected ) {
		$this->assertSame( $expected, \normalize_webfinger_host( $host ) );
	}

	/**
	 * Data provider for test_normalize_webfinger_host.
	 *
	 * @return array[] Test data.
	 */
	public function data_normalize_webfinger_host() {
		return array(
			'ASCII host'          => array( 'example.org', 'example.org' ),
			'uppercase host'      => array( 'Example.ORG', 'example.org' ),
			'U-label'             => array( 'café.example', 'xn--caf-dma.example' ),
			'uppercase A-label'   => array( 'XN--CAF-DMA.example', 'xn--caf-dma.example' ),
			'IDN TLD'             => array( 'example.中国', 'example.xn--fiqs8s' ),
			'Thai U-label'        => array( 'ตัวอย่าง.example', 'xn--72c1a1bt4awk9o.example' ),
			'invalid, label > 63' => array( \str_repeat( 'A', 64 ) . '.example', \str_repeat( 'a', 64 ) . '.example' ),
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
