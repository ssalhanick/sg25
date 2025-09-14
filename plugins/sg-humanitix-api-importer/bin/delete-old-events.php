<?php
require_once(dirname(__DIR__, 4) . '/wp-load.php');

// Ensure we're running in WP-CLI context
if (!defined('WP_CLI') || !WP_CLI) {
    echo "This script must be run with WP-CLI. Use: wp eval-file bin/delete-old-events.php\n";
    exit(1);
}

use WP_CLI;

try {
    echo "=== WP-CLI Event Deletion Script ===\n";
    
    // Get total events count using WP-CLI
    $total_events = WP_CLI::runcommand('post list --post_type=tribe_events --format=count', array('return' => true));
    echo "Total events in database: {$total_events}\n";
    
    // Get old events (older than 4 years) using WP-CLI
    // We'll use a date filter to find events older than 4 years
    $cutoff_date = date('Y-m-d', strtotime('-4 years'));
    echo "Looking for events older than: {$cutoff_date}\n";
    
    // Get old event IDs using WP-CLI
    $old_event_ids = WP_CLI::runcommand("post list --post_type=tribe_events --format=ids --meta_query='[{\"key\":\"_EventStartDate\",\"value\":\"{$cutoff_date}\",\"compare\":\"<\"}]'", array('return' => true));
    
    // If no events found with meta query, try with post_date
    if (empty(trim($old_event_ids))) {
        echo "No events found with meta query, trying post_date filter...\n";
        $old_event_ids = WP_CLI::runcommand("post list --post_type=tribe_events --format=ids --date_query='[{\"before\":\"{$cutoff_date}\"}]'", array('return' => true));
    }
    
    $event_ids_array = array_filter(explode("\n", trim($old_event_ids)));
    $event_count = count($event_ids_array);
    
    echo "Found {$event_count} old events to delete...\n";
    
    if ($event_count > 0) {
        echo "\n=== Starting Deletion Process ===\n";
        
        // Show first few events for verification
        echo "First few events to delete:\n";
        for ($i = 0; $i < min(5, $event_count); $i++) {
            if (isset($event_ids_array[$i])) {
                $event_id = $event_ids_array[$i];
                // Get event title for display
                $event_title = WP_CLI::runcommand("post get {$event_id} --field=title", array('return' => true));
                echo "  - ID {$event_id}: '{$event_title}'\n";
            }
        }
        
        if ($event_count > 5) {
            echo "  ... and " . ($event_count - 5) . " more\n";
        }
        
        echo "\nProceeding with deletion...\n";
        
        $deleted_count = 0;
        $failed_count = 0;
        
        // Process events in smaller batches to avoid command line length limits
        $batch_size = 50;
        $total_batches = ceil($event_count / $batch_size);
        
        for ($batch = 0; $batch < $total_batches; $batch++) {
            $start_index = $batch * $batch_size;
            $end_index = min($start_index + $batch_size, $event_count);
            
            echo "\n--- Processing Batch " . ($batch + 1) . "/{$total_batches} (Events " . ($start_index + 1) . "-{$end_index}) ---\n";
            
            // Get batch of event IDs
            $batch_ids = array_slice($event_ids_array, $start_index, $batch_size);
            $batch_ids_string = implode(' ', $batch_ids);
            
            echo "Deleting events: {$batch_ids_string}\n";
            
            try {
                // Use WP-CLI to delete the batch
                $result = WP_CLI::runcommand("post delete {$batch_ids_string} --force", array('return' => true));
                
                if (strpos($result, 'Success:') !== false) {
                    $deleted_count += count($batch_ids);
                    echo "✓ Batch " . ($batch + 1) . " deleted successfully\n";
                } else {
                    echo "✗ Batch " . ($batch + 1) . " deletion failed: {$result}\n";
                    $failed_count += count($batch_ids);
                }
                
            } catch (Exception $e) {
                echo "✗ Batch " . ($batch + 1) . " error: " . $e->getMessage() . "\n";
                $failed_count += count($batch_ids);
            }
            
            // Small delay between batches
            if ($batch < $total_batches - 1) {
                sleep(1);
            }
        }
        
        echo "\n=== Deletion Summary ===\n";
        echo "Total processed: {$event_count}\n";
        echo "Successfully deleted: {$deleted_count}\n";
        echo "Failed: {$failed_count}\n";
        
        if ($deleted_count > 0) {
            echo "\n✅ Deletion process completed successfully!\n";
        } else {
            echo "\n❌ No events were deleted. Check permissions and try again.\n";
        }
        
    } else {
        echo "No old events found to delete.\n";
    }
    
    // Verify final count
    $final_count = WP_CLI::runcommand('post list --post_type=tribe_events --format=count', array('return' => true));
    echo "\nFinal event count: {$final_count}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nScript completed successfully!\n";
?>