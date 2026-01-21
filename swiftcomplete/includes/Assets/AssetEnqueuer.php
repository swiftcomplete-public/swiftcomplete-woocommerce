<?php
/**
 * Asset Enqueuer
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Assets;

use Swiftcomplete\Contracts\AssetEnqueuerInterface;
use Swiftcomplete\Core\HookManager;
use Swiftcomplete\Settings\SettingsManager;
use Swiftcomplete\Utilities\CheckoutTypeIdentifier;
use Swiftcomplete\Utilities\FieldConstants;

defined('ABSPATH') || exit;

/**
 * Handles script and style enqueuing
 */
class AssetEnqueuer implements AssetEnqueuerInterface
{
    /**
     * Checkout type detector
     *
     * @var CheckoutTypeIdentifier
     */
    private $checkout_type_identifier;

    /**
     * Hook manager
     *
     * @var HookManager
     */
    private $hook_manager;

    /**
     * Settings manager
     *
     * @var SettingsManager
     */
    private $settings_manager;

    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Plugin URL
     *
     * @var string
     */
    private $plugin_url;

    /**
     * Constructor
     *
     * @param CheckoutTypeIdentifier $checkout_type_identifier Checkout type detector
     * @param HookManager         $hook_manager  Hook manager
     * @param SettingsManager     $settings_manager Settings manager
     * @param string                          $version       Plugin version
     * @param string                          $plugin_url    Plugin URL
     */
    public function __construct(
        CheckoutTypeIdentifier $checkout_type_identifier,
        HookManager $hook_manager,
        SettingsManager $settings_manager,
        string $version,
        string $plugin_url
    ) {
        $this->checkout_type_identifier = $checkout_type_identifier;
        $this->hook_manager = $hook_manager;
        $this->settings_manager = $settings_manager;
        $this->version = $version;
        $this->plugin_url = $plugin_url;
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Only register if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        if (!$this->settings_manager->is_enabled()) {
            return;
        }

        // Enqueue scripts for blocks checkout
        $this->hook_manager->register_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', array($this, 'enqueue_for_checkout'), 10, 0);
        // Fallback for shortcode checkout
        $this->hook_manager->register_action('wp_enqueue_scripts', array($this, 'enqueue_for_checkout'), 10, 0);
    }

    /**
     * Enqueue scripts and styles for checkout
     *
     * @return void
     */
    public function enqueue_for_checkout(): void
    {
        // Check if scripts are already enqueued to prevent duplicate enqueuing
        if (wp_script_is('swiftcomplete-component', 'enqueued')) {
            return;
        }
        $this->enqueue_scripts();
    }

    /**
     * Enqueue all checkout scripts and styles
     * Common method used by both enqueue_for_checkout and fallback
     *
     * @return void
     */
    private function enqueue_scripts(): void
    {
        if (!wp_script_is('swiftcomplete-component', 'enqueued')) {
            wp_enqueue_script(
                'swiftcomplete-component',
                'https://assets.swiftcomplete.com/js/swiftlookup.js',
                array(),
                $this->version,
                true
            );
        }
        $this->enqueue_blocks_checkout_scripts();
        $this->enqueue_shortcode_checkout_scripts();
    }

    /**
     * Enqueue scripts for blocks checkout
     *
     * @return void
     */
    private function enqueue_blocks_checkout_scripts(): void
    {
        if (!$this->checkout_type_identifier->is_blocks_checkout()) {
            return;
        }

        // Enqueue CSS for styling
        wp_enqueue_style(
            'swiftcomplete-checkout-fields',
            $this->plugin_url . 'assets/css/blocks-checkout.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'swiftcomplete-checkout-fields',
            $this->plugin_url . 'assets/js/blocks/fields.js',
            array('wp-hooks', 'wp-element', 'wc-blocks-checkout'),
            $this->version,
            true
        );

        self::invoke_function_inline_script(
            'swiftcomplete-checkout-fields',
            'initialiseSwiftcompleteFields(%s);',
            $this->get_blocks_checkout_field_config()
        );

        wp_enqueue_script(
            'swiftcomplete-component-loader',
            $this->plugin_url . 'assets/js/component-loader.js',
            array('swiftcomplete-component', 'swiftcomplete-checkout-fields'),
            $this->version,
            true
        );

        self::invoke_function_inline_script(
            'swiftcomplete-component-loader',
            'loadSwiftcompleteComponent(%s);',
            $this->settings_manager->get_js_settings()
        );
    }

    /**
     * Enqueue scripts for shortcode checkout
     *
     * @return void
     */
    private function enqueue_shortcode_checkout_scripts(): void
    {
        if (!$this->checkout_type_identifier->is_shortcode_checkout()) {
            return;
        }

        // Enqueue JavaScript to position the field before address_1
        wp_enqueue_script(
            'swiftcomplete-component-loader',
            $this->plugin_url . 'assets/js/component-loader.js',
            array('swiftcomplete-component'),
            $this->version,
            true
        );

        self::invoke_function_inline_script(
            'swiftcomplete-component-loader',
            'loadSwiftcompleteComponent(%s);',
            $this->settings_manager->get_js_settings()
        );
    }

    /**
     * Get configuration array for blocks checkout JavaScript
     *
     * @return array<string, string> Configuration array with field constants
     */
    private function get_blocks_checkout_field_config(): array
    {
        return array(
            'fieldId' => FieldConstants::ADDRESS_SEARCH_FIELD_ID,
            'dataFieldNameSuffix' => FieldConstants::ADDRESS_SEARCH_DATA_FIELD_NAME_SUFFIX,
        );
    }

    /**
     * Invoke a function inline script
     *
     * @param string $handle The handle of the script to invoke
     * @param string $fn The function to invoke
     * @param array $args The arguments to pass to the function
     * @return void
     */
    private static function invoke_function_inline_script(string $handle, string $fn, array $args = array()): void
    {
        $script = sprintf($fn, wp_json_encode($args));
        wp_add_inline_script($handle, $script, 'after');
    }
}
