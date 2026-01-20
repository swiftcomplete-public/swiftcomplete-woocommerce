<?php
/**
 * Order Meta Repository
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Order;

use Swiftcomplete\Contracts\OrderMetaRepositoryInterface;
use Swiftcomplete\Utilities\FieldConstants;

defined('ABSPATH') || exit;

/**
 * Repository for order meta operations
 */
class OrderMetaRepository implements OrderMetaRepositoryInterface
{
    /**
     * Save meta value to order
     *
     * @param int    $order_id Order ID
     * @param string $key      Meta key
     * @param string $value    Meta value
     * @return void
     */
    public function save(int $order_id, string $key, string $value): void
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $order->update_meta_data($key, $value);
        $order->save_meta_data();
    }

    /**
     * Get meta value from order
     *
     * @param int    $order_id Order ID
     * @param string $key      Meta key
     * @return string|null
     */
    public function get(int $order_id, string $key): ?string
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }

        $value = $order->get_meta($key, true);
        return $value ?: null;
    }

    /**
     * Check if meta key exists for order
     *
     * @param int    $order_id Order ID
     * @param string $key      Meta key
     * @return bool
     */
    public function exists(int $order_id, string $key): bool
    {
        $value = $this->get($order_id, $key);
        return null !== $value && '' !== $value;
    }

    /**
     * Get field values from order (billing and shipping)
     *
     * @param \WC_Order $order Order object
     * @return array{billing: string, shipping: string}
     */
    public function get_field_values_from_order(\WC_Order $order): array
    {
        // Try non-blocks meta keys first
        $billing_value = $order->get_meta(FieldConstants::get_billing_meta_key(), true);
        $shipping_value = $order->get_meta(FieldConstants::get_shipping_meta_key(), true);

        // Fallback to blocks meta keys
        if (empty($billing_value)) {
            $billing_value = $order->get_meta(FieldConstants::get_billing_blocks_meta_key(), true);
        }

        if (empty($shipping_value)) {
            $shipping_value = $order->get_meta(FieldConstants::get_shipping_blocks_meta_key(), true);
        }

        return array(
            'billing' => $billing_value ?: '',
            'shipping' => $shipping_value ?: '',
        );
    }
}
