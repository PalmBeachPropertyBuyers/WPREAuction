<?php
/*
Plugin Name: Real Estate Auction Plugin
Description: Collects and displays foreclosure and tax deed auction data from 80+ Florida counties. Built for investors, brokers, and lead generators.
Version: 1.0.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

// Autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'REAP_') === 0) {
        $file = __DIR__ . '/includes/' . str_replace('REAP_', '', $class) . '.php';
        if (file_exists($file)) require_once $file;
    }
});

// Activation/Deactivation Hooks
register_activation_hook(__FILE__, ['REAP_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['REAP_Activator', 'deactivate']);

// Bootstrap Plugin
add_action('plugins_loaded', function() {
    REAP_Plugin::instance();
});