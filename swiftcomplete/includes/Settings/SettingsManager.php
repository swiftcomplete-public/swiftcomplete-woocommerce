<?php
/**
 * Settings Manager
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Settings;

use Swiftcomplete\Core\HookManager;

defined('ABSPATH') || exit;

/**
 * Manages plugin settings
 */
class SettingsManager
{
  /**
   * Settings option name
   *
   * @var string
   */
  private const SETTINGS_OPTION = 'swiftcomplete_settings';

  /**
   * Settings page slug
   *
   * @var string
   */
  private const SETTINGS_PAGE = 'swiftcomplete';

  /**
   * Hook manager
   *
   * @var HookManager
   */
  private $hook_manager;

  /**
   * Constructor
   *
   * @param HookManager $hook_manager Hook manager
   */
  public function __construct(HookManager $hook_manager)
  {
    $this->hook_manager = $hook_manager;
    $this->register_hooks();
  }

  /**
   * Register WordPress hooks
   *
   * @return void
   */
  private function register_hooks(): void
  {
    $this->hook_manager->register_action('admin_init', array($this, 'register_settings'), 10, 0);
    $this->hook_manager->register_action('admin_menu', array($this, 'add_settings_menu'), 10, 0);
    $this->hook_manager->register_filter('plugin_action_links_' . plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE), array($this, 'add_plugin_settings_link'), 10, 1);
  }

  /**
   * Register settings
   *
   * @return void
   */
  public function register_settings(): void
  {
    register_setting(
      self::SETTINGS_OPTION,
      self::SETTINGS_OPTION,
      array($this, 'load_settings')
    );

    add_settings_section('swiftcomplete_api_settings', 'Swiftcomplete Settings', array($this, 'render_help_text'), self::SETTINGS_PAGE);
    add_settings_field('swiftcomplete_api_key', 'API Key(required)', array($this, 'render_api_key_field'), self::SETTINGS_PAGE, 'swiftcomplete_api_settings');
    add_settings_field('swiftcomplete_w3w_enabled', 'Enable what3words?', array($this, 'render_w3w_enabled_field'), self::SETTINGS_PAGE, 'swiftcomplete_api_settings');
    add_settings_field('swiftcomplete_hide_fields', 'Hide address fields until an address is selected?', array($this, 'render_hide_fields_field'), self::SETTINGS_PAGE, 'swiftcomplete_api_settings');
    add_settings_field('swiftcomplete_state_counties_enabled', 'Return states / UK counties?', array($this, 'render_state_counties_enabled_field'), self::SETTINGS_PAGE, 'swiftcomplete_api_settings');

    add_settings_section('swiftcomplete_country_settings', 'Location biasing', array($this, 'render_country_header'), self::SETTINGS_PAGE);
    add_settings_field('swiftcomplete_bias_towards', 'Prioritise addresses near place', array($this, 'render_bias_towards_field'), self::SETTINGS_PAGE, 'swiftcomplete_country_settings');

    add_settings_section('swiftcomplete_text_settings', 'Search field labels and placeholders', array($this, 'render_text_header'), self::SETTINGS_PAGE);
    add_settings_field('swiftcomplete_billing_label', 'Billing field label', array($this, 'render_billing_label_field'), self::SETTINGS_PAGE, 'swiftcomplete_text_settings');
    add_settings_field('swiftcomplete_billing_placeholder', 'Billing field placeholder text', array($this, 'render_billing_placeholder_field'), self::SETTINGS_PAGE, 'swiftcomplete_text_settings');
    add_settings_field('swiftcomplete_shipping_label', 'Shipping field label', array($this, 'render_shipping_label_field'), self::SETTINGS_PAGE, 'swiftcomplete_text_settings');
    add_settings_field('swiftcomplete_shipping_placeholder', 'Shipping field placeholder text', array($this, 'render_shipping_placeholder_field'), self::SETTINGS_PAGE, 'swiftcomplete_text_settings');
  }

  /**
   * Validate settings
   *
   * @param array $input Input data
   * @return array
   */
  public function load_settings(array $input): array
  {
    // Get existing settings to preserve values when checkboxes are unchecked
    $existing = get_option(self::SETTINGS_OPTION, array());
    if (!is_array($existing)) {
      $existing = array();
    }

    $validated = array();
    $validated['api_key'] = isset($input['api_key']) ? sanitize_text_field(wp_unslash($input['api_key'])) : '';
    $validated['billing_label'] = isset($input['billing_label']) ? sanitize_text_field(wp_unslash($input['billing_label'])) : '';
    $validated['billing_placeholder'] = isset($input['billing_placeholder']) ? sanitize_text_field(wp_unslash($input['billing_placeholder'])) : '';
    $validated['shipping_label'] = isset($input['shipping_label']) ? sanitize_text_field(wp_unslash($input['shipping_label'])) : '';
    $validated['shipping_placeholder'] = isset($input['shipping_placeholder']) ? sanitize_text_field(wp_unslash($input['shipping_placeholder'])) : '';
    $validated['bias_towards'] = isset($input['bias_towards']) ? sanitize_text_field(wp_unslash($input['bias_towards'])) : '';
    $validated['bias_towards_lat_lon'] = isset($input['bias_towards_lat_lon']) ? sanitize_text_field(wp_unslash($input['bias_towards_lat_lon'])) : '';
    // Checkboxes: if key exists in input, it's checked (value is a string from form, not boolean)
    // If key doesn't exist, checkbox was unchecked, so set to false
    $validated['w3w_enabled'] = isset($input['w3w_enabled']);
    $validated['state_counties_enabled'] = isset($input['state_counties_enabled']);
    $validated['hide_fields'] = isset($input['hide_fields']);
    return $validated;
  }

  /**
   * Check if Swiftcomplete is enabled
   *
   * @return bool
   */
  public function is_enabled(): bool
  {
    $settings = get_option(self::SETTINGS_OPTION);
    return $settings !== false && array_key_exists('api_key', $settings) && strlen($settings['api_key']) > 0;
  }

  /**
   * Add settings menu
   *
   * @return void
   */
  public function add_settings_menu(): void
  {
    add_submenu_page('options-general.php', 'Swiftcomplete', 'Swiftcomplete', 'manage_options', self::SETTINGS_PAGE, array($this, 'render_settings_page'));
  }

  /**
   * Add settings link to plugin actions
   *
   * @param array $actions Plugin actions
   * @return array
   */
  public function add_plugin_settings_link(array $actions): array
  {
    $mylinks = array(
      '<a href="' . admin_url('options-general.php?page=' . self::SETTINGS_PAGE) . '">' . __('Settings') . '</a>'
    );
    return array_merge($mylinks, $actions);
  }

  /**
   * Render settings page
   *
   * @return void
   */
  public function render_settings_page(): void
  {
    \Swiftcomplete\Core\Plugin::load_partial('admin/settings-page');
  }

  /**
   * Render help text
   *
   * @return void
   */
  public function render_help_text(): void
  {
    \Swiftcomplete\Core\Plugin::load_partial('admin/settings-help-text');
  }

  /**
   * Render country header
   *
   * @return void
   */
  public function render_country_header(): void
  {
    \Swiftcomplete\Core\Plugin::load_partial('admin/country-header');
  }

  /**
   * Render text header
   *
   * @return void
   */
  public function render_text_header(): void
  {
    \Swiftcomplete\Core\Plugin::load_partial('admin/text-header');
  }

  /**
   * Render API key field
   *
   * @return void
   */
  public function render_api_key_field(): void
  {
    $settings = get_option(self::SETTINGS_OPTION);
    if ($settings === false || !array_key_exists('api_key', $settings)) {
      echo "<input id='swiftcomplete_api_key' name='swiftcomplete_settings[api_key]' type='text' value='' />";
    } else {
      echo "<input id='swiftcomplete_api_key' name='swiftcomplete_settings[api_key]' type='text' value='" . esc_attr($settings['api_key']) . "' />";
    }
  }

  /**
   * Render W3W enabled field
   *
   * @return void
   */
  public function render_w3w_enabled_field(): void
  {
    $settings = get_option(self::SETTINGS_OPTION);
    $w3w_enabled = $settings === false || (!\array_key_exists('w3w_enabled', $settings) || (\array_key_exists('w3w_enabled', $settings) && $settings['w3w_enabled'] === true));

    if ($w3w_enabled === true) {
      echo "<input id='swiftcomplete_w3w_enabled' name='swiftcomplete_settings[w3w_enabled]' type='checkbox' checked />";
    } else {
      echo "<input id='swiftcomplete_w3w_enabled' name='swiftcomplete_settings[w3w_enabled]' type='checkbox' />";
    }
  }

  /**
   * Render state counties enabled field
   *
   * @return void
   */
  public function render_state_counties_enabled_field(): void
  {
    $settings = get_option(self::SETTINGS_OPTION);

    if ($settings === false || !array_key_exists('state_counties_enabled', $settings)) {
      echo "<input id='swiftcomplete_state_counties_enabled' name='swiftcomplete_settings[state_counties_enabled]' type='checkbox' checked />";
    } else {
      echo "<input id='swiftcomplete_state_counties_enabled' name='swiftcomplete_settings[state_counties_enabled]' type='checkbox' " . ($settings['state_counties_enabled'] ? "checked " : "") . "/>";
    }
  }

  /**
   * Render hide fields field
   *
   * @return void
   */
  public function render_hide_fields_field(): void
  {
    $settings = get_option(self::SETTINGS_OPTION);
    if ($settings === false || !array_key_exists('hide_fields', $settings)) {
      echo "<input id='swiftcomplete_hide_fields' name='swiftcomplete_settings[hide_fields]' type='checkbox' />";
    } else {
      echo "<input id='swiftcomplete_hide_fields' name='swiftcomplete_settings[hide_fields]' type='checkbox' " . ($settings['hide_fields'] ? "checked " : "") . "/>";
    }
  }

  /**
   * Render billing label field
   *
   * @return void
   */
  public function render_billing_label_field(): void
  {
    $settings = get_option(self::SETTINGS_OPTION);
    if ($settings === false || !array_key_exists('billing_label', $settings)) {
      echo "<input id='swiftcomplete_billing_label' name='swiftcomplete_settings[billing_label]' type='text' value='Address Finder' />";
    } else {
      echo "<input id='swiftcomplete_billing_label' name='swiftcomplete_settings[billing_label]' type='text' value='" . esc_attr($settings['billing_label']) . "' />";
    }
    echo "<span class='help-text'>Name above billing search field (e.g. 'Address Finder')</span>";
  }

  /**
   * Render billing placeholder field
   *
   * @return void
   */
  public function render_billing_placeholder_field(): void
  {
    $settings = get_option(self::SETTINGS_OPTION);
    if ($settings === false || !array_key_exists('billing_placeholder', $settings)) {
      echo "<input id='swiftcomplete_billing_placeholder' name='swiftcomplete_settings[billing_placeholder]' type='text' value='' />";
    } else {
      echo "<input id='swiftcomplete_billing_placeholder' name='swiftcomplete_settings[billing_placeholder]' type='text' value='" . esc_attr($settings['billing_placeholder']) . "' />";
    }
    echo "<span class='help-text'>Prompt displayed in the billing search field (e.g. 'Type your address or postcode')</span>";
  }

  /**
   * Render shipping label field
   *
   * @return void
   */
  public function render_shipping_label_field(): void
  {
    $settings = get_option(self::SETTINGS_OPTION);
    if ($settings === false || !array_key_exists('shipping_label', $settings)) {
      echo "<input id='swiftcomplete_shipping_label' name='swiftcomplete_settings[shipping_label]' type='text' value='Address Finder' />";
    } else {
      echo "<input id='swiftcomplete_shipping_label' name='swiftcomplete_settings[shipping_label]' type='text' value='" . esc_attr($settings['shipping_label']) . "' />";
    }
    echo "<span class='help-text'>Name above shipping search field (e.g. 'Address Finder')</span>";
  }

  /**
   * Render shipping placeholder field
   *
   * @return void
   */
  public function render_shipping_placeholder_field(): void
  {
    $settings = get_option(self::SETTINGS_OPTION);
    if ($settings === false || !array_key_exists('shipping_placeholder', $settings)) {
      echo "<input id='swiftcomplete_shipping_placeholder' name='swiftcomplete_settings[shipping_placeholder]' type='text' value='' />";
    } else {
      echo "<input id='swiftcomplete_shipping_placeholder' name='swiftcomplete_settings[shipping_placeholder]' type='text' value='" . esc_attr($settings['shipping_placeholder']) . "' />";
    }
    echo "<span class='help-text'>Prompt displayed in the shipping search field (e.g. 'Type your address or postcode')</span>";
  }

  /**
   * Render bias towards field
   *
   * @return void
   */
  public function render_bias_towards_field(): void
  {
    $settings = get_option(self::SETTINGS_OPTION);
    if ($settings === false || !array_key_exists('bias_towards', $settings)) {
      echo "<input autocomplete='address' style='display: none;' />";
      echo "<input id='swiftcomplete_bias_towards' name='swiftcomplete_settings[bias_towards]' type='text' value='' placeholder='City, town or postcode' />";
      echo "<input type='hidden' id='swiftcomplete_bias_towards_lat_lon' name='swiftcomplete_settings[bias_towards_lat_lon]' />";
    } else {
      echo "<input autocomplete='address' style='display: none;' />";
      echo "<input id='swiftcomplete_bias_towards' name='swiftcomplete_settings[bias_towards]' type='text' value='" . esc_attr($settings['bias_towards']) . "' placeholder='City, town or postcode' />";
      echo "<input type='hidden' id='swiftcomplete_bias_towards_lat_lon' name='swiftcomplete_settings[bias_towards_lat_lon]' value='" . (array_key_exists('bias_towards_lat_lon', $settings) ? esc_attr($settings['bias_towards_lat_lon']) : '') . "' />";
    }
  }

  /**
   * Get a setting value.
   * Returns the value as stored (bool/string/etc). If the key doesn't exist, returns $default.
   *
   * @param string $key Setting key
   * @param mixed  $default Default value if setting doesn't exist
   * @return mixed
   */
  public function get_setting(string $key, $default = null)
  {
    $settings = get_option(self::SETTINGS_OPTION);
    if (!is_array($settings)) {
      $settings = array();
    }
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
  }

  /**
   * Get settings formatted for JavaScript
   * Returns settings in a format ready to be passed to JavaScript
   *
   * @return array<string, mixed> Formatted settings array
   */
  public function get_js_settings(): array
  {
    $settings = get_option(self::SETTINGS_OPTION);
    if (!is_array($settings)) {
      $settings = array();
    }

    // Get API key
    $api_key = isset($settings['api_key']) && strlen($settings['api_key']) > 0
      ? $settings['api_key']
      : '';

    // Determine search for value (address or address,what3words)
    $w3w_enabled = !isset($settings['w3w_enabled']) || $settings['w3w_enabled'] === true;
    $search_for = $w3w_enabled ? 'address,what3words' : 'address';

    // Get hide fields setting
    $hide_fields = isset($settings['hide_fields']) && $settings['hide_fields'] === true;

    // Get bias towards lat/lon
    $bias_lat_lon = isset($settings['bias_towards_lat_lon']) ? $settings['bias_towards_lat_lon'] : '';

    // Get placeholders
    $billing_placeholder = isset($settings['billing_placeholder']) ? $settings['billing_placeholder'] : '';
    $shipping_placeholder = isset($settings['shipping_placeholder']) ? $settings['shipping_placeholder'] : '';

    // Get state/counties enabled
    $state_counties = isset($settings['state_counties_enabled']) && $settings['state_counties_enabled'] === true;

    return array(
      'api_key' => $api_key,
      'w3w_enabled' => $w3w_enabled,
      'search_for' => $search_for,
      'hide_fields' => $hide_fields,
      'bias_lat_lon' => $bias_lat_lon,
      'billing_placeholder' => $billing_placeholder,
      'shipping_placeholder' => $shipping_placeholder,
      'state_counties' => $state_counties,
    );
  }
}
