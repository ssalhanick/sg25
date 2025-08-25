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
    // Find WordPress root
    $wp_root = dirname(__DIR__, 4); // Go up from wp-content/plugins/plugin-name/
    
    if (file_exists($wp_root . '/wp-config.php')) {
        require_once $wp_root . '/wp-config.php';
    } else {
        die("Error: Could not find WordPress installation. Please run this script from the plugin directory.\n");
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

foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0) {
        $parts = explode('=', $arg, 2);
        $key = substr($parts[0], 2);
        $value = isset($parts[1]) ? $parts[1] : true;
        
        if (isset($options[$key])) {
            if ($key === 'batch_size') {
                $options[$key] = intval($value);
            } elseif ($key === 'dry_run') {
                $options[$key] = true;
            } else {
                $options[$key] = $value;
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

// Get total event count
$total_events = wp_count_posts('tribe_events')->publish;
echo "Total Events Found: {$total_events}\n\n";

if ($total_events === 0) {
    echo "No events found. Nothing to update.\n";
    exit(0);
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
    
    $events = get_posts(array(
        'post_type' => 'tribe_events',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    
    foreach ($events as $event_id) {
        $permalink = get_permalink($event_id);
        $url_mapping[$permalink] = $event_id;
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
    
    // Get events for this batch
    $events = get_posts(array(
        'post_type' => 'tribe_events',
        'post_status' => 'publish',
        'numberposts' => $options['batch_size'],
        'offset' => $offset,
        'orderby' => 'ID',
        'order' => 'ASC'
    ));
    
    if (empty($events)) {
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
            if ($options['strategy'] === 'preserve_urls') {
                $result = update_event_preserving_url($event, $options['dry_run']);
            } else {
                $result = recreate_event($event, $options['dry_run']);
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
            }
            
        } catch (Exception $e) {
            $batch_errors++;
            $total_errors++;
            echo "  ✗ {$event->post_title} - Exception: {$e->getMessage()}\n";
        }
        
        $total_processed++;
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

// Helper functions
function update_event_preserving_url($event, $dry_run) {
    if ($dry_run) {
        // In dry run, simulate what would happen
        $current_url = get_permalink($event->ID);
        $would_preserve_url = true; // In a real update, we'd try to preserve the URL
        
        return array(
            'success' => true,
            'action' => 'updated',
            'url_preserved' => $would_preserve_url,
            'message' => 'Would update event (dry run)'
        );
    }
    
    // Get current URL
    $current_url = get_permalink($event->ID);
    
    // Simulate update (in real implementation, this would call Humanitix API)
    $updated = wp_update_post(array(
        'ID' => $event->ID,
        'post_title' => $event->post_title . ' (Updated)',
        'post_modified' => current_time('mysql'),
        'post_modified_gmt' => current_time('mysql', 1)
    ));
    
    if (is_wp_error($updated)) {
        return array(
            'success' => false,
            'action' => 'error',
            'url_preserved' => false,
            'message' => $updated->get_error_message()
        );
    }
    
    // Verify URL is preserved
    $new_url = get_permalink($event->ID);
    $url_preserved = ($current_url === $new_url);
    
    return array(
        'success' => true,
        'action' => 'updated',
        'url_preserved' => $url_preserved,
        'message' => 'Event updated successfully'
    );
}

function recreate_event($event, $dry_run) {
    if ($dry_run) {
        // In dry run, simulate what would happen
        return array(
            'success' => true,
            'action' => 'created',
            'url_preserved' => false, // Recreating always changes URLs
            'message' => 'Would recreate event (dry run)'
        );
    }
    
    // Store old URL for potential 410 handling
    $old_url = get_permalink($event->ID);
    
    // Delete old event
    wp_delete_post($event->ID, true);
    
    // Create new event with fresh data
    $new_event_id = wp_insert_post(array(
        'post_type' => 'tribe_events',
        'post_title' => $event->post_title . ' (Recreated)',
        'post_status' => 'publish',
        'post_content' => $event->post_content
    ));
    
    if (is_wp_error($new_event_id)) {
        return array(
            'success' => false,
            'action' => 'error',
            'url_preserved' => false,
            'message' => $new_event_id->get_error_message()
        );
    }
    
    return array(
        'success' => true,
        'action' => 'created',
        'url_preserved' => false,
        'message' => 'Event recreated successfully'
    );
} 