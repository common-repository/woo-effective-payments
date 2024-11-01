<?php
defined('ABSPATH') or die();

class WC_Gateway_Instamojo_WEPSR extends WC_Payment_Gateway {

    /**
     * Class constructor
     */
    public function __construct() {

        global $woocommerce;
        $this->id                 = 'wl_wepsr_instamojo'; // payment gateway plugin ID
        $this->icon               = WL_WEPSR_PLUGIN_URL . 'assets/images/instamojo.png';
        $this->has_fields         = true; // in case you need a custom credit card form
        $this->method_title       = __( 'Instamojo Payment Gateway ( By RajThemes )', 'effective-payment-solutions-by-rajthemes' );
        $this->method_description = __( 'Direct payment via Instamojo. Instamojo accepts Credit / Debit Cards, Netbanking.', 'effective-payment-solutions-by-rajthemes' ); // will be displayed on the options page

        // gateways can support subscriptions, refunds, saved payment methods,
        // but in this tutorial we begin with simple payments
        $this->supports        = array(
            'products',
            'refunds'
        );
        $this->liveurl         = 'https://www.instamojo.com/api/1.1/';
        $this->testurl         = 'https://test.instamojo.com/api/1.1/';

        // Method with all the options fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        $this->title           = $this->get_option('title') ? $this->get_option('title') : 'Instamojo Credit/Debit Card Payment';
        $this->description     = $this->get_option('description');

        if (get_option('woocommerce_currency') == 'INR') {
            $wl_wepsr_insta_enabled = $this->get_option('enabled');
        } else {
            $wl_wepsr_insta_enabled = 'no';
        }
        $this->enabled         = $wl_wepsr_insta_enabled;
        $this->testmode        = 'yes' === $this->get_option('testmode');
        $this->private_key     = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('live_private_key');
        $this->publishable_key = $this->testmode ? $this->get_option('test_auth_token') : $this->get_option('live_auth_token');

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // We need custom JavaScript to obtain a token
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        add_action('woocommerce_receipt_' . $this->id, array($this, 'wl_wepsr_payment_receipt_page'));

        // You can also register a webhook here
        add_action('woocommerce_api_wl_instamojo', array($this, 'webhook'));
    }

    /**
     * Plugin options
     */
    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title'       => esc_html__( 'Enable/Disable', 'effective-payment-solutions-by-rajthemes' ),
                'label'       => esc_html__( 'Enable Instamojo Gateway', 'effective-payment-solutions-by-rajthemes' ),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => esc_html__( 'Title', 'effective-payment-solutions-by-rajthemes' ),
                'type'        => 'text',
                'description' => esc_html__( 'This controls the title which the user sees during checkout.', 'effective-payment-solutions-by-rajthemes' ),
                'default'     => esc_html__( 'Instamojo', 'effective-payment-solutions-by-rajthemes' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => esc_html__( 'Description', 'effective-payment-solutions-by-rajthemes' ),
                'type'        => 'textarea',
                'description' => esc_html__( 'This controls the description which the user sees during checkout.', 'effective-payment-solutions-by-rajthemes' ),
                'default'     => esc_html__( 'Pay with your credit card via our super-cool payment gateway.', 'effective-payment-solutions-by-rajthemes' ),
            ),
            'testmode' => array(
                'title'       => esc_html__( 'Test mode', 'effective-payment-solutions-by-rajthemes' ),
                'label'       => esc_html__( 'Enable Test Mode', 'effective-payment-solutions-by-rajthemes' ),
                'type'        => 'checkbox',
                'description' => esc_html__( 'Place the payment gateway in test mode using test API keys.', 'effective-payment-solutions-by-rajthemes' ),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'test_auth_token' => array(
                'title'       => esc_html__( 'Test Private Auth Token', 'effective-payment-solutions-by-rajthemes' ),
                'type'        => 'text'
            ),
            'test_private_key' => array(
                'title'       => esc_html__( 'Test Private API Key', 'effective-payment-solutions-by-rajthemes' ),
                'type'        => 'password',
            ),
            'live_auth_token' => array(
                'title'       => esc_html__( 'Live Private Auth Token', 'effective-payment-solutions-by-rajthemes' ),
                'type'        => 'text'
            ),
            'live_private_key' => array(
                'title'       => esc_html__( 'Live Private API Key', 'effective-payment-solutions-by-rajthemes' ),
                'type'        => 'password'
            )
        );
    }

    /**
     * WP Admin Options admin_options() 
     *
     */
    public function admin_options()
    {
        ?>
        <h3><?php _e('Instamojo', 'effective-payment-solutions-by-rajthemes'); ?></h3>
        <p><?php _e('Instamojo works by give you interface to Instamojo to enter their payment information to complete their payment process. Note that Instamojo will only take payments in Indian Rupee(INR).', 'effective-payment-solutions-by-rajthemes'); ?></p>
        <?php
                if (get_option('woocommerce_currency') == 'INR') {
                    ?>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
        <?php
                } else {
                    ?>
            <div class="inline error">
                <p><strong><?php _e('Instamojo Gateway Disabled', 'effective-payment-solutions-by-rajthemes'); ?></strong>
                    <?php echo sprintf(__('Choose Indian Rupee (Rs.) as your store currency in 
						<a href="%s">Pricing Options</a> to enable the Instamojo WooCommerce payment gateway.', 'effective-payment-solutions-by-rajthemes'), admin_url('admin.php?page=wc-settings')); ?>
                </p>
            </div>
        <?php
        } // End check currency
    }

    /**
     * Build the form after click on Instamojo Paynow button wl_wepsr_generate_instamojo_form()
     *
     */
    private function wl_wepsr_generate_instamojo_form($order_id)
    {
        $this->wl_wepsr_payment_clear_cache();
        global $wp;
        global $woocommerce;

        $this->log( __( "Creating Instamojo Order for order id: $order_id", "effective-payment-solutions-by-rajthemes" ) );
        $xclient_id     = $this->truncate_secret($this->private_key);
        $xclient_secret = $this->truncate_secret($this->publishable_key);
        $this->log( __( "Client ID: $xclient_id | Client Secret: $xclient_secret | Testmode: $this->testmode ", "effective-payment-solutions-by-rajthemes" ) );

        $order = new WC_Order($order_id);
        $txnid = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
        update_post_meta($order_id, '_transaction_id', $txnid);
        try {

            if ($this->testmode) {
                $request_url = $this->testurl;
            } else {
                $request_url = $this->liveurl;
            }

            $order_data = array(
                "purpose"      => time() . "-" . $order_id,
                "amount"       => $order->get_total(),
                'phone'        => $order->get_billing_phone(),
                'buyer_name'   => substr(trim((html_entity_decode($order->get_billing_first_name() . " " . $order->get_billing_last_name(), ENT_QUOTES, 'UTF-8'))), 0, 20),
                'redirect_url' => get_site_url(),
                "send_email"   => true,
                "email"        => $order->get_billing_email(),
            );
            $this->log( __( "Data sent for creating order ", "effective-payment-solutions-by-rajthemes" ).' '. print_r($order_data, true));
             
            $headers = array(
                "X-Api-key"    => $this->private_key,
                "X-Auth-Token" => $this->publishable_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            );

            $args    = array(
                'method'      => 'POST',
                'body'        => $order_data,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => $headers,
                'cookies'     => array(),
                'cainfo'      => WL_WEPSR_PLUGIN_DIR_PATH. 'admin/instamojo/lib/cacert.pem',
            );
             
            $response = wp_remote_request( $request_url.'/payment-requests', $args );
            $response = wp_remote_retrieve_body($response);
            $response = json_decode($response, true );

            $this->log( __( "Response from server on creating order", "effective-payment-solutions-by-rajthemes" ).' '. print_r($response, true));

            if ($response['success'] == true) {
                WC()->session->set('payment_request_id',  $response['payment_request']['id']);
                return __('<button type="button" id="wl_wepsr_intamojo_pay_btn">Pay Now</button>
                <script>
                jQuery(document).ready(function () {
                    "use strict";
                    jQuery("#wl_wepsr_intamojo_pay_btn").click();
                    jQuery(document).on("click", "#wl_wepsr_intamojo_pay_btn", function (e) {
                        e.preventDefault();
                        Instamojo.open("' . $response['payment_request']['longurl'] . '"); 
                    });
                });
                </script>');
            } else {
                return __('Response from server:' . print_r( $response['message'], true ) . '', 'effective-payment-solutions-by-rajthemes');
                $this->log("An error occurred, Response from server:  " . $response['message'] . "");
            }
        } catch (Exception $e) {
            print('Error: ' . $e->getMessage());
        }
    }

    /**
     * You will need it if you want your custom credit card form, Step 4 is about it
     */
    public function payment_fields()
    {
        // ok, let's display some description before the payment form
        if ($this->description) {
            // you can instructions for test mode, I mean test card numbers etc.
            if ($this->testmode) {
                $this->description .= esc_html__( 'TEST MODE ENABLED. In test mode, you can use the card numbers listed in ', 'multiple-payment-solutions-for-woocommerce' ).''.wp_kses_post( '<a href="https://support.instamojo.com/hc/en-us/articles/208485675-Test-or-Sandbox-Account" target="_blank" rel="noopener noreferrer">documentation</a>.' );
                $this->description  = trim($this->description);
            }
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
        }
    }

    /*
        * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
        */
    public function payment_scripts()
    {
        // we need JavaScript to process a token only on cart/checkout pages, right?
        if (!is_cart() && !is_checkout() && isset( $_GET['pay_for_order'] ) ) {
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ('no' === $this->enabled) {
            return;
        }

        // no reason to enqueue JavaScript if API keys are not set
        if (empty($this->private_key) || empty($this->publishable_key)) {
            return;
        }

        // do not work with card detailes without SSL unless your website is in a test mode
        if (!$this->testmode && !is_ssl()) {
            return;
        }

        // let's suppose it is our payment processor JavaScript that allows to obtain a token
        wp_enqueue_script('instamojo-checkout', 'https://js.instamojo.com/v1/checkout.js', array('jquery'));
    }

    /*
        * Fields validation, more in Step 5
        */
    public function validate_fields()
    {
        if (empty($_POST['billing_first_name'])) {
            wc_add_notice( __( 'First name is required!', "effective-payment-solutions-by-rajthemes" ), 'error');
            return false;
        }
        if (empty($_POST['billing_last_name'])) {
            wc_add_notice( __( 'Last name is required!', "effective-payment-solutions-by-rajthemes" ), 'error');
            return false;
        }
        if (empty($_POST['billing_email'])) {
            wc_add_notice( __( 'Email is required!', "effective-payment-solutions-by-rajthemes" ), 'error');
            return false;
        }
        return true;
    }

    /*
        * We're processing the payments here, everything about it is in Step 5
        */
    public function process_payment($order_id)
    {
        $this->wl_wepsr_payment_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Page after cheout button and redirect to Instamojo payment page wl_wepsr_payment_receipt_page()
     * 
     */
    public function wl_wepsr_payment_receipt_page($order_id)
    {
        $this->wl_wepsr_payment_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);
        printf('<h3>%1$s</h3>', __('Thank you for your order, please click the button below to Pay with Instamojo.', 'effective-payment-solutions-by-rajthemes'));
        _e($this->wl_wepsr_generate_instamojo_form($order_id));
    } // Cheout button and redirect wl_wepsr_payment_receipt_page() end

    /**
     * Clear cache for the previous value wl_wepsr_payment_clear_cache()
     *
     */
    public function wl_wepsr_payment_clear_cache()
    {
        header("Pragma: no-cache");
        header("Cache-Control: no-cache");
        header("Expires: 0");
    } // Clear cache for the previous value wl_wepsr_payment_clear_cache() end

    /**
     * Process refund call process_refund()
     *
     */
    function process_refund($order_id, $amount = null, $reason = '')
    {
        $this->wl_wepsr_payment_clear_cache();
        global $woocommerce;
        $order = new WC_Order($order_id);

        if (empty($reason)) {
            $reason = __('Customer isn\'t satisfied with the quality', 'effective-payment-solutions-by-rajthemes');
        }

        try {

            if ($this->testmode) {
                $request_url = $this->testurl;
            } else {
                $request_url = $this->liveurl;
            }

            $payment_id     = get_post_meta($order_id, '_insta_paymrnt_id', true);
            $transaction_id = get_post_meta($order_id, '_transaction_id', true);
            $payload        = array(
                'transaction_id' => $order->get_transaction_id(),
                'payment_id'     => $payment_id,
                'type'           => 'QFL',
                'refund_amount'  => $amount,
                'body'           => $reason
            );

            $headers = array(
                "X-Api-key"    => $client_id,
                "X-Auth-Token" => $client_secret,
            );

            $args    = array(
                'method'      => 'GET',
                'body'        => $payload,
                'timeout'     => '5',
                'redirection' => '5',
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => $headers,
                'cookies'     => array(),
                'cainfo'      => WL_WEPSR_PLUGIN_DIR_PATH. 'admin/instamojo/lib/cacert.pem',
            );
             
            $response = wp_remote_request( $request_url.'refunds/' . $args );
            $response = wp_remote_retrieve_body($response);
            $response = json_decode($response, true);

            if (!empty($response) && $response['status'] == 'success') {
                if ($response['refunds']['status'] == 'Refunded' || $response['refunds']['status'] == 'Closed') {
                    $refund_note =  sprintf(__('Refund: %1$s %2$s<br>Paytm Refund ID: %3$s<br>Reference ID: %4$s', 'effective-payment-solutions-by-rajthemes'), $amount, get_option('woocommerce_currency'), $response['refunds']['id']);
                    $order->add_order_note($refund_note);
                    return true;
                } else {
                    return new WP_Error('error', __('Not Refunded', 'effective-payment-solutions-by-rajthemes'));
                }
            } else {
                return new WP_Error('error', __(isset($response['message']), 'effective-payment-solutions-by-rajthemes'));
            }
        } catch (Exception $e) {
            return new WP_Error('error', $e->getMessage());
        }
    } // Process refund call process_refund() end

    /*
        * In case you need a webhook, like PayPal IPN etc
        */
    public function webhook() {
        $order = wc_get_order( sanitize_text_field( $_GET['id'] ) );
        $order->payment_complete();
        $order->reduce_order_stock();
        update_option('webhook_debug', sanitize_text_field( $_GET ) );
    }

    public function log($message) {
        wl_wepsr_insta_log($message);
    }

    public function truncate_secret($secret) {
        return wl_wepsr_truncate_secret($secret);
    }
} // end \WC_Gateway_Instamojo_WEPSR class