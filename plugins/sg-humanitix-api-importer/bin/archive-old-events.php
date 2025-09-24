<?php
require_once(dirname(__DIR__, 4) . '/wp-load.php');  // Go up 2 levels from bin/ to WordPress root

try {
    $pdo = new PDO('mysql:host=localhost;dbname=wordpress', 'root', '');
    $stmt = $pdo->query('SELECT ID FROM wp_posts WHERE post_type = "tribe_events" AND post_date < DATE_SUB(NOW(), INTERVAL 2 YEAR)');
    $event_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Archiving " . count($event_ids) . " old events...\n";
    
    foreach($event_ids as $id) {
        $post_data = array(
            'ID'=>$id,
            'post_status'=>'archived',
        );
        wp_update_post($post_data);
        echo "Archived event ID: $id\n";
    }
    
    echo "Archival complete!\n";
    
} catch(Exception $e) {
    echo 'PDO Error: ' . $e->getMessage() . "\n";
}
?>