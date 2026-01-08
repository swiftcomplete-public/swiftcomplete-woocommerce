<?php
/**
 * Checkout class
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;

/**
 * Swiftcomplete Checkout class
 */
class SwiftcompleteCheckout
{

    /**
     * Instance of this class
     *
     * @var SwiftcompleteCheckout
     */
    private static $instance = null;

    /**
     * Get instance of this class
     *
     * @return SwiftcompleteCheckout
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
        // Safety check: Only register hooks if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', array($this, 'enqueue_checkout_scripts'));
        add_action('woocommerce_checkout_billing', array($this, 'enqueue_billing_scripts'));
        add_action('woocommerce_checkout_shipping', array($this, 'enqueue_shipping_scripts'));
    }

    /**
     * Enqueue scripts for checkout blocks
     */
    public function enqueue_checkout_scripts()
    {
        // Safety check: Ensure WooCommerce is still active
        if (!class_exists('WooCommerce')) {
            return;
        }

        $this->enqueue_base_scripts();
        $this->add_inline_script('shipping');
        $this->add_inline_script('billing');
    }

    /**
     * Enqueue scripts for billing
     */
    public function enqueue_billing_scripts()
    {
        // Safety check: Ensure WooCommerce is still active
        if (!class_exists('WooCommerce')) {
            return;
        }

        $this->enqueue_base_scripts();
        $this->add_inline_script('billing');
    }

    /**
     * Enqueue scripts for shipping
     */
    public function enqueue_shipping_scripts()
    {
        // Safety check: Ensure WooCommerce is still active
        if (!class_exists('WooCommerce')) {
            return;
        }

        $this->enqueue_base_scripts();
        $this->add_inline_script('shipping');
    }

    /**
     * Enqueue base scripts (only once)
     */
    private function enqueue_base_scripts()
    {
        static $scripts_enqueued = false;

        if ($scripts_enqueued) {
            return;
        }

        // Safety check: Ensure WordPress functions exist
        if (!function_exists('wp_enqueue_script')) {
            return;
        }

        $scripts_enqueued = true;

        wp_enqueue_script('swiftcomplete_script', 'https://assets.swiftcomplete.com/js/swiftlookup.js', array(), Swiftcomplete::VERSION, true);

        $addressfinder_path = Swiftcomplete::get_plugin_path() . 'public/js/addressfinder.js';
        if (file_exists($addressfinder_path)) {
            wp_enqueue_script('swiftcomplete_launch', Swiftcomplete::get_plugin_url() . 'public/js/addressfinder.js', array('jquery'), Swiftcomplete::VERSION, true);
        }
    }

    /**
     * Add inline script for address lookup
     *
     * @param string $type Address type (billing or shipping).
     */
    private function add_inline_script($type)
    {
        // Safety check: Ensure WordPress functions exist
        if (!function_exists('wp_add_inline_script') || !function_exists('get_option')) {
            return;
        }

        $settings = get_option('swiftcomplete_settings');
        if (!is_array($settings)) {
            $settings = false;
        }

        $api_key = '';

        if ($settings !== false && array_key_exists('api_key', $settings) && strlen($settings['api_key']) > 0) {
            $api_key = $settings['api_key'];
        }

        $w3w_enabled = $settings === false || (!array_key_exists('w3w_enabled', $settings) || (array_key_exists('w3w_enabled', $settings) && $settings['w3w_enabled'] == true));
        $search_for = $w3w_enabled ? 'address,what3words' : 'address';
        $hide_fields = $settings !== false && array_key_exists('hide_fields', $settings) && $settings['hide_fields'] ? 'true' : 'false';
        $bias_lat_lon = $settings !== false && array_key_exists('bias_towards_lat_lon', $settings) ? esc_js($settings['bias_towards_lat_lon']) : '';

        if ($type === 'billing') {
            $placeholder = $settings !== false && array_key_exists('billing_placeholder', $settings) ? esc_js($settings['billing_placeholder']) : '';
        } else {
            $placeholder = $settings !== false && array_key_exists('shipping_placeholder', $settings) ? esc_js($settings['shipping_placeholder']) : '';
        }

        $state_counties = $settings !== false && array_key_exists('state_counties_enabled', $settings) ? esc_js($settings['state_counties_enabled']) : '';

        wp_add_inline_script('swiftcomplete_launch', sprintf('launchAddressLookup("%s", "%s", "%s", "%s", "%s", "%s", "%s");', esc_js($type), esc_js($api_key), esc_js($search_for), $hide_fields, $bias_lat_lon, $placeholder, $state_counties));
    }
}

