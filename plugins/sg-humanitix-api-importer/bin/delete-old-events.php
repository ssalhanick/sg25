<?php
require_once(dirname(__DIR__, 4) . '/wp-load.php'); 
require_once(__DIR__ . '/../src/Database/DatabaseManager.php');

// Ensure DatabaseManager is loaded
if ( ! class_exists( 'SG\\HumanitixApiImporter\\Database\\DatabaseManager' ) ) {
	require_once __DIR__ . '/../src/Database/DatabaseManager.php';
}

use SG\HumanitixApiImporter\Database\DatabaseManager;

try {
    // Initialize database manager with PDO fallback
    $db = new DatabaseManager();
    
    // Check connection type
    $connection_type = $db->getConnectionType();
    echo "Using database connection: {$connection_type}\n";
    
    if ($db->isUsingPDOFallback()) {
        echo "PDO fallback mode: WP-CLI database commands failed, using direct PDO connection\n";
    }
    
    // Test connection
    if (!$db->testConnection()) {
        throw new Exception("Database connection test failed");
    }
    
    // Get table prefix
    $prefix = $db->getTablePrefix();
    echo "Table prefix: {$prefix}\n";
    
    // Get total events count
    $total_sql = "SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'tribe_events'";
    $total_events = $db->getVar($total_sql);
    echo "Total events in database: {$total_events}\n";
    
    // Get old events (older than 4 years) - simplified query
    $old_events_sql = "SELECT ID, post_title, post_date FROM {$prefix}posts 
                       WHERE post_type = 'tribe_events' 
                       AND post_date < DATE_SUB(NOW(), INTERVAL 4 YEAR)
                       AND post_status != 'trash'
                       ORDER BY post_date ASC";
    
    echo "Executing query: {$old_events_sql}\n";
    $old_events = $db->query($old_events_sql);
    
    echo "Query executed. Checking results...\n";
    
    if ($old_events === false || $old_events === null) {
        throw new Exception("Query returned invalid result");
    }
    
    $event_count = count($old_events);
    echo "Found {$event_count} old events to delete...\n";
    
    if ($event_count > 0) {
        echo "\n=== Starting Deletion Process ===\n";
        
        // Show first few events for verification
        echo "First few events to delete:\n";
        for ($i = 0; $i < min(5, $event_count); $i++) {
            if (isset($old_events[$i])) {
                $event = $old_events[$i];
                echo "  - ID {$event['ID']}: '{$event['post_title']}' ({$event['post_date']})\n";
            }
        }
        
        if ($event_count > 5) {
            echo "  ... and " . ($event_count - 5) . " more\n";
        }
        
        echo "\nProceeding with deletion...\n";
        
        $deleted_count = 0;
        $failed_count = 0;
        
        // Process events in smaller batches to avoid memory issues
        $batch_size = 50;
        $total_batches = ceil($event_count / $batch_size);
        
        for ($batch = 0; $batch < $total_batches; $batch++) {
            $start_index = $batch * $batch_size;
            $end_index = min($start_index + $batch_size, $event_count);
            
            echo "\n--- Processing Batch " . ($batch + 1) . "/{$total_batches} (Events " . ($start_index + 1) . "-{$end_index}) ---\n";
            
            for ($i = $start_index; $i < $end_index; $i++) {
                if (!isset($old_events[$i])) {
                    echo "  [{$i}] Event not found in array, skipping...\n";
                    continue;
                }
                
                $event = $old_events[$i];
                $event_id = $event['ID'];
                $event_title = $event['post_title'];
                $event_date = $event['post_date'];
                
                echo "  [{$i}] Processing: ID {$event_id} - '{$event_title}' (from {$event_date})... ";
                
                try {
                    // Try to delete using WordPress function first
                    $result = wp_delete_post($event_id, true);
                    
                    if ($result) {
                        echo "✓ DELETED\n";
                        $deleted_count++;
                    } else {
                        // Fallback: try direct database deletion
                        echo "WordPress delete failed, trying direct deletion... ";
                        
                        // Delete from posts table
                        $delete_posts = $db->execute("DELETE FROM {$prefix}posts WHERE ID = %d", [$event_id]);
                        
                        // Delete from postmeta table
                        $delete_meta = $db->execute("DELETE FROM {$prefix}postmeta WHERE post_id = %d", [$event_id]);
                        
                        if ($delete_posts && $delete_meta) {
                            echo "✓ DELETED (direct)\n";
                            $deleted_count++;
                        } else {
                            echo "✗ FAILED\n";
                            $failed_count++;
                        }
                    }
                    
                } catch (Exception $e) {
                    echo "✗ ERROR: " . $e->getMessage() . "\n";
                    $failed_count++;
                }
                
                // Flush output
                if (ob_get_level()) ob_flush();
                flush();
            }
            
            echo "Batch " . ($batch + 1) . " completed. Progress: {$deleted_count} deleted, {$failed_count} failed\n";
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
    $final_count = $db->getVar($total_sql);
    echo "\nFinal event count: {$final_count}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nScript completed successfully!\n";
?>