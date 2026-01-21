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
            // Register save action for shortcode checkout, this hook passes order ID and checkout data
            $this->hook_manager->register_action('woocommerce_checkout_update_order_meta', array($this, 'legacy_save_field_value'), 5, 2);
        }

        $blocks_checkout = $this->get_checkout('blocks');
        if ($blocks_checkout instanceof BlocksCheckout) {
            // Register extension data mapping filter
            $this->hook_manager->register_filter('woocommerce_store_api_checkout_update_order_from_request', array($this, 'save_extension_data_to_order'), 10, 1);
        }
    }


    /**
     * Legacy save field value for shortcode checkout
     *
     * @param int   $order_id Order ID
     * @param array $_data    Checkout data (optional, may not always be passed)
     * @return void
     */
    public function legacy_save_field_value($order_id, $_data = array())
    {
        $nonce = isset($_POST['woocommerce-process-checkout-nonce']) ? wp_unslash($_POST['woocommerce-process-checkout-nonce']) : '';
        error_log('nonce: ' . $nonce);
        if (empty($nonce) || !wp_verify_nonce($nonce, 'woocommerce-process_checkout')) {
            return;
        }

        $order = wc_get_order($order_id);
        if ($order) {
            $this->save_extension_data_to_order($order, new \WP_REST_Request());
        }
    }

    /**
     * Map extension data from request to order
     *
     * @param \WC_Order        $order   Order object
     * @param \WP_REST_Request $request Request object
     * @return \WC_Order
     */
    public function save_extension_data_to_order(\WC_Order $order, \WP_REST_Request $request): \WC_Order
    {
        $checkout = $this->get_applicable_checkout();
        if ($checkout) {
            return $checkout->save_extension_data_to_order($order, $request);
        }
        return $order;
    }

    /**
     * Get applicable checkout for current checkout
     *
     * @return CheckoutInterface|null
     */
    private function get_applicable_checkout(): ?CheckoutInterface
    {
        foreach ($this->checkouts as $checkout) {
            if ($checkout->applies()) {
                return $checkout;
            }
        }
        return null;
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
