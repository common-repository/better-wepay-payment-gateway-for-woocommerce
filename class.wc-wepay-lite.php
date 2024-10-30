<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Omnipay\Omnipay;

/**
 * Gateway class
 */
class WC_WePay_Gateway_Lite extends \WC_Payment_Gateway {

	/** @var bool Whether or not logging is enabled */
	public $debug_active = false;

	/** @var WC_Logger Logger instance */
	public $log = false;

	/** @var string WC_API for the gateway - being use as return URL */
	public $returnUrl;

	function __construct() {

		// The global ID for this Payment method
		$this->id = W3GUY_WEPAY_LITE_WOOCOMMERCE_ID;

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "WePay", 'better-wepay-for-woocommerce' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "WePay Payment Gateway Plug-in for WooCommerce", 'better-wepay-for-woocommerce' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "WePay", 'better-wepay-for-woocommerce' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;

		$this->supports = array();

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		$this->init_settings();

		$this->debug_active = true;
		$this->has_fields = false;

		$this->description  = $this->get_option( 'description' );

		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		// Set if the place order button should be renamed on selection.
		$this->order_button_text = __( 'Proceed to WePay', 'better-wepay-for-woocommerce' );

		// Save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' ) );
		}

		// Hooks
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'handle_wc_api' ) );
	}

	/**
	 * Gateway settings page.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable / Disable', 'better-wepay-for-woocommerce' ),
				'label'   => __( 'Enable this payment gateway', 'better-wepay-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			'environment'    => array(
				'title'       => __( 'WePay Test Mode', 'better-wepay-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'better-wepay-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf( __( 'WePay stage environment can be used to test payments. Sign up for an account <a href="%s">here</a>',
					'better-wepay-for-woocommerce' ), 'https://stage.wepay.com' ),
				'default'     => 'no',
			),
			'title'          => array(
				'title'   => __( 'Title', 'better-wepay-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'WePay', 'better-wepay-for-woocommerce' ),
			),
			'description'    => array(
				'title'   => __( 'Description', 'better-wepay-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Pay securely using your credit card.', 'better-wepay-for-woocommerce' ),
				'css'     => 'max-width:350px;'
			),
			'account_id'     => array(
				'title'       => __( 'Account ID', 'better-wepay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your account ID.', 'better-wepay-for-woocommerce' ),
			),
			'access_token'   => array(
				'title'       => __( 'Access Token', 'better-wepay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your access token.', 'better-wepay-for-woocommerce' ),
			),
			'client_id'      => array(
				'title'       => __( 'Client ID', 'better-wepay-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your client ID.', 'better-wepay-for-woocommerce' ),
			),
			'payment_type'   => array(
				'title'       => __( 'Payment Type', 'better-wepay-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the payment type.', 'better-wepay-for-woocommerce' ),
				'options'     => array(
					'goods'    => __( 'Goods', 'better-wepay-for-woocommerce' ),
					'service'  => __( 'Service', 'better-wepay-for-woocommerce' ),
					'donation' => __( 'Donation', 'better-wepay-for-woocommerce' ),
					'event'    => __( 'Event', 'better-wepay-for-woocommerce' ),
					'personal' => __( 'Personal', 'better-wepay-for-woocommerce' ),
				),
			)
		);
	}



	public function admin_options() { ?>

		<h3><?php echo ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings', 'woocommerce' ) ; ?></h3>

		<?php echo ( ! empty( $this->method_description ) ) ? wpautop( $this->method_description ) : ''; ?>

		<div id="message" class="error notice"><p>
				<?php printf(
					__(
						'iFrame checkout style, on-site checkout style and access to support from WooCommerce experts. <strong><a target="_blank" href="%s">Upgrade to PRO Now</a></strong>.',
						'better-wepay-for-woocommerce'
					), 'https://omnipay.io/downloads/better-wepay-payment-gateway-for-woocommerce/'
				); ?>
			</p></div>
		<table class="form-table">
		<?php $this->generate_settings_html(); ?>
		</table><?php
	}

	/**
	 * Is gateway in test mode?
	 *
	 * @return bool
	 */
	public function is_test_mode() {
		return $this->environment == "yes";
	}

	/**
	 * WooCommerce payment processing function/method.
	 *
	 * @inheritdoc
	 *
	 * @param int $order_id
	 *
	 * @return mixed
	 */
	public function process_payment( $order_id ) {
		$order           = new WC_Order( $order_id );
		$this->returnUrl = WC()->api_request_url( 'WC_WePay_Gateway_Lite' );

		do_action('omnipay_wepay_lite_before_process_payment');

		// call the appropriate method to process the payment.
		return $this->process_off_site_payment( $order );
	}


	/**
	 * Return WePay Omnipay instance.
	 *
	 * @return \Omnipay\WePay\Message\PurchaseRequest;
	 */
	public function omnipay_gateway_instance() {

		$gateway = Omnipay::create( 'WePay' );
		$gateway->setAccountId( $this->account_id );
		$gateway->setAccessToken( $this->access_token );
		$gateway->setTestMode( $this->is_test_mode() );

		return $gateway;
	}

	/**
	 * Process off-site payment.
	 *
	 * @param $order
	 *
	 * @return array|void
	 */
	public function process_off_site_payment( WC_Order $order ) {
		try {
			$gateway = $this->omnipay_gateway_instance();
			$gateway->setMode('regular');

			$formData = array(
				'firstName' => $order->billing_first_name,
				'lastName'  => $order->billing_last_name,
				'email'     => $order->billing_email
			);

			$cart_items_description = '';
			$order_cart             = $order->get_items();
			foreach ( $order_cart as $index => $item ) {
				$cart_items_description .= $item['name'] . ' x ' . $item['qty'] . "\r\n";
			}

			$response = $gateway->purchase(
				apply_filters( 'omnipay_wepay_lite_args', array(
						'transactionId' => $order->get_order_number(),
						'amount'        => $order->order_total,
						'currency'      => get_woocommerce_currency(),
						'description'   => $cart_items_description,
						'returnUrl'     => $this->returnUrl,
						'card'          => $formData
					)
				)
			)->send();

			// save checkout URL
			update_post_meta( $order->id, '_wc_wepay_checkout_url', $response->getRedirectUrl() );


			do_action('omnipay_wepay_lite_before_payment_redirect', $response);

			if ( $response->isRedirect() ) {
				return array(
					'result'   => 'success',
					'redirect' => $response->getRedirectUrl(),
				);
			} else {
				$error = $response->getMessage();
				$order->add_order_note( sprintf( "%s Payments Failed: '%s'", $this->method_title, $error ) );
				wc_add_notice( $error, 'error' );
				$this->log( $error );

				return array(
					'result'   => 'fail',
					'redirect' => ''
				);
			}
		} catch ( Exception $e ) {
			$error = $e->getMessage();
			$order->add_order_note( sprintf( "%s Payments Failed: '%s'", $this->method_title, $error ) );
			wc_add_notice( $error, "error" );

			return array(
				'result'   => 'fail',
				'redirect' => ''
			);
		}
	}

	/**
	 * Handles off-site return and processing of order.
	 */
	public function handle_wc_api() {
		if ( isset( $_GET['checkout_id'] ) ) {
			$gateway = $this->omnipay_gateway_instance();

			$response = $gateway->completePurchase()->send();

			$order = new WC_Order( $response->getTransactionId() );

			if ( $response->isSuccessful() ) {
				$transaction_ref = $response->getTransactionReference();
				$order->payment_complete();
				// Add order note
				$order->add_order_note(
					sprintf( __( 'WePay payment complete (Charge ID: %s)', 'better-wepay-for-woocommerce' ),
						$transaction_ref
					)
				);

				WC()->cart->empty_cart();
				wp_redirect( $this->get_return_url( $order ) );
				exit;
			} else {
				$error = $response->getMessage();
				$order->add_order_note( sprintf( "%s Payments Failed: '%s'", $this->method_title, $error ) );
				wc_add_notice( $error, 'error' );
				$this->log( $error );
				wp_redirect( wc_get_checkout_url() );
				exit;
			}
		}
	}


	/**
	 * Logger helper function.
	 *
	 * @param $message
	 */
	public function log( $message ) {
		if ( $this->debug_active ) {
			if ( ! ( $this->log ) ) {
				$this->log = new WC_Logger();
			}
			$this->log->add( 'omnipay_wepay_lite', $message );
		}
	}
}
