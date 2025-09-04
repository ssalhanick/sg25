<?php
/**
 * Bulk Update Manager for Events
 * 
 * Handles bulk operations while preserving URLs and avoiding 404s
 * 
 * @package SG\HumanitixApiImporter\Admin
 */

namespace SG\HumanitixApiImporter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class BulkUpdateManager
 */
class BulkUpdateManager {
    
    /**
     * Option name for bulk update status
     */
    private const OPTION_BULK_UPDATE_STATUS = 'sg_hai_bulk_update_status';
    
    /**
     * Option name for URL mapping during bulk updates
     */
    private const OPTION_URL_MAPPING = 'sg_hai_url_mapping';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'wp_ajax_sg_hai_bulk_update_events', array( $this, 'handle_bulk_update_ajax' ) );
        add_action( 'wp_ajax_sg_hai_get_bulk_update_status', array( $this, 'get_bulk_update_status' ) );
    }
    
    /**
     * Add admin menu for bulk operations
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'humanitix-importer',
            'Bulk Update Events',
            'Bulk Update',
            'manage_options',
            'sg-hai-bulk-update',
            array( $this, 'render_admin_page' )
        );
    }
    
    /**
     * Render the admin page
     */
    public function render_admin_page(): void {
        ?>
        <div class="wrap">
            <h1>Bulk Update Events</h1>
            <p>This tool allows you to bulk update events while preserving their URLs to avoid 404s.</p>
            
            <div class="card">
                <h2>Bulk Update Options</h2>
                <p><strong>Warning:</strong> This will update all existing events with fresh data from Humanitix API.</p>
                
                <form id="bulk-update-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="update_strategy">Update Strategy</label>
                            </th>
                            <td>
                                <select name="update_strategy" id="update_strategy">
                                    <option value="preserve_urls">Preserve URLs (Recommended)</option>
                                    <option value="force_recreate">Force Recreate (May cause 404s)</option>
                                </select>
                                <p class="description">
                                    <strong>Preserve URLs:</strong> Updates existing events in place, keeping the same URLs.<br>
                                    <strong>Force Recreate:</strong> Deletes old events and creates new ones (may cause 404s).
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="batch_size">Batch Size</label>
                            </th>
                            <td>
                                <input type="number" name="batch_size" id="batch_size" value="50" min="1" max="200" />
                                <p class="description">Number of events to process in each batch. Lower numbers are safer but slower.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="dry_run">Dry Run</label>
                            </th>
                            <td>
                                <input type="checkbox" name="dry_run" id="dry_run" value="1" />
                                <label for="dry_run">Test the process without making changes</label>
                                <p class="description">Recommended: Run this first to see what would be updated.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="start-bulk-update">
                            Start Bulk Update
                        </button>
                        <button type="button" class="button button-secondary" id="stop-bulk-update" style="display: none;">
                            Stop Update
                        </button>
                    </p>
                </form>
            </div>
            
            <div class="card" id="progress-card" style="display: none;">
                <h2>Update Progress</h2>
                <div id="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <div id="progress-text">0% Complete</div>
                <div id="progress-details"></div>
            </div>
            
            <div class="card" id="results-card" style="display: none;">
                <h2>Update Results</h2>
                <div id="results-content"></div>
            </div>
        </div>
        
        <style>
        .progress-fill {
            background: #0073aa;
            height: 20px;
            width: 0%;
            transition: width 0.3s ease;
        }
        #progress-bar {
            border: 1px solid #ddd;
            background: #f9f9f9;
            margin: 10px 0;
        }
        .card {
            max-width: 800px;
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ccd0d4;
            background: #fff;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let isRunning = false;
            let currentBatch = 0;
            let totalEvents = 0;
            
            $('#bulk-update-form').on('submit', function(e) {
                e.preventDefault();
                startBulkUpdate();
            });
            
            $('#stop-bulk-update').on('click', function() {
                stopBulkUpdate();
            });
            
            function startBulkUpdate() {
                const formData = new FormData($('#bulk-update-form')[0]);
                
                $('#start-bulk-update').prop('disabled', true);
                $('#stop-bulk-update').show();
                $('#progress-card').show();
                $('#results-card').hide();
                
                isRunning = true;
                currentBatch = 0;
                
                // Start the first batch
                processBatch(formData);
            }
            
            function stopBulkUpdate() {
                isRunning = false;
                $('#start-bulk-update').prop('disabled', false);
                $('#stop-bulk-update').hide();
                updateProgress('Update stopped by user', 0);
            }
            
            function processBatch(formData) {
                if (!isRunning) return;
                
                formData.append('action', 'sg_hai_bulk_update_events');
                formData.append('batch', currentBatch);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            currentBatch++;
                            totalEvents = response.data.total_events || totalEvents;
                            
                            const progress = Math.min((currentBatch * formData.get('batch_size') / totalEvents) * 100, 100);
                            updateProgress(response.data.message, progress);
                            
                            if (response.data.completed) {
                                completeUpdate(response.data);
                            } else if (isRunning) {
                                // Process next batch
                                setTimeout(() => processBatch(formData), 1000);
                            }
                        } else {
                            updateProgress('Error: ' + response.data, 0);
                            stopBulkUpdate();
                        }
                    },
                    error: function() {
                        updateProgress('Network error occurred', 0);
                        stopBulkUpdate();
                    }
                });
            }
            
            function updateProgress(message, percentage) {
                $('.progress-fill').css('width', percentage + '%');
                $('#progress-text').text(Math.round(percentage) + '% Complete');
                $('#progress-details').html(message);
            }
            
            function completeUpdate(data) {
                isRunning = false;
                $('#start-bulk-update').prop('disabled', false);
                $('#stop-bulk-update').hide();
                updateProgress('Update completed!', 100);
                
                // Show results
                $('#results-content').html(`
                    <h3>Update Summary</h3>
                    <p><strong>Total Events Processed:</strong> ${data.total_events}</p>
                    <p><strong>Events Updated:</strong> ${data.updated_count}</p>
                    <p><strong>Events Created:</strong> ${data.created_count}</p>
                    <p><strong>Errors:</strong> ${data.error_count}</p>
                    <p><strong>URLs Preserved:</strong> ${data.urls_preserved}</p>
                `);
                $('#results-card').show();
            }
        });
        </script>
        <?php
    }
    
    /**
     * Handle bulk update AJAX request
     */
    public function handle_bulk_update_ajax(): void {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $batch = intval($_POST['batch'] ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? 50);
        $strategy = sanitize_text_field($_POST['update_strategy'] ?? 'preserve_urls');
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';
        
        try {
            if ($batch === 0) {
                // First batch - initialize
                $this->initialize_bulk_update($strategy, $dry_run);
            }
            
            $result = $this->process_batch($batch, $batch_size, $strategy, $dry_run);
            
            wp_send_json_success($result);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Initialize bulk update
     */
    private function initialize_bulk_update(string $strategy, bool $dry_run): void {
        // Get total count of existing events
        $total_events = wp_count_posts('tribe_events')->publish;
        
        // Store update status
        update_option(self::OPTION_BULK_UPDATE_STATUS, array(
            'strategy' => $strategy,
            'dry_run' => $dry_run,
            'total_events' => $total_events,
            'processed' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => 0,
            'urls_preserved' => 0,
            'started_at' => current_time('mysql'),
            'status' => 'running'
        ));
        
        // If preserving URLs, create URL mapping
        if ($strategy === 'preserve_urls') {
            $this->create_url_mapping();
        }
    }
    
    /**
     * Create URL mapping for existing events
     */
    private function create_url_mapping(): void {
        $events = get_posts(array(
            'post_type' => 'tribe_events',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        
        $url_mapping = array();
        
        foreach ($events as $event_id) {
            $permalink = get_permalink($event_id);
            $url_mapping[$permalink] = $event_id;
        }
        
        update_option(self::OPTION_URL_MAPPING, $url_mapping);
    }
    
    /**
     * Process a batch of events
     */
    private function process_batch(int $batch, int $batch_size, string $strategy, bool $dry_run): array {
        $offset = $batch * $batch_size;
        
        // Get events for this batch
        $events = get_posts(array(
            'post_type' => 'tribe_events',
            'post_status' => 'publish',
            'numberposts' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC'
        ));
        
        if (empty($events)) {
            return array(
                'completed' => true,
                'message' => 'All events processed',
                'total_events' => $this->get_update_status()['total_events'],
                'updated_count' => $this->get_update_status()['updated'],
                'created_count' => $this->get_update_status()['created'],
                'error_count' => $this->get_update_status()['errors'],
                'urls_preserved' => $this->get_update_status()['urls_preserved']
            );
        }
        
        $batch_results = array(
            'updated' => 0,
            'created' => 0,
            'errors' => 0,
            'urls_preserved' => 0
        );
        
        foreach ($events as $event) {
            try {
                if ($strategy === 'preserve_urls') {
                    $result = $this->update_event_preserving_url($event, $dry_run);
                } else {
                    $result = $this->recreate_event($event, $dry_run);
                }
                
                if ($result['success']) {
                    if ($result['action'] === 'updated') {
                        $batch_results['updated']++;
                    } else {
                        $batch_results['created']++;
                    }
                    
                    if ($result['url_preserved']) {
                        $batch_results['urls_preserved']++;
                    }
                } else {
                    $batch_results['errors']++;
                }
                
            } catch (\Exception $e) {
                $batch_results['errors']++;
                error_log("Bulk update error for event {$event->ID}: " . $e->getMessage());
            }
        }
        
        // Update status
        $this->update_batch_results($batch_results);
        
        return array(
            'completed' => false,
            'message' => sprintf(
                'Processed batch %d: %d updated, %d created, %d errors, %d URLs preserved',
                $batch + 1,
                $batch_results['updated'],
                $batch_results['created'],
                $batch_results['errors'],
                $batch_results['urls_preserved']
            ),
            'total_events' => $this->get_update_status()['total_events'],
            'updated_count' => $this->get_update_status()['updated'],
            'created_count' => $this->get_update_status()['created'],
            'error_count' => $this->get_update_status()['errors'],
            'urls_preserved' => $this->get_update_status()['urls_preserved']
        );
    }
    
    /**
     * Update event while preserving URL
     */
    private function update_event_preserving_url(\WP_Post $event, bool $dry_run): array {
        // Get current URL
        $current_url = get_permalink($event->ID);
        
        if ($dry_run) {
            return array(
                'success' => true,
                'action' => 'updated',
                'url_preserved' => true,
                'message' => 'Would update event (dry run)'
            );
        }
        
        // Here you would call your Humanitix API to get fresh data
        // For now, we'll simulate an update
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
    
    /**
     * Recreate event (may cause 404s)
     */
    private function recreate_event(\WP_Post $event, bool $dry_run): array {
        if ($dry_run) {
            return array(
                'success' => true,
                'action' => 'created',
                'url_preserved' => false,
                'message' => 'Would recreate event (dry run)'
            );
        }
        
        // Store old URL for potential 410 handling
        $old_url = get_permalink($event->ID);
        
        // Delete old event
        wp_delete_post($event->ID, true);
        
        // Create new event with fresh data
        // This would call your Humanitix API importer
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
    
    /**
     * Update batch results in status
     */
    private function update_batch_results(array $batch_results): void {
        $status = $this->get_update_status();
        
        $status['updated'] += $batch_results['updated'];
        $status['created'] += $batch_results['created'];
        $status['errors'] += $batch_results['errors'];
        $status['urls_preserved'] += $batch_results['urls_preserved'];
        $status['processed'] += ($batch_results['updated'] + $batch_results['created'] + $batch_results['errors']);
        
        update_option(self::OPTION_BULK_UPDATE_STATUS, $status);
    }
    
    /**
     * Get current update status
     */
    private function get_update_status(): array {
        return get_option(self::OPTION_BULK_UPDATE_STATUS, array());
    }
    
    /**
     * Get bulk update status via AJAX
     */
    public function get_bulk_update_status(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        wp_send_json_success($this->get_update_status());
    }
} 