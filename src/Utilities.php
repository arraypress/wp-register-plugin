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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	function register_edd_plugin( string $file, callable $bootstrap, array $config = [] ): void {
		$defaults = [
			'requirements' => [
				'php'                    => '7.4',
				'wp'                     => '6.0',
				'easy-digital-downloads' => '3.0',
			],
			'priority'     => 99, // Load after EDD ExtensionLoader (priority 98)
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
	 * @since 1.0.0
	 */
	function register_woocommerce_plugin( string $file, callable $bootstrap, array $config = [] ): void {
		$defaults = [
			'requirements' => [
				'php'         => '7.4',
				'wp'          => '6.0',
				'woocommerce' => '5.0',
			],
			'setup_hooks'  => [
				'woocommerce_compatibility' => [
					'features'   => [ 'custom_order_tables' ],
					'compatible' => true,
				],
			],
			'priority'     => 20, // Load after WooCommerce (which loads at priority 10)
		];

		$config = array_merge_recursive( $defaults, $config );
		register_plugin( $file, $bootstrap, $config );
	}
endif;

if ( ! function_exists( 'register_elementor_plugin' ) ):
	/**
	 * Register an Elementor plugin with common defaults
	 *
	 * @param string   $file      Plugin file (__FILE__)
	 * @param callable $bootstrap Bootstrap function
	 * @param array    $config    Additional configuration
	 *
	 * @return void
	 * @since 1.0.0
	 */
	function register_elementor_plugin( string $file, callable $bootstrap, array $config = [] ): void {
		$defaults = [
			'requirements' => [
				'php'       => '7.4',
				'wp'        => '6.0',
				'elementor' => '3.0.0',
			],
			'priority'     => 20, // Load after Elementor
		];

		$config = array_merge_recursive( $defaults, $config );
		register_plugin( $file, $bootstrap, $config );
	}
endif;

if ( ! function_exists( 'register_acf_plugin' ) ):
	/**
	 * Register an ACF plugin with common defaults
	 *
	 * @param string   $file      Plugin file (__FILE__)
	 * @param callable $bootstrap Bootstrap function
	 * @param array    $config    Additional configuration
	 *
	 * @return void
	 * @since 1.0.0
	 */
	function register_acf_plugin( string $file, callable $bootstrap, array $config = [] ): void {
		$defaults = [
			'requirements' => [
				'php'                    => '7.4',
				'wp'                     => '6.0',
				'advanced-custom-fields' => '5.0.0',
			],
			'priority'     => 20, // Load after ACF
		];

		$config = array_merge_recursive( $defaults, $config );
		register_plugin( $file, $bootstrap, $config );
	}
endif;