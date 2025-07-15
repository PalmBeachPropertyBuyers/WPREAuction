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
        $this->log[] = "Fetched " . strlen($body) . " bytes from $url.";
        $this->log_to_db('info', "Fetched " . strlen($body) . " bytes from $url.");
        // Parse and save auctions
        $auctions = $this->parse_auctions($body);
        $this->log[] = "Parsed " . count($auctions) . " auctions.";
        foreach ($auctions as $auction) {
            $post_id = wp_insert_post([
                'post_type' => 'reap_auction',
                'post_title' => $auction['case_number'] . ' - ' . $auction['address'],
                'post_status' => 'publish',
                'meta_input' => $auction
            ]);
            if ($post_id) {
                $this->log[] = "Saved auction: {$auction['case_number']} ({$auction['address']})";
            }
        }
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

    private function parse_auctions($html) {
        $auctions = [];
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        // Example: Find rows in a table with class 'auctionTable'
        $rows = $xpath->query("//table[contains(@class, 'auctionTable')]/tbody/tr");
        foreach ($rows as $row) {
            $cells = $row->getElementsByTagName('td');
            if ($cells->length < 6) continue; // adjust as needed
            $auctions[] = [
                'address' => trim($cells->item(0)->textContent),
                'auction_date' => trim($cells->item(1)->textContent),
                'opening_bid' => trim($cells->item(2)->textContent),
                'case_number' => trim($cells->item(3)->textContent),
                'sale_type' => trim($cells->item(4)->textContent),
                'status' => trim($cells->item(5)->textContent),
            ];
        }
        return $auctions;
    }
}