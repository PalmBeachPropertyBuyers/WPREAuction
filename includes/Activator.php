<?php
class REAP_Activator {
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Sources table
        $sources = $wpdb->prefix . 'reap_sources';
        $sql1 = "CREATE TABLE IF NOT EXISTS $sources (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            enabled TINYINT(1) DEFAULT 1,
            last_scraped DATETIME DEFAULT NULL
        ) $charset_collate;";

        // Logs table
        $logs = $wpdb->prefix . 'reap_logs';
        $sql2 = "CREATE TABLE IF NOT EXISTS $logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50),
            message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }

    public static function deactivate() {
        // No action needed for now
    }
}