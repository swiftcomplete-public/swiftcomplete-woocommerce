<?php
/**
 * Checkout Handler
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Checkout;

use Swiftcomplete\Checkout\BlocksCheckout;
use Swiftcomplete\Checkout\ShortcodeCheckout;
use Swiftcomplete\Contracts\CheckoutInterface;
use Swiftcomplete\Core\HookManager;
use Swiftcomplete\Settings\SettingsManager;

defined('ABSPATH') || exit;

/**
 * Main checkout handler that delegates to appropriate checkout
 */
class CheckoutHandler
{
    /**
     * Checkouts
     *
     * @var CheckoutInterface[]
     */
    private $checkouts;

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
     * Constructor
     *
     * @param CheckoutInterface[] $checkouts   Available checkouts
     * @param HookManager                 $hook_manager Hook manager
     */
    public function __construct(array $checkouts, HookManager $hook_manager, SettingsManager $settings_manager)
    {
        $this->checkouts = $checkouts;
        $this->hook_manager = $hook_manager;
        $this->settings_manager = $settings_manager;
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

        $shortcode_checkout = $this->get_checkout('shortcode');
        if ($shortcode_checkout instanceof ShortcodeCheckout) {
            // Register fields filter
            $this->hook_manager->register_filter('woocommerce_checkout_fields', array($shortcode_checkout, 'register_fields'), 10, 1);
            $this->hook_manager->register_filter('woocommerce_form_field', array($shortcode_checkout, 'remove_optional_fields_label'), 10, 4);
            // Register filter to pre-fill customer values
            $this->hook_manager->register_filter('woocommerce_checkout_get_value', array($this, 'get_customer_checkout_value'), 10, 2);
            // Register save action for shortcode checkout, this hook passes order ID and checkout data
            $this->hook_manager->register_action('woocommerce_checkout_update_order_meta', array($this, 'save_extension_data_to_order'), 5, 2);
        }

        $blocks_checkout = $this->get_checkout('blocks');
        if ($blocks_checkout instanceof BlocksCheckout) {
            // Register extension data mapping filter
            $this->hook_manager->register_filter('woocommerce_store_api_checkout_update_order_from_request', array($this, 'save_blocks_extension_data_to_order'), 10, 2);
        }
    }


    /**
     * Legacy save field value for shortcode checkout
     *
     * @param int   $order_id Order ID
     * @param array $_data    Checkout data (optional, may not always be passed)
     * @return void
     */
    public function save_extension_data_to_order($order_id, $data = array())
    {
        $nonce = isset($_POST['woocommerce-process-checkout-nonce']) ? wp_unslash($_POST['woocommerce-process-checkout-nonce']) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'woocommerce-process_checkout')) {
            return;
        }

        $order = wc_get_order($order_id);
        if ($order) {
            $shortcode_checkout = $this->get_checkout('shortcode');
            if ($shortcode_checkout instanceof ShortcodeCheckout) {
                $shortcode_checkout->save_extension_data_to_order($order, $data);
            }
        }
    }

    /**
     * Map extension data from request to order for blocks checkout
     *
     * @param \WC_Order        $order   Order object
     * @param \WP_REST_Request $request Request object
     * @return \WC_Order
     */
    public function save_blocks_extension_data_to_order(\WC_Order $order, \WP_REST_Request $request): \WC_Order
    {
        $blocks_checkout = $this->get_checkout('blocks');
        if ($blocks_checkout instanceof BlocksCheckout) {
            $data = $blocks_checkout->extract_extension_data_from_request($request);
            $blocks_checkout->save_extension_data_to_order($order, $data);
        }
        return $order;
    }

    /**
     * Get customer checkout value for shortcode checkout
     * Wrapper for woocommerce_checkout_get_value filter
     *
     * @param string|null $value Current field value
     * @param string      $input Field key
     * @return string|null
     */
    public function get_customer_checkout_value($value, string $input)
    {
        $shortcode_checkout = $this->get_checkout('shortcode');
        if ($shortcode_checkout instanceof ShortcodeCheckout) {
            $customer_value = $shortcode_checkout->get_customer_field_value($input);
            if ($customer_value !== null) {
                return $customer_value;
            }
        }
        return $value;
    }

    /**
     * Get checkout by type
     *
     * @param string $type Checkout type ('blocks' or 'shortcode')
     * @return CheckoutInterface|null
     */
    private function get_checkout(string $type): ?CheckoutInterface
    {
        foreach ($this->checkouts as $checkout) {
            if (
                ('blocks' === $type && $checkout instanceof BlocksCheckout) ||
                ('shortcode' === $type && $checkout instanceof ShortcodeCheckout)
            ) {
                return $checkout;
            }
        }
        return null;
    }
}
