<?php
/**
 * Settings page template
 *
 * @package Swiftcomplete
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form action="options.php" method="post">
    <?php
    settings_fields('swiftcomplete_settings');
    do_settings_sections('swiftcomplete');
    ?>
    <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e('Save'); ?>" />
</form>


