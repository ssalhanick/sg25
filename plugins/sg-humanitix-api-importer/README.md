# Humanitix API Importer

A WordPress plugin to import events from the Humanitix API into The Events Calendar plugin.

## Features

- Import events from Humanitix API
- Automatic venue and organizer creation
- Comprehensive logging and debugging
- Admin interface for configuration
- Support for recurring imports

## Installation

1. Upload the plugin files to `/wp-content/plugins/sg-humanitix-api-importer/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure your API settings in the admin panel

## Configuration

### API Settings

1. Go to **WordPress Admin** → **Humanitix** → **Settings**
2. Enter your Humanitix API key
3. Enter your Organization ID
4. Optionally set a custom API endpoint
5. Test your connection using the "Test API Connection" button

### Debug Mode

For plugin authors and developers, debug mode can be enabled in several ways:

#### Method 1: Define Debug Constant
Add this to your `wp-config.php`:
```php
define( 'HUMANITIX_DEBUG', true );
```

#### Method 2: WordPress Debug Mode
If `WP_DEBUG` is enabled, debug mode will be automatically available.

#### Method 3: User Capabilities
Debug mode is available to users with:
- `manage_network_options` capability
- Administrator role with `edit_plugins` capability

#### Method 4: Specific User IDs
Add your WordPress user ID to the `$debug_user_ids` array in `src/Admin/AdminInterface.php`.

### Debug Features

When debug mode is enabled, you'll see a **Debug** menu item that provides:
- WordPress debug settings status
- Plugin configuration details
- API connection test results
- Recent logs and activity
- Event import debugging information

## Usage

### Manual Import

1. Go to **WordPress Admin** → **Humanitix**
2. Click **"Start Import"** to manually import events
3. Monitor the import progress and results

### Automatic Import

1. Enable automatic imports in the settings
2. Set your preferred import frequency
3. The plugin will automatically import events according to your schedule

## Troubleshooting

### Common Issues

1. **API Connection Failed**
   - Verify your API key and organization ID
   - Check that your API key has the correct permissions
   - Ensure your organization ID is correct

2. **No Events Imported**
   - Check the debug page for detailed information
   - Verify that your organization has events in Humanitix
   - Check the API response format

3. **Import Errors**
   - Review the logs in the admin panel
   - Check for conflicts with other plugins
   - Verify The Events Calendar plugin is active

### Debug Information

When debug mode is enabled, you can access detailed debugging information:
- API request/response logs
- Event mapping details
- Import process step-by-step logs
- Error details and stack traces

## Development

### File Structure

```
sg-humanitix-api-importer/
├── src/
│   ├── Admin/
│   │   ├── AdminInterface.php
│   │   ├── Logger.php
│   │   └── SettingsManager.php
│   ├── Importer/
│   │   ├── DataMapper.php
│   │   └── EventsImporter.php
│   ├── Security/
│   │   ├── AjaxSecurityHandler.php
│   │   ├── RestApiSecurityHandler.php
│   │   └── SecurityValidator.php
│   ├── Assets.php
│   ├── HumanitixAPI.php
│   └── Plugin.php
├── assets/
├── composer.json
└── README.md
```

### Adding Debug Information

To add debug information to your code:

```php
// Log information
$this->logger->log( 'info', 'Your message here', array( 'context' => 'data' ) );

// Error logging
$this->logger->log( 'error', 'Error message', array( 'error_details' => $error ) );

// Success logging
$this->logger->log( 'success', 'Operation completed', array( 'results' => $results ) );
```

## Support

For support and bug reports, please use the plugin's debug features to gather detailed information about any issues.

## License

This plugin is licensed under the GPL v2 or later. 