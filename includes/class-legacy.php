<?php
/**
 * Legacy class file.
 *
 * @package Webfinger
 */

namespace Webfinger;

/**
 * WebFinger Legacy class.
 *
 * Provides backwards compatibility for older WebFinger implementations.
 *
 * @author Matthias Pfefferle
 */
class Legacy {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'query_vars', array( static::class, 'query_vars' ) );
		\add_filter( 'host_meta', array( static::class, 'host_meta_discovery' ) );

		// Host-meta resource.
		\add_action( 'host_meta_render', array( static::class, 'render_host_meta' ), -1, 3 );

		// XRD output.
		\add_action( 'webfinger_render', array( static::class, 'render_xrd' ), 5 );

		// Support plugins pre 3.0.0.
		\add_filter( 'webfinger_user_data', array( static::class, 'legacy_filter' ), 10, 3 );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars The query vars.
	 *
	 * @return array The modified query vars.
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'format';
		$vars[] = 'resource';
		$vars[] = 'rel';

		return $vars;
	}

	/**
	 * Render the XRD representation of the WordPress resource.
	 *
	 * @param array $webfinger The WordPress data-array.
	 */
	public static function render_xrd( $webfinger ) {
		global $wp;

		$accept = array();

		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			// Interpret accept header.
			$accept_header = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );
			$pos           = \stripos( $accept_header, ';' );
			if ( $pos ) {
				$accept_header = \substr( $accept_header, 0, $pos );
			}

			// Accept header as an array.
			$accept = \explode( ',', \trim( $accept_header ) );
		}

		$format = null;
		if ( \array_key_exists( 'format', $wp->query_vars ) ) {
			$format = $wp->query_vars['format'];
		}

		if (
			! \in_array( 'application/xrd+xml', $accept, true ) &&
			! \in_array( 'application/xml+xrd', $accept, true ) &&
			'xrd' !== $format
		) {
			return $webfinger;
		}

		\header( 'Content-Type: application/xrd+xml; charset=' . \get_bloginfo( 'charset' ), true );

		echo '<?xml version="1.0" encoding="' . \esc_attr( \get_bloginfo( 'charset' ) ) . '"?>' . \PHP_EOL;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_action returns null, XRD content is already escaped.
		echo '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0"' . \do_action( 'webfinger_ns' ) . '>' . \PHP_EOL;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- jrd_to_xrd returns escaped content.
		echo self::jrd_to_xrd( $webfinger );
		// Add xml-only content.
		\do_action( 'webfinger_xrd' );

		echo \PHP_EOL . '</XRD>';

		exit;
	}

	/**
	 * Host-meta resource feature.
	 *
	 * @param string $format    The format.
	 * @param array  $host_meta The host meta.
	 * @param array  $query     The query.
	 */
	public static function render_host_meta( $format, $host_meta, $query ) {
		if ( ! \array_key_exists( 'resource', $query ) ) {
			return;
		}

		global $wp;

		// Filter WebFinger array.
		$webfinger = \apply_filters( 'webfinger_data', array(), $query['resource'] );

		// Check if "user" exists.
		if ( empty( $webfinger ) ) {
			\status_header( 404 );
			\header( 'Content-Type: text/plain; charset=' . \get_bloginfo( 'charset' ), true );
			echo 'no data for resource "' . \esc_html( $query['resource'] ) . '" found';
			exit;
		}

		if ( 'xrd' === $format ) {
			$wp->query_vars['format'] = 'xrd';
		}

		\do_action( 'webfinger_render', $webfinger );
		// Stop exactly here!
		exit;
	}

	/**
	 * Add the host-meta information.
	 *
	 * @param array $host_meta The host meta array.
	 *
	 * @return array The modified host meta array.
	 */
	public static function host_meta_discovery( $host_meta ) {
		$host_meta['links'][] = array(
			'rel'      => 'lrdd',
			'template' => \add_query_arg(
				array(
					'resource' => '{uri}',
					'format'   => 'xrd',
				),
				\get_webfinger_endpoint()
			),
			'type'     => 'application/xrd+xml',
		);
		$host_meta['links'][] = array(
			'rel'      => 'lrdd',
			'template' => \add_query_arg( 'resource', '{uri}', \get_webfinger_endpoint() ),
			'type'     => 'application/jrd+xml',
		);
		$host_meta['links'][] = array(
			'rel'      => 'lrdd',
			'template' => \add_query_arg( 'resource', '{uri}', \get_webfinger_endpoint() ),
			'type'     => 'application/json',
		);

		return $host_meta;
	}

	/**
	 * Recursive helper to generate the xrd-xml from the jrd array.
	 *
	 * @param array $webfinger The webfinger data.
	 *
	 * @return string The XRD XML string.
	 */
	public static function jrd_to_xrd( $webfinger ) {
		$xrd = null;

		// Supported protocols.
		$protocols = \array_merge(
			array( 'aim', 'ymsgr', 'acct' ),
			\wp_allowed_protocols()
		);

		foreach ( $webfinger as $type => $content ) {
			// Print subject.
			if ( 'subject' === $type ) {
				$xrd .= '<Subject>' . \esc_url( $content, $protocols ) . '</Subject>';
				continue;
			}

			// Print aliases.
			if ( 'aliases' === $type ) {
				foreach ( $content as $uri ) {
					$xrd .= '<Alias>' . \esc_url( $uri, $protocols ) . '</Alias>';
				}
				continue;
			}

			// Print properties.
			if ( 'properties' === $type ) {
				foreach ( $content as $prop_type => $uri ) {
					$xrd .= '<Property type="' . \esc_attr( $prop_type ) . '">' . \esc_html( $uri ) . '</Property>';
				}
				continue;
			}

			// Print titles.
			if ( 'titles' === $type ) {
				foreach ( $content as $key => $value ) {
					if ( 'default' === $key ) {
						$xrd .= '<Title>' . \esc_html( $value ) . '</Title>';
					} else {
						$xrd .= '<Title xml:lang="' . \esc_attr( $key ) . '">' . \esc_html( $value ) . '</Title>';
					}
				}
				continue;
			}

			// Print links.
			if ( 'links' === $type ) {
				foreach ( $content as $links ) {
					$temp     = array();
					$cascaded = false;
					$xrd     .= '<Link ';

					foreach ( $links as $key => $value ) {
						if ( \is_array( $value ) ) {
							$temp[ $key ] = $value;
							$cascaded     = true;
						} else {
							$xrd .= \esc_attr( $key ) . '="' . \esc_attr( $value ) . '" ';
						}
					}
					if ( $cascaded ) {
						$xrd .= '>';
						$xrd .= self::jrd_to_xrd( $temp );
						$xrd .= '</Link>';
					} else {
						$xrd .= ' />';
					}
				}
				continue;
			}
		}

		return $xrd;
	}

	/**
	 * Backwards compatibility for old versions. Please don't use!
	 *
	 * @deprecated
	 *
	 * @param array    $webfinger    The webfinger data.
	 * @param string   $resource_uri The resource.
	 * @param \WP_User $user         The user.
	 *
	 * @return array The filtered webfinger data.
	 */
	public static function legacy_filter( $webfinger, $resource_uri, $user ) {
		// Filter WebFinger array.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a public WebFinger endpoint.
		return \apply_filters( 'webfinger', $webfinger, $user, $resource_uri, $_GET );
	}
}
