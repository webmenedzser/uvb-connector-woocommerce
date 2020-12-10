<?php
class UVBConnectorWooCommerce_Settings
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'UV-B Connector',
            'UV-B Connector',
            'manage_options',
            'uvb-connector-woocommerce.php',
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'uvb_connector_woocommerce_options' );
        ?>
        <div class="wrap">
            <h1><?php _e('UV-B Connector Settings', 'uvb-connector-woocommerce'); ?></h1>
            <form method="post" action="options.php">
                <?php
                    // This prints out all hidden setting fields
                    settings_fields( 'uvb_connector_woocommerce_group' );
                    do_settings_sections( 'uvb-connector-woocommerce-admin' );
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting(
            'uvb_connector_woocommerce_group', // Option group
            'uvb_connector_woocommerce_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'uvb_connector_woocommerce_section_id', // ID
            __('API settings', 'uvb-connector-woocommerce'), // Title
            array( $this, 'print_section_info' ), // Callback
            'uvb-connector-woocommerce-admin' // Page
        );

        add_settings_field(
            'public_api_key', // ID
            __('Public API Key', 'uvb-connector-woocommerce'), // Title
            array( $this, 'public_api_key_callback' ), // Callback
            'uvb-connector-woocommerce-admin', // Page
            'uvb_connector_woocommerce_section_id' // Section
        );

        add_settings_field(
            'private_api_key',
            __('Private API Key', 'uvb-connector-woocommerce'),
            array( $this, 'private_api_key_callback' ),
            'uvb-connector-woocommerce-admin',
            'uvb_connector_woocommerce_section_id'
        );

        add_settings_field(
            'reputation_threshold',
            __('Reputation threshold', 'uvb-connector-woocommerce'),
            array( $this, 'reputation_threshold_callback' ),
            'uvb-connector-woocommerce-admin',
            'uvb_connector_woocommerce_section_id'
        );

        add_settings_field(
            'sandbox_mode',
            __('Sandbox mode', 'uvb-connector-woocommerce'),
            array( $this, 'sandbox_mode_callback' ),
            'uvb-connector-woocommerce-admin',
            'uvb_connector_woocommerce_section_id'
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $input_array = array();
        if( isset( $input['public_api_key'] ) )
            $input_array['public_api_key'] = (string) sanitize_text_field($input['public_api_key']);

        if( isset( $input['private_api_key'] ) )
            $input_array['private_api_key'] = (string) sanitize_text_field($input['private_api_key']);

        if( isset( $input['reputation_threshold'] ) )
            $input_array['reputation_threshold'] = (float) $input['reputation_threshold'];

        if( isset( $input['sandbox_mode'] ) )
            $input_array['sandbox_mode'] = (bool) $input['sandbox_mode'];


        return $input_array;
    }

    /**
     * Print the Section text
     */
    public function print_section_info()
    {
        _e('Enter your API keys and set your preferences. ', 'uvb-connector-woocommerce');
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function public_api_key_callback()
    {
        printf(
            '<input type="text" id="public_api_key" name="uvb_connector_woocommerce_options[public_api_key]" value="%s" />',
            isset( $this->options['public_api_key'] ) ? esc_attr( $this->options['public_api_key']) : ''
        );
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function private_api_key_callback()
    {
        printf(
            '<input type="text" id="private_api_key" name="uvb_connector_woocommerce_options[private_api_key]" value="%s" />',
            isset( $this->options['private_api_key'] ) ? esc_attr( $this->options['private_api_key']) : ''
        );
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function reputation_threshold_callback()
    {
        printf(
            '<input type="number" max="1" step="0.0001" id="reputation_threshold" name="uvb_connector_woocommerce_options[reputation_threshold]" value="%s" />',
            isset( $this->options['reputation_threshold'] ) ? esc_attr( $this->options['reputation_threshold']) : ''
        );

        _e('<p><em>Calculated with the following formula: <code>(good-bad) / all</code>, so a 0.5 reputation can mean 6 successful and 2 rejected deliveries.</em></p>', 'uvb-connector-woocommerce');
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function sandbox_mode_callback()
    {
        $sandboxMode = isset($this->options['sandbox_mode']) ? $this->options['sandbox_mode'] : false;
        $endpointUrl = $sandboxMode ? \webmenedzser\UVBConnector\UVBConnector::SANDBOX_BASE_URL : \webmenedzser\UVBConnector\UVBConnector::PRODUCTION_BASE_URL;

        if ($sandboxMode) {
            printf('<input type="checkbox" id="sandbox_mode" name="uvb_connector_woocommerce_options[sandbox_mode]" checked />');
        } else {
            printf('<input type="checkbox" id="sandbox_mode" name="uvb_connector_woocommerce_options[sandbox_mode]" />');
        }

        _e('<p><em>Depending on this setting the plugin will use the production or sandbox environment of Utánvét Ellenőr. <strong>Please make sure this is set up correctly.</strong></em></p>', 'uvb-connector-woocommerce');

        _e("<p>Plugin will use <code>$endpointUrl</code>.</p>", 'uvb-connector-woocommerce');

        if ($sandboxMode) {
            _e('<p><span style="background: linear-gradient(to bottom right, #d20087, #79098f, #0c024a); color: white; font-weight: bold; display: inline-block; padding: 0.25rem 0.75rem; border-radius: 4px;">SANDBOX ENABLED</span></p>');
        }
    }
}
