<?php
/**
 * Checkout Strategy Interface
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Contracts;

defined('ABSPATH') || exit;

/**
 * Interface for checkout strategies (blocks vs shortcode)
 */
interface CheckoutInterface
{
    /**
     * Save field value to order
     *
     * @param \WC_Order $order Order object
     * @param \WP_REST_Request $request Request object
     * @return \WC_Order Modified order object
     */
    public function save_extension_data_to_order(\WC_Order $order, \WP_REST_Request $request): \WC_Order;

    /**
     * Get the field ID for this strategy
     *
     * @return string
     */
    public function get_field_id(): string;

    /**
     * Check if this strategy applies to current checkout
     *
     * @return bool
     */
    public function applies(): bool;
}
