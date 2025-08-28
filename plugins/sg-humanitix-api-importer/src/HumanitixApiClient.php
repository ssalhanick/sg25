<?php

namespace SG\HumanitixApiImporter;

/**
 * Humanitix API Client for fetching event data
 */
class HumanitixApiClient {
    
    private $api_key;
    private $base_url = 'https://api.humanitix.com/v1';
    private $rate_limit_delay = 0.6; // 100 requests per minute
    
    public function __construct($api_key = null) {
        if ($api_key) {
            $this->api_key = $api_key;
        } else {
            // Try to get API key from plugin options
            $plugin_options = get_option('humanitix_importer_options', array());
            $this->api_key = $plugin_options['api_key'] ?? null;
        }
        
        if (!$this->api_key) {
            throw new \Exception('Humanitix API key not configured. Please set it in the admin panel.');
        }
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        try {
            // Test with events endpoint (requires page parameter)
            $response = wp_remote_get($this->base_url . "/events?page=1", array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 10
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                $data = json_decode($body, true);
                $event_count = isset($data['events']) ? count($data['events']) : 0;
                return array(
                    'success' => true, 
                    'status_code' => $status_code, 
                    'message' => "Connection successful - found {$event_count} events"
                );
            } elseif ($status_code === 401) {
                return array('success' => false, 'message' => 'API key is invalid or expired');
            } elseif ($status_code === 403) {
                return array('success' => false, 'message' => 'API key does not have permission to access this endpoint');
            } elseif ($status_code === 400) {
                return array('success' => false, 'message' => 'Bad request - check API parameters');
            } else {
                return array('success' => false, 'message' => "API request failed with status {$status_code}: {$body}");
            }
            
        } catch (\Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Fetch events from Humanitix API
     */
    public function fetch_events($page = 1, $limit = 50) {
        $this->check_rate_limit();
        
        $response = wp_remote_get($this->base_url . "/events?page={$page}&limit={$limit}", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new \Exception('API request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new \Exception('API request failed with status: ' . $status_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || isset($data['error'])) {
            throw new \Exception('API response error: ' . ($data['error'] ?? 'Invalid response'));
        }
        
        return $data;
    }
    
    /**
     * Fetch a single event by ID
     */
    public function fetch_event($event_id) {
        $this->check_rate_limit();
        
        $response = wp_remote_get($this->base_url . "/events/{$event_id}", array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            throw new \Exception('API request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new \Exception('API request failed with status: ' . $status_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || isset($data['error'])) {
            throw new \Exception('API response error: ' . ($data['error'] ?? 'Invalid response'));
        }
        
        return $this->transform_api_data($data);
    }
    
    /**
     * Transform Humanitix API data to WordPress format
     */
    private function transform_api_data($api_data) {
        return array(
            'title' => $api_data['name'] ?? '',
            'description' => $api_data['description'] ?? '',
            'excerpt' => $api_data['excerpt'] ?? '',
            'start_date' => $api_data['start_date'] ?? '',
            'end_date' => $api_data['end_date'] ?? '',
            'venue_id' => $this->get_or_create_venue($api_data['venue'] ?? array()),
            'organizer_id' => $this->get_or_create_organizer($api_data['organizer'] ?? array()),
            'ticket_info' => $api_data['tickets'] ?? array(),
            'external_url' => $api_data['url'] ?? '',
            'image_url' => $api_data['image_url'] ?? '',
            'categories' => $api_data['categories'] ?? array()
        );
    }
    
    /**
     * Get or create venue
     */
    private function get_or_create_venue($venue_data) {
        if (empty($venue_data)) {
            return 0;
        }
        
        // For now, return 0 - we'll implement venue handling later
        return 0;
    }
    
    /**
     * Get or create organizer
     */
    private function get_or_create_organizer($organizer_data) {
        if (empty($organizer_data)) {
            return 0;
        }
        
        // For now, return 0 - we'll implement organizer handling later
        return 0;
    }
    
    /**
     * Check and enforce rate limiting
     */
    private function check_rate_limit() {
        $last_request = get_option('sg_hai_last_api_request', 0);
        $current_time = time();
        
        if ($current_time - $last_request < $this->rate_limit_delay) {
            sleep(1);
        }
        
        update_option('sg_hai_last_api_request', $current_time);
    }
} 