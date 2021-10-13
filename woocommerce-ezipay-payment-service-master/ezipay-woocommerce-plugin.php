<?php
/**
 * Plugin Name: EziPay Payment Service for WooCommerce
 * Plugin URI: index.php
 * Description: Secure Payment for Mobile Money and Cards
 * Version: 1.0
 * Author: AI Technologies
 * Developed by: Sebastian Anetey Shamo
 * Author URI: https://ezipaygh.com
 * Author Email: sebastian.shamo@aituniversal.com
 * License: GPLv2 or later
 * Requires at least: 4.4
 * Tested up to: 5.2
 * 
 * 
 * @package EziPay Payment Service
 * @category Plugin
 * @author Sebastian Anetey Shamo
 * @company AI Technologies

 Copyright 2021 AI Technologies
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Admin 'Settings' link on plugin page
 **/
function ezipay_wc_plugin_admin_action( $actions, $plugin_file ) {
	if ( false == strpos( $plugin_file, basename( __FILE__ ) ) ) {
		return $actions;
	}
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ezipay-wc-payment' ) . '">Settings</a>';

	array_unshift( $actions, $settings_link );
	return $actions;
}
add_filter( 'plugin_action_links', 'ezipay_wc_plugin_admin_action', 10, 2 );

function wh_log($log_msg)
{
    $log_filename = "log";
    if (!file_exists($log_filename)) 
    {
        // create directory/folder uploads.
        mkdir($log_filename, 0777, true);
    }
    $log_file_data = $log_filename.'/log_' . date('d-M-Y') . '.log';
    // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
    file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
} 
// call to function



function init_ezipay_wc_payment_gateway() {

	if ( class_exists( 'WC_Payment_Gateway' ) ) {
		class ezipay_WC_Payment_Gateway extends WC_Payment_Gateway {
			public function __construct() {
				$this->id                   = 'ezipay-wc-payment';
				$this->icon                 = plugins_url( 'assets/images/ezipay-pay.png', __FILE__ );
				$this->has_fields           = true;
				$this->method_title         = __( 'ezipay Payment Service', '' );
				$this->method_description = 'WooCommerce Payment Plugin for Ezipay Payment Service.';
				$this->description = "Secure payment with Mobile Money or Credit Card";
				$this->init_form_fields();
				$this->init_settings();
				$this->title                = $this->get_option( 'title' );
				$this->checkout_url = 'https://payments.ezipaygh.com/api/requesttoken';

				//$this->default_payment             = $this->get_option( 'default_payment');
				$this->ezipay_app_secret 	           = $this->get_option( 'app_secret' );
				$this->ezipay_api_key               = $this->get_option( 'api_key' );
				$this->ezipay_merchant_name         = $this->get_option( 'merchant_name' );
				$this->ezipay_merchant_logo         = $this->get_option( 'merchant_logo' );
				$this->ezipay_success_callback      = $this->get_option( 'success_callback' );
				$this->ezipay_failure_callback      = $this->get_option( 'failure_callback' );

				if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
					add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
				} else {
					add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
				}

				/*if ($this->get_option( 'default_payment' ) != "no") {
					add_filter( 'woocommerce_checkout_fields' , 'ezipay_custom_override_checkout_fields' );
				}*/
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'       => __( 'Enable/Disable', '' ),
						'type'        => 'checkbox',
						'description' => __( 'Check in order to enable eziPay WooCommerce Payment Gateway, otherwise, uncheck to disable.', '' ),
						'label'       => __( 'Enable Ezipay Payment', '' ),
						'default'     => 'no',
						'desc_tip'    => true,
					),
					/*'default_payment' => array(
						'title'       => __( 'Set as Default Payment Gateway', '' ),
						'type'        => 'checkbox',
						'description' => __( 'Check to enable or disable ezipay as your default payment gatement. Also this will remove some fields from woocommerce during checkout.', '' ),
						'label'       => __( 'Set as default', '' ),
						'default'     => 'no',
						'desc_tip'    => false,
					),*/
					'title' => array(
						'title'       => __( 'Title', '' ),
						'type'        => 'text',
						'class'       => 'is-read-only',
						'description' => __( 'This controls the title which the user sees during checkout.', '' ),
						'default'     => __( 'ezipay Checkout', '' ),
						'desc_tip'    => true,
					),
					'app_secret' => array(
						'title'       => __( 'App Secret', '' ),
						'type'        => 'text',
						'description' => __( 'App id given to you by ezipay Team', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => true,
					),
					'api_key' => array(
						'title'       => __( 'Apikey', '' ),
						'type'        => 'text',
						'description' => __( 'Apikey given to you by ezipay Team', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => true,
					),
					'description' => array(
						'title'       => __( 'Description', '' ),
						'type'        => 'text',
						'label'       => __( 'Enable to collect onsite payment.', '' ),
						'description' => __( 'Description for merchants payment', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => false,
					),
					'merchant_name' => array(
						'title'       => __( 'Merchant Name', '' ),
						'type'        => 'text',
						'description' => __( 'This will display merchant name on checkout', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => false,
					),
					'merchant_logo' => array(
						'title'       => __( 'Merchant Logo', '' ),
						'type'        => 'text',
						'description' => __( 'This will be used to display merchant logo', '' ),
						'default'     => __( '', '' ),
						'desc_tip'    => false,
					),
				);
			}

			/**
			 * Handle payment and process the order.
			 * Also tells WC where to redirect the user, and this is done with a returned array.
			 * Redirect to ezipay
			 'failurecallback'=> esc_url( plugin_dir_url(__FILE__).'status/failure.php'),
					'successcallback'=> esc_url( plugin_dir_url(__FILE__).'status/success.php'),
			 **/
			 
			function process_payment($order_id)
			{
				global $woocommerce;
				$order = new WC_Order( $order_id );

				// Get an instance of the WC_Order object
				$order = wc_get_order( $order_id );
				$order_data = $order->get_items();
				$transactionid = time() .'-'. $order_id;
				$message = $this->ezipay_api_key.($woocommerce->cart->total).$order_id.$transactionid ;
				$signature = hash_hmac('sha256', $message, $this->ezipay_app_secret);
				$payload = array (
					'merchantcode' => $this->ezipay_app_secret,
					'merchantid' => $this->ezipay_api_key,
					'customer' => $order_id,
					'amount' => $woocommerce->cart->total,
					'description' => $this->get_option( 'description' ),
					'signature' => $signature,
					'transactionid' => $transactionid
					
				);
				$test = json_encode($payload);
				//wh_log($test);
				$response = wp_remote_post($this->checkout_url, array(
					'method'    => 'POST',
					'body'      => json_encode($payload),
					'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
					'timeout'   => 45,
				)
										  );
				//wh_log($response);
				//echo var_dump($response);
				//Get response code and body 
				$response_code = wp_remote_retrieve_response_code( $response );
				$response_body = wp_remote_retrieve_body($response);
				//echo var_dump($response_body);
				wh_log($response_body);
				$checkout_response = json_decode($response_body);
				$respoDecode = json_decode($response);
				//wh_log($checkout_response);
				//echo var_dump($checkout_response);
				//wh_log($checkout_response);
				$token = $checkout_response->TokenId;
				//echo var_dump($token);
				//wh_log($token);
				$checkout_response = ( ! is_wp_error( $response ) ) ? json_decode( $response_body ) : null;
				$return_url = esc_url( plugin_dir_url(__FILE__).'status/success.php');
				//$token = urlencode($checkout_response->responsetoken);
				//$token = $response_body->TokenId;
			    //$checkout_url = "http://local.io/ezipay_checkout/?token=" . $token;
				$checkout_url_final ="https://payments.ezipaygh.com/checkout?token=".$token."&returnurl=".$return_url;
				switch ($response_code) {
					case 200:
						$order->update_status('on-hold', 'Payment in progress');
						//Set session variables
						@session_start();
						$_SESSION['ezipay_payload'] = $payload;
						$_SESSION['ezipay_order_id'] = $order_id;
						$_SESSION['ezipay_checkout_response'] = $checkout_response;
						return array (
							'result'   => 'success',
							'redirect' => $checkout_url_final//$checkout_response->checkouturl //$checkout_url
						);
						break;
					case 400:
						wc_add_notice("HTTP STATUS: $response_code - $checkout_response->reason", "error" );
						break;
					case 500:
						wc_add_notice("HTTP STATUS: $response_code - $checkout_response->reason", "error" );
						break;
					default:
						wc_add_notice("HTTP STATUS CODE here: $response_code Error Connecting to ezipay Payment Service, Please try again.", "error" );
						break;
				}
			}
		}//end of class 

	}
}

function wc_add_ezipay_payment_gateway( $methods ) {
	$methods[] = 'ezipay_WC_Payment_Gateway';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'wc_add_ezipay_payment_gateway' );
add_action( 'plugins_loaded', 'init_ezipay_wc_payment_gateway', 0 );
