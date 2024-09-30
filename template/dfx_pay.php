<?php
$order_id = isset($order_id) ? $order_id : null;
$amount = isset($amount) ? $amount : null;
$dfx_settings = get_option('woocommerce_dfx_settings', array());
$routeId = isset($dfx_settings['routeId']) ? $dfx_settings['routeId'] : '16760';

// Calculate expiry date (1 month from now)
$expiryDate = date('Y-m-d\TH:i:s\Z', strtotime('+1 month'));

if ($order_id && $amount) {
    ?>
    <iframe 
        src="https://services.dfx.swiss/pl?routeId=<?php echo esc_attr($routeId); ?>&message=<?php echo esc_attr($order_id); ?>&amount=<?php echo esc_attr($amount); ?>&expiryDate=<?php echo esc_attr($expiryDate); ?>" 
        width="100%" 
        height="1500"
        frameborder="0"
        allowfullscreen
    >
    </iframe>
    <?php
} else {
    ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 20px;">
        <strong>No payments for now</strong>
    </div>
    <?php
}
?>