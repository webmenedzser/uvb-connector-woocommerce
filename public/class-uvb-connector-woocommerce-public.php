<?php

require UVB_CONNECTOR_VENDOR_AUTOLOAD_PATH;

use webmenedzser\UVBConnector\UVBConnector;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.webmenedzser.hu
 * @since      1.0.0
 *
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/public
 * @author     Radics Ottó <otto@webmenedzser.hu>
 */
class UVBConnectorWooCommerce_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/uvb-connector-woocommerce-public.js', array( 'jquery' ), $this->version, false );

        wp_localize_script( $this->plugin_name, 'ajax_object', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'user_email' => NULL
        ) );

	}

    /**
     * Function to answer AJAX whether the user is flagged or not
     *
     * @return void
     */
	public function check_if_email_is_flagged() {
        $options = get_option('uvb_connector_woocommerce_options');
        $threshold = $options['reputation_threshold'];

        if( WC()->session->get('email_is_flagged') ){
            WC()->session->__unset('email_is_flagged');
        }

        $email = sanitize_email($_POST['email']);
        $response = $this->checkInUVBService($email, $threshold);

        // If no response is given.
        if ($response === null) {
            wp_die();
        }

        // If the threshold is not met.
        if ($response->message->totalRate < $threshold) {
            WC()->session->set('email_is_flagged', 1);

            wp_die();
        }

        // Else.
        wp_die();
    }

    /**
     * Check if the user is flagged or not
     *
     * @param $email
     * @param $threshold
     * @return mixed|null
     */
    public function checkInUVBService($email, $threshold) {
        $options = get_option('uvb_connector_woocommerce_options');
        $publicApiKey = $options['public_api_key'];
        $privateApiKey = $options['private_api_key'];
        $production = isset($options['sandbox_mode']) ? false : true;

        $connector = new UVBConnector(
            $email,
            $publicApiKey,
            $privateApiKey,
            $production
        );

        $connector->threshold = $threshold;

        return json_decode($connector->get());
    }

    /**
     * Remove payment options from available gateways
     *
     * @param $available_gateways
     * @return array
     */
    public function remove_payment_methods($available_gateways) : array {
        $options = get_option('uvb_connector_woocommerce_options');
        $payment_methods_to_hide = $options['payment_methods_to_hide'] ?? [];

        /**
         * If there are no payment methods to hide, return.
         */
        if (!$payment_methods_to_hide || !count($payment_methods_to_hide)) {
            return $available_gateways;
        }

        foreach ($available_gateways as $key => $value) {
            if (in_array($key, $payment_methods_to_hide)) {
                unset($available_gateways[$key]);
            }
        }

        return $available_gateways;
    }

    /**
     * Update available payment options
     *
     * @param $available_gateways
     * @return mixed
     */
    public function update_available_payment_options($available_gateways) {
        if ( is_admin() ) {
            return $available_gateways;
        }

        if (WC()->session->get('email_is_flagged')) {
            $available_gateways = $this->remove_payment_methods($available_gateways);
        }

        return $available_gateways;
    }

}
