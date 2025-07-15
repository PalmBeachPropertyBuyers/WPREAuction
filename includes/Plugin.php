<?php
class REAP_Plugin {
    private static $instance = null;
    private static $cron_event = 'reap_cron_scrape';
    private static $intervals = [
        'hourly' => 'Hourly',
        'twicedaily' => 'Twice Daily',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
    ];

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init',        [$this, 'register_post_types']);
        add_action('admin_menu',  [$this, 'register_admin_menu']);
        add_action(self::$cron_event, [self::class, 'cron_scrape_handler']);
        register_activation_hook(dirname(__FILE__,2).'/real-estate-auction.php', [self::class, 'activate_cron']);
        register_deactivation_hook(dirname(__FILE__,2).'/real-estate-auction.php', [self::class, 'deactivate_cron']);
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
        if ($hook === 'real-estate-auction_page_reap_scraping' || $hook === 'real-estate-auction_page_reap_sources') {
            wp_enqueue_script('reap-scraper', plugin_dir_url(__FILE__).'../js/reap-scraper.js', ['jquery'], null, true);
        }
        if ($hook === 'real-estate-auction_page_reap_sources') {
            wp_enqueue_script('reap-sources', plugin_dir_url(__FILE__).'../js/reap-sources.js', ['jquery'], null, true);
            wp_localize_script('reap-sources', 'REAP_AJAX', [
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
        global $wpdb;
        $table = $wpdb->prefix . 'reap_sources';
        $sources = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
        echo '<div class="wrap"><h1>Source Manager</h1>';
        echo '<button class="button" id="reap-add-source">Add Source</button> ';
        echo '<button class="button" id="reap-import-defaults">Import All Default Sources</button>';
        echo '<table class="widefat" style="margin-top:20px"><thead><tr><th>Name</th><th>URL</th><th>Enabled</th><th>Last Scraped</th><th>Actions</th></tr></thead><tbody>';
        foreach ($sources as $src) {
            echo '<tr data-id="'.$src->id.'">';
            echo '<td>'.esc_html($src->name).'</td>';
            echo '<td><a href="'.esc_url($src->url).'" target="_blank">'.esc_html($src->url).'</a></td>';
            echo '<td><input type="checkbox" class="reap-toggle-source" '.($src->enabled?'checked':'').' /></td>';
            echo '<td>'.esc_html($src->last_scraped).'</td>';
            echo '<td>';
            echo '<button class="button reap-edit-source">Edit</button> ';
            echo '<button class="button reap-test-source">Test</button> ';
            echo '<button class="button reap-delete-source">Delete</button>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        // Modal for add/edit
        echo '<div id="reap-source-modal" style="display:none;background:#fff;padding:20px;max-width:400px;border:1px solid #ccc;position:fixed;top:20%;left:50%;transform:translateX(-50%);z-index:9999;">
            <h2 id="reap-modal-title">Add Source</h2>
            <form id="reap-source-form">
                <input type="hidden" name="id" value="">
                <label>Name:<br><input type="text" name="name" style="width:100%"></label><br><br>
                <label>URL:<br><input type="text" name="url" style="width:100%"></label><br><br>
                <button class="button button-primary" type="submit">Save</button>
                <button class="button" id="reap-cancel-modal" type="button">Cancel</button>
            </form>
        </div>';
        echo '</div>';
        // Enqueue JS
        echo '<script src="'.plugin_dir_url(__FILE__).'../js/reap-sources.js"></script>';
    }
    public function settings_page() {
        $interval = get_option('reap_cron_interval', 'daily');
        $last = get_option('reap_cron_last_run');
        $next = wp_next_scheduled(self::$cron_event);
        echo '<div class="wrap"><h1>Settings</h1>';
        echo '<form method="post">';
        echo '<label>Scraping Interval: <select name="reap_cron_interval">';
        foreach (self::$intervals as $val => $label) {
            echo '<option value="'.$val.'"'.selected($interval, $val, false).'>'.$label.'</option>';
        }
        echo '</select></label> ';
        echo '<button class="button button-primary" type="submit">Save</button>';
        echo '</form>';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reap_cron_interval'])) {
            update_option('reap_cron_interval', sanitize_text_field($_POST['reap_cron_interval']));
            self::reschedule_cron();
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }
        echo '<p>Last run: '.($last ? esc_html($last) : 'Never').'</p>';
        echo '<p>Next run: '.($next ? date('Y-m-d H:i:s', $next) : 'Not scheduled').'</p>';
        echo '</div>';
    }

    public static function activate_cron() {
        if (!wp_next_scheduled(self::$cron_event)) {
            $interval = get_option('reap_cron_interval', 'daily');
            wp_schedule_event(time(), $interval, self::$cron_event);
        }
    }
    public static function deactivate_cron() {
        wp_clear_scheduled_hook(self::$cron_event);
    }
    public static function reschedule_cron() {
        self::deactivate_cron();
        self::activate_cron();
    }
    public static function cron_scrape_handler() {
        $scraper = new REAP_Scraper();
        $scraper->scrape_all_sources();
        update_option('reap_cron_last_run', current_time('mysql'));
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

    // AJAX: Save source (add/edit)
    public static function ajax_save_source() {
        check_ajax_referer('reap_scrape', '_wpnonce');
        global $wpdb;
        $id = intval($_POST['id']);
        $name = sanitize_text_field($_POST['name']);
        $url = esc_url_raw($_POST['url']);
        if ($id) {
            $wpdb->update($wpdb->prefix.'reap_sources', ['name'=>$name,'url'=>$url], ['id'=>$id]);
        } else {
            $wpdb->insert($wpdb->prefix.'reap_sources', ['name'=>$name,'url'=>$url,'enabled'=>1]);
        }
        wp_send_json_success();
    }
    // AJAX: Delete source
    public static function ajax_delete_source() {
        check_ajax_referer('reap_scrape', '_wpnonce');
        global $wpdb;
        $id = intval($_POST['id']);
        $wpdb->delete($wpdb->prefix.'reap_sources', ['id'=>$id]);
        wp_send_json_success();
    }
    // AJAX: Toggle enable
    public static function ajax_toggle_source() {
        check_ajax_referer('reap_scrape', '_wpnonce');
        global $wpdb;
        $id = intval($_POST['id']);
        $enabled = intval($_POST['enabled']);
        $wpdb->update($wpdb->prefix.'reap_sources', ['enabled'=>$enabled], ['id'=>$id]);
        wp_send_json_success();
    }
    // AJAX: Test source
    public static function ajax_test_source() {
        check_ajax_referer('reap_scrape', '_wpnonce');
        global $wpdb;
        $id = intval($_POST['id']);
        $src = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}reap_sources WHERE id=%d", $id));
        if (!$src) wp_send_json_error('Not found');
        $resp = wp_remote_get($src->url);
        if (is_wp_error($resp)) wp_send_json_error($resp->get_error_message());
        $body = wp_remote_retrieve_body($resp);
        wp_send_json_success('Fetched '.strlen($body).' bytes.');
    }
    // AJAX: Import defaults
    public static function ajax_import_defaults() {
        check_ajax_referer('reap_scrape', '_wpnonce');
        global $wpdb;
        $defaults = [
            ['name'=>'Palm Beach Foreclosure','url'=>'https://palmbeach.realforeclose.com/index.cfm?zaction=AUCTION&ZCMD=list&AUCTIONDATE=all'],
            ['name'=>'Broward Foreclosure','url'=>'https://broward.realforeclose.com/index.cfm?zaction=AUCTION&ZCMD=list&AUCTIONDATE=all'],
            // ... add more counties as needed ...
        ];
        foreach ($defaults as $src) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}reap_sources WHERE url=%s", $src['url']));
            if (!$exists) $wpdb->insert($wpdb->prefix.'reap_sources', $src+['enabled'=>1]);
        }
        wp_send_json_success();
    }
}
add_action('wp_ajax_reap_manual_scrape', ['REAP_Plugin', 'ajax_manual_scrape']);
add_action('wp_ajax_reap_save_source', ['REAP_Plugin', 'ajax_save_source']);
add_action('wp_ajax_reap_delete_source', ['REAP_Plugin', 'ajax_delete_source']);
add_action('wp_ajax_reap_toggle_source', ['REAP_Plugin', 'ajax_toggle_source']);
add_action('wp_ajax_reap_test_source', ['REAP_Plugin', 'ajax_test_source']);
add_action('wp_ajax_reap_import_defaults', ['REAP_Plugin', 'ajax_import_defaults']);