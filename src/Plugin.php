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

namespace ArrayPress\RegisterPlugin;

use InvalidArgumentException;
use WP_Screen;

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
     * @var string Plugin directory path
     */
    private string $plugin_dir;

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
     * @var array Track registered plugins to prevent duplicates
     */
    private static array $registered_plugins = [];

    /**
     * @var bool Track if this instance has been executed
     */
    private bool $executed = false;

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
        $this->plugin_dir         = plugin_dir_path( $this->plugin_file );
        $this->plugin_dir_name    = dirname( $this->plugin_basename );
        $this->bootstrap_callback = $config['bootstrap'];

        // Default config
        $this->config = wp_parse_args( $config, [
                'priority'         => 10,
                'early_includes'   => [],
                'includes'         => [],
                'constants'        => null,
                'textdomain'       => null,
                'setup_hooks'      => [],
                'activation'       => null,
                'deactivation'     => null,
                'success'          => null,
                'requirements_url' => '',
                'support_url'      => '',
                'error_message'    => __( 'This plugin is not fully active.', 'wp-register-plugin' ),
                'css_class_prefix' => 'wp-register-plugin',
                'plugin_links'     => [],
                'plugin_meta'      => [],
                'notices'          => [],
        ] );

        // Prevent duplicate registration
        if ( isset( self::$registered_plugins[ $this->plugin_basename ] ) ) {
            return;
        }
        self::$registered_plugins[ $this->plugin_basename ] = true;
    }

    /**
     * Initialize the plugin instance.
     *
     * Always defers to plugins_loaded to ensure all plugin constants are available.
     *
     * @since 1.0.0
     */
    public function setup(): void {
        // Always load textdomain early
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ], 1 );

        // Always defer main initialization to plugins_loaded
        // This ensures all plugins are loaded and constants are defined
        if ( did_action( 'plugins_loaded' ) ) {
            // If plugins_loaded already fired, run immediately
            $this->delayed_setup();
        } else {
            // Otherwise, wait for plugins_loaded
            add_action( 'plugins_loaded', [ $this, 'delayed_setup' ], $this->config['priority'] );
        }
    }

    /**
     * Delayed setup that runs on plugins_loaded.
     *
     * This ensures all plugin constants are available before checking requirements.
     *
     * @since 1.0.0
     */
    public function delayed_setup(): void {
        // Prevent double execution
        if ( $this->executed ) {
            return;
        }
        $this->executed = true;

        // Now we can safely check requirements since all plugins are loaded
        $this->init_requirements();

        // Check if we can load or need to quit
        if ( $this->requirements->met() ) {
            $this->load();
        } else {
            $this->quit();
        }
    }

    /**
     * Initialize requirements after plugins_loaded.
     *
     * @since 1.0.0
     */
    private function init_requirements(): void {
        $requirements = $this->config['requirements'] ?? [];
        $conflicts    = $this->config['conflicts'] ?? [];

        $this->requirements = new Requirements( $requirements, $conflicts );
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

        // Include files before bootstrap
        $this->include_files();

        // Define constants
        $this->define_constants();

        // Execute bootstrap callback immediately (we're already in plugins_loaded)
        call_user_func( $this->bootstrap_callback );

        // Execute setup hooks
        $this->execute_setup_hooks();

        // Execute success callback if provided
        if ( ! empty( $this->config['success'] ) && is_callable( $this->config['success'] ) ) {
            call_user_func( $this->config['success'] );
        }

        // Register activation/deactivation hooks
        if ( ! empty( $this->config['activation'] ) && is_callable( $this->config['activation'] ) ) {
            register_activation_hook( $this->plugin_file, $this->config['activation'] );
        }

        if ( ! empty( $this->config['deactivation'] ) && is_callable( $this->config['deactivation'] ) ) {
            register_deactivation_hook( $this->plugin_file, $this->config['deactivation'] );
        }

        // Set up plugin links and notices for a successful load
        $this->setup_plugin_enhancements();
    }

    /**
     * Quit without loading - set up the error display.
     *
     * @since 1.0.0
     */
    private function quit(): void {
        add_action( 'admin_head', [ $this, 'admin_head' ] );
        add_filter( "plugin_action_links_{$this->plugin_basename}", [ $this, 'error_plugin_action_links' ] );
        add_action( "after_plugin_row_{$this->plugin_basename}", [ $this, 'plugin_row_notice' ] );
    }

    /**
     * WordPress plugins_loaded hook.
     *
     * @deprecated Kept for backward compatibility, use delayed_setup instead
     * @since      1.0.0
     */
    public function plugins_loaded(): void {
        $this->delayed_setup();
    }

    /**
     * Load plugin textdomain.
     *
     * @since 1.0.0
     */
    public function load_textdomain(): void {
        // Load register plugin textdomain
        load_plugin_textdomain( 'wp-register-plugin' );

        // Load plugin-specific textdomain if configured
        if ( ! empty( $this->config['textdomain'] ) ) {
            $this->load_plugin_textdomain();
        }
    }

    /**
     * Load plugin-specific textdomain.
     *
     * @since 1.0.0
     */
    private function load_plugin_textdomain(): void {
        $textdomain_config = $this->config['textdomain'];

        if ( is_string( $textdomain_config ) ) {
            // Simple string - just the domain
            load_plugin_textdomain( $textdomain_config, false, $this->plugin_dir_name . '/languages' );
        } elseif ( is_array( $textdomain_config ) ) {
            // Array configuration
            $domain = $textdomain_config['domain'] ?? '';
            $path   = $textdomain_config['path'] ?? '/languages';

            if ( ! empty( $domain ) ) {
                if ( isset( $textdomain_config['callback'] ) && is_callable( $textdomain_config['callback'] ) ) {
                    // Custom callback
                    call_user_func( $textdomain_config['callback'], $this->plugin_dir );
                } else {
                    // Standard loading
                    $full_path = $this->plugin_dir_name . $path;
                    load_plugin_textdomain( $domain, false, $full_path );
                }
            }
        }
    }

    /**
     * Include configured files.
     *
     * @since 1.0.0
     */
    private function include_files(): void {
        if ( empty( $this->config['includes'] ) || ! is_array( $this->config['includes'] ) ) {
            return;
        }

        foreach ( $this->config['includes'] as $file ) {
            if ( ! is_string( $file ) ) {
                continue;
            }

            // Make path relative to plugin directory
            $file_path = $this->plugin_dir . ltrim( $file, '/' );

            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }

    /**
     * Define plugin constants.
     *
     * @since 1.0.0
     */
    private function define_constants(): void {
        if ( empty( $this->config['constants'] ) ) {
            return;
        }

        $constants_config = $this->config['constants'];

        if ( is_string( $constants_config ) ) {
            // Simple string - just the prefix
            $this->define_standard_constants( $constants_config );
        } elseif ( is_array( $constants_config ) && ! empty( $constants_config['prefix'] ) ) {
            // Array configuration
            $prefix  = $constants_config['prefix'];
            $version = $constants_config['version'] ?? $this->get_plugin_version();
            $skip    = $constants_config['skip'] ?? [];

            $this->define_standard_constants( $prefix, $version, $skip );

            // Define custom constants
            if ( ! empty( $constants_config['definitions'] ) && is_array( $constants_config['definitions'] ) ) {
                foreach ( $constants_config['definitions'] as $name => $value ) {
                    if ( ! defined( $name ) ) {
                        define( $name, $value );
                    }
                }
            }
        }
    }

    /**
     * Define standard plugin constants.
     *
     * @param string $prefix  Constant prefix
     * @param string $version Plugin version
     * @param array  $skip    Constants to skip
     *
     * @since 1.0.0
     */
    private function define_standard_constants( string $prefix, string $version = '', array $skip = [] ): void {
        $constants = [
            'VERSION'     => $version ?: $this->get_plugin_version(),
            'FILE'        => $this->plugin_file,
            'BASE'        => $this->plugin_basename,
            'DIR'         => $this->plugin_dir,
            'URL'         => plugin_dir_url( $this->plugin_file ),
            'QUERY_LIMIT' => 9999999
        ];

        foreach ( $constants as $suffix => $value ) {
            if ( in_array( $suffix, $skip, true ) ) {
                continue;
            }

            $constant_name = $prefix . '_PLUGIN_' . $suffix;

            if ( ! defined( $constant_name ) ) {
                define( $constant_name, $value );
            }
        }
    }

    /**
     * Execute setup hooks.
     *
     * @since 1.0.0
     */
    private function execute_setup_hooks(): void {
        if ( empty( $this->config['setup_hooks'] ) || ! is_array( $this->config['setup_hooks'] ) ) {
            return;
        }

        foreach ( $this->config['setup_hooks'] as $hook => $callback ) {
            if ( $hook === 'woocommerce_compatibility' && is_array( $callback ) ) {
                // Special handling for WooCommerce compatibility
                $this->setup_woocommerce_compatibility( $callback );
            } elseif ( is_callable( $callback ) ) {
                // Check if callback expects the plugin file parameter
                $reflection = new \ReflectionFunction( $callback );
                if ( $reflection->getNumberOfParameters() > 0 ) {
                    add_action( $hook, function () use ( $callback ) {
                        call_user_func( $callback, $this->plugin_file );
                    } );
                } else {
                    add_action( $hook, $callback );
                }
            }
        }
    }

    /**
     * Setup WooCommerce compatibility.
     *
     * @param array $config Compatibility configuration
     *
     * @since 1.0.0
     */
    private function setup_woocommerce_compatibility( array $config ): void {
        $compatibility_callback = function () use ( $config ) {
            if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                return;
            }

            $features   = $config['features'] ?? [ 'custom_order_tables' ];
            $compatible = $config['compatible'] ?? true;

            foreach ( $features as $feature ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                        $feature,
                        $this->plugin_file,
                        $compatible
                );
            }

            // Execute custom callback if provided
            if ( ! empty( $config['callback'] ) && is_callable( $config['callback'] ) ) {
                call_user_func( $config['callback'], $this->plugin_file );
            }
        };

        // Check if before_woocommerce_init already fired
        if ( did_action( 'before_woocommerce_init' ) ) {
            // Run immediately if WooCommerce hook already fired
            $compatibility_callback();
        } else {
            // Otherwise wait for the hook
            add_action( 'before_woocommerce_init', $compatibility_callback );
        }
    }

    /**
     * Get plugin version from header.
     *
     * @return string
     * @since 1.0.0
     */
    private function get_plugin_version(): string {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( $this->plugin_file );

        return $plugin_data['Version'] ?? '1.0.0';
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
     * WordPress plugin action links filter - Add requirements/support links for errors.
     *
     * @param array $links Existing plugin action links
     *
     * @return array Modified plugin action links
     * @since 1.0.0
     */
    public function error_plugin_action_links( array $links ): array {
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
     * Set up plugin enhancements for successful loads.
     *
     * @since 1.0.0
     */
    private function setup_plugin_enhancements(): void {
        // Plugin action links
        if ( ! empty( $this->config['plugin_links'] ) ) {
            add_filter( "plugin_action_links_{$this->plugin_basename}", [ $this, 'plugin_action_links' ] );
        }

        // Plugin meta links
        if ( ! empty( $this->config['plugin_meta'] ) ) {
            add_filter( "plugin_row_meta", [ $this, 'plugin_row_meta' ], 10, 2 );
        }

        // Admin notices
        if ( ! empty( $this->config['notices'] ) ) {
            add_action( 'admin_notices', [ $this, 'admin_notices' ] );
            add_action( 'wp_ajax_wp_register_plugin_dismiss_notice', [ $this, 'dismiss_notice' ] );
        }
    }

    /**
     * WordPress plugin action links filter - Add custom plugin links.
     *
     * @param array $links Existing plugin action links
     *
     * @return array Modified plugin action links
     * @since 1.0.0
     */
    public function plugin_action_links( array $links ): array {
        $custom_links = [];

        foreach ( $this->config['plugin_links'] as $key => $link ) {
            if ( is_string( $link ) ) {
                // Simple URL
                $custom_links[ $key ] = $this->create_plugin_link( $key, $link );
            } elseif ( is_array( $link ) && ! empty( $link['url'] ) ) {
                // Advanced configuration
                if ( ! empty( $link['capability'] ) && ! current_user_can( $link['capability'] ) ) {
                    continue;
                }
                $custom_links[ $key ] = $this->create_plugin_link(
                        $link['text'] ?? ucfirst( $key ),
                        $link['url'],
                        $link['target'] ?? '',
                        $link['style'] ?? ''
                );
            }
        }

        return array_merge( $custom_links, $links );
    }

    /**
     * WordPress plugin row meta filter - Add custom meta links.
     *
     * @param array  $links       Plugin meta links
     * @param string $plugin_file Plugin file path
     *
     * @return array Modified meta links
     * @since 1.0.0
     */
    public function plugin_row_meta( array $links, string $plugin_file ): array {
        if ( $plugin_file !== $this->plugin_basename ) {
            return $links;
        }

        foreach ( $this->config['plugin_meta'] as $key => $link ) {
            if ( is_string( $link ) ) {
                // Simple URL
                $links[] = $this->create_plugin_link( ucfirst( $key ), $link, '_blank' );
            } elseif ( is_array( $link ) && ! empty( $link['url'] ) ) {
                // Advanced configuration
                if ( ! empty( $link['capability'] ) && ! current_user_can( $link['capability'] ) ) {
                    continue;
                }
                $links[] = $this->create_plugin_link(
                        $link['text'] ?? ucfirst( $key ),
                        $link['url'],
                        $link['target'] ?? '_blank',
                        $link['style'] ?? ''
                );
            }
        }

        return $links;
    }

    /**
     * Display admin notices.
     *
     * @since 1.0.0
     */
    public function admin_notices(): void {
        $current_screen = get_current_screen();

        foreach ( $this->config['notices'] as $notice_id => $notice ) {
            if ( ! $this->should_show_notice( $notice_id, $notice, $current_screen ) ) {
                continue;
            }

            $this->render_admin_notice( $notice_id, $notice );
        }
    }

    /**
     * Handle notice dismissal via AJAX.
     *
     * @since 1.0.0
     */
    public function dismiss_notice(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( - 1, 403 );
        }

        $notice_id = sanitize_key( $_POST['notice_id'] ?? '' );
        if ( empty( $notice_id ) ) {
            wp_die( - 1, 400 );
        }

        update_user_meta( get_current_user_id(), "wp_register_plugin_dismissed_{$notice_id}", true );
        wp_die( 1 );
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
     * Create a plugin link with proper formatting.
     *
     * @param string $text   Link text
     * @param string $url    Link URL
     * @param string $target Link target
     * @param string $style  Custom CSS styles
     *
     * @return string Formatted link HTML
     * @since 1.0.0
     */
    private function create_plugin_link( string $text, string $url, string $target = '', string $style = '' ): string {
        $attributes = [];

        if ( ! empty( $target ) ) {
            $attributes[] = sprintf( 'target="%s"', esc_attr( $target ) );
        }

        if ( ! empty( $style ) ) {
            $attributes[] = sprintf( 'style="%s"', esc_attr( $style ) );
        }

        $attr_string = ! empty( $attributes ) ? ' ' . implode( ' ', $attributes ) : '';

        return sprintf(
                '<a href="%s"%s>%s</a>',
                esc_url( $url ),
                $attr_string,
                esc_html( $text )
        );
    }

    /**
     * Check if a notice should be displayed.
     *
     * @param string    $notice_id      Notice ID
     * @param array     $notice         Notice configuration
     * @param WP_Screen $current_screen Current admin screen
     *
     * @return bool Whether to show the notice
     * @since 1.0.0
     */
    private function should_show_notice( string $notice_id, array $notice, $current_screen ): bool {
        // Check capability
        if ( ! empty( $notice['capability'] ) && ! current_user_can( $notice['capability'] ) ) {
            return false;
        }

        // Check if dismissed
        if ( ! empty( $notice['dismissible'] ) && get_user_meta( get_current_user_id(), "wp_register_plugin_dismissed_{$notice_id}", true ) ) {
            return false;
        }

        // Check show_once
        if ( ! empty( $notice['show_once'] ) && get_option( "wp_register_plugin_shown_{$notice_id}" ) ) {
            return false;
        }

        // Check pages restriction
        if ( ! empty( $notice['pages'] ) && is_array( $notice['pages'] ) ) {
            $current_page  = $current_screen->id ?? '';
            $allowed_pages = array_map( function ( $page ) {
                return str_replace( '-', '_', $page );
            }, $notice['pages'] );

            if ( ! in_array( $current_page, $allowed_pages ) && ! in_array( str_replace( '_', '-', $current_page ), $notice['pages'] ) ) {
                return false;
            }
        }

        // Check show_until condition
        if ( ! empty( $notice['show_until'] ) ) {
            if ( is_callable( $notice['show_until'] ) ) {
                if ( ! call_user_func( $notice['show_until'] ) ) {
                    return false;
                }
            } elseif ( is_string( $notice['show_until'] ) && str_starts_with( $notice['show_until'], 'option_not_empty:' ) ) {
                $option_name = substr( $notice['show_until'], 17 );
                if ( ! empty( get_option( $option_name ) ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Render an admin notice.
     *
     * @param string $notice_id Notice ID
     * @param array  $notice    Notice configuration
     *
     * @since 1.0.0
     */
    private function render_admin_notice( string $notice_id, array $notice ): void {
        $type        = $notice['type'] ?? 'info';
        $message     = $notice['message'] ?? '';
        $dismissible = ! empty( $notice['dismissible'] );

        $classes = [ 'notice', "notice-{$type}" ];
        if ( $dismissible ) {
            $classes[] = 'is-dismissible';
        }

        // Mark as shown for show_once notices
        if ( ! empty( $notice['show_once'] ) ) {
            update_option( "wp_register_plugin_shown_{$notice_id}", true, false );
        }

        printf( '<div class="%s" data-notice-id="%s">', esc_attr( implode( ' ', $classes ) ), esc_attr( $notice_id ) );
        printf( '<p>%s</p>', wp_kses_post( $message ) );
        echo '</div>';

        // Add a dismissal script for custom handling
        if ( $dismissible ) {
            $this->add_notice_dismissal_script();
        }
    }

    /**
     * Add JavaScript for notice dismissal.
     *
     * @since 1.0.0
     */
    private function add_notice_dismissal_script(): void {
        static $script_added = false;

        if ( $script_added ) {
            return;
        }

        $script_added = true;
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $(document).on('click', '.notice[data-notice-id] .notice-dismiss', function () {
                    var $notice = $(this).closest('.notice[data-notice-id]');
                    var noticeId = $notice.data('notice-id');

                    if (noticeId) {
                        $.post(ajaxurl, {
                            action: 'wp_register_plugin_dismiss_notice',
                            notice_id: noticeId
                        });
                    }
                });
            });
        </script>
        <?php
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