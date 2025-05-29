<?php
/**
 * WP Register Plugin Utility Functions
 *
 * @package     ArrayPress\WPRegisterPlugin
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      ArrayPress Team
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use ArrayPress\WP\Register\Plugin;

if ( ! function_exists( 'register_plugin' ) ):
	/**
	 * Register a plugin with requirement checking
	 *
	 * @param string   $file      Plugin file (__FILE__)
	 * @param callable $bootstrap Bootstrap function
	 * @param array    $config    Configuration options
	 *
	 * @return void
	 */
	function register_plugin( string $file, callable $bootstrap, array $config = [] ): void {
		$config['file']      = $file;
		$config['bootstrap'] = $bootstrap;

		Plugin::register( $config );
	}
endif;

if ( ! function_exists( 'register_edd_plugin' ) ):
	/**
	 * Register an EDD plugin with common defaults
	 *
	 * @param string   $file      Plugin file (__FILE__)
	 * @param callable $bootstrap Bootstrap function
	 * @param array    $config    Additional configuration
	 *
	 * @return void
	 */
	function register_edd_plugin( string $file, callable $bootstrap, array $config = [] ): void {
		$defaults = [
			'requirements' => [
				'php'                    => '7.4',
				'wp'                     => '6.8.1',
				'easy-digital-downloads' => '3.3.9',
			],
		];

		$config = array_merge_recursive( $defaults, $config );
		register_plugin( $file, $bootstrap, $config );
	}
endif;

if ( ! function_exists( 'register_woocommerce_plugin' ) ):
	/**
	 * Register a WooCommerce plugin with common defaults
	 *
	 * @param string   $file      Plugin file (__FILE__)
	 * @param callable $bootstrap Bootstrap function
	 * @param array    $config    Additional configuration
	 *
	 * @return void
	 */
	function register_woocommerce_plugin( string $file, callable $bootstrap, array $config = [] ): void {
		$defaults = [
			'requirements' => [
				'php'         => '7.4',
				'wp'          => '6.8.1',
				'woocommerce' => '9.8.5',
			],
			'setup_hooks'  => [
				'woocommerce_compatibility' => [
					'features'   => [ 'custom_order_tables' ],
					'compatible' => true,
				],
			],
		];

		$config = array_merge_recursive( $defaults, $config );
		register_plugin( $file, $bootstrap, $config );
	}
endif;

if ( ! function_exists( 'register_pro_plugin' ) ):
	/**
	 * Register a Pro plugin that conflicts with its free version
	 *
	 * @param string   $file        Plugin file (__FILE__)
	 * @param callable $bootstrap   Bootstrap function
	 * @param string   $free_plugin Free version plugin path
	 * @param array    $config      Additional configuration
	 *
	 * @return void
	 */
	function register_pro_plugin( string $file, callable $bootstrap, string $free_plugin, array $config = [] ): void {
		$defaults = [
			'requirements' => [
				'php' => '7.4',
				'wp'  => '6.0',
			],
			'conflicts'    => [
				'free-version' => $free_plugin,
			],
			'plugin_links' => [
				'support' => 'https://support.example.com', // Default pro support
			],
		];

		$config = array_merge_recursive( $defaults, $config );
		register_plugin( $file, $bootstrap, $config );
	}
endif;