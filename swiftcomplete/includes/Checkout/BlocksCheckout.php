<?php
/**
 * Blocks Checkout Strategy
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
 * Strategy for handling blocks-based checkout
 */
class BlocksCheckout implements CheckoutInterface
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
     * @param CheckoutTypeIdentifier         $checkout_type_identifier   Checkout type detector
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
        // Blocks checkout handles fields via JavaScript, not PHP filters
        return $fields;
    }

    /**
     * Load Swiftcomplete data from request and save to order
     *
     * @param \WC_Order $order Order object
     * @param array     $data  Extension data
     * @return void
     */
    public function save_extension_data_to_order(\WC_Order $order, array $data): void
    {
        if (!isset($data['swiftcomplete']) || !is_array($data['swiftcomplete'])) {
            return;
        }
        $swiftcomplete_data = $data['swiftcomplete'];

        $billing_value = $this->extract_sanitized_value($swiftcomplete_data, 'billing_what3words');
        $shipping_value = $this->extract_sanitized_value($swiftcomplete_data, 'shipping_what3words');
        if ($billing_value) {
            $this->meta_repository->save($order->get_id(), '_billing_' . $this->get_field_id(), $billing_value);
            $this->meta_repository->save($order->get_id(), FieldConstants::get_blocks_billing_what3words_meta_key(), $billing_value);
        }

        if ($shipping_value) {
            $this->meta_repository->save($order->get_id(), '_shipping_' . $this->get_field_id(), $shipping_value);
            $this->meta_repository->save($order->get_id(), FieldConstants::get_blocks_shipping_what3words_meta_key(), $shipping_value);
        }
    }

    public function extract_extension_data_from_request(\WP_REST_Request $request): array
    {
        $extension_data = $request->get_param('extensions');
        if (!is_array($extension_data)) {
            $extension_data = array();
        }
        if (!isset($extension_data['swiftcomplete'])) {
            $raw_body = $request->get_body();
            if (!empty($raw_body)) {
                $body_data = json_decode($raw_body, true);
                if ($body_data && isset($body_data['extensions']['swiftcomplete'])) {
                    $extension_data['swiftcomplete'] = $body_data['extensions']['swiftcomplete'];
                    $request->set_param('extensions', $extension_data);
                }
            }
        }
        return $extension_data;
    }

    /**
     * Extract and sanitize a value from swiftcomplete data
     *
     * @param array  $swiftcomplete_data Swiftcomplete data array
     * @param string $key              Key to extract
     * @return string Sanitized value or empty string
     */
    private function extract_sanitized_value(array $swiftcomplete_data, string $key): string
    {
        if (empty($swiftcomplete_data) || !isset($swiftcomplete_data[$key])) {
            return '';
        }
        return sanitize_text_field($swiftcomplete_data[$key]);
    }

    /**
     * Get the field ID for this strategy (what3words)
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
        return $this->checkout_type_identifier->is_blocks_checkout();
    }
}
