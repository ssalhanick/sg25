# Humanitix API Importer

A WordPress plugin to import events from the Humanitix API into The Events Calendar plugin.

## Features

- Import events from Humanitix API
- Automatic venue and organizer creation
- Comprehensive logging and debugging
- Admin interface for configuration
- Support for recurring imports
- Optional HTTP 410 (Gone) for deleted events with configurable retention (TTL)

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

### Deleted Content (410) Settings

1. Go to **WordPress Admin** → **Humanitix** → **Settings** → Archive Settings
2. Enable **410 for Deleted Content** (on by default)
3. Set **410 Retention (days)**. Default: `365` (set `0` to never expire)
4. Save changes

What this does: when an event is trashed or deleted, its URL is recorded. Future requests to that exact URL return HTTP 410 Gone instead of 404 Not Found, until the retention period expires or the event is restored.

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

### Verifying 410 for a deleted event

- Browser: Open DevTools → Network → request → confirm Status `410`
- PowerShell (Windows):
  - `curl -I "https://your-site.test/events/old-event-slug/"`
  - or `iwr -Method Head "https://your-site.test/events/old-event-slug/" -UseBasicParsing | Select-Object StatusCode,StatusDescription,Headers`

If the slug was reused by a new post, it will no longer be a 404 and won’t return 410.

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
│   ├── Templates/
│   │   ├── Assets/
│   │   │   ├── css/
│   │   │   │   └── templates.css
│   │   │   └── js/
│   │   │       └── templates.js
│   │   ├── Hooks/
│   │   │   └── TemplateHooks.php
│   │   ├── Overrides/
│   │   └── TemplateManager.php
│   ├── Security/
│   │   ├── AjaxSecurityHandler.php
│   │   ├── RestApiSecurityHandler.php
│   │   ├── SecurityValidator.php
│   │   └── ContentGoneHandler.php
│   ├── Assets.php
│   ├── HumanitixAPI.php
│   └── Plugin.php
├── tests/
│   ├── test_template_module.php
│   ├── test_template_validation.php
│   ├── run_template_tests.php
│   └── execute_template_tests.php
├── assets/
├── composer.json
└── README.md
```

### Template Module

The plugin includes a comprehensive TEC (The Events Calendar) template customization module that allows you to:

- **Customize TEC Templates**: Override default TEC templates with custom versions
- **Hook into TEC Events**: Add custom functionality to event displays
- **Manage Assets**: Load custom CSS/JS only on TEC pages
- **Conditional Loading**: Module only activates when TEC is present

#### Template Module Components

- **TemplateManager**: Main entry point for template functionality
- **TemplateHooks**: Handles all TEC-specific hooks and filters
- **TemplateAssets**: Manages CSS/JS assets for template customizations
- **Template Overrides**: Custom template files in `src/Templates/Overrides/`

#### Template Customization Features

- **Event Title Customization**: Modify event titles via hooks
- **Event Meta Customization**: Customize event metadata display
- **Venue Customization**: Customize venue information display
- **Organizer Customization**: Customize organizer information display
- **Asset Management**: Load custom CSS/JS conditionally
- **Template Overrides**: Override TEC template files

### Testing Framework

The plugin includes a comprehensive testing framework for the template module:

#### Test Files

- **`test_template_module.php`**: Unit tests for core template functionality
- **`test_template_validation.php`**: Real-world validation tests
- **`run_template_tests.php`**: Comprehensive test runner
- **`execute_template_tests.php`**: Automated test executor

#### Running Tests

##### Method 1: Individual Test Files
```php
// Run unit tests only
include 'tests/test_template_module.php';

// Run validation tests only
include 'tests/test_template_validation.php';

// Run comprehensive tests only
include 'tests/run_template_tests.php';
```

##### Method 2: Automated Test Runner
```php
// Run all tests automatically
include 'tests/execute_template_tests.php';
```

##### Method 3: WordPress Admin
1. Enable `WP_DEBUG` in `wp-config.php`
2. Navigate to WordPress admin
3. Tests run automatically when plugin loads

#### Test Coverage

The testing framework covers:

- **Module Initialization**: TemplateManager singleton pattern and TEC detection
- **Hook System**: All TEC-specific hooks registration and functionality
- **Asset Management**: CSS/JS file loading and conditional loading
- **Template Overrides**: Template path system and file existence
- **Plugin Integration**: Integration with main plugin and module independence
- **Performance**: Memory usage and initialization time validation

#### Test Categories

1. **Unit Tests**: Core functionality testing
   - TemplateManager initialization
   - TemplateHooks method existence
   - TemplateAssets functionality

2. **Validation Tests**: Real-world scenario testing
   - Module initialization in WordPress environment
   - Conditional loading with/without TEC
   - Asset file existence and readability

3. **Integration Tests**: Plugin integration testing
   - Template module integration with main plugin
   - Module independence from importer
   - Template override system functionality

4. **Performance Tests**: Performance impact testing
   - Initialization time (target: < 100ms)
   - Memory usage (target: < 1MB)
   - Asset loading performance (target: < 50ms)

#### Expected Test Results

All tests should pass with:
- ✅ **Module Initialization**: TemplateManager creates successfully
- ✅ **TEC Detection**: Properly detects TEC availability
- ✅ **Hook Registration**: All TEC hooks registered correctly
- ✅ **Asset Loading**: CSS/JS files load conditionally
- ✅ **Template Overrides**: Template path system works
- ✅ **Plugin Integration**: Integrates with main plugin
- ✅ **Module Independence**: Works without importer
- ✅ **Performance**: Acceptable memory and time usage

#### Test Output

Tests provide detailed output including:
- Pass/fail status for each test
- Detailed error messages for failed tests
- Performance metrics (execution time, memory usage)
- Summary statistics (total tests, success rate)

#### Debugging Failed Tests

If tests fail:
1. Check that all required files exist
2. Verify TEC plugin is installed (if testing TEC functionality)
3. Review error messages for specific issues
4. Check WordPress debug log for additional information
5. Ensure proper file permissions on test files

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

## Development Roadmap

### Step 4: Advanced Template Customization (Next Phase)

The next phase of development will focus on advanced template customization features:

#### Planned Features

1. **Template Builder Interface**
   - Visual template editor in WordPress admin
   - Drag-and-drop template customization
   - Real-time preview of template changes
   - Template version control and rollback

2. **Advanced Hook System**
   - Custom hook creation for specific events
   - Hook priority management
   - Conditional hook execution based on event properties
   - Hook performance monitoring

3. **Template Library**
   - Pre-built template designs
   - Template import/export functionality
   - Template sharing between sites
   - Template marketplace integration

4. **Advanced Asset Management**
   - CSS/JS minification and optimization
   - Asset versioning and cache busting
   - Conditional asset loading based on event properties
   - Asset performance monitoring

5. **Template Analytics**
   - Template usage tracking
   - Performance metrics for custom templates
   - User interaction analytics
   - A/B testing for template variations

6. **Developer Tools**
   - Template debugging tools
   - Hook inspection and monitoring
   - Performance profiling
   - Code generation for common customizations

#### Technical Implementation

- **Admin Interface**: New admin pages for template management
- **Database Schema**: Tables for template storage and versioning
- **API Endpoints**: REST API for template operations
- **Caching System**: Template and asset caching
- **Security**: Template validation and sanitization

#### Testing Strategy

- **Unit Tests**: Individual component testing
- **Integration Tests**: Template system integration
- **Performance Tests**: Template rendering performance
- **User Acceptance Tests**: End-to-end template customization workflows

### Future Enhancements

- **Multi-site Support**: Template sharing across WordPress networks
- **Third-party Integrations**: Integration with popular page builders
- **Mobile Optimization**: Responsive template customization
- **Accessibility**: WCAG compliance tools for templates
- **Internationalization**: Multi-language template support

## Support

For support and bug reports, please use the plugin's debug features to gather detailed information about any issues.

## License

This plugin is licensed under the GPL v2 or later. 

---

## Developer Notes

- The 410 feature stores tombstones in the non-autoloaded option `sg_hai_410_tombstones` and only checks them on 404 requests.
- Filters:
  - `sg_hai_410_post_types` (array): post types to track for 410s. Default: `['tribe_events']`.
- Settings (stored under `humanitix_importer_options`):
  - `deleted_410_enable` (bool, default true)
  - `deleted_410_ttl_days` (int days, default 365; `0` means never expire)