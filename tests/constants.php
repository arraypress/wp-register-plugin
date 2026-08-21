<?php
/**
 * Constants that must exist before Composer's autoloader runs.
 *
 * src/Functions.php is a `files` autoload entry opening with
 * `defined( 'ABSPATH' ) || exit;`. The phpunit binary requires the autoloader
 * before it reads the bootstrap in phpunit.xml, so defining ABSPATH there is
 * already too late -- the process exits with status 0 and no output, which
 * looks exactly like a test suite that found nothing to run.
 *
 * Loaded via auto_prepend_file; see the `test` script in composer.json.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}
