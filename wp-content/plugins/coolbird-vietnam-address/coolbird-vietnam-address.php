<?php
/*
 * Plugin Name: Coolbird Vietnam Address for WooCommerce
 * Plugin URI: https://github.com/coolbirdzik/coolbird-vietnam-address-for-woocommerce/coolbird-vietnam-address
 * Version: 1.0.1
 * Description: Add province/city, district, commune/ward/town to checkout form and simplify checkout form
 * Author: CoolBirdZik
 * Author URI: https://github.com/coolbirdzik/coolbird-vietnam-address-for-woocommerce
 * Text Domain: coolbird-vietnam-address
 * Domain Path: /languages
 * WC requires at least: 8.0.0
 * WC tested up to: 10.1.2
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0
Coolbird Vietnam Address

Copyright (C) 2026 Nguyen Tan Hung - https://github.com/coolbirdzik/coolbird-vietnam-address-for-woocommerce

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

defined('ABSPATH') or die('No script kiddies please!');

use Automattic\WooCommerce\Utilities\OrderUtil;

// Define constants before using them
if (!defined('COOLVIAD_PLUGIN_DIR')) {
    define('COOLVIAD_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('COOLVIAD_URL')) {
    define('COOLVIAD_URL', plugin_dir_url(__FILE__));
}
if (!defined('COOLVIAD_PLUGIN_FILE')) {
    define('COOLVIAD_PLUGIN_FILE', __FILE__);
}

if (
    in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))
) {

    include COOLVIAD_PLUGIN_DIR . 'cities/provinces.php';

    register_activation_hook(__FILE__, array('Coolviad_Address_Selectbox_Class', 'on_activation'));
    register_deactivation_hook(__FILE__, array('Coolviad_Address_Selectbox_Class', 'on_deactivation'));
    register_uninstall_hook(__FILE__, array('Coolviad_Address_Selectbox_Class', 'on_uninstall'));

    class Coolviad_Address_Selectbox_Class
    {
        protected static $instance;

        protected $_version = '2.1.6';
        public $_optionName = 'coolviad_woo_district';
        public $_optionGroup = 'coolviad-district-options-group';
        public $_defaultOptions = array(
            'active_village' => '',
            'required_village' => '',
            'to_vnd' => '',
            'remove_methob_title' => '',
            'freeship_remove_other_methob' => '',
            'khoiluong_quydoi' => '6000',
            'tinhthanh_default' => '01',
            'active_vnd2usd' => 0,
            'vnd_usd_rate' => '22745',
            'vnd2usd_currency' => 'USD',

            'alepay_support' => 0,
            'enable_firstname' => 0,
            'enable_country' => 0,
            'enable_postcode' => 0,

            'enable_getaddressfromphone' => 0,
            'enable_recaptcha' => 0,
            'active_filter_order' => 0,
            'recaptcha_sitekey' => '',
            'recaptcha_secretkey' => '',

            // Address schema: 'old' = Province/City → District → Ward/Commune
            //                 'new' = Province/City → Ward/Commune (no district)
            'address_schema' => 'new',

            'license_key' => ''
        );

        public static function init()
        {
            is_null(self::$instance) and self::$instance = new self;
            return self::$instance;
        }

        public function __construct()
        {

            $this->define_constants();

            add_action('plugins_loaded', array($this, 'load_textdomain'));
            add_action('pll_language_defined', array($this, 'load_textdomain'));

            add_filter('woocommerce_checkout_fields', array($this, 'custom_override_checkout_fields'), 999999);
            add_filter('woocommerce_states', array($this, 'vietnam_cities_woocommerce'), 99999);

            add_action('wp_enqueue_scripts', array($this, 'coolviad_enqueue_UseAjaxInWp'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

            add_action('wp_ajax_coolviad_load_administrative_units', array($this, 'coolviad_load_administrative_units'));
            add_action('wp_ajax_nopriv_coolviad_load_administrative_units', array($this, 'coolviad_load_administrative_units'));

            // AJAX handlers for getting district and ward names (for Blocks checkout address card)
            add_action('wp_ajax_coolviad_get_district_name', array($this, 'coolviad_ajax_get_district_name'));
            add_action('wp_ajax_nopriv_coolviad_get_district_name', array($this, 'coolviad_ajax_get_district_name'));
            add_action('wp_ajax_coolviad_get_ward_name', array($this, 'coolviad_ajax_get_ward_name'));
            add_action('wp_ajax_nopriv_coolviad_get_ward_name', array($this, 'coolviad_ajax_get_ward_name'));

            add_filter('woocommerce_localisation_address_formats', array($this, 'coolviad_woocommerce_localisation_address_formats'), 99999);
            add_filter('woocommerce_order_formatted_billing_address', array($this, 'coolviad_woocommerce_order_formatted_billing_address'), 10, 2);

            add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'coolviad_after_shipping_address'), 10, 1);
            add_action('woocommerce_after_order_object_save', array($this, 'save_shipping_phone_meta'), 10);
            add_filter('woocommerce_order_formatted_shipping_address', array($this, 'coolviad_woocommerce_order_formatted_shipping_address'), 10, 2);

            add_filter('woocommerce_order_details_after_customer_details', array($this, 'coolviad_woocommerce_order_details_after_customer_details'), 10);

            //my account
            add_filter('woocommerce_my_account_my_address_formatted_address', array($this, 'coolviad_woocommerce_my_account_my_address_formatted_address'), 10, 3);
            add_filter('woocommerce_default_address_fields', array($this, 'coolviad_custom_override_default_address_fields'), 99999);
            add_filter('woocommerce_get_country_locale', array($this, 'coolviad_woocommerce_get_country_locale'), 99999);
            add_filter('woocommerce_get_country_locale_base', array($this, 'coolviad_woocommerce_get_country_locale_base'), 99999);

            //More action
            add_filter('default_checkout_billing_country', array($this, 'change_default_checkout_country'), 9999);
            add_filter('woocommerce_customer_get_shipping_country', array($this, 'change_default_checkout_country'), 9999);
            //add_filter( 'default_checkout_billing_state', array($this, 'change_default_checkout_state'), 99 );

            //Options
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_init', array($this, 'register_mysettings'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));


            add_option($this->_optionName, $this->_defaultOptions);

            // Vite builds ES modules — add type="module" to all Vite-built scripts
            add_filter('script_loader_tag', array($this, 'coolviad_set_module_type'), 10, 2);

            //admin order address, form billing
            add_filter('woocommerce_admin_billing_fields', array($this, 'coolviad_woocommerce_admin_billing_fields'), 99);
            add_filter('woocommerce_admin_shipping_fields', array($this, 'coolviad_woocommerce_admin_shipping_fields'), 99);

            add_filter('woocommerce_form_field_select', array($this, 'coolviad_woocommerce_form_field_select'), 10, 4);

            add_filter('woocommerce_formatted_address_replacements', array($this, 'coolviad_woocommerce_formatted_address_replacements'), 99);

            add_action('before_woocommerce_init', function () {
                if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                }
            });

            // Also filter the formatted address for emails and other displays
            add_filter('woocommerce_order_get_formatted_billing_address', array($this, 'coolviad_format_address_for_display'), 10, 2);
            add_filter('woocommerce_order_get_formatted_shipping_address', array($this, 'coolviad_format_address_for_display'), 10, 2);

            // Address ID-to-name formatting helpers
            add_filter('woocommerce_customer_get_shipping_city', array($this, 'coolviad_convert_city_id_to_name'), 10, 1);
            add_filter('woocommerce_customer_get_billing_city', array($this, 'coolviad_convert_city_id_to_name'), 10, 1);
            add_filter('woocommerce_customer_get_shipping_address_2', array($this, 'coolviad_convert_ward_id_to_name'), 10, 1);
            add_filter('woocommerce_customer_get_billing_address_2', array($this, 'coolviad_convert_ward_id_to_name'), 10, 1);

            // Hook into customer data retrieval for Blocks checkout
            add_filter('woocommerce_checkout_get_value', array($this, 'coolviad_convert_checkout_value'), 10, 2);
        }

        public function define_constants()
        {
            if (!defined('COOLVIAD_PLUGIN_FILE'))
                define('COOLVIAD_PLUGIN_FILE', __FILE__);
            if (!defined('COOLVIAD_VERSION_NUM'))
                define('COOLVIAD_VERSION_NUM', $this->_version);
            if (!defined('COOLVIAD_URL'))
                define('COOLVIAD_URL', plugin_dir_url(__FILE__));
            if (!defined('COOLVIAD_BASENAME'))
                define('COOLVIAD_BASENAME', plugin_basename(__FILE__));
            if (!defined('COOLVIAD_PLUGIN_DIR'))
                define('COOLVIAD_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }

        public function load_textdomain()
        {

            $locale = determine_locale();
            if (function_exists('pll_current_language')) {
                $pll_locale = pll_current_language('locale');
                if (!empty($pll_locale)) {
                    $locale = $pll_locale;
                }
            }
            $locale = apply_filters('plugin_locale', $locale, 'coolbird-vietnam-address');

            unload_textdomain('coolbird-vietnam-address');
            load_textdomain('coolbird-vietnam-address', COOLVIAD_PLUGIN_DIR . 'languages/coolbird-vietnam-address-' . $locale . '.mo');
            load_plugin_textdomain('coolbird-vietnam-address', false, dirname(plugin_basename(COOLVIAD_PLUGIN_FILE)) . '/languages/');
        }

        public static function on_activation()
        {
            if (!current_user_can('activate_plugins'))
                return false;
            $plugin = self::get_requested_plugin_basename();
            check_admin_referer("activate-plugin_{$plugin}");

        }

        /**
         * Create or upgrade shipping rates table
         */
        public static function create_shipping_rates_table()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'coolviad_shipping_rates';
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    location_type varchar(20) NOT NULL,
                    location_code varchar(50) NOT NULL,
                    base_rate decimal(10,2) NOT NULL DEFAULT 0,
                    weight_tiers longtext,
                    order_total_rules longtext,
                    weight_calc_type varchar(20) NOT NULL DEFAULT 'replace',
                    priority int(11) NOT NULL DEFAULT 0,
                    updated_at datetime DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY location_type (location_type),
                    KEY location_code (location_code),
                    KEY priority (priority)
                ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Create or upgrade shipping regions table and seed predefined regions
         */
        public static function create_shipping_regions_table()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'coolviad_shipping_regions';
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    region_name varchar(100) NOT NULL,
                    region_code varchar(50) NOT NULL,
                    province_codes longtext NOT NULL,
                    is_predefined tinyint(1) NOT NULL DEFAULT 0,
                    updated_at datetime DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY region_code (region_code)
                ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            self::seed_predefined_regions();
        }

        /**
         * Seed predefined Vietnam regions (idempotent)
         */
        private static function seed_predefined_regions()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'coolviad_shipping_regions';

            $predefined = array(
                array(
                    'region_name'    => 'Northern Vietnam',
                    'region_code'    => 'mien_bac',
                    'province_codes' => json_encode(array(
                        'HANOI',
                        'HAIPHONG',
                        'BACNINH',
                        'CAOBANG',
                        'DIENBIEN',
                        'HUNGYEN',
                        'LAICHAU',
                        'LANGSON',
                        'LAOCAI',
                        'NINHBINH',
                        'PHUTHO',
                        'QUANGNINH',
                        'SONLA',
                        'THAINGUYEN',
                        'TUYENQUANG',
                    )),
                    'is_predefined'  => 1,
                    'updated_at'     => current_time('mysql'),
                ),
                array(
                    'region_name'    => 'Central Vietnam',
                    'region_code'    => 'mien_trung',
                    'province_codes' => json_encode(array(
                        'THANHHOA',
                        'NGHEAN',
                        'HATINH',
                        'QUANGTRI',
                        'THUATHIENHUE',
                        'DANANG',
                        'QUANGNGAI',
                        'KHANHHOA',
                        'GIALAI',
                        'DAKLAK',
                        'LAMDONG',
                    )),
                    'is_predefined'  => 1,
                    'updated_at'     => current_time('mysql'),
                ),
                array(
                    'region_name'    => 'Southern Vietnam',
                    'region_code'    => 'mien_nam',
                    'province_codes' => json_encode(array(
                        'HOCHIMINH',
                        'ANGIANG',
                        'CAMAU',
                        'CANTHO',
                        'DONGNAI',
                        'DONGTHAP',
                        'TAYNINH',
                        'VINHLONG',
                    )),
                    'is_predefined'  => 1,
                    'updated_at'     => current_time('mysql'),
                ),
            );

            foreach ($predefined as $region) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Intentionally bypass caching: seeding runs once on activation/admin_init and checks for existence before insert. Table name is $wpdb->prefix + hardcoded suffix, safe.
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM " . esc_sql($table_name) . " WHERE region_code = %s",
                    $region['region_code']
                ));
                if (!$exists) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentionally direct insert for one-time idempotent seeding.
                    $wpdb->insert($table_name, $region);
                }
            }
        }

        public static function on_deactivation()
        {
            if (!current_user_can('activate_plugins'))
                return false;
            $plugin = self::get_requested_plugin_basename();
            check_admin_referer("deactivate-plugin_{$plugin}");
        }

        /**
         * Get the current plugin basename from the request.
         *
         * @return string
         */
        private static function get_requested_plugin_basename()
        {
            if (!isset($_REQUEST['plugin'])) {
                return plugin_basename(__FILE__);
            }

            $plugin = wp_unslash($_REQUEST['plugin']);

            if (!is_string($plugin) || $plugin === '') {
                return plugin_basename(__FILE__);
            }

            $plugin = plugin_basename(sanitize_text_field($plugin));

            return $plugin !== '' ? $plugin : plugin_basename(__FILE__);
        }

        public static function on_uninstall()
        {
            if (!current_user_can('activate_plugins'))
                return false;
        }

        function admin_menu()
        {
            add_submenu_page(
                'woocommerce',
                __('Coolbird Vietnam Address', 'coolbird-vietnam-address'),
                __('Coolbird Vietnam Address', 'coolbird-vietnam-address'),
                'manage_woocommerce',
                'coolviad-district-address',
                array(
                    $this,
                    'coolviad_district_setting'
                )
            );
        }

        function register_mysettings()
        {
            register_setting($this->_optionGroup, $this->_optionName, array($this, "sanitize_options"));
        }

        function sanitize_options($input)
        {
            $input = is_array($input) ? $input : array();
            $sanitized = $this->_defaultOptions;
            $checkbox_fields = array(
                'active_village',
                'required_village',
                'enable_firstname',
                'enable_country',
                'enable_postcode',
            );

            foreach ($checkbox_fields as $field_key) {
                $sanitized[$field_key] = !empty($input[$field_key]) ? '1' : '';
            }

            $sanitized['address_schema'] = in_array($input['address_schema'] ?? 'new', array('old', 'new'), true)
                ? $input['address_schema']
                : 'new';

            return $sanitized;
        }

        function coolviad_district_setting()
        {
            include COOLVIAD_PLUGIN_DIR . 'includes/options-page.php';
        }

        function vietnam_cities_woocommerce($states)
        {
            // Switch between "old" and "new" Vietnam address datasets.
            // - old: numeric province codes (01, 79...) + districts-legacy.php
            // - new: string province codes (HANOI, HOCHIMINH...) + districts.php
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            if ($schema === 'old') {
                include COOLVIAD_PLUGIN_DIR . 'cities/provinces-legacy.php';
            } else {
                include COOLVIAD_PLUGIN_DIR . 'cities/provinces.php';
            }
            $states['VN'] = apply_filters('coolviad_states_vn', $coolviad_provinces);
            return $states;
        }

        function custom_override_checkout_fields($fields)
        {
            global $coolviad_provinces;
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            if ($schema === 'old') {
                include COOLVIAD_PLUGIN_DIR . 'cities/provinces-legacy.php';
            } else {
                include COOLVIAD_PLUGIN_DIR . 'cities/provinces.php';
            }

            $billing_country = wc_get_post_data_by_key('billing_country', WC()->customer->get_billing_country());
            $shipping_country = wc_get_post_data_by_key('shipping_country', WC()->customer->get_shipping_country());
            $billing_is_vn = $this->is_vietnam_country($billing_country);
            $shipping_is_vn = $this->is_vietnam_country($shipping_country);

            if (!$this->get_options('enable_firstname')) {
                //Billing
                $fields['billing']['billing_last_name'] = array(
                    'label' => __('Full name', 'coolbird-vietnam-address'),
                    'placeholder' => _x('Type Full name', 'placeholder', 'coolbird-vietnam-address'),
                    'required' => true,
                    'class' => array('form-row-wide'),
                    'clear' => true,
                    'priority' => 10
                );
            }
            if (isset($fields['billing']['billing_phone'])) {
                $fields['billing']['billing_phone']['class'] = array('form-row-first');
                $fields['billing']['billing_phone']['placeholder'] = __('Type your phone', 'coolbird-vietnam-address');
            }
            if (isset($fields['billing']['billing_email'])) {
                $fields['billing']['billing_email']['class'] = array('form-row-last');
                $fields['billing']['billing_email']['placeholder'] = __('Type your email', 'coolbird-vietnam-address');
            }
            if ($billing_is_vn) {
                $fields['billing']['billing_state'] = array(
                    'label' => __('Province/City', 'coolbird-vietnam-address'),
                    'required' => true,
                    'type' => 'select',
                    'class' => array('form-row-first', 'address-field', 'update_totals_on_change'),
                    'placeholder' => _x('Select Province/City', 'placeholder', 'coolbird-vietnam-address'),
                    'options' => array('' => __('Select Province/City', 'coolbird-vietnam-address')) + apply_filters('coolviad_states_vn', $coolviad_provinces),
                    'priority' => 30
                );
                $fields['billing']['billing_city'] = array(
                    'label' => ($schema === 'new') ? __('Ward/Commune', 'coolbird-vietnam-address') : __('District', 'coolbird-vietnam-address'),
                    'required' => true,
                    'type' => 'select',
                    'class' => array('form-row-last'),
                    'placeholder' => ($schema === 'new') ? _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address') : _x('Select District', 'placeholder', 'coolbird-vietnam-address'),
                    'options' => array(
                        '' => ($schema === 'new') ? _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address') : _x('Select District', 'placeholder', 'coolbird-vietnam-address')
                    ),
                    'priority' => 40,
                    'input_class' => array('woocommerce-enhanced-select'),
                    'custom_attributes' => array(
                        'data-plugin' => 'coolbird-vietnam-address'
                    )
                );
                $fields['billing']['billing_address_1']['placeholder'] = _x('Ex: No. 20, 90 Alley', 'placeholder', 'coolbird-vietnam-address');
                $fields['billing']['billing_address_1']['class'] = array('form-row-wide');
            }

            $fields['billing']['billing_address_1']['priority'] = 60;
            if (isset($fields['billing']['billing_phone'])) {
                $fields['billing']['billing_phone']['priority'] = 20;
            }
            if (isset($fields['billing']['billing_email'])) {
                $fields['billing']['billing_email']['priority'] = 21;
            }
            if (!$this->get_options('enable_firstname')) {
                unset($fields['billing']['billing_first_name']);
            }
            if (!$this->get_options('enable_country')) {
                unset($fields['billing']['billing_country']);
            } else {
                $fields['billing']['billing_country']['priority'] = 22;
            }
            if ($billing_is_vn) {
                if ($schema === 'new') {
                    unset($fields['billing']['billing_address_2']);
                } elseif (!$this->get_options('active_village')) {
                    $ward_required = !$this->get_options('required_village');
                    $fields['billing']['billing_address_2'] = array(
                        'label' => __('Ward/Commune', 'coolbird-vietnam-address'),
                        'required' => $ward_required,
                        'type' => 'select',
                        'class' => array('form-row-wide'),
                        'placeholder' => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address'),
                        'options' => array('' => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address')),
                        'priority' => 50,
                        'input_class' => array('woocommerce-enhanced-select'),
                        'custom_attributes' => array(
                            'data-plugin' => 'coolbird-vietnam-address'
                        )
                    );
                } else {
                    unset($fields['billing']['billing_address_2']);
                }
            }
            unset($fields['billing']['billing_company']);

            //Shipping
            if (!$this->get_options('enable_firstname')) {
                $fields['shipping']['shipping_last_name'] = array(
                    'label' => __('Recipient full name', 'coolbird-vietnam-address'),
                    'placeholder' => _x('Recipient full name', 'placeholder', 'coolbird-vietnam-address'),
                    'required' => true,
                    'class' => array('form-row-first'),
                    'clear' => true,
                    'priority' => 10
                );
            }
            $fields['shipping']['shipping_phone'] = array(
                'label' => __('Recipient phone', 'coolbird-vietnam-address'),
                'placeholder' => _x('Recipient phone', 'placeholder', 'coolbird-vietnam-address'),
                'required' => false,
                'class' => array('form-row-last'),
                'clear' => true,
                'priority' => 20
            );
            if ($this->get_options('enable_firstname')) {
                $fields['shipping']['shipping_phone']['class'] = array('form-row-wide');
            }
            if ($shipping_is_vn) {
                $fields['shipping']['shipping_state'] = array(
                    'label' => __('Province/City', 'coolbird-vietnam-address'),
                    'required' => true,
                    'type' => 'select',
                    'class' => array('form-row-first', 'address-field', 'update_totals_on_change'),
                    'placeholder' => _x('Select Province/City', 'placeholder', 'coolbird-vietnam-address'),
                    'options' => array('' => __('Select Province/City', 'coolbird-vietnam-address')) + apply_filters('coolviad_states_vn', $coolviad_provinces),
                    'priority' => 30
                );
                $fields['shipping']['shipping_city'] = array(
                    'label' => ($schema === 'new') ? __('Ward/Commune', 'coolbird-vietnam-address') : __('District', 'coolbird-vietnam-address'),
                    'required' => true,
                    'type' => 'select',
                    'class' => array('form-row-last'),
                    'placeholder' => ($schema === 'new') ? _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address') : _x('Select District', 'placeholder', 'coolbird-vietnam-address'),
                    'options' => array(
                        '' => ($schema === 'new') ? _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address') : _x('Select District', 'placeholder', 'coolbird-vietnam-address'),
                    ),
                    'priority' => 40,
                    'input_class' => array('woocommerce-enhanced-select'),
                    'custom_attributes' => array(
                        'data-plugin' => 'coolbird-vietnam-address'
                    )
                );
                $fields['shipping']['shipping_address_1']['placeholder'] = _x('Ex: No. 20, 90 Alley', 'placeholder', 'coolbird-vietnam-address');
                $fields['shipping']['shipping_address_1']['class'] = array('form-row-wide');
            }
            $fields['shipping']['shipping_address_1']['priority'] = 60;
            if (!$this->get_options('enable_firstname')) {
                unset($fields['shipping']['shipping_first_name']);
            }
            if (!$this->get_options('enable_country')) {
                unset($fields['shipping']['shipping_country']);
            } else {
                $fields['shipping']['shipping_country']['priority'] = 22;
            }
            if ($shipping_is_vn) {
                if ($schema === 'new') {
                    unset($fields['shipping']['shipping_address_2']);
                } elseif (!$this->get_options('active_village')) {
                    $ward_required = !$this->get_options('required_village');
                    $fields['shipping']['shipping_address_2'] = array(
                        'label' => __('Ward/Commune', 'coolbird-vietnam-address'),
                        'required' => $ward_required,
                        'type' => 'select',
                        'class' => array('form-row-wide'),
                        'placeholder' => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address'),
                        'options' => array('' => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address')),
                        'priority' => 50,
                        'input_class' => array('woocommerce-enhanced-select'),
                        'custom_attributes' => array(
                            'data-plugin' => 'coolbird-vietnam-address'
                        )
                    );
                } else {
                    unset($fields['shipping']['shipping_address_2']);
                }
            }
            unset($fields['shipping']['shipping_company']);

            uasort($fields['billing'], array($this, 'sort_fields_by_order'));
            uasort($fields['shipping'], array($this, 'sort_fields_by_order'));

            return apply_filters('coolviad_checkout_fields', $fields);
        }

        function sort_fields_by_order($a, $b)
        {
            if (!isset($b['priority']) || !isset($a['priority']) || $a['priority'] == $b['priority']) {
                return 0;
            }
            return ($a['priority'] < $b['priority']) ? -1 : 1;
        }

        function search_in_array($array, $key, $value)
        {
            $results = array();

            if (is_array($array)) {
                if (isset($array[$key]) && $array[$key] == $value) {
                    $results[] = $array;
                } elseif (isset($array[$key]) && is_serialized($array[$key]) && in_array($value, maybe_unserialize($array[$key]))) {
                    $results[] = $array;
                }
                foreach ($array as $subarray) {
                    $results = array_merge($results, $this->search_in_array($subarray, $key, $value));
                }
            }

            return $results;
        }

        function coolviad_check_remote_file_status($file_url = '')
        {
            if (empty($file_url) || ! filter_var($file_url, FILTER_VALIDATE_URL)) {
                return false;
            }

            $cache_key = 'coolviad_get_address_file_status';
            $status    = get_transient($cache_key);

            if (false !== $status) {
                return $status;
            }

            $response = wp_safe_remote_get(
                esc_url_raw($file_url),
                array(
                    'redirection' => 0,
                )
            );

            if (is_wp_error($response)) {
                return false;
            }

            $response_code = intval(wp_remote_retrieve_response_code($response));

            if ($response_code === 200) {
                set_transient($cache_key, $response_code, WEEK_IN_SECONDS);
                return $response_code;
            }

            return false;
        }


        function coolviad_enqueue_UseAjaxInWp()
        {
            // Support both classic and block-based checkout pages (including translated checkouts)
            if (is_checkout() || is_cart() || is_account_page() || apply_filters('coolviad_checkout_allow_script_all_page', false)) {
                // React and ReactDOM are already bundled locally via Vite in assets/dist/
                // No need to load from external CDN (https://unpkg.com)

                $get_address = COOLVIAD_URL . 'get-address.php';
                if ($this->coolviad_check_remote_file_status($get_address) != 200) {
                    $get_address = admin_url('admin-ajax.php');
                }

                // Saved customer address (for Edit Address / My Account prefill)
                $saved = array(
                    'billing'  => array('state' => '', 'city' => '', 'ward' => ''),
                    'shipping' => array('state' => '', 'city' => '', 'ward' => ''),
                );

                // Helper to get value from multiple sources
                $get_value = function ($key, $meta_key) {
                    // First check POST data
                    if (isset($_POST[$key])) {
                        return wc_clean(wp_unslash($_POST[$key]));
                    }
                    // Then check checkout values (session)
                    if (WC()->checkout) {
                        $value = WC()->checkout->get_value($key);
                        if ($value) {
                            return $value;
                        }
                    }
                    // Finally check customer meta for logged in users
                    if (is_user_logged_in()) {
                        $user_id = get_current_user_id();
                        return get_user_meta($user_id, $meta_key, true);
                    }
                    return '';
                };

                $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
                $saved['billing']['state']  = $get_value('billing_state', 'billing_state');
                $saved['billing']['city']   = $get_value('billing_city', 'billing_city');
                $saved['billing']['ward']   = $schema === 'new' ? '' : $get_value('billing_address_2', 'billing_address_2');
                $saved['shipping']['state'] = $get_value('shipping_state', 'shipping_state');
                $saved['shipping']['city']  = $get_value('shipping_city', 'shipping_city');
                $saved['shipping']['ward']  = $schema === 'new' ? '' : $get_value('shipping_address_2', 'shipping_address_2');

                // Localize config for both jQuery and React scripts
                $localize_data = array(
                    'ajaxurl'           => admin_url('admin-ajax.php'),
                    'admin_ajax'        => admin_url('admin-ajax.php'),
                    'get_address'       => $get_address,
                    'home_url'          => home_url(),
                    'formatNoMatches'   => __('No value', 'coolbird-vietnam-address'),
                    'phone_error'       => __('Phone number is incorrect', 'coolbird-vietnam-address'),
                    'loading_text'      => __('Loading...', 'coolbird-vietnam-address'),
                    'loadaddress_error' => __('Phone number does not exist', 'coolbird-vietnam-address'),
                    'select_district'   => _x('Select District', 'placeholder', 'coolbird-vietnam-address'),
                    'select_ward'       => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address'),
                    'recaptcha_required' => __('Please complete verification.', 'coolbird-vietnam-address'),
                    // Address schema + fallback label when a ward does not exist (new schema)
                    'address_schema'    => $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new',
                    'no_ward_label'     => __('No ward / N/A', 'coolbird-vietnam-address'),
                    'saved'             => $saved,
                );

                // Pre-resolve names for saved addresses to display in address card
                $preloaded_names = array();
                $address_types = array('billing', 'shipping');
                foreach ($address_types as $type) {
                    $city_id = $saved[$type]['city'];
                    $ward_id = $saved[$type]['ward'];
                    if ($city_id && is_numeric($city_id)) {
                        $name = $this->get_name_district($city_id);
                        if ($name) {
                            $preloaded_names[$city_id] = $name;
                        }
                    }
                    if ($ward_id && is_numeric($ward_id)) {
                        $name = $this->get_name_village($ward_id);
                        if ($name) {
                            $preloaded_names[$ward_id] = $name;
                        }
                    }
                }

                // Add ajax_url to localize data for React AddressSelector
                $localize_data['ajax_url'] = admin_url('admin-ajax.php');

                // Woo Blocks checkout — inject district/ward dropdowns
                // React and ReactDOM are already bundled locally in the Vite build (assets/dist/)
                $checkout_asset = COOLVIAD_PLUGIN_DIR . 'assets/dist/checkout.js';
                $checkout_asset_version = file_exists($checkout_asset) ? filemtime($checkout_asset) : $this->_version;

                wp_enqueue_script(
                    'coolviad_blocks_checkout',
                    COOLVIAD_URL . 'assets/dist/checkout.js',
                    array(),
                    $checkout_asset_version,
                    true
                );

                // Add type="module" for ES module support
                add_filter('script_loader_tag', function ($tag, $handle) {
                    if ($handle === 'coolviad_blocks_checkout') {
                        $tag = str_replace(' src=', ' type="module" src=', $tag);
                    }
                    return $tag;
                }, 10, 2);

                // Localize config for React AddressSelector
                wp_localize_script('coolviad_blocks_checkout', 'coolviad_checkout_data', $localize_data);

                // Also localize for jQuery legacy code (if any)
                wp_localize_script('coolviad_blocks_checkout', 'coolviad_vn', array(
                    'ajax_url'        => admin_url('admin-ajax.php'),
                    'address_schema'  => $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new',
                    'preloaded_names' => $preloaded_names,
                    'i18n' => array(
                        'district_label'   => __('District', 'coolbird-vietnam-address'),
                        'ward_label'       => __('Ward/Commune', 'coolbird-vietnam-address'),
                        'select_district'  => _x('Select District', 'placeholder', 'coolbird-vietnam-address'),
                        'select_ward'      => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address'),
                        'loading'          => __('Loading...', 'coolbird-vietnam-address'),
                        'load_error'       => __('Failed to load data', 'coolbird-vietnam-address'),
                    ),
                ));
            }
        }

        function coolviad_load_administrative_units()
        {
            $matp = isset($_POST['matp']) ? wc_clean(wp_unslash($_POST['matp'])) : '';
            // Keep as string (can be 3-digit old codes like '001' or 5-digit new codes like '26758')
            $maqh = isset($_POST['maqh']) ? wc_clean(wp_unslash($_POST['maqh'])) : '';
            if ($matp) {
                $result = $this->get_list_district($matp);
                wp_send_json_success($result);
            }
            if ($maqh) {
                $result = $this->get_list_village($maqh);
                wp_send_json_success($result);
            }
            wp_send_json_error();
            die();
        }

        /**
         * AJAX handler to get district name by ID
         */
        function coolviad_ajax_get_district_name()
        {
            $district_id = isset($_POST['district_id']) ? wc_clean(wp_unslash($_POST['district_id'])) : '';
            if (!$district_id) {
                wp_send_json_error(array('message' => 'No district ID provided'));
            }
            $name = $this->get_name_district($district_id);
            if ($name) {
                wp_send_json_success(array('name' => $name));
            } else {
                wp_send_json_error(array('message' => 'District not found'));
            }
            die();
        }

        /**
         * AJAX handler to get ward name by ID
         */
        function coolviad_ajax_get_ward_name()
        {
            $ward_id = isset($_POST['ward_id']) ? wc_clean(wp_unslash($_POST['ward_id'])) : '';
            if (!$ward_id) {
                wp_send_json_error(array('message' => 'No ward ID provided'));
            }
            $name = $this->get_name_village($ward_id);
            if ($name) {
                wp_send_json_success(array('name' => $name));
            } else {
                wp_send_json_error(array('message' => 'Ward not found'));
            }
            die();
        }

        /**
         * Register the custom extension data schema for the WC Store API (Blocks checkout).
         * This allows the JS to send ward codes alongside the checkout request.
         */
        /**
         * Modify WooCommerce Blocks checkout fields to render SELECT for districts/wards
         */
        function coolviad_modify_blocks_checkout_fields($fields)
        {
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            $city_placeholder = ($schema === 'new')
                ? _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address')
                : _x('Select District', 'placeholder', 'coolbird-vietnam-address');

            // Force city and address_2 fields to be SELECT type in Blocks
            if (isset($fields['billing_city'])) {
                $fields['billing_city']['type'] = 'select';
                $fields['billing_city']['options'] = array(
                    '' => $city_placeholder
                );
            }

            if (isset($fields['shipping_city'])) {
                $fields['shipping_city']['type'] = 'select';
                $fields['shipping_city']['options'] = array(
                    '' => $city_placeholder
                );
            }

            if ($schema === 'new') {
                unset($fields['billing_address_2'], $fields['shipping_address_2']);
                return $fields;
            }

            if (isset($fields['billing_address_2'])) {
                $fields['billing_address_2']['type'] = 'select';
                $fields['billing_address_2']['options'] = array(
                    '' => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address')
                );
            }

            if (isset($fields['shipping_address_2'])) {
                $fields['shipping_address_2']['type'] = 'select';
                $fields['shipping_address_2']['options'] = array(
                    '' => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address')
                );
            }

            return $fields;
        }

        function coolviad_register_store_api_extension()
        {
            if (function_exists('woocommerce_store_api_register_endpoint_data')) {
                woocommerce_store_api_register_endpoint_data(array(
                    'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
                    'namespace'       => 'coolviad',
                    'schema_callback' => function () {
                        return array(
                            'shipping_ward_code' => array(
                                'description' => 'Shipping ward/commune code',
                                'type'        => 'string',
                                'context'     => array('view', 'edit'),
                                'optional'    => true,
                            ),
                            'billing_ward_code' => array(
                                'description' => 'Billing ward/commune code',
                                'type'        => 'string',
                                'context'     => array('view', 'edit'),
                                'optional'    => true,
                            ),
                        );
                    },
                ));
            }
        }

        /**
         * Save ward codes sent from the Blocks checkout (Store API) to order meta.
         */
        function coolviad_save_ward_from_blocks($order, $request)
        {
            $extensions = $request->get_param('extensions');
            if (empty($extensions['coolviad'])) return;
            $data = $extensions['coolviad'];
            if (!empty($data['shipping_ward_code'])) {
                $order->update_meta_data('_shipping_ward', wc_clean($data['shipping_ward_code']));
            }
            if (!empty($data['billing_ward_code'])) {
                $order->update_meta_data('_billing_ward', wc_clean($data['billing_ward_code']));
            }
        }

        function coolviad_normalize_store_api_checkout_request($response, $handler, $request)
        {
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            if ($schema !== 'new' || !($request instanceof WP_REST_Request)) {
                return $response;
            }

            $route = $request->get_route();
            if (strpos($route, '/wc/store/v1/checkout') !== 0) {
                return $response;
            }

            $normalize_address = function ($address, $address_type) {
                if (!is_array($address)) {
                    return $address;
                }

                $city = isset($address['city']) ? wc_clean(wp_unslash($address['city'])) : '';
                if (!$city) {
                    return $address;
                }

                $address['address_2'] = $city;
                $address[$address_type . '_address_2'] = $city;
                $address[$address_type . '_city'] = $city;
                return $address;
            };

            $billing_address = $normalize_address($request->get_param('billing_address'), 'billing');
            $shipping_address = $normalize_address($request->get_param('shipping_address'), 'shipping');

            if (is_array($billing_address)) {
                $request->set_param('billing_address', $billing_address);
            }

            if (is_array($shipping_address)) {
                $request->set_param('shipping_address', $shipping_address);
            }

            $extensions = $request->get_param('extensions');
            if (!is_array($extensions)) {
                $extensions = array();
            }

            $additional_fields = $request->get_param('additional_fields');
            if (!is_array($additional_fields)) {
                $additional_fields = array();
            }

            if (!isset($extensions['coolviad']) || !is_array($extensions['coolviad'])) {
                $extensions['coolviad'] = array();
            }

            if (!empty($billing_address['city'])) {
                $extensions['coolviad']['billing_ward_code'] = $billing_address['city'];
                $additional_fields['billing_address_2'] = $billing_address['city'];
                $request->set_param('billing_city', $billing_address['city']);
                $request->set_param('billing_address_2', $billing_address['city']);
            }

            if (!empty($shipping_address['city'])) {
                $extensions['coolviad']['shipping_ward_code'] = $shipping_address['city'];
                $additional_fields['shipping_address_2'] = $shipping_address['city'];
                $request->set_param('shipping_city', $shipping_address['city']);
                $request->set_param('shipping_address_2', $shipping_address['city']);
            }

            $request->set_param('extensions', $extensions);
            $request->set_param('additional_fields', $additional_fields);

            return $response;
        }

        private function get_schema_new_store_api_city_value($request, $address_type)
        {
            if (!($request instanceof WP_REST_Request)) {
                return '';
            }

            $address = $request->get_param($address_type . '_address');
            if (!is_array($address)) {
                return '';
            }

            $city = isset($address['city']) ? wc_clean(wp_unslash($address['city'])) : '';
            if ($city) {
                return $city;
            }

            $legacy_address_2 = isset($address['address_2']) ? wc_clean(wp_unslash($address['address_2'])) : '';
            return $legacy_address_2;
        }

        function coolviad_sync_schema_new_store_api_customer_fields($customer, $request)
        {
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            if ($schema !== 'new' || !($customer instanceof WC_Customer)) {
                return;
            }

            foreach (array('billing', 'shipping') as $address_type) {
                $city = $this->get_schema_new_store_api_city_value($request, $address_type);
                if (!$city) {
                    continue;
                }

                $city_setter = 'set_' . $address_type . '_city';
                if (is_callable(array($customer, $city_setter))) {
                    $customer->{$city_setter}($city);
                }

                $setter = 'set_' . $address_type . '_address_2';
                if (is_callable(array($customer, $setter))) {
                    $customer->{$setter}($city);
                }

                $customer->update_meta_data($address_type . '_city', $city);
                $customer->update_meta_data($address_type . '_address_2', $city);
            }
        }

        function coolviad_sync_schema_new_store_api_order_fields($order, $request)
        {
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            if ($schema !== 'new' || !($order instanceof WC_Order)) {
                return;
            }

            foreach (array('billing', 'shipping') as $address_type) {
                $city = $this->get_schema_new_store_api_city_value($request, $address_type);
                if (!$city) {
                    continue;
                }

                $city_setter = 'set_' . $address_type . '_city';
                if (is_callable(array($order, $city_setter))) {
                    $order->{$city_setter}($city);
                }

                $setter = 'set_' . $address_type . '_address_2';
                if (is_callable(array($order, $setter))) {
                    $order->{$setter}($city);
                }

                $order->update_meta_data($address_type . '_city', $city);
                $order->update_meta_data($address_type . '_address_2', $city);
            }
        }

        /**
         * Resolve district/ward IDs to names in Store API cart response
         * This ensures address cards in Blocks checkout show names instead of IDs
         */
        function coolviad_resolve_address_names_in_cart_response($response)
        {
            if (empty($response) || !is_array($response)) {
                return $response;
            }

            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';

            if ($schema === 'new') {
                $normalize_address = function ($address) {
                    if (!is_array($address)) {
                        return $address;
                    }

                    if (array_key_exists('address_2', $address)) {
                        $address['address_2'] = '';
                    }

                    return $address;
                };

                foreach (array('shipping_address', 'billing_address', 'shippingAddress', 'billingAddress') as $key) {
                    if (isset($response[$key])) {
                        $response[$key] = $normalize_address($response[$key]);
                    }
                }

                if (isset($response['customer']) && is_array($response['customer'])) {
                    foreach (array('shipping_address', 'billing_address', 'shippingAddress', 'billingAddress') as $key) {
                        if (isset($response['customer'][$key])) {
                            $response['customer'][$key] = $normalize_address($response['customer'][$key]);
                        }
                    }
                }
            }

            // Do NOT convert IDs to names in Store API responses.
            // Store API is used by WooCommerce Blocks checkout for edit forms,
            // which need raw IDs to match against SELECT option values.
            // Name conversion is handled separately for display purposes.

            return $response;
        }

        /**
         * Format address for display - resolves IDs to names
         */
        function coolviad_format_address_for_display($address, $order)
        {
            if (is_string($address)) {
                $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
                // If it's already a formatted string, check for numeric IDs
                if (preg_match('/\b\d{4,6}\b/', $address)) {
                    // Extract and replace IDs with names
                    $address = preg_replace_callback('/\b(\d{4,6})\b/', function ($matches) {
                        $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
                        if ($schema === 'new') {
                            $name = $this->get_name_district($matches[1]);
                            if ($name) return $name;
                            $name = $this->get_name_village($matches[1]);
                            if ($name) return $name;
                            return $matches[1];
                        }
                        $name = $this->get_name_district($matches[1]);
                        if ($name) return $name;
                        $name = $this->get_name_village($matches[1]);
                        if ($name) return $name;
                        return $matches[1];
                    }, $address);
                }
                if ($schema === 'new') {
                    $address = preg_replace('/,\s*,+/', ', ', $address);
                }
            }
            return $address;
        }

        /**
         * Format customer address for WooCommerce Blocks address card - resolves IDs to names
         */
        function coolviad_format_customer_address_for_blocks($formatted_address, $args, $customer)
        {
            if (empty($formatted_address) || !is_string($formatted_address)) {
                return $formatted_address;
            }

            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';

            // Replace numeric IDs with names (4-6 digit numbers)
            if (preg_match('/\b\d{4,6}\b/', $formatted_address)) {
                $formatted_address = preg_replace_callback('/\b(\d{4,6})\b/', function ($matches) {
                    $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
                    if ($schema === 'new') {
                        $name = $this->get_name_district($matches[1]);
                        if ($name) return $name;
                        $name = $this->get_name_village($matches[1]);
                        if ($name) return $name;
                        return $matches[1];
                    }
                    $name = $this->get_name_village($matches[1]);
                    if ($name) return $name;
                    $name = $this->get_name_district($matches[1]);
                    if ($name) return $name;
                    return $matches[1];
                }, $formatted_address);
            }

            if ($schema === 'new') {
                $formatted_address = preg_replace('/,\s*,+/', ', ', $formatted_address);
                $segments = array_values(array_filter(array_map('trim', explode(',', $formatted_address))));
                $deduped_segments = array();

                foreach ($segments as $segment) {
                    $last_segment = empty($deduped_segments) ? null : end($deduped_segments);
                    if ($last_segment !== $segment) {
                        $deduped_segments[] = $segment;
                    }
                }

                $formatted_address = implode(', ', $deduped_segments);
            }
            return $formatted_address;
        }

        /**
         * Convert city ID to name for customer address display
         */
        function coolviad_convert_city_id_to_name($city)
        {
            if (is_checkout()) {
                return $city;
            }
            if (is_numeric($city)) {
                $name = $this->get_name_district($city);
                if ($name) return $name;
            }
            return $city;
        }

        /**
         * Convert ward ID to name for customer address display
         */
        function coolviad_convert_ward_id_to_name($ward)
        {
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            if (is_checkout() || (defined('REST_REQUEST') && REST_REQUEST)) {
                return $ward;
            }
            if ($schema === 'new') {
                return '';
            }
            if (is_numeric($ward)) {
                $name = $this->get_name_village($ward);
                if ($name) return $name;
            }
            return $ward;
        }

        /**
         * Convert checkout value IDs to names for address display
         */
        function coolviad_convert_checkout_value($value, $input)
        {
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';

            // Checkout and edit forms must keep raw IDs so the selected option
            // matches the rendered <select> values. Only clear legacy address_2
            // when the new schema is active.
            if (
                $schema === 'new'
                && in_array($input, array('billing_address_2', 'shipping_address_2'), true)
            ) {
                return '';
            }

            return $value;
        }

        function coolviad_get_name_location($arg = array(), $id = '', $key = '')
        {
            if (is_array($arg) && !empty($arg)) {
                $nameQuan = $this->search_in_array($arg, $key, $id);
                $nameQuan = isset($nameQuan[0]['name']) ? $nameQuan[0]['name'] : '';
                return $nameQuan;
            }
            return false;
        }

        function get_name_city($id = '')
        {
            global $coolviad_provinces;
            $coolviad_provinces = apply_filters('coolviad_states_vn', $coolviad_provinces);
            if (is_numeric($id)) {
                $id_tinh = sprintf("%02d", intval($id));
                if (!is_array($coolviad_provinces) || empty($coolviad_provinces)) {
                    include COOLVIAD_PLUGIN_DIR . 'cities/provinces-legacy.php';
                }
            } else {
                $id_tinh = wc_clean(wp_unslash($id));
            }
            $province_name = (isset($coolviad_provinces[$id_tinh])) ? $coolviad_provinces[$id_tinh] : '';
            if (!$province_name) {
                include COOLVIAD_PLUGIN_DIR . 'cities/provinces-fallback.php';
                $province_name = (isset($coolviad_provinces[$id_tinh])) ? $coolviad_provinces[$id_tinh] : '';
            }
            return $province_name;
        }

        function get_name_district($id = '')
        {
            if (strlen($id) === 3) {
                include COOLVIAD_PLUGIN_DIR . 'cities/districts-legacy.php';
                $id_quan = sprintf("%03d", intval($id));
            } else {
                include COOLVIAD_PLUGIN_DIR . 'cities/districts.php';
                $id_quan = sprintf("%05d", intval($id));
            }
            if (is_array($quan_huyen) && !empty($quan_huyen)) {
                $nameQuan = $this->search_in_array($quan_huyen, 'maqh', $id_quan);
                $nameQuan = isset($nameQuan[0]['name']) ? $nameQuan[0]['name'] : '';
                return $nameQuan;
            }
            return false;
        }

        function get_name_village($id = '')
        {
            include COOLVIAD_PLUGIN_DIR . 'cities/wards.php';
            $id_xa = sprintf("%05d", intval($id));
            if (is_array($xa_phuong_thitran) && !empty($xa_phuong_thitran)) {
                $name = $this->search_in_array($xa_phuong_thitran, 'xaid', $id_xa);
                $name = isset($name[0]['name']) ? $name[0]['name'] : '';
                return $name;
            }
            return false;
        }

        function coolviad_woocommerce_localisation_address_formats($arg)
        {
            unset($arg['default']);
            unset($arg['VN']);
            $arg['default'] = "{name}\n{company}\n{address_1}\n{address_2}\n{city}\n{state}\n{country}";
            $arg['VN'] = "{name}\n{company}\n{address_1}\n{address_2}\n{city}\n{state}\n{country}";
            return $arg;
        }

        function coolviad_woocommerce_order_formatted_billing_address($eArg, $eThis)
        {

            if (!$eArg) return '';

            if ($this->coolviad_has_woocommerce_version()) {
                $orderID = $eThis->get_id();
            } else {
                $orderID = $eThis->id;
            }

            $nameTinh = $this->get_name_city($eThis->get_billing_state());
            $nameQuan = $this->get_name_district($eThis->get_billing_city());
            $nameXa = $this->get_name_village($eThis->get_billing_address_2());

            unset($eArg['state']);
            unset($eArg['city']);
            unset($eArg['address_2']);

            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';

            if ($schema === 'new') {
                // New schema: Province/City → District (phường/xã mới trong cities/districts.php).
                // address_2 is legacy, so ignore it and use only city.
                $eArg['state'] = $nameTinh;
                $eArg['city'] = $nameQuan;
                $eArg['address_2'] = '';
            } else {
                // Old schema: Province/City → District → Ward/Commune
                $eArg['state'] = $nameTinh;
                $eArg['city'] = $nameQuan;
                $eArg['address_2'] = $nameXa;
            }

            return $eArg;
        }

        function coolviad_woocommerce_order_formatted_shipping_address($eArg, $eThis)
        {

            if (!$eArg) return '';

            if ($this->coolviad_has_woocommerce_version()) {
                $orderID = $eThis->get_id();
            } else {
                $orderID = $eThis->id;
            }

            $nameTinh = $this->get_name_city($eThis->get_shipping_state());
            $nameQuan = $this->get_name_district($eThis->get_shipping_city());
            $nameXa = $this->get_name_village($eThis->get_shipping_address_2());

            unset($eArg['state']);
            unset($eArg['city']);
            unset($eArg['address_2']);

            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';

            if ($schema === 'new') {
                // New schema: Province/City → District (phường/xã mới trong cities/districts.php).
                // Ignore legacy ward meta.
                $eArg['state'] = $nameTinh;
                $eArg['city'] = $nameQuan;
                $eArg['address_2'] = '';
            } else {
                // Old schema: Province/City → District → Ward/Commune
                $eArg['state'] = $nameTinh;
                $eArg['city'] = $nameQuan;
                $eArg['address_2'] = $nameXa;
            }

            return $eArg;
        }

        function coolviad_woocommerce_my_account_my_address_formatted_address($args, $customer_id, $name)
        {

            if (!$args) return '';

            $nameTinh = $this->get_name_city(get_user_meta($customer_id, $name . '_state', true));
            $nameQuan = $this->get_name_district(get_user_meta($customer_id, $name . '_city', true));
            $nameXa = $this->get_name_village(get_user_meta($customer_id, $name . '_address_2', true));

            unset($args['address_2']);
            unset($args['city']);
            unset($args['state']);

            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';

            if ($schema === 'new') {
                // New schema: Province/City → District (phường/xã mới).
                // Only show district once; ignore legacy ward value.
                $args['state'] = $nameTinh;
                $args['city'] = $nameQuan;
                $args['address_2'] = '';
            } else {
                // Old schema: Province/City → District → Ward/Commune
                $args['state'] = $nameTinh;
                $args['city'] = $nameQuan;
                $args['address_2'] = $nameXa;
            }

            return $args;
        }

        function natorder($a, $b)
        {
            return strnatcasecmp($a['name'], $b['name']);
        }

        function get_list_district($matp = '')
        {
            if (!$matp) return false;

            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            if ($schema === 'new' && !is_numeric($matp)) {
                include COOLVIAD_PLUGIN_DIR . 'cities/districts.php';
                $matp = wc_clean(wp_unslash($matp));
                $result = $this->search_in_array($quan_huyen, 'matp', $matp);
                usort($result, array($this, 'natorder'));
                return $result;
            }

            // Original logic for old schema or numeric codes
            if (is_numeric($matp)) {
                include COOLVIAD_PLUGIN_DIR . 'cities/districts-legacy.php';
                $matp = sprintf("%02d", intval($matp));
            } else {
                include COOLVIAD_PLUGIN_DIR . 'cities/districts.php';
                $matp = wc_clean(wp_unslash($matp));
            }
            $result = $this->search_in_array($quan_huyen, 'matp', $matp);
            usort($result, array($this, 'natorder'));
            return $result;
        }

        function get_list_district_select($matp = '')
        {
            $district_select = array();
            $district_select_array = $this->get_list_district($matp);
            if ($district_select_array && is_array($district_select_array)) {
                foreach ($district_select_array as $district) {
                    $district_select[$district['maqh']] = $district['name'];
                }
            }
            return $district_select;
        }

        function get_list_village($maqh = '')
        {
            if (!$maqh) return false;
            include COOLVIAD_PLUGIN_DIR . 'cities/wards.php';
            $maqh_raw = wc_clean(wp_unslash($maqh));
            // Old dataset uses 3-digit district codes; new dataset uses 5-digit codes.
            if (strlen($maqh_raw) <= 3) {
                $maqh_key = sprintf("%03d", intval($maqh_raw));
            } else {
                $maqh_key = sprintf("%05d", intval($maqh_raw));
            }
            $result = $this->search_in_array($xa_phuong_thitran, 'maqh', $maqh_key);
            usort($result, array($this, 'natorder'));
            return $result;
        }

        function get_list_village_select($maqh = '')
        {
            $village_select = array();
            $village_select_array = $this->get_list_village($maqh);
            if ($village_select_array && is_array($village_select_array)) {
                foreach ($village_select_array as $village) {
                    $village_select[$village['xaid']] = $village['name'];
                }
            }
            return $village_select;
        }

        function coolviad_after_shipping_address($order)
        {
            echo '<p><label for="_shipping_phone">' . esc_html__('Phone number of the recipient', 'coolbird-vietnam-address') . ':</label> <br>
                <input type="text" class="short" style="" name="_shipping_phone" id="_shipping_phone" value="' . esc_attr($order->get_shipping_phone()) . '" placeholder=""></p>';
        }

        function coolviad_woocommerce_order_details_after_customer_details($order)
        {
            ob_start();
            $sdtnguoinhan = $order->get_shipping_phone();
            if ($sdtnguoinhan) : ?>
                <tr>
                    <th><?php esc_html_e('Shipping Phone:', 'coolbird-vietnam-address'); ?></th>
                    <td><?php echo esc_html($sdtnguoinhan); ?></td>
                </tr>
<?php endif;
            echo ob_get_clean();
        }

        public function get_options($option = 'active_village')
        {
            $flra_options = wp_parse_args(get_option($this->_optionName), $this->_defaultOptions);
            return isset($flra_options[$option]) ? $flra_options[$option] : false;
        }

        public function admin_enqueue_scripts($hook_suffix = '')
        {
            global $post, $pagenow;

            // Get current screen
            $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;

            // Enqueue options page CSS and JS when our settings page is active
            $options_page_screen_ids = array(
                'woocommerce_page_coolviad-district-address',
                'toplevel_page_coolviad-district-address',
            );
            $is_options_page = false;
            if ($current_screen && in_array($current_screen->id, $options_page_screen_ids, true)) {
                $is_options_page = true;
            }

            // The Shipping Rates page loads its own scripts via Coolviad_Shipping_Admin
            if ($current_screen && $current_screen->id === 'woocommerce_page_coolviad-shipping-rates') {
                return;
            }

            // Enqueue options page styles
            if ($is_options_page) {
                $css_file = COOLVIAD_PLUGIN_DIR . 'assets/css/admin-options.css';
                $css_url  = COOLVIAD_URL . 'assets/css/admin-options.css';
                $css_ver  = file_exists($css_file) ? filemtime($css_file) : COOLVIAD_VERSION_NUM;

                wp_enqueue_style(
                    'coolviad-admin-options',
                    $css_url,
                    array(),
                    $css_ver
                );

                // Register a dummy handle so we can attach inline JS to it
                wp_register_script(
                    'coolviad-admin-options',
                    false,
                    array(),
                    COOLVIAD_VERSION_NUM,
                    true
                );
                wp_enqueue_script('coolviad-admin-options');

                $tab_script = <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    var tabs = document.querySelectorAll('.coolviad-tab');
    var tabContents = document.querySelectorAll('.coolviad-tab-content');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault();

            var targetId = this.getAttribute('href').substring(1);

            // Remove active class from all tabs and contents
            tabs.forEach(function(t) { t.classList.remove('active'); });
            tabContents.forEach(function(c) { c.classList.remove('active'); });

            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            var targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
            }

            // Store active tab in localStorage
            localStorage.setItem('coolviad_active_tab', targetId);
        });
    });

    // Restore active tab from localStorage
    var savedTab = localStorage.getItem('coolviad_active_tab');
    if (savedTab) {
        var savedTabEl = document.querySelector('.coolviad-tab[href="#' + savedTab + '"]');
        if (savedTabEl) {
            tabs.forEach(function(t) { t.classList.remove('active'); });
            tabContents.forEach(function(c) { c.classList.remove('active'); });
            savedTabEl.classList.add('active');
            var savedContent = document.getElementById(savedTab);
            if (savedContent) {
                savedContent.classList.add('active');
            }
        }
    }
});
JS;
                wp_add_inline_script('coolviad-admin-options', $tab_script);
            }

            // Check if we're on an order edit page (both Classic Editor and HPOS)
            $is_order_edit_page = false;
            $order_id = 0;

            // Classic Editor: post.php or post-new.php
            if (($pagenow === 'post.php' || $pagenow === 'post-new.php') && isset($post->post_type) && $post->post_type === 'shop_order') {
                $is_order_edit_page = true;
                $order_id = $post->ID;
            }
            // HPOS: Woo Orders page (admin.php?page=wc-orders&action=edit&order_id=xxx)
            elseif ($current_screen && $current_screen->id === 'woocommerce_page_wc-orders') {
                $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
                if ($order_id > 0) {
                    $is_order_edit_page = true;
                }
            }

            // Enqueue React admin order bundle on order edit page
            if ($is_order_edit_page && $order_id > 0) {
                $react_js = COOLVIAD_PLUGIN_DIR . 'assets/dist/admin-order.js';
                if (file_exists($react_js)) {
                    wp_enqueue_script('coolviad_admin_order_react', COOLVIAD_URL . 'assets/dist/admin-order.js', array('jquery'), filemtime($react_js), true);

                    $react_css = COOLVIAD_PLUGIN_DIR . 'assets/dist/admin-order.css';
                    if (file_exists($react_css)) {
                        wp_enqueue_style('coolviad_admin_order_react', COOLVIAD_URL . 'assets/dist/admin-order.css', array(), filemtime($react_css));
                    }

                    global $coolviad_provinces;
                    $provinces = array();
                    if (isset($coolviad_provinces) && is_array($coolviad_provinces)) {
                        foreach ($coolviad_provinces as $code => $name) {
                            $provinces[] = array('code' => $code, 'name' => $name);
                        }
                    }

                    // Get order metadata (works for both classic and HPOS)
                    $billing_state = get_post_meta($order_id, '_billing_state', true);
                    $billing_city = get_post_meta($order_id, '_billing_city', true);
                    $shipping_state = get_post_meta($order_id, '_shipping_state', true);
                    $shipping_city = get_post_meta($order_id, '_shipping_city', true);

                    // Try HPOS if meta not found
                    if (empty($billing_state) && class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
                        $order = wc_get_order($order_id);
                        if ($order) {
                            $billing_state = $order->get_billing_state();
                            $billing_city = $order->get_billing_city();
                            $shipping_state = $order->get_shipping_state();
                            $shipping_city = $order->get_shipping_city();
                        }
                    }

                    wp_localize_script('coolviad_admin_order_react', 'coolviad_admin_order_data', array(
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'formatNoMatches' => __('No value', 'coolbird-vietnam-address'),
                        'provinces' => $provinces,
                        'billing_state' => $billing_state,
                        'billing_city' => $billing_city,
                        'shipping_state' => $shipping_state,
                        'shipping_city' => $shipping_city,
                        'i18n' => array(
                            'loading' => __('Loading...', 'coolbird-vietnam-address'),
                            'select_district' => _x('Select District', 'placeholder', 'coolbird-vietnam-address'),
                            'select_ward' => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address'),
                        ),
                    ));
                }
            }
        }

        /*Check version*/
        function coolviad_district_zone_shipping_check_woo_version($minimum_required = "2.6")
        {
            $woocommerce = WC();
            $version = $woocommerce->version;
            $active = version_compare($version, $minimum_required, "ge");
            return ($active);
        }


        function coolviad_sort_desc_array($input = array(), $keysort = 'dk')
        {
            $sort = array();
            if ($input && is_array($input)) {
                foreach ($input as $k => $v) {
                    $sort[$keysort][$k] = $v[$keysort];
                }
                array_multisort($sort[$keysort], SORT_DESC, $input);
            }
            return $input;
        }

        function coolviad_sort_asc_array($input = array(), $keysort = 'dk')
        {
            $sort = array();
            if ($input && is_array($input)) {
                foreach ($input as $k => $v) {
                    $sort[$keysort][$k] = $v[$keysort];
                }
                array_multisort($sort[$keysort], SORT_ASC, $input);
            }
            return $input;
        }

        function coolviad_format_key_array($input = array())
        {
            $output = array();
            if ($input && is_array($input)) {
                foreach ($input as $k => $v) {
                    $output[] = $v;
                }
            }
            return $output;
        }

        function coolviad_search_bigger_in_array($array, $key, $value)
        {
            $results = array();

            if (is_array($array)) {
                if (isset($array[$key]) && ($array[$key] <= $value)) {
                    $results[] = $array;
                }

                foreach ($array as $subarray) {
                    $results = array_merge($results, $this->coolviad_search_bigger_in_array($subarray, $key, $value));
                }
            }

            return $results;
        }

        function coolviad_search_bigger_in_array_weight($array, $key, $value)
        {
            $results = array();

            if (is_array($array)) {
                if (isset($array[$key]) && ($array[$key] >= $value)) {
                    $results[] = $array;
                }

                foreach ($array as $subarray) {
                    $results = array_merge($results, $this->coolviad_search_bigger_in_array_weight($subarray, $key, $value));
                }
            }

            return $results;
        }

        public static function plugin_action_links($links)
        {
            $action_links = array(
                'settings' => '<a href="' . admin_url('admin.php?page=coolviad-district-address') . '" title="' . esc_attr__('Settings', 'coolbird-vietnam-address') . '">' . esc_html__('Settings', 'coolbird-vietnam-address') . '</a>',
            );

            return array_merge($action_links, $links);
        }

        public function coolviad_has_woocommerce_version($version = '3.0.0')
        {
            if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, $version, '>=')) {
                return true;
            }
            return false;
        }

        function change_default_checkout_country($country = '', $customer = null)
        {
            $posted_shipping = wc_get_post_data_by_key('shipping_country', '');
            if ($posted_shipping) {
                return $posted_shipping;
            }
            $posted_billing = wc_get_post_data_by_key('billing_country', '');
            if ($posted_billing) {
                return $posted_billing;
            }
            if (!empty($country)) {
                return $country;
            }
            if ($customer instanceof WC_Customer) {
                $customer_country = $customer->get_shipping_country();
                if ($customer_country) {
                    return $customer_country;
                }
            }
            return 'VN';
        }

        private function is_vietnam_country($country)
        {
            if (!$this->get_options('enable_country')) {
                return true;
            }
            if (!$country) {
                return true;
            }
            return strtoupper($country) === 'VN';
        }

        private function get_vietnam_country_locale_fields()
        {
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            $field_s = array(
                'state' => array(
                    'label' => __('Province/City', 'coolbird-vietnam-address'),
                    'priority' => 41,
                    'required' => true,
                    'hidden' => false,
                ),
                'city' => array(
                    // In "old" schema: this is District (Quận/Huyện)
                    // In "new" schema: this contains Ward/Commune (Phường/Xã)
                    'label' => ($schema === 'new') ? __('Ward/Commune', 'coolbird-vietnam-address') : __('District', 'coolbird-vietnam-address'),
                    'priority' => 42,
                    'required' => true,
                    'hidden' => false,
                ),
            );
            // active_village = '1' means HIDE ward; empty (default) = show ward
            $hide_ward = (bool) $this->get_options('active_village');
            $ward_required = !$this->get_options('required_village');
            $field_s['address_2'] = array(
                'label' => __('Ward/Commune', 'coolbird-vietnam-address'),
                'priority' => 43,
                // In the new schema, we don't expose a separate ward field at all.
                // Keep it for legacy data but hide it on the frontend.
                'required' => ($schema === 'new') ? false : (!$hide_ward && $ward_required),
                'hidden'   => ($schema === 'new') ? true : $hide_ward,
            );
            $field_s['address_1'] = array(
                'priority' => 44,
                'hidden' => false,
            );
            return $field_s;
        }

        function coolviad_woocommerce_get_country_locale($args)
        {
            $field_s = $this->get_vietnam_country_locale_fields();
            $args['VN'] = $field_s;
            return $args;
        }

        function coolviad_woocommerce_get_country_locale_base($args)
        {
            $field_s = $this->get_vietnam_country_locale_fields();
            foreach (array('state', 'city', 'address_2', 'address_1') as $field_key) {
                if (!isset($field_s[$field_key]) || !is_array($field_s[$field_key])) {
                    continue;
                }

                $args[$field_key] = isset($args[$field_key]) && is_array($args[$field_key])
                    ? wp_parse_args($field_s[$field_key], $args[$field_key])
                    : $field_s[$field_key];
            }

            return $args;
        }

        function change_default_checkout_state()
        {
            $state = $this->get_options('tinhthanh_default');
            return ($state) ? $state : '01';
        }

        function coolviad_hide_shipping_when_shipdisable($rates)
        {
            $shipdisable = array();
            foreach ($rates as $rate_id => $rate) {
                if ('shipdisable' === $rate->id) {
                    $shipdisable[$rate_id] = $rate;
                    break;
                }
            }
            return !empty($shipdisable) ? $shipdisable : $rates;
        }

        function coolviad_custom_override_default_address_fields($address_fields)
        {
            $country = wc_get_post_data_by_key('country', '');
            if (!$country && WC()->customer) {
                $country = WC()->customer->get_billing_country();
                if (!$country) {
                    $country = WC()->customer->get_shipping_country();
                }
            }
            if (!$this->get_options('enable_firstname')) {
                unset($address_fields['first_name']);
                $address_fields['last_name'] = array(
                    'label' => __('Full name', 'coolbird-vietnam-address'),
                    'placeholder' => _x('Type Full name', 'placeholder', 'coolbird-vietnam-address'),
                    'required' => true,
                    'class' => array('form-row-wide'),
                    'clear' => true
                );
            }
            if (!$this->get_options('enable_postcode')) {
                unset($address_fields['postcode']);
            }
            if (!$this->is_vietnam_country($country)) {
                return $address_fields;
            }
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
            $address_1_field = isset($address_fields['address_1']) ? $address_fields['address_1'] : array();
            $address_fields['city'] = array(
                'label' => ($schema === 'new') ? __('Ward/Commune', 'coolbird-vietnam-address') : __('District', 'coolbird-vietnam-address'),
                'type' => 'select',
                'required' => true,
                'class' => array('form-row-wide'),
                'priority' => 20,
                'placeholder' => ($schema === 'new') ? _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address') : _x('Select District', 'placeholder', 'coolbird-vietnam-address'),
                'options' => array(
                    '' => ''
                ),
            );
            if ($schema === 'new') {
                unset($address_fields['address_2']);
            } elseif (!$this->get_options('active_village')) {
                $ward_required = !$this->get_options('required_village');
                $address_fields['address_2'] = array(
                    'label' => __('Ward/Commune', 'coolbird-vietnam-address'),
                    'type' => 'select',
                    'required' => $ward_required,
                    'class' => array('form-row-wide'),
                    'priority' => 25,
                    'placeholder' => _x('Select Ward/Commune', 'placeholder', 'coolbird-vietnam-address'),
                    'options' => array('' => ''),
                );
            } else {
                unset($address_fields['address_2']);
            }
            $address_fields['address_1']['class'] = array('form-row-wide');
            if (!empty($address_1_field)) {
                $address_fields['address_1'] = array_merge($address_1_field, $address_fields['address_1']);
                $address_fields['address_1']['class'] = array('form-row-wide');
            }

            // Reinsert address_1 after address_2 so consumers that preserve array order
            // render Province/City -> District -> Ward/Commune -> Address.
            $reordered_fields = array();
            foreach ($address_fields as $key => $field) {
                if ('address_1' === $key) {
                    continue;
                }
                $reordered_fields[$key] = $field;
                if ('address_2' === $key && isset($address_fields['address_1'])) {
                    $reordered_fields['address_1'] = $address_fields['address_1'];
                }
            }
            if (!isset($reordered_fields['address_1']) && isset($address_fields['address_1'])) {
                $reordered_fields['address_1'] = $address_fields['address_1'];
            }
            return $reordered_fields;
        }

        function coolviad_woocommerce_admin_billing_fields($billing_fields)
        {
            global $post;

            $legacy_order_post_id = isset($GLOBALS['thepostid']) ? absint($GLOBALS['thepostid']) : 0;
            $order = ($post instanceof WP_Post) ? wc_get_order($post->ID) : wc_get_order($legacy_order_post_id);

            $city = $district = '';
            if ($order && !is_wp_error($order)) {
                $city = $order->get_billing_state();
                $district = $order->get_billing_city();
            } elseif (isset($_GET['id'])) {
                $order_id = intval($_GET['id']);
                $order = wc_get_order($order_id);
                $city = $order->get_billing_state();
                $district = $order->get_billing_city();
            }

            $billing_fields = array(
                'first_name' => array(
                    'label' => __('First name', 'coolbird-vietnam-address'),
                    'show' => false,
                ),
                'last_name' => array(
                    'label' => __('Last name', 'coolbird-vietnam-address'),
                    'show' => false,
                ),
                'company' => array(
                    'label' => __('Company', 'coolbird-vietnam-address'),
                    'show' => false,
                ),
                'country' => array(
                    'label' => __('Country', 'coolbird-vietnam-address'),
                    'show' => false,
                    'class' => 'js_field-country select short',
                    'type' => 'select',
                    'options' => array('' => __('Select a country&hellip;', 'coolbird-vietnam-address')) + WC()->countries->get_allowed_countries(),
                ),
                'state' => array(
                    'label' => __('Province/City', 'coolbird-vietnam-address'),
                    'class' => 'js_field-state select short',
                    'show' => false,
                ),
                'city' => array(
                    'label' => __('District', 'coolbird-vietnam-address'),
                    'class' => 'js_field-city select short',
                    'type' => 'select',
                    'show' => false,
                    'options' => array('' => __('Select District&hellip;', 'coolbird-vietnam-address')) + $this->get_list_district_select($city),
                ),
                'address_2' => array(
                    'label' => __('Ward/Commune', 'coolbird-vietnam-address'),
                    'show' => false,
                    'class' => 'js_field-address_2 select short',
                    'type' => 'select',
                    'options' => array('' => __('Select Ward/Commune&hellip;', 'coolbird-vietnam-address')) + $this->get_list_village_select($district),
                ),
                'address_1' => array(
                    'label' => __('Address line 1', 'coolbird-vietnam-address'),
                    'show' => false,
                ),
                'email' => array(
                    'label' => __('Email address', 'coolbird-vietnam-address'),
                ),
                'phone' => array(
                    'label' => __('Phone', 'coolbird-vietnam-address'),
                )
            );
            unset($billing_fields['address_2']);
            return $billing_fields;
        }

        function coolviad_woocommerce_admin_shipping_fields($shipping_fields)
        {
            global $post;

            $legacy_order_post_id = isset($GLOBALS['thepostid']) ? absint($GLOBALS['thepostid']) : 0;
            $order = (empty($legacy_order_post_id) && $post instanceof WP_Post) ? wc_get_order($post->ID) : wc_get_order($legacy_order_post_id);

            $city = $district = '';
            if ($order && !is_wp_error($order)) {
                $city = $order->get_shipping_state();
                $district = $order->get_shipping_city();
            } elseif (isset($_GET['id'])) {
                $order_id = intval($_GET['id']);
                $order = wc_get_order($order_id);
                $city = $order->get_shipping_state();
                $district = $order->get_shipping_city();
            }

            $billing_fields = array(
                'first_name' => array(
                    'label' => __('First name', 'coolbird-vietnam-address'),
                    'show' => false,
                ),
                'last_name' => array(
                    'label' => __('Last name', 'coolbird-vietnam-address'),
                    'show' => false,
                ),
                'company' => array(
                    'label' => __('Company', 'coolbird-vietnam-address'),
                    'show' => false,
                ),
                'country' => array(
                    'label' => __('Country', 'coolbird-vietnam-address'),
                    'show' => false,
                    'type' => 'select',
                    'class' => 'js_field-country select short',
                    'options' => array('' => __('Select a country&hellip;', 'coolbird-vietnam-address')) + WC()->countries->get_shipping_countries(),
                ),
                'state' => array(
                    'label' => __('Province/City', 'coolbird-vietnam-address'),
                    'class' => 'js_field-state select short',
                    'show' => false,
                ),
                'city' => array(
                    'label' => __('District', 'coolbird-vietnam-address'),
                    'class' => 'js_field-city select short',
                    'type' => 'select',
                    'show' => false,
                    'options' => array('' => __('Select District&hellip;', 'coolbird-vietnam-address')) + $this->get_list_district_select($city),
                ),
                'address_1' => array(
                    'label' => __('Address line 1', 'coolbird-vietnam-address'),
                    'show' => false,
                ),
            );
            unset($billing_fields['address_2']);
            return $billing_fields;
        }

        function coolviad_woocommerce_form_field_select($field, $key, $args, $value)
        {
            // Handle billing_city and shipping_city fields (districts)
            // AND billing_address_2 and shipping_address_2 fields (wards)
            if (in_array($key, array('billing_city', 'shipping_city', 'billing_address_2', 'shipping_address_2'))) {
                // Determine if this is district or ward field
                $is_district = in_array($key, array('billing_city', 'shipping_city'));
                $is_ward = in_array($key, array('billing_address_2', 'shipping_address_2'));

                // On checkout page, render minimal select with placeholder for JavaScript to populate
                if (is_checkout()) {
                    // Set minimal placeholder option - JavaScript will load districts/wards dynamically
                    $args['options'] = array('' => ($args['placeholder']) ? $args['placeholder'] : __('Choose an option', 'coolbird-vietnam-address'));
                    $selected_value = ''; // No pre-selection on checkout
                } else {
                    // On My Account edit-address page, populate full options from saved parent field

                    if ($is_district) {
                        // For district field, get province to load districts
                        $state_key = 'billing_city' === $key ? 'billing_state' : 'shipping_state';
                        $state = '';

                        // Get from posted data or user meta
                        if (isset($_POST[$state_key])) {
                            $state = sanitize_text_field(wp_unslash($_POST[$state_key]));
                        } elseif (is_user_logged_in()) {
                            $user_id = get_current_user_id();
                            $state = get_user_meta($user_id, $state_key, true);
                        }

                        // Populate district options based on selected province
                        if ($state) {
                            $city = array('' => ($args['placeholder']) ? $args['placeholder'] : __('Choose an option', 'coolbird-vietnam-address')) + $this->get_list_district_select($state);
                            $args['options'] = $city;
                        }
                    } elseif ($is_ward) {
                        // For ward field, get district to load wards
                        $city_key = 'billing_address_2' === $key ? 'billing_city' : 'shipping_city';
                        $district = '';

                        // Get from posted data or user meta
                        if (isset($_POST[$city_key])) {
                            $district = sanitize_text_field(wp_unslash($_POST[$city_key]));
                        } elseif (is_user_logged_in()) {
                            $user_id = get_current_user_id();
                            $district = get_user_meta($user_id, $city_key, true);
                        }

                        // Populate ward options based on selected district
                        if ($district) {
                            $wards = array('' => ($args['placeholder']) ? $args['placeholder'] : __('Choose an option', 'coolbird-vietnam-address')) + $this->get_list_village_select($district);
                            $args['options'] = $wards;
                        }
                    }

                    // Set selected value - use passed $value parameter, or get from user meta as fallback
                    $selected_value = $value;
                    if (empty($selected_value) && is_user_logged_in()) {
                        $user_id = get_current_user_id();
                        $selected_value = get_user_meta($user_id, $key, true);
                    }
                }

                if ($args['required']) {
                    $args['class'][] = 'validate-required';
                    $required = ' <abbr class="required" title="' . esc_attr__('required', 'coolbird-vietnam-address') . '">*</abbr>';
                } else {
                    $required = '';
                }

                if (is_string($args['label_class'])) {
                    $args['label_class'] = array($args['label_class']);
                }

                // Custom attribute handling.
                $custom_attributes = array();
                $args['custom_attributes'] = array_filter((array)$args['custom_attributes'], 'strlen');

                if ($args['maxlength']) {
                    $args['custom_attributes']['maxlength'] = absint($args['maxlength']);
                }

                if (!empty($args['autocomplete'])) {
                    $args['custom_attributes']['autocomplete'] = $args['autocomplete'];
                }

                if (true === $args['autofocus']) {
                    $args['custom_attributes']['autofocus'] = 'autofocus';
                }

                if (!empty($args['custom_attributes']) && is_array($args['custom_attributes'])) {
                    foreach ($args['custom_attributes'] as $attribute => $attribute_value) {
                        $custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
                    }
                }

                if (!empty($args['validate'])) {
                    foreach ($args['validate'] as $validate) {
                        $args['class'][] = 'validate-' . $validate;
                    }
                }

                $label_id = $args['id'];
                $sort = $args['priority'] ? $args['priority'] : '';
                $field_container = '<p class="form-row %1$s" id="%2$s" data-priority="' . esc_attr($sort) . '">%3$s</p>';

                $options = $field = '';

                if (!empty($args['options'])) {
                    foreach ($args['options'] as $option_key => $option_text) {
                        if ('' === $option_key) {
                            // If we have a blank option, select2 needs a placeholder.
                            if (empty($args['placeholder'])) {
                                $args['placeholder'] = $option_text ? $option_text : __('Choose an option', 'coolbird-vietnam-address');
                            }
                            $custom_attributes[] = 'data-allow_clear="true"';
                        }
                        $options .= '<option value="' . esc_attr($option_key) . '" ' . selected($selected_value, $option_key, false) . '>' . esc_attr($option_text) . '</option>';
                    }

                    $field .= '<select name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" class="select ' . esc_attr(implode(' ', $args['input_class'])) . '" ' . implode(' ', $custom_attributes) . ' data-placeholder="' . esc_attr($args['placeholder']) . '">
                        ' . $options . '
                    </select>';
                }

                if (!empty($field)) {
                    $field_html = '';

                    if ($args['label'] && 'checkbox' != $args['type']) {
                        $field_html .= '<label for="' . esc_attr($label_id) . '" class="' . esc_attr(implode(' ', $args['label_class'])) . '">' . $args['label'] . $required . '</label>';
                    }

                    $field_html .= $field;

                    if ($args['description']) {
                        $field_html .= '<span class="description">' . esc_html($args['description']) . '</span>';
                    }

                    $container_class = esc_attr(implode(' ', $args['class']));
                    $container_id = esc_attr($args['id']) . '_field';
                    $field = sprintf($field_container, $container_class, $container_id, $field_html);
                }
                return $field;
            }
            return $field;
        }

        function convert_weight_to_kg($weight)
        {
            switch (get_option('woocommerce_weight_unit')) {
                case 'g':
                    $weight = $weight * 0.001;
                    break;
                case 'lbs':
                    $weight = $weight * 0.45359237;
                    break;
                case 'oz':
                    $weight = $weight * 0.02834952;
                    break;
            }
            return $weight; //return kg
        }

        function convert_dimension_to_cm($dimension)
        {
            switch (get_option('woocommerce_dimension_unit')) {
                case 'm':
                    $dimension = $dimension * 100;
                    break;
                case 'mm':
                    $dimension = $dimension * 0.1;
                    break;
                case 'in':
                    $dimension = $dimension * 2.54;
                case 'yd':
                    $dimension = $dimension * 91.44;
                    break;
            }
            return $dimension; //return cm
        }

        function coolviad_woocommerce_get_order_address($value, $type)
        {
            if ($type == 'billing' || $type == 'shipping') {
                $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';
                if (isset($value['state']) && $value['state']) {
                    $state = $value['state'];
                    $value['state'] = $this->get_name_city($state);
                }
                if (isset($value['city']) && $value['city']) {
                    $city = $value['city'];
                    $value['city'] = $this->get_name_district($city);
                }
                if (isset($value['address_2']) && $value['address_2']) {
                    if ($schema === 'new') {
                        $value['address_2'] = '';
                    } else {
                        $address_2 = $value['address_2'];
                        $value['address_2'] = $this->get_name_village($address_2);
                    }
                }
            }
            return $value;
        }

        function coolviad_woocommerce_rest_prepare_shop_order_object($response, $order, $request)
        {
            if (empty($response->data)) {
                return $response;
            }

            // Do NOT convert IDs to names in REST response.
            // REST API is used by WooCommerce Blocks checkout for edit forms,
            // which need raw IDs to match against SELECT option values.
            // Name conversion is handled separately for display purposes
            // (via formatted_address and customer getters).

            return $response;
        }

        function coolviad_woocommerce_api_order_response($order_data, $order)
        {
            // Do NOT convert IDs to names in API response.
            // API is used by checkout forms which need raw IDs to match against SELECT option values.
            // Name conversion is handled separately for display purposes.
            return $order_data;
        }

        function coolviad_modify_plugin_update_message($plugin_data, $response)
        {
            // Removed license notice
        }

        function coolviad_woocommerce_formatted_address_replacements($replace)
        {
            $schema = $this->get_options('address_schema') ? $this->get_options('address_schema') : 'new';

            if (isset($replace['{city}']) && is_numeric($replace['{city}'])) {
                $oldCity = $replace['{city}'];
                $replace['{city}'] = $this->get_name_district($oldCity);
            }

            if (isset($replace['{city_upper}']) && is_numeric($replace['{city_upper}'])) {
                $oldCityUpper = $replace['{city_upper}'];
                $replace['{city_upper}'] = strtoupper($this->get_name_district($oldCityUpper));
            }

            if ($schema === 'new') {
                $replace['{address_2}'] = '';
                $replace['{address_2_upper}'] = '';
            } elseif (isset($replace['{address_2}']) && is_numeric($replace['{address_2}'])) {
                $oldCity = $replace['{address_2}'];
                $replace['{address_2}'] = $this->get_name_village($oldCity);
            }

            if ($schema !== 'new' && isset($replace['{address_2_upper}']) && is_numeric($replace['{address_2_upper}'])) {
                $oldCityUpper = $replace['{address_2_upper}'];
                $replace['{address_2_upper}'] = strtoupper($this->get_name_village($oldCityUpper));
            }

            if (is_cart() && !is_checkout()) {
                $replace['{address_1}'] = '';
                $replace['{address_1_upper}'] = '';
                $replace['{address_2}'] = '';
                $replace['{address_2_upper}'] = '';
            }

            return $replace;
        }

        function save_shipping_phone_meta($order)
        {
            if (isset($_POST['_shipping_phone'])) {
                $order->update_meta_data('_shipping_phone', sanitize_text_field(wp_unslash($_POST['_shipping_phone'])));
            }
        }

        /**
         * Add type="module" to Vite-built ES module scripts.
         * Vite outputs native ES modules which require this attribute.
         */
        public function coolviad_set_module_type($tag, $handle)
        {
            $vite_handles = array(
                'coolviad_checkout_react',
                'coolviad_admin_order_react',
                'coolviad-admin-shipping',
            );
            if (in_array($handle, $vite_handles, true)) {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }

        function remove_http($url)
        {
            $disallowed = array('http://', 'https://', 'https://www.', 'http://www.');
            foreach ($disallowed as $d) {
                if (strpos($url, $d) === 0) {
                    return str_replace($d, '', $url);
                }
            }
            return $url;
        }
        function hpos_enabled()
        {
            return get_option('woocommerce_custom_orders_table_enabled') == 'no' ? false : true;
        }

        /**
         * Add Coolviad shipping method to Woo
         *
         * @param array $methods Existing shipping methods
         * @return array Modified shipping methods
         */
        public function add_coolviad_shipping_method($methods)
        {
            $methods['coolbird_vietnam_address_shipping'] = 'Coolviad_Shipping_Method';
            return $methods;
        }
    }

    function coolviad_up_to_pro()
    {
        // Removed pro version notice
    }

    function coolviad_vietnam_shipping()
    {
        return Coolviad_Address_Selectbox_Class::init();
    }

    coolviad_vietnam_shipping();

    function coolviad_round_up($value, $step)
    {
        if (intval($value) == $value) return $value;
        $value_int = intval($value);
        $value_float = $value - $value_int;
        if ($step == 0.5 && $value_float <= 0.5) {
            $output = $value_int + 0.5;
        } elseif ($step == 1 || ($step == 0.5 && $value_float > 0.5)) {
            $output = $value_int + 1;
        }
        return $output;
    }
}
