<?php
    session_start();
    /**
     * Plugin Name:       PayWave
     * Plugin URI:        https://paywave.net
     * Description:       PayWave is a versatile plugin designed to integrate all Bangladeshi payment gateways seamlessly.
     * Version:           1.0
     * Author:            S. Saif
     * License:           GPL v2 or later
     * Text Domain:       paywave
     */

    if (!defined('ABSPATH')) {
        exit;
    }

    // Autoload classes
    spl_autoload_register(function ($class) {
        $prefix = 'PayWave\\';
        $base_dir = __DIR__ . '/inc/';

        // Only process the classes in our namespace
        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });

    // Now you can use the custom class
    // use PayWave\QueryPayment;

    // Create an instance of CustomClass
    // $custom_class_instance = new QueryPayment();
    // $custom_class_instance->save_transaction();

    // Activation and deactivation hooks
    function paywave_plugin_activation() {
        PayWave\RewriteRules::paywave_custom_rewrite_rule();
        flush_rewrite_rules();
    }
    register_activation_hook(__FILE__, 'paywave_plugin_activation');

    function paywave_plugin_deactivation() {
        flush_rewrite_rules();
    }
    register_deactivation_hook(__FILE__, 'paywave_plugin_deactivation');

    // Add the payment gateway
    function paywave_add_gateway_class($gateways) {
        $gateways[] = 'PayWave\PayWaveGateway';
        return $gateways;
    }
    add_filter('woocommerce_payment_gateways', 'paywave_add_gateway_class');

    // Initialize the gateway
    add_action('plugins_loaded', 'PayWave\PayWaveGateway::init');

    // Add custom rewrite rule
    add_action('init', 'PayWave\RewriteRules::paywave_custom_rewrite_rule');

    // Add custom query var
    add_filter('query_vars', 'PayWave\RewriteRules::paywave_custom_query_vars');

    // Handle the payment execution
    add_action('template_redirect', 'PayWave\ExecutePayment::handle_payment_execution');
