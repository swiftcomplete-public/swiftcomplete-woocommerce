<?php
/**
 * Settings page template
 *
 * @package Swiftcomplete
 */

defined('ABSPATH') || exit;
?>
<form action="options.php" method="post">
    <?php
    settings_fields('swiftcomplete_settings');
    do_settings_sections('swiftcomplete');
    ?>
    <details class="swiftcomplete-advanced">
        <summary><?php esc_html_e('Advanced Settings', 'swiftcomplete'); ?></summary>
        <div class="swiftcomplete-advanced__body">
            <?php do_settings_sections('swiftcomplete_advanced'); ?>
        </div>
    </details>
    <input name="submit" class="button button-primary" type="submit"
        value="<?php esc_attr_e('Save', 'swiftcomplete'); ?>" />
</form>