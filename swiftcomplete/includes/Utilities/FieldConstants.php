<?php
/**
 * Field Constants
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Utilities;

defined('ABSPATH') || exit;

/**
 * Centralized constants for field IDs and meta keys
 */
class FieldConstants
{

    /**
     * Default Field ID for address search
     */
    public const ADDRESS_SEARCH_FIELD_ID = 'swiftcomplete-address-search';

    /**
     * Field ID for blocks checkout
     */
    public const ADDRESS_SEARCH_DATA_FIELD_NAME_SUFFIX = 'swiftcomplete/address-search';

    /**
     * WooCommerce Blocks meta key prefix for billing
     */
    public const WC_BILLING_META_PREFIX = '_wc_billing/';

    /**
     * WooCommerce Blocks meta key prefix for shipping
     */
    public const WC_SHIPPING_META_PREFIX = '_wc_shipping/';

    /**
     * Get billing meta key for non-blocks checkout
     *
     * @return string
     */
    public static function get_billing_meta_key(): string
    {
        return '_billing_' . self::ADDRESS_SEARCH_FIELD_ID;
    }

    /**
     * Get shipping meta key for non-blocks checkout
     *
     * @return string
     */
    public static function get_shipping_meta_key(): string
    {
        return '_shipping_' . self::ADDRESS_SEARCH_FIELD_ID;
    }

    /**
     * Get billing meta key for blocks checkout
     *
     * @return string
     */
    public static function get_billing_blocks_meta_key(): string
    {
        return self::WC_BILLING_META_PREFIX . self::ADDRESS_SEARCH_DATA_FIELD_NAME_SUFFIX;
    }

    /**
     * Get shipping meta key for blocks checkout
     *
     * @return string
     */
    public static function get_shipping_blocks_meta_key(): string
    {
        return self::WC_SHIPPING_META_PREFIX . self::ADDRESS_SEARCH_DATA_FIELD_NAME_SUFFIX;
    }
}
