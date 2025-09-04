<?php

namespace SG\HumanitixApiImporter;

/**
 * Logger for bulk update operations
 */
class Logger {
    
    private $max_log_entries = 1000;
    
    /**
     * Log successful operation
     */
    public function log_success($event_id, $message, $context = array()) {
        $this->log('success', $event_id, $message, $context);
    }
    
    /**
     * Log error
     */
    public function log_error($event_id, $message, $context = array()) {
        $this->log('error', $event_id, $message, $context);
    }
    
    /**
     * Log warning
     */
    public function log_warning($event_id, $message, $context = array()) {
        $this->log('warning', $event_id, $message, $context);
    }
    
    /**
     * Log info
     */
    public function log_info($event_id, $message, $context = array()) {
        $this->log('info', $event_id, $message, $context);
    }
    
    /**
     * Main logging method
     */
    private function log($level, $event_id, $message, $context = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'event_id' => $event_id,
            'message' => $message,
            'context' => $context
        );
        
        $option_name = "sg_hai_log_{$level}";
        $existing_logs = get_option($option_name, array());
        $existing_logs[] = $log_entry;
        
        // Keep only the last N log entries
        if (count($existing_logs) > $this->max_log_entries) {
            $existing_logs = array_slice($existing_logs, -$this->max_log_entries);
        }
        
        update_option($option_name, $existing_logs);
        
        // Also add to combined log
        $this->add_to_combined_log($log_entry);
    }
    
    /**
     * Add to combined log for all levels
     */
    private function add_to_combined_log($log_entry) {
        $combined_logs = get_option('sg_hai_combined_log', array());
        $combined_logs[] = $log_entry;
        
        // Keep only the last N log entries
        if (count($combined_logs) > $this->max_log_entries) {
            $combined_logs = array_slice($combined_logs, -$this->max_log_entries);
        }
        
        update_option('sg_hai_combined_log', $combined_logs);
    }
    
    /**
     * Get logs by level
     */
    public function get_logs($level = 'all', $limit = 100) {
        if ($level === 'all') {
            $logs = get_option('sg_hai_combined_log', array());
        } else {
            $logs = get_option("sg_hai_log_{$level}", array());
        }
        
        // Return the last N entries
        return array_slice($logs, -$limit);
    }
    
    /**
     * Clear logs by level
     */
    public function clear_logs($level = 'all') {
        if ($level === 'all') {
            delete_option('sg_hai_combined_log');
            delete_option('sg_hai_log_success');
            delete_option('sg_hai_log_error');
            delete_option('sg_hai_log_warning');
            delete_option('sg_hai_log_info');
        } else {
            delete_option("sg_hai_log_{$level}");
        }
    }
    
    /**
     * Get log statistics
     */
    public function get_log_stats() {
        $stats = array();
        
        $levels = ['success', 'error', 'warning', 'info'];
        foreach ($levels as $level) {
            $logs = get_option("sg_hai_log_{$level}", array());
            $stats[$level] = count($logs);
        }
        
        $stats['total'] = array_sum($stats);
        
        return $stats;
    }
    
    /**
     * Export logs to file
     */
    public function export_logs($level = 'all', $format = 'json') {
        $logs = $this->get_logs($level, 1000);
        
        if ($format === 'json') {
            return json_encode($logs, JSON_PRETTY_PRINT);
        } elseif ($format === 'csv') {
            return $this->logs_to_csv($logs);
        }
        
        return $logs;
    }
    
    /**
     * Convert logs to CSV format
     */
    private function logs_to_csv($logs) {
        if (empty($logs)) {
            return '';
        }
        
        $csv = array();
        
        // Add headers
        $headers = array_keys($logs[0]);
        $csv[] = implode(',', $headers);
        
        // Add data rows
        foreach ($logs as $log) {
            $row = array();
            foreach ($headers as $header) {
                $value = is_array($log[$header]) ? json_encode($log[$header]) : $log[$header];
                $row[] = '"' . str_replace('"', '""', $value) . '"';
            }
            $csv[] = implode(',', $row);
        }
        
        return implode("\n", $csv);
    }
} 