<?php
/*
 * Plugin Name: sKash Payment
 * Description: Have your sKash customers pay with their Mobile, without entering any confidential information.
 * Author: sKash
 * Author URI: https://skash.com
 * Version: 1.2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	add_action('plugins_loaded', 'spfw_woocommerce_init', 0);
	
	function spfw_woocommerce_init() {
		if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

		/**
		 * Skesh Payment Gateway class
		 */
		class SPFW_Woocommerce_Gateway extends WC_Payment_Gateway {
			
			public function __construct(){
				$this->id = 'skash'; // payment gateway plugin ID
				$this->icon = plugins_url( '/skash-payment/skash-logo.png', dirname(__FILE__) ); // URL of the icon that will be displayed on checkout page near your gateway name
				$this->has_fields = false; // in case you need a custom credit card form
				$this->method_title = __('sKash Payment');
				$this->method_description = __('Have your sKash customers pay with their Mobile, without entering any confidential information.'); // will be displayed on the options page
				// gateways can support subscriptions, refunds, saved payment methods
				$this->supports = array(
					'products'
				);
				// Method with all the options fields
				$this->init_form_fields();
				// Load the settings.
				$this->init_settings();				
				$this->title = __($this->get_option( 'title' ));
				$this->description = __($this->get_option( 'description' ));
				$this->enabled = $this->get_option( 'enabled' );
				$this->currency = $this->get_option( 'currency');
				$this->certificate_key = $this->get_option( 'certificate_key' );
				$this->test_certificate_key = $this->get_option( 'test_certificate_key' );
				$this->test_merchant_id = $this->get_option( 'test_merchant_id' );
				$this->live_merchant_id = $this->get_option( 'live_merchant_id' );
				$this->skash_iframe_mode = $this->get_option( 'skash-iframe-mode' );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );	
				add_action('woocommerce_receipt_'.$this->id, array($this, 'spfw_woocommerce_receipt_page'), 10);
				add_action('woocommerce_thankyou_'.$this->id, array($this, 'spfw_woocommerce_thankyou_page'));
				add_action( 'woocommerce_api_'.$this->id, array( $this, 'spfw_webhook' ),20 );
			}

			function init_form_fields(){
				$this->form_fields = apply_filters( 'wc_offline_form_fields', array(
					'enabled' => array(
						'title'			=>	__('Enable/Disable'),
						'label'			=>	__('Enable sKash Gateway'),
						'type'			=>	'checkbox',
						'description'	=>	'',
						'default'		=>	'no'
					),
					'title' => array(
						'title'			=>	__('Title'),
						'type'			=>	'text',
						'description'	=>	__('This controls the title which the user sees during checkout.'),
						'default'		=>	'sKash Method',
						'desc_tip'		=>	true,
					),
					'description' => array(
						'title'			=>	__('Description'),
						'type'			=>	'textarea',
						'description'	=>	__('This controls the description which the user sees during checkout.'),
						'default'		=>	'Pay less with sKash',
						'desc_tip'		=>	true,
					),
					'test_merchant_id'=> array(
						'title'			=>	__('Test Merchant Id'),
						'type'			=>	'text',
						'default'		=> $this->get_option( 'test_merchant_id' )
					),
					
					'test_certificate_key'=> array(
						'title'			=>	__('sKash Certificate for Test'),
						'type'			=>	'text',
						'default'		=> $this->get_option( 'test_certificate_key' )
					),

					'live_mode' => array(
						'title'			=>	__('Live mode'),
						'label'			=>	__('Enable Live Mode'),
						'type'			=>	'checkbox',
						'description'	=>	'',
						'default'		=>	$this->get_option( 'live_mode' ),
						'desc_tip'		=>	true,
					),


					'live_merchant_id'=> array(
						'title'			=>	__('Live Merchant Id'),
						'type'			=>	'text',
						'default'		=>  $this->get_option( 'live_merchant_id' )
					),
					'certificate_key'=> array(
						'title'			=>	__('sKash Certificate for Live'),
						'type'			=>	'text',
						'default'		=>  $this->get_option( 'certificate_key' )
					),

				));
			}

			function payment_fields(){
				echo __($this->description);
			}

			function spfw_woocommerce_receipt_page( $order ) {
				global $woocommerce;
				$order = new WC_Order($order);
				$order_status = $order->get_status();
				$order_id = $order->get_id();
				$order_key = $order->get_order_key();
				$skash_sandbox = $this->get_option('live_mode');
				if($skash_sandbox == 'yes'){
					$certificate_key = $this->get_option('certificate_key');
				}else{
					$certificate_key = $this->get_option('test_certificate_key');
				}

				if(isset($_GET['Flag'])) {
					$flag = sanitize_text_field($_GET['Flag']);
					if ($flag == '1'){
						$match_secure = sanitize_text_field($_GET['Flag']).sanitize_text_field($_GET['ReferenceNo']).sanitize_text_field($_GET['TranID']).sanitize_text_field($_GET['ReturnText']).$certificate_key;
						$SecureHash = base64_encode(hash('sha512', $match_secure, true));
                        /*if($SecureHash == rawurldecode(sanitize_text_field($_GET['SecureHash']))) {
                            $order->update_status('completed');
                        }else if($order_status != 'completed'){
                            $order->update_status('processing', "Your order is Processing");
                        }*/
                        if($SecureHash == rawurldecode(sanitize_text_field($_GET['SecureHash']))) {
                            $order->update_status('processing', "Your order is Processing");
                        }
					}else{
			    		$order->update_status( 'failed', sanitize_text_field($_GET['ReturnText']) );
					}
                    $order_ref = $order_id.'&key='.$order_key;
                    $checkout_page_id = wc_get_page_id( 'checkout' );
                    $checkout_page_url = $checkout_page_id ? get_permalink( $checkout_page_id ) : '';
                    $data= add_query_arg( 'order-received', $order_ref, $checkout_page_url );
                    $return_url = $data;
					wp_redirect( $return_url );
					exit;
				}
				$cardForm = '<iframe name="skash-iframe-QR" id="skash-iframe-QR" src="" scrolling="auto" height="600px"width="900px" style="border: 1px solid #ccc; margin: 10px 0"></iframe>
						   <script type="text/javascript">
							  (function($){
							    $(document).ready(function(){
								  	var iframe_id = $("#skash-iframe-QR"); 
								  	if($(window).width() < 768){
								  		window.addEventListener ("message", function (event) {
											if (event.data.url) {
												setTimeout(function(){
													location = event.data.url;
												}, 2000);
											}
										}, false);
										$("#skash-iframe-QR").attr("height",250);
										$("#skash-iframe-QR").attr("width",320);
										$("#skash-iframe-QR").attr("name","skash-iframe-DL");
										$("#skash-iframe-QR").attr("id","skash-iframe-DL");
										$("#skash-iframe-QR").attr("SandBox","allow-same-origin allow-scripts allow-popups allow-forms allow-top-navigation allow-modals");
										$.ajax({
								    		type: "post",
								    		url: "'.get_home_url().'/wp-admin/admin-ajax.php",
								    		data: {
								    			action: "spfw_mobile_payment_url",
								    			order_id: "'.$order_id.'",
								    		},
								    		success: function(data){
								    			$("#skash-iframe-DL").attr("src",data.data);

								    		}
										});
									}else{
								    	$.ajax({
								    		type: "post",
								    		url: "'.get_home_url().'/wp-admin/admin-ajax.php",
								    		data: {
								    			action: "spfw_window_payment_url",
								    			order_id: "'.$order_id.'",
								    		},
								    		success: function(data){
								    			$("#skash-iframe-QR").attr("src",data.data);
								    		}
										});
									}
								});   
						  	})(jQuery);
						  </script>';

				echo ($cardForm);
			}

			/**
			 * Process the payment and return the result
			 *
			 * @access public
			 * @param int $order_id
			 * @return array
			 */
			function process_payment( $order_id ) {
				global $woocommerce;
				$order = new WC_Order($order_id);

				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url( true )
				);
			}

			function spfw_woocommerce_thankyou_page($order_id){
				
			}

			/**
			 * Skash Payment IPN webhook
			 */
			public function spfw_webhook() {

			}

		}
		
		/**
		 * Add the Gateway to WooCommerce
		 */
		function spfw_woocommerce_add_gateway($methods) {
			$methods[] = 'SPFW_Woocommerce_Gateway';
			return $methods;
		}
		
		add_filter('woocommerce_payment_gateways', 'spfw_woocommerce_add_gateway' );
	}

}


add_action( 'wp_ajax_spfw_window_payment_url', 'spfw_prepare_window_payment_url');
add_action('wp_ajax_nopriv_spfw_window_payment_url', 'spfw_prepare_window_payment_url');

function spfw_prepare_window_payment_url(){
	global $woocommerce;  
	$order_id = sanitize_text_field($_POST['order_id']);
	$order = wc_get_order( $order_id );
	if(empty($order)){
		return '';
	}
	$installed_payment_methods = WC()->payment_gateways->payment_gateways();
	$data = $installed_payment_methods['skash']->settings;
	$order_key = $order->get_order_key();
	$amount = empty($woocommerce->cart->total) ? $order->get_total() : $woocommerce->cart->total;
    $amount = number_format((float)$amount, 2, '.', '');
    $checkout_page_id = wc_get_page_id( 'checkout' );
    $checkout_page_url = $checkout_page_id ? get_permalink( $checkout_page_id ) : '/checkout';
    $callbackurl= add_query_arg( [
        'order-pay'=> $order_id,
        'key' =>$order_key
    ], $checkout_page_url);
	$currency = $order->get_currency();
	$created_at = $order->get_date_created();
	$timestamp = strtotime($created_at)*1000;
	$skash_sandbox = $data['live_mode'];
	if( $skash_sandbox == 'yes'){
        $payment_url = 'https://skash.com/payment';
	    $merchant_id = $data['live_merchant_id'];
	}else{
        $payment_url = 'https://skash.com/payment_test';
	    $merchant_id = $data['test_merchant_id'];
	}
	$SecureHash = spfw_window_secure_hash($order_id);
	$url = $payment_url.'/api?rq=paySkashQR&TranID='.$order_id.'&Amount='.$amount.'&Currency='.$currency.'&CallBackURL='.rawurlencode($callbackurl).'&SecureHash='.rawurlencode($SecureHash).'&TS='.$timestamp.'&TranTS='.$timestamp.'&MerchantID='.$merchant_id;
	wp_send_json_success($url);
}


function spfw_window_secure_hash($order_id){
	global $woocommerce;
	$installed_payment_methods = WC()->payment_gateways->payment_gateways();
	$data = $installed_payment_methods['skash']->settings;
	$order = wc_get_order( $order_id );
	$amount = empty($woocommerce->cart->total) ? $order->get_total() : $woocommerce->cart->total;
	$amount = number_format((float)$amount, 2, '.', '');
	$currency = $order->get_currency();
	$created_at = $order->get_date_created();
	$timestamp = strtotime($created_at)*1000;
	$skash_sandbox = $data['live_mode'];
	if( $skash_sandbox == 'yes'){
	    $merchant_id = $data['live_merchant_id'];
	    $certificate_key = $data['certificate_key'];
	}else{
	    $merchant_id = $data['test_merchant_id'];
		$certificate_key = $data['test_certificate_key'];
	}
	$window_secure = $order_id.$timestamp.$amount.$currency.$timestamp.$certificate_key;
	$WindowSecureHash = base64_encode(hash('sha512', $window_secure, true));

	return $WindowSecureHash;
}


add_action( 'wp_ajax_spfw_mobile_payment_url', 'spfw_prepare_mobile_payment_url');
add_action('wp_ajax_nopriv_spfw_mobile_payment_url', 'spfw_prepare_mobile_payment_url');

function spfw_prepare_mobile_payment_url(){
	global $woocommerce;  
	$order_id = sanitize_text_field($_POST['order_id']);
	$installed_payment_methods = WC()->payment_gateways->payment_gateways();
	$data = $installed_payment_methods['skash']->settings;
	$order = wc_get_order( $order_id );
	$order_key = $order->get_order_key();
	$amount = empty($woocommerce->cart->total) ? $order->get_total() : $woocommerce->cart->total;
	$amount = number_format((float)$amount, 2, '.', '');
    $checkout_page_id = wc_get_page_id( 'checkout' );
    $checkout_page_url = $checkout_page_id ? get_permalink( $checkout_page_id ) : '/checkout';
    $callbackurl= add_query_arg( [
        'order-pay'=> $order_id,
        'key' =>$order_key
    ], $checkout_page_url);
	$currency = $order->get_currency();
	$created_at = $order->get_date_created();
	$timestamp = strtotime($created_at)*1000;
	$skash_sandbox = $data['live_mode'];
	if( $skash_sandbox == 'yes'){
        $payment_url = 'https://skash.com/payment';
	    $merchant_id = $data['live_merchant_id'];
	}else{
        $payment_url = 'https://skash.com/payment_test';
	    $merchant_id = $data['test_merchant_id'];
	}
	$MobileSecureHash = spfw_mobile_secure_hash($order_id);
	$browser = spfw_get_browser_type();
	$mobile_url = $payment_url."/api?rq=paySkashDL&TranID=".$order_id."&Amount=".$amount."&Currency=".$currency."&CallBackURL=".rawurlencode($callbackurl)."&SecureHash=".rawurlencode($MobileSecureHash)."&TS=".$timestamp."&TranTS=".$timestamp."&MerchantID=".$merchant_id."&currentUrl=".rawurlencode($callbackurl)."&browsertype=".$browser;
	wp_send_json_success($mobile_url);
}

function spfw_mobile_secure_hash($order_id){
	global $woocommerce;
	$order = wc_get_order( $order_id );
	$installed_payment_methods = WC()->payment_gateways->payment_gateways();
	$data = $installed_payment_methods['skash']->settings;
	$amount = empty($woocommerce->cart->total) ? $order->get_total() : $woocommerce->cart->total;
	$amount = number_format((float)$amount, 2, '.', '');
	$currency = $order->get_currency();
	$created_at = $order->get_date_created();
	$timestamp = strtotime($created_at)*1000;
	$skash_sandbox = $data['live_mode'];
	if( $skash_sandbox == 'yes'){
	    $merchant_id = $data['live_merchant_id'];
	    $certificate_key = $data['certificate_key'];
	}else{
	    $merchant_id = $data['test_merchant_id'];
	    $certificate_key = $data['test_certificate_key'];
	}
	$mobile_secure = $order_id.$merchant_id.$amount.$currency.$timestamp.$certificate_key;
	$MobileSecureHash = base64_encode(hash('sha512', $mobile_secure, true));
	return $MobileSecureHash;
}


function spfw_get_browser_type(){
    $browser="";
    if(strrpos(strtolower($_SERVER["HTTP_USER_AGENT"]),strtolower("MSIE")))
    {
        $browser="IE";
    }
    else if(strrpos(strtolower($_SERVER["HTTP_USER_AGENT"]),strtolower("Presto")))
    {
        $browser="opera";
    }
    else if(strrpos(strtolower($_SERVER["HTTP_USER_AGENT"]),strtolower("CHROME")))
    {
        $browser="chrome";
    }
    else if(strrpos(strtolower($_SERVER["HTTP_USER_AGENT"]),strtolower("SAFARI")))
    {
        $browser="safari";
    }
    else if(strrpos(strtolower($_SERVER["HTTP_USER_AGENT"]),strtolower("FIREFOX")))
    {
        $browser="firefox";
    }
    else if(strrpos(strtolower($_SERVER["HTTP_USER_AGENT"]),strtolower("Netscape")))
    {
        $browser="netscape";
    }
    return $browser;
}
