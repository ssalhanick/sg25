# Performance Optimizations

The Humanitix API Importer includes several performance optimizations that are enabled by default to provide the best experience for most users.

## Default Optimizations

### 1. **Optimized Logging** (Default: Enabled)
- Critical errors are logged to the database for admin visibility
- Debug and info messages are logged to file (`wp-content/humanitix-debug.log`)
- Reduces database I/O and improves import performance

### 2. **Image Download** (Default: Disabled)
- Images are not downloaded during bulk imports by default
- Reduces import time and server load
- Images can be processed separately if needed

### 3. **Caching** (Default: Enabled)
- Venue lookups are cached within the same request
- Image lookups are cached to prevent duplicate downloads
- Improves performance for repeated operations

### 4. **File Logging** (Default: Enabled)
- Debug messages are written to file instead of database
- Reduces database load during imports
- Logs are still accessible for debugging

## Configuration Options

If you need to modify these defaults, you can add these constants to your `wp-config.php`:

### For Debugging (Full Logging)
```php
define( 'HUMANITIX_OPTIMIZED_LOGGING', false );
define( 'HUMANITIX_ENABLE_IMAGE_DOWNLOAD', true );
define( 'HUMANITIX_BATCH_SIZE', 5 );
```

### For Maximum Performance
```php
define( 'HUMANITIX_OPTIMIZED_LOGGING', true );
define( 'HUMANITIX_ENABLE_IMAGE_DOWNLOAD', false );
define( 'HUMANITIX_BATCH_SIZE', 50 );
```

### To Disable Caching (Not Recommended)
```php
define( 'HUMANITIX_DISABLE_CACHING', true );
```

### To Disable File Logging
```php
define( 'HUMANITIX_DISABLE_FILE_LOGGING', true );
```

## Performance Benefits

- **Reduced Database Load**: File-based logging and optimized queries
- **Faster Imports**: Caching and batch processing
- **Lower Memory Usage**: Optimized data structures and reduced JSON encoding
- **Better Scalability**: Configurable batch sizes and async operations

## Debugging

When debugging is needed:
1. Set `HUMANITIX_OPTIMIZED_LOGGING` to `false`
2. Enable image downloads with `HUMANITIX_ENABLE_IMAGE_DOWNLOAD`
3. Reduce batch size to `5` for easier tracking
4. Check the debug log file at `wp-content/humanitix-debug.log`

## Recommended Settings

### For Production Sites
- Use all default optimizations
- Batch size of 25-50 events
- Disable image downloads for bulk imports

### For Development/Debugging
- Disable optimized logging
- Enable image downloads
- Use smaller batch sizes (5-10)
- Monitor debug logs closely 