<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://utanvet-ellenor.hu
 * @since      1.0.0
 *
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    UVBConnectorWooCommerce
 * @subpackage UVBConnectorWooCommerce/includes
 * @author     Utánvét Ellenőr <hello@utanvet-ellenor.hu>
 */
class UVBConnectorWooCommerce {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      UVBConnectorWooCommerce_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'UVB_CONNECTOR_WOOCOMMERCE_VERSION' ) ) {
			$this->version = UVB_CONNECTOR_WOOCOMMERCE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'uvb-connector-woocommerce';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - UVBConnectorWooCommerce_Loader. Orchestrates the hooks of the plugin.
	 * - UVBConnectorWooCommerce_i18n. Defines internationalization functionality.
	 * - UVBConnectorWooCommerce_Admin. Defines all hooks for the admin area.
	 * - UVBConnectorWooCommerce_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-uvb-connector-woocommerce-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-uvb-connector-woocommerce-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-uvb-connector-woocommerce-admin.php';
        require_once plugin_dir_path( __DIR__ ) . 'admin/class-uvb-connector-woocommerce-settings.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-uvb-connector-woocommerce-public.php';

		$this->loader = new UVBConnectorWooCommerce_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the UVBConnectorWooCommerce_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new UVBConnectorWooCommerce_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

	$plugin_admin = new UVBConnectorWooCommerce_Admin( $this->get_plugin_name(), $this->get_version() );
        $plugin_settings = new UVBConnectorWooCommerce_Settings( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );

	// Register order status for WooCommerce
	$this->loader->add_action( 'init', $plugin_admin, 'registerOrderStatusFlagged');
        $this->loader->add_action( 'wc_order_statuses', $plugin_admin, 'addOrderStatusFlagged');

        // Add hooks to `completed` and `uvb_flagged` order statuses
        $this->loader->add_action( 'woocommerce_order_status_completed', $plugin_admin, 'sendPlusToUVBService');
        $this->loader->add_action( 'woocommerce_order_status_uvb_flagged', $plugin_admin, 'sendMinusToUVBService');

        // Add hooks needed for order flagging.
        $this->loader->add_action( 'woocommerce_new_order', $plugin_admin, 'flagOrder');
        $this->loader->add_action( 'manage_edit-shop_order_columns', $plugin_admin, 'addColumnFlagged');
        $this->loader->add_action( 'manage_woocommerce_page_wc-orders_columns', $plugin_admin, 'addColumnFlagged');
        $this->loader->add_action( 'manage_shop_order_posts_custom_column', $plugin_admin, 'showFlagNoticeInColumn', 5, 2);
        $this->loader->add_action( 'manage_woocommerce_page_wc-orders_custom_column', $plugin_admin, 'showFlagNoticeInColumn', 5, 2);

        // Add hooks for bulk order status changes
        $this->loader->add_filter('bulk_actions-edit-shop_order', $plugin_admin, 'addUvbActionsToBulkMenu');
        $this->loader->add_filter('bulk_actions-woocommerce_page_wc-orders', $plugin_admin, 'addUvbActionsToBulkMenu');
        $this->loader->add_action('handle_bulk_actions-edit-shop_order', $plugin_admin, 'catchUvbActionFromBulkMenu', 10, 3);
        $this->loader->add_action('handle_bulk_actions-woocommerce_page_wc-orders', $plugin_admin, 'catchUvbActionFromBulkMenu', 10 , 3);

        $this->loader->add_action( 'admin_menu', $plugin_settings, 'add_plugin_page');

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new UVBConnectorWooCommerce_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'init', $plugin_public, 'init_session');

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

        $this->loader->add_action( 'wp_ajax_check_if_email_is_flagged', $plugin_public, 'check_if_email_is_flagged' );
        $this->loader->add_action( 'wp_ajax_nopriv_check_if_email_is_flagged', $plugin_public, 'check_if_email_is_flagged' );

        $this->loader->add_action( 'woocommerce_available_payment_gateways', $plugin_public, 'update_available_payment_options' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    UVBConnectorWooCommerce_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
