<?php
/**
 * Asset Enqueuer
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Assets;

use Swiftcomplete\Customer\CustomerMeta;
use Swiftcomplete\Core\HookManager;
use Swiftcomplete\Settings\SettingsManager;
use Swiftcomplete\Utilities\CheckoutTypeIdentifier;
use Swiftcomplete\Utilities\FieldConstants;
use Swiftcomplete\Utilities\WooCommercePageContext;

defined('ABSPATH') || exit;

/**
 * Handles script and style enqueuing
 */
class AssetEnqueuer
{
    /**
     * Checkout type detector
     *
     * @var CheckoutTypeIdentifier
     */
    private $checkout_type_identifier;

    /**
     * WooCommerce page context detector
     *
     * @var WooCommercePageContext
     */
    private $page_context;

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
     * Customer what3words meta
     *
     * @var CustomerMeta
     */
    private $customer_meta;

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
     * @param CheckoutTypeIdentifier          $checkout_type_identifier  Checkout type detector
     * @param WooCommercePageContext        $page_context             Page context
     * @param HookManager                   $hook_manager             Hook manager
     * @param SettingsManager               $settings_manager         Settings manager
     * @param CustomerMeta $customer_meta Customer what3words meta
     * @param string                        $version                  Plugin version
     * @param string                        $plugin_url               Plugin URL
     */
    public function __construct(
        CheckoutTypeIdentifier $checkout_type_identifier,
        WooCommercePageContext $page_context,
        HookManager $hook_manager,
        SettingsManager $settings_manager,
        CustomerMeta $customer_meta,
        string $version,
        string $plugin_url
    ) {
        $this->checkout_type_identifier = $checkout_type_identifier;
        $this->page_context = $page_context;
        $this->hook_manager = $hook_manager;
        $this->settings_manager = $settings_manager;
        $this->customer_meta = $customer_meta;
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
        if (!class_exists('WooCommerce')) {
            return;
        }

        if (!$this->settings_manager->is_enabled()) {
            return;
        }

        $this->hook_manager->register_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', array($this, 'enqueue_blocks_scripts'), 10, 0);

        $this->hook_manager->register_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 10, 0);

        $this->hook_manager->register_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'), 10, 0);
    }

    public function enqueue_blocks_scripts(): void
    {
        $deps = $this->enqueue_default_scripts();
        $this->enqueue_checkout_scripts($deps, true);
    }

    /**
     * Enqueue all checkout scripts and styles
     * Common method used by both enqueue_for_checkout and fallback
     *
     * @return void
     */
    public function enqueue_scripts(): void
    {
        $deps = $this->enqueue_default_scripts();
        if ($this->checkout_type_identifier->is_shortcode_checkout()) {
            $this->enqueue_checkout_scripts($deps, false);
        }
        if ($this->checkout_type_identifier->is_order_received_page()) {
            $handle = 'swiftcomplete-address-fields';
            wp_enqueue_script(
                $handle,
                $this->plugin_url . 'assets/js/address.js',
                $deps,
                $this->version,
                true
            );
            self::invoke_function_inline_script(
                $handle,
                'repositionConfirmationFields()',
                array()
            );
        }
        if ($this->page_context->is_my_account_view_order_page()) {
            $handle = 'swiftcomplete-address-fields';
            wp_enqueue_script(
                $handle,
                $this->plugin_url . 'assets/js/address.js',
                $deps,
                $this->version,
                true
            );
            self::invoke_function_inline_script(
                $handle,
                'repositionConfirmationFields();',
                array()
            );
        }
    }

    /**
     * Enqueue scripts and styles for admin
     *
     * @return void
     */
    public function enqueue_admin_scripts(): void
    {
        $deps = $this->enqueue_default_scripts();
        $handle = 'swiftcomplete-address';
        wp_enqueue_script(
            $handle,
            $this->plugin_url . 'assets/js/admin.js',
            $deps,
            $this->version,
            true
        );
        $component_handle = $this->enqueue_component_loader(array_merge($deps, array($handle)));
        self::invoke_function_inline_script(
            $handle,
            'const COMPONENT_DEFAULTS = %s;',
            array(
                'ADDRESS_SEARCH_FIELD_ID' => str_replace('-', '_', FieldConstants::ADDRESS_SEARCH_FIELD_ID),
                'WHAT3WORDS_FIELD_ID' => FieldConstants::WHAT3WORDS_FIELD_ID,
                'STATE_UPDATE_DELAY' => 50,
                'WHAT3WORDS_UPDATE_DELAY' => 50,
                'WHAT3WORDS_VISIBILITY_DELAY' => 100,
                'COUNTRY_CHANGE_DELAY' => 50,
            ),
            'before'
        );
        self::invoke_function_inline_script(
            $handle,
            'if (typeof COMPONENT_DEFAULTS !== "undefined") { sc_fields.what3wordsFieldId = COMPONENT_DEFAULTS.WHAT3WORDS_FIELD_ID || null; sc_fields.addressSearchFieldId = COMPONENT_DEFAULTS.ADDRESS_SEARCH_FIELD_ID || null; }',
            array(),
            'after'
        );

        if ($this->page_context->is_swiftcomplete_settings_page()) {
            self::invoke_function_inline_script(
                $component_handle,
                'setupLocationBiasedSearch(%s);',
                array($this->settings_manager->get_setting('api_key')),
                'after',
            );
        }
    }

    private function enqueue_default_scripts(): array
    {
        $browser_support_handle = $this->enqueue_browser_compatibility();
        $component_handle = $this->enqueue_swiftcomplete_component(array($browser_support_handle));
        return array($browser_support_handle, $component_handle);
    }

    private function enqueue_checkout_scripts(array $deps, bool $is_blocks): void
    {
        $handle = $this->enqueue_checkout($deps, $is_blocks);
        self::invoke_function_inline_script(
            $handle,
            'const COMPONENT_DEFAULTS = %s;',
            array(
                'ADDRESS_SEARCH_FIELD_ID' => FieldConstants::ADDRESS_SEARCH_FIELD_ID,
                'WHAT3WORDS_FIELD_ID' => FieldConstants::WHAT3WORDS_FIELD_ID,
                'STATE_UPDATE_DELAY' => 50,
                'WHAT3WORDS_UPDATE_DELAY' => 50,
                'WHAT3WORDS_VISIBILITY_DELAY' => 100,
                'COUNTRY_CHANGE_DELAY' => 50,
            ),
            'before'
        );
    }

    private function enqueue_swiftcomplete_component(array $deps = array()): string
    {
        $handle = 'swiftcomplete-component';
        if (!wp_script_is($handle, 'enqueued')) {
            wp_enqueue_script(
                $handle,
                'https://assets.swiftcomplete.com/js/swiftlookup.js',
                $deps,
                $this->version,
                true
            );
        }
        return $handle;
    }

    /**
     * Enqueue browser compatibility check script
     * This should load before all other Swiftcomplete scripts
     *
     * @return void
     */
    private function enqueue_browser_compatibility(): string
    {
        $handle = 'swiftcomplete-browser-support';
        if (!wp_script_is($handle, 'enqueued')) {
            wp_enqueue_script(
                $handle,
                $this->plugin_url . 'assets/js/support.js',
                array(),
                $this->version,
                true
            );
        }
        return $handle;
    }

    /**
     * Enqueue scripts for shortcode checkout
     *
     * @return void
     */
    private function enqueue_checkout(array $deps = array(), bool $is_blocks = false): string
    {
        $handle = 'swiftcomplete-fields';
        wp_enqueue_script(
            $handle,
            $this->plugin_url . 'assets/js/' . ($is_blocks ? 'blocks/' : '') . 'fields.js',
            $deps,
            $this->version,
            true
        );

        if ($is_blocks) {
            self::invoke_function_inline_script(
                $handle,
                'initialiseSwiftcompleteFields(%s);',
                $this->get_blocks_checkout_field_config()
            );
        } else {
            self::invoke_function_inline_script(
                $handle,
                'if (typeof COMPONENT_DEFAULTS !== "undefined") { sc_fields.what3wordsFieldId = COMPONENT_DEFAULTS.WHAT3WORDS_FIELD_ID || null; sc_fields.addressSearchFieldId = COMPONENT_DEFAULTS.ADDRESS_SEARCH_FIELD_ID || null; }',
                array(),
                'after'
            );
        }

        $this->enqueue_component_loader(array_merge($deps, array($handle)), $is_blocks);
        return $handle;
    }

    private function enqueue_component_loader(array $deps = array(), bool $is_blocks = false): string
    {
        $handle = 'swiftcomplete-component-loader';
        wp_enqueue_script(
            $handle,
            $this->plugin_url . 'assets/js/component-loader.js',
            $deps,
            $this->version,
            true
        );

        self::invoke_function_inline_script(
            $handle,
            'loadSwiftcompleteComponent(%s);',
            array_merge(
                array('isBlocks' => $is_blocks),
                $this->settings_manager->get_js_settings()
            )
        );

        return $handle;
    }

    /**
     * Get configuration array for blocks checkout JavaScript
     *
     * @return array<string, string> Configuration array with field constants
     */
    private function get_blocks_checkout_field_config(): array
    {
        $config = array(
            'w3wEnabled' => ($this->settings_manager->get_setting('w3w_enabled', false) === true),
            'billingLabel' => $this->settings_manager->get_setting('billing_label', 'Address Finder'),
            'shippingLabel' => $this->settings_manager->get_setting('shipping_label', 'Address Finder'),
        );

        $customer_values = $this->customer_meta->get_current_user_what3words();
        if (!empty($customer_values['billing']) || !empty($customer_values['shipping'])) {
            $config['customerValues'] = $customer_values;
        }

        return $config;
    }

    /**
     * Invoke a function inline script
     *
     * @param string $handle The handle of the script to invoke
     * @param string $fn The function to invoke
     * @param array $args The arguments to pass to the function
     * @return void
     */
    private static function invoke_function_inline_script(string $handle, string $fn, array $args = array(), string $position = 'after'): void
    {
        if (!wp_script_is($handle, 'enqueued')) {
            error_log('Script not enqueued: ' . $handle);
            return;
        }
        $script = sprintf($fn, wp_json_encode($args));
        wp_add_inline_script($handle, $script, $position);
    }
}
