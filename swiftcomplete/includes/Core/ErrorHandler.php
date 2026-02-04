<?php
/**
 * Error Handler
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Core;

defined('ABSPATH') || exit;

/**
 * Global error handler for the plugin
 * Catches fatal errors and prevents WordPress crashes
 */
class ErrorHandler
{
  /**
   * Error count threshold before deactivating plugin
   *
   * @var int
   */
  private const ERROR_THRESHOLD = 5;

  /**
   * Option name for storing error count
   *
   * @var string
   */
  private const ERROR_COUNT_OPTION = 'swiftcomplete_error_count';

  /**
   * Option name for storing last error time
   *
   * @var string
   */
  private const LAST_ERROR_OPTION = 'swiftcomplete_last_error_time';

  /**
   * Time window for error counting (in seconds)
   *
   * @var int
   */
  private const ERROR_WINDOW = 3600; // 1 hour

  /**
   * Initialize error handler
   *
   * @return void
   */
  public static function init(): void
  {
    register_shutdown_function(array(__CLASS__, 'handle_shutdown'));
    set_error_handler(array(__CLASS__, 'handle_error'));
    set_exception_handler(array(__CLASS__, 'handle_exception'));
  }

  /**
   * Handle shutdown errors (fatal errors)
   *
   * @return void
   */
  public static function handle_shutdown(): void
  {
    $error = error_get_last();

    if ($error === null) {
      return;
    }

    $fatal_errors = array(
      E_ERROR,
      E_CORE_ERROR,
      E_COMPILE_ERROR,
      E_PARSE,
      E_RECOVERABLE_ERROR,
    );

    if (!in_array($error['type'], $fatal_errors, true)) {
      return;
    }

    if (!self::is_plugin_error($error['file'])) {
      return;
    }

    self::log_fatal_error($error);
    self::increment_error_count();

    if (self::should_deactivate()) {
      self::deactivate_plugin();
    }
  }

  /**
   * Handle non-fatal errors
   *
   * @param int    $errno   Error number
   * @param string $errstr  Error message
   * @param string $errfile Error file
   * @param int    $errline Error line
   * @return bool
   */
  public static function handle_error(int $errno, string $errstr, string $errfile, int $errline): bool
  {
    if (!self::is_plugin_error($errfile)) {
      return false;
    }

    self::log_error($errno, $errstr, $errfile, $errline);
    return false;
  }

  /**
   * Handle uncaught exceptions
   *
   * @param \Throwable $exception Exception object
   * @return void
   */
  public static function handle_exception(\Throwable $exception): void
  {
    if (!self::is_plugin_error($exception->getFile())) {
      if (function_exists('restore_exception_handler')) {
        restore_exception_handler();
      }
      throw $exception;
    }

    self::log_exception($exception);
    self::increment_error_count();

    if (self::should_deactivate()) {
      self::deactivate_plugin();
    }

    if (is_admin()) {
      add_action('admin_notices', function () use ($exception) {
        self::display_admin_notice($exception);
      });
    }
  }

  /**
   * Check if error is from this plugin
   *
   * @param string $file File path
   * @return bool
   */
  private static function is_plugin_error(string $file): bool
  {
    $plugin_dir = defined('SWIFTCOMPLETE_PLUGIN_DIR') ? SWIFTCOMPLETE_PLUGIN_DIR : '';
    if (empty($plugin_dir)) {
      return false;
    }

    return strpos($file, $plugin_dir) === 0;
  }

  /**
   * Log fatal error
   *
   * @param array $error Error array from error_get_last()
   * @return void
   */
  private static function log_fatal_error(array $error): void
  {
    $message = sprintf(
      '[Swiftcomplete FATAL ERROR] %s in %s on line %d',
      $error['message'],
      $error['file'],
      $error['line']
    );

    self::write_log($message);
  }

  /**
   * Log non-fatal error
   *
   * @param int    $errno   Error number
   * @param string $errstr  Error message
   * @param string $errfile Error file
   * @param int    $errline Error line
   * @return void
   */
  private static function log_error(int $errno, string $errstr, string $errfile, int $errline): void
  {
    $message = sprintf(
      '[Swiftcomplete ERROR] [%d] %s in %s on line %d',
      $errno,
      $errstr,
      $errfile,
      $errline
    );

    self::write_log($message);
  }

  /**
   * Log exception
   *
   * @param \Throwable $exception Exception object
   * @return void
   */
  private static function log_exception(\Throwable $exception): void
  {
    $message = sprintf(
      '[Swiftcomplete EXCEPTION] %s in %s on line %d',
      $exception->getMessage(),
      $exception->getFile(),
      $exception->getLine()
    );

    if ($exception->getTraceAsString()) {
      $message .= "\nStack trace:\n" . $exception->getTraceAsString();
    }

    self::write_log($message);
  }

  /**
   * Write to error log
   *
   * @param string $message Message to log
   * @return void
   */
  private static function write_log(string $message): void
  {
    if (!function_exists('error_log')) {
      return;
    }

    $log_message = sprintf(
      '%s | Time: %s | PHP: %s | WP: %s',
      $message,
      current_time('mysql'),
      PHP_VERSION,
      get_bloginfo('version')
    );

    error_log($log_message);
  }

  /**
   * Increment error count
   *
   * @return void
   */
  private static function increment_error_count(): void
  {
    $current_time = time();
    $last_error_time = get_option(self::LAST_ERROR_OPTION, 0);
    $error_count = get_option(self::ERROR_COUNT_OPTION, 0);

    if (($current_time - $last_error_time) > self::ERROR_WINDOW) {
      $error_count = 0;
    }

    $error_count++;
    update_option(self::ERROR_COUNT_OPTION, $error_count);
    update_option(self::LAST_ERROR_OPTION, $current_time);
  }

  /**
   * Check if plugin should be deactivated
   *
   * @return bool
   */
  private static function should_deactivate(): bool
  {
    $error_count = get_option(self::ERROR_COUNT_OPTION, 0);
    return $error_count >= self::ERROR_THRESHOLD;
  }

  /**
   * Deactivate plugin safely
   *
   * @return void
   */
  private static function deactivate_plugin(): void
  {
    if (!function_exists('deactivate_plugins')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugin_file = defined('SWIFTCOMPLETE_PLUGIN_FILE') ? SWIFTCOMPLETE_PLUGIN_FILE : '';
    if (empty($plugin_file)) {
      return;
    }

    deactivate_plugins(plugin_basename($plugin_file));

    self::write_log('[Swiftcomplete AUTO-DEACTIVATED] Too many fatal errors detected');
    delete_option(self::ERROR_COUNT_OPTION);
    delete_option(self::LAST_ERROR_OPTION);

    if (is_admin()) {
      add_action('admin_notices', array(__CLASS__, 'display_deactivation_notice'));
    }
  }

  /**
   * Display admin notice for exceptions
   *
   * @param \Throwable $exception Exception object
   * @return void
   */
  private static function display_admin_notice(\Throwable $exception): void
  {
    if (!current_user_can('manage_options')) {
      return;
    }

    $message = sprintf(
      __('Swiftcomplete encountered an error: %s. Please check the error log for details.', 'swiftcomplete'),
      esc_html($exception->getMessage())
    );

    printf(
      '<div class="notice notice-error"><p>%s</p></div>',
      esc_html($message)
    );
  }

  /**
   * Display deactivation notice
   *
   * @return void
   */
  public static function display_deactivation_notice(): void
  {
    if (!current_user_can('manage_options')) {
      return;
    }

    $message = __(
      'Swiftcomplete has been automatically deactivated due to multiple fatal errors. Please check the error log and contact support if the issue persists.',
      'swiftcomplete'
    );

    printf(
      '<div class="notice notice-error"><p><strong>%s</strong></p></div>',
      esc_html($message)
    );
  }

  /**
   * Reset error count (useful for testing or manual reset)
   *
   * @return void
   */
  public static function reset_error_count(): void
  {
    delete_option(self::ERROR_COUNT_OPTION);
    delete_option(self::LAST_ERROR_OPTION);
  }

  /**
   * Get current error count
   *
   * @return int
   */
  public static function get_error_count(): int
  {
    return get_option(self::ERROR_COUNT_OPTION, 0);
  }
}
