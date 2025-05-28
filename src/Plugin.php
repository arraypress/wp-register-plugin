<?php
/**
 * WP Register Plugin - Main Class
 *
 * Registers plugins after checking requirements and conflicts.
 * Uses WordPress-friendly hooks and methods.
 *
 * @package   arraypress/wp-register-plugin
 * @copyright Copyright (c) 2025, ArrayPress
 * @license   GPL2+
 * @since     1.0.0
 */

namespace ArrayPress\WP\Register;

use InvalidArgumentException;

/**
 * Plugin Class
 *
 * @since 1.0.0
 */
class Plugin {

	const VERSION = '1.0.0';

	/**
	 * @var string Path to the plugin file
	 */
	private string $plugin_file;

	/**
	 * @var string Plugin basename
	 */
	private string $plugin_basename;

	/**
	 * @var string Plugin directory name for CSS classes
	 */
	private string $plugin_dir_name;

	/**
	 * @var callable Bootstrap callback
	 */
	private $bootstrap_callback;

	/**
	 * @var Requirements
	 */
	private Requirements $requirements;

	/**
	 * @var array Configuration options
	 */
	private array $config;

	/**
	 * Constructor.
	 *
	 * @param array $config Configuration array
	 *
	 * @throws InvalidArgumentException
	 * @since 1.0.0
	 */
	public function __construct( array $config = [] ) {

		// Validate required config
		if ( empty( $config['file'] ) || ! is_callable( $config['bootstrap'] ) ) {
			throw new InvalidArgumentException( 'Invalid configuration: file and bootstrap are required.' );
		}

		$this->plugin_file        = $config['file'];
		$this->plugin_basename    = plugin_basename( $this->plugin_file );
		$this->plugin_dir_name    = dirname( $this->plugin_basename );
		$this->bootstrap_callback = $config['bootstrap'];

		// Set up requirements
		$requirements       = $config['requirements'] ?? [];
		$conflicts          = $config['conflicts'] ?? [];
		$this->requirements = new Requirements( $requirements, $conflicts );

		// Default config
		$this->config = wp_parse_args( $config, [
			'priority'         => 10,
			'early_includes'   => [],
			'activation'       => null,
			'deactivation'     => null,
			'success'          => null,
			'requirements_url' => '',
			'support_url'      => '',
			'error_message'    => __( 'This plugin is not fully active.', 'wp-register-plugin' ),
			'css_class_prefix' => 'wp-register-plugin',
		] );
	}

	/**
	 * Initialize the plugin instance.
	 *
	 * @since 1.0.0
	 */
	public function setup(): void {
		// Always load textdomain
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Check if we can load or need to quit
		if ( $this->requirements->met() ) {
			$this->load();
		} else {
			$this->quit();
		}
	}

	/**
	 * Load the plugin normally.
	 *
	 * @since 1.0.0
	 */
	private function load(): void {
		// Include early files
		foreach ( $this->config['early_includes'] as $file ) {
			if ( is_string( $file ) && file_exists( $file ) ) {
				require_once $file;
			}
		}

		// Bootstrap on plugins_loaded
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ], $this->config['priority'] );

		// Register activation/deactivation hooks
		if ( ! empty( $this->config['activation'] ) && is_callable( $this->config['activation'] ) ) {
			register_activation_hook( $this->plugin_file, $this->config['activation'] );
		}

		if ( ! empty( $this->config['deactivation'] ) && is_callable( $this->config['deactivation'] ) ) {
			register_deactivation_hook( $this->plugin_file, $this->config['deactivation'] );
		}
	}

	/**
	 * Quit without loading - set up error display.
	 *
	 * @since 1.0.0
	 */
	private function quit(): void {
		add_action( 'admin_head', [ $this, 'admin_head' ] );
		add_filter( "plugin_action_links_{$this->plugin_basename}", [ $this, 'plugin_action_links' ] );
		add_action( "after_plugin_row_{$this->plugin_basename}", [ $this, 'plugin_row_notice' ] );
	}

	/**
	 * WordPress plugins_loaded hook.
	 *
	 * @since 1.0.0
	 */
	public function plugins_loaded(): void {
		// Execute bootstrap callback
		call_user_func( $this->bootstrap_callback );

		// Execute success callback if provided
		if ( ! empty( $this->config['success'] ) && is_callable( $this->config['success'] ) ) {
			call_user_func( $this->config['success'] );
		}
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wp-register-plugin' );
	}

	/**
	 * WordPress admin_head hook - Add CSS styling for error display.
	 *
	 * @since 1.0.0
	 */
	public function admin_head(): void {
		$css_class = $this->get_css_class(); ?>

        <style id="<?php echo esc_attr( $css_class ); ?>">
            .plugins tr[data-plugin="<?php echo esc_attr( $this->plugin_basename ); ?>"] th,
            .plugins tr[data-plugin="<?php echo esc_attr( $this->plugin_basename ); ?>"] td,
            .plugins .<?php echo esc_attr( $css_class ); ?>-row th,
            .plugins .<?php echo esc_attr( $css_class ); ?>-row td {
                background: #fff5f5;
            }

            .plugins tr[data-plugin="<?php echo esc_attr( $this->plugin_basename ); ?>"] th,
            .plugins tr[data-plugin="<?php echo esc_attr( $this->plugin_basename ); ?>"] td {
                box-shadow: none;
            }

            .plugins .<?php echo esc_attr( $css_class ); ?>-row th span {
                margin-left: 6px;
                color: #dc3232;
            }

            .plugins tr[data-plugin="<?php echo esc_attr( $this->plugin_basename ); ?>"] th,
            .plugins .<?php echo esc_attr( $css_class ); ?>-row th.check-column {
                border-left: 4px solid #dc3232 !important;
            }

            .plugins .<?php echo esc_attr( $css_class ); ?>-row .column-description p {
                margin: 0;
                padding: 0;
            }

            .plugins .<?php echo esc_attr( $css_class ); ?>-row .column-description p:not(:last-of-type) {
                margin-bottom: 8px;
            }
        </style>
		<?php
	}

	/**
	 * WordPress plugin row notice - Display error messages in plugin list.
	 *
	 * @since 1.0.0
	 */
	public function plugin_row_notice(): void {
		$css_class = $this->get_css_class();
		$colspan   = $this->get_description_colspan();
		?>
        <tr class="active <?php echo esc_attr( $css_class ); ?>-row">
            <th class="check-column">
                <span class="dashicons dashicons-warning"></span>
            </th>
            <td class="column-primary">
				<?php echo esc_html( $this->config['error_message'] ); ?>
            </td>
            <td class="column-description" colspan="<?php echo esc_attr( $colspan ); ?>">
				<?php $this->output_error_messages(); ?>
            </td>
        </tr>
		<?php
	}

	/**
	 * WordPress plugin action links filter - Add requirements/support links.
	 *
	 * @param array $links Existing plugin action links
	 *
	 * @return array Modified plugin action links
	 * @since 1.0.0
	 */
	public function plugin_action_links( array $links ): array {
		if ( ! empty( $this->config['requirements_url'] ) ) {
			$requirements_link = sprintf(
				'<a href="%s" target="_blank" aria-label="%s">%s</a>',
				esc_url( $this->config['requirements_url'] ),
				esc_attr__( 'View plugin requirements', 'wp-register-plugin' ),
				esc_html__( 'Requirements', 'wp-register-plugin' )
			);

			$links['requirements'] = $requirements_link;
		}

		if ( ! empty( $this->config['support_url'] ) ) {
			$support_link = sprintf(
				'<a href="%s" target="_blank" aria-label="%s">%s</a>',
				esc_url( $this->config['support_url'] ),
				esc_attr__( 'Get plugin support', 'wp-register-plugin' ),
				esc_html__( 'Support', 'wp-register-plugin' )
			);

			$links['support'] = $support_link;
		}

		return $links;
	}

	/**
	 * Output error messages.
	 *
	 * @since 1.0.0
	 */
	private function output_error_messages(): void {
		$errors = $this->requirements->get_errors();

		foreach ( $errors->get_error_messages() as $message ) {
			echo wpautop( wp_kses_post( $message ) );
		}
	}

	/**
	 * Get CSS class name for styling.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function get_css_class(): string {
		return sanitize_html_class( $this->config['css_class_prefix'] . '-' . $this->plugin_dir_name );
	}

	/**
	 * Get colspan for description column.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	private function get_description_colspan(): int {
		// WordPress 5.5+ shows an auto-update column
		return function_exists( 'wp_is_auto_update_enabled_for_type' )
		       && wp_is_auto_update_enabled_for_type( 'plugin' ) ? 2 : 1;
	}

	/**
	 * Static method to register plugin from the configuration array.
	 *
	 * @param array $config Configuration array
	 *
	 * @since 1.0.0
	 */
	public static function register( array $config = [] ): void {
		try {
			$plugin = new self( $config );
			$plugin->setup();
		} catch ( InvalidArgumentException $e ) {
			// Log the error if needed
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WP Register Plugin Error: ' . $e->getMessage() );
			}
		}
	}
}