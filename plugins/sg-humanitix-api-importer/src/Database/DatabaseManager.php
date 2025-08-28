<?php

namespace SG\HumanitixApiImporter\Database;

/**
 * Database Manager with PDO fallback for WP-CLI failures
 */
class DatabaseManager {
    private $pdo = null;
    private $wpdb = null;
    private $use_pdo_fallback = false;
    private $table_prefix = '';

    public function __construct() {
        $this->initConnections();
        $this->setTablePrefix();
    }

    /**
     * Initialize database connections
     */
    private function initConnections() {
        // Try WP-CLI first
        if (defined('WP_CLI') && WP_CLI) {
            global $wpdb;
            if ($wpdb && $wpdb->db_connect()) {
                $this->wpdb = $wpdb;
                return;
            }
        }

        // Fallback to PDO
        $this->use_pdo_fallback = true;
        $this->initPDO();
    }

    /**
     * Initialize PDO connection
     */
    private function initPDO() {
        try {
            $host = DB_HOST;
            $dbname = DB_NAME;
            $username = DB_USER;
            $password = DB_PASSWORD;
            
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $this->pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch (\PDOException $e) {
            throw new \Exception("PDO connection failed: " . $e->getMessage());
        }
    }

    /**
     * Set table prefix
     */
    private function setTablePrefix() {
        if ($this->wpdb) {
            $this->table_prefix = $this->wpdb->prefix;
        } else {
            // Try to get prefix from wp-config or default
            global $table_prefix;
            $this->table_prefix = $table_prefix ?? 'wp_';
        }
    }

    /**
     * Execute a query and return results
     */
    public function query($sql, $params = []) {
        if ($this->use_pdo_fallback) {
            return $this->pdoQuery($sql, $params);
        } else {
            return $this->wpdbQuery($sql, $params);
        }
    }

    /**
     * Execute a query using wpdb
     */
    private function wpdbQuery($sql, $params = []) {
        if (empty($params)) {
            return $this->wpdb->get_results($sql);
        }
        
        // Handle parameterized queries for wpdb
        $prepared_sql = $this->wpdb->prepare($sql, ...$params);
        return $this->wpdb->get_results($prepared_sql);
    }

    /**
     * Execute a query using PDO
     */
    private function pdoQuery($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query without returning results
     */
    public function execute($sql, $params = []) {
        if ($this->use_pdo_fallback) {
            return $this->pdoExecute($sql, $params);
        } else {
            return $this->wpdbExecute($sql, $params);
        }
    }

    /**
     * Execute using wpdb
     */
    private function wpdbExecute($sql, $params = []) {
        if (empty($params)) {
            return $this->wpdb->query($sql);
        }
        
        $prepared_sql = $this->wpdb->prepare($sql, ...$params);
        return $this->wpdb->query($prepared_sql);
    }

    /**
     * Execute using PDO
     */
    private function pdoExecute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get a single value from a query
     */
    public function getVar($sql, $params = []) {
        if ($this->use_pdo_fallback) {
            return $this->pdoGetVar($sql, $params);
        } else {
            return $this->wpdbGetVar($sql, $params);
        }
    }

    /**
     * Get var using wpdb
     */
    private function wpdbGetVar($sql, $params = []) {
        if (empty($params)) {
            return $this->wpdb->get_var($sql);
        }
        
        $prepared_sql = $this->wpdb->prepare($sql, ...$params);
        return $this->wpdb->get_var($prepared_sql);
    }

    /**
     * Get var using PDO
     */
    private function pdoGetVar($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Get a single row
     */
    public function getRow($sql, $params = []) {
        if ($this->use_pdo_fallback) {
            return $this->pdoGetRow($sql, $params);
        } else {
            return $this->wpdbGetRow($sql, $params);
        }
    }

    /**
     * Get row using wpdb
     */
    private function wpdbGetRow($sql, $params = []) {
        if (empty($params)) {
            return $this->wpdb->get_row($sql);
        }
        
        $prepared_sql = $this->wpdb->prepare($sql, ...$params);
        return $this->wpdb->get_row($prepared_sql);
    }

    /**
     * Get row using PDO
     */
    private function pdoGetRow($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Get table prefix
     */
    public function getTablePrefix() {
        return $this->table_prefix;
    }

    /**
     * Check if using PDO fallback
     */
    public function isUsingPDOFallback() {
        return $this->use_pdo_fallback;
    }

    /**
     * Get connection type
     */
    public function getConnectionType() {
        return $this->use_pdo_fallback ? 'PDO' : 'wpdb';
    }

    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $result = $this->getVar("SELECT 1");
            return $result == 1;
        } catch (Exception $e) {
            return false;
        }
    }
} 