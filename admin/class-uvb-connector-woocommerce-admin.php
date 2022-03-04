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
    public function register_order_status_flagged() {
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
    public function add_order_status_flagged($order_statuses) {
        $order_statuses['wc-uvb_flagged'] = 'Rendelést nem vette át';

        return $order_statuses;
    }

    public function addColumnFlagged($columns) {
        $columns['uvb_status'] = 'Utánvét Ellenőr státusz';

        return $columns;
    }

    public function flagOrder($orderId)
    {
        $options = get_option('uvb_connector_woocommerce_options');
        $flagOrders = $options['flag_orders'] ?? false;

        if ($flagOrders) {
            $order = new WC_Order($orderId);
            $email = $order->get_billing_email();

            $options = get_option('uvb_connector_woocommerce_options');
            $publicApiKey = $options['public_api_key'];
            $privateApiKey = $options['private_api_key'];
            $production = isset($options['sandbox_mode']) ? false : true;
            $threshold = $options['reputation_threshold'];

            $connector = new UVBConnector(
                $email,
                $publicApiKey,
                $privateApiKey,
                $production
            );

            $connector->threshold = $threshold;

            $response = json_decode($connector->get());

            /**
             * If the `totalRate` is below the threshold + 5%, mark it as suspicious.
             */
            if ($threshold != 1 && $response->message->totalRate < ($threshold + 0.05)) {
                $flagValue = 'warning';
            }

            /**
             * If the `totalRate` is below the threshold, mark it as failed.
             */
            if ($response->message->totalRate < $threshold) {
                $flagValue = 'error';
            }

            /**
             * If no `$flagValue` is set, return.
             */
            if (!isset($flagValue)) {
                return;
            }

            update_post_meta($orderId, '_uvb_connector_woocommerce_flag', $flagValue);
        }
    }

    /**
     * Show notice if the Order was flagged during checkout.
     */
    public function showFlagNotice() {
        global $post;

        $options = get_option('uvb_connector_woocommerce_options');
        $flagOrders = $options['flag_orders'] ?? false;

        /**
         * Do not show notices if the flagging is disabled.
         */
        if (!$flagOrders) {
            return;
        }

        /**
         * If the screen type and ID are not `shop_order`, return.
         */
        $screen = get_current_screen();
        if ($screen->post_type != 'shop_order' || $screen->id != 'shop_order') {
            return;
        }

        $flag = get_post_meta($post->ID, '_uvb_connector_woocommerce_flag', true);

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

    public function showFlagNoticeInColumn( $column ) {
        global $post;

        $flag = get_post_meta($post->ID, '_uvb_connector_woocommerce_flag', true);

        if ('uvb_status' === $column) {
            if ($flag) {
                echo '<div style="display: flex; align-items: center; font-weight: bold; color: #db3535;"><svg xmlns="http://www.w3.org/2000/svg" style="width: 1.5rem; height: 1.5rem; margin-right: 1rem;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg> Figyelem!</div>';
            } else {
                echo '<div style="display: flex; align-items: center; font-weight: bold; color: #2c9188;"><svg xmlns="http://www.w3.org/2000/svg" style="width: 1.5rem; height: 1.5rem; margin-right: 1rem;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg> Rendben.</div>';
            }
        }
    }

    /**
     * Flag the user if the order ends up in the 'Flagged' order status
     *
     * @param $order_id
     * @return void
     */
    public function sendMinusToUVBService($order_id) {
        $order = wc_get_order($order_id);

        /**
         * Restock product automatically.
         */
        wc_maybe_increase_stock_levels($order_id);

        $email = $order->get_billing_email();
        $options = get_option('uvb_connector_woocommerce_options');
        $publicApiKey = $options['public_api_key'];
        $privateApiKey = $options['private_api_key'];
        $production = isset($options['sandbox_mode']) ? false : true;
        $outcome = -1;

        $connector = new UVBConnector(
            $email,
            $publicApiKey,
            $privateApiKey,
            $production
        );

        return $connector->post($outcome);
    }

    /**
     * Pat the user if the order ends up in the 'Completed' order status
     *
     * @param $order_id
     * @return void
     */
    public function sendPlusToUVBService($order_id) {
        $order = wc_get_order($order_id);

        $email = $order->get_billing_email();
        $options = get_option('uvb_connector_woocommerce_options');
        $publicApiKey = $options['public_api_key'];
        $privateApiKey = $options['private_api_key'];
        $production = isset($options['sandbox_mode']) ? false : true;
        $outcome = 1;

        $connector = new UVBConnector(
            $email,
            $publicApiKey,
            $privateApiKey,
            $production
        );

        return $connector->post($outcome);
    }

    public function addUvbActionsToBulkMenu($actions) {
        $actions['set-uvb_flagged'] = 'Change status to Rendelést nem vette át';

        return $actions;
    }

    public function catchUvbActionFromBulkMenu() {
        if ( !isset($_GET['post_type']) || $_GET['post_type'] != 'shop_order' ) {
            return;
        }

        if ( isset($_GET['action']) && 'set-' === substr( $_GET['action'], 0, 4 ) ) {
            // Check Nonce
            if ( !check_admin_referer('bulk-posts') ) {
                return;
            }

            // Remove 'set-' from action
            $new_status =  substr( $_GET['action'], 4 );

            $posts = $_GET['post'];

            foreach ($posts as $postID) {
                if ( !is_numeric($postID) ) {
                    continue;
                }

                $order = new WC_Order( (int)$postID );
                $order->update_status( $new_status, 'Status updated through bulk edit menu.' );
            }
        }
    }
}
