<?php
/**
 * Plugin Name: XpertCreation Pi Network Payments for WooCommerce
 * Plugin URI: https://app.xpertcreation.com/pi-pay
 * Description: Accept Pi Network cryptocurrency payments in your WooCommerce store. Not officially affiliated with Pi Network or WooCommerce.
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
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

define('PI_PAY_VERSION', '1.0.0');
define('PI_PAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PI_PAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action('plugins_loaded', 'xpertcreation_pi_pay_init');

function xpertcreation_pi_pay_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p><strong>XpertCreation Pi Network Payments for WooCommerce</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }
    require_once PI_PAY_PLUGIN_PATH . 'includes/class-pi-gateway.php';
    require_once PI_PAY_PLUGIN_PATH . 'includes/class-pi-api.php';
    add_filter('woocommerce_payment_gateways', 'xpertcreation_pi_pay_add_gateway');
}

function xpertcreation_pi_pay_add_gateway($gateways) {
    $gateways[] = 'XpertPi_Gateway';
    return $gateways;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'xpertcreation_pi_pay_settings_link');

function xpertcreation_pi_pay_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=xpertcreation_pi_pay">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
