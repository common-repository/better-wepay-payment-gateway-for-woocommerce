<?php

/**
 * Plugin Name: Better WePay for WooCommerce (Lite)
 * Plugin URI: http://omnipay.io
 * Description: Accept Credit Card payments in your WooCommerce store via WePay
 * Version: 1.0
 * Author: Agbonghama Collins (W3Guy LLC)
 * Author URI: http://omnipay.io
 * Text Domain: better-wepay-for-woocommerce
 * Domain Path: /languages
 */

namespace OmnipayWP\WC_WePay_Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'W3GUY_WEPAY_LITE_FILE_PATH', __FILE__ );
define( 'W3GUY_WEPAY_LITE_ROOT', plugin_dir_path( __FILE__ ) );
define( 'W3GUY_WEPAY_LITE_ROOT_URL', plugin_dir_url( __FILE__ ) );
define( 'W3GUY_WEPAY_LITE_WOOCOMMERCE_ID', 'better-wepay-for-woocommerce' );
define( 'W3GUY_WEPAY_LITE_ASSETS_URL', W3GUY_WEPAY_LITE_ROOT_URL . 'assets/' );

class Base {

	public function __construct() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway_name' ) );
	}


	/**
	 * Add the Gateway to WooCommerce
	 **/
	function add_gateway_name( $methods ) {
		$methods[] = 'WC_WePay_Gateway_Lite';

		return $methods;
	}

}

function wepay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	load_plugin_textdomain( 'better-wepay-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	require W3GUY_WEPAY_LITE_ROOT . 'vendor/autoload.php';
	require W3GUY_WEPAY_LITE_ROOT . 'class.wc-wepay-lite.php';

	new Base;
}


add_action( 'plugins_loaded', 'OmnipayWP\WC_WePay_Lite\wepay_init', 0 );
