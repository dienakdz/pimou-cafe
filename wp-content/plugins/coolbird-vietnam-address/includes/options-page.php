<?php
if (! defined('ABSPATH')) {
    exit;
}

$flra_options = wp_parse_args(get_option($this->_optionName), $this->_defaultOptions);
?>
<div class="wrap coolviad-settings-wrap">
    <h1><?php esc_html_e('Coolbird Vietnam Address for WooCommerce', 'coolbird-vietnam-address'); ?></h1>
    <p><?php esc_html_e('Configure Vietnamese province and ward fields for WooCommerce checkout.', 'coolbird-vietnam-address'); ?></p>

    <?php
    if (isset($_POST['save_coolviad_settings']) && check_admin_referer('coolviad_settings_nonce')) {
        $option_name = $this->_optionName;
        $current_options = wp_parse_args(get_option($option_name, array()), $this->_defaultOptions);
        $posted_options = isset($_POST[$option_name]) && is_array($_POST[$option_name])
            ? wp_unslash($_POST[$option_name])
            : array();
        $posted_options = $this->sanitize_options($posted_options);

        $managed_fields = array(
            'address_schema',
            'active_village',
            'required_village',
            'enable_firstname',
            'enable_country',
            'enable_postcode',
        );

        foreach ($managed_fields as $field_key) {
            if (in_array($field_key, array('active_village', 'required_village', 'enable_firstname', 'enable_country', 'enable_postcode'), true)) {
                $current_options[$field_key] = isset($posted_options[$field_key]) ? '1' : '';
                continue;
            }

            if (isset($posted_options[$field_key])) {
                $current_options[$field_key] = $posted_options[$field_key];
            }
        }

        update_option($option_name, $current_options);
        $flra_options = wp_parse_args($current_options, $this->_defaultOptions);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'coolbird-vietnam-address') . '</p></div>';
    }
    ?>

    <form method="post" action="">
        <?php wp_nonce_field('coolviad_settings_nonce'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Address schema', 'coolbird-vietnam-address'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="<?php echo esc_attr($this->_optionName); ?>[address_schema]" value="new" <?php checked('new', $flra_options['address_schema']); ?>>
                                <?php esc_html_e('New administrative data: Province/City → Ward/Commune', 'coolbird-vietnam-address'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="<?php echo esc_attr($this->_optionName); ?>[address_schema]" value="old" <?php checked('old', $flra_options['address_schema']); ?>>
                                <?php esc_html_e('Legacy administrative data: Province/City → District → Ward/Commune', 'coolbird-vietnam-address'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Checkout fields', 'coolbird-vietnam-address'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr($this->_optionName); ?>[active_village]" value="1" <?php checked('1', $flra_options['active_village']); ?>>
                            <?php esc_html_e('Hide ward/commune field', 'coolbird-vietnam-address'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr($this->_optionName); ?>[required_village]" value="1" <?php checked('1', $flra_options['required_village']); ?>>
                            <?php esc_html_e('Ward/commune is not required', 'coolbird-vietnam-address'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr($this->_optionName); ?>[enable_firstname]" value="1" <?php checked('1', $flra_options['enable_firstname']); ?>>
                            <?php esc_html_e('Show first name field', 'coolbird-vietnam-address'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr($this->_optionName); ?>[enable_country]" value="1" <?php checked('1', $flra_options['enable_country']); ?>>
                            <?php esc_html_e('Show country field', 'coolbird-vietnam-address'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr($this->_optionName); ?>[enable_postcode]" value="1" <?php checked('1', $flra_options['enable_postcode']); ?>>
                            <?php esc_html_e('Show postcode field', 'coolbird-vietnam-address'); ?>
                        </label>
                    </td>
                </tr>
            </tbody>
        </table>

        <p class="submit">
            <input type="submit" name="save_coolviad_settings" class="button button-primary" value="<?php esc_attr_e('Save settings', 'coolbird-vietnam-address'); ?>">
        </p>
    </form>
</div>
