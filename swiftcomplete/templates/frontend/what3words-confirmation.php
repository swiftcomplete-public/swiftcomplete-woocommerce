<?php
/**
 * What3words confirmation template
 *
 * @package Swiftcomplete
 * @var int $order_id Order ID
 * @var string $what3words What3words value
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<p><strong>what3words:</strong> <?php echo esc_html($what3words); ?></p>


