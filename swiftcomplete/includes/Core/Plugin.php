<?php
/**
 * Main Plugin Class
 *
 * @package Swiftcomplete
 */

namespace Swiftcomplete\Core;

use Swiftcomplete\Assets\AssetEnqueuer;
use Swiftcomplete\Checkout\CheckoutHandler;
use Swiftcomplete\Checkout\BlocksCheckout;
use Swiftcomplete\Checkout\ShortcodeCheckout;
use Swiftcomplete\Order\OrderDisplayManager;
use Swiftcomplete\Order\OrderMetaRepository;
use Swiftcomplete\Settings\SettingsManager;
use Swiftcomplete\Utilities\CheckoutTypeIdentifier;
use Swiftcomplete\Core\ServiceContainer;
use Swiftcomplete\Core\HookManager;
use Swiftcomplete\Core\ErrorHandler;

defined('ABSPATH') || exit;

/**
 * Main plugin class using dependency injection
 */
class Plugin
{
    /**
     * Plugin version
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Instance of this class
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Service container
     *
     * @var ServiceContainer
     */
    private $container;

    /**
     * Get instance of this class
     *
     * @return Plugin
     */
    public static function get_instance(): self
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
        ErrorHandler::init();
        try {
            $this->container = ServiceContainer::get_instance();
            $this->register_services();
            $this->init();
        } catch (\Throwable $e) {
            // Error handler will log this, but we need to prevent fatal error
            if (function_exists('error_log')) {
                error_log('Swiftcomplete: Failed to initialize plugin - ' . $e->getMessage());
            }
        }
    }

    /**
     * Register all services in the container
     *
     * @return void
     */
    private function register_services(): void
    {
        // Register core services as singletons
        $this->container->register_singleton('hook_manager', function () {
            return new HookManager();
        });

        $this->container->register_singleton('checkout_type_identifier', function () {
            return new CheckoutTypeIdentifier();
        });

        $this->container->register_singleton('meta_repository', function () {
            return new OrderMetaRepository();
        });

        // Register checkout strategies
        $this->container->register('shortcode_strategy', function ($container) {
            return new ShortcodeCheckout(
                $container->get('meta_repository'),
                $container->get('checkout_type_identifier')
            );
        });

        $this->container->register('blocks_strategy', function ($container) {
            return new BlocksCheckout(
                $container->get('meta_repository'),
                $container->get('checkout_type_identifier')
            );
        });

        // Register checkout handler
        $this->container->register_singleton('checkout_handler', function ($container) {
            return new CheckoutHandler(
                array(
                    $container->get('blocks_strategy'),
                    $container->get('shortcode_strategy'),
                ),
                $container->get('hook_manager'),
                $container->get('settings_manager'),
            );
        });

        // Register order display manager
        $this->container->register_singleton('order_display', function ($container) {
            return new OrderDisplayManager(
                $container->get('meta_repository'),
                $container->get('checkout_type_identifier'),
                $container->get('hook_manager')
            );
        });

        // Register asset enqueuer
        $this->container->register_singleton('asset_enqueuer', function ($container) {
            return new AssetEnqueuer(
                $container->get('checkout_type_identifier'),
                $container->get('hook_manager'),
                $container->get('settings_manager'),
                self::VERSION,
                SWIFTCOMPLETE_PLUGIN_URL
            );
        });

        // Register settings manager
        $this->container->register_singleton('settings_manager', function ($container) {
            return new SettingsManager(
                $container->get('hook_manager')
            );
        });
    }

    /**
     * Initialize plugin
     *
     * @return void
     */
    private function init(): void
    {
        // Safety check: Only initialize if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        // Initialize components by getting them from container
        // This triggers their constructors and hook registration
        $this->container->get('checkout_handler');
        $this->container->get('order_display');
        $this->container->get('asset_enqueuer');
        $this->container->get('settings_manager');
    }

    /**
     * Display notice if WooCommerce is missing
     *
     * @return void
     */
    public function woocommerce_missing_notice(): void
    {
        self::load_partial('admin/woocommerce-missing-notice');
    }

    /**
     * Load a partial
     *
     * @param string $partial Partial name (e.g., 'admin/settings-page').
     * @param array  $args    Variables to pass to partial.
     * @return void
     */
    public static function load_partial(string $partial, array $args = array()): void
    {
        $partial_path = SWIFTCOMPLETE_PLUGIN_DIR . 'partials/' . $partial . '.php';

        if (!file_exists($partial_path)) {
            if (function_exists('error_log')) {
                error_log("Swiftcomplete partial not found: {$partial_path}");
            }
            return;
        }

        // Extract variables for partial
        if (!empty($args)) {
            extract($args);
        }

        include $partial_path;
    }

    /**
     * Get plugin URL
     *
     * @return string
     */
    public static function get_plugin_url(): string
    {
        return SWIFTCOMPLETE_PLUGIN_URL;
    }

    /**
     * Get plugin path
     *
     * @return string
     */
    public static function get_plugin_path(): string
    {
        return SWIFTCOMPLETE_PLUGIN_DIR;
    }

    /**
     * Get service container
     *
     * @return ServiceContainer
     */
    public function get_container(): ServiceContainer
    {
        return $this->container;
    }
}
