<?php
defined( 'ABSPATH' ) or die();

class WC_Gateway_Cashfree_WEPSR extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
    function __construct() {
      	global $woocommerce;
      	global $wpdb;
      	$this->id                 = "wl_wepsr_cashfree";
      	$this->icon               = WL_WEPSR_PLUGIN_URL.'assets/images/cf-mailer-logo.png';
      	$this->method_title       = __( "Cashfree Payment Gateway ( By RajThemes )", 'effective-payment-solutions-by-rajthemes' );
      	$this->method_description = __( "Cashfree Payment Gateway ( By RajThemes ) redirects customers to checkout page to fill in their payment details and complete the payment", 'effective-payment-solutions-by-rajthemes' ); // will be displayed on the options page
      	$this->title              = __( "Cashfree Credit/Debit Card Payment", 'effective-payment-solutions-by-rajthemes' ); 
      	$this->has_fields         = false;
      	$this->init_form_fields();
      	$this->init_settings();     
      	$this->environment        = $this->settings['environment'];
     	  $this->app_id             = $this->settings['app_id'];
      	$this->secret_key         = $this->settings['secret_key'];
      	$this->description        = $this->settings['description'];
      	$this->title              = $this->settings['title'];

       	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      	if ( isset( $_GET['cashfree_callback'] ) ) {
            $this->wl_wepsr_check_cashfree_response();
      	}
    }

    public function init_form_fields() {
      	$this->form_fields = array(
            'enabled' => array(
                'title'        => esc_html__( 'Enable/Disable', 'effective-payment-solutions-by-rajthemes' ),
                'type'         => 'checkbox',
                'label'        => esc_html__( 'Enable Cashfree payment gateway.', 'effective-payment-solutions-by-rajthemes' ),
                'default'      => 'no',
                'description'  => esc_html__( 'Show in the Payment List as a payment option', 'effective-payment-solutions-by-rajthemes' )
            ),
              'title' => array(
                'title'        => esc_html__( 'Title:', 'effective-payment-solutions-by-rajthemes' ),
                'type'         => 'text',
                'default'      => esc_html__( 'Cashfree', 'effective-payment-solutions-by-rajthemes' ),
                'description'  => esc_html__( 'This controls the title which the user sees during checkout.', 'effective-payment-solutions-by-rajthemes' ),
                'desc_tip'     => true
            ),
            'description' => array(
                'title'        => esc_html__( 'Description:', 'effective-payment-solutions-by-rajthemes' ),
                'type'         => 'textarea',
                'default'      => esc_html__( "Pay securely via Card/Net Banking/Wallet via Cashfree."),
                'description'  => esc_html__( 'This controls the description which the user sees during checkout.', 'effective-payment-solutions-by-rajthemes' ),
                'desc_tip'         => true
            ),
            'environment'  => array(
                'type'        => 'select',
                'options'     => array(
                    'sandbox'    => esc_html__( 'Test Mode', 'effective-payment-solutions-by-rajthemes' ),
                    'production' => esc_html__( 'Live Mode', 'effective-payment-solutions-by-rajthemes' ) 
                ),
                'default'     => 'sandbox',
                'title'       => esc_html__( 'Active Environment', 'effective-payment-solutions-by-rajthemes' ),
                'class'       => array(
                    'effective-payment-solutions-by-rajthemes-active-environment' 
                ),
                'tool_tip'    => true,
        				'description' => esc_html__( 'You can enable Test mode or Live mode with this setting. When testing the plugin, enable Test mode and you can run test transactions using your Cashfree account.
        					When you are ready to go live, enable Live mode.', 'effective-payment-solutions-by-rajthemes' ) 
            ),
      			'app_id' => array(
      				'title'         => esc_html__( 'App Id', 'effective-payment-solutions-by-rajthemes' ),
      				'type'          => 'text',
      				'description'   => esc_html__( 'Copy from your dashboard or contact Cashfree Team', 'effective-payment-solutions-by-rajthemes' ),
      				'desc_tip'      => true
      			),
            'secret_key' => array(
                'title'       => esc_html__( 'Secret Key', 'effective-payment-solutions-by-rajthemes' ),
                'type'        => 'password',
                'description' => esc_html__( 'Copy from your dashboard or contact Cashfree Team', 'effective-payment-solutions-by-rajthemes' ),
                'desc_tip'    => true
            ),                
        );
    }

    function wl_wepsr_check_cashfree_response(){
	    global $woocommerce;
	    global $wpdb;

      if ( isset( $_POST["orderId"] ) ) {
        if ( isset( $_GET["ipn"] ) ) {
            $showContent = false;
        } else {
            $showContent = true;
        }

        $orderId = sanitize_text_field( $_POST["orderId"] );
        $order   = new WC_Order( $orderId );
        if ( $order && $order->get_status() == "pending" ) {

          $cashfree_response                = array();
          $cashfree_response["orderId"]     = $orderId;
          $cashfree_response["orderAmount"] = sanitize_text_field( $_POST["orderAmount"] );
          $cashfree_response["txStatus"]    = sanitize_text_field( $_POST["txStatus"] );
          $cashfree_response["referenceId"] = sanitize_text_field( $_POST["referenceId"] );
          $cashfree_response["txTime"]      = sanitize_text_field( $_POST["txTime"] );
          $cashfree_response["txMsg"]       = sanitize_text_field( $_POST["txMsg"] );
          $cashfree_response["paymentMode"] = sanitize_text_field( $_POST["paymentMode"] );
          $cashfree_response["signature"]   = sanitize_text_field( $_POST["signature"] );
  
          $secret_key        = $this->secret_key;
          $data              = "{$cashfree_response['orderId']}{$cashfree_response['orderAmount']}{$cashfree_response['referenceId']}{$cashfree_response['txStatus']}{$cashfree_response['paymentMode']}{$cashfree_response['txMsg']}{$cashfree_response['txTime']}";
          $hash_hmac         = hash_hmac('sha256', $data, $secret_key, true) ;
          $computedSignature = base64_encode( $hash_hmac );
          if ( $cashfree_response["signature"] != $computedSignature ) {
             //error
             die();
          } 

          if ( $cashfree_response["txStatus"] == 'SUCCESS' ) {

            $order -> payment_complete();
            $order -> set_transaction_id( $cashfree_response["referenceId"] );
            $order -> add_order_note( 'Cashfree payment successful. Reference id ' . $cashfree_response["referenceId"] );
            $order -> add_order_note( $cashfree_response["txMsg"] );
            $woocommerce->cart->empty_cart();

            $this->msg['message'] = __( "Thank you for shopping with us. Your payment has been confirmed. Cashfree reference id is:", 'effective-payment-solutions-by-rajthemes')." <b>".$cashfree_response["referenceId"]."</b>.";
            $this->msg['class']   = 'woocommerce-message';

          } elseif ( $cashfree_response["txStatus"] == "CANCELLED" ) {

            $order->update_status( 'failed', __( 'Payment has been cancelled.', 'effective-payment-solutions-by-rajthemes' ) );
            $this->msg['class']   = 'woocommerce-error';
            $this->msg['message'] = __( "Your transaction has been cancelled. Please try again.", 'effective-payment-solutions-by-rajthemes' );
            wp_safe_redirect( wc_get_cart_url() ); 

          } elseif ( $cashfree_response["txStatus"] == "PENDING" ) {

            $order->update_status( 'failed', __( 'Payment is under review.', 'effective-payment-solutions-by-rajthemes' ) );
            $this->msg['class']   = 'woocommerce-error';
            $this->msg['message'] = __( "Your transaction is under review. Please wait for an status update.", 'effective-payment-solutions-by-rajthemes' );
            wp_safe_redirect( wc_get_cart_url() ); 

          } else {

            $order->update_status( 'failed', __( 'Payment Failed', 'effective-payment-solutions-by-rajthemes' ) );
            $this->msg['class']   = 'woocommerce-error';
            $this->msg['message'] = __( "Your transaction has failed.", 'effective-payment-solutions-by-rajthemes' );
            wp_safe_redirect( wc_get_cart_url() ); 

          }
          if ( $showContent ) {
            add_action( 'the_content', array( &$this, 'wl_wepsr_showMessage' ) );
          } 
        }       
      }
    }

    public function wl_wepsr_getEnvironment() {
      $environment = $this->get_option( 'environment' ) === 'sandbox' ? 'sandbox' : 'production';
      return $environment;
    }
  
    function wl_wepsr_showMessage ( $content ) {
       return '<div class="woocommerce"><div class="'.$this->msg['class'].'">'.$this->msg['message'].'</div></div>'.$content;
    }
  
    // Submit payment and handle response
    public function process_payment( $order_id ) {

        global $woocommerce;
        global $wpdb;
        global $current_user;

        //get user details   
        $current_user  = wp_get_current_user();
        $user_email    = $current_user->user_email;
        $first_name    = $current_user->shipping_first_name;
        $last_name     = $current_user->shipping_last_name;
        $phone_number  = $current_user->billing_phone;
        $customerName  = $first_name." ".$last_name;
        $customerEmail = $user_email;
        $customerPhone = $phone_number;

        if ( $user_email == '' ) {
          $user_email    = sanitize_text_field( $_POST['billing_email'] );
          $first_name    = sanitize_text_field( $_POST['billing_first_name'] );
          $last_name     = sanitize_text_field( $_POST['billing_last_name'] );
          $phone_number  = sanitize_text_field( $_POST['billing_phone'] );     
          $customerName  = $first_name." ".$last_name;
          $customerEmail = $user_email;
          $customerPhone = $phone_number;
        }

        $order            = new WC_Order( $order_id );
        $this->return_url = add_query_arg( array( 'cashfree_callback' => 1 ), $this->get_return_url( $order ) );
        $this->notify_url = add_query_arg( array( 'cashfree_callback' => 1, 'ipn' => '1' ), $this->get_return_url( $order ) );
    
        $cf_request                  = array();
        $cf_request["appId"]         =  $this->app_id;
        $cf_request["secretKey"]     = $this->secret_key;
        $cf_request["orderId"]       = $order_id;  
        $cf_request["orderAmount"]   = $order->get_total();
        $cf_request["orderCurrency"] = $order->get_currency();
        $cf_request["customerPhone"] = $customerPhone;
        $cf_request["customerName"]  = $customerName;
        $cf_request["customerEmail"] =  $customerEmail;
        $cf_request["source"]        =  "woocommerce";
        $cf_request["returnUrl"]     = $this->return_url;
        $cf_request["notifyUrl"]     = $this->notify_url;
        $timeout = 30;

        if ( $this->wl_wepsr_getEnvironment() === 'sandbox' ) {
          $apiEndpoint = "https://test.cashfree.com";
        } elseif ( $this->wl_wepsr_getEnvironment() === 'production' ) {
          $apiEndpoint = "https://api.cashfree.com";
        }

        $apiEndpoint  = $apiEndpoint."/api/v1/order/create";
        $postBody     = array( "body" => $cf_request, "timeout" => $timeout );
        $cf_result    = wp_remote_retrieve_body( wp_remote_post( esc_url( $apiEndpoint ), $postBody ) );
        $jsonResponse = json_decode( $cf_result );

        if ( $jsonResponse->{'status'} == "OK" ) {
          $paymentLink = $jsonResponse->{"paymentLink"};
          return array( 'result' => 'success', 'redirect' => $paymentLink );
        } else {
          return array( 'result' => 'failed', 'messages' => __( 'Gateway request failed. Please try again', 'effective-payment-solutions-by-rajthemes' ) );
        }

      exit;
    }
}