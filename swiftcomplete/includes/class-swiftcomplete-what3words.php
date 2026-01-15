<?php
/**
 * What3Words class
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;

/**
 * Swiftcomplete What3Words class
 */
class SwiftcompleteWhat3Words
{

    /**
     * Instance of this class
     *
     * @var SwiftcompleteWhat3Words
     */
    private static $instance = null;

    /**
     * Get instance of this class
     *
     * @return SwiftcompleteWhat3Words
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
        // Safety check: Only register hooks if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        $settings = get_option('swiftcomplete_settings');
        $w3w_enabled = $settings === false || (!array_key_exists('w3w_enabled', $settings) || (array_key_exists('w3w_enabled', $settings) && $settings['w3w_enabled'] == true));

        if ($w3w_enabled) {
            add_action('woocommerce_after_order_notes', array($this, 'display_w3w_field'));
        }

        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_w3w_to_order'));
        add_action('woocommerce_order_details_after_customer_details', array($this, 'display_w3w_on_confirmation'), 10, 2);
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_w3w_on_order'), 10, 1);
        add_filter('woocommerce_admin_order_preview_get_order_details', array($this, 'add_w3w_to_order_preview'), 10, 2);
    }

    /**
     * Display what3words field on checkout
     *
     * @param WC_Checkout $checkout Checkout object.
     */
    public function display_w3w_field($checkout)
    {
        woocommerce_form_field('swiftcomplete_what3words', array(
            'label' => __('///what3words', 'woocommerce'),
            'required' => false,
            'class' => array('form-row-wide'),
            'type' => 'text',
            'id' => 'swiftcomplete_what3words',
            'placeholder' => __('e.g. ///word.word.word')
        ), $checkout->get_value('swiftcomplete_what3words'));
    }

    /**
     * Save what3words to order
     *
     * @param int $order_id Order ID.
     */
    public function save_w3w_to_order($order_id)
    {
        // Safety checks
        if (!function_exists('update_post_meta') || !function_exists('sanitize_text_field')) {
            return;
        }

        $nonce = isset($_POST['woocommerce-process-checkout-nonce']) ? wp_unslash($_POST['woocommerce-process-checkout-nonce']) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'woocommerce-process_checkout')) {
            return;
        }

        if (isset($_POST['swiftcomplete_what3words']) && !empty($_POST['swiftcomplete_what3words'])) {
            update_post_meta($order_id, 'swiftcomplete_what3words', sanitize_text_field(wp_unslash($_POST['swiftcomplete_what3words'])));
        }
    }

    /**
     * Display what3words on order confirmation
     *
     * @param WC_Order $order Order object.
     */
    public function display_w3w_on_confirmation($order)
    {
        $order_id = $order->get_id();
        $what3words = get_post_meta($order_id, 'swiftcomplete_what3words', true);

        if ($what3words) {
            SwiftcompleteTemplateLoader::load_template('frontend/what3words-confirmation', array(
                'order_id' => $order_id,
                'what3words' => $what3words,
            ));
        }
    }

    /**
     * Display what3words on admin order page
     *
     * @param WC_Order $order Order object.
     */
    public function display_w3w_on_order($order)
    {
        $what3words = get_post_meta($order->get_id(), 'swiftcomplete_what3words', true);

        if ($what3words) {
            SwiftcompleteTemplateLoader::load_template('frontend/what3words-order', array(
                'what3words' => $what3words,
            ));
        }
    }

    /**
     * Add what3words to order preview
     *
     * @param array    $data  Order preview data.
     * @param WC_Order $order Order object.
     * @return array
     */
    public function add_w3w_to_order_preview($data, $order)
    {
        if ($order->get_meta('swiftcomplete_what3words')) {
            $data['formatted_shipping_address'] = $data['formatted_shipping_address'] . '<br />' . esc_attr($order->get_meta('swiftcomplete_what3words')) . '<br />';
        }

        return $data;
    }
}

