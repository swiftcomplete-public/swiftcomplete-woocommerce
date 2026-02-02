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
     * @param array $data Extension data
     * @return void
     */
    public function save_extension_data_to_order(\WC_Order $order, array $data): void;

    /**
     * Check if this strategy applies to current checkout
     *
     * @return bool
     */
    public function applies(): bool;
}
