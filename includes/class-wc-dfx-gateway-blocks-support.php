<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_DFX_Gateway_Blocks_Support extends AbstractPaymentMethodType
{
    protected $name = 'dfx';

    public function initialize()
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", array());
    }

    public function is_active()
    {
        return ! empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles()
    {

        wp_register_script(
            'wc-dfx-blocks-integration',
            plugin_dir_url(__DIR__) . 'build/index.js',
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
            ),
            null,
            true
        );

        return array('wc-dfx-blocks-integration');
    }

    public function get_payment_method_data()
    {
        return array(
            'title'        => $this->get_setting('title'),
            'description'  => $this->get_setting('description'),
        );
    }
}
