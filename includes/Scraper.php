<?php
class REAP_Scraper {
    private $log = [];
    private $rate_limit = 2; // seconds between requests

    public function scrape_all_sources() {
        global $wpdb;
        $sources = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}reap_sources WHERE enabled = 1");
        $results = [];
        foreach ($sources as $source) {
            $this->log[] = "Scraping: {$source->name} ({$source->url})";
            $result = $this->scrape_source($source->url, $source->id);
            $results[] = $result;
            sleep($this->rate_limit);
        }
        return $results;
    }

    public function scrape_source($url, $source_id = null) {
        $html = wp_remote_get($url);
        if (is_wp_error($html)) {
            $this->log[] = "Error fetching $url: " . $html->get_error_message();
            $this->log_to_db('error', $html->get_error_message());
            return false;
        }
        $body = wp_remote_retrieve_body($html);
        // TODO: Parse $body for auction data (address, date, bid, etc.)
        // For now, just log length
        $this->log[] = "Fetched " . strlen($body) . " bytes from $url.";
        $this->log_to_db('info', "Fetched " . strlen($body) . " bytes from $url.");
        // TODO: Save parsed data as posts
        if ($source_id) {
            global $wpdb;
            $wpdb->update($wpdb->prefix.'reap_sources', ['last_scraped' => current_time('mysql')], ['id' => $source_id]);
        }
        return true;
    }

    public function get_log() {
        return $this->log;
    }

    private function log_to_db($type, $message) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix.'reap_logs', [
            'type' => $type,
            'message' => $message,
            'created_at' => current_time('mysql')
        ]);
    }
}