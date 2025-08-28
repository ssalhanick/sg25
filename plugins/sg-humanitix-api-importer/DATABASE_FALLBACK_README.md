# Database Manager with PDO Fallback

This plugin now includes a robust database management system that automatically falls back to PDO when WP-CLI database commands fail.

## How It Works

The `DatabaseManager` class automatically detects the best available database connection method:

1. **Primary**: WordPress database connection (via `$wpdb`)
2. **Fallback**: Direct PDO connection when WordPress connection fails

## Usage

### Basic Database Operations

```php
use SG\HumanitixApiImporter\Database\DatabaseManager;

$db = new DatabaseManager();

// Get a single value
$count = $db->getVar("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'tribe_events'");

// Execute a query
$results = $db->query("SELECT * FROM {$prefix}posts WHERE post_type = 'tribe_events'");

// Execute without returning results
$db->execute("UPDATE {$prefix}posts SET post_status = 'archived' WHERE post_type = 'tribe_events'");
```

### Available Methods

- `query($sql, $params = [])` - Execute query and return results
- `execute($sql, $params = [])` - Execute query without returning results
- `getVar($sql, $params = [])` - Get single value
- `getRow($sql, $params = [])` - Get single row
- `getTablePrefix()` - Get current table prefix
- `isUsingPDOFallback()` - Check if using PDO fallback
- `getConnectionType()` - Get connection type ('PDO' or 'wpdb')
- `testConnection()` - Test database connectivity

## Scripts

### 1. Test Database Manager
```bash
# Test the database manager functionality
php tests/test-database-manager.php
```

### 2. Count Events
```bash
# Count events (alternative to WP-CLI)
php bin/count-events.php
```

### 3. Delete Old Events
```bash
# Delete old events with PDO fallback
php bin/delete-old-events.php

# Or via WP-CLI (if working)
wp eval-file bin/delete-old-events.php
```

## When PDO Fallback Activates

The PDO fallback automatically activates when:

- WP-CLI database commands fail
- WordPress database connection is unavailable
- Database credentials are incorrect
- Network connectivity issues occur

## Benefits

✅ **Automatic fallback** - No manual intervention needed
✅ **Seamless operation** - Same API regardless of connection method
✅ **Error resilience** - Continues working even when WP-CLI fails
✅ **Performance monitoring** - Track which connection method is used
✅ **Maintainable code** - Single point of database interaction

## Example Output

```
=== Database Manager Test ===
Testing database connection...
✓ Database connection successful
Connection type: PDO
✓ Using PDO fallback (WP-CLI database commands failed)
Table prefix: wp_

=== Testing Basic Queries ===
Simple query test: ✓ PASS
Tables found: 15
Total posts: 1250
Total events: 89
✓ TEC events instances table exists
Event instances: 89

=== Test Complete ===
Database manager is working correctly!
```

## Troubleshooting

### If PDO Fallback Fails

1. Check your `wp-config.php` database credentials
2. Ensure MySQL service is running
3. Verify database permissions
4. Check network connectivity

### Connection Issues

The system will throw exceptions with detailed error messages if both connection methods fail. Check the error logs for specific details.

## Security

- All queries use parameterized statements to prevent SQL injection
- Database credentials are read from WordPress configuration
- No hardcoded credentials in the code

## Performance

- PDO fallback has minimal overhead
- Connection pooling is handled efficiently
- Automatic cleanup of database resources 