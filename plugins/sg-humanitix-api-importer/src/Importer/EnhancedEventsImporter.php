<?php
/**
 * Enhanced Events Importer with URL Preservation
 * 
 * Extends the base EventsImporter to handle bulk updates while preserving URLs
 * 
 * @package SG\HumanitixApiImporter\Importer
 */

namespace SG\HumanitixApiImporter\Importer;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class EnhancedEventsImporter
 */
class EnhancedEventsImporter extends EventsImporter {
    
    /**
     * Option name for URL mapping during bulk updates
     */
    private const OPTION_URL_MAPPING = 'sg_hai_url_mapping';
    
    /**
     * Whether we're in bulk update mode
     */
    private bool $bulk_update_mode = false;
    
    /**
     * URL mapping for existing events
     */
    private array $url_mapping = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Check if we're in bulk update mode
        $this->bulk_update_mode = get_option('sg_hai_bulk_update_status', array())['status'] === 'running';
        
        if ($this->bulk_update_mode) {
            $this->url_mapping = get_option(self::OPTION_URL_MAPPING, array());
        }
    }
    
    /**
     * Enhanced event import that preserves URLs when possible
     */
    public function import_single_event_enhanced($event_data, $preserve_url = true) {
        try {
            $humanitix_id = $event_data['_id'] ?? 'unknown';
            $event_name = $event_data['name'] ?? 'Unknown';
            
            // Initialize debug helper
            $debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper($this->logger);
            $debug_helper->log_event_processing($event_name, $humanitix_id, $event_data, 'enhanced_import');
            
            // Check if this is a recurring event
            $dates = $event_data['dates'] ?? array();
            
            if (count($dates) > 1) {
                return $this->import_recurring_event_enhanced($event_data, $dates, $preserve_url);
            } else {
                return $this->import_single_event_instance_enhanced($event_data, $preserve_url);
            }
            
        } catch (\Exception $e) {
            $error_code = \SG\HumanitixApiImporter\Admin\ErrorCode::from_exception($e);
            $this->logger->log_error_code($error_code, 'Enhanced import failed: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => 'Enhanced import failed: ' . $e->getMessage(),
                'error_code' => $error_code
            );
        }
    }
    
    /**
     * Enhanced single event import with URL preservation
     */
    private function import_single_event_instance_enhanced($event_data, $preserve_url = true) {
        try {
            $humanitix_id = $event_data['_id'] ?? 'unknown';
            $event_name = $event_data['name'] ?? 'Unknown';
            
            // Initialize debug helper
            $debug_helper = new \SG\HumanitixApiImporter\Admin\DebugHelper($this->logger);
            $debug_helper->log_event_processing($event_name, $humanitix_id, $event_data, 'enhanced_single');
            
            // Use DataMapper to convert Humanitix format to TEC format
            $mapper = new DataMapper();
            $mapped_event = $mapper->map_event($event_data);
            
            if (empty($mapped_event)) {
                $error_code = \SG\HumanitixApiImporter\Admin\ErrorCode::IMPORT_MAPPING_FAILED;
                $this->logger->log_error_code($error_code, 'DataMapper returned empty mapped event for: ' . $event_name);
                return array(
                    'success' => false,
                    'message' => 'Failed to map event data for event: ' . $event_name,
                    'error_code' => $error_code
                );
            }
            
            // Process venue data
            $venue_id = $this->process_venue_from_mapped_event($mapped_event, $event_data);
            
            // Try to find existing event by URL first (if preserving URLs)
            $existing_event_id = null;
            $url_preserved = false;
            
            if ($preserve_url && $this->bulk_update_mode) {
                $existing_event_id = $this->find_event_by_url($mapped_event, $event_data);
                if ($existing_event_id) {
                    $url_preserved = true;
                    $debug_helper->log('EnhancedImporter', "Found existing event by URL: {$existing_event_id}");
                }
            }
            
            // If no URL match found, try enhanced duplicate detection
            if (!$existing_event_id) {
                $existing_event_id = $this->find_existing_event_enhanced($event_data);
                if ($existing_event_id) {
                    $debug_helper->log('EnhancedImporter', "Found existing event by duplicate detection: {$existing_event_id}");
                }
            }
            
            if ($existing_event_id) {
                // Update existing event
                $debug_helper->log('EnhancedImporter', "Updating existing event {$existing_event_id}");
                
                // Preserve the original slug if we're preserving URLs
                if ($preserve_url && $url_preserved) {
                    $existing_post = get_post($existing_event_id);
                    if ($existing_post) {
                        $mapped_event['post_name'] = $existing_post->post_name;
                    }
                }
                
                $post_id = wp_update_post(array_merge($mapped_event, array('ID' => $existing_event_id)));
                $action = 'updated';
            } else {
                // Create new event
                $debug_helper->log('EnhancedImporter', "Creating new event");
                
                // If preserving URLs and we have a URL mapping, try to use a similar slug
                if ($preserve_url && $this->bulk_update_mode) {
                    $mapped_event = $this->optimize_slug_for_url_preservation($mapped_event, $event_data);
                }
                
                $post_id = wp_insert_post($mapped_event);
                $action = 'created';
            }
            
            if (is_wp_error($post_id)) {
                $error_code = $action === 'created' ? 
                    \SG\HumanitixApiImporter\Admin\ErrorCode::WP_POST_CREATION_FAILED : 
                    \SG\HumanitixApiImporter\Admin\ErrorCode::WP_POST_UPDATE_FAILED;
                
                $this->logger->log_error_code($error_code, "Failed to {$action} event: " . $post_id->get_error_message());
                
                return array(
                    'success' => false,
                    'message' => 'Failed to ' . $action . ' event: ' . $post_id->get_error_message(),
                    'error_code' => $error_code
                );
            }
            
            // Update meta fields
            $this->update_event_meta($post_id, $mapped_event['meta_input']);
            
            // Store Humanitix ID and fingerprint
            update_post_meta($post_id, '_humanitix_event_id', $humanitix_id);
            
            $event_fingerprint = $this->generate_event_fingerprint($event_data);
            update_post_meta($post_id, '_humanitix_event_fingerprint', $event_fingerprint);
            
            // Store recurring event metadata
            if (isset($event_data['_humanitix_date_id'])) {
                update_post_meta($post_id, '_humanitix_date_id', $event_data['_humanitix_date_id']);
            }
            if (isset($event_data['_humanitix_date_index'])) {
                update_post_meta($post_id, '_humanitix_date_index', $event_data['_humanitix_date_index']);
            }
            if (isset($event_data['_humanitix_series_id'])) {
                update_post_meta($post_id, '_humanitix_series_id', $event_data['_humanitix_series_id']);
            }
            
            // Link venue to event
            if ($venue_id) {
                update_post_meta($post_id, '_EventVenueID', $venue_id);
            }
            
            // Log the import
            $this->logger->log(
                'import',
                'Enhanced event ' . $action,
                array(
                    'post_id' => $post_id,
                    'humanitix_id' => $humanitix_id,
                    'action' => $action,
                    'event_title' => $mapped_event['post_title'] ?? '',
                    'url_preserved' => $url_preserved
                )
            );
            
            return array(
                'success' => true,
                'message' => 'Enhanced event ' . $action . ' successfully',
                'post_id' => $post_id,
                'action' => $action,
                'url_preserved' => $url_preserved
            );
            
        } catch (\Exception $e) {
            $error_code = \SG\HumanitixApiImporter\Admin\ErrorCode::from_exception($e);
            $this->logger->log_error_code($error_code, 'Exception during enhanced single event import: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => 'Enhanced import failed: ' . $e->getMessage(),
                'error_code' => $error_code
            );
        }
    }
    
    /**
     * Find existing event by URL matching
     */
    private function find_event_by_url($mapped_event, $event_data) {
        if (empty($this->url_mapping)) {
            return null;
        }
        
        // Try to find by exact URL match first
        $event_url = $event_data['url'] ?? '';
        if ($event_url && isset($this->url_mapping[$event_url])) {
            return $this->url_mapping[$event_url];
        }
        
        // Try to find by slug similarity
        $target_slug = $mapped_event['post_name'] ?? '';
        if ($target_slug) {
            foreach ($this->url_mapping as $url => $post_id) {
                $url_parts = parse_url($url);
                $url_slug = basename($url_parts['path'] ?? '');
                
                if ($url_slug === $target_slug) {
                    return $post_id;
                }
                
                // Check for slug similarity (e.g., "event-name" vs "event-name-2")
                if (strpos($url_slug, $target_slug) === 0) {
                    return $post_id;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Optimize slug for URL preservation
     */
    private function optimize_slug_for_url_preservation($mapped_event, $event_data) {
        $target_slug = $mapped_event['post_name'] ?? '';
        
        if (!$target_slug) {
            return $mapped_event;
        }
        
        // Check if we have a URL mapping that might conflict
        foreach ($this->url_mapping as $url => $post_id) {
            $url_parts = parse_url($url);
            $url_slug = basename($url_parts['path'] ?? '');
            
            if ($url_slug === $target_slug) {
                // Found exact match - this URL is already taken
                // Try to find a similar event to update instead
                $existing_event = $this->find_existing_event_enhanced($event_data);
                if ($existing_event) {
                    // Update existing event instead of creating new one
                    $mapped_event['ID'] = $existing_event;
                    return $mapped_event;
                }
                
                // If no existing event found, we'll have to create with a different slug
                // WordPress will handle this automatically
                break;
            }
        }
        
        return $mapped_event;
    }
    
    /**
     * Enhanced recurring event import
     */
    private function import_recurring_event_enhanced($event_data, $dates, $preserve_url = true) {
        $results = array();
        $total_dates = count($dates);
        
        foreach ($dates as $index => $date) {
            try {
                // Create a copy of event data for this specific date
                $date_event_data = $event_data;
                $date_event_data['_humanitix_date_id'] = $date['_id'] ?? '';
                $date_event_data['_humanitix_date_index'] = $index;
                $date_event_data['_humanitix_series_id'] = $event_data['_id'] ?? '';
                
                // Update dates for this instance
                $date_event_data['startDate'] = $date['startDate'] ?? '';
                $date_event_data['endDate'] = $date['endDate'] ?? '';
                
                $result = $this->import_single_event_instance_enhanced($date_event_data, $preserve_url);
                $results[] = $result;
                
            } catch (\Exception $e) {
                $results[] = array(
                    'success' => false,
                    'message' => 'Failed to import date ' . ($index + 1) . ': ' . $e->getMessage(),
                    'date_index' => $index
                );
            }
        }
        
        // Return summary of results
        $successful = array_filter($results, function($r) { return $r['success']; });
        $failed = array_filter($results, function($r) { return !$r['success']; });
        
        return array(
            'success' => count($successful) > 0,
            'message' => sprintf(
                'Recurring event processed: %d/%d dates successful, %d failed',
                count($successful),
                $total_dates,
                count($failed)
            ),
            'total_dates' => $total_dates,
            'successful_dates' => count($successful),
            'failed_dates' => count($failed),
            'results' => $results
        );
    }
    
    /**
     * Bulk import with URL preservation
     */
    public function bulk_import_with_url_preservation($events_data, $preserve_url = true) {
        $results = array(
            'total' => count($events_data),
            'successful' => 0,
            'failed' => 0,
            'urls_preserved' => 0,
            'details' => array()
        );
        
        foreach ($events_data as $index => $event_data) {
            try {
                $result = $this->import_single_event_enhanced($event_data, $preserve_url);
                
                if ($result['success']) {
                    $results['successful']++;
                    if ($result['url_preserved'] ?? false) {
                        $results['urls_preserved']++;
                    }
                } else {
                    $results['failed']++;
                }
                
                $results['details'][] = array(
                    'index' => $index,
                    'event_name' => $event_data['name'] ?? 'Unknown',
                    'result' => $result
                );
                
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = array(
                    'index' => $index,
                    'event_name' => $event_data['name'] ?? 'Unknown',
                    'result' => array(
                        'success' => false,
                        'message' => 'Exception: ' . $e->getMessage()
                    )
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Get URL preservation statistics
     */
    public function get_url_preservation_stats() {
        $stats = array(
            'total_events' => 0,
            'urls_preserved' => 0,
            'urls_changed' => 0,
            'preservation_rate' => 0
        );
        
        $events = get_posts(array(
            'post_type' => 'tribe_events',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => '_humanitix_event_id',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $stats['total_events'] = count($events);
        
        foreach ($events as $event) {
            $humanitix_id = get_post_meta($event->ID, '_humanitix_event_id', true);
            if ($humanitix_id) {
                // Check if URL was preserved by comparing with original mapping
                $current_url = get_permalink($event->ID);
                $original_url = $this->get_original_url_from_mapping($current_url);
                
                if ($original_url && $current_url === $original_url) {
                    $stats['urls_preserved']++;
                } else {
                    $stats['urls_changed']++;
                }
            }
        }
        
        if ($stats['total_events'] > 0) {
            $stats['preservation_rate'] = round(($stats['urls_preserved'] / $stats['total_events']) * 100, 2);
        }
        
        return $stats;
    }
    
    /**
     * Get original URL from mapping
     */
    private function get_original_url_from_mapping($current_url) {
        // This would need to be implemented based on how you store the original URL mapping
        // For now, we'll return null
        return null;
    }
} 