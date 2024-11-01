<?php
/*
Plugin Name: Effective Payment solutions
Plugin URI:  
Description: 
Author: 
Author URI:
Version: 1.2.2
Requires at least: 4.4
Tested up to: 5.3.0
WC requires at least: 2.6
WC tested up to: 3.8
Text Domain:
Domain Path: /lang/
*/

defined('ABSPATH') or die();

if (!defined('WL_WEPSR_PLUGIN_URL')) {
    define('WL_WEPSR_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('WL_WEPSR_PLUGIN_DIR_PATH')) {
    define('WL_WEPSR_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
}

if (!defined('WL_WEPSR_PLUGIN_BASENAME')) {
    define('WL_WEPSR_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

if (!defined('WL_WEPSR_PLUGIN_FILE')) {
    define('WL_WEPSR_PLUGIN_FILE', __FILE__);
}

/**
 * WooCommerce fallback notice.
 *
 */
function wl_wepsr_woocommerce_missing_wc_notice()
{
    /* translators: 1. URL link. */
    echo '<div class="error"><p><strong>' . sprintf(esc_html__( 'Effective Payments to be installed and active. You can download %s here.', 'effective-payment-solutions-by-'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

/**
 * initialize Instamojo Gateway Class
 */
function wl_wepsr_offline_gateway_init()
{

    load_plugin_textdomain('effective-payment-solutions-by-', false, basename(WL_WEPSR_PLUGIN_DIR_PATH) . '/lang');

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wl_wepsr_woocommerce_missing_wc_notice');
        return;
    }

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require('admin/instamojo/class-instamojo-payments.php');
    require('admin/paytm/class-paytm-payments.php');
    require('admin/cashfree/class-cashfree-payments.php');

    add_filter( 'woocommerce_currencies', 'wl_wepsr_paytm_add_indian_rupee' );
    add_filter( 'woocommerce_currency_symbol', 'wl_wepsr_add_indian_rupee_currency_symbol', 10, 2 );
    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wl_wepsr_add_action_links' );
    add_filter( 'woocommerce_payment_gateways', 'wl_wepsr_add_gateway_class' );
    add_action( 'template_redirect', 'wl_wepsr_init_instamojo_payment_gateway_redirect' );
}
add_action('plugins_loaded', 'wl_wepsr_offline_gateway_init', 11);

/**
 * look for redirect from instamojo.
 */
function wl_wepsr_init_instamojo_payment_gateway_redirect()
{
    if (isset($_REQUEST['payment_id']) && isset($_REQUEST['payment_request_id']) && isset($_REQUEST['payment_status'])) {
        include_once "admin/instamojo/payment-confirm.php";
    }
}

/**
 * Add Gateway class to all payment gateway methods
 */
function wl_wepsr_add_gateway_class($methods)
{
    $methods[] = 'WC_Gateway_Instamojo_WEPSR';
    $methods[] = 'WC_Gateway_Paytm_WEPSR';
    $methods[] = 'WC_Gateway_Cashfree_WEPSR';
    return $methods;
}

/**
 * To generate log
 */
function wl_wepsr_insta_log($message)
{
    $log = new WC_Logger();
    $log->add( 'Instamojo Payment Gateway ( By  ) Log', $message );
}

/**
 * function for truncating client_id and client_secret
 */
function wl_wepsr_truncate_secret($secret)
{
    return substr($secret, 0, 4) . str_repeat('x', 10);
}

/**
 * add Indian rupee wl_wcp_paytm_add_indian_rupee()
 *
 */
function wl_wepsr_paytm_add_indian_rupee($currencies)
{
    $currencies['INR'] = __('Indian Rupee', 'woocommerce');
    return $currencies;
} // add indian rupee wl_wcp_paytm_add_indian_rupee() end

/**
 * Add Indian rupee currency symbol if not exists wl_wcp_paytm_add_indian_rupee_currency_symbol()
 *
 */
function wl_wepsr_add_indian_rupee_currency_symbol($currency_symbol, $currency)
{
    switch ($currency) {
        case 'INR':
            $currency_symbol = 'Rs.';
            break;
    }
    return $currency_symbol;
}// Add Indian rupee currency symbol if not exists wl_wcp_paytm_add_indian_rupee_currency_symbol() end

function wl_wepsr_add_action_links ( $links ) {
    $mylinks = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '"><b>Settings</b></a>',
    );
    return array_merge($mylinks, $links);
}

require_once( 'admin/admin.php' );