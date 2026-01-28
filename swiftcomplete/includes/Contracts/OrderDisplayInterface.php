<?php
/**
 * Order Display Interface
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Contracts;

defined('ABSPATH') || exit;

/**
 * Interface for order display handlers
 */
interface OrderDisplayInterface
{
    /**
     * Display field on order page (customer view)
     *
     * @param \WC_Order $order Order object
     * @return void
     */
    public function display_on_order(\WC_Order $order): void;

    /**
     * Display field on confirmation page
     *
     * @param int $order_id Order ID
     * @return void
     */
    public function display_on_confirmation(int $order_id): void;
}
