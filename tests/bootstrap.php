<?php
/**
 * Test bootstrap.
 *
 * The WordPress surface this package touches, kept behaviourally honest: the
 * escaping functions escape, and the option and plugin-state stubs read from
 * globals a test can arrange.
 */

declare( strict_types=1 );

// ABSPATH is defined in tests/constants.php, which runs via auto_prepend_file
// before Composer's autoloader; see the `test` script in composer.json.
require_once __DIR__ . '/../vendor/autoload.php';

$GLOBALS['wp_test_options']        = [];
$GLOBALS['wp_test_active_plugins'] = [];

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ): string {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		return $GLOBALS['wp_test_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( string $plugin ): bool {
		return in_array( $plugin, $GLOBALS['wp_test_active_plugins'], true );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '' ): string {
		return 'version' === $show ? ( $GLOBALS['wp_test_wp_version'] ?? '6.8' ) : '';
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private array $errors = [];

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->add( $code, $message, $data );
			}
		}

		public function add( $code, $message, $data = '' ): void {
			$this->errors[ $code ][] = $message;
		}

		public function get_error_codes(): array {
			return array_keys( $this->errors );
		}

		public function get_error_code() {
			return array_key_first( $this->errors ) ?? '';
		}

		public function get_error_messages( $code = '' ): array {
			if ( '' === $code ) {
				return array_merge( ...array_values( $this->errors ?: [ [] ] ) );
			}

			return $this->errors[ $code ] ?? [];
		}

		public function get_error_message( $code = '' ): string {
			$messages = $this->get_error_messages( $code );

			return $messages[0] ?? '';
		}

		public function has_errors(): bool {
			return ! empty( $this->errors );
		}
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ): array {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}

		return is_array( $args ) ? array_merge( $defaults, $args ) : $defaults;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $text ): string {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ): string {
		return trim( strip_tags( (string) $text ) );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
		$GLOBALS['wp_test_hooks'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
		return add_action( $hook, $callback, $priority, $args );
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( string $hook ): int {
		return (int) ( $GLOBALS['wp_test_did_action'][ $hook ] ?? 0 );
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ): string {
		return rtrim( dirname( $file ), '/' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $file ): string {
		return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return (bool) ( $GLOBALS['wp_test_is_admin'] ?? true );
	}
}
