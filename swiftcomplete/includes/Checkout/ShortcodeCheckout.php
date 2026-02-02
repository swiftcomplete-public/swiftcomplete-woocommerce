<?php
/**
 * Shortcode Checkout Strategy
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Checkout;

use Swiftcomplete\Contracts\CheckoutInterface;
use Swiftcomplete\Customer\CustomerMeta;
use Swiftcomplete\Order\OrderMeta;
use Swiftcomplete\Utilities\CheckoutTypeIdentifier;
use Swiftcomplete\Utilities\FieldConstants;
use Swiftcomplete\Settings\SettingsManager;

defined('ABSPATH') || exit;

/**
 * Strategy for handling shortcode-based checkout
 */
class ShortcodeCheckout implements CheckoutInterface
{
    /**
     * Order meta
     *
     * @var OrderMeta
     */
    private $order_meta;

    /**
     * Customer what3words meta
     *
     * @var CustomerMeta
     */
    private $customer_meta;

    /**
     * Checkout type detector
     *
     * @var CheckoutTypeIdentifier
     */
    private $checkout_type_identifier;

    /**
     * Settings manager
     *
     * @var SettingsManager
     */
    private $settings_manager;

    /**
     * Constructor
     *
     * @param OrderMeta              $order_meta               Order meta
     * @param CustomerMeta  $customer_meta Customer what3words meta
     * @param CheckoutTypeIdentifier $checkout_type_identifier Checkout type detector
     * @param SettingsManager        $settings_manager        Settings manager
     */
    public function __construct(
        OrderMeta $order_meta,
        CustomerMeta $customer_meta,
        CheckoutTypeIdentifier $checkout_type_identifier,
        SettingsManager $settings_manager
    ) {
        $this->order_meta = $order_meta;
        $this->customer_meta = $customer_meta;
        $this->checkout_type_identifier = $checkout_type_identifier;
        $this->settings_manager = $settings_manager;
    }

    /**
     * Register checkout fields
     *
     * @param array $fields Existing checkout fields
     * @return array Modified checkout fields
     */
    public function register_fields(array $fields): array
    {
        if (!$this->applies() || !is_array($fields)) {
            return $fields;
        }

        foreach (array('billing', 'shipping') as $type) {
            if (!isset($fields[$type])) {
                continue;
            }
            $fields = $this->process_address_type_fields($fields, $type);
        }

        return $fields;
    }

    /**
     * Process address type fields (billing or shipping)
     *
     * @param array  $address_fields Address fields
     * @param string $type           'billing' or 'shipping'
     * @return array
     */
    private function process_address_type_fields(array $address_fields, string $type): array
    {
        $search = $this->build_search_field_definition($type);
        $w3w = $this->build_what3words_field_definition($type);
        $w3w_enabled = (bool) $this->settings_manager->get_setting('w3w_enabled');

        $has_type = !empty($address_fields[$type]) && is_array($address_fields[$type]);
        $address_fields[$type] = $has_type
            ? $this->merge_search_field_into_group($address_fields[$type], $type, $search, $w3w, $w3w_enabled)
            : $this->build_initial_group($search, $w3w, $w3w_enabled);

        $this->assign_field_priorities($address_fields[$type]);

        return $address_fields;
    }

    /**
     * Build search field key and definition for an address type.
     *
     * @param string $type 'billing' or 'shipping'
     * @return array{key: string, field: array}
     */
    private function build_search_field_definition(string $type): array
    {
        $field_ids = FieldConstants::get_field_ids();
        $key = $type . '_' . $field_ids['search_field'];
        $label = $this->settings_manager->get_setting("{$type}_label", 'Address Finder');
        $placeholder = $this->settings_manager->get_setting("{$type}_placeholder", 'Type your address or postcode...');

        return array(
            'key' => $key,
            'field' => array_merge(
                array(
                    'label' => __($label, 'swiftcomplete'),
                    'required' => false,
                    'class' => array('form-row-wide'),
                    'type' => 'text',
                    'placeholder' => $placeholder,
                ),
                array('id' => $key)
            ),
        );
    }

    /**
     * Build what3words field key and definition for an address type.
     *
     * @param string $type 'billing' or 'shipping'
     * @return array{key: string, field: array}
     */
    private function build_what3words_field_definition(string $type): array
    {
        $field_ids = FieldConstants::get_field_ids();
        $key = $type . '_' . $field_ids['what3words'];

        return array(
            'key' => $key,
            'field' => array_merge(
                array(
                    'label' => __('what3words address', 'swiftcomplete'),
                    'required' => false,
                    'class' => array('form-row-wide'),
                    'type' => 'text',
                ),
                array('id' => $key)
            ),
        );
    }

    /**
     * Build initial address group with only search (and optionally what3words) field.
     *
     * @param array{key: string, field: array} $search
     * @param array{key: string, field: array} $w3w
     * @param bool $w3w_enabled
     * @return array<string, array>
     */
    private function build_initial_group(array $search, array $w3w, bool $w3w_enabled): array
    {
        $group = array($search['key'] => $search['field']);
        if ($w3w_enabled) {
            $group[$w3w['key']] = $w3w['field'];
        }
        return $group;
    }

    /**
     * Merge search field (and optionally what3words) into existing group, inserting search before address_1.
     *
     * @param array<string, array> $existing
     * @param string $type
     * @param array{key: string, field: array} $search
     * @param array{key: string, field: array} $w3w
     * @param bool $w3w_enabled
     * @return array<string, array>
     */
    private function merge_search_field_into_group(array $existing, string $type, array $search, array $w3w, bool $w3w_enabled): array
    {
        $search_value = isset($existing[$search['key']]) ? $existing[$search['key']] : $search['field'];
        $new_group = $this->rebuild_group_with_search_before_anchor(
            $existing,
            $type . '_address_1',
            $search['key'],
            $search_value
        );
        $this->ensure_what3words_in_group($new_group, $existing, $w3w, $w3w_enabled);
        return $new_group;
    }

    /**
     * Rebuild address group with search field inserted before anchor key; preserves order and unknown keys.
     *
     * @param array<string, array> $existing
     * @param string $anchor_key
     * @param string $search_key
     * @param array $search_value
     * @return array<string, array>
     */
    private function rebuild_group_with_search_before_anchor(array $existing, string $anchor_key, string $search_key, array $search_value): array
    {
        $new_group = array();
        $inserted = false;
        foreach ($existing as $key => $value) {
            if (!$inserted && $key === $anchor_key) {
                $new_group[$search_key] = $search_value;
                $inserted = true;
            }
            if ($key !== $search_key) {
                $new_group[$key] = $value;
            }
        }
        if (!$inserted) {
            $new_group[$search_key] = $search_value;
        }
        return $new_group;
    }

    /**
     * Add what3words field to group when enabled and not already present.
     *
     * @param array<string, array> $group Modified by reference
     * @param array<string, array> $existing
     * @param array{key: string, field: array} $w3w
     * @param bool $w3w_enabled
     * @return void
     */
    private function ensure_what3words_in_group(array &$group, array $existing, array $w3w, bool $w3w_enabled): void
    {
        if (!$w3w_enabled || isset($group[$w3w['key']])) {
            return;
        }
        $group[$w3w['key']] = isset($existing[$w3w['key']]) ? $existing[$w3w['key']] : $w3w['field'];
    }

    /**
     * Assign incremental priorities to fields in a group (by reference).
     *
     * @param array<string, array> $group
     * @return void
     */
    private function assign_field_priorities(array &$group): void
    {
        $priority = 0;
        foreach ($group as $key => $args) {
            if (is_array($args)) {
                $group[$key]['priority'] = $priority;
                $priority += 10;
            }
        }
    }

    /**
     * Save field value to order
     *
     * @param \WC_Order $order Order object
     * @param array     $data  Extension data
     * @return void
     */
    public function save_extension_data_to_order(\WC_Order $order, array $data): void
    {
        $field_ids = FieldConstants::get_field_ids();
        $billing_key = 'billing_' . $field_ids['what3words'];
        $shipping_key = 'shipping_' . $field_ids['what3words'];

        // Get values from POST
        $billing_value = isset($_POST[$billing_key])
            ? sanitize_text_field(wp_unslash($_POST[$billing_key]))
            : '';
        $shipping_value = isset($_POST[$shipping_key])
            ? sanitize_text_field(wp_unslash($_POST[$shipping_key]))
            : '';

        // Check if "Ship to different address" toggle is enabled
        $ship_to_different_address = isset($_POST['ship_to_different_address']) && $_POST['ship_to_different_address'];

        if (!$ship_to_different_address && $billing_value) {
            // Shipping address is same as billing, so use billing value for shipping
            $shipping_value = $billing_value;
        }

        // Save to order meta
        if ($billing_value) {
            $this->order_meta->save($order->get_id(), FieldConstants::get_billing_what3words_meta_key(), $billing_value);
        }
        if ($shipping_value) {
            $this->order_meta->save($order->get_id(), FieldConstants::get_shipping_what3words_meta_key(), $shipping_value);
        }

        $customer_id = $order->get_customer_id();
        $this->customer_meta->save_what3words($customer_id, $billing_value, $shipping_value);
    }

    /**
     * Remove optional fields label
     *
     * @param string $field Field HTML
     * @param string $key   Field key
     * @param array  $args  Field arguments
     * @param string $value Field value
     * @return string
     */
    public function remove_optional_fields_label(string $field, string $key, array $args, string $value): string
    {
        $field_ids = FieldConstants::get_field_ids();
        if (
            is_checkout() &&
            !is_wc_endpoint_url() &&
            (strpos($key, 'billing_') === 0 || strpos($key, 'shipping_') === 0) &&
            (strpos($key, $field_ids['what3words']) !== false || strpos($key, $field_ids['search_field']) !== false)
        ) {
            $optional = '&nbsp;<span class="optional">(' . esc_html__('optional', 'woocommerce') . ')</span>';
            $field = str_replace($optional, '', $field);
        }
        return $field;
    }

    /**
     * Get customer what3words value for a field
     * Used by woocommerce_checkout_get_value filter
     *
     * @param string $field_key Field key (e.g., 'billing_swiftcomplete_what3words')
     * @return string|null Customer value or null
     */
    public function get_customer_field_value(string $field_key): ?string
    {
        $field_ids = FieldConstants::get_field_ids();
        $billing_key = 'billing_' . $field_ids['what3words'];
        $shipping_key = 'shipping_' . $field_ids['what3words'];

        $values = $this->customer_meta->get_current_user_what3words();

        if ($field_key === $billing_key && $values['billing'] !== '') {
            return $values['billing'];
        }
        if ($field_key === $shipping_key && $values['shipping'] !== '') {
            return $values['shipping'];
        }

        return null;
    }

    /**
     * Check if this strategy applies to current checkout
     *
     * @return bool
     */
    public function applies(): bool
    {
        return $this->checkout_type_identifier->is_shortcode_checkout();
    }
}
