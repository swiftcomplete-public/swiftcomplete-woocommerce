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
use Swiftcomplete\Settings\SettingsManager;

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
     * Settings manager
     *
     * @var SettingsManager
     */
    private $settings_manager;

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
        HookManager $hook_manager,
        SettingsManager $settings_manager
    ) {
        $this->meta_repository = $meta_repository;
        $this->checkout_type_identifier = $checkout_type_identifier;
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

        // Display on order confirmation page
        $this->hook_manager->register_action('woocommerce_order_details_after_customer_details', array($this, 'display_on_order'), 10, 1);
        $this->hook_manager->register_action('woocommerce_thankyou', array($this, 'display_on_confirmation'), 10, 1);

        // Add fields to order in admin
        $this->hook_manager->register_action('woocommerce_admin_shipping_fields', array($this, 'add_swiftcomplete_order_shipping'), 10, 2);
        $this->hook_manager->register_action('woocommerce_admin_billing_fields', array($this, 'add_swiftcomplete_order_billing'), 10, 2);
    }

    /**
     * Display field on order page (customer view)
     *
     * @param \WC_Order $order Order object
     * @return void
     */
    public function display_on_order(\WC_Order $order): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $values = $this->get_field_values($order);
        $billing_value = $values['billing'];
        $shipping_value = $values['shipping'];

        if ($billing_value) {
            ?>
            <p id="what3words-billing">
                <b>what3words:</b>&nbsp;<?php echo esc_html($billing_value); ?>
            </p>
            <?php
        }
        if ($shipping_value) {
            ?>
            <p id="what3words-shipping">
                <b>what3words:</b>&nbsp;<?php echo esc_html($shipping_value); ?>
            </p>
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
        if (!$this->checkout_type_identifier->is_blocks_checkout()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $values = $this->get_field_values($order);
        $billing_value = $values['billing'];
        $shipping_value = $values['shipping'];
        if ($billing_value) {
            ?>
            <p id="what3words-billing">
                <b>what3words:</b>&nbsp;<?php echo esc_html($billing_value); ?>
            </p>
            <?php
        }
        if ($shipping_value) {
            ?>
            <p id="what3words-shipping">
                <b>what3words:</b>&nbsp;<?php echo esc_html($shipping_value); ?>
            </p>
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
        if (!$this->meta_repository instanceof OrderMetaRepository) {
            return array(
                'billing' => null,
                'shipping' => null,
            );
        }
        return $this->meta_repository->get_field_values_from_order($order);
    }

    public function add_swiftcomplete_order_billing(array $fields, $order = null): array
    {
        $order_id = $this->resolve_order_id($order);
        return $this->add_field_to_address('billing', $fields, $order_id);
    }

    public function add_swiftcomplete_order_shipping(array $fields, $order = null): array
    {
        $order_id = $this->resolve_order_id($order);
        return $this->add_field_to_address('shipping', $fields, $order_id);
    }

    /**
     * Resolve an order ID from WooCommerce admin field filters.
     *
     * WooCommerce versions differ here: some pass only $fields, others also pass
     * an order object (or order ID). This method supports all cases.
     *
     * @param mixed $order
     * @return int
     */
    private function resolve_order_id($order): int
    {
        $order_id = 0;

        if ($order instanceof \WC_Order) {
            $order_id = (int) $order->get_id();
        } elseif (is_numeric($order)) {
            $order_id = absint($order);
        } elseif (isset($_GET['post'])) {
            // Classic order edit screen: /wp-admin/post.php?post=XX&action=edit
            $order_id = absint($_GET['post']);
        } elseif (isset($_GET['id'])) {
            // HPOS order edit screen: /wp-admin/admin.php?page=wc-orders&action=edit&id=XX
            $order_id = absint($_GET['id']);
        } elseif (isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post) {
            $order_id = (int) $GLOBALS['post']->ID;
        }

        return $order_id;
    }

    private function add_field_to_address(string $field_id, array $fields, int $order_id, bool $show_w3w = true): array
    {
        $api_key = $this->settings_manager->get_setting('api_key', '');

        if (empty($api_key)) {
            return $fields;
        }

        $position = array_search('company', array_keys($fields));
        if ($position === false) {
            $position = array_search('address_1', array_keys($fields));
        }

        $label = $this->settings_manager->get_setting("{$field_id}_label", 'Address Finder');
        $search_field = array(
            'label' => __($label, 'swiftcomplete'),
            'class' => 'short',
            'show' => false,
            'type' => 'text',
            'wrapper_class' => 'form-field-wide',
        );
        if ($position === false) {
            $fields['swiftcomplete_address_search'] = $search_field;
        } else {
            $array = array_slice($fields, 0, $position, true);
            $array['swiftcomplete_address_search'] = $search_field;
            $fields = $array + array_slice($fields, $position, null, true);
        }

        $w3w_enabled = $this->settings_manager->get_setting('w3w_enabled');
        if ($w3w_enabled) {
            $order = wc_get_order($order_id);
            $order_values = $this->get_field_values($order);
            $what3words = isset($order_values[$field_id]) ? $order_values[$field_id] : '';
            $fields[FieldConstants::WHAT3WORDS_FIELD_ID] = array(
                'label' => __('what3words', 'swiftcomplete'),
                'value' => $what3words,
                'class' => 'short',
                'show' => $show_w3w,
                'type' => 'text',
                'wrapper_class' => 'form-field-wide',
            );
        }

        return $fields;
    }

}
