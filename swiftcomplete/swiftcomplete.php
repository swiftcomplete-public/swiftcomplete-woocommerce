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
 * WC requires at least: 5.7.1
 * WC tested up to: 9.6.2
 *
 * @package Swiftcomplete
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Define plugin constants
if (!defined('SWIFTCOMPLETE_VERSION')) {
  define('SWIFTCOMPLETE_VERSION', '1.0.0');
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
 * Load PSR-4 autoloader
 */
$autoloader_file = SWIFTCOMPLETE_PLUGIN_DIR . 'includes/autoloader.php';
if (file_exists($autoloader_file)) {
  require_once $autoloader_file;
}

if (!function_exists('swiftcomplete_init')) {
  /**
   * Initialize the plugin
   * Uses OOP architecture with dependency injection and namespaces
   */
  function swiftcomplete_init()
  {
    if (!class_exists('\Swiftcomplete\Core\Plugin')) {
      if (function_exists('error_log')) {
        error_log('Swiftcomplete: Main plugin class not found. Plugin may be corrupted.');
      }
      return null;
    }

    try {
      return \Swiftcomplete\Core\Plugin::get_instance();
    } catch (\Throwable $e) {
      // Log error but don't crash WordPress
      if (function_exists('error_log')) {
        error_log('Swiftcomplete error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
      }
      return null;
    }
  }
}

// Register activation and deactivation hooks
if (function_exists('register_activation_hook')) {
  // Load activator class
  $activator_file = SWIFTCOMPLETE_PLUGIN_DIR . 'includes/activator.php';
  if (file_exists($activator_file)) {
    require_once $activator_file;
  }

  register_activation_hook(SWIFTCOMPLETE_PLUGIN_FILE, array('\Swiftcomplete\Activator', 'activate'));
  register_deactivation_hook(SWIFTCOMPLETE_PLUGIN_FILE, array('\Swiftcomplete\Activator', 'deactivate'));
}

// Initialize plugin only if WordPress is fully loaded
// Priority 10 ensures WooCommerce has had a chance to load first
if (function_exists('add_action')) {
  add_action('plugins_loaded', 'swiftcomplete_init', 10);

  // Declare compatibility with WooCommerce features
  add_action('before_woocommerce_init', array('\Swiftcomplete\Activator', 'declare_wc_compatibility'), 10);
}
