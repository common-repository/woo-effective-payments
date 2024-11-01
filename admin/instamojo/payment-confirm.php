<?php

defined( 'ABSPATH' ) or die();
$payment_id         = sanitize_text_field( $_GET['payment_id'] );
$payment_request_id = sanitize_text_field( $_GET['payment_request_id'] );

wl_wepsr_insta_log( __( "Callback Called with payment ID: $payment_id and payment req id: $payment_request_id", "effective-payment-solutions-by-rajthemes" ) );

if ( ! isset( $payment_id ) or ! isset( $payment_request_id ) ) {
	wl_wepsr_insta_log( __( "Callback Called without  payment ID or payment req id exittng..", "effective-payment-solutions-by-rajthemes" ) );
	wp_redirect( get_site_url() );
}

$stored_payment_req_id = WC()->session->get( 'payment_request_id' );
if ( $stored_payment_req_id != $payment_request_id ) {
	wl_wepsr_insta_log( __( "Given Payment request id not matched with stored payment request id: $stored_payment_req_id ", "effective-payment-solutions-by-rajthemes" ) );
	wp_redirect( get_site_url() );
}

try{
    $Instamojo_object = new WC_Gateway_Instamojo_WEPSR();
    $testmode         = 'yes' === $Instamojo_object->get_option('testmode', 'no');
    $testurl          = 'https://test.instamojo.com/api/1.1/';
    $liveurl          = 'https://www.instamojo.com/api/1.1/';
	$client_id        = $Instamojo_object->private_key;
	$client_secret    = $Instamojo_object->publishable_key;
	$xclient_id       = $Instamojo_object->truncate_secret($client_id);
	$xclient_secret   = $Instamojo_object->truncate_secret($client_secret);
    wl_wepsr_insta_log( __( "Client ID: $xclient_id | Client Secret: $xclient_secret | Testmode: $testmode ", "effective-payment-solutions-by-rajthemes" ) );

    if ( $testmode  ) {
        $requested_url = $testurl;
    } else {
        $requested_url = $liveurl;
    }

    $header = array(
        'X-Api-key'    => $client_id,
        'X-Auth-Token' => $client_secret,
        'Content-Type' => 'application/x-www-form-urlencoded',
    );

    $args = array(
        'method'      => 'GET',
        'body'        => array(),
        'timeout'     => '5',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => $header,
        'cookies'     => array(),
        'cainfo'      => WL_WEPSR_PLUGIN_DIR_PATH . 'admin/instamojo/lib/cacert.pem'
    );

    $response = wp_remote_request( $requested_url.'payment-requests/' . $payment_request_id, $args );
    $response = wp_remote_retrieve_body( $response );
    $response = json_decode( $response, true );

    wl_wepsr_insta_log( __( "Response from server:", "effective-payment-solutions-by-rajthemes" )." ".print_r( $response,true ) );

    if ( $response['success'] == true ) {
        $payment_status1 = $response['payment_request']['status'];
        $order_id        = $response['payment_request']['purpose'];
        wl_wepsr_insta_log( __( "Payment Request status for $payment_request_id is $payment_status1", "effective-payment-solutions-by-rajthemes" ) );

        $payment_status2 = wp_remote_request( $requested_url.'payments/' . $payment_id, $args );
        $payment_status2 = wp_remote_retrieve_body( $payment_status2 );
        $payment_status2 = json_decode( $payment_status2, true );
        $payment_status2 = $payment_status2['payment']['status'];

        wl_wepsr_insta_log( __( "Payment status for $payment_id is $payment_status2", "effective-payment-solutions-by-rajthemes" ) );

        if ( $payment_status1 == 'Completed' ) {
            $order_id = explode("-",$order_id);
            $order_id = $order_id[1];
            wl_wepsr_insta_log( __( "Extracted order id from trasaction_id:", "effective-payment-solutions-by-rajthemes" )." ".$order_id );
            $order = new WC_Order( $order_id );

            if ( $order ) {
                if ( $payment_status2 == "Credit" ) {
                    wl_wepsr_insta_log( __( "Payment for $payment_id was credited.", "effective-payment-solutions-by-rajthemes" ) );
                    $order->payment_complete( $payment_request_id );
                    update_post_meta( $order_id, '_insta_paymrnt_id', $payment_id );
                    wp_safe_redirect( $Instamojo_object->get_return_url( $order ) );
                } else {
                    wl_wepsr_insta_log( __( "Payment for $payment_id failed.", "effective-payment-solutions-by-rajthemes" ) );
                    $order->cancel_order( __( 'Unpaid order cancelled - Instamojo Returned Failed Status for payment Id '.$payment_id, 'woocommerce' ));
                    global $woocommerce;
                    wp_safe_redirect( $woocommerce->cart->get_cart_url() ); 
                }
                    
            } else
                wl_wepsr_insta_log( __( "Order not found with order id $order_id" , "effective-payment-solutions-by-rajthemes" ) );

        } elseif ( $payment_status1 == 'Pending' ) {
            wl_wepsr_insta_log( __( "Order payment is still pending $order_id" , "effective-payment-solutions-by-rajthemes" ) );
        }

    } else {
        $payment_status = $response['status'];
        $error_message  = $response['message'];
        wl_wepsr_insta_log( __( "Payment status for $payment_id is $payment_status and error message: $error_message", "effective-payment-solutions-by-rajthemes" ) );
    }
} catch( Exception $e ) {
	wl_wepsr_insta_log( $e->getMessage() );	
}