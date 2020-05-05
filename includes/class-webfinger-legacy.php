<?php
/**
 * WebFinger Legacy
 *
 * @author Matthias Pfefferle
 */
class Webfinger_Legacy {
	/**
	 * add query vars
	 *
	 * @param array $vars
	 *
	 * @return array
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'format';
		$vars[] = 'resource';
		$vars[] = 'rel';

		return $vars;
	}

	/**
	 * render the XRD representation of the WordPress resource.
	 *
	 * @param array $webfinger the WordPress data-array
	 */
	public static function render_xrd( $webfinger ) {
		global $wp;

		$accept = array();

		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			// interpret accept header
			$pos = stripos( $_SERVER['HTTP_ACCEPT'], ';' );
			if ( $pos ) {
				$accept_header = substr( $_SERVER['HTTP_ACCEPT'], 0, $pos );
			} else {
				$accept_header = $_SERVER['HTTP_ACCEPT'];
			}

			// accept header as an array
			$accept = explode( ',', trim( $accept_header ) );
		}

		$format = null;
		if ( array_key_exists( 'format', $wp->query_vars ) ) {
			$format = $wp->query_vars['format'];
		}

		if (
			! in_array( 'application/xrd+xml', $accept, true ) &&
			! in_array( 'application/xml+xrd', $accept, true ) &&
			'xrd' !== $format
		) {
			return $webfinger;
		}

		header( 'Content-Type: application/xrd+xml; charset=' . get_bloginfo( 'charset' ), true );

		echo '<?xml version="1.0" encoding="' . get_bloginfo( 'charset' ) . '"?>' . PHP_EOL;
		echo '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0"' . do_action( 'webfinger_ns' ) . '>' . PHP_EOL;

		echo self::jrd_to_xrd( $webfinger );
		// add xml-only content
		do_action( 'webfinger_xrd' );

		echo PHP_EOL . '</XRD>';

		exit;
	}

	/*
	 * host-meta resource feature
	 *
	 * @param array $query
	 */
	public static function render_host_meta( $format, $host_meta, $query ) {
		if ( ! array_key_exists( 'resource', $query ) ) {
			return;
		}

		global $wp;

		// filter WebFinger array
		$webfinger = apply_filters( 'webfinger_data', array(), $query['resource'] );

		// check if "user" exists
		if ( empty( $webfinger ) ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ), true );
			echo 'no data for resource "' . $query['resource'] . '" found';
			exit;
		}

		if ( 'xrd' === $format ) {
			$wp->query_vars['format'] = 'xrd';
		}

		do_action( 'webfinger_render', $webfinger );
		// stop exactly here!
		exit;
	}

	/**
	 * add the host meta information
	 */
	public static function host_meta_discovery( $array ) {
		$array['links'][] = array(
			'rel' => 'lrdd',
			'template' => add_query_arg(
				array(
					'resource' => '{uri}',
					'format' => 'xrd',
				),
				get_webfinger_endpoint()
			),
			'type' => 'application/xrd+xml',
		);
		$array['links'][] = array(
			'rel' => 'lrdd',
			'template' => add_query_arg( 'resource', '{uri}', get_webfinger_endpoint() ),
			'type' => 'application/jrd+xml',
		);
		$array['links'][] = array(
			'rel' => 'lrdd',
			'template' => add_query_arg( 'resource', '{uri}', get_webfinger_endpoint() ),
			'type' => 'application/json',
		);

		return $array;
	}

	/**
	 * recursive helper to generade the xrd-xml from the jrd array
	 *
	 * @param string $host_meta
	 *
	 * @return string
	 */
	public static function jrd_to_xrd( $webfinger ) {
		$xrd = null;

		// supported protocols
		$protocols = array_merge(
			array( 'aim', 'ymsgr', 'acct' ),
			wp_allowed_protocols()
		);

		foreach ( $webfinger as $type => $content ) {
			// print subject
			if ( 'subject' === $type ) {
				$xrd .= '<Subject>' . esc_url( $content, $protocols ) . '</Subject>';
				continue;
			}

			// print aliases
			if ( 'aliases' === $type ) {
				foreach ( $content as $uri ) {
					$xrd .= '<Alias>' . esc_url( $uri, $protocols ) . '</Alias>';
				}
				continue;
			}

			// print properties
			if ( 'properties' === $type ) {
				foreach ( $content as $type => $uri ) {
					$xrd .= '<Property type="' . esc_attr( $type ) . '">' . esc_html( $uri ) . '</Property>';
				}
				continue;
			}

			// print titles
			if ( 'titles' === $type ) {
				foreach ( $content as $key => $value ) {
					if ( 'default' === $key ) {
						$xrd .= '<Title>' . esc_html( $value ) . '</Title>';
					} else {
						$xrd .= '<Title xml:lang="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</Title>';
					}
				}
				continue;
			}

			// print links
			if ( 'links' === $type ) {
				foreach ( $content as $links ) {
					$temp = array();
					$cascaded = false;
					$xrd .= '<Link ';

					foreach ( $links as $key => $value ) {
						if ( is_array( $value ) ) {
							$temp[ $key ] = $value;
							$cascaded = true;
						} else {
							$xrd .= esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
						}
					}
					if ( $cascaded ) {
						$xrd .= '>';
						$xrd .= Webfinger_Legacy::jrd_to_xrd( $temp );
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
	 * Backwards compatibility for old versions. please don't use!
	 *
	 * @deprecated
	 *
	 * @param array   $webfinger
	 * @param string  $resource
	 * @param WP_User $user
	 *
	 * @return array
	 */
	public static function legacy_filter( $webfinger, $resource, $user ) {
		// filter WebFinger array
		return apply_filters( 'webfinger', $webfinger, $user, $resource, $_GET );
	}
}
