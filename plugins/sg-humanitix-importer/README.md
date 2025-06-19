# Humanitix Importer

HUmanitix API importer for THe Events Calendar

## Installation

1. Clone this repository to your plugins directory:
   ```bash
   cd /wp-content/plugins/
   git clone [repository-url] sg-humanitix-importer
   ```

2. Install dependencies:
   ```bash
   cd sg-humanitix-importer
   composer install
   npm install
   ```

3. Build assets:
   ```bash
   npm run prod
   ```

4. Activate the plugin in WordPress admin.

## Development

### Prerequisites

- PHP 8.0+
- Node.js 16+
- Composer


### Build Commands

```bash
# Development build with watch
npm run dev

# Production build
npm run prod

# Clean build directory
npm run clean

# Lint PHP code
npm run lint:php

# Fix PHP code style
npm run lint:php:fix

# Lint JavaScript
npm run lint

# Fix JavaScript issues
npm run lint:fix

# Lint CSS/SCSS
npm run lint-css

# Fix CSS/SCSS issues
npm run lint-css:fix
```

## Architecture

This plugin follows the UTD plugin architecture pattern with the following structure:

```
sg-humanitix-importer/
├── src/                    # PHP source files
│   ├── Plugin.php          # Main plugin class
│   └── Assets.php          # Asset management
├── assets/
│   ├── src/                # Source assets
│   │   ├── js/             # JavaScript files
│   │   └── sass/           # SCSS files
│   └── build/              # Built assets (auto-generated)
├── inc/                    # Custom functions and includes
├── templates/              # Template files
└── examples/               # Usage examples
```



## Plugin Structure

### Main Classes

#### Plugin Class
The main plugin initialization class:

```php
<?php
use Utd\SG\HumanitixImporter\Plugin;

// The plugin automatically initializes when WordPress loads
$plugin = new Plugin();
```

#### Assets Class
Handles all asset enqueuing and optimization:

```php
<?php
use Utd\SG\HumanitixImporter\Assets;

// Automatically initialized by Plugin class
// Enqueues frontend and editor assets
// Handles script localization
```

### Plugin Classes

This plugin includes comprehensive utilities for WordPress development:

#### Security Classes

**AJAX Security Handler**
```php
<?php
use SG\HumanitixImporter\Security\AjaxSecurityHandler;

// Register secure AJAX action
AjaxSecurityHandler::register_action(
    'my_secure_action',
    array( $this, 'handle_action' ),
    array( 'capability_required' => 'edit_posts' ),
    array(
        'email' => array(
            'type' => 'email',
            'required' => true
        )
    )
);
```

**REST API Security Handler**
```php
<?php
use SG\HumanitixImporter\Security\RestApiSecurityHandler;

// Register secure REST endpoint
RestApiSecurityHandler::register_endpoint(
    '{{PLUGIN_SLUG}}/v1',
    '/data',
    array(
        'methods' => 'GET',
        'callback' => array( $this, 'get_data' )
    ),
    array(
        'capability_required' => 'read',
        'rate_limit' => true
    )
);
```

**Security Validator**
```php
<?php
use SG\HumanitixImporter\Security\SecurityValidator;

// Validate and sanitize user input
$clean_data = SecurityValidator::validate($input, 'email');
```

#### Block Manager
```php
<?php
// Custom blocks are managed automatically
// Add your own blocks by editing src/BlockManager.php
$block_manager = new BlockManager();
$block_manager->register_block('{{PLUGIN_SLUG}}/my-block', $config);
```

#### Patterns Manager
```php
<?php
// Block patterns are registered automatically
// Customize patterns in src/Patterns.php
$patterns = new Patterns();
$patterns->add_pattern('{{PLUGIN_SLUG}}/my-pattern', $pattern_data);
```

## Constants

The plugin defines the following constants:

- `SG_HUMANITIX_IMPORTER_PLUGIN_FILE` - Main plugin file path
- `SG_HUMANITIX_IMPORTER_PLUGIN_PATH` - Plugin directory path
- `SG_HUMANITIX_IMPORTER_PLUGIN_URL` - Plugin directory URL
- `SG_HUMANITIX_IMPORTER_PLUGIN_BUILD_PATH` - Built assets directory path
- `SG_HUMANITIX_IMPORTER_PLUGIN_BUILD_URL` - Built assets directory URL
- `SG_HUMANITIX_IMPORTER_PLUGIN_VERSION` - Plugin version

## JavaScript Global

The plugin exposes a global JavaScript object `sgHumanitixImporter` with:

```javascript
window.sgHumanitixImporter = {
    ajax_url: '/wp-admin/admin-ajax.php',
    nonce: 'security-nonce',
    plugin_url: '/wp-content/plugins/sg-humanitix-importer',
    version: '1.0.0'
};
```

## Hooks and Filters

### Actions
- `plugins_loaded` - Plugin initialization
- `wp_enqueue_scripts` - Frontend asset enqueuing
- `enqueue_block_assets` - Editor asset enqueuing

### Filters
- `script_loader_tag` - Script optimization
- `style_loader_tag` - Style optimization

## Contributing

1. Follow WordPress coding standards
2. Run linting before committing: `npm run precommit`
3. Write unit tests for new features
4. Update documentation for API changes

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release 