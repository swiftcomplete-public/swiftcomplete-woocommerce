<?php
/**
 * Shortcode Checkout Strategy
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Checkout;

use Swiftcomplete\Contracts\CheckoutInterface;
use Swiftcomplete\Contracts\OrderMetaRepositoryInterface;
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
     * Settings manager
     *
     * @var SettingsManager
     */
    private $settings_manager;

    /**
     * Constructor
     *
     * @param OrderMetaRepositoryInterface $meta_repository Order meta repository
     * @param CheckoutTypeIdentifier        $checkout_type_identifier   Checkout type detector
     */
    public function __construct(
        OrderMetaRepositoryInterface $meta_repository,
        CheckoutTypeIdentifier $checkout_type_identifier,
        SettingsManager $settings_manager
    ) {
        $this->meta_repository = $meta_repository;
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
        $field_ids = $this->get_field_ids();
        $field_suffixes = array(
            'first_name',
            'last_name',
            'country',
            $field_ids['search_field'],
            'company',
            'address_2',
            'address_1',
            'city',
            'postcode',
            'state',
            $field_ids['what3words'],
        );
        $optional_suffixes = array('company', 'address_2');
        $label = $this->settings_manager->get_setting("{$type}_label", 'Address Finder');
        $placeholder = $this->settings_manager->get_setting("{$type}_placeholder", 'Type your address or postcode...');
        $address_search_field = array(
            'label' => __($label, 'woocommerce'),
            'required' => false,
            'class' => array('form-row-wide'),
            'type' => 'text',
            'placeholder' => $placeholder,
        );

        $fields = array();
        foreach ($field_suffixes as $suffix) {
            $field_name = $type . '_' . $suffix;
            if (in_array($suffix, $optional_suffixes, true) && !array_key_exists($field_name, $address_fields[$type])) {
                continue;
            }
            $fields[] = $field_name;
        }

        $field_id = "{$type}_" . $field_ids['search_field'];
        $address_fields[$type][$field_id] = array_merge(
            $address_search_field,
            array('id' => $field_id)
        );

        $w3w_enabled = $this->settings_manager->get_setting('w3w_enabled');
        if ($w3w_enabled) {
            $field_id = "{$type}_" . $field_ids['what3words'];
            if (!isset($address_fields[$type][$field_id])) {
                $address_fields[$type][$field_id] = array_merge(
                    array(
                        'label' => __('what3words address', 'woocommerce'),
                        'required' => false,
                        'class' => array('form-row-wide'),
                        'type' => 'text',
                    ),
                    array('id' => $field_id)
                );
            }
        }

        $priority = 0;
        foreach ($fields as $field_name) {
            if (isset($address_fields[$type][$field_name])) {
                $address_fields[$type][$field_name]['priority'] = $priority;
                $priority += 10;
            }
        }

        return $address_fields;
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
        $field_ids = $this->get_field_ids();
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
            $this->meta_repository->save($order->get_id(), FieldConstants::get_billing_what3words_meta_key(), $billing_value);
        }
        if ($shipping_value) {
            $this->meta_repository->save($order->get_id(), FieldConstants::get_shipping_what3words_meta_key(), $shipping_value);
        }

        // Save to customer meta if user is logged in
        $customer_id = $order->get_customer_id();
        if ($customer_id > 0) {
            if ($billing_value) {
                update_user_meta($customer_id, '_billing_' . $field_ids['what3words'], $billing_value);
            }
            if ($shipping_value) {
                update_user_meta($customer_id, '_shipping_' . $field_ids['what3words'], $shipping_value);
            }
        }
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
        $field_ids = $this->get_field_ids();
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
        if (!is_user_logged_in()) {
            return null;
        }

        $field_ids = $this->get_field_ids();
        $what3words_field_id = $field_ids['what3words'];
        $billing_key = 'billing_' . $what3words_field_id;
        $shipping_key = 'shipping_' . $what3words_field_id;

        $user_id = get_current_user_id();
        $value = null;

        if ($field_key === $billing_key) {
            $value = get_user_meta($user_id, '_billing_' . $what3words_field_id, true);
        } elseif ($field_key === $shipping_key) {
            $value = get_user_meta($user_id, '_shipping_' . $what3words_field_id, true);
        }

        return $value ? sanitize_text_field($value) : null;
    }

    /**
     * Get the field ID for this strategy
     *
     * @return string
     */
    public function get_field_ids(): array
    {
        return array(
            'what3words' => str_replace('-', '_', FieldConstants::WHAT3WORDS_FIELD_ID),
            'search_field' => str_replace('-', '_', FieldConstants::ADDRESS_SEARCH_FIELD_ID),
        );
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
