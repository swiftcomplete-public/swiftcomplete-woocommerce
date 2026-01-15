<?php
/**
 * Plugin Activation Handler
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;

/**
 * Swiftcomplete Activator class
 */
class SwiftcompleteActivator
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

    // Set up error handler to catch fatal errors
    set_error_handler(array(__CLASS__, 'error_handler'));
    register_shutdown_function(array(__CLASS__, 'shutdown_handler'));

    try {
      // Check PHP version
      if (version_compare(PHP_VERSION, '7.2', '<')) {
        deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
        self::safe_wp_die(
          sprintf(
            __('SwiftLookup for WooCommerce requires PHP 7.2 or higher. You are running PHP %s. Please upgrade PHP and try again.', 'swiftcomplete'),
            PHP_VERSION
          ),
          __('Plugin Activation Error', 'swiftcomplete'),
          array('back_link' => true)
        );
      }

      // Check WordPress version
      global $wp_version;
      if (version_compare($wp_version, '5.0', '<')) {
        deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
        self::safe_wp_die(
          sprintf(
            __('SwiftLookup for WooCommerce requires WordPress 5.0 or higher. You are running WordPress %s. Please upgrade WordPress and try again.', 'swiftcomplete'),
            $wp_version
          ),
          __('Plugin Activation Error', 'swiftcomplete'),
          array('back_link' => true)
        );
      }

      // Check if WooCommerce is active
      if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
        self::safe_wp_die(
          __('SwiftLookup for WooCommerce requires WooCommerce to be installed and active. Please install and activate WooCommerce first.', 'swiftcomplete'),
          __('Plugin Activation Error', 'swiftcomplete'),
          array('back_link' => true)
        );
      }

      // Check if required files exist
      $required_files = array(
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/class-swiftcomplete.php',
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/class-swiftcomplete-settings.php',
        SWIFTCOMPLETE_PLUGIN_DIR . 'includes/class-swiftcomplete-checkout.php',
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
            __('Plugin Activation Error', 'swiftcomplete'),
            array('back_link' => true)
          );
        }
      }

      // Try to load main class
      if (!class_exists('Swiftcomplete')) {
        $main_class_file = SWIFTCOMPLETE_PLUGIN_DIR . 'includes/class-swiftcomplete.php';
        if (file_exists($main_class_file)) {
          require_once $main_class_file;
        }
      }

      // Verify class loaded
      if (!class_exists('Swiftcomplete')) {
        $error_msg = __('Failed to load Swiftcomplete main class. Plugin may be corrupted.', 'swiftcomplete');
        self::log_error('ACTIVATION_ERROR', $error_msg);
        deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
        self::safe_wp_die(
          $error_msg,
          __('Plugin Activation Error', 'swiftcomplete'),
          array('back_link' => true)
        );
      }

      // Set activation flag
      update_option('swiftcomplete_activated', time());
      update_option('swiftcomplete_version', SWIFTCOMPLETE_VERSION);

      // Log successful activation
      self::log_error('ACTIVATION_SUCCESS', 'Plugin activated successfully');

    } catch (Exception $e) {
      $error_msg = sprintf(
        __('Plugin activation failed: %s', 'swiftcomplete'),
        $e->getMessage()
      );
      self::log_error('ACTIVATION_EXCEPTION', $error_msg . ' | Trace: ' . $e->getTraceAsString());
      deactivate_plugins(plugin_basename(SWIFTCOMPLETE_PLUGIN_FILE));
      self::safe_wp_die(
        $error_msg,
        __('Plugin Activation Error', 'swiftcomplete'),
        array('back_link' => true)
      );
    } catch (Error $e) {
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

    // Restore error handler on successful activation
    restore_error_handler();
  }

  /**
   * Deactivation hook callback
   */
  public static function deactivate()
  {
    // Clean up activation flag
    delete_option('swiftcomplete_activated');

    // Log deactivation
    self::log_error('DEACTIVATION', 'Plugin deactivated');
  }

  /**
   * Custom error handler
   *
   * @param int    $errno   Error number.
   * @param string $errstr  Error message.
   * @param string $errfile Error file.
   * @param int    $errline Error line.
   * @return bool
   */
  public static function error_handler($errno, $errstr, $errfile, $errline)
  {
    // Only log errors during activation
    if (defined('SWIFTCOMPLETE_ACTIVATING') && SWIFTCOMPLETE_ACTIVATING) {
      $error_msg = sprintf(
        'Error [%d]: %s in %s on line %d',
        $errno,
        $errstr,
        $errfile,
        $errline
      );
      self::log_error('ACTIVATION_WARNING', $error_msg);
    }
    return false; // Let PHP handle the error normally
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
   * Safely die with error message, ensuring error handler is restored first
   *
   * @param string $message Error message to display.
   * @param string $title   Error title.
   * @param array  $args    Additional arguments for wp_die.
   */
  private static function safe_wp_die($message, $title, $args = array())
  {
    // Restore error handler before dying to prevent leaving PHP with custom handler
    restore_error_handler();
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

    // Also log to custom file if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
      $log_file = WP_CONTENT_DIR . '/debug.log';
      if (is_writable($log_file) || is_writable(dirname($log_file))) {
        $log_entry = sprintf(
          "[%s] Swiftcomplete %s: %s\n",
          current_time('mysql'),
          $type,
          $message
        );
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
      }
    }
  }
}

