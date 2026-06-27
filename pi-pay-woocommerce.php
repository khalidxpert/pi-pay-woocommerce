<?php
/**
 * Plugin Name: Pi Pay for WooCommerce
 * Plugin URI: https://app.xpertcreation.com/pi-pay
 * Description: Accept Pi Network cryptocurrency payments in your WooCommerce store. The easiest way to integrate Pi payments.
 * Version: 1.0.0
 * Author: XpertCreation
 * Author URI: https://app.xpertcreation.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pi-pay-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) exit;

define('PI_PAY_VERSION', '1.0.0');
define('PI_PAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PI_PAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Check WooCommerce is active
add_action('plugins_loaded', 'pi_pay_init');

function pi_pay_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>Pi Pay for WooCommerce</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    require_once PI_PAY_PLUGIN_PATH . 'includes/class-pi-gateway.php';
    require_once PI_PAY_PLUGIN_PATH . 'includes/class-pi-api.php';

    add_filter('woocommerce_payment_gateways', 'pi_pay_add_gateway');
}

function pi_pay_add_gateway($gateways) {
    $gateways[] = 'WC_Pi_Pay_Gateway';
    return $gateways;
}

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pi_pay_settings_link');
function pi_pay_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=pi_pay">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
