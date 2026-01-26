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
     * Default Field ID for address search (used by shortcode checkout)
     */
    public const ADDRESS_SEARCH_FIELD_ID = 'swiftcomplete-address-search';

    /**
     * Field ID for what3words (used by blocks checkout)
     */
    public const WHAT3WORDS_FIELD_ID = 'swiftcomplete-what3words';

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
     * Get billing meta key for blocks checkout (what3words)
     *
     * @return string
     */
    public static function get_blocks_billing_what3words_meta_key(): string
    {
        return self::WC_BILLING_META_PREFIX . self::WHAT3WORDS_FIELD_ID;
    }

    /**
     * Get shipping meta key for blocks checkout (what3words)
     *
     * @return string
     */
    public static function get_blocks_shipping_what3words_meta_key(): string
    {
        return self::WC_SHIPPING_META_PREFIX . self::WHAT3WORDS_FIELD_ID;
    }

    /**
     * Get billing meta key for what3words (non-blocks checkout)
     *
     * @return string
     */
    public static function get_billing_what3words_meta_key(): string
    {
        return '_billing_' . str_replace('-', '_', self::WHAT3WORDS_FIELD_ID);
    }

    /**
     * Get shipping meta key for what3words (non-blocks checkout)
     *
     * @return string
     */
    public static function get_shipping_what3words_meta_key(): string
    {
        return '_shipping_' . str_replace('-', '_', self::WHAT3WORDS_FIELD_ID);
    }
}
