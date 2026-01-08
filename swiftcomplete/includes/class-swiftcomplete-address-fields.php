<?php
/**
 * Address Fields class
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;

/**
 * Swiftcomplete Address Fields class
 */
class SwiftcompleteAddressFields
{

    /**
     * Instance of this class
     *
     * @var SwiftcompleteAddressFields
     */
    private static $instance = null;

    /**
     * Get instance of this class
     *
     * @return SwiftcompleteAddressFields
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

        add_filter('woocommerce_checkout_fields', array($this, 'override_address_fields'));
        add_filter('woocommerce_form_field', array($this, 'remove_optional_fields_label'), 10, 4);
    }

    /**
     * Override default address fields
     *
     * @param array $address_fields Address fields.
     * @return array
     */
    public function override_address_fields($address_fields)
    {
        // Safety check: Ensure $address_fields is an array
        if (!is_array($address_fields)) {
            return $address_fields;
        }

        $settings = get_option('swiftcomplete_settings');
        if (!is_array($settings)) {
            $settings = false;
        }
        $w3w_enabled = $settings === false || (!array_key_exists('w3w_enabled', $settings) || (array_key_exists('w3w_enabled', $settings) && $settings['w3w_enabled'] === true));

        if ($settings !== false && array_key_exists('billing_placeholder', $settings) && strlen($settings['billing_placeholder']) > 0) {
            $billing_placeholder = esc_attr($settings['billing_placeholder']);
        } else {
            $billing_placeholder = $w3w_enabled === true ? 'Type your address, what3words or postcode...' : 'Type your address or postcode...';
        }

        if ($settings !== false && array_key_exists('shipping_placeholder', $settings) && strlen($settings['shipping_placeholder']) > 0) {
            $shipping_placeholder = esc_attr($settings['shipping_placeholder']);
        } else {
            $shipping_placeholder = $w3w_enabled === true ? 'Type your address, what3words or postcode...' : 'Type your address or postcode...';
        }

        $billing_label = $settings !== false && array_key_exists('billing_label', $settings) && strlen($settings['billing_label']) > 0 ? esc_attr($settings['billing_label']) : 'Address Finder';
        $shipping_label = $settings !== false && array_key_exists('shipping_label', $settings) && strlen($settings['shipping_label']) > 0 ? esc_attr($settings['shipping_label']) : 'Address Finder';

        // Override default address fields
        $billing_address_fields = array(
            'billing_first_name',
            'billing_last_name',
            'billing_country',
            'billing_address_autocomplete',
            'billing_company',
            'billing_address_2',
            'billing_address_1',
            'billing_city',
            'billing_postcode',
            'billing_state',
        );

        foreach ($billing_address_fields as $key => $value) {
            if ($value == 'billing_company' && !array_key_exists('billing_company', $address_fields['billing'])) {
                unset($billing_address_fields[$key]);
            } elseif ($value == 'billing_address_2' && !array_key_exists('billing_address_2', $address_fields['billing'])) {
                unset($billing_address_fields[$key]);
            }
        }

        $shipping_address_fields = array(
            'shipping_first_name',
            'shipping_last_name',
            'shipping_country',
            'shipping_address_autocomplete',
            'shipping_company',
            'shipping_address_2',
            'shipping_address_1',
            'shipping_city',
            'shipping_postcode',
            'shipping_state',
        );

        foreach ($shipping_address_fields as $key => $value) {
            if ($value == 'shipping_company' && !array_key_exists('shipping_company', $address_fields['shipping'])) {
                unset($shipping_address_fields[$key]);
            } elseif ($value == 'shipping_address_2' && !array_key_exists('shipping_address_2', $address_fields['shipping'])) {
                unset($shipping_address_fields[$key]);
            }
        }

        $address_fields['billing']['billing_address_autocomplete'] = array(
            'label' => __($billing_label, 'woocommerce'),
            'required' => false,
            'class' => array('form-row-wide'),
            'type' => 'text',
            'id' => 'swiftcomplete_billing_address_autocomplete',
            'placeholder' => $billing_placeholder
        );

        $address_fields['shipping']['shipping_address_autocomplete'] = array(
            'label' => __($shipping_label, 'woocommerce'),
            'required' => false,
            'class' => array('form-row-wide'),
            'type' => 'text',
            'id' => 'swiftcomplete_shipping_address_autocomplete',
            'placeholder' => $shipping_placeholder
        );

        $priority = 0;

        foreach ($billing_address_fields as $key) {
            $address_fields['billing'][$key]['priority'] = $priority;
            $priority += 10;
        }

        $priority = 0;

        foreach ($shipping_address_fields as $key) {
            $address_fields['shipping'][$key]['priority'] = $priority;
            $priority += 10;
        }

        return $address_fields;
    }

    /**
     * Remove optional fields label
     *
     * @param string $field Field HTML.
     * @param string $key   Field key.
     * @param array  $args   Field arguments.
     * @param string $value  Field value.
     * @return string
     */
    public function remove_optional_fields_label($field, $key, $args, $value)
    {
        if (is_checkout() && !is_wc_endpoint_url() && ($key == 'billing_address_autocomplete' || $key == 'shipping_address_autocomplete')) {
            $optional = '&nbsp;<span class="optional">(' . esc_html__('optional', 'woocommerce') . ')</span>';
            $field = str_replace($optional, '', $field);
        }
        return $field;
    }
}

