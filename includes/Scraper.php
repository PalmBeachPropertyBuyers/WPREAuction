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
        // Find each auction item
        $items = $xpath->query("//div[contains(@class, 'AUCTION_ITEM')]");
        foreach ($items as $item) {
            // Status, Date, Amount, Sold To
            $status = $xpath->evaluate(".//div[contains(@class, 'ASTAT_MSGA')]/text()", $item)->item(0)?->nodeValue ?? '';
            $auction_date = $xpath->evaluate(".//div[contains(@class, 'ASTAT_MSGB')]/text()", $item)->item(0)?->nodeValue ?? '';
            $amount = $xpath->evaluate(".//div[contains(@class, 'ASTAT_MSGD')]/text()", $item)->item(0)?->nodeValue ?? '';
            $sold_to = $xpath->evaluate(".//div[contains(@class, 'ASTAT_MSG_SOLDTO_MSG')]/text()", $item)->item(0)?->nodeValue ?? '';
            // Table details
            $case_number = $xpath->evaluate(".//tr[td[contains(text(),'Case')]]/td[2]//a/text()", $item)->item(0)?->nodeValue ?? '';
            $auction_type = $xpath->evaluate(".//tr[td[contains(text(),'Auction Type')]]/td[2]/text()", $item)->item(0)?->nodeValue ?? '';
            $final_judgment = $xpath->evaluate(".//tr[td[contains(text(),'Final Judgment')]]/td[2]/text()", $item)->item(0)?->nodeValue ?? '';
            $parcel_id = $xpath->evaluate(".//tr[td[contains(text(),'Parcel ID')]]/td[2]//a/text()", $item)->item(0)?->nodeValue ?? '';
            $address1 = $xpath->evaluate(".//tr[td[contains(text(),'Property Address')]]/td[2]/text()", $item)->item(0)?->nodeValue ?? '';
            $address2 = $xpath->evaluate(".//tr[td[not(@scope) and not(@class) and not(text())]]/td[2]/text()", $item)->item(0)?->nodeValue ?? '';
            $address = trim($address1 . ' ' . $address2);
            $plaintiff_max_bid = $xpath->evaluate(".//tr[td[contains(text(),'Plaintiff Max Bid')]]/td[2]/text()", $item)->item(0)?->nodeValue ?? '';
            $auctions[] = [
                'status' => trim($status),
                'auction_date' => trim($auction_date),
                'amount' => trim($amount),
                'sold_to' => trim($sold_to),
                'auction_type' => trim($auction_type),
                'case_number' => trim($case_number),
                'final_judgment' => trim($final_judgment),
                'parcel_id' => trim($parcel_id),
                'address' => trim($address),
                'plaintiff_max_bid' => trim($plaintiff_max_bid),
            ];
        }
        return $auctions;
    }

    // Test function for sample HTML
    public function test_parse_sample_html($html) {
        $auctions = $this->parse_auctions($html);
        foreach ($auctions as $auction) {
            foreach ($auction as $k => $v) {
                echo $k . ': ' . $v . "\n";
            }
            echo "---------------------\n";
        }
    }
}