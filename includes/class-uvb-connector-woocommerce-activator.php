<?php

/**
 * Fired during plugin activation
 *
 * @link       https://utanvet-ellenor.hu
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
 * @author     Utánvét Ellenőr <hello@utanvet-ellenor.hu>
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
         * Set default values if they are missing from the database.
         */
        if (!is_array($options) || empty($options)) {
            $options['payment_methods_to_hide'] = ['cod'];
            $options['flag_orders'] = true;
            $options['reputation_threshold'] = 0.5;

            update_option('uvb_connector_woocommerce_options', $options);
        }
	}
}
