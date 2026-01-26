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
     * Constructor
     *
     * @param OrderMetaRepositoryInterface $meta_repository Order meta repository
     * @param CheckoutTypeIdentifier        $checkout_type_identifier   Checkout type detector
     */
    public function __construct(
        OrderMetaRepositoryInterface $meta_repository,
        CheckoutTypeIdentifier $checkout_type_identifier
    ) {
        $this->meta_repository = $meta_repository;
        $this->checkout_type_identifier = $checkout_type_identifier;
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
        $field_suffixes = array(
            'first_name',
            'last_name',
            'country',
            $this->get_field_id(),
            'company',
            'address_2',
            'address_1',
            'city',
            'postcode',
            'state',
        );
        $optional_suffixes = array('company', 'address_2');
        $additional_checkout_field = array(
            'label' => __('Address Finder', 'woocommerce'),
            'required' => false,
            'class' => array('form-row-wide'),
            'type' => 'text',
            'placeholder' => 'Type your address or postcode...',
        );

        $fields = array();
        foreach ($field_suffixes as $suffix) {
            $field_name = $type . '_' . $suffix;
            if (in_array($suffix, $optional_suffixes, true) && !array_key_exists($field_name, $address_fields[$type])) {
                continue;
            }
            $fields[] = $field_name;
        }

        $field_id = "{$type}_" . $this->get_field_id();
        $address_fields[$type][$field_id] = array_merge(
            $additional_checkout_field,
            array('id' => $field_id)
        );

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
        $billing_key = 'billing_' . $this->get_field_id();
        $shipping_key = 'shipping_' . $this->get_field_id();

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
        if (
            is_checkout() &&
            !is_wc_endpoint_url() &&
            (strpos($key, 'billing_') === 0 || strpos($key, 'shipping_') === 0) &&
            strpos($key, $this->get_field_id()) !== false
        ) {
            $optional = '&nbsp;<span class="optional">(' . esc_html__('optional', 'woocommerce') . ')</span>';
            $field = str_replace($optional, '', $field);
        }
        return $field;
    }

    /**
     * Get the field ID for this strategy
     *
     * @return string
     */
    public function get_field_id(): string
    {
        return str_replace('-', '_', FieldConstants::WHAT3WORDS_FIELD_ID);
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
