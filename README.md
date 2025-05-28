# WordPress Plugin Registration - Simplified Plugin Development Made Easy

A universal WordPress plugin registration system that handles requirements checking and conflict detection automatically. No more boilerplate code - just register your plugin with smart defaults and get back to building features.

## Features

* 🎯 **Dead Simple API**: One line to register most plugins
* 🔧 **Smart Defaults**: Pre-configured for EDD, WooCommerce, Elementor ecosystems
* ✅ **Requirements Checking**: Automatic PHP, WordPress, and plugin dependency validation
* ❌ **Conflict Detection**: Auto-deactivates conflicting plugins (like free vs pro versions)
* 🎨 **Professional Errors**: Beautiful WordPress-native error display in admin
* 🔗 **Action Links**: Automatic Requirements and Support links in plugin list
* ⚡ **Zero Configuration**: Works out of the box with sensible defaults

## Requirements

* PHP 7.4 or later
* WordPress 6.8.1 or later

## Installation

Install via Composer:

```bash
composer require arraypress/wp-register-plugin
```

## Basic Usage

You can use either the Plugin class directly or the utility functions:

```php
// Using utility functions (recommended)
register_plugin( __FILE__, function() {
    MyPlugin::init();
}, [
    'requirements' => [
        'php' => '7.4',
        'wp' => '6.0',
    ],
] );

// Using the Plugin class directly
use ArrayPress\WP\Register\Plugin;

Plugin::register([
    'file' => __FILE__,
    'bootstrap' => function() {
        MyPlugin::init();
    },
    'requirements' => [
        'php' => '7.4',
        'wp' => '6.0',
    ],
]);
```

### Utility Functions

The package provides convenient utility functions for common scenarios:

```php
// Basic plugin registration
register_plugin( __FILE__, function() {
    MyPlugin::init();
} );

// EDD extension with defaults
register_edd_plugin( __FILE__, function() {
    MyEDDExtension::init();
} );

// WooCommerce plugin with defaults
register_woocommerce_plugin( __FILE__, function() {
    MyWooPlugin::init();
} );

// Pro plugin that conflicts with free version
register_pro_plugin( __FILE__, function() {
    MyProPlugin::init();
}, 'my-plugin-free/plugin.php' );
```

## Examples

### EDD Extension

```php
// Simple EDD extension - uses smart defaults
register_edd_plugin( __FILE__, function() {
    MyEDDExtension::init();
} );

// EDD extension with custom requirements
register_edd_plugin( __FILE__, function() {
    MyAdvancedEDDExtension::init();
}, [
    'requirements' => [
        'easy-digital-downloads' => '3.2.0', // Override default
        'php' => '8.0', // Higher PHP requirement
    ],
    'activation' => function() {
        MyAdvancedEDDExtension\Installer::create_tables();
    },
    'success' => function() {
        update_option( 'my_edd_extension_loaded', true );
    },
] );
```

### WooCommerce Plugin

```php
// Simple WooCommerce plugin
register_woocommerce_plugin( __FILE__, function() {
    MyWooPlugin::init();
} );

// Advanced WooCommerce plugin with custom config
register_woocommerce_plugin( __FILE__, function() {
    MyProWooPlugin::init();
}, [
    'requirements' => [
        'php' => '8.1',
        'woocommerce' => '7.0.0',
        'memory_limit' => [
            'name' => 'PHP Memory Limit',
            'check' => function() {
                return ini_get('memory_limit');
            },
            'exists' => function() {
                $memory = wp_convert_hr_to_bytes( ini_get('memory_limit') );
                return $memory >= wp_convert_hr_to_bytes( '512M' );
            },
            'type' => 'callback',
        ],
    ],
    'activation' => function() {
        MyProWooPlugin\Installer::setup();
    },
    'requirements_url' => 'https://docs.my-plugin.com/requirements',
    'support_url' => 'https://support.my-plugin.com',
] );
```

### Pro Plugin with Free Version Conflict

```php
// Pro plugin that auto-deactivates free version
register_pro_plugin( __FILE__, function() {
    MyProPlugin::init();
}, 'my-plugin-free/my-plugin-free.php' );

// Pro plugin with additional configuration
register_pro_plugin( __FILE__, function() {
    MyEDDPro::init();
}, 'my-edd-extension/my-edd-extension.php', [
    'requirements' => [
        'php' => '8.0',
        'easy-digital-downloads' => '3.2.0',
    ],
    'activation' => function() {
        MyEDDPro\License::activate();
        MyEDDPro\Installer::create_tables();
    },
    'success' => function() {
        update_option( 'my_edd_pro_active', true );
        do_action( 'my_edd_pro_loaded' );
    },
    'requirements_url' => 'https://docs.my-plugin.com/pro-requirements',
] );
```

### Complex Plugin with Custom Requirements

```php
use ArrayPress\WP\Register\Plugin;

Plugin::register([
    'file' => __FILE__,
    'bootstrap' => function() {
        MyComplexPlugin::init();
    },
    'requirements' => [
        'php' => '8.1',
        'wp' => '6.3',
        'woocommerce' => '7.0.0',
        'curl_extension' => [
            'name' => 'PHP cURL Extension',
            'exists' => function() {
                return extension_loaded( 'curl' );
            },
        ],
        'memory_limit' => [
            'name' => 'PHP Memory Limit',
            'check' => function() {
                return ini_get('memory_limit');
            },
            'exists' => function() {
                $memory = wp_convert_hr_to_bytes( ini_get('memory_limit') );
                return $memory >= wp_convert_hr_to_bytes( '256M' );
            },
            'type' => 'callback',
        ],
    ],
    'conflicts' => [
        'competitor' => [
            'name' => 'Competitor Plugin',
            'check' => 'COMPETITOR_VERSION',
            'type' => 'constant',
            'action' => 'block',
            'message' => '<strong>Conflict:</strong> Please deactivate Competitor Plugin first.',
        ],
        'old_version' => [
            'name' => 'Outdated Version',
            'check' => 'MY_PLUGIN_OLD_VERSION',
            'type' => 'constant',
            'condition' => function( $result ) {
                return $result['exists'] && version_compare( $result['version'], '2.0.0', '<' );
            },
            'action' => 'block',
            'message' => '<strong>Update Required:</strong> Please update to version 2.0+ first.',
        ],
    ],
    'priority' => 5,
    'early_includes' => [
        __DIR__ . '/vendor/autoload.php',
    ],
    'activation' => function() {
        MyComplexPlugin\Installer::create_tables();
        flush_rewrite_rules();
    },
    'deactivation' => function() {
        MyComplexPlugin\Cleanup::remove_cron_jobs();
        flush_rewrite_rules();
    },
    'success' => function() {
        MyComplexPlugin\Analytics::track_load();
    },
    'requirements_url' => 'https://docs.my-plugin.com/requirements',
    'support_url' => 'https://support.my-plugin.com',
    'error_message' => 'My Complex Plugin cannot be activated due to unmet requirements.',
]);
```

## Built-in Ecosystem Support

### Easy Digital Downloads
```php
register_edd_plugin( __FILE__, $callback );
// Defaults: PHP 7.4+, WordPress 6.0+, EDD 3.0.0+
```

### WooCommerce
```php
register_woocommerce_plugin( __FILE__, $callback );
// Defaults: PHP 7.4+, WordPress 6.0+, WooCommerce 6.0.0+
```

### Pro Plugins
```php
register_pro_plugin( __FILE__, $callback, 'free-plugin/plugin.php' );
// Defaults: PHP 7.4+, WordPress 6.0+, auto-deactivates free version
```

## Configuration Options

### Requirements
```php
'requirements' => [
    // Built-in dependencies
    'php' => '8.0',
    'wp' => '6.3',
    'easy-digital-downloads' => '3.2.0',
    'woocommerce' => '7.0.0',
    'elementor' => '3.8.0',
    'advanced-custom-fields' => '6.0.0',
    
    // Custom requirements
    'custom_check' => [
        'name' => 'Custom Requirement',
        'minimum' => '2.0.0',
        'check' => 'MY_PLUGIN_VERSION', // Constant
        'type' => 'constant',
    ],
    
    // PHP extension check
    'gd_extension' => [
        'name' => 'PHP GD Extension',
        'exists' => function() {
            return extension_loaded( 'gd' );
        },
    ],
    
    // Custom callback
    'api_key' => [
        'name' => 'API Key Configuration',
        'exists' => function() {
            return ! empty( get_option( 'my_api_key' ) );
        },
    ],
]
```

### Conflicts
```php
'conflicts' => [
    // Plugin conflict (auto-deactivate by default)
    'free-version' => 'my-plugin-free/plugin.php',
    
    // Custom conflict with blocking
    'competitor' => [
        'name' => 'Competitor Plugin',
        'check' => 'competitor-plugin/plugin.php',
        'type' => 'plugin_active',
        'action' => 'block',
        'message' => 'Please deactivate Competitor Plugin first.',
    ],
    
    // Version-based conflict
    'old_version' => [
        'check' => 'OLD_PLUGIN_VERSION',
        'type' => 'constant',
        'condition' => function( $result ) {
            return $result['exists'] && version_compare( $result['version'], '2.0.0', '<' );
        },
        'message' => 'Please update the old version first.',
    ],
]
```

### Hooks
```php
'activation' => function() {
    // Run on plugin activation
    MyPlugin\Installer::create_tables();
    flush_rewrite_rules();
},

'deactivation' => function() {
    // Run on plugin deactivation
    MyPlugin\Cleanup::remove_cron_jobs();
},

'success' => function() {
    // Run after successful requirements check
    update_option( 'my_plugin_loaded', true );
    do_action( 'my_plugin_ready' );
},
```

## Error Display

When requirements aren't met or conflicts are detected, the system automatically:

- **Prevents plugin activation** - Your bootstrap callback won't execute
- **Shows professional errors** - Clean, WordPress-native error messages
- **Highlights plugin row** - Visual indicators in the plugins list
- **Adds action links** - Requirements and Support links for easy access
- **Provides specific details** - Exact version requirements and conflict information

## API Reference

### Plugin Class

```php
// Register plugin with full configuration
Plugin::register( array $config );
```

### Utility Functions

```php
// Basic registration
register_plugin( string $file, callable $bootstrap, array $config = [] );

// Ecosystem-specific registration
register_edd_plugin( string $file, callable $bootstrap, array $config = [] );
register_woocommerce_plugin( string $file, callable $bootstrap, array $config = [] );

// Pro plugin registration
register_pro_plugin( string $file, callable $bootstrap, string $free_plugin, array $config = [] );
```

### Configuration Array

```php
[
    'file' => string,           // Plugin file (__FILE__)
    'bootstrap' => callable,    // Function to execute when requirements are met
    'requirements' => array,    // Requirements that must be met
    'conflicts' => array,       // Conflicts that must not exist
    'priority' => int,          // plugins_loaded priority (default: 10)
    'early_includes' => array,  // Files to include before bootstrap
    'activation' => callable,   // Activation hook callback
    'deactivation' => callable, // Deactivation hook callback
    'success' => callable,      // Success callback after requirements check
    'requirements_url' => string, // URL for requirements documentation
    'support_url' => string,    // URL for support
    'error_message' => string,  // Custom error message
]
```

## Requirements Detection Types

- **`constant`** - Check if a PHP constant is defined
- **`function`** - Call a function to get version
- **`function_with_args`** - Call a function with arguments
- **`class`** - Check if a PHP class exists
- **`plugin_active`** - Check if a plugin is active
- **`callback`** - Custom callback function
- **`auto`** - Auto-detect the best method

## Conflict Actions

- **`auto_deactivate`** (default) - Automatically deactivate conflicting plugin
- **`block`** - Prevent activation and show error message

## Copy-Paste Templates

### EDD Extension Template
```php
register_edd_plugin( __FILE__, function() {
    YourEDDPlugin::init();
} );
```

### WooCommerce Extension Template
```php
register_woocommerce_plugin( __FILE__, function() {
    YourWooPlugin::init();
} );
```

### Pro Plugin Template
```php
register_pro_plugin( __FILE__, function() {
    YourProPlugin::init();
}, 'your-free-plugin/plugin.php' );
```

### Basic Plugin Template
```php
register_plugin( __FILE__, function() {
    YourPlugin::init();
}, [
    'requirements' => [
        'php' => '7.4',
        'wp' => '6.0',
    ],
] );
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

## License

Licensed under the GPLv2 or later license.

## Support

- [Issue Tracker](https://github.com/arraypress/wp-register-plugin/issues)