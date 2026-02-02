<?php
/**
 * Customer What3words Meta
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Customer;

use Swiftcomplete\Utilities\FieldConstants;

defined('ABSPATH') || exit;

/**
 * Handles customer (user) meta for what3words billing and shipping values
 */
class CustomerMeta
{
    /**
     * Save what3words values to customer user meta
     *
     * @param int    $customer_id   User ID (customer)
     * @param string $billing_value Billing what3words value
     * @param string $shipping_value Shipping what3words value
     * @return void
     */
    public function save_what3words(int $customer_id, string $billing_value, string $shipping_value): void
    {
        if ($customer_id <= 0) {
            return;
        }

        $billing_key = FieldConstants::get_billing_what3words_meta_key();
        $shipping_key = FieldConstants::get_shipping_what3words_meta_key();

        if ($billing_value !== '') {
            update_user_meta($customer_id, $billing_key, $billing_value);
        }
        if ($shipping_value !== '') {
            update_user_meta($customer_id, $shipping_key, $shipping_value);
        }
    }

    /**
     * Get current logged-in user's what3words values
     *
     * @return array{billing: string, shipping: string}
     */
    public function get_current_user_what3words(): array
    {
        $billing_value = '';
        $shipping_value = '';

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $billing_key = FieldConstants::get_billing_what3words_meta_key();
            $shipping_key = FieldConstants::get_shipping_what3words_meta_key();

            $billing_value = get_user_meta($user_id, $billing_key, true);
            $shipping_value = get_user_meta($user_id, $shipping_key, true);

            $billing_value = $billing_value ? sanitize_text_field($billing_value) : '';
            $shipping_value = $shipping_value ? sanitize_text_field($shipping_value) : '';
        }

        return array(
            'billing' => $billing_value,
            'shipping' => $shipping_value,
        );
    }
}
