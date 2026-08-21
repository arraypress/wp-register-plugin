<?php
declare( strict_types=1 );

namespace ArrayPress\RegisterPlugin\Tests;

use ArrayPress\RegisterPlugin\Requirements;
use PHPUnit\Framework\TestCase;

/**
 * Deciding whether a plugin may load.
 *
 * A false negative here is the worst outcome this package can produce: the
 * plugin refuses to run and tells the site owner a dependency is missing that
 * is sitting right there, active.
 */
final class RequirementsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_test_active_plugins'] = [];
		$GLOBALS['wp_test_options']        = [];
		$GLOBALS['wp_test_wp_version']     = '6.8';
	}

	// -- Versioned dependencies -------------------------------------------

	public function test_a_satisfied_php_version_passes(): void {
		$this->assertTrue( ( new Requirements( [ 'php' => '8.0' ] ) )->met() );
	}

	public function test_an_unsatisfied_php_version_fails(): void {
		$this->assertFalse( ( new Requirements( [ 'php' => '99.0' ] ) )->met() );
	}

	public function test_the_wordpress_version_is_read(): void {
		$GLOBALS['wp_test_wp_version'] = '6.8';

		$this->assertTrue( ( new Requirements( [ 'wp' => '6.0' ] ) )->met() );
		$this->assertFalse( ( new Requirements( [ 'wp' => '7.0' ] ) )->met() );
	}

	public function test_an_equal_version_satisfies_the_minimum(): void {
		$this->assertTrue( ( new Requirements( [ 'wp' => '6.8' ] ) )->met() );
	}

	/**
	 * A constant-based check reads the version from the constant.
	 */
	public function test_a_constant_dependency_is_version_checked(): void {
		define( 'TEST_DEP_VERSION', '3.2.1' );

		$requirement = [ 'name' => 'Test Dep', 'check' => 'TEST_DEP_VERSION', 'type' => 'constant' ];

		$this->assertTrue( ( new Requirements( [ 'dep' => $requirement + [ 'minimum' => '3.0' ] ] ) )->met() );
		$this->assertFalse( ( new Requirements( [ 'dep' => $requirement + [ 'minimum' => '4.0' ] ] ) )->met() );
	}

	public function test_a_missing_constant_is_unmet(): void {
		$this->assertFalse( ( new Requirements( [
			'dep' => [ 'name' => 'Gone', 'check' => 'NOT_DEFINED_ANYWHERE', 'type' => 'constant', 'minimum' => '1.0' ],
		] ) )->met() );
	}

	// -- Dependencies with no version to read ------------------------------

	/**
	 * A class either exists or it does not; there is no version to compare.
	 * Requiring one used to leave the requirement permanently unmet, because a
	 * minimum of '1.0' was filled in whether or not the caller asked for one
	 * and then compared against a version that could never be read.
	 */
	public function test_an_existing_class_satisfies_a_requirement(): void {
		$this->assertTrue( ( new Requirements( [
			'dep' => [ 'name' => 'Some Class', 'check' => 'stdClass', 'type' => 'class' ],
		] ) )->met() );
	}

	public function test_a_missing_class_is_unmet(): void {
		$this->assertFalse( ( new Requirements( [
			'dep' => [ 'name' => 'Some Class', 'check' => 'No\\Such\\Class', 'type' => 'class' ],
		] ) )->met() );
	}

	/**
	 * The same fault made every plugin_active requirement unsatisfiable: an
	 * active WooCommerce reported "Missing WooCommerce: minimum required 1.0".
	 */
	public function test_an_active_plugin_satisfies_a_requirement(): void {
		$GLOBALS['wp_test_active_plugins'] = [ 'woocommerce/woocommerce.php' ];

		$this->assertTrue( ( new Requirements( [
			'woo' => [ 'name' => 'WooCommerce', 'check' => 'woocommerce/woocommerce.php', 'type' => 'plugin_active' ],
		] ) )->met() );
	}

	public function test_an_inactive_plugin_is_unmet(): void {
		$this->assertFalse( ( new Requirements( [
			'woo' => [ 'name' => 'WooCommerce', 'check' => 'woocommerce/woocommerce.php', 'type' => 'plugin_active' ],
		] ) )->met() );
	}

	/**
	 * Asking for a version that cannot be read is not the same as asking for
	 * nothing. Reporting it as met would claim a check that never happened.
	 */
	public function test_a_version_that_cannot_be_read_is_unmet(): void {
		$this->assertFalse( ( new Requirements( [
			'dep' => [ 'name' => 'Some Class', 'check' => 'stdClass', 'type' => 'class', 'minimum' => '2.0' ],
		] ) )->met() );
	}

	// -- Messages ----------------------------------------------------------

	/**
	 * Naming a minimum nobody asked for reads as a version problem when the
	 * dependency is simply absent.
	 */
	public function test_a_missing_dependency_without_a_minimum_says_only_that(): void {
		$requirements = new Requirements( [
			'dep' => [ 'name' => 'Some Plugin', 'check' => 'nope/nope.php', 'type' => 'plugin_active' ],
		] );
		$requirements->met();

		$message = $requirements->get_errors()->get_error_message();

		$this->assertStringContainsString( 'Some Plugin', $message );
		$this->assertStringNotContainsString( 'minimum required', $message );
	}

	public function test_an_outdated_dependency_reports_both_versions(): void {
		$requirements = new Requirements( [ 'php' => '99.0' ] );
		$requirements->met();

		$message = $requirements->get_errors()->get_error_message();

		$this->assertStringContainsString( '99.0', $message );
		$this->assertStringContainsString( PHP_VERSION, $message );
	}

	public function test_errors_are_keyed_by_requirement(): void {
		$requirements = new Requirements( [ 'php' => '99.0', 'wp' => '99.0' ] );
		$requirements->met();

		$this->assertSame(
			[ 'requirement_php', 'requirement_wp' ],
			$requirements->get_errors()->get_error_codes()
		);
	}

	public function test_a_satisfied_set_reports_no_errors(): void {
		$requirements = new Requirements( [ 'php' => '8.0' ] );
		$requirements->met();

		$this->assertFalse( $requirements->get_errors()->has_errors() );
	}

	// -- Conflicts ---------------------------------------------------------

	public function test_an_active_conflicting_plugin_blocks_loading(): void {
		$GLOBALS['wp_test_active_plugins'] = [ 'other/other.php' ];

		$requirements = new Requirements( [], [
			'other' => [ 'name' => 'Other', 'plugin' => 'other/other.php', 'action' => 'notice' ],
		] );

		$this->assertFalse( $requirements->met() );
	}

	public function test_an_inactive_conflicting_plugin_does_not(): void {
		$requirements = new Requirements( [], [
			'other' => [ 'name' => 'Other', 'plugin' => 'other/other.php', 'action' => 'notice' ],
		] );

		$this->assertTrue( $requirements->met() );
	}

	/**
	 * A conflict can be narrowed: present, but only a problem under some
	 * condition the caller decides.
	 */
	public function test_a_conflict_condition_can_clear_it(): void {
		$GLOBALS['wp_test_active_plugins'] = [ 'other/other.php' ];

		$requirements = new Requirements( [], [
			'other' => [
				'name'      => 'Other',
				'plugin'    => 'other/other.php',
				'action'    => 'notice',
				'condition' => static fn(): bool => false,
			],
		] );

		$this->assertTrue( $requirements->met() );
	}

	public function test_a_conflict_is_reported(): void {
		$GLOBALS['wp_test_active_plugins'] = [ 'other/other.php' ];

		$requirements = new Requirements( [], [
			'other' => [ 'name' => 'Other', 'plugin' => 'other/other.php', 'action' => 'notice' ],
		] );
		$requirements->met();

		$this->assertSame( [ 'conflict_other' ], $requirements->get_errors()->get_error_codes() );
	}

	public function test_a_custom_conflict_message_is_used(): void {
		$GLOBALS['wp_test_active_plugins'] = [ 'other/other.php' ];

		$requirements = new Requirements( [], [
			'other' => [
				'name'    => 'Other',
				'plugin'  => 'other/other.php',
				'action'  => 'notice',
				'message' => 'Turn off Other first.',
			],
		] );
		$requirements->met();

		$this->assertSame( 'Turn off Other first.', $requirements->get_errors()->get_error_message() );
	}

	// -- Shorthand ---------------------------------------------------------

	public function test_a_bare_string_is_read_as_a_minimum_version(): void {
		$this->assertTrue( ( new Requirements( [ 'php' => '8.0' ] ) )->met() );
	}

	public function test_a_custom_callback_supplies_existence_and_version(): void {
		$requirements = new Requirements( [
			'dep' => [
				'name'    => 'Callback Dep',
				'check'   => static fn(): array => [ 'exists' => true, 'version' => '5.0' ],
				'type'    => 'callback',
				'minimum' => '4.0',
			],
		] );

		$this->assertTrue( $requirements->met() );
	}

	public function test_a_callback_reporting_an_old_version_is_unmet(): void {
		$requirements = new Requirements( [
			'dep' => [
				'name'    => 'Callback Dep',
				'check'   => static fn(): array => [ 'exists' => true, 'version' => '1.0' ],
				'type'    => 'callback',
				'minimum' => '4.0',
			],
		] );

		$this->assertFalse( $requirements->met() );
	}

	public function test_an_empty_requirement_set_is_met(): void {
		$this->assertTrue( ( new Requirements() )->met() );
	}
}
