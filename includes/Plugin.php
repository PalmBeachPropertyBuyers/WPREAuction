<?php
class REAP_Plugin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init',        [$this, 'register_post_types']);
        add_action('admin_menu',  [$this, 'register_admin_menu']);
    }

    public function register_post_types() {
        // Auctions
        register_post_type('reap_auction', [
            'label' => 'Auctions',
            'public' => false,
            'show_ui' => true,
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-hammer',
        ]);
        // Properties
        register_post_type('reap_property', [
            'label' => 'Properties',
            'public' => false,
            'show_ui' => true,
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-admin-home',
        ]);
        // Leads
        register_post_type('reap_lead', [
            'label' => 'Leads',
            'public' => false,
            'show_ui' => true,
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-businessman',
        ]);
    }

    public function register_admin_menu() {
        add_menu_page('Auctions', 'Auctions', 'manage_options', 'reap_auctions', [$this, 'auctions_page'], 'dashicons-hammer');
        add_submenu_page('reap_auctions', 'Properties', 'Properties', 'manage_options', 'edit.php?post_type=reap_property');
        add_submenu_page('reap_auctions', 'Leads', 'Leads', 'manage_options', 'edit.php?post_type=reap_lead');
        add_submenu_page('reap_auctions', 'Sources', 'Sources', 'manage_options', 'reap_sources', [$this, 'sources_page']);
        add_submenu_page('reap_auctions', 'Settings', 'Settings', 'manage_options', 'reap_settings', [$this, 'settings_page']);
        add_submenu_page('reap_auctions', 'Manual Scraping', 'Manual Scraping', 'manage_options', 'reap_scraping', [$this, 'scraping_page']);
        add_submenu_page('reap_auctions', 'Bulk Scrape Test', 'Bulk Scrape Test', 'manage_options', 'reap_bulk_scrape', [self::class, 'bulk_scrape_page']);
        add_submenu_page('reap_auctions', 'Test Parser', 'Test Parser', 'manage_options', 'reap_test_parser', [self::class, 'test_parser_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook === 'real-estate-auction_page_reap_scraping') {
            wp_enqueue_script('reap-scraper', plugin_dir_url(__FILE__).'../js/reap-scraper.js', ['jquery'], null, true);
            wp_localize_script('reap-scraper', 'REAP_AJAX', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('reap_scrape')
            ]);
        }
    }

    public function scraping_page() {
        echo '<div class="wrap"><h1>Manual Scraping</h1>';
        echo '<button id="reap-start-scrape" class="button button-primary">Start Manual Scraping</button>';
        echo '<pre id="reap-scrape-log" style="margin-top:20px;max-height:300px;overflow:auto;"></pre>';
        echo '</div>';
    }

    // AJAX handler
    public static function ajax_manual_scrape() {
        check_ajax_referer('reap_scrape', 'nonce');
        $scraper = new REAP_Scraper();
        $scraper->scrape_all_sources();
        wp_send_json_success(['log' => $scraper->get_log()]);
    }

    public function auctions_page() {
        echo '<div class="wrap"><h1>Auctions</h1><p>Auctions dashboard coming soon.</p></div>';
    }
    public function sources_page() {
        echo '<div class="wrap"><h1>Sources</h1><p>Source manager coming soon.</p></div>';
    }
    public function settings_page() {
        echo '<div class="wrap"><h1>Settings</h1><p>Settings page coming soon.</p></div>';
    }

    public static function test_parser_page() {
        echo '<div class="wrap"><h1>Test Auction Parser</h1>';
        echo '<form method="post">';
        echo '<textarea name="reap_test_html" rows="15" style="width:100%">'.(isset($_POST['reap_test_html']) ? esc_textarea($_POST['reap_test_html']) : '').'</textarea><br>';
        echo '<button class="button button-primary" type="submit">Parse HTML</button>';
        echo '</form>';
        if (!empty($_POST['reap_test_html'])) {
            echo '<h2>Parsed Output</h2><pre>';
            $scraper = new REAP_Scraper();
            ob_start();
            $scraper->test_parse_sample_html(stripslashes($_POST['reap_test_html']));
            echo esc_html(ob_get_clean());
            echo '</pre>';
        }
        echo '</div>';
    }

    public static function bulk_scrape_page() {
        echo '<div class="wrap"><h1>Bulk Scrape Test</h1>';
        echo '<form method="post">';
        echo '<input type="text" name="reap_bulk_url" style="width:60%" placeholder="Enter auction listing page URL" value="'.(isset($_POST['reap_bulk_url']) ? esc_attr($_POST['reap_bulk_url']) : '').'"> ';
        echo '<button class="button button-primary" type="submit">Bulk Scrape</button>';
        echo '</form>';
        if (!empty($_POST['reap_bulk_url'])) {
            echo '<h2>Scrape Log</h2><pre>';
            $scraper = new REAP_Scraper();
            $scraper->scrape_listing_page(esc_url_raw($_POST['reap_bulk_url']));
            echo esc_html(implode("\n", $scraper->get_log()));
            echo '</pre>';
        }
        echo '</div>';
    }
}
add_action('wp_ajax_reap_manual_scrape', ['REAP_Plugin', 'ajax_manual_scrape']);