<?php
/**
 * Settings class
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;

/**
 * Swiftcomplete Settings class
 */
class SwiftcompleteSettings
{

    /**
     * Instance of this class
     *
     * @var SwiftcompleteSettings
     */
    private static $instance = null;

    /**
     * Get instance of this class
     *
     * @return SwiftcompleteSettings
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_menu'));
        add_filter('plugin_action_links_' . plugin_basename(Swiftcomplete::get_plugin_path() . 'swiftcomplete.php'), array($this, 'add_plugin_settings_link'));
    }

    /**
     * Add settings link to plugin actions
     *
     * @param array $actions Plugin actions.
     * @return array
     */
    public function add_plugin_settings_link($actions)
    {
        $mylinks = array(
            '<a href="' . admin_url('options-general.php?page=swiftcomplete') . '">' . __('Settings') . '</a>'
        );
        return array_merge($mylinks, $actions);
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('swiftcomplete_settings', 'swiftcomplete_settings', array($this, 'validate_settings'));

        add_settings_section('swiftcomplete_api_settings', 'Swiftcomplete Settings', array($this, 'render_help_text'), 'swiftcomplete');
        add_settings_field('swiftcomplete_api_key', 'API Key(required)', array($this, 'render_api_key_field'), 'swiftcomplete', 'swiftcomplete_api_settings');
        add_settings_field('swiftcomplete_w3w_enabled', 'Enable what3words?', array($this, 'render_w3w_enabled_field'), 'swiftcomplete', 'swiftcomplete_api_settings');
        add_settings_field('swiftcomplete_hide_fields', 'Hide address fields until an address is selected?', array($this, 'render_hide_fields_field'), 'swiftcomplete', 'swiftcomplete_api_settings');
        add_settings_field('swiftcomplete_state_counties_enabled', 'Return states / UK counties?', array($this, 'render_state_counties_enabled_field'), 'swiftcomplete', 'swiftcomplete_api_settings');

        add_settings_section('swiftcomplete_country_settings', 'Location biasing', array($this, 'render_country_header'), 'swiftcomplete');
        add_settings_field('swiftcomplete_bias_towards', 'Prioritise addresses near place', array($this, 'render_bias_towards_field'), 'swiftcomplete', 'swiftcomplete_country_settings');

        add_settings_section('swiftcomplete_text_settings', 'Search field labels and placeholders', array($this, 'render_text_header'), 'swiftcomplete');
        add_settings_field('swiftcomplete_billing_label', 'Billing field label', array($this, 'render_billing_label_field'), 'swiftcomplete', 'swiftcomplete_text_settings');
        add_settings_field('swiftcomplete_billing_placeholder', 'Billing field placeholder text', array($this, 'render_billing_placeholder_field'), 'swiftcomplete', 'swiftcomplete_text_settings');
        add_settings_field('swiftcomplete_shipping_label', 'Shipping field label', array($this, 'render_shipping_label_field'), 'swiftcomplete', 'swiftcomplete_text_settings');
        add_settings_field('swiftcomplete_shipping_placeholder', 'Shipping field placeholder text', array($this, 'render_shipping_placeholder_field'), 'swiftcomplete', 'swiftcomplete_text_settings');
    }

    /**
     * Validate settings
     *
     * @param array $input Input data.
     * @return array
     */
    public function validate_settings($input)
    {
        $newinput = array();
        $newinput['api_key'] = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        $newinput['billing_label'] = isset($input['billing_label']) ? sanitize_text_field($input['billing_label']) : '';
        $newinput['billing_placeholder'] = isset($input['billing_placeholder']) ? sanitize_text_field($input['billing_placeholder']) : '';
        $newinput['shipping_label'] = isset($input['shipping_label']) ? sanitize_text_field($input['shipping_label']) : '';
        $newinput['shipping_placeholder'] = isset($input['shipping_placeholder']) ? sanitize_text_field($input['shipping_placeholder']) : '';
        $newinput['bias_towards'] = isset($input['bias_towards']) ? sanitize_text_field($input['bias_towards']) : '';
        $newinput['bias_towards_lat_lon'] = isset($input['bias_towards_lat_lon']) ? sanitize_text_field($input['bias_towards_lat_lon']) : '';
        $newinput['w3w_enabled'] = isset($input['w3w_enabled']) && $input['w3w_enabled'] == true;
        $newinput['state_counties_enabled'] = isset($input['state_counties_enabled']) && $input['state_counties_enabled'] == true;
        $newinput['hide_fields'] = isset($input['hide_fields']) && $input['hide_fields'] == true;
        return $newinput;
    }

    /**
     * Add settings menu
     */
    public function add_settings_menu()
    {
        add_submenu_page('options-general.php', 'Swiftcomplete', 'Swiftcomplete', 'manage_options', 'swiftcomplete', array($this, 'render_settings_page'));
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        SwiftcompleteTemplateLoader::load_template('admin/settings-page');
    }

    /**
     * Render help text
     */
    public function render_help_text()
    {
        SwiftcompleteTemplateLoader::load_template('admin/settings-help-text');
    }

    /**
     * Render country header
     */
    public function render_country_header()
    {
        SwiftcompleteTemplateLoader::load_template('admin/country-header');
    }

    /**
     * Render text header
     */
    public function render_text_header()
    {
        SwiftcompleteTemplateLoader::load_template('admin/text-header');
    }

    /**
     * Render API key field
     */
    public function render_api_key_field()
    {
        $settings = get_option('swiftcomplete_settings');
        if ($settings === false || !array_key_exists('api_key', $settings)) {
            echo "<input id='swiftcomplete_api_key' name='swiftcomplete_settings[api_key]' type='text' value='' />";
        } else {
            echo "<input id='swiftcomplete_api_key' name='swiftcomplete_settings[api_key]' type='text' value='" . esc_attr($settings['api_key']) . "' />";
        }
    }

    /**
     * Render W3W enabled field
     */
    public function render_w3w_enabled_field()
    {
        $settings = get_option('swiftcomplete_settings');
        $w3w_enabled = $settings === false || (!array_key_exists('w3w_enabled', $settings) || (array_key_exists('w3w_enabled', $settings) && $settings['w3w_enabled'] == true));

        if ($w3w_enabled == true) {
            echo "<input id='swiftcomplete_w3w_enabled' name='swiftcomplete_settings[w3w_enabled]' type='checkbox' checked />";
        } else {
            echo "<input id='swiftcomplete_w3w_enabled' name='swiftcomplete_settings[w3w_enabled]' type='checkbox' />";
        }
    }

    /**
     * Render state counties enabled field
     */
    public function render_state_counties_enabled_field()
    {
        $settings = get_option('swiftcomplete_settings');

        if ($settings === false || !array_key_exists('state_counties_enabled', $settings)) {
            echo "<input id='swiftcomplete_state_counties_enabled' name='swiftcomplete_settings[state_counties_enabled]' type='checkbox' checked />";
        } else {
            echo "<input id='swiftcomplete_state_counties_enabled' name='swiftcomplete_settings[state_counties_enabled]' type='checkbox' " . ($settings['state_counties_enabled'] ? "checked " : "") . "/>";
        }
    }

    /**
     * Render hide fields field
     */
    public function render_hide_fields_field()
    {
        $settings = get_option('swiftcomplete_settings');
        if ($settings === false || !array_key_exists('hide_fields', $settings)) {
            echo "<input id='swiftcomplete_hide_fields' name='swiftcomplete_settings[hide_fields]' type='checkbox' />";
        } else {
            echo "<input id='swiftcomplete_hide_fields' name='swiftcomplete_settings[hide_fields]' type='checkbox' " . ($settings['hide_fields'] ? "checked " : "") . "/>";
        }
    }

    /**
     * Render billing label field
     */
    public function render_billing_label_field()
    {
        $settings = get_option('swiftcomplete_settings');
        if ($settings === false || !array_key_exists('billing_label', $settings)) {
            echo "<input id='swiftcomplete_billing_label' name='swiftcomplete_settings[billing_label]' type='text' value='Address Finder' />";
        } else {
            echo "<input id='swiftcomplete_billing_label' name='swiftcomplete_settings[billing_label]' type='text' value='" . esc_attr($settings['billing_label']) . "' />";
        }
        echo "<span class='help-text'>Name above billing search field (e.g. 'Address Finder')</span>";
    }

    /**
     * Render billing placeholder field
     */
    public function render_billing_placeholder_field()
    {
        $settings = get_option('swiftcomplete_settings');
        if ($settings === false || !array_key_exists('billing_placeholder', $settings)) {
            echo "<input id='swiftcomplete_billing_placeholder' name='swiftcomplete_settings[billing_placeholder]' type='text' value='' />";
        } else {
            echo "<input id='swiftcomplete_billing_placeholder' name='swiftcomplete_settings[billing_placeholder]' type='text' value='" . esc_attr($settings['billing_placeholder']) . "' />";
        }
        echo "<span class='help-text'>Prompt displayed in the billing search field (e.g. 'Type your address or postcode')</span>";
    }

    /**
     * Render shipping label field
     */
    public function render_shipping_label_field()
    {
        $settings = get_option('swiftcomplete_settings');
        if ($settings === false || !array_key_exists('shipping_label', $settings)) {
            echo "<input id='swiftcomplete_shipping_label' name='swiftcomplete_settings[shipping_label]' type='text' value='Address Finder' />";
        } else {
            echo "<input id='swiftcomplete_shipping_label' name='swiftcomplete_settings[shipping_label]' type='text' value='" . esc_attr($settings['shipping_label']) . "' />";
        }
        echo "<span class='help-text'>Name above shipping search field (e.g. 'Address Finder')</span>";
    }

    /**
     * Render shipping placeholder field
     */
    public function render_shipping_placeholder_field()
    {
        $settings = get_option('swiftcomplete_settings');
        if ($settings === false || !array_key_exists('shipping_placeholder', $settings)) {
            echo "<input id='swiftcomplete_shipping_placeholder' name='swiftcomplete_settings[shipping_placeholder]' type='text' value='' />";
        } else {
            echo "<input id='swiftcomplete_shipping_placeholder' name='swiftcomplete_settings[shipping_placeholder]' type='text' value='" . esc_attr($settings['shipping_placeholder']) . "' />";
        }
        echo "<span class='help-text'>Prompt displayed in the shipping search field (e.g. 'Type your address or postcode')</span>";
    }

    /**
     * Render bias towards field
     */
    public function render_bias_towards_field()
    {
        $settings = get_option('swiftcomplete_settings');
        if ($settings === false || !array_key_exists('bias_towards', $settings)) {
            echo "<input autocomplete='address' style='display: none;' />";
            echo "<input id='swiftcomplete_bias_towards' name='swiftcomplete_settings[bias_towards]' type='text' value='' placeholder='City, town or postcode' />";
            echo "<input type='hidden' id='swiftcomplete_bias_towards_lat_lon' name='swiftcomplete_settings[bias_towards_lat_lon]' />";
        } else {
            echo "<input autocomplete='address' style='display: none;' />";
            echo "<input id='swiftcomplete_bias_towards' name='swiftcomplete_settings[bias_towards]' type='text' value='" . esc_attr($settings['bias_towards']) . "' placeholder='City, town or postcode' />";
            echo "<input type='hidden' id='swiftcomplete_bias_towards_lat_lon' name='swiftcomplete_settings[bias_towards_lat_lon]' value='" . (array_key_exists('bias_towards_lat_lon', $settings) ? esc_attr($settings['bias_towards_lat_lon']) : '') . "' />";
        }
    }
}

