<?php
/**
 * PSR-4 Autoloader
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;

/**
 * PSR-4 compliant autoloader for Swiftcomplete plugin
 */
spl_autoload_register(function ($class) {
  $prefix = 'Swiftcomplete\\';
  $base_dir = SWIFTCOMPLETE_PLUGIN_DIR . 'includes/';
  $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0) {
    return;
  }
  $relative_class = substr($class, $len);
  $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
  if (file_exists($file)) {
    require $file;
  }
});
