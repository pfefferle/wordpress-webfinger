<?php
/**
 * Health Check class file.
 *
 * @package Webfinger
 */

namespace Webfinger;

/**
 * Health Check class.
 *
 * Adds WebFinger-related checks to WordPress Site Health.
 */
class Health_Check {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'site_status_tests', array( static::class, 'add_tests' ) );
	}

	/**
	 * Add WebFinger tests to Site Health.
	 *
	 * @param array $tests The existing Site Health tests.
	 *
	 * @return array The modified tests array.
	 */
	public static function add_tests( $tests ) {
		$tests['direct']['webfinger_permalinks'] = array(
			'label' => \__( 'WebFinger Permalinks', 'webfinger' ),
			'test'  => array( static::class, 'test_permalinks' ),
		);

		$tests['direct']['webfinger_endpoint'] = array(
			'label' => \__( 'WebFinger Endpoint', 'webfinger' ),
			'test'  => array( static::class, 'test_webfinger_endpoint' ),
		);

		return $tests;
	}

	/**
	 * Test if pretty permalinks are enabled.
	 *
	 * @return array The test result.
	 */
	public static function test_permalinks() {
		global $wp_rewrite;

		$result = array(
			'label'       => \__( 'Pretty permalinks are enabled', 'webfinger' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'WebFinger', 'webfinger' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'Pretty permalinks are enabled, which is required for the WebFinger endpoint to work correctly.', 'webfinger' )
			),
			'actions'     => '',
			'test'        => 'webfinger_permalinks',
		);

		if ( ! $wp_rewrite->using_permalinks() ) {
			$result['status']         = 'critical';
			$result['label']          = \__( 'Pretty permalinks are not enabled', 'webfinger' );
			$result['badge']['color'] = 'red';
			$result['description']    = \sprintf(
				'<p>%s</p><p>%s</p>',
				\__( 'WebFinger requires pretty permalinks to be enabled. The .well-known/webfinger endpoint will not work with plain permalinks.', 'webfinger' ),
				\__( 'Without pretty permalinks, other servers and services will not be able to discover your users via WebFinger.', 'webfinger' )
			);
			$result['actions']        = \sprintf(
				'<p><a href="%s">%s</a></p>',
				\esc_url( \admin_url( 'options-permalink.php' ) ),
				\__( 'Go to Permalink Settings and select any option other than "Plain".', 'webfinger' )
			);
		}

		return $result;
	}

	/**
	 * Test if the WebFinger endpoint is accessible.
	 *
	 * @return array The test result.
	 */
	public static function test_webfinger_endpoint() {
		$result = array(
			'label'       => \__( 'WebFinger endpoint is accessible', 'webfinger' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => \__( 'WebFinger', 'webfinger' ),
				'color' => 'green',
			),
			'description' => \sprintf(
				'<p>%s</p>',
				\__( 'The WebFinger endpoint is properly configured and accessible.', 'webfinger' )
			),
			'actions'     => '',
			'test'        => 'webfinger_endpoint',
		);

		// Get a test user for the WebFinger request.
		$users = \get_users(
			array(
				'number'  => 1,
				'orderby' => 'registered',
				'order'   => 'ASC',
			)
		);

		if ( empty( $users ) ) {
			$result['status']         = 'recommended';
			$result['label']          = \__( 'WebFinger endpoint cannot be tested', 'webfinger' );
			$result['badge']['color'] = 'orange';
			$result['description']    = \sprintf(
				'<p>%s</p>',
				\__( 'No users found to test the WebFinger endpoint. Create a user to enable this test.', 'webfinger' )
			);

			return $result;
		}

		$user     = $users[0];
		$resource = User::get_resource( $user->ID );

		// Always use the rewritten URL to test if URL rewriting is working.
		$endpoint = \home_url( '/.well-known/webfinger' );
		$url      = \add_query_arg( 'resource', $resource, $endpoint );

		$response = \wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => false,
			)
		);

		if ( \is_wp_error( $response ) ) {
			$result['status']         = 'critical';
			$result['label']          = \__( 'WebFinger endpoint is not accessible', 'webfinger' );
			$result['badge']['color'] = 'red';
			$result['description']    = \sprintf(
				'<p>%s</p><p><strong>%s</strong> %s</p>',
				\__( 'The WebFinger endpoint could not be reached. This may prevent federation and discovery from working properly.', 'webfinger' ),
				\__( 'Error:', 'webfinger' ),
				\esc_html( $response->get_error_message() )
			);
			$result['actions']        = self::get_guidance_actions( 'connection_error' );

			return $result;
		}

		$status_code = \wp_remote_retrieve_response_code( $response );
		$body        = \wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			$result['status']         = 'critical';
			$result['label']          = \__( 'WebFinger endpoint returned an error', 'webfinger' );
			$result['badge']['color'] = 'red';
			$result['description']    = \sprintf(
				'<p>%s</p><p><strong>%s</strong> %d</p>',
				\__( 'The WebFinger endpoint returned an unexpected status code.', 'webfinger' ),
				\__( 'Status code:', 'webfinger' ),
				$status_code
			);

			if ( 404 === $status_code ) {
				$result['actions'] = self::get_guidance_actions( 'not_found' );
			} else {
				$result['actions'] = self::get_guidance_actions( 'server_error' );
			}

			return $result;
		}

		// Check if the response is valid JSON.
		$data = \json_decode( $body, true );

		if ( null === $data || ! isset( $data['subject'] ) ) {
			$result['status']         = 'recommended';
			$result['label']          = \__( 'WebFinger endpoint returned invalid data', 'webfinger' );
			$result['badge']['color'] = 'orange';
			$result['description']    = \sprintf(
				'<p>%s</p>',
				\__( 'The WebFinger endpoint is accessible but returned invalid JSON data.', 'webfinger' )
			);
			$result['actions']        = self::get_guidance_actions( 'invalid_response' );

			return $result;
		}

		// Check content type header.
		$content_type = \wp_remote_retrieve_header( $response, 'content-type' );

		if ( false === \strpos( $content_type, 'application/jrd+json' ) ) {
			$result['status']         = 'recommended';
			$result['label']          = \__( 'WebFinger endpoint has incorrect content type', 'webfinger' );
			$result['badge']['color'] = 'orange';
			$result['description']    = \sprintf(
				'<p>%s</p><p><strong>%s</strong> %s</p><p><strong>%s</strong> application/jrd+json</p>',
				\__( 'The WebFinger endpoint is working but returns an incorrect content type header.', 'webfinger' ),
				\__( 'Current:', 'webfinger' ),
				\esc_html( $content_type ),
				\__( 'Expected:', 'webfinger' )
			);

			return $result;
		}

		// All checks passed.
		$result['description'] = \sprintf(
			'<p>%s</p><p><code>%s</code></p>',
			\__( 'The WebFinger endpoint is properly configured and returning valid responses.', 'webfinger' ),
			\esc_html( $endpoint )
		);

		return $result;
	}

	/**
	 * Get guidance actions based on the error type.
	 *
	 * @param string $error_type The type of error encountered.
	 *
	 * @return string HTML string with guidance actions.
	 */
	private static function get_guidance_actions( $error_type ) {
		$actions = '<h4>' . \__( 'Troubleshooting Steps:', 'webfinger' ) . '</h4><ol>';

		switch ( $error_type ) {
			case 'not_found':
				$actions .= '<li>' . \__( 'Go to Settings → Permalinks and click "Save Changes" to flush rewrite rules.', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Ensure your web server supports URL rewriting (mod_rewrite for Apache, try_files for Nginx).', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Check if your .htaccess file (Apache) or server configuration (Nginx) is properly configured.', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \sprintf(
					/* translators: %s: .well-known/webfinger URL */
					\__( 'Verify that requests to %s are not blocked by security plugins or server rules.', 'webfinger' ),
					'<code>/.well-known/webfinger</code>'
				) . '</li>';
				break;

			case 'connection_error':
				$actions .= '<li>' . \__( 'Check if your site is accessible from the internet (not localhost or behind a firewall).', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Verify your SSL certificate is valid if using HTTPS.', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Check if your hosting provider allows loopback connections.', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Temporarily disable security plugins to check for conflicts.', 'webfinger' ) . '</li>';
				break;

			case 'server_error':
				$actions .= '<li>' . \__( 'Check your server error logs for more details.', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Temporarily disable other plugins to check for conflicts.', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Verify PHP has enough memory allocated (at least 128MB recommended).', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Contact your hosting provider if the issue persists.', 'webfinger' ) . '</li>';
				break;

			case 'invalid_response':
				$actions .= '<li>' . \__( 'Check if another plugin is intercepting the WebFinger request.', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Temporarily disable caching plugins and try again.', 'webfinger' ) . '</li>';
				$actions .= '<li>' . \__( 'Verify the WebFinger plugin is up to date.', 'webfinger' ) . '</li>';
				break;
		}

		$actions .= '</ol>';

		// Add link to test endpoint manually.
		$endpoint = \home_url( '/.well-known/webfinger' );
		$actions .= '<p>' . \sprintf(
			/* translators: %s: WebFinger endpoint URL */
			\__( 'Test the endpoint manually: %s', 'webfinger' ),
			'<a href="' . \esc_url( $endpoint . '?resource=acct:test@example.com' ) . '" target="_blank" rel="noopener noreferrer">' . \esc_html( $endpoint ) . '</a>'
		) . '</p>';

		return $actions;
	}
}
