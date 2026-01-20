<?php
/**
 * Plugin Activation Handler
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete;

defined('ABSPATH') || exit;

if (!defined('SWIFTCOMPLETE_ACTIVATION_ERROR_TITLE')) {
  define('SWIFTCOMPLETE_ACTIVATION_ERROR_TITLE', 'Plugin Activation Error');
}

/**
 * Swiftcomplete Activator class
 */
class Activator
{

  /**
   * Activation hook callback
   */
  public static function activate()
  {
    // Define activation flag for error handlers
    if (!defined('SWIFTCOMPLETE_ACTIVATING')) {
      define('SWIFTCOMPLETE_ACTIVATING', true);
    }

    // Set up shutdown handler to catch fatal errors (parse errors, etc. that try-catch can't catch)
    register_shutdown_function(array(__CLASS__, 'shutdown_handler'));

    try {
      // Check PHP version
      if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
        self::safe_wp_die(
          sprintf(
            __('Swiftcomplete for WooCommerce requires PHP 7.4 or higher. You are running PHP %s. Please upgrade PHP and try again.', 'swiftcomplete'),
            PHP_VERSION
          ),
          __(SWIFTCOMPLETE_ACTIVATION_ERROR_TITLE, 'swiftcomplete'),
          array('back_link' => true)
        );
      }

      // Check WordPress version
      global $wp_version;
      if (version_compare($wp_version, '5.7.2', '<')) {
        deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
        self::safe_wp_die(
          sprintf(
            __('Swiftcomplete for WooCommerce requires WordPress 5.7.2 or higher. You are running WordPress %s. Please upgrade WordPress and try again.', 'swiftcomplete'),
            $wp_version
          ),
          __(SWIFTCOMPLETE_ACTIVATION_ERROR_TITLE, 'swiftcomplete'),
          array('back_link' => true)
        );
      }

      // Check if WooCommerce is active
      if (!class_exists('WooCommerce')) {
        self::log_error('ACTIVATION_ERROR', 'WooCommerce is not active');
        deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
        self::safe_wp_die(
          __('Swiftcomplete for WooCommerce requires WooCommerce to be installed and active. Please install and activate WooCommerce first.', 'swiftcomplete'),
          __(SWIFTCOMPLETE_ACTIVATION_ERROR_TITLE, 'swiftcomplete'),
          array('back_link' => true)
        );
      }

      // Check if required files exist (new architecture with namespaces)
      $required_files = array(
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/autoloader.php',
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/Core/Plugin.php',
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/Core/ServiceContainer.php',
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/Core/HookManager.php',
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/Checkout/CheckoutHandler.php',
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/Order/OrderDisplayManager.php',
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/Assets/AssetEnqueuer.php',
      );

      foreach ($required_files as $file) {
        if (!file_exists($file)) {
          $error_msg = sprintf(
            __('Required plugin file is missing: %s. Please reinstall the plugin.', 'swiftcomplete'),
            basename($file)
          );
          self::log_error('ACTIVATION_ERROR', $error_msg);
          deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
          self::safe_wp_die(
            $error_msg,
            __(SWIFTCOMPLETE_ACTIVATION_ERROR_TITLE, 'swiftcomplete'),
            array('back_link' => true)
          );
        }
      }

      // Verify main plugin class is available
      if (!class_exists('\Swiftcomplete\Core\Plugin')) {
        $error_msg = __('Failed to load Swiftcomplete main plugin class. Plugin may be corrupted.', 'swiftcomplete');
        self::log_error('ACTIVATION_ERROR', $error_msg);
        deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
        self::safe_wp_die(
          $error_msg,
          __(SWIFTCOMPLETE_ACTIVATION_ERROR_TITLE, 'swiftcomplete'),
          array('back_link' => true)
        );
      }

      // Set activation flag
      update_option('swiftcomplete_activated', time());
      update_option('swiftcomplete_version', SWIFTCOMPLETE_VERSION);

      // Log successful activation
      self::log_error('ACTIVATION_SUCCESS', 'Plugin activated successfully');

    } catch (\Exception $e) {
      $error_msg = sprintf(
        __('Plugin activation failed: %s', 'swiftcomplete'),
        $e->getMessage()
      );
      self::log_error('ACTIVATION_EXCEPTION', $error_msg . ' | Trace: ' . $e->getTraceAsString());
      deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
      self::safe_wp_die(
        $error_msg,
        __(SWIFTCOMPLETE_ACTIVATION_ERROR_TITLE, 'swiftcomplete'),
        array('back_link' => true)
      );
    } catch (\Error $e) {
      $error_msg = sprintf(
        __('Plugin activation fatal error: %s', 'swiftcomplete'),
        $e->getMessage()
      );
      self::log_error('ACTIVATION_FATAL', $error_msg . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
      deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
      self::safe_wp_die(
        $error_msg,
        __('Plugin Activation Fatal Error', 'swiftcomplete'),
        array('back_link' => true)
      );
    }

  }

  /**
   * Deactivation hook callback
   */
  public static function deactivate()
  {
    // Clean up activation flag
    delete_option('swiftcomplete_activated');

    // Reset error count if ErrorHandler was tracking errors
    if (class_exists('\Swiftcomplete\Core\ErrorHandler')) {
      \Swiftcomplete\Core\ErrorHandler::reset_error_count();
    }

    // Log deactivation
    self::log_error('DEACTIVATION', 'Plugin deactivated');
  }

  /**
   * Shutdown handler to catch fatal errors
   */
  public static function shutdown_handler()
  {
    if (defined('SWIFTCOMPLETE_ACTIVATING') && SWIFTCOMPLETE_ACTIVATING) {
      $error = error_get_last();
      if ($error !== null && in_array($error['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE))) {
        $error_msg = sprintf(
          'Fatal Error: %s in %s on line %d',
          $error['message'],
          $error['file'],
          $error['line']
        );
        self::log_error('ACTIVATION_FATAL_SHUTDOWN', $error_msg);
      }
    }
  }

  /**
   * Declare compatibility with WooCommerce features (HPOS, Blocks, etc.)
   */
  public static function declare_wc_compatibility()
  {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
      // Declare HPOS (High-Performance Order Storage) compatibility
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'custom_order_tables',
        SWIFTCOMPLETE_PLUGIN_FILE,
        true
      );
    }
  }

  /**
   * Safely die with error message
   *
   * @param string $message Error message to display.
   * @param string $title   Error title.
   * @param array  $args    Additional arguments for wp_die.
   */
  private static function safe_wp_die($message, $title, $args = array())
  {
    wp_die($message, $title, $args);
  }

  /**
   * Log error to WordPress debug log
   *
   * @param string $type    Error type.
   * @param string $message Error message.
   */
  private static function log_error($type, $message)
  {
    if (function_exists('error_log')) {
      $log_message = sprintf(
        '[Swiftcomplete %s] %s | Time: %s | PHP: %s | WP: %s',
        $type,
        $message,
        current_time('mysql'),
        PHP_VERSION,
        get_bloginfo('version')
      );
      error_log($log_message);
    }
  }
}

