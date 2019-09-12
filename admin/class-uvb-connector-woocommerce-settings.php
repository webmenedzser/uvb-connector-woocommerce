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
            <h1>UV-B Connector Settings</h1>
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
            'API settings', // Title
            array( $this, 'print_section_info' ), // Callback
            'uvb-connector-woocommerce-admin' // Page
        );

        add_settings_field(
            'public_api_key', // ID
            'Public API Key', // Title
            array( $this, 'public_api_key_callback' ), // Callback
            'uvb-connector-woocommerce-admin', // Page
            'uvb_connector_woocommerce_section_id' // Section
        );

        add_settings_field(
            'private_api_key',
            'Private API Key',
            array( $this, 'private_api_key_callback' ),
            'uvb-connector-woocommerce-admin',
            'uvb_connector_woocommerce_section_id'
        );

        add_settings_field(
            'reputation_threshold',
            'Reputation threshold',
            array( $this, 'reputation_threshold_callback' ),
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

        return $input_array;
    }

    /**
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your API keys: ';
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
    }
}
