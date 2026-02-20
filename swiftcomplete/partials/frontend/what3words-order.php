<?php
/**
 * What3words order display template
 *
 * @package Swiftcomplete
 * @var string $what3words What3words value
 */

defined('ABSPATH') || exit;
?>
<p><strong><?php echo esc_html__('what3words'); ?>:</strong><br /><?php echo esc_html($what3words); ?></p>