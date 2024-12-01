<?php

require UVB_CONNECTOR_VENDOR_AUTOLOAD_PATH;

use UtanvetEllenor\Client;
use UtanvetEllenor\Reasons;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://utanvet-ellenor.hu
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
 * @author     Utánvét Ellenőr <hello@utanvet-ellenor.hu>
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
        $order = new WC_Order($orderId);
        $email = $order->get_billing_email();
        $client = new Client($this->publicKey, $this->privateKey);
        $client->email = $email;
        $client->threshold = $this->threshold;
        $client->sandbox = !$this->production;

        $response = $client->sendRequest();
        if (!$response) {
            return;
        }

        $flagValue = $response->result->reason;
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
        $value = self::isHposActive() ? wc_get_order($orderId)->get_meta($metaKey, true) : get_post_meta($orderId, $metaKey, true);

        return $value;
    }

    public static function getFlagLevel(string $flag) : string
    {
        switch ($flag) {
            case Reasons::EXCEPTION_FOUND:
            case Reasons::NOT_FOUND:
            case Reasons::TEST_HASH:
                return 'notice';

            case Reasons::OUT_OF_QUOTA:
            case 'warning':
                return 'warning';

            case Reasons::MAILBOX_NON_EXISTENT:
            case Reasons::THRESHOLD_NOT_MET:
            case Reasons::TEMP_EMAIL:
            case 'error':
                return 'error';

            case Reasons::PASSED:
                return 'success';

            default:
                return 'success';
        }
    }

    public static function getFlagLabel(string $flag) : string
    {
        if ($flag === 'error') {
            return 'Figyelem!';
        }

        if ($flag === 'warning') {
            return 'Bizonytalan eredmény.';
        }

        return __($flag, 'uvb-connector-woocommerce');
    }

    public function showFlagNoticeInColumn($column, $order)
    {
        if ($column !== 'uvb_status') {
            return;
        }

        $postId = self::isHposActive() ? json_decode($order)->id : $order;
        $flag = self::getFlagFromDb($postId);
        if (!$flag) {
            return;
        }

        $level = self::getFlagLevel($flag);
        $label = self::getFlagLabel($flag);

        if ('uvb_status' === $column) {
            if ($level == 'error') {
                echo '<div style="display: flex; align-items: start; font-weight: bold; font-size: 12px; line-height: 1.5; color: #db3535;" title="' . $label . '"><svg xmlns="http://www.w3.org/2000/svg" style="width: 1.25rem; height: 1.25rem; margin-right: 0.75rem; flex: none;" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" /></svg>'. $label . '</div>';
            } elseif ($level == 'warning') {
                echo '<div style="display: flex; align-items: start; font-weight: bold; font-size: 12px; line-height: 1.5; color: #c68c02;" title="' . $label . '"><svg xmlns="http://www.w3.org/2000/svg" style="width: 1.25rem; height: 1.25rem; margin-right: 0.75rem; flex: none;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>'. $label . '</div>';
            } elseif ($level == 'success') {
                echo '<div style="display: flex; align-items: start; font-weight: bold; font-size: 12px; line-height: 1.5; color: #2c9188;" title="' . $label . '"><svg xmlns="http://www.w3.org/2000/svg" style="width: 1.25rem; height: 1.25rem; margin-right: 0.75rem; flex: none;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>'. $label . '</div>';
            } else {
                echo '<div style="display: flex; align-items: start; font-weight: bold; font-size: 12px; line-height: 1.5; color: #777;" title="' . $label . '"><svg xmlns="http://www.w3.org/2000/svg" fill="none" style="width: 1.25rem; height: 1.25rem; margin-right: 0.75rem; flex: none;" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>'. $label . '</div>';
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

        $client = new Client($this->publicKey, $this->privateKey);
        $client->email = $email;
        $client->orderId = $order_id;
        $client->outcome = $outcome;
        $client->countryCode = $countryCode;
        $client->postalCode = $postalCode;
        $client->phoneNumber = $phoneNumber;
        $client->addressLine = $addressLine;

        return $client->sendSignal();
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
