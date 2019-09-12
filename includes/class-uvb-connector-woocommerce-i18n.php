<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.webmenedzser.hu
 * @since      1.0.0
 *
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/includes
 * @author     Radics Ottó <otto@webmenedzser.hu>
 */
class UVBConnectorWooCommerce_i18n {
	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'uvb-connector-woocommerce',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}
}
