<?php
/**
 * Checkout Type Detector
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Utilities;

defined('ABSPATH') || exit;

/**
 * Detects checkout type (blocks vs shortcode)
 */
class CheckoutTypeIdentifier
{
  /**
   * Check if WooCommerce Blocks is available
   *
   * @return bool
   */
  public function has_blocks(): bool
  {
    // Check if WooCommerce Blocks is available
    // Using string class name for better compatibility with older PHP versions
    return class_exists('\Automattic\WooCommerce\Blocks\Package');
  }

  /**
   * Check if current request is for checkout page
   *
   * @return bool
   */
  public function is_checkout(): bool
  {
    return function_exists('is_checkout') && is_checkout();
  }

  /**
   * Check if current checkout is blocks-based
   *
   * @return bool
   */
  public function is_blocks_checkout(): bool
  {
    if (!$this->is_checkout() || !$this->has_blocks()) {
      return false;
    }

    // Block theme (FSE) checkout
    if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
      return true;
    }

    // Classic theme with block-based checkout page
    $post = get_post();
    return $post && has_block('woocommerce/checkout', $post);
  }

  /**
   * Check if current checkout is shortcode-based
   *
   * @return bool
   */
  public function is_shortcode_checkout(): bool
  {
    $checkout_page_id = wc_get_page_id('checkout');
    if ($checkout_page_id <= 0) {
      return false;
    }
    if ($this->is_checkout()) {
      $post = get_post();
      if ($post && has_shortcode($post->post_content, 'woocommerce_checkout')) {
        return true;
      }
    }
    $is_wc_ajax = defined('DOING_AJAX') && DOING_AJAX &&
      isset($_REQUEST['wc-ajax']) &&
      in_array(sanitize_text_field(wp_unslash($_REQUEST['wc-ajax'])), array('checkout', 'update_order_review'), true);
    return ($is_wc_ajax || !$this->is_checkout()) && $this->checkout_page_has_shortcode($checkout_page_id);
  }

  /**
   * Check if the checkout page has the shortcode
   *
   * @param int $checkout_page_id Checkout page ID
   * @return bool
   */
  private function checkout_page_has_shortcode(int $checkout_page_id): bool
  {
    $checkout_page = get_post($checkout_page_id);
    return $checkout_page && has_shortcode($checkout_page->post_content, 'woocommerce_checkout');
  }
}
