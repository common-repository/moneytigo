<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;


/**
 * https://sevengits.com/payments-with-woocommerce-checkout-blocks/
 */
final class WC_Ovri_Blocks extends AbstractPaymentMethodType
{

    private $gateway;
    protected $name = 'ovri'; // your payment gateway name

    public function initialize()
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", array());
        $this->gateway = new WC_Ovri();
    }

    public function is_active()
    {
        return ! empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles()
    {

        $this->gateway->log("Method script handles");
        wp_register_script(
            'wc-ovri-blocks-integration',
            plugin_dir_url(__FILE__) . 'block/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        return ['wc-ovri-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'supports' => $this->gateway->supports
        ];
    }
}
