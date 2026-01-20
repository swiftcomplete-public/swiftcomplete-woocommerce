<?php
/**
 * Order Meta Repository Interface
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Contracts;

defined('ABSPATH') || exit;

/**
 * Interface for order meta operations
 */
interface OrderMetaRepositoryInterface
{
    /**
     * Save meta value to order
     *
     * @param int    $order_id Order ID
     * @param string $key      Meta key
     * @param string $value    Meta value
     * @return void
     */
    public function save(int $order_id, string $key, string $value): void;

    /**
     * Get meta value from order
     *
     * @param int    $order_id Order ID
     * @param string $key      Meta key
     * @return string|null
     */
    public function get(int $order_id, string $key): ?string;

    /**
     * Check if meta key exists for order
     *
     * @param int    $order_id Order ID
     * @param string $key      Meta key
     * @return bool
     */
    public function exists(int $order_id, string $key): bool;
}
