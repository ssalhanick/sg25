<?php
/**
 * CLI Bulk Update Script for Humanitix Events
 * 
 * Run this from the command line to bulk update events while preserving URLs
 * 
 * Usage: php cli-bulk-update.php [options]
 * 
 * Options:
 *   --strategy=preserve_urls|force_recreate  Update strategy (default: preserve_urls)
 *   --batch-size=50                          Batch size (default: 50)
 *   --dry-run                                Test without making changes
 *   --help                                   Show this help
 */

// Bootstrap WordPress if not already loaded
if (!defined('ABSPATH')) {
    // Find WordPress root - go up 4 levels from bin/ to WordPress root
    $wp_root = dirname(__DIR__, 4);
    
    if (file_exists($wp_root . '/wp-config.php')) {
        require_once $wp_root . '/wp-config.php';
    } else {
        die("Error: Could not find WordPress installation. Please run this script from the plugin bin directory.\n");
    }
}

// Check if we're in CLI mode
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Parse command line arguments
$options = array(
    'strategy' => 'preserve_urls',
    'batch_size' => 50,
    'dry_run' => false,
    'help' => false
);

// Debug: Check if $argv is available
if (!isset($argv) || !is_array($argv)) {
    echo "Warning: \$argv not available, using default options\n";
} else {
    echo "Debug: Processing " . count($argv) . " arguments\n";
    foreach ($argv as $i => $arg) {
        echo "Debug: Arg {$i}: '{$arg}'\n";
        if (strpos($arg, '--') === 0) {
            $parts = explode('=', $arg, 2);
            $key = substr($parts[0], 2);
            $value = isset($parts[1]) ? $parts[1] : true;
            
            echo "Debug: Parsed key='{$key}', value=" . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
            
            // Handle both hyphenated and underscore versions of keys
            $normalized_key = str_replace('-', '_', $key);
            
            if (isset($options[$normalized_key])) {
                if ($normalized_key === 'batch_size') {
                    $options[$normalized_key] = intval($value);
                } elseif ($normalized_key === 'dry_run') {
                    $options[$normalized_key] = true;
                    echo "Debug: Set dry_run to true\n";
                } else {
                    $options[$normalized_key] = $value;
                }
            }
        }
    }
}

// Show help if requested
if ($options['help']) {
    echo "CLI Bulk Update Script for Humanitix Events\n\n";
    echo "Usage: php cli-bulk-update.php [options]\n\n";
    echo "Options:\n";
    echo "  --strategy=preserve_urls|force_recreate  Update strategy (default: preserve_urls)\n";
    echo "  --batch-size=50                          Batch size (default: 50)\n";
    echo "  --dry-run                                Test without making changes\n";
    echo "  --help                                   Show this help\n\n";
    echo "Examples:\n";
    echo "  php cli-bulk-update.php --dry-run\n";
    echo "  php cli-bulk-update.php --strategy=preserve_urls --batch-size=100\n";
    exit(0);
}

// Validate options
if (!in_array($options['strategy'], ['preserve_urls', 'force_recreate'])) {
    die("Error: Invalid strategy. Use 'preserve_urls' or 'force_recreate'.\n");
}

if ($options['batch_size'] < 1 || $options['batch_size'] > 500) {
    die("Error: Batch size must be between 1 and 500.\n");
}

echo "=== Humanitix Events Bulk Update ===\n";
echo "Strategy: {$options['strategy']}\n";
echo "Batch Size: {$options['batch_size']}\n";
echo "Dry Run: " . ($options['dry_run'] ? 'Yes' : 'No') . "\n";
echo "WordPress Root: " . ABSPATH . "\n\n";

// Check if plugin is active
if (!class_exists('SG\HumanitixApiImporter\Plugin')) {
    die("Error: Humanitix API Importer plugin is not active.\n");
}

// Check if The Events Calendar is active
if (!class_exists('Tribe__Events__Main')) {
    die("Error: The Events Calendar plugin is not active.\n");
}

// Load our classes
require_once dirname(__DIR__) . '/src/API/HumanitixAPI.php';
require_once dirname(__DIR__) . '/src/EventUpdateService.php';
require_once dirname(__DIR__) . '/src/Logger.php';

use SG\HumanitixApiImporter\API\HumanitixAPI;
use SG\HumanitixApiImporter\EventUpdateService;
use SG\HumanitixApiImporter\Logger;

// Initialize services
try {
    // Get API settings from plugin options
    $plugin_options = get_option('humanitix_importer_options', array());
    $api_key = $plugin_options['api_key'] ?? '';
    $org_id = $plugin_options['org_id'] ?? '';
    $api_endpoint = $plugin_options['api_endpoint'] ?? '';
    
    if (empty($api_key)) {
        die("Error: Humanitix API key not configured. Please set it in the admin panel.\n");
    }
    
    $api_client = new HumanitixAPI($api_key, $api_endpoint, $org_id);
    $update_service = new EventUpdateService($api_client);
    $logger = new Logger();
} catch (Exception $e) {
    die("Error initializing services: " . $e->getMessage() . "\n");
}

// Test API connection
echo "Testing Humanitix API connection...\n";
$connection_test = $api_client->test_connection();
if (!$connection_test['success']) {
    die("Error: Cannot connect to Humanitix API: " . $connection_test['message'] . "\n");
}
echo "✓ API connection successful\n\n";

// Add this after line 146 (after API connection test)
echo "Testing individual API calls...\n";

// Test a single event update to see the exact error
try {
    // Get one event for testing
    $test_event = $wpdb->get_row("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'tribe_events' AND post_status = 'publish' ORDER BY ID ASC LIMIT 1");
    
    if ($test_event) {
        echo "Testing with event ID: {$test_event->ID} - {$test_event->post_title}\n";
        
        // Test the API call directly
        $test_result = $api_client->test_connection();
        echo "API test result: " . print_r($test_result, true) . "\n";
        
        // Test a single event update
        $event_obj = (object) array(
            'ID' => $test_event->ID,
            'post_title' => $test_event->post_title,
            'post_content' => '',
            'post_excerpt' => ''
        );
        
        echo "Testing update_event_preserving_url...\n";
        $update_result = $update_service->update_event_preserving_url($event_obj, true); // dry run
        echo "Update test result: " . print_r($update_result, true) . "\n";
        
        if (!$update_result['success']) {
            echo "❌ API call failed. This explains the HTTP 500 errors.\n";
            echo "Error details: " . $update_result['message'] . "\n";
            
            // Exit here to prevent bulk processing with broken API
            die("Cannot proceed with bulk update due to API failures.\n");
        } else {
            echo "✅ API call successful. Proceeding with bulk update...\n\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Exception during API testing: " . $e->getMessage() . "\n";
    die("Cannot proceed with bulk update.\n");
}

// Get total event count using WordPress database (more reliable than PDO)
try {
    global $wpdb;
    $total_events = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'tribe_events' AND post_status = 'publish'");
    echo "Total Events Found: {$total_events}\n\n";
    
    if ($total_events === 0) {
        echo "No events found. Nothing to update.\n";
        exit(0);
    }
} catch (Exception $e) {
    die("Error connecting to database: " . $e->getMessage() . "\n");
}

// Confirm before proceeding (unless dry run)
if (!$options['dry_run']) {
    echo "WARNING: This will update {$total_events} events. Are you sure? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 'y') {
        echo "Operation cancelled.\n";
        exit(0);
    }
}

// Initialize bulk update
echo "Initializing bulk update...\n";

// Set bulk update status
update_option('sg_hai_bulk_update_status', array(
    'strategy' => $options['strategy'],
    'dry_run' => $options['dry_run'],
    'total_events' => $total_events,
    'processed' => 0,
    'updated' => 0,
    'created' => 0,
    'errors' => 0,
    'urls_preserved' => 0,
    'started_at' => current_time('mysql'),
    'status' => 'running'
));

// Create URL mapping if preserving URLs
if ($options['strategy'] === 'preserve_urls') {
    echo "Creating URL mapping...\n";
    $url_mapping = array();
    
    // Use WordPress database to get event IDs
    $events = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'tribe_events' AND post_status = 'publish' ORDER BY ID ASC");
    
    foreach ($events as $event) {
        $event_id = $event->ID;
        // Construct permalink manually since get_permalink might not work
        $permalink = home_url() . '/?post_type=tribe_events&p=' . $event_id;
        $url_mapping[$permalink] = $event_id;
        usleep(333000); // 333 milliseconds delay for rate limiting
    }
    
    update_option('sg_hai_url_mapping', $url_mapping);
    echo "URL mapping created for " . count($url_mapping) . " events.\n";
}

// Process events in batches
$batch = 0;
$total_processed = 0;
$total_updated = 0;
$total_created = 0;
$total_errors = 0;
$total_urls_preserved = 0;

echo "\nStarting batch processing...\n";

while ($total_processed < $total_events) {
    $offset = $batch * $options['batch_size'];
    echo "Debug: Processing batch {$batch}, offset: {$offset}\n";
    
    // Get events for this batch using WordPress database
    try {
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_content, post_excerpt FROM {$wpdb->posts} 
             WHERE post_type = 'tribe_events' AND post_status = 'publish' 
             ORDER BY ID ASC LIMIT %d OFFSET %d",
            $options['batch_size'],
            $offset
        ));
        
        echo "Debug: Fetched " . count($events) . " events\n";
        
        if (empty($events)) {
            echo "Debug: No events in this batch, breaking\n";
            break;
        }
        
        $batch_num = $batch + 1;
        $batch_events = count($events);
        echo "\nProcessing batch {$batch_num} ({$batch_events} events)...\n";
        
        $batch_updated = 0;
        $batch_created = 0;
        $batch_errors = 0;
        $batch_urls_preserved = 0;
        
        foreach ($events as $event) {
            try {
                echo "  Processing event ID: {$event->ID} - {$event->post_title}\n";
                
                // Convert WordPress result to a simple object for compatibility
                $event_obj = (object) array(
                    'ID' => $event->ID,
                    'post_title' => $event->post_title,
                    'post_content' => $event->post_content,
                    'post_excerpt' => $event->post_excerpt
                );
                echo "    Event object created successfully\n";
                
                if ($options['strategy'] === 'preserve_urls') {
                    echo "    Calling update_event_preserving_url with dry_run={$options['dry_run']}...\n";
                    $result = $update_service->update_event_preserving_url($event_obj, $options['dry_run']);
                    echo "    Result received: " . print_r($result, true) . "\n";
                } else {
                    echo "    Calling recreate_event with dry_run={$options['dry_run']}...\n";
                    $result = $update_service->recreate_event($event_obj, $options['dry_run']);
                    echo "    Result received: " . print_r($result, true) . "\n";
                }
                
                if ($result['success']) {
                    if ($result['action'] === 'updated') {
                        $batch_updated++;
                        $total_updated++;
                    } else {
                        $batch_created++;
                        $total_created++;
                    }
                    
                    if ($result['url_preserved']) {
                        $batch_urls_preserved++;
                        $total_urls_preserved++;
                    }
                    
                    // Show progress for first few events in each batch
                    if ($batch_updated + $batch_created <= 5) {
                        echo "  ✓ {$event->post_title} - {$result['action']}" . 
                             ($result['url_preserved'] ? ' (URL preserved)' : '') . "\n";
                    }
                } else {
                    $batch_errors++;
                    $total_errors++;
                    echo "  ✗ {$event->post_title} - Error: {$result['message']}\n";
                    
                    // Enhanced error logging for HTTP 500 errors
                    if (strpos($result['message'], 'HTTP 500') !== false) {
                        echo "    ⚠️ HTTP 500 detected - this might be a rate limit or server issue\n";
                        echo "    💡 Try increasing delays or reducing batch size\n";
                    }
                }
                
            } catch (Exception $e) {
                $batch_errors++;
                $total_errors++;
                echo "  ✗ {$event->post_title} - Exception: {$e->getMessage()}\n";
            }
            
            $total_processed++;
            
            // ADD THIS: Rate limiting delay for Humanitix API (200 requests/minute)
            if (!$options['dry_run']) {
                usleep(333000); // 333 milliseconds delay between API calls
                echo "    ⏱️ Rate limiting delay applied (333ms)\n";
            }
        }
        
        // Show batch summary
        if ($batch_updated + $batch_created > 5) {
            echo "  ... and " . (($batch_updated + $batch_created) - 5) . " more events processed\n";
        }
        
        echo "Batch {$batch_num} complete: {$batch_updated} updated, {$batch_created} created, {$batch_errors} errors, {$batch_urls_preserved} URLs preserved\n";
        
        // Update progress
        $progress = round(($total_processed / $total_events) * 100, 1);
        echo "Overall progress: {$progress}% ({$total_processed}/{$total_events})\n";
        
        $batch++;
        
        // Small delay between batches to avoid overwhelming the system
        if ($batch < ceil($total_events / $options['batch_size'])) {
            sleep(1);
        }
        
    } catch (Exception $e) {
        echo "Error processing batch: " . $e->getMessage() . "\n";
        break;
    }
}

// Update final status
update_option('sg_hai_bulk_update_status', array(
    'strategy' => $options['strategy'],
    'dry_run' => $options['dry_run'],
    'total_events' => $total_events,
    'processed' => $total_processed,
    'updated' => $total_updated,
    'created' => $total_created,
    'errors' => $total_errors,
    'urls_preserved' => $total_urls_preserved,
    'started_at' => get_option('sg_hai_bulk_update_status')['started_at'],
    'status' => 'completed',
    'completed_at' => current_time('mysql')
));

// Show final results
echo "\n=== Bulk Update Complete ===\n";
echo "Total Events Processed: {$total_processed}\n";
echo "Events Updated: {$total_updated}\n";
echo "Events Created: {$total_created}\n";
echo "Errors: {$total_errors}\n";
echo "URLs Preserved: {$total_urls_preserved}\n";

if ($total_processed > 0) {
    $url_preservation_rate = round(($total_urls_preserved / $total_processed) * 100, 1);
    echo "URL Preservation Rate: {$url_preservation_rate}%\n";
}

if ($options['dry_run']) {
    echo "\n=== DRY RUN RESULTS ===\n";
    echo "This was a test run. No actual changes were made.\n";
    echo "The above numbers show what WOULD happen if you run this for real.\n";
    echo "\nTo run the actual update, remove the --dry-run flag:\n";
    echo "php cli-bulk-update.php --strategy={$options['strategy']} --batch-size={$options['batch_size']}\n";
} else {
    echo "\n=== ACTUAL UPDATE COMPLETED ===\n";
    echo "All events have been processed and updated.\n";
}

echo "\nDone!\n";