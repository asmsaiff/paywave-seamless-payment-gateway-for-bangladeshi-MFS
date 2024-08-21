<?php
/**
 * Plugin Name:       PayWave
 * Plugin URI:        https://paywave.com
 * Description:       PayWave integrates Bangladeshi payment gateways like bKash, Nagad, Rocket, etc.
 * Version:           1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            S. Saif
 * Author URI:        https://saif.im
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       paywave
 */

if (!defined('ABSPATH')) {
    exit;
}

echo $_SESSION["base_url"];

// Autoload files and classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'PayWave\\') === 0) {
        $class = str_replace('PayWave\\', '', $class);
        $path = __DIR__ . '/inc/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

// Register Activation and Deactivation hooks
register_activation_hook(__FILE__, ['PayWave\\Hooks', 'on_activation']);
register_deactivation_hook(__FILE__, ['PayWave\\Hooks', 'on_deactivation']);

// Initialize the plugin
add_action('plugins_loaded', ['PayWave\\Init', 'run']);
