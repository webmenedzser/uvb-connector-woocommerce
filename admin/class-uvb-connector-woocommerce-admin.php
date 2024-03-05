<?php

require UVB_CONNECTOR_VENDOR_AUTOLOAD_PATH;

use webmenedzser\UVBConnector\UVBConnector;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.webmenedzser.hu
 * @since      1.0.0
 *
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/admin
 * @author     Radics Ottó <otto@webmenedzser.hu>
 */
class UVBConnectorWooCommerce_Admin {

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

    private $publicKey;
    private $privateKey;
    private $production;
    private $threshold;
    private $flagOrders;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
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
        $this->flagOrders = $options['flag_orders'] ?? false;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/uvb-connector-woocommerce-admin.css', array(), $this->version, 'all' );
    }

    /*
     * Register WooCommerce order statuses
     *
     * @since   1.0.0
     */
    public function registerOrderStatusFlagged() {
        register_post_status( 'wc-uvb_flagged', array(
            'label'                     => 'Rendelést nem vette át',
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Rendelést nem vette át <span class="count">(%s)</span>', 'Rendelést nem vette át <span class="count">(%s)</span>' )
        ) );
    }

    /**
     * Add WooCommerce order statuses
     *
     * @since   1.0.0
     * @return  array
     */
    public function addOrderStatusFlagged($order_statuses)
    {
        $order_statuses['wc-uvb_flagged'] = 'Rendelést nem vette át';

        return $order_statuses;
    }

    public function addColumnFlagged($columns)
    {
        $columns['uvb_status'] = 'Utánvét Ellenőr státusz';

        return $columns;
    }

    public function flagOrder($orderId)
    {
        if (!$this->flagOrders) {
            return;
        }

        $order = new WC_Order($orderId);
        $email = $order->get_billing_email();
        $connector = new UVBConnector(
            $email,
            $this->publicKey,
            $this->privateKey,
            $this->production
        );

        $connector->threshold = $this->threshold;

        $response = json_decode($connector->get());
        if (!$response) {
            return;
        }

        $flagValue = $this->getFlagValue($response->message->totalRate);
        if (!$flagValue) {
            return;
        }

        self::writeFlagToDb($orderId, $flagValue);
    }

    public static function writeFlagToDb($orderId, $flagValue)
    {
        $metaKey = '_uvb_connector_woocommerce_flag';

        if (self::isHposActive()) {
            $order = wc_get_order($orderId);
            $order->add_meta_data($metaKey, $flagValue);
            $order->save();
        } else {
            update_post_meta($orderId, $metaKey, $flagValue);
        }
    }

    public static function getFlagFromDb($orderId)
    {
        $metaKey = '_uvb_connector_woocommerce_flag';

        if (self::isHposActive()) {
            return wc_get_order($orderId)->get_meta($metaKey, true);
        }

        return get_post_meta($orderId, $metaKey, true);
    }

    public function getFlagValue($totalRate)
    {
        if ($this->threshold <= $totalRate) {
            return null;
        }

        if (0 < $this->threshold - $totalRate && $this->threshold - $totalRate < 0.05) {
            return 'warning';
        }

        return 'error';
    }

    /**
     * Show notice if the Order was flagged during checkout.
     */
    public function showFlagNotice()
    {
        global $post;

        /**
         * Do not show notices if the flagging is disabled.
         */
        if (!$this->flagOrders) {
            return;
        }

        /**
         * If the screen type and ID are not `shop_order`, return.
         */
        $screen = get_current_screen();
        if ($screen->post_type != 'shop_order' || $screen->id != 'shop_order') {
            return;
        }

        $flag = self::getFlagFromDb($post->ID);

        /**
         * If no flag exists for the order, return.
         */
        if (!$flag) {
            return;
        }

        if ($flag == 'error') {
            $class = 'notice-error';
            $message = 'Beware! Fulfilling this order might result in a refused package!';
        }
        
        if ($flag == 'warning') {
            $class = 'notice-warning';
            $message = 'The reputation for this customer was around the threshold. Be careful when fulfilling this order!';
        }
        
        if (!isset($message)) {
            return;
        }

        $message = __($message, 'uvb-connector-woocommerce' );

        printf( '<div class="notice %1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }

    public function showFlagNoticeInColumn($column, $order)
    {
        if ($column !== 'uvb_status') {
            return;
        }

        $postId = self::isHposActive() ? json_decode($order)->id : $order;
        $flag = self::getFlagFromDb($postId);

        if ('uvb_status' === $column) {
            if ($flag == 'error') {
                echo '<div style="display: flex; align-items: center; font-weight: bold; color: #db3535;" title="A vásárló az ellenőrzés során nem felelt meg a beállított követelménynek."><svg xmlns="http://www.w3.org/2000/svg" style="width: 1.5rem; height: 1.5rem; margin-right: 1rem;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg> Figyelem!</div>';
            } elseif ($flag == 'warning') {
                echo '<div style="display: flex; align-items: center; font-weight: bold; color: #c68c02;" title="A vásárló az ellenőrzés során éppen csak megfelelt a beállított követelménynek."><svg xmlns="http://www.w3.org/2000/svg" style="width: 1.5rem; height: 1.5rem; margin-right: 1rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg> Bizonytalan eredmény.</div>';
            } else {
                echo '<div style="display: flex; align-items: center; font-weight: bold; color: #2c9188;" title="Nem tudunk arról, hogy a rendelés kiszállítása a vásárlónak kockázatos lenne."><svg xmlns="http://www.w3.org/2000/svg" style="width: 1.5rem; height: 1.5rem; margin-right: 1rem;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg> Rendben.</div>';
            }
        }
    }

    /**
     * Flag the user if the order ends up in the 'Flagged' order status
     *
     * @param $order_id
     * @return void
     */
    public function sendMinusToUVBService($order_id)
    {
        return $this->sendSignalToUVBService($order_id, -1);
    }

    /**
     * Pat the user if the order ends up in the 'Completed' order status
     *
     * @param $order_id
     * @return void
     */
    public function sendPlusToUVBService($order_id)
    {
        return $this->sendSignalToUVBService($order_id, 1);
    }

    public function sendSignalToUVBService($order_id, $outcome)
    {
        $order = wc_get_order($order_id);

        /**
         * Restock product automatically.
         */
        if ($outcome === -1) {
            wc_maybe_increase_stock_levels($order_id);
        }

        $email = $order->get_billing_email();
        $phoneNumber = $order->get_shipping_phone();
        $countryCode = $order->get_shipping_country();
        $postalCode = $order->get_shipping_postcode();
        $addressLine1 = $order->get_shipping_address_1();
        $addressLine2 = $order->get_shipping_address_2();
        $addressLine = implode(' ', [$addressLine1, $addressLine2]);

        $connector = new UVBConnector(
            $email,
            $this->publicKey,
            $this->privateKey,
            $this->production
        );

        return $connector->post($outcome, $order_id, $phoneNumber, $countryCode, $postalCode, $addressLine);
    }

    public function addUvbActionsToBulkMenu($actions)
    {
        $actions['set-uvb_flagged'] = 'Change status to Rendelést nem vette át';

        return $actions;
    }

    public function catchUvbActionFromBulkMenu($redirect, $doaction, $postIds)
    {
        if (!wp_verify_nonce( $_GET['_wpnonce'], 'bulk-posts') && !wp_verify_nonce( $_GET['_wpnonce'], 'bulk-orders')) {
            return $redirect;
        }

        if (!self::isOrdersListPage()) {
            return $redirect;
        }

        $action = $doaction ?? null;
        if (!$action) {
            return $redirect;
        }

        $status = substr($action, 4, strlen($action) - 4);
        if ($status !== 'uvb_flagged') {
            return $redirect;
        }

        foreach ($postIds as $postId) {
            self::updateOrderStatus($postId, $status);
        }

        return $redirect;
    }

    public static function isHposActive()
    {
        return class_exists(Automattic\WooCommerce\Utilities\OrderUtil::class) && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    public static function isOrdersListPage()
    {
        if (!self::isHposActive()) {
            $postType = $_GET['post_type'] ?? null;

            return $postType == 'shop_order';
        }

        $page = $_GET['page'] ?? null;

        return $page == 'wc-orders';
    }

    public static function updateOrderStatus($orderId, $status)
    {
        if (!is_numeric($orderId)) {
            return;
        }

        $order = wc_get_order($orderId);
        $order->update_status($status);
    }
}
