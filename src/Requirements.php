<?php
/**
 * WP Register Plugin - Requirements
 *
 * Handles checking plugin requirements and conflicts with flexible detection methods.
 *
 * @package   arraypress/wp-register-plugin
 * @copyright Copyright (c) 2025, ArrayPress
 * @license   GPL2+
 * @since     1.0.0
 */

namespace ArrayPress\WP\Register;

use WP_Error;

/**
 * Requirements Class
 *

 */
class Requirements {

	/**
	 * @var array Requirements that must be met
	 */
	private array $requirements = [];

	/**
	 * @var array Conflicts that must not exist
	 */
	private array $conflicts = [];

	/**
	 * Constructor.
	 *
	 * @param array $requirements Array of requirements
	 * @param array $conflicts    Array of conflicts
	 *

	 */
	public function __construct( array $requirements = [], array $conflicts = [] ) {
		if ( ! empty( $requirements ) ) {
			foreach ( $requirements as $id => $requirement ) {
				$this->add_requirement( $id, $requirement );
			}
		}

		if ( ! empty( $conflicts ) ) {
			foreach ( $conflicts as $id => $conflict ) {
				$this->add_conflict( $id, $conflict );
			}
		}
	}

	/**
	 * Get known dependencies configuration.
	 *
	 * @return array
	 */
	private function get_known_dependencies(): array {
		return [
			'php'                    => [
				'name'  => 'PHP',
				'check' => 'phpversion',
				'type'  => 'function'
			],
			'wp'                     => [
				'name'  => 'WordPress',
				'check' => [ 'get_bloginfo', 'version' ],
				'type'  => 'function_with_args'
			],
			'easy-digital-downloads' => [
				'name'  => 'Easy Digital Downloads',
				'check' => 'EDD_VERSION',
				'type'  => 'constant'
			],
			'woocommerce'            => [
				'name'  => 'WooCommerce',
				'check' => 'WC_VERSION',
				'type'  => 'constant'
			],
			'elementor'              => [
				'name'  => 'Elementor',
				'check' => 'ELEMENTOR_VERSION',
				'type'  => 'constant'
			],
			'advanced-custom-fields' => [
				'name'  => 'Advanced Custom Fields',
				'check' => [ $this, 'check_acf_version' ],
				'type'  => 'callback'
			],
		];
	}

	/**
	 * Check ACF version.
	 *
	 * @return string|false
	 */
	private function check_acf_version() {
		return class_exists( 'ACF' ) ? get_option( 'acf_version', false ) : false;
	}

	/**
	 * Adds a new requirement.
	 *
	 * @param string       $id   Unique ID for the requirement
	 * @param array|string $args Array of arguments or minimum version string
	 *

	 */
	public function add_requirement( string $id, $args ): void {
		if ( ! is_array( $args ) ) {
			$args = [ 'minimum' => (string) $args ];
		}

		$known = $this->get_known_dependency( $id );

		$args = wp_parse_args( $args, [
			'minimum' => '1.0',
			'name'    => $known['name'] ?? $id,
			'exists'  => $known['exists'] ?? null,
			'current' => $known['current'] ?? null,
			'check'   => $known['check'] ?? null,
			'type'    => $known['type'] ?? 'auto',
			'checked' => false,
			'met'     => false,
		] );

		$this->requirements[ sanitize_key( $id ) ] = $args;
	}

	/**
	 * Adds a new conflict.
	 *
	 * @param string       $id   Unique ID for the conflict
	 * @param array|string $args Array of arguments or plugin path string
	 *

	 */
	public function add_conflict( string $id, $args ): void {
		if ( ! is_array( $args ) ) {
			$args = [ 'plugin' => (string) $args ];
		}

		// If plugin is specified, set up plugin_active check
		if ( isset( $args['plugin'] ) && ! isset( $args['check'] ) ) {
			$args['check'] = $args['plugin'];
			$args['type']  = 'plugin_active';
		}

		$known = $this->get_known_dependency( $id );

		$args = wp_parse_args( $args, [
			'name'      => $known['name'] ?? $id,
			'exists'    => $known['exists'] ?? null,
			'check'     => $known['check'] ?? null,
			'type'      => $known['type'] ?? 'auto',
			'message'   => null,
			'action'    => 'auto_deactivate',
			'condition' => null,
			'checked'   => false,
			'conflict'  => false,
		] );

		$this->conflicts[ sanitize_key( $id ) ] = $args;
	}

	/**
	 * Get known dependency configuration.
	 *
	 * @param string $id Dependency ID
	 *
	 * @return array
	 */
	private function get_known_dependency( string $id ): array {
		$known_dependencies = $this->get_known_dependencies();

		return $known_dependencies[ $id ] ?? [];
	}

	/**
	 * Whether all requirements are met and no conflicts exist.
	 *
	 * @return bool
	 */
	public function met(): bool {
		$this->check();

		// Check requirements
		foreach ( $this->requirements as $requirement ) {
			if ( empty( $requirement['met'] ) ) {
				return false;
			}
		}

		// Check conflicts
		foreach ( $this->conflicts as $conflict ) {
			if ( ! empty( $conflict['conflict'] ) ) {
				// Try to handle auto_deactivate conflicts
				if ( $conflict['action'] === 'auto_deactivate' && isset( $conflict['plugin'] ) ) {
					if ( function_exists( 'deactivate_plugins' ) ) {
						deactivate_plugins( $conflict['plugin'] );
						continue; // Skip this conflict since we handled it
					}
				}

				return false;
			}
		}

		return true;
	}

	/**
	 * Checks all requirements and conflicts.
	 *

	 */
	private function check(): void {
		$this->check_requirements();
		$this->check_conflicts();
	}

	/**
	 * Check all requirements.
	 *

	 */
	private function check_requirements(): void {
		foreach ( $this->requirements as $requirement_id => $properties ) {
			$result = $this->perform_check( $properties );

			$this->requirements[ $requirement_id ] = array_merge(
				$this->requirements[ $requirement_id ],
				[
					'current' => $result['version'],
					'checked' => true,
					'met'     => $result['exists'] && $this->minimum_version_met(
							$result['version'],
							$this->parse_property( $properties, 'minimum' )
						),
					'exists'  => $result['exists'],
				]
			);
		}
	}

	/**
	 * Check all conflicts.
	 *

	 */
	private function check_conflicts(): void {
		foreach ( $this->conflicts as $conflict_id => $properties ) {
			$result = $this->perform_check( $properties );

			$is_conflict = $result['exists'];

			// Check custom condition if provided
			if ( $is_conflict && isset( $properties['condition'] ) && is_callable( $properties['condition'] ) ) {
				$is_conflict = call_user_func( $properties['condition'], $result );
			}

			$this->conflicts[ $conflict_id ] = array_merge(
				$this->conflicts[ $conflict_id ],
				[
					'current'  => $result['version'],
					'checked'  => true,
					'conflict' => $is_conflict,
					'exists'   => $result['exists'],
				]
			);
		}
	}

	/**
	 * Perform a check based on the configuration.
	 *
	 * @param array $properties Check properties
	 *
	 * @return array
	 */
	private function perform_check( array $properties ): array {
		$exists  = false;
		$version = false;

		// Custom exists function takes precedence
		if ( isset( $properties['exists'] ) && is_callable( $properties['exists'] ) ) {
			$exists = call_user_func( $properties['exists'] );
		}

		// Custom current function takes precedence
		if ( isset( $properties['current'] ) && is_callable( $properties['current'] ) ) {
			$version = call_user_func( $properties['current'] );
		}

		// If not custom, use the check configuration
		if ( ! $exists && ! $version && isset( $properties['check'] ) ) {
			$result  = $this->perform_check_by_type( $properties['check'], $properties['type'] );
			$exists  = $result['exists'];
			$version = $result['version'];
		}

		return [
			'exists'  => $exists,
			'version' => $version,
		];
	}

	/**
	 * Perform check by type.
	 *
	 * @param mixed  $check What to check
	 * @param string $type  Type of check
	 *
	 * @return array
	 */
	private function perform_check_by_type( $check, string $type ): array {
		$exists  = false;
		$version = false;

		switch ( $type ) {
			case 'constant':
				$exists  = defined( $check );
				$version = $exists ? constant( $check ) : false;
				break;

			case 'function':
				if ( is_string( $check ) && function_exists( $check ) ) {
					$exists  = true;
					$version = call_user_func( $check );
				}
				break;

			case 'function_with_args':
				if ( is_array( $check ) && function_exists( $check[0] ) ) {
					$exists  = true;
					$args    = array_slice( $check, 1 );
					$version = call_user_func_array( $check[0], $args );
				}
				break;

			case 'class':
				if ( is_string( $check ) ) {
					$exists = class_exists( $check );
				}
				break;

			case 'plugin_active':
				if ( is_string( $check ) ) {
					if ( ! function_exists( 'is_plugin_active' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}
					$exists = is_plugin_active( $check );
				}
				break;

			case 'callback':
				if ( is_callable( $check ) ) {
					$result = call_user_func( $check );
					if ( is_array( $result ) ) {
						$exists  = $result['exists'] ?? false;
						$version = $result['version'] ?? false;
					} else {
						$version = $result;
						$exists  = ! empty( $result );
					}
				}
				break;

			case 'auto':
				// Try to auto-detect the best method
				if ( is_string( $check ) && defined( $check ) ) {
					$exists  = true;
					$version = constant( $check );
				} elseif ( is_string( $check ) && function_exists( $check ) ) {
					$exists  = true;
					$version = call_user_func( $check );
				} elseif ( is_string( $check ) && class_exists( $check ) ) {
					$exists = true;
				}
				break;
		}

		return [
			'exists'  => $exists,
			'version' => $version,
		];
	}

	/**
	 * Parse a property from requirements/conflicts array.
	 *
	 * @param array  $properties Properties array
	 * @param string $key        Key to parse
	 *
	 * @return mixed
	 */
	private function parse_property( array $properties, string $key ) {
		if ( ! array_key_exists( $key, $properties ) ) {
			return false;
		}

		return is_callable( $properties[ $key ] )
			? call_user_func( $properties[ $key ] )
			: $properties[ $key ];
	}

	/**
	 * Determine if minimum version requirement is met.
	 *
	 * @param mixed  $current Current version
	 * @param string $minimum Minimum required version
	 *
	 * @return bool
	 */
	private function minimum_version_met( $current, string $minimum ): bool {
		if ( ! is_string( $current ) ) {
			return false;
		}

		return version_compare( $current, $minimum, '>=' );
	}

	/**
	 * Get all errors (unmet requirements and conflicts).
	 *
	 * @return WP_Error
	 */
	public function get_errors(): WP_Error {
		$error = new WP_Error();

		// Add requirement errors
		foreach ( $this->requirements as $requirement_id => $properties ) {
			if ( empty( $properties['met'] ) ) {
				$error->add(
					"requirement_{$requirement_id}",
					$this->unmet_requirement_description( $properties )
				);
			}
		}

		// Add conflict errors
		foreach ( $this->conflicts as $conflict_id => $properties ) {
			if ( ! empty( $properties['conflict'] ) ) {
				$error->add(
					"conflict_{$conflict_id}",
					$this->conflict_description( $properties )
				);
			}
		}

		return $error;
	}

	/**
	 * Generate description for unmet requirement.
	 *
	 * @param array $requirement Requirement properties
	 *
	 * @return string
	 */
	private function unmet_requirement_description( array $requirement ): string {
		if ( $this->parse_property( $requirement, 'exists' ) && $this->parse_property( $requirement, 'current' ) ) {
			return sprintf(
			/* translators: %1$s: requirement name, %2$s: minimum version, %3$s: current version */
				__( '%1$s: minimum required %2$s (you have %3$s)', 'arraypress' ),
				'<strong>' . esc_html( $this->parse_property( $requirement, 'name' ) ) . '</strong>',
				'<strong>' . esc_html( $this->parse_property( $requirement, 'minimum' ) ) . '</strong>',
				'<strong>' . esc_html( $this->parse_property( $requirement, 'current' ) ) . '</strong>'
			);
		}

		return sprintf(
		/* translators: %1$s: requirement name, %2$s: minimum version */
			__( '<strong>Missing %1$s</strong>: minimum required %2$s', 'arraypress' ),
			esc_html( $this->parse_property( $requirement, 'name' ) ),
			'<strong>' . esc_html( $this->parse_property( $requirement, 'minimum' ) ) . '</strong>'
		);
	}

	/**
	 * Generate description for conflict.
	 *
	 * @param array $conflict Conflict properties
	 *
	 * @return string
	 */
	private function conflict_description( array $conflict ): string {
		$message = $this->parse_property( $conflict, 'message' );

		if ( ! empty( $message ) ) {
			return (string) $message;
		}

		return sprintf(
		/* translators: %s: conflicting plugin name */
			__( '<strong>Conflict detected</strong>: %s is active and conflicts with this plugin.', 'arraypress' ),
			'<strong>' . esc_html( $this->parse_property( $conflict, 'name' ) ) . '</strong>'
		);
	}

}