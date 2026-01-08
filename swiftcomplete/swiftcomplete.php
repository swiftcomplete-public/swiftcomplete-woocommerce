<?php
/**
 * Plugin Name: SwiftLookup for WooCommerce
 * Plugin URI: https://swiftcomplete.notion.site/Swiftcomplete-WooCommerce-plugin-for-SwiftLookup-1a466db17f3b8018bc4ce65f85f6c852
 * Version: 1.1.0
 * Description: SwiftLookup Plugin for WooCommerce
 * Author: Swiftcomplete
 * Author URI: https://www.swiftcomplete.com
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 *
 * @package Swiftcomplete
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants (with safety checks)
if (!defined('SWIFTCOMPLETE_VERSION')) {
  define('SWIFTCOMPLETE_VERSION', '1.1.0');
}

if (!defined('SWIFTCOMPLETE_PLUGIN_FILE')) {
  define('SWIFTCOMPLETE_PLUGIN_FILE', __FILE__);
}

if (!defined('SWIFTCOMPLETE_PLUGIN_DIR')) {
  if (function_exists('plugin_dir_path')) {
    define('SWIFTCOMPLETE_PLUGIN_DIR', plugin_dir_path(__FILE__));
  } else {
    define('SWIFTCOMPLETE_PLUGIN_DIR', dirname(__FILE__) . '/');
  }
}

if (!defined('SWIFTCOMPLETE_PLUGIN_URL')) {
  if (function_exists('plugin_dir_url')) {
    define('SWIFTCOMPLETE_PLUGIN_URL', plugin_dir_url(__FILE__));
  } else {
    // Fallback - should never happen in WordPress, but safety first
    if (function_exists('plugins_url') && function_exists('plugin_basename')) {
      $plugin_dir = dirname(plugin_basename(__FILE__));
      define('SWIFTCOMPLETE_PLUGIN_URL', plugins_url($plugin_dir . '/'));
    } else {
      // Last resort fallback
      define('SWIFTCOMPLETE_PLUGIN_URL', '');
    }
  }
}

/**
 * Main plugin class loader
 */
if (!class_exists('Swiftcomplete')) {
  $main_class_file = SWIFTCOMPLETE_PLUGIN_DIR . 'includes/class-swiftcomplete.php';
  if (file_exists($main_class_file)) {
    require_once $main_class_file;
  }
}

/**
 * Initialize the plugin
 */
function swiftcomplete_init()
{
  // Safety check: Ensure class was loaded
  if (!class_exists('Swiftcomplete')) {
    return;
  }

  try {
    return Swiftcomplete::get_instance();
  } catch (Exception $e) {
    // Log error but don't crash WordPress
    if (function_exists('error_log')) {
      error_log('Swiftcomplete plugin error: ' . $e->getMessage());
    }
    return;
  }
}

// Register activation and deactivation hooks
if (function_exists('register_activation_hook')) {
  // Load activator class
  $activator_file = SWIFTCOMPLETE_PLUGIN_DIR . 'includes/class-swiftcomplete-activator.php';
  if (file_exists($activator_file)) {
    require_once $activator_file;
  }

  register_activation_hook(SWIFTCOMPLETE_PLUGIN_FILE, array('SwiftcompleteActivator', 'activate'));
  register_deactivation_hook(SWIFTCOMPLETE_PLUGIN_FILE, array('SwiftcompleteActivator', 'deactivate'));
}

// Initialize plugin only if WordPress is fully loaded
if (function_exists('add_action')) {
  add_action('plugins_loaded', 'swiftcomplete_init', 5);
}
