<?php
/*
 * Plugin Name: WooCommerce DFX Payment Gateway
 * Description: Take cryptocurrency payments in your Woocommerce store.
 * Author: DFX
 * Version: 1.0.4
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_payment_gateways', 'dfx_add_gateway_class');
function dfx_add_gateway_class($gateways)
{
    $gateways[] = 'WC_DFX_Gateway';
    return $gateways;
}

add_action('plugins_loaded', 'dfx_init_gateway_class');
function dfx_init_gateway_class()
{
    add_shortcode('dfx_pay_shortcode', 'dfx_pay_shortcode');
    function dfx_pay_shortcode()
    {
        $order_id = isset($_GET['orderId']) ? intval($_GET['orderId']) : null;
        $amount = isset($_GET['amount']) ? floatval($_GET['amount']) : null;
        $currency = isset($_GET['currency']) ? sanitize_text_field($_GET['currency']) : null;

        // Verify the order exists and belongs to the current user
        if ($order_id) {
            $order = wc_get_order($order_id);
            if (!$order || $order->get_customer_id() != get_current_user_id()) {
                $order_id = null;
            }
        }

        // Pass these variables to your template
        return wc_get_template_html('dfx_pay.php', array(
            'order_id' => $order_id,
            'amount' => $amount,
            'currency' => $currency,
        ), '', plugin_dir_path(__FILE__) . 'template/');
    }

    class WC_DFX_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {

            $this->id = 'dfx';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = 'DFX Gateway';
            $this->method_description = 'Description of DFX payment gateway';
            $this->supports = array(
                'products'
            );

            $this->init_form_fields();

            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            
            // uncomment for adding a webhook
            add_action('woocommerce_api_dfx_gateway', array($this, 'handle_webhook'));
            
            // uncomment this to load the payment scripts
            //add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable DFX Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'DFX Gateway',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with Bitcoins, Lightning or your favorite cryptocurrency.',
                ),
                'routeId' => array(
                    'title'       => 'Route ID',
                    'type'        => 'text',
                    'description' => 'The route ID for the DFX payment service.',
                    'default'     => '16760',
                    'desc_tip'    => true,
                ),
                'public_key' => array(
                    'title'       => 'Public Key',
                    'type'        => 'textarea',
                    'description' => 'Enter the public key provided by DFX for signature verification.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        public function payment_fields() {}

        public function payment_scripts() {}

        public function validate_fields() {}

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            // Mark as pending (we're awaiting the payment)
            $order->update_status('pending', __('Awaiting DFX payment', 'woocommerce'));

            // Reduce stock levels
            wc_reduce_stock_levels($order_id);

            // Clear the cart
            WC()->cart->empty_cart();

            // Get the amount
            $amount = $order->get_total();

            // Build the URL for the dfx-pay page with parameters
            $dfx_pay_url = add_query_arg(
                array(
                    'orderId' => $order_id,
                    'amount' => $amount,
                ),
                home_url('/dfx-pay/')
            );

            // Return redirect to dfx-pay page
            return array(
                'result'   => 'success',
                'redirect' => $dfx_pay_url,
            );
        }

        public function handle_webhook()
        {
            $payload = file_get_contents('php://input');
            $data = json_decode($payload, true);

            // Get the x-payload-signature header value
            $dfx_signature = isset($_SERVER['HTTP_X_PAYLOAD_SIGNATURE']) ? sanitize_text_field($_SERVER['HTTP_X_PAYLOAD_SIGNATURE']) : '';

            // Get the public key from settings
            $public_key = $this->get_option('public_key');
            
            // Verify the signature against the public key and the payload
            $verification_result = $this->verify_signature($payload, $dfx_signature, $public_key);

            // If verification fails, log it and return a generic error
            if (!$verification_result) {
                error_log('DFX webhook: Signature verification failed');
                wp_die('Webhook processing failed', 'Error', array('response' => 400));
            }

            // Continue processing only if signature is verified
            if (!isset($data['payment']['status']) || !isset($data['externalId']) || !isset($data['routeId'])) {
                wp_die('Webhook processing failed', 'Error', array('response' => 400));
            }

            // Validate routeId
            $stored_route_id = $this->get_option('routeId');
            $incoming_route_id = $data['routeId'];

            // Ensure both values are strings for comparison
            if (strval($stored_route_id) !== strval($incoming_route_id)) {
                wp_die('Webhook processing failed', 'Error', array('response' => 400));
            }

            // Extract order_id from externalId
            $external_id_parts = explode('/', $data['externalId']);
            $order_id = isset($external_id_parts[0]) ? intval($external_id_parts[0]) : null;

            if (!$order_id) {
                wp_die('Webhook processing failed', 'Error', array('response' => 400));
            }

            $order = wc_get_order($order_id);

            if (!$order) {
                wp_die('Webhook processing failed', 'Error', array('response' => 400));
            }

            // Check if the order is in a 'pending' state
            if ($order->get_status() !== 'pending') {
                wp_die('Webhook processed', 'Success', array('response' => 200));
            }

            // Check if the amount and currency are present in the payload
            if (!isset($data['payment']['amount']) || !isset($data['payment']['currency']['name'])) {
                error_log('DFX webhook: Payment amount or currency is missing');
                wp_die('Webhook processing failed', 'Error', array('response' => 400));
            }

            // Check if the amount in the payload matches the order amount
            $payload_amount = floatval($data['payment']['amount']);
            $order_amount = floatval($order->get_total());

            if ($payload_amount !== $order_amount) {
                $order->add_order_note(
                    sprintf('DFX payment amount mismatch. Expected: %f, Received: %f', $order_amount, $payload_amount),
                    false,
                    true
                );
                wp_die('Webhook processing failed', 'Error', array('response' => 400));
            }

            // Check if the currency in the payload matches the order currency
            $payload_currency = strtoupper($data['payment']['currency']['name']);
            $order_currency = strtoupper($order->get_currency());

            if ($payload_currency !== $order_currency) {
                $order->add_order_note(
                    sprintf('DFX payment currency mismatch. Expected: %s, Received: %s', $order_currency, $payload_currency),
                    false,
                    true
                );
                wp_die('Webhook processing failed', 'Error', array('response' => 400));
            }

            // Process the webhook data
            $status = $data['payment']['status'];

            switch ($status) {
                case 'Canceled':
                    $order->update_status('cancelled', __('Payment canceled by DFX.', 'woocommerce'));
                    break;
                case 'Expired':
                    $order->update_status('failed', __('Payment expired on DFX.', 'woocommerce'));
                    break;
                case 'Completed':
                    $order->update_status('processing', __('Payment completed on DFX. Order is being prepared for shipping.', 'woocommerce'));
                    break;
                case 'Pending':
                    // Order is already in pending state, no need to update
                    break;
                default:
                    $order->add_order_note(
                        sprintf('Unknown DFX payment status received: %s', $status),
                        false,
                        true
                    );
                    break;
            }

            wp_die('Webhook processed', 'Success', array('response' => 200));
        }

        private function verify_signature($payload, $signature, $public_key)
        {
            // Check if any of the required parameters are empty
            if (empty($payload) || empty($signature) || empty($public_key)) {
                return false;
            }

            // Hash the payload using SHA-256
            $hashed_payload = hash('sha256', $payload);

            // Decode the base64 signature
            $decoded_signature = base64_decode($signature);
            if ($decoded_signature === false) {
                return false;
            }

            // Get the public key resource
            $public_key_resource = openssl_pkey_get_public($public_key);
            if ($public_key_resource === false) {
                return false;
            }

            // Verify the signature against the hashed payload
            $verify = openssl_verify($hashed_payload, $decoded_signature, $public_key_resource, OPENSSL_ALGO_SHA256);
            
            // Free the key resource
            openssl_free_key($public_key_resource);

            return $verify === 1;
        }
    }
}

add_action('woocommerce_blocks_loaded', 'dfx_gateway_block_support');
function dfx_gateway_block_support()
{
    require_once __DIR__ . '/includes/class-wc-dfx-gateway-blocks-support.php';
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new WC_DFX_Gateway_Blocks_Support);
        }
    );
}
