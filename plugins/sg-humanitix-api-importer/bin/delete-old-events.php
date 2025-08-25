<?php
require_once('wp-load.php');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=wordpress', 'root', '');
    $stmt = $pdo->query('SELECT ID FROM wp_posts WHERE post_type = "tribe_events" AND post_date < DATE_SUB(NOW(), INTERVAL 4 YEAR)');
    $event_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Deleting " . count($event_ids) . " old events...\n";
    
    foreach($event_ids as $id) {
        wp_delete_post($id, true);
        echo "Deleted event ID: $id\n";
    }
    
    echo "Deletion complete!\n";
    
} catch(Exception $e) {
    echo 'PDO Error: ' . $e->getMessage() . "\n";
}
?>