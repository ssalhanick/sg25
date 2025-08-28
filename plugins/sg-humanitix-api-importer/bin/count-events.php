<?php
require_once(dirname(__DIR__, 4) . '/wp-load.php'); 
require_once(__DIR__ . '/../src/Database/DatabaseManager.php');

// Ensure DatabaseManager is loaded
if ( ! class_exists( 'SG\\HumanitixApiImporter\\Database\\DatabaseManager' ) ) {
	require_once __DIR__ . '/../src/Database/DatabaseManager.php';
}

use SG\HumanitixApiImporter\Database\DatabaseManager;

try {
    $db = new DatabaseManager();
    
    // Show connection info
    echo "Database connection: " . $db->getConnectionType() . "\n";
    
    if ($db->isUsingPDOFallback()) {
        echo "Using PDO fallback\n";
    }
    
    $prefix = $db->getTablePrefix();
    
    // Count total events
    $total_events = $db->getVar("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'tribe_events'");
    echo "Total events: {$total_events}\n";
    
    // Count by status
    $published_events = $db->getVar("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'tribe_events' AND post_status = 'publish'");
    echo "Published events: {$published_events}\n";
    
    $draft_events = $db->getVar("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'tribe_events' AND post_status = 'draft'");
    echo "Draft events: {$draft_events}\n";
    
    $archived_events = $db->getVar("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'tribe_events' AND post_status = 'archived'");
    echo "Archived events: {$archived_events}\n";
    
    // Count events by year
    $current_year = date('Y');
    $this_year_events = $db->getVar("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'tribe_events' AND YEAR(post_date) = %d", [$current_year]);
    echo "Events in {$current_year}: {$this_year_events}\n";
    
    $last_year_events = $db->getVar("SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'tribe_events' AND YEAR(post_date) = %d", [$current_year - 1]);
    echo "Events in " . ($current_year - 1) . ": {$last_year_events}\n";
    
    // Check for TEC-specific tables
    $tec_tables = [
        'tribe_events_instances',
        'tribe_events_organizers', 
        'tribe_events_venues'
    ];
    
    echo "\nTEC Tables:\n";
    foreach ($tec_tables as $table) {
        $table_exists = $db->getVar("SHOW TABLES LIKE '{$prefix}{$table}'");
        if ($table_exists) {
            $count = $db->getVar("SELECT COUNT(*) FROM {$prefix}{$table}");
            echo "  {$table}: {$count} records\n";
        } else {
            echo "  {$table}: not found\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 