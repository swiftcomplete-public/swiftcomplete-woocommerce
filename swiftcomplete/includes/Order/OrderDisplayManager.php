<?php
/**
 * Order Display Manager
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Order;

use Swiftcomplete\Contracts\OrderDisplayInterface;
use Swiftcomplete\Contracts\OrderMetaRepositoryInterface;
use Swiftcomplete\Core\HookManager;
use Swiftcomplete\Utilities\CheckoutTypeIdentifier;
use Swiftcomplete\Utilities\FieldConstants;

defined('ABSPATH') || exit;

/**
 * Manages order display functionality
 */
class OrderDisplayManager implements OrderDisplayInterface
{
    /**
     * Order meta repository
     *
     * @var OrderMetaRepositoryInterface
     */
    private $meta_repository;

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
     * Constructor
     *
     * @param OrderMetaRepositoryInterface $meta_repository         Order meta repository
     * @param CheckoutTypeIdentifier        $checkout_type_identifier Checkout type detector
     * @param HookManager                  $hook_manager            Hook manager
     */
    public function __construct(
        OrderMetaRepositoryInterface $meta_repository,
        CheckoutTypeIdentifier $checkout_type_identifier,
        HookManager $hook_manager
    ) {
        $this->meta_repository = $meta_repository;
        $this->checkout_type_identifier = $checkout_type_identifier;
        $this->hook_manager = $hook_manager;
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

        // Display in admin order details
        $this->hook_manager->register_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_in_admin'), 10, 1);
        $this->hook_manager->register_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_in_admin'), 10, 1);

        // Display on order confirmation page
        $this->hook_manager->register_action('woocommerce_order_details_after_customer_details', array($this, 'display_on_order'), 10, 1);
        $this->hook_manager->register_action('woocommerce_thankyou', array($this, 'display_on_confirmation'), 10, 1);
    }

    /**
     * Display field in admin order details
     *
     * @param \WC_Order $order Order object
     * @return void
     */
    public function display_in_admin(\WC_Order $order): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $values = $this->get_field_values($order);
        $billing_value = $values['billing'];
        $shipping_value = $values['shipping'];

        // TODO: Replace this with what3words address (if enabled)
        if ($billing_value) {
            ?>
            <p class="form-field form-field-wide">
                <strong><?php esc_html_e('Billing Custom Field:', 'swiftcomplete'); ?></strong>
                <?php echo esc_html($billing_value); ?>
            </p>
            <?php
        }

        // TODO: Replace this with what3words address (if enabled)
        if ($shipping_value) {
            ?>
            <p class="form-field form-field-wide">
                <strong><?php esc_html_e('Shipping Custom Field:', 'swiftcomplete'); ?></strong>
                <?php echo esc_html($shipping_value); ?>
            </p>
            <?php
        }
    }

    /**
     * Display field on order page (customer view)
     *
     * @param \WC_Order $order Order object
     * @return void
     */
    public function display_on_order(\WC_Order $order): void
    {
        if ($this->checkout_type_identifier->is_shortcode_checkout()) {
            return;
        }

        if (!$order instanceof \WC_Order) {
            return;
        }

        $values = $this->get_field_values($order);
        $billing_value = $values['billing'];
        $shipping_value = $values['shipping'];

        // TODO: Replace this with what3words address (if enabled)
        if ($billing_value || $shipping_value) {
            ?>
            <section class="woocommerce-customer-details">
                <h2 class="woocommerce-order-details__title"><?php esc_html_e('Custom Field', 'swiftcomplete'); ?></h2>
                <?php if ($billing_value): ?>
                    <p><strong><?php esc_html_e('Billing:', 'swiftcomplete'); ?></strong> <?php echo esc_html($billing_value); ?></p>
                <?php endif; ?>
                <?php if ($shipping_value): ?>
                    <p><strong><?php esc_html_e('Shipping:', 'swiftcomplete'); ?></strong> <?php echo esc_html($shipping_value); ?></p>
                <?php endif; ?>
            </section>
            <?php
        }
    }

    /**
     * Display field on confirmation page (woocommerce_thankyou hook)
     *
     * @param int $order_id Order ID
     * @return void
     */
    public function display_on_confirmation(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $values = $this->get_field_values($order);
        $billing_value = $values['billing'];
        $shipping_value = $values['shipping'];

        // TODO: Replace this with what3words address (if enabled)
        if ($billing_value || $shipping_value) {
            ?>
            <section class="woocommerce-customer-details">
                <h2 class="woocommerce-order-details__title"><?php esc_html_e('Custom Fields', 'swiftcomplete'); ?></h2>
                <?php if ($billing_value): ?>
                    <p><strong><?php esc_html_e('Billing:', 'swiftcomplete'); ?></strong> <?php echo esc_html($billing_value); ?></p>
                <?php endif; ?>
                <?php if ($shipping_value): ?>
                    <p><strong><?php esc_html_e('Shipping:', 'swiftcomplete'); ?></strong> <?php echo esc_html($shipping_value); ?></p>
                <?php endif; ?>
            </section>
            <?php
        }
    }

    /**
     * Get field values from order
     *
     * @param \WC_Order $order Order object
     * @return array{billing: string, shipping: string}
     */
    private function get_field_values(\WC_Order $order): array
    {
        if ($this->meta_repository instanceof OrderMetaRepository) {
            return $this->meta_repository->get_field_values_from_order($order);
        }

        // Fallback if repository doesn't have the method
        $billing_value = $this->meta_repository->get($order->get_id(), FieldConstants::get_billing_meta_key());
        $shipping_value = $this->meta_repository->get($order->get_id(), FieldConstants::get_shipping_meta_key());

        // Try blocks meta keys
        if (empty($billing_value)) {
            $billing_value = $this->meta_repository->get($order->get_id(), FieldConstants::get_billing_blocks_meta_key());
        }

        if (empty($shipping_value)) {
            $shipping_value = $this->meta_repository->get($order->get_id(), FieldConstants::get_shipping_blocks_meta_key());
        }

        return array(
            'billing' => $billing_value ?: '',
            'shipping' => $shipping_value ?: '',
        );
    }
}
