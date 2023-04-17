<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://utanvet-ellenor.hu
 * @since             1.0.0
 * @package           UVBConnectorWooCommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Utánvét Ellenőr
 * Plugin URI:        https://utanvet-ellenor.hu
 * Description:       🚨 Kiszállításokat szűrünk és védünk.
 * Version:           1.9.0
 * Author:            Utánvét Ellenőr
 * Author URI:        https://utanvet-ellenor.hu
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       uvb-connector-woocommerce
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'UVB_CONNECTOR_WOOCOMMERCE_VERSION', '1.9.0' );

/**
 * Define vendor/autoload.php path used across the plugin
 *
 * @since 1.0.1
 */
define( 'UVB_CONNECTOR_VENDOR_AUTOLOAD_PATH', plugin_dir_path(__FILE__) . 'vendor/autoload.php');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-uvb-connector-woocommerce-activator.php
 */
function activate_uvb_connector_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-uvb-connector-woocommerce-activator.php';
	UVBConnectorWooCommerce_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-uvb-connector-woocommerce-deactivator.php
 */
function deactivate_uvb_connector_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-uvb-connector-woocommerce-deactivator.php';
	UVBConnectorWooCommerce_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_uvb_connector_woocommerce' );
register_deactivation_hook( __FILE__, 'deactivate_uvb_connector_woocommerce' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-uvb-connector-woocommerce.php';

/**
 * Add settings link to plugin on Plugins listing page.
 *
 * @param $links
 *
 * @return mixed
 */
function uvb_connector_woocommerce_add_plugin_page_settings_link( $links ) {
    $links[] = '<a href="' .
        admin_url( 'options-general.php?page=uvb-connector-woocommerce.php' ) .
        '">' . __('Settings') . '</a>';
    return $links;
}
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'uvb_connector_woocommerce_add_plugin_page_settings_link');

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_uvb_connector_woocommerce() {

	$plugin = new UVBConnectorWooCommerce();
	$plugin->run();

}
run_uvb_connector_woocommerce();
