<?php
/**
 * Main plugin class
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;

/**
 * Main Swiftcomplete class
 */
class Swiftcomplete
{

    /**
     * Plugin version
     *
     * @var string
     */
    const VERSION = '1.1.0';

    /**
     * Instance of this class
     *
     * @var Swiftcomplete
     */
    private static $instance = null;

    /**
     * Get instance of this class
     *
     * @return Swiftcomplete
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
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init()
    {
        $this->init_components();
    }

    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return false;
        }
        return true;
    }

    /**
     * Display notice if WooCommerce is missing
     */
    public function woocommerce_missing_notice()
    {
        ?>
        <div class="error">
            <p><strong>SwiftLookup for WooCommerce</strong> requires WooCommerce to be installed and active.</p>
        </div>
        <?php
    }

    /**
     * Initialize plugin components
     */
    public function init_components()
    {
        if (!$this->check_woocommerce()) {
            return;
        }

        // Load plugin classes
        $this->load_dependencies();

        // Initialize components safely
        try {
            if (class_exists('SwiftcompleteSettings')) {
                SwiftcompleteSettings::get_instance();
            }
            if (class_exists('SwiftcompleteCheckout')) {
                SwiftcompleteCheckout::get_instance();
            }
            if (class_exists('SwiftcompleteAdmin')) {
                SwiftcompleteAdmin::get_instance();
            }
            if (class_exists('SwiftcompleteWhat3Words')) {
                SwiftcompleteWhat3Words::get_instance();
            }
            if (class_exists('SwiftcompleteAddressFields')) {
                SwiftcompleteAddressFields::get_instance();
            }
        } catch (Exception $e) {
            // Log error but don't crash WordPress
            if (function_exists('error_log')) {
                error_log('Swiftcomplete component initialization error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies()
    {
        $includes_dir = plugin_dir_path(__FILE__);

        $classes = array(
            'class-swiftcomplete-settings.php',
            'class-swiftcomplete-checkout.php',
            'class-swiftcomplete-admin.php',
            'class-swiftcomplete-what3words.php',
            'class-swiftcomplete-address-fields.php',
        );

        foreach ($classes as $class_file) {
            $file_path = $includes_dir . $class_file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Get plugin URL
     *
     * @return string
     */
    public static function get_plugin_url()
    {
        return defined('SWIFTCOMPLETE_PLUGIN_URL') ? SWIFTCOMPLETE_PLUGIN_URL : plugin_dir_url(dirname(__FILE__));
    }

    /**
     * Get plugin path
     *
     * @return string
     */
    public static function get_plugin_path()
    {
        return defined('SWIFTCOMPLETE_PLUGIN_DIR') ? SWIFTCOMPLETE_PLUGIN_DIR : plugin_dir_path(dirname(__FILE__));
    }
}

