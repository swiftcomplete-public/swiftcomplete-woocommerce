<?php
/**
 * Admin class
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;

/**
 * Swiftcomplete Admin class
 */
class SwiftcompleteAdmin
{

  /**
   * Instance of this class
   *
   * @var SwiftcompleteAdmin
   */
  private static $instance = null;

  /**
   * Get instance of this class
   *
   * @return SwiftcompleteAdmin
   */
  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Constructor
   */
  private function __construct()
  {
    // Safety check: Only register hooks if WooCommerce is active
    if (!class_exists('WooCommerce')) {
      return;
    }

    add_filter('woocommerce_admin_billing_fields', array($this, 'add_billing_field'), 10, 2);
    add_filter('woocommerce_admin_shipping_fields', array($this, 'add_shipping_field'), 10, 2);
  }

  /**
   * Add billing field to admin order page
   *
   * @param array $fields Order fields.
   * @return array
   */
  public function add_billing_field($fields)
  {
    return $this->add_address_field($fields, 'billing');
  }

  /**
   * Add shipping field to admin order page
   *
   * @param array $fields Order fields.
   * @return array
   */
  public function add_shipping_field($fields)
  {
    return $this->add_address_field($fields, 'shipping');
  }

  /**
   * Add address field to admin order page
   *
   * @param array  $fields Order fields.
   * @param string $type   Address type (billing or shipping).
   * @return array
   */
  private function add_address_field($fields, $type)
  {
    // Safety checks
    if (!is_array($fields) || !function_exists('get_option') || !function_exists('wp_enqueue_script')) {
      return $fields;
    }

    $settings = get_option('swiftcomplete_settings');
    if (!is_array($settings)) {
      $settings = false;
    }

    $api_key = '';

    if ($settings !== false && array_key_exists('api_key', $settings) && strlen($settings['api_key']) > 0) {
      $api_key = $settings['api_key'];
    }

    wp_enqueue_script('swiftcomplete_script', 'https://assets.swiftcomplete.com/js/swiftlookup.js', array(), Swiftcomplete::VERSION, true);

    $addressfinder_path = Swiftcomplete::get_plugin_path() . 'public/js/addressfinder.js';
    if (file_exists($addressfinder_path)) {
      wp_enqueue_script('swiftcomplete_launch', Swiftcomplete::get_plugin_url() . 'public/js/addressfinder.js', array('jquery'), Swiftcomplete::VERSION, true);

      $hide_fields = $settings !== false && array_key_exists('hide_fields', $settings) && $settings['hide_fields'] ? 'true' : 'false';
      $bias_lat_lon = $settings !== false && array_key_exists('bias_towards_lat_lon', $settings) ? esc_js($settings['bias_towards_lat_lon']) : '';

      if ($type === 'billing') {
        $placeholder = $settings !== false && array_key_exists('billing_placeholder', $settings) ? esc_js($settings['billing_placeholder']) : '';
      } else {
        $placeholder = $settings !== false && array_key_exists('shipping_placeholder', $settings) ? esc_js($settings['shipping_placeholder']) : '';
      }

      $state_counties = $settings !== false && array_key_exists('state_counties_enabled', $settings) ? esc_js($settings['state_counties_enabled']) : '';

      if (function_exists('wp_add_inline_script')) {
        wp_add_inline_script('swiftcomplete_launch', sprintf('launchAdminAddressLookup("%s", "%s", "address", "%s", "%s", "%s", "%s");', esc_js($type), esc_js($api_key), $hide_fields, $bias_lat_lon, $placeholder, $state_counties));
      }
    }

    $position = array_search('company', array_keys($fields));

    if ($position === false) {
      $position = array_search('address_1', array_keys($fields));
    }

    $search_field = array(
      'label' => __('Address Finder', 'text-domain'),
      'class' => 'form-field-wide',
      'show' => true,
      'type' => 'text',
      'id' => 'swiftcomplete_' . $type . '_address_autocomplete'
    );

    if ($position === false) {
      $fields['swiftcomplete_' . $type . '_address_autocomplete'] = $search_field;
    } else {
      $array = array_slice($fields, 0, $position, true);
      $array['swiftcomplete_' . $type . '_address_autocomplete'] = $search_field;
      $fields = $array + array_slice($fields, $position, null, true);
    }

    return $fields;
  }
}

