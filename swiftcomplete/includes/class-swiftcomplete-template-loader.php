<?php
/**
 * Template Loader
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;

/**
 * Swiftcomplete Template Loader class
 */
class SwiftcompleteTemplateLoader
{

  /**
   * Get template path
   *
   * @param string $template Template name.
   * @return string
   */
  public static function get_template_path($template)
  {
    return SWIFTCOMPLETE_PLUGIN_DIR . 'templates/' . $template . '.php';
  }

  /**
   * Load template
   *
   * @param string $template Template name.
   * @param array  $args     Variables to pass to template.
   * @param bool   $require  Whether to require or include.
   */
  public static function load_template($template, $args = array(), $require = true)
  {
    $template_path = self::get_template_path($template);

    if (!file_exists($template_path)) {
      if (function_exists('error_log')) {
        error_log('Swiftcomplete template not found: ' . $template_path);
      }
      return;
    }

    // Extract variables for template
    if (!empty($args)) {
      extract($args);
    }

    if ($require) {
      require_once $template_path;
    } else {
      include_once $template_path;
    }
  }
}

