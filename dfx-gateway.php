<?php
/*
 * Plugin Name: WooCommerce DFX Payment Gateway
 * Description: Take cryptocurrency payments in your Woocommerce store.
 * Author: DFX
 * Version: 1.0.3
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

            if (!isset($data['payment']['status']) || !isset($data['externalId'])) {
                wp_die('Invalid webhook payload', 'Invalid Webhook', array('response' => 400));
            }

            // Extract order_id from externalId
            $external_id_parts = explode('/', $data['externalId']);
            $order_id = isset($external_id_parts[0]) ? intval($external_id_parts[0]) : null;

            if (!$order_id) {
                wp_die('Invalid order ID', 'Invalid Order', array('response' => 400));
            }

            $order = wc_get_order($order_id);

            if (!$order) {
                wp_die('Order not found', 'Invalid Order', array('response' => 404));
            }

            // Add a note to the order about the DFX webhook call
            $order->add_order_note(
                sprintf('DFX webhook received with status: %s', $data['payment']['status']),
                false, // This makes the note private (only visible to admin)
                true  // This adds the note as a separate line item
            );

            $status = $data['payment']['status'];

            switch ($status) {
                case 'Canceled':
                    $order->update_status('cancelled', __('Payment canceled by DFX.', 'woocommerce'));
                    break;
                case 'Expired':
                    $order->update_status('failed', __('Payment expired on DFX.', 'woocommerce'));
                    break;
                case 'Completed':
                    $order->update_status('on-hold', __('Payment completed on DFX. Awaiting manual confirmation.', 'woocommerce'));
                    break;
                default:
                    // Add a note for unknown status
                    $order->add_order_note(
                        sprintf('Unknown DFX payment status received: %s', $status),
                        false, // This makes the note private (only visible to admin)
                        true  // This adds the note as a separate line item
                    );
                    break;
            }

            wp_die('Webhook processed successfully', 'Success', array('response' => 200));
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
