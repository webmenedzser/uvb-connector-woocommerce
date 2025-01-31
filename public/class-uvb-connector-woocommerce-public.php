<?php

require UVB_CONNECTOR_VENDOR_AUTOLOAD_PATH;

use UtanvetEllenor\Client;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://utanvet-ellenor.hu
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
 * @author     Utánvét Ellenőr <hello@utanvet-ellenor.hu>
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
    public const TRANSIENT_PREFIX = 'utanvet_ellenor_request_is_blocked';

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

    private $publicKey;
    private $privateKey;
    private $production;
    private $threshold;

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

        $options = get_option('uvb_connector_woocommerce_options');

        $this->publicKey = $options['public_api_key'] ?? '';
        $this->privateKey = $options['private_api_key'] ?? '';
        $this->production = isset($options['sandbox_mode']) ? false : true;
        $this->threshold = $options['reputation_threshold'] ?: 0.5;
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
        if (!function_exists( 'is_woocommerce')) {
            return;
        }

        if (!is_cart() && !is_checkout()) {
            return;
        }

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/uvb-connector-woocommerce-public.js', array( 'jquery' ), $this->version, false );
        wp_localize_script( $this->plugin_name, 'ajax_object', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'user_email' => NULL
        ) );
	}

    /**
     * Function to answer AJAX whether the user is flagged or not
     *
     * @return void
     */
	public function check_if_email_is_flagged() {
        $email = sanitize_email($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            wp_die();
        }

        $response = $this->checkInUVBService($email);
        if ($response === null) {
            wp_die();
        }

        $key = $this->getSessionKey();
        if (!$key) {
            wp_die();
        }

        $blocked = $response->result->blocked ? true : false;
        set_transient($key, $blocked, 86400);

        wp_die();
    }

    /**
     * Check if the user is flagged or not
     *
     * @param $email
     * @return mixed|null
     */
    public function checkInUVBService($email) {
        $client = new Client($this->publicKey, $this->privateKey);
        $client->email = $email;
        $client->threshold = $this->threshold;
        $client->sandbox = !$this->production;

        return $client->sendRequest();
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

    public function getSessionKey() {
        $session = WC()->session ?? false;
        if (!$session) {
            return null;
        }

        $customerUniqueId = $session->get_customer_unique_id() ?? null;
        if (!$customerUniqueId) {
            return null;
        }

        return implode('_', [self::TRANSIENT_PREFIX, $customerUniqueId]);
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

        $key = $this->getSessionKey();
        if (!$key) {
            return $available_gateways;
        }

        $blocked = get_transient($key) ?? false;
        if (!$blocked) {
            return $available_gateways;
        }

        return $this->remove_payment_methods($available_gateways);
    }

}
