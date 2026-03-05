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
        if (!is_array($options)) {
            $options = [];
        }

        $this->publicKey = $options['public_api_key'] ?? '';
        $this->privateKey = $options['private_api_key'] ?? '';
        $this->production = isset($options['sandbox_mode']) ? false : true;
        $this->threshold = $options['reputation_threshold'] ?? 0.5;
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
        $email = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $countryCode = isset($_POST['country_code']) ? wp_unslash($_POST['country_code']) : '';
        $postalCode = isset($_POST['postal_code']) ? wp_unslash($_POST['postal_code']) : '';
        $phoneNumber = isset($_POST['phone_number']) ? wp_unslash($_POST['phone_number']) : '';
        $addressLine = isset($_POST['address_line']) ? wp_unslash($_POST['address_line']) : '';

        $this->check_and_store_blocked($email, $countryCode, $postalCode, $phoneNumber, $addressLine);

        wp_die();
    }

    /**
     * Function to handle Store API requests and set the blocked transient
     *
     * @param mixed $object WC_Customer or WC_Order.
     * @param mixed $request WP_REST_Request.
     * @return void
     */
    public function check_if_email_is_flagged_store_api($object, $request) {
        if (!$request || !is_object($request) || !method_exists($request, 'get_param')) {
            return;
        }

        $billing = $request->get_param('billing_address');
        $shipping = $request->get_param('shipping_address');

        $shipping = (is_array($shipping) && array_filter($shipping)) ? $shipping : [];
        $billing = (is_array($billing) && array_filter($billing)) ? $billing : [];

        $address = $shipping ?: $billing;
        if (!$address) {
            return;
        }

        $email = $billing['email'] ?? ($address['email'] ?? '');
        $countryCode = $address['country'] ?? '';
        $postalCode = $address['postcode'] ?? '';
        $phoneNumber = $billing['phone'] ?? ($address['phone'] ?? '');
        $addressLine = $address['address_1'] ?? '';

        $cartToken = '';
        if (method_exists($request, 'get_header')) {
            $cartToken = $request->get_header('cart-token');
        }
        if (!$cartToken && isset($_SERVER['HTTP_CART_TOKEN'])) {
            $cartToken = sanitize_text_field(wp_unslash($_SERVER['HTTP_CART_TOKEN']));
        }

        $this->check_and_store_blocked($email, $countryCode, $postalCode, $phoneNumber, $addressLine, $cartToken);
    }

    /**
     * Common logic to validate input, check UVB service, and store the blocked flag.
     *
     * @param string $email
     * @param string $countryCode
     * @param string $postalCode
     * @param string $phoneNumber
     * @param string $addressLine
     * @return void
     */
    private function check_and_store_blocked($email, $countryCode, $postalCode, $phoneNumber, $addressLine, $cartToken = '') {
        $email = sanitize_email($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $countryCode = sanitize_text_field($countryCode);
        $postalCode = sanitize_text_field($postalCode);
        $phoneNumber = sanitize_text_field($phoneNumber);
        $addressLine = sanitize_text_field($addressLine);

        $response = $this->checkInUVBService($email, $countryCode, $postalCode, $phoneNumber, $addressLine);
        if ($response === null) {
            return;
        }

        $key = $this->getSessionKey($cartToken);
        if (!$key) {
            return;
        }

        $blocked = $response->result->blocked ? true : false;
        set_transient($key, $blocked, 86400);
    }

    /**
     * Check if the user is flagged or not
     *
     * @param $email
     * @return mixed|null
     */
    public function checkInUVBService($email, $countryCode = '', $postalCode = '', $phoneNumber = '', $addressLine = '') {
        $client = new Client($this->publicKey, $this->privateKey);
        $client->email = $email;
        $client->threshold = $this->threshold;
        $client->sandbox = !$this->production;
        $client->countryCode = $countryCode;
        $client->postalCode = $postalCode;
        $client->phoneNumber = $phoneNumber;
        $client->addressLine = $addressLine;

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
        if (!is_array($options)) {
            $options = [];
        }
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
     * Hide fallback payment method from available gateways
     *
     * @param $available_gateways
     *
     * @return array
     */
    public function hide_fallback_payment_method($available_gateways) : array {
        $options = get_option('uvb_connector_woocommerce_options');
        if (!is_array($options)) {
            $options = [];
        }
        $fallback_payment_methods = $options['fallback_payment_methods'] ?? [];

        /**
         * If there are no fallback payment methods, return.
         */
        if (!$fallback_payment_methods || !count($fallback_payment_methods)) {
            return $available_gateways;
        }

        foreach ($available_gateways as $key => $value) {
            if (in_array($key, $fallback_payment_methods)) {
                unset($available_gateways[$key]);
            }
        }

        return $available_gateways;
    }

    public function getSessionKey($cartToken = null) {
        $cartToken = $cartToken ?: $this->getCartTokenFromRequest();
        if ($cartToken) {
            return implode('_', [self::TRANSIENT_PREFIX, hash('sha256', $cartToken)]);
        }

        $session = WC()->session ?? false;
        if (!$session) {
            return null;
        }

        $customerUniqueId = null;
        if (method_exists($session, 'get_customer_unique_id')) {
            $customerUniqueId = $session->get_customer_unique_id();
        } elseif (method_exists($session, 'get_customer_id')) {
            $customerUniqueId = $session->get_customer_id();
        } elseif (method_exists($session, 'get_session_id')) {
            $customerUniqueId = $session->get_session_id();
        }

        $customerUniqueId = $customerUniqueId ?? null;
        if (!$customerUniqueId) {
            return null;
        }

        return implode('_', [self::TRANSIENT_PREFIX, $customerUniqueId]);
    }

    /**
     * Try to extract Cart-Token from the current request.
     *
     * @return string|null
     */
    private function getCartTokenFromRequest() {
        if (empty($_SERVER['HTTP_CART_TOKEN'])) {
            return null;
        }

        return sanitize_text_field(wp_unslash($_SERVER['HTTP_CART_TOKEN']));
    }

    /**
     * Update available payment options
     *
     * @param $available_gateways
     * @return mixed
     */
    public function update_available_payment_options($available_gateways) {
        $available_gateways_without_fallback = $this->hide_fallback_payment_method($available_gateways);

        if ( is_admin() ) {
            return $available_gateways_without_fallback;
        }

        $key = $this->getSessionKey();
        if (!$key) {
            return $available_gateways_without_fallback;
        }

        $blocked = get_transient($key) ?? false;
        if (!$blocked) {
            return $available_gateways_without_fallback;
        }

        return $this->remove_payment_methods($available_gateways);
    }

}
