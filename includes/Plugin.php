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
}