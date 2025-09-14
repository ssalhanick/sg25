<?php

namespace SG\HumanitixApiImporter;

/**
 * Service for updating events with Humanitix data
 */
class EventUpdateService {
    
    private $api_client;
    private $logger;
    
    public function __construct($api_client = null) {
        $this->api_client = $api_client;
        $this->logger = new Logger();
    }
    
    /**
     * Update event preserving URL
     */
    public function update_event_preserving_url($event, $dry_run = false) {
        try {
            if ($dry_run) {
                return $this->simulate_update($event, 'updated');
            }
            
            // Get fresh data from Humanitix API
            $humanitix_data = $this->api_client->fetch_event($event->ID);
            
            if (is_wp_error($humanitix_data)) {
                throw new \Exception('Failed to fetch Humanitix data: ' . $humanitix_data->get_error_message());
            }
            
            // Validate data
            $this->validate_humanitix_data($humanitix_data);
            
            // Update event with real API data
            $updated = wp_update_post(array(
                'ID' => $event->ID,
                'post_title' => $humanitix_data['title'],
                'post_content' => $humanitix_data['description'],
                'post_excerpt' => $humanitix_data['excerpt'],
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1)
            ));
            
            if (is_wp_error($updated)) {
                throw new \Exception($updated->get_error_message());
            }
            
            // Update event meta (dates, location, etc.)
            $this->update_event_meta($event->ID, $humanitix_data);
            
            // Log success
            $this->logger->log_success($event->ID, 'Event updated with Humanitix data');
            
            return array(
                'success' => true,
                'action' => 'updated',
                'url_preserved' => true,
                'message' => 'Event updated successfully'
            );
            
        } catch (\Exception $e) {
            $this->logger->log_error($event->ID, $e->getMessage());
            
            return array(
                'success' => false,
                'action' => 'error',
                'url_preserved' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Recreate event with fresh data
     */
    public function recreate_event($event, $dry_run = false) {
        try {
            if ($dry_run) {
                return $this->simulate_update($event, 'created');
            }
            
            // Get fresh data from Humanitix API
            $humanitix_data = $this->api_client->fetch_event($event->ID);
            
            if (is_wp_error($humanitix_data)) {
                throw new \Exception('Failed to fetch Humanitix data: ' . $humanitix_data->get_error_message());
            }
            
            // Validate data
            $this->validate_humanitix_data($humanitix_data);
            
            // Store old URL for potential 410 handling
            $old_url = get_permalink($event->ID);
            
            // Delete old event
            wp_delete_post($event->ID, true);
            
            // Create new event with fresh data
            $new_event_id = wp_insert_post(array(
                'post_type' => 'tribe_events',
                'post_title' => $humanitix_data['title'],
                'post_status' => 'publish',
                'post_content' => $humanitix_data['description'],
                'post_excerpt' => $humanitix_data['excerpt']
            ));
            
            if (is_wp_error($new_event_id)) {
                throw new \Exception($new_event_id->get_error_message());
            }
            
            // Update event meta
            $this->update_event_meta($new_event_id, $humanitix_data);
            
            // Log success
            $this->logger->log_success($new_event_id, 'Event recreated with Humanitix data');
            
            return array(
                'success' => true,
                'action' => 'created',
                'url_preserved' => false,
                'message' => 'Event recreated successfully'
            );
            
        } catch (\Exception $e) {
            $this->logger->log_error($event->ID, $e->getMessage());
            
            return array(
                'success' => false,
                'action' => 'error',
                'url_preserved' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Update event meta data
     */
    private function update_event_meta($event_id, $humanitix_data) {
        // Update event dates
        if (!empty($humanitix_data['start_date'])) {
            update_post_meta($event_id, '_EventStartDate', $humanitix_data['start_date']);
        }
        
        if (!empty($humanitix_data['end_date'])) {
            update_post_meta($event_id, '_EventEndDate', $humanitix_data['end_date']);
        }
        
        // Update venue and organizer
        if (!empty($humanitix_data['venue_id'])) {
            update_post_meta($event_id, '_EventVenueID', $humanitix_data['venue_id']);
        }
        
        if (!empty($humanitix_data['organizer_id'])) {
            update_post_meta($event_id, '_EventOrganizerID', $humanitix_data['organizer_id']);
        }
        
        // Update external URL
        if (!empty($humanitix_data['external_url'])) {
            update_post_meta($event_id, '_EventURL', $humanitix_data['external_url']);
        }
        
        // Update featured image if available
        if (!empty($humanitix_data['image_url'])) {
            $this->set_featured_image($event_id, $humanitix_data['image_url']);
        }
        
        // Update ticket information
        if (!empty($humanitix_data['ticket_info'])) {
            update_post_meta($event_id, '_EventTicketInfo', $humanitix_data['ticket_info']);
        }
    }
    
    /**
     * Set featured image from URL
     */
    private function set_featured_image($event_id, $image_url) {
        // This is a placeholder - we'll implement image handling later
        // For now, just store the URL
        update_post_meta($event_id, '_EventImageURL', $image_url);
    }
    
    /**
     * Validate Humanitix data
     */
    private function validate_humanitix_data($data) {
        $required_fields = ['title', 'start_date', 'end_date'];
        $errors = array();
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }
        
        if (!empty($errors)) {
            throw new \Exception('Data validation failed: ' . implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * Simulate update for dry runs
     */
    private function simulate_update($event, $action) {
        return array(
            'success' => true,
            'action' => $action,
            'url_preserved' => ($action === 'updated'),
            'message' => "Would {$action} event (dry run)"
        );
    }
} 