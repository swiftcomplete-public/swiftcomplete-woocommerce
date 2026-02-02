<?php
/**
 * WooCommerce Page Context
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Utilities;

defined('ABSPATH') || exit;

/**
 * Identifies WooCommerce-related pages/endpoints (non-checkout-type specific).
 */
class WooCommercePageContext
{
  /**
   * WooCommerce admin order edit screen (HPOS): /wp-admin/admin.php?page=wc-orders&action=edit&id=XX
   *
   * @return bool
   */
  public function is_admin_wc_orders_edit_page(): bool
  {
    if (!function_exists('is_admin') || !is_admin()) {
      return false;
    }

    global $pagenow;
    if (!isset($pagenow) || $pagenow !== 'admin.php') {
      return false;
    }

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;

    return $page === 'wc-orders' && $action === 'edit' && $id > 0;
  }

  /**
   * My Account edit address page: /my-account/edit-address/{billing|shipping}/
   *
   * If $type is provided, it must match the endpoint value (e.g. 'billing').
   *
   * @param string|null $type Optional address type to match (billing|shipping).
   * @return bool
   */
  public function is_my_account_edit_address_page(?string $type = null): bool
  {
    $is_account_page = function_exists('is_account_page') && is_account_page();
    $current_type = $is_account_page ? $this->get_my_account_edit_address_type() : null;

    return (bool) $current_type && ($type === null || $current_type === sanitize_key($type));
  }

  /**
   * Get the edit-address endpoint value (e.g. 'billing' or 'shipping').
   *
   * @return string|null
   */
  public function get_my_account_edit_address_type(): ?string
  {
    if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('edit-address')) {
      return null;
    }

    global $wp;
    $value = $wp->query_vars['edit-address'] ?? null;
    if (!is_string($value) || $value === '') {
      return null;
    }

    return sanitize_key($value);
  }

  /**
   * My Account order details page: /my-account/view-order/{order_id}/
   *
   * @return bool
   */
  public function is_my_account_view_order_page(): bool
  {
    if (!function_exists('is_account_page') || !is_account_page()) {
      return false;
    }

    if (!function_exists('is_wc_endpoint_url')) {
      return false;
    }

    return is_wc_endpoint_url('view-order');
  }

  /**
   * Get the order ID from /my-account/view-order/{order_id}/
   *
   * @return int
   */
  public function get_my_account_view_order_id(): int
  {
    if (!$this->is_my_account_view_order_page()) {
      return 0;
    }

    global $wp;
    return absint($wp->query_vars['view-order'] ?? 0);
  }


  // add function to check if the current page is the swiftcomplete 
  public function is_swiftcomplete_settings_page(): bool
  {
    if (!function_exists('is_admin') || !is_admin()) {
      return false;
    }

    global $pagenow;
    if (!isset($pagenow) || $pagenow !== 'options-general.php') {
      return false;
    }

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    return $page === 'swiftcomplete';
  }
}

