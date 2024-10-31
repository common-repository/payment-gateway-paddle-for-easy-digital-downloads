<?php 
/**
* Plugin Name: Payment Gateway Paddle for Easy Digital Downloads
* Plugin URI: https://github.com/ThemeBing/paddle-payment-gateway-for-easy-digital-downloads
* Description: This is Paddle Payment Gateway plugin for Easy Digital Downloads.
* Version: 1.0.2
* Author: themebing
* Author URI: http://themebing.com
* Text Domain: paddle
* License: GPL/GNU.
* Domain Path: /languages
*/

defined( 'ABSPATH' ) or exit;

// Make sure WooCommerce is active
if ( ! in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Payment Gateway Paddle for Easy Digital Downloads
 */
class themebingPaddlePaymentGatewayEDD {
	
	public function __construct(){
		
		add_filter('edd_payment_gateways', array($this,'paddle_edd_register_gateway'));
		add_action('edd_paddle_payment_gateway_cc_form', array($this,'paddle_payment_gateway_cc_form'));
		add_action('edd_gateway_paddle_payment_gateway', array($this,'paddle_edd_process_payment'));
		add_filter('edd_settings_gateways', array($this,'paddle_edd_add_settings'));
		add_filter('init', array($this,'paddle_init'));
	}


	// registers the gateway
	public function paddle_edd_register_gateway($gateways) {
		$gateways['paddle_payment_gateway'] = array(
			'admin_label' => 'Paddle Payment Gateway',
			'checkout_label' => esc_html__('Paddle Payment Gateway', 'paddle')
		);
		return $gateways;
	}


	// Credit Card Form
	public function paddle_payment_gateway_cc_form() {
		// register the action to remove default CC form
		return;
	}

	// processes the payment
	public function paddle_edd_process_payment($purchase_data) {
	 
		global $edd_options;

		// check for any stored errors
		if(!$errors) {
	 
			/**********************************
			* setup the payment details
			**********************************/
	 		
			$payment = array( 
				'price'=> $purchase_data['price'], 
				'date'=> $purchase_data['date'], 
				'user_email'=> $purchase_data['user_email'],
				'purchase_key'=> $purchase_data['purchase_key'],
				'currency'=> $edd_options['currency'],
				'downloads'=> $purchase_data['downloads'],
				'cart_details'=> $purchase_data['cart_details'],
				'user_info'=> $purchase_data['user_info'],
				'status'=> 'pending'
			);
	 
			// record the pending payment
			$payment = edd_insert_payment($payment);
	 
			foreach ( $purchase_data['cart_details'] as $item ) {
			    $product_name[] = $item['name'];
			    $product_id = $item['id'];
			}

			$generate_pay_link = json_decode(wp_remote_retrieve_body(wp_remote_post( 'https://vendors.paddle.com/api/2.0/product/generate_pay_link', array( 
			    'method' => 'POST',
				'timeout' => 30,
				'httpversion' => '1.1',
				'body' => array(
					'vendor_id' => $edd_options[ 'vendor_id' ],
					'vendor_auth_code' => $edd_options[ 'vendor_auth_code' ],
					'title' => implode(', ', $product_name),
					'image_url' => wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), array('220','220'),true )[0],
					'prices' => array(edd_get_currency().':'.$purchase_data['price']),
					'customer_email' => $purchase_data['user_email'],
					'affiliates' => array('51310:0.02'),
					'return_url' => get_permalink( $edd_options['success_page'] ),
					'webhook_url' => get_bloginfo('url') . '/?purchase_key=' . $purchase_data['purchase_key']
				)
			))));

			if (is_wp_error($generate_pay_link)) {
		      	edd_set_error('api_fail', esc_html__('Something went wrong. Unable to get API response.', 'paddle'));
		      	error_log('Paddle error. Unable to get API response. Method: ' . __METHOD__ . ' Error message: ' . $generate_pay_link->get_error_message());
		    } else {
		    	if ($generate_pay_link && $generate_pay_link->success === true) {
			        edd_empty_cart();
			        wp_redirect($generate_pay_link->response->url,302);
			        exit;
			    } else {
		          wp_redirect($purchase_data['parent_url'], 302);
		          edd_set_error('api_fail', esc_html__('Something went wrong. Unable to get API response.', 'paddle'));
		        }
		    }
	 
		} else {
			$fail = true; // errors were detected
		}
	 
		if( $fail !== false ) {
			// if errors are present, send the user back to the purchase page so they can be corrected
			edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
		}
	}


	// adds the settings to the Payment Gateways section
	public function paddle_edd_add_settings($settings) {

		global $edd_options;

		if ($edd_options[ 'vendor_id' ] && $edd_options[ 'vendor_auth_code' ]) {
			$connection_button = '<p style=\'color:green\'>Your paddle account has already been connected</p>' .
			'<a class=\'button-primary open_paddle_integration_window\'>'.esc_html__('Reconnect your Paddle Account','paddle').'</a>';
		} else {
			$connection_button = '<a class=\'button-primary open_paddle_integration_window\'>'.esc_html__('Connect your Paddle Account','paddle').'</a>';
		}
	 
		$paddle_gateway_settings = array(
			array(
				'id'   => 'paddle_payment_gateway_settings',
				'name' => '<strong>' . __('Paddle Gateway Settings', 'paddle') . '</strong>',
				'desc' => __('Configure the gateway settings', 'paddle'),
				'type' => 'header'
			),
			array(
				'id'   => 'integration',
				'name' => __('Integration', 'paddle'),
				'desc' => $connection_button . '<br /><p class = "description"><a href="#!" id=\'manualEntry\'>'.esc_html__( 'Click here to enter your account details manually', 'paddle' ).'</a></p>',
				'type' => 'text'
			),
			array(
				'id'   => 'vendor_id',
				'name' => __('Vendor ID', 'paddle'),
				'desc' => '<a href="https://vendors.paddle.com/authentication" target="_blank">'.esc_html__( 'Get Vendor ID.', 'paddle' ).'</a>',
				'type' => 'text',
				'size' => 'regular'
			),
			array(
				'id'   => 'vendor_auth_code',
				'name' => __('Vendor auth code', 'paddle'),
				'desc' => '<a href="https://vendors.paddle.com/authentication" target="_blank">'.esc_html__( 'Get Auth Code.', 'paddle' ).'</a>',
				'type' => 'text',
				'size' => 'regular'
			)
		);
	 
		return array_merge($settings, $paddle_gateway_settings);	
	}

	// Paddle init
	public function paddle_init() {
		// if the merchant payment is complete, set a flag
		if (isset($_GET['purchase_key'])) {
			$payment_id = edd_get_purchase_id_by_key( $_GET['purchase_key'] );
			edd_update_payment_status($payment_id, 'complete');
			// go to the success page			
			edd_send_to_success_page();
			exit;
		}		

		add_action( 'admin_enqueue_scripts', array($this,'paddle_admin_enqueue_scripts'));
	}

	// Admin script for popup integration
	public function paddle_admin_enqueue_scripts() {
		wp_enqueue_script( 'paddle-js', plugins_url('assets/js/admin-paddle.js', __FILE__), array('jquery'));
		wp_localize_script('paddle-js', 'integration_popup', array('url' => 'https://vendors.paddle.com/vendor/external/integrate?app_name=EDD Paddle Payment Gateway&app_description=Easy Digital Downloads Paddle Payment Gateway Plugin for ' . get_bloginfo('name').'&app_icon='.plugins_url('assets/images/paddle.png', __FILE__)));
		
		wp_enqueue_style( 'paddle-admin-css', plugins_url('assets/css/paddle.css', __FILE__));
	}
}

new themebingPaddlePaymentGatewayEDD;