<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.webmenedzser.hu
 * @since      1.0.0
 *
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/includes
 * @author     Radics Ottó <otto@webmenedzser.hu>
 */
class UVBConnectorWooCommerce_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
	    $options = get_option('uvb_connector_woocommerce_options');

        /**
         * Add Cash on Delivery as a default if the payment_methods_to_hide key is missing.
         */
	    if (!array_key_exists('payment_methods_to_hide', $options)) {
            $options['payment_methods_to_hide'] = ['cod'];

            update_option('uvb_connector_woocommerce_options', $options);
        }
	}
}
