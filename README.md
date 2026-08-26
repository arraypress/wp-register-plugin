# Register Plugin

Boot a plugin only when what it needs is actually there, and say so plainly
when it is not.

## What it does

Every plugin has requirements — a PHP version, a WordPress version, another
plugin it extends — and the usual handling is a `version_compare()` at the
top of the main file, or nothing at all. Without it, a plugin installed on an
old PHP fatals on activation and takes the site with it.

This wraps the bootstrap. Requirements are checked first; if they are not met
the plugin does not load, and an admin notice explains which one failed and
what to do about it.

## Features

* Boot only when the PHP and WordPress versions are high enough
* Require another plugin, and check its version too
* Say which requirement failed, rather than white-screening
* Detect a conflicting plugin and refuse to run alongside it
* Get shorthand for the common hosts — EDD, WooCommerce, network-wide
* Deactivate cleanly instead of fatalling when something disappears later

## Installation

```bash
composer require arraypress/wp-register-plugin
```

## Quick start

In the plugin's main file, in place of calling your bootstrap directly:

```php
register_plugin( __FILE__, function () {
	MyPlugin::init();
}, [
	'requirements' => [
		'php' => '8.3',
		'wp'  => '7.1',
	],
] );
```

`MyPlugin::init()` runs only if both are satisfied. If not, nothing loads and
the notice names the version that is missing.

For a plugin that extends something:

```php
register_edd_plugin( __FILE__, function () {
	MyPlugin::init();
}, [
	'requirements' => [ 'easy-digital-downloads' => '3.6.1' ],
] );
```

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
