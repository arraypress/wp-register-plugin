<?php
declare( strict_types=1 );

namespace ArrayPress\RegisterPlugin\Tests;

use ArrayPress\RegisterPlugin\Plugin;
use ArrayPress\RegisterPlugin\Requirements;
use PHPUnit\Framework\TestCase;

/**
 * Folding a caller's config into a preset's defaults.
 *
 * register_edd_plugin() and register_woocommerce_plugin() used
 * array_merge_recursive() here, which keeps both values for a repeated string
 * key instead of letting the second win. A plugin asking for PHP 8.3 over the
 * default 7.4 therefore got [ '7.4', '8.3' ] -- and because that is an array,
 * add_requirement() stopped reading it as a version and left the minimum
 * empty. The declared floor was then never enforced, silently, which is worse
 * than declaring none: the plugin loads on a version it said it could not run
 * on and fatals somewhere further in.
 */
final class MergeConfigTest extends TestCase {

	/**
	 * The defaults register_edd_plugin() applies.
	 *
	 * @return array<string, mixed>
	 */
	private function edd_defaults(): array {
		return [
			'requirements' => [
				'php'                    => '7.4',
				'wp'                     => '6.8.4',
				'easy-digital-downloads' => '3.6.1',
			],
			'priority'     => 99,
		];
	}

	// -- Overriding -------------------------------------------------------

	public function test_a_caller_requirement_replaces_the_default(): void {
		$merged = Plugin::merge_config(
			$this->edd_defaults(),
			[ 'requirements' => [ 'php' => '8.3' ] ]
		);

		$this->assertSame( '8.3', $merged['requirements']['php'] );
	}

	public function test_the_replaced_requirement_is_still_a_string(): void {
		$merged = Plugin::merge_config(
			$this->edd_defaults(),
			[ 'requirements' => [ 'php' => '8.3' ] ]
		);

		$this->assertIsString(
			$merged['requirements']['php'],
			'An array here reads to add_requirement() as a full argument list, not a version, and the minimum is dropped.'
		);
	}

	public function test_requirements_the_caller_did_not_mention_are_kept(): void {
		$merged = Plugin::merge_config(
			$this->edd_defaults(),
			[ 'requirements' => [ 'php' => '8.3' ] ]
		);

		$this->assertSame( '6.8.4', $merged['requirements']['wp'] );
		$this->assertSame( '3.6.1', $merged['requirements']['easy-digital-downloads'] );
	}

	public function test_a_scalar_default_is_overridden_not_paired(): void {
		$merged = Plugin::merge_config( $this->edd_defaults(), [ 'priority' => 5 ] );

		$this->assertSame( 5, $merged['priority'] );
	}

	public function test_a_default_the_caller_omits_survives(): void {
		$merged = Plugin::merge_config( $this->edd_defaults(), [ 'textdomain' => 'x' ] );

		$this->assertSame( 99, $merged['priority'] );
		$this->assertSame( 'x', $merged['textdomain'] );
	}

	public function test_a_caller_key_with_no_default_is_kept(): void {
		$merged = Plugin::merge_config( $this->edd_defaults(), [ 'constants' => 'EDD_THING' ] );

		$this->assertSame( 'EDD_THING', $merged['constants'] );
	}

	public function test_a_caller_can_add_a_requirement_of_its_own(): void {
		$merged = Plugin::merge_config(
			$this->edd_defaults(),
			[ 'requirements' => [ 'edd-recurring' => '2.0' ] ]
		);

		$this->assertSame( '2.0', $merged['requirements']['edd-recurring'] );
		$this->assertSame( '7.4', $merged['requirements']['php'] );
	}

	// -- What the bug actually cost ---------------------------------------

	public function test_an_overridden_floor_is_enforced(): void {
		$GLOBALS['wp_test_wp_version'] = '6.8';

		$merged = Plugin::merge_config(
			[ 'requirements' => [ 'php' => '5.6' ] ],
			[ 'requirements' => [ 'php' => '999.0' ] ]
		);

		$this->assertFalse(
			( new Requirements( $merged['requirements'] ) )->met(),
			'PHP 999 cannot be satisfied, so the plugin must refuse to load.'
		);
	}

	public function test_array_merge_recursive_would_not_have_enforced_it(): void {
		$broken = array_merge_recursive(
			[ 'requirements' => [ 'php' => '5.6' ] ],
			[ 'requirements' => [ 'php' => '999.0' ] ]
		);

		$this->assertTrue(
			( new Requirements( $broken['requirements'] ) )->met(),
			'Pinned so the regression is visible: the old merge let an impossible floor pass.'
		);
	}
}
