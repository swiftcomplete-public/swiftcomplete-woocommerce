<?php
/*
Plugin Name: Swiftcomplete
Plugin URI: https://www.swiftcomplete.com
Version: 1.0.3
Description: Add Swiftcomplete address lookup to the checkout
Author: Swiftcomplete
Author URI: https://www.swiftcomplete.com
*/

// Override default address fields --------------------------------------------------------------------------------------------
$billing_address_fields = array(
  'billing_first_name',
  'billing_last_name',
  'billing_country',
  'billing_address_autocomplete',
  'billing_company',
  'billing_address_2',
  'billing_address_1',
  'billing_city',
  'billing_postcode',
  'billing_state',
);

$shipping_address_fields = array(
  'shipping_first_name',
  'shipping_last_name',
  'shipping_country',
  'shipping_address_autocomplete',
  'shipping_company',
  'shipping_address_2',
  'shipping_address_1',
  'shipping_city',
  'shipping_postcode',
  'shipping_state',
);

$settings = get_option('swiftcomplete_settings');

add_filter('woocommerce_checkout_fields', 'override_default_address_fields');
add_action('woocommerce_checkout_update_order_meta', 'save_what3words_to_order');
add_action('woocommerce_order_details_after_customer_details', 'display_what3words_on_confirmation', 10, 2);
add_action('woocommerce_admin_order_data_after_shipping_address', 'display_what3words_on_order', 10, 1);
add_filter('woocommerce_form_field', 'remove_checkout_optional_fields_label', 10, 4);
add_filter('woocommerce_admin_order_preview_get_order_details', 'admin_order_preview_add_what3words', 10, 2);
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'swiftcomplete_add_plugin_page_settings_link', 10, 4);

function swiftcomplete_add_plugin_page_settings_link( $actions ) {
	$mylinks = array('<a href="' . admin_url( 'options-general.php?page=swiftcomplete' ) . '">' . __('Settings') . '</a>'
  );

  $actions = array_merge( $mylinks, $actions  );
  return $actions;
}

if ($settings['w3w_enabled'] == true) {
  add_action('woocommerce_after_order_notes', 'display_w3w_field');
}

function display_w3w_field($checkout)
{
  woocommerce_form_field('swiftcomplete_what3words', array(
    'label'     => __('///what3words', 'woocommerce'),
    'required'  => false,
    'class'     => array('form-row-wide'),
    'type'  => 'text',
    'id' => 'swiftcomplete_what3words',
    'placeholder' => __('e.g. ///word.word.word')
  ), $checkout->get_value('swiftcomplete_what3words'));
}

function display_what3words_on_confirmation($order)
{
  $order_id = $order->get_id();

  if (get_post_meta($order_id, 'swiftcomplete_what3words', true))
    echo '<p><strong>what3words:</strong> ' . get_post_meta($order_id, 'swiftcomplete_what3words', true) . '</p>';
}

function save_what3words_to_order($order_id)
{
  update_post_meta($order_id, 'swiftcomplete_what3words', sanitize_text_field($_POST['swiftcomplete_what3words']));
}

function display_what3words_on_order($order)
{
  echo '<p><strong>' . __('what3words') . ':</strong><br />' . get_post_meta($order->get_id(), 'swiftcomplete_what3words', true) . '</p>';
}

function remove_checkout_optional_fields_label($field, $key, $args, $value)
{
  if (is_checkout() && !is_wc_endpoint_url() && ($key == 'billing_address_autocomplete' || $key == 'shipping_address_autocomplete')) {
    $optional = '&nbsp;<span class="optional">(' . esc_html__('optional', 'woocommerce') . ')</span>';
    $field = str_replace($optional, '', $field);
  }
  return $field;
}

function admin_order_preview_add_what3words($data, $order)
{
  if ($order->get_meta('swiftcomplete_what3words'))
    $data['formatted_shipping_address'] = $data['formatted_shipping_address'] . '<br />' . esc_attr($order->get_meta('swiftcomplete_what3words')) . '<br />';

  return $data;
}
// -------------------
function override_default_address_fields($address_fields)
{
  $settings = get_option('swiftcomplete_settings');

  if (array_key_exists('billing_placeholder', $settings) && strlen($settings['billing_placeholder']) > 0)
    $billing_placeholder = esc_attr($settings['billing_placeholder']);
  else
    $billing_placeholder = array_key_exists('w3w_enabled', $settings) && $settings['w3w_enabled'] ? 'Type your address, postcode or what3words...' : 'Type your address or postcode...';

  if (array_key_exists('shipping_placeholder', $settings) && strlen($settings['shipping_placeholder']) > 0)
    $shipping_placeholder = esc_attr($settings['shipping_placeholder']);
  else
    $shipping_placeholder = array_key_exists('w3w_enabled', $settings) && $settings['w3w_enabled'] ? 'Type your address, postcode or what3words...' : 'Type your address or postcode...';

  $billing_label = array_key_exists('billing_label', $settings)  && strlen($settings['billing_label']) > 0 ? esc_attr($settings['billing_label']) : 'Address Finder';
  $shipping_label = array_key_exists('shipping_label', $settings)  && strlen($settings['shipping_label']) > 0 ? esc_attr($settings['shipping_label']) : 'Address Finder';

  $address_fields['billing']['billing_address_autocomplete'] = array(
    'label'     => __($billing_label, 'woocommerce'),
    'required'  => false,
    'class'     => array('form-row-wide'),
    'type'  => 'text',
    'id' => 'swiftcomplete_billing_address_autocomplete',
    'placeholder' => $billing_placeholder
  );

  $address_fields['shipping']['shipping_address_autocomplete'] = array(
    'label'     => __($shipping_label, 'woocommerce'),
    'required'  => false,
    'class'     => array('form-row-wide'),
    'type'  => 'text',
    'id' => 'swiftcomplete_shipping_address_autocomplete',
    'placeholder' => $shipping_placeholder
  );

  $priority = 0;
  global $billing_address_fields;

  foreach ($billing_address_fields as $key) {
    $address_fields['billing'][$key]['priority'] = $priority;
    $priority += 10;
  }

  $priority = 0;
  global $shipping_address_fields;

  foreach ($shipping_address_fields as $key) {
    $address_fields['shipping'][$key]['priority'] = $priority;
    $priority += 10;
  }

  return $address_fields;
}

// Settings --------------------------------------------------------------------------------------------
function swiftcomplete_api_key()
{
  $settings = get_option('swiftcomplete_settings');
  if ($settings === false || !array_key_exists('api_key', $settings)) {
    echo "<input id='swiftcomplete_api_key' name='swiftcomplete_settings[api_key]' type='text' value='' />";
  } else
    echo "<input id='swiftcomplete_api_key' name='swiftcomplete_settings[api_key]' type='text' value='" . esc_attr($settings['api_key']) . "' />";
}

function swiftcomplete_w3w_enabled()
{
  $settings = get_option('swiftcomplete_settings');
  if ($settings === false || !array_key_exists('w3w_enabled', $settings)) {
    echo "<input id='swiftcomplete_w3w_enabled' name='swiftcomplete_settings[w3w_enabled]' type='checkbox' />";
  } else
    echo "<input id='swiftcomplete_w3w_enabled' name='swiftcomplete_settings[w3w_enabled]' type='checkbox' " . ($settings['w3w_enabled'] ? "checked " : "") . "/>";
}

function swiftcomplete_hide_fields()
{
  $settings = get_option('swiftcomplete_settings');
  if ($settings === false || !array_key_exists('hide_fields', $settings)) {
    echo "<input id='swiftcomplete_hide_fields' name='swiftcomplete_settings[hide_fields]' type='checkbox' checked />";
  } else
    echo "<input id='swiftcomplete_hide_fields' name='swiftcomplete_settings[hide_fields]' type='checkbox' " . ($settings['hide_fields'] ? "checked " : "") . "/>";
}

function swiftcomplete_billing_label()
{
  $settings = get_option('swiftcomplete_settings');
  if ($settings === false || !array_key_exists('billing_label', $settings)) {
    echo "<input id='swiftcomplete_billing_label' name='swiftcomplete_settings[billing_label]' type='text' value='Address Finder' />";
  } else
    echo "<input id='swiftcomplete_billing_label' name='swiftcomplete_settings[billing_label]' type='text' value='" . esc_attr($settings['billing_label']) . "' />";
  echo "<span class='help-text'>Name above billing search field (e.g. 'Address Finder')</span>";
}

function swiftcomplete_billing_placeholder()
{
  $settings = get_option('swiftcomplete_settings');
  if ($settings === false || !array_key_exists('billing_placeholder', $settings)) {
    echo "<input id='swiftcomplete_billing_placeholder' name='swiftcomplete_settings[billing_placeholder]' type='text' value='' />";
  } else
    echo "<input id='swiftcomplete_billing_placeholder' name='swiftcomplete_settings[billing_placeholder]' type='text' value='" . esc_attr($settings['billing_placeholder']) . "' />";
  echo "<span class='help-text'>Prompt displayed in the billing search field (e.g. 'Type your address or postcode')</span>";
}

function swiftcomplete_shipping_label()
{
  $settings = get_option('swiftcomplete_settings');
  if ($settings === false || !array_key_exists('shipping_label', $settings)) {
    echo "<input id='swiftcomplete_shipping_label' name='swiftcomplete_settings[shipping_label]' type='text' value='Address Finder' />";
  } else
    echo "<input id='swiftcomplete_shipping_label' name='swiftcomplete_settings[shipping_label]' type='text' value='" . esc_attr($settings['shipping_label']) . "' />";
  echo "<span class='help-text'>Name above shipping search field (e.g. 'Address Finder')</span>";
}

function swiftcomplete_shipping_placeholder()
{
  $settings = get_option('swiftcomplete_settings');
  if ($settings === false || !array_key_exists('shipping_placeholder', $settings)) {
    echo "<input id='swiftcomplete_shipping_placeholder' name='swiftcomplete_settings[shipping_placeholder]' type='text' value='' />";
  } else
    echo "<input id='swiftcomplete_shipping_placeholder' name='swiftcomplete_settings[shipping_placeholder]' type='text' value='" . esc_attr($settings['shipping_placeholder']) . "' />";
  echo "<span class='help-text'>Prompt displayed in the shipping search field (e.g. 'Type your address or postcode')</span>";
}

function swiftcomplete_bias_towards()
{
  $settings = get_option('swiftcomplete_settings');
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

function swiftcomplete_settings_validate($input)
{
  $newinput['api_key'] = sanitize_text_field($input['api_key']);
  $newinput['billing_label'] = sanitize_text_field($input['billing_label']);
  $newinput['billing_placeholder'] = sanitize_text_field($input['billing_placeholder']);
  $newinput['shipping_label'] = sanitize_text_field($input['shipping_label']);
  $newinput['shipping_placeholder'] = sanitize_text_field($input['shipping_placeholder']);
  $newinput['bias_towards'] = sanitize_text_field($input['bias_towards']);
  $newinput['bias_towards_lat_lon'] = sanitize_text_field($input['bias_towards_lat_lon']);
  $newinput['w3w_enabled'] = $input['w3w_enabled'] == true;
  $newinput['hide_fields'] = $input['hide_fields'] == true;
  return $newinput;
}

function swiftcomplete_settings()
{
  register_setting('swiftcomplete_settings', 'swiftcomplete_settings', 'swiftcomplete_settings_validate');

  add_settings_section('swiftcomplete_api_settings', 'Swiftcomplete Settings', 'swiftcomplete_help_text', 'swiftcomplete');
  add_settings_field('swiftcomplete_api_key', 'API Key', 'swiftcomplete_api_key', 'swiftcomplete', 'swiftcomplete_api_settings');
  add_settings_field('swiftcomplete_w3w_enabled', 'Enable what3words?', 'swiftcomplete_w3w_enabled', 'swiftcomplete', 'swiftcomplete_api_settings');
  add_settings_field('swiftcomplete_hide_fields', 'Hide address fields until an address is selected?', 'swiftcomplete_hide_fields', 'swiftcomplete', 'swiftcomplete_api_settings');

  add_settings_section('swiftcomplete_country_settings', 'Location biasing', 'swiftcomplete_country_header', 'swiftcomplete');
  add_settings_field('swiftcomplete_bias_towards', 'Prioritise addresses near place', 'swiftcomplete_bias_towards', 'swiftcomplete', 'swiftcomplete_country_settings');

  add_settings_section('swiftcomplete_text_settings', 'Search field labels and placeholders', 'swiftcomplete_text_header', 'swiftcomplete');
  add_settings_field('swiftcomplete_billing_label', 'Billing field label', 'swiftcomplete_billing_label', 'swiftcomplete', 'swiftcomplete_text_settings');
  add_settings_field('swiftcomplete_billing_placeholder', 'Billing field placeholder text', 'swiftcomplete_billing_placeholder', 'swiftcomplete', 'swiftcomplete_text_settings');
  add_settings_field('swiftcomplete_shipping_label', 'Shipping field label', 'swiftcomplete_shipping_label', 'swiftcomplete', 'swiftcomplete_text_settings');
  add_settings_field('swiftcomplete_shipping_placeholder', 'Shipping field placeholder text', 'swiftcomplete_shipping_placeholder', 'swiftcomplete', 'swiftcomplete_text_settings');
}

function swiftcomplete_country_header()
{
  echo "<p class='swiftcomplete-text-sm'>Are your customers mostly from one area? Prioritise addresses near a city, town or postcode (<b>optional</b>)</p>";
  echo "<p>Location biasing increases relevance by prioritising addresses near a point (It doesn't stop customers finding addresses elsewhere). Leave this blank to use the customer's IP address for approximate location biasing.</p>";
}

function swiftcomplete_text_header()
{
  echo "<p class='swiftcomplete-text-sm'>Customise the address search field label and placeholder text (<b>optional</b> - leave fields blank for default text)</p>";
}
function swiftcomplete_help_text()
{
?>
  <script type="text/javascript">
    ! function(e, t, c) {
      e.swiftcomplete = e.swiftcomplete || {};
      var s = t.createElement("script");
      s.async = !0, s.src = c;
      var r = t.getElementsByTagName("script")[0];
      r.parentNode.insertBefore(s, r)
    }(window, document, "https://script.swiftcomplete.com/js/swiftcomplete.js");

    var SWIFTCOMPLETE_API_KEY = "24d0aea7-8227-44d8-b867-ba4a4a919e5a";
    var SWIFTCOMPLETE_SEARCH_FIELD_ID = "swiftcomplete_bias_towards";

    function initialiseSwiftcomplete() {
      swiftcomplete.runWhenReady(function() {
        swiftcomplete.controls["Places search"] = new swiftcomplete.PlaceAutoComplete({
          field: document.getElementById(SWIFTCOMPLETE_SEARCH_FIELD_ID),
          key: SWIFTCOMPLETE_API_KEY,
          searchFor: ""
        });

        document.getElementById(SWIFTCOMPLETE_SEARCH_FIELD_ID).addEventListener('swiftcomplete:place:selected', function(e) {
          document.getElementById('swiftcomplete_bias_towards_lat_lon').value = e.detail.result.geometry.centre.lat + ',' + e.detail.result.geometry.centre.lon;
        }, false);
      });
    }

    window.addEventListener("load", initialiseSwiftcomplete, false);
  </script>
  <style>
    .swiftcomplete-card {
      background-color: #FFF;
      padding: 20px;
      margin-bottom: 20px;
    }

    .swiftcomplete-text {
      font-size: 1.5em;
    }

    .swiftcomplete-text-sm {
      font-size: 1.2em;
    }

    .swiftcomplete-card h1 {
      line-height: 1.3em;
    }

    .help-text {
      color: #555;
      margin-left: 10px;
    }
  </style>
  <script type="text/javascript">
    window.addEventListener('load', function() {
      if (document.getElementById('swiftcomplete_api_key').value.length == 0) {
        document.getElementById('swiftcomplete-setup-instructions').style.display = 'block';
      } else {
        document.getElementById('swiftcomplete-existing-integration-instructions').style.display = 'block';
      }

      document.getElementById('swiftcomplete-run-setup-again').onclick = function() {
        document.getElementById('swiftcomplete-setup-instructions').style.display = 'block';
        document.getElementById('swiftcomplete-existing-integration-instructions').style.display = 'none';
      };
    });
  </script>
  <div class="swiftcomplete-card">
    <div style="display: grid; column-gap: 50px; max-width: 1200px;">
      <div style="grid-column-start: 1; grid-column-end: 3;">
        <img src="https://www.swiftcomplete.com/images/swiftcomplete-logo-pink-small.png" alt="Swiftcomplete" title="Swiftcomplete" width="175px" />
        <h1>Swiftcomplete Address Autocomplete Plugin</h1>
        <p class="swiftcomplete-text">Capture accurate billing and shipping addresses with Swiftcomplete's Address Autocomplete plugin for WooCommerce.</p>
        <div style="display: none;" id="swiftcomplete-setup-instructions">
          <h1>1. Sign up for Swiftcomplete</h1>
          <p class="swiftcomplete-text-sm"><a href='https://www.swiftcomplete.com/account/register/' target="_blank">Create a Swiftcomplete account</a> (or <a href='https://www.swiftcomplete.com/account/login/' target="_blank">sign in</a>)</p>
          <h1>2. Enable what3words? (optional)</h1>
          <p class="swiftcomplete-text-sm">Would you like to allow customers to optionally add ///what3words accurate delivery points to their address?</p>
          <p class="swiftcomplete-text-sm">What3words divides the whole world into 3x3 metre squares. It's ideal for helping couriers reliably find difficult addresses (particularly rural deliveries where postcodes cover a large area).</p>
          <p class="swiftcomplete-text-sm">If you want to use what3words, go to step 3a. If not, skip to step 3b.</p>
          <h1>3a. Setup with what3words</h1>
          <p class="swiftcomplete-text-sm">Go to the <a href="https://www.swiftcomplete.com/account/api/" target="_blank">API keys page</a>, and create a <b>what3words API key</b>.</p>
          <p class="swiftcomplete-text-sm">Copy and paste your API key in the text box at the bottom of this page, tick <b>Enable what3words</b>, and click <b>Save</b>.</p>
          <p class="swiftcomplete-text-sm">Skip to step 4.</p>
          <h1>3b. Setup without what3words</h1>
          <p class="swiftcomplete-text-sm">Go to the <a href="https://www.swiftcomplete.com/account/api/" target="_blank">API keys page</a>, and create an <b>Address Autocomplete API key</b>.
          <p class="swiftcomplete-text-sm">Copy and paste your API key in the text box at the bottom of this page, and click <b>Save</b>.</p>
          <h1>4. Finished</h1>
          <p class="swiftcomplete-text-sm">Go to your checkout page. There should be a new Address Finder search field in the billing and shipping address sections.</p>
          <p class="swiftcomplete-text-sm">Try searching for a postcode or address. Click the address, and it should be filled into your form correctly.</p>
          <p class="swiftcomplete-text-sm"><b>Any problems?</b> <a href="https://www.swiftcomplete.com/contact-us/" target="_blank">Get in touch with our customer support team</a> and we'll help you work through any issues.</p>
        </div>
        <div style="display: none;" id="swiftcomplete-existing-integration-instructions">
          <p class="swiftcomplete-text-sm"><b>Installation complete</b>. Having problems? <a id="swiftcomplete-run-setup-again" style="cursor: pointer;"><u>Run through setup again</u></a>, or <a href="https://www.swiftcomplete.com/contact-us/" target="_blank">get in touch with our customer support team</a> and we'll help you work through any issues.</p>
        </div>
      </div>
      <div style="grid-column-start: 3; grid-column-end: 4;">
        <img src="https://www.swiftcomplete.com/images/woocommerce-address-autocomplete.png" />
      </div>
    </div>
  </div>
<?php
}

function swiftcomplete_settings_page()
{
?>
  <form action="options.php" method="post">
    <?php
    settings_fields('swiftcomplete_settings');
    do_settings_sections('swiftcomplete');
    ?>
    <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
  </form>
<?php
}

function swiftcomplete_settings_menu()
{
  add_submenu_page('options-general.php', 'Swiftcomplete', 'Swiftcomplete', 'manage_options', 'swiftcomplete', 'swiftcomplete_settings_page');
}

// Add Swiftcomplete to checkout --------------------------------------------------------------------------------------------
function add_swiftcomplete_billing()
{
  $settings = get_option('swiftcomplete_settings');
  $api_key = '';

  if (array_key_exists('api_key', $settings) && strlen($settings['api_key']) > 0)
    $api_key = $settings['api_key'];

  wp_enqueue_script('swiftcomplete_script', 'https://script.swiftcomplete.com/js/swiftcomplete.js');
  wp_enqueue_script('swiftcomplete_launch', plugin_dir_url(__FILE__) . 'addressfinder.js', array('jquery'));
  wp_add_inline_script('swiftcomplete_launch', sprintf('launchAddressLookup("billing", "' . esc_attr($api_key) . '", "' . (array_key_exists('w3w_enabled', $settings) && $settings['w3w_enabled'] ? 'address,what3words' : 'address') . '", "' . (array_key_exists('hide_fields', $settings) && $settings['hide_fields'] ? true : false) . '", "' . (array_key_exists('bias_towards_lat_lon', $settings) ? esc_attr($settings['bias_towards_lat_lon']) : '') . '", "' . (array_key_exists('billing_placeholder', $settings) ? esc_attr($settings['billing_placeholder']) : '') . '")'));
}

function add_swiftcomplete_shipping()
{
  $settings = get_option('swiftcomplete_settings');
  $api_key = '';

  if (array_key_exists('api_key', $settings) && strlen($settings['api_key']) > 0)
    $api_key = $settings['api_key'];

  wp_enqueue_script('swiftcomplete_script', 'https://script.swiftcomplete.com/js/swiftcomplete.js');
  wp_enqueue_script('swiftcomplete_launch', plugin_dir_url(__FILE__) . 'addressfinder.js', array('jquery'));
  wp_add_inline_script('swiftcomplete_launch', sprintf('launchAddressLookup("shipping", "' . esc_attr($api_key) . '", "' . (array_key_exists('w3w_enabled', $settings) && $settings['w3w_enabled'] ? 'address,what3words' : 'address') . '", "' . (array_key_exists('hide_fields', $settings) && $settings['hide_fields'] ? true : false) . '", "' . (array_key_exists('bias_towards_lat_lon', $settings) ? esc_attr($settings['bias_towards_lat_lon']) : '') . '", "' . (array_key_exists('shipping_placeholder', $settings) ? esc_attr($settings['shipping_placeholder']) : '') . '")'));
}

add_action('woocommerce_checkout_billing', 'add_swiftcomplete_billing');
add_action('woocommerce_checkout_shipping', 'add_swiftcomplete_shipping');
add_action('admin_init', 'swiftcomplete_settings');
add_action('admin_menu', 'swiftcomplete_settings_menu');
