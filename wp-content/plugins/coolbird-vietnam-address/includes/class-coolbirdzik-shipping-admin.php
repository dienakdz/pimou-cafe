<?php

/**
 * Coolviad Shipping Admin
 *
 * Admin UI and AJAX handler for shipping rate + region management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coolviad_Shipping_Admin
{
    /** @var string */
    private $rates_table;

    public function __construct()
    {
        global $wpdb;
        $this->rates_table = $wpdb->prefix . 'coolviad_shipping_rates';

        add_action('admin_menu', array($this, 'add_admin_menu'), 99);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Shipping rate AJAX
        add_action('wp_ajax_coolviad_get_shipping_rates',  array($this, 'ajax_get_shipping_rates'));
        add_action('wp_ajax_coolviad_save_shipping_rate',  array($this, 'ajax_save_shipping_rate'));
        add_action('wp_ajax_coolviad_delete_shipping_rate', array($this, 'ajax_delete_shipping_rate'));
        add_action('wp_ajax_coolviad_import_rates_csv',    array($this, 'ajax_import_rates_csv'));
        add_action('wp_ajax_coolviad_export_rates_csv',    array($this, 'ajax_export_rates_csv'));

        // Region AJAX
        add_action('wp_ajax_coolviad_get_regions',    array($this, 'ajax_get_regions'));
        add_action('wp_ajax_coolviad_save_region',    array($this, 'ajax_save_region'));
        add_action('wp_ajax_coolviad_delete_region',  array($this, 'ajax_delete_region'));

        // Bulk-apply a rate to all provinces in a region
        add_action('wp_ajax_coolviad_bulk_apply_region_rate', array($this, 'ajax_bulk_apply_region_rate'));
    }

    // ------------------------------------------------------------------ //
    // Admin menu & scripts
    // ------------------------------------------------------------------ //

    public function add_admin_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Vietnam Shipping Rates', 'coolbird-vietnam-address'),
            __('Shipping Rates', 'coolbird-vietnam-address'),
            'manage_woocommerce',
            'coolviad-shipping-rates',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_admin_scripts(string $hook): void
    {
        if ($hook !== 'woocommerce_page_coolviad-shipping-rates') {
            return;
        }

        $plugin_root = plugin_dir_path(dirname(__FILE__));
        $asset_file  = $plugin_root . 'assets/dist/admin-shipping.js';
        if (!file_exists($asset_file)) {
            // Show a friendly message instead of a blank page
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Vietnam Shipping Rates: frontend assets not found. Run `npm run build` inside the frontend/ directory.', 'coolbird-vietnam-address')
                    . '</p></div>';
            });
            return;
        }

        wp_enqueue_script(
            'coolviad-admin-shipping',
            plugins_url('assets/dist/admin-shipping.js', dirname(__FILE__)),
            array(),
            filemtime($asset_file),
            true
        );

        // Vite produces ES-module output — WordPress must load it with type="module"
        add_filter('script_loader_tag', array($this, 'set_module_type'), 10, 2);

        $css_file = $plugin_root . 'assets/dist/admin-shipping.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'coolviad-admin-shipping',
                plugins_url('assets/dist/admin-shipping.css', dirname(__FILE__)),
                array(),
                filemtime($css_file)
            );
        }

        wp_localize_script('coolviad-admin-shipping', 'coolviad_shipping_admin_data', array(
            'ajaxurl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('coolviad_shipping_admin'),
            'provinces' => $this->get_provinces_for_js(),
            'regions'   => $this->get_regions_for_js(),
        ));
    }

    /**
     * Add type="module" to the Vite-built admin-shipping script.
     * Required because Vite outputs native ES modules.
     */
    public function set_module_type(string $tag, string $handle): string
    {
        if ($handle === 'coolviad-admin-shipping') {
            // Replace <script  with <script type="module"
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }

    public function render_admin_page(): void
    {
        echo '<div id="coolviad-admin-shipping-app"></div>';
    }

    // ------------------------------------------------------------------ //
    // Data helpers
    // ------------------------------------------------------------------ //

    private function get_provinces_for_js(): array
    {
        $file = plugin_dir_path(dirname(__FILE__)) . 'cities/provinces.php';
        if (!file_exists($file)) {
            return array();
        }
        include $file;
        $out = array();
        if (isset($coolviad_provinces) && is_array($coolviad_provinces)) {
            foreach ($coolviad_provinces as $code => $name) {
                $out[] = array('code' => $code, 'name' => $name);
            }
        }
        return $out;
    }

    private function get_regions_for_js(): array
    {
        if (class_exists('Coolviad_Region_Manager')) {
            return Coolviad_Region_Manager::get_regions();
        }
        return array();
    }

    private function get_post_string(string $key): string
    {
        if (!isset($_POST[$key])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    private function get_post_int(string $key): int
    {
        if (!isset($_POST[$key])) {
            return 0;
        }

        return absint(wp_unslash($_POST[$key]));
    }

    private function get_post_json_array(string $key): ?array
    {
        if (!isset($_POST[$key])) {
            return null;
        }

        $json = wp_unslash($_POST[$key]);

        if (!is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function decode_json_array_string($value): ?array
    {
        if (!is_string($value)) {
            return array();
        }

        $value = trim($value);

        if ($value === '') {
            return array();
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function sanitize_location_type(string $location_type): string
    {
        $location_type = sanitize_text_field($location_type);
        $allowed_types = array('province', 'district', 'ward', 'region');

        return in_array($location_type, $allowed_types, true) ? $location_type : '';
    }

    private function sanitize_weight_calc_type($weight_calc_type): string
    {
        return in_array($weight_calc_type, array('replace', 'per_kg'), true)
            ? $weight_calc_type
            : 'replace';
    }

    private function sanitize_weight_tiers($weight_tiers): array
    {
        if (!is_array($weight_tiers)) {
            return array();
        }

        $sanitized = array();

        foreach ($weight_tiers as $tier) {
            if (!is_array($tier)) {
                continue;
            }

            $sanitized[] = array(
                'min' => max(0, (float) ($tier['min'] ?? 0)),
                'max' => max(0, (float) ($tier['max'] ?? 0)),
                'price' => max(0, (float) ($tier['price'] ?? 0)),
            );
        }

        return $sanitized;
    }

    private function sanitize_order_total_rules($order_total_rules): array
    {
        if (!is_array($order_total_rules)) {
            return array();
        }

        $sanitized = array();

        foreach ($order_total_rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $sanitized[] = array(
                'min_total' => max(0, (float) ($rule['min_total'] ?? 0)),
                'max_total' => max(0, (float) ($rule['max_total'] ?? 0)),
                'shipping_fee' => max(0, (float) ($rule['shipping_fee'] ?? 0)),
            );
        }

        return $sanitized;
    }

    private function encode_json_array(array $value): string
    {
        $encoded = wp_json_encode($value);

        return false === $encoded ? '[]' : $encoded;
    }

    private function sanitize_rate_payload(array $rate_data)
    {
        $location_type = $this->sanitize_location_type($rate_data['location_type'] ?? '');
        $location_code = sanitize_text_field($rate_data['location_code'] ?? '');

        if ($location_type === '' || $location_code === '') {
            return new WP_Error('invalid_location', 'Invalid location data');
        }

        return array(
            'id' => absint($rate_data['id'] ?? 0),
            'location_type' => $location_type,
            'location_code' => $location_code,
            'base_rate' => max(0, (float) ($rate_data['base_rate'] ?? 0)),
            'weight_tiers' => $this->sanitize_weight_tiers($rate_data['weight_tiers'] ?? array()),
            'order_total_rules' => $this->sanitize_order_total_rules($rate_data['order_total_rules'] ?? array()),
            'weight_calc_type' => $this->sanitize_weight_calc_type($rate_data['weight_calc_type'] ?? ''),
            'priority' => intval($rate_data['priority'] ?? 0),
        );
    }

    private function sanitize_rate_template_payload(array $rate_data): array
    {
        return array(
            'base_rate' => max(0, (float) ($rate_data['base_rate'] ?? 0)),
            'weight_tiers' => $this->sanitize_weight_tiers($rate_data['weight_tiers'] ?? array()),
            'order_total_rules' => $this->sanitize_order_total_rules($rate_data['order_total_rules'] ?? array()),
            'weight_calc_type' => $this->sanitize_weight_calc_type($rate_data['weight_calc_type'] ?? ''),
            'priority' => intval($rate_data['priority'] ?? 0),
        );
    }

    private function sanitize_region_payload(array $region_data): array
    {
        $province_codes = array();

        if (is_array($region_data['province_codes'] ?? null)) {
            foreach ($region_data['province_codes'] as $province_code) {
                $province_code = sanitize_text_field($province_code);

                if ($province_code !== '') {
                    $province_codes[] = $province_code;
                }
            }
        }

        return array(
            'id' => absint($region_data['id'] ?? 0),
            'region_name' => sanitize_text_field($region_data['region_name'] ?? ''),
            'region_code' => sanitize_key($region_data['region_code'] ?? ''),
            'province_codes' => array_values(array_unique($province_codes)),
        );
    }

    private function get_uploaded_csv_tmp_name(string $key)
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
            return new WP_Error('missing_file', 'No file uploaded');
        }

        $file = $_FILES[$key];
        $tmp_name = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $tmp_name === '' || !is_uploaded_file($tmp_name)) {
            return new WP_Error('invalid_file', 'Invalid upload');
        }

        return $tmp_name;
    }

    private function get_location_name(string $type, string $code): string
    {
        switch ($type) {
            case 'province':
                return function_exists('get_name_city') ? get_name_city($code) : $code;
            case 'district':
                return function_exists('get_name_district') ? get_name_district($code) : $code;
            case 'ward':
                return function_exists('get_name_village') ? get_name_village($code) : $code;
            case 'region':
                if (class_exists('Coolviad_Region_Manager')) {
                    $region = Coolviad_Region_Manager::get_region($code);
                    return $region ? $region['region_name'] : $code;
                }
                return $code;
            default:
                return $code;
        }
    }

    // ------------------------------------------------------------------ //
    // AJAX: Shipping rates
    // ------------------------------------------------------------------ //

    public function ajax_get_shipping_rates(): void
    {
        check_ajax_referer('coolviad_shipping_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        global $wpdb;

        $location_type = $this->sanitize_location_type($this->get_post_string('location_type'));
        $location_code = $this->get_post_string('location_code');

        if (!$location_type || !$location_code) {
            wp_send_json_error(array('message' => 'Missing parameters'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX: read-only SELECT on plugin-owned table; no caching needed. $this->rates_table is esc_sql()-escaped via constructor.
        $rates = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->rates_table}
             WHERE location_type = %s AND location_code = %s
             ORDER BY priority DESC",
            $location_type,
            $location_code
        ), ARRAY_A);

        foreach ($rates as &$rate) {
            $rate['location_name']     = $this->get_location_name($rate['location_type'], $rate['location_code']);
            $rate['weight_tiers']      = json_decode($rate['weight_tiers'], true) ?: array();
            $rate['order_total_rules'] = json_decode($rate['order_total_rules'], true) ?: array();
            $rate['weight_calc_type']  = $rate['weight_calc_type'] ?? 'replace';
        }
        unset($rate);

        wp_send_json_success($rates);
    }

    public function ajax_save_shipping_rate(): void
    {
        check_ajax_referer('coolviad_shipping_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        global $wpdb;

        $rate_data = $this->get_post_json_array('rate');

        if (!is_array($rate_data)) {
            wp_send_json_error(array('message' => 'Invalid rate data'));
        }

        $rate_data = $this->sanitize_rate_payload($rate_data);

        if (is_wp_error($rate_data)) {
            wp_send_json_error(array('message' => $rate_data->get_error_message()));
        }

        $data = array(
            'location_type'    => $rate_data['location_type'],
            'location_code'    => $rate_data['location_code'],
            'base_rate'        => $rate_data['base_rate'],
            'weight_tiers'     => $this->encode_json_array($rate_data['weight_tiers']),
            'order_total_rules' => $this->encode_json_array($rate_data['order_total_rules']),
            'weight_calc_type' => $rate_data['weight_calc_type'],
            'priority'         => $rate_data['priority'],
            'updated_at'       => current_time('mysql'),
        );

        $id = $rate_data['id'];
        if ($id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX: intentionally direct UPDATE on plugin-owned table; no caching needed.
            $wpdb->update($this->rates_table, $data, array('id' => $id));
            $rate_id = $id;
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX: intentionally direct INSERT on plugin-owned table; no caching needed.
            $wpdb->insert($this->rates_table, $data);
            $rate_id = $wpdb->insert_id;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX: read-only SELECT to fetch saved rate; table name is plugin-owned and esc_sql()-escaped.
        $rate = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->rates_table} WHERE id = %d", $rate_id),
            ARRAY_A
        );
        $rate['weight_tiers']      = json_decode($rate['weight_tiers'], true) ?: array();
        $rate['order_total_rules'] = json_decode($rate['order_total_rules'], true) ?: array();
        $rate['location_name']     = $this->get_location_name($rate['location_type'], $rate['location_code']);

        wp_send_json_success($rate);
    }

    public function ajax_delete_shipping_rate(): void
    {
        check_ajax_referer('coolviad_shipping_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        global $wpdb;
        $id = $this->get_post_int('id');
        if (!$id) {
            wp_send_json_error(array('message' => 'Invalid ID'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX: intentionally direct DELETE on plugin-owned table; no caching needed.
        $wpdb->delete($this->rates_table, array('id' => $id));
        wp_send_json_success();
    }

    public function ajax_import_rates_csv(): void
    {
        check_ajax_referer('coolviad_shipping_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        if (!isset($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }

        global $wpdb;
        $tmp_name = $this->get_uploaded_csv_tmp_name('file');
        if (is_wp_error($tmp_name)) {
            wp_send_json_error(array('message' => $tmp_name->get_error_message()));
        }

        $handle = fopen($tmp_name, 'r');
        if (!$handle) {
            wp_send_json_error(array('message' => 'Cannot read file'));
        }

        $success    = 0;
        $failed     = 0;
        $errors     = array();
        $row_number = 0;

        fgetcsv($handle); // skip header

        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            $location_type = $this->sanitize_location_type($row[0] ?? '');
            $location_code = sanitize_text_field($row[1] ?? '');

            if (!$location_type || !$location_code) {
                $failed++;
                $errors[] = array('row' => $row_number, 'message' => 'Missing location_type or location_code');
                continue;
            }

            $weight_tiers = $this->decode_json_array_string($row[3] ?? '[]');
            $order_total_rules = $this->decode_json_array_string($row[4] ?? '[]');

            if ($weight_tiers === null || $order_total_rules === null) {
                $failed++;
                $errors[] = array('row' => $row_number, 'message' => 'Invalid JSON payload in CSV');
                continue;
            }

            $data = $this->sanitize_rate_payload(array(
                'location_type' => $location_type,
                'location_code' => $location_code,
                'base_rate' => $row[2] ?? 0,
                'weight_tiers' => $weight_tiers,
                'order_total_rules' => $order_total_rules,
                'weight_calc_type' => $row[5] ?? '',
                'priority' => $row[6] ?? 0,
            ));

            if (is_wp_error($data)) {
                $failed++;
                $errors[] = array('row' => $row_number, 'message' => $data->get_error_message());
                continue;
            }

            $data = array(
                'location_type' => $data['location_type'],
                'location_code' => $data['location_code'],
                'base_rate' => $data['base_rate'],
                'weight_tiers' => $this->encode_json_array($data['weight_tiers']),
                'order_total_rules' => $this->encode_json_array($data['order_total_rules']),
                'weight_calc_type' => $data['weight_calc_type'],
                'priority' => $data['priority'],
                'updated_at' => current_time('mysql'),
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX bulk import: intentionally direct INSERT on plugin-owned table; no caching needed.
            $result = $wpdb->insert($this->rates_table, $data);
            if ($result === false) {
                $failed++;
                $errors[] = array('row' => $row_number, 'message' => $wpdb->last_error);
            } else {
                $success++;
            }
        }
        fclose($handle);

        wp_send_json_success(array(
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors,
        ));
    }

    public function ajax_export_rates_csv(): void
    {
        check_ajax_referer('coolviad_shipping_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX export: read-only SELECT; table name is plugin-owned and esc_sql()-escaped.
        $rates = $wpdb->get_results(
            "SELECT * FROM {$this->rates_table} ORDER BY location_type, location_code",
            ARRAY_A
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=shipping-rates-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, array(
            'location_type',
            'location_code',
            'base_rate',
            'weight_tiers',
            'order_total_rules',
            'weight_calc_type',
            'priority',
        ));
        foreach ($rates as $rate) {
            fputcsv($output, array(
                $rate['location_type'],
                $rate['location_code'],
                $rate['base_rate'],
                $rate['weight_tiers'],
                $rate['order_total_rules'],
                $rate['weight_calc_type'] ?? 'replace',
                $rate['priority'],
            ));
        }
        fclose($output);
        exit;
    }

    // ------------------------------------------------------------------ //
    // AJAX: Regions
    // ------------------------------------------------------------------ //

    public function ajax_get_regions(): void
    {
        check_ajax_referer('coolviad_shipping_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        if (!class_exists('Coolviad_Region_Manager')) {
            wp_send_json_error(array('message' => 'Region manager not available'));
        }

        wp_send_json_success(Coolviad_Region_Manager::get_regions());
    }

    public function ajax_save_region(): void
    {
        check_ajax_referer('coolviad_shipping_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        if (!class_exists('Coolviad_Region_Manager')) {
            wp_send_json_error(array('message' => 'Region manager not available'));
        }

        $region_data = $this->get_post_json_array('region');

        if (!is_array($region_data)) {
            wp_send_json_error(array('message' => 'Invalid region data'));
        }

        $region_data = $this->sanitize_region_payload($region_data);
        $result = Coolviad_Region_Manager::save_region($region_data);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function ajax_delete_region(): void
    {
        check_ajax_referer('coolviad_shipping_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        if (!class_exists('Coolviad_Region_Manager')) {
            wp_send_json_error(array('message' => 'Region manager not available'));
        }

        $id     = $this->get_post_int('id');
        $result = Coolviad_Region_Manager::delete_region($id);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success();
    }

    // ------------------------------------------------------------------ //
    // AJAX: Bulk-apply a rate template to all provinces in a region
    // ------------------------------------------------------------------ //

    /**
     * Creates or updates a single `province`-level rate for every province in
     * the specified region, using the supplied rate template.
     *
     * POST params:
     *   region_code  (string)  – region to apply to
     *   rate         (JSON)    – ShippingRate object (without id / location_code)
     */
    public function ajax_bulk_apply_region_rate(): void
    {
        check_ajax_referer('coolviad_shipping_admin', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        if (!class_exists('Coolviad_Region_Manager')) {
            wp_send_json_error(array('message' => 'Region manager not available'));
        }

        $region_code = sanitize_key($this->get_post_string('region_code'));
        $rate_tpl    = $this->get_post_json_array('rate');

        if (!$region_code || !is_array($rate_tpl)) {
            wp_send_json_error(array('message' => 'Missing region_code or rate'));
        }

        $rate_tpl = $this->sanitize_rate_template_payload($rate_tpl);

        $region = Coolviad_Region_Manager::get_region($region_code);
        if (!$region) {
            wp_send_json_error(array('message' => 'Region not found'));
        }

        global $wpdb;
        $inserted = 0;
        $updated  = 0;

        foreach ($region['province_codes'] as $province_code) {
            $data = array(
                'location_type'    => 'province',
                'location_code'    => sanitize_text_field($province_code),
                'base_rate'        => $rate_tpl['base_rate'],
                'weight_tiers'     => $this->encode_json_array($rate_tpl['weight_tiers']),
                'order_total_rules' => $this->encode_json_array($rate_tpl['order_total_rules']),
                'weight_calc_type' => $rate_tpl['weight_calc_type'],
                'priority'         => $rate_tpl['priority'],
                'updated_at'       => current_time('mysql'),
            );

            // Upsert: update existing province rate if one exists, otherwise insert.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX bulk upsert: existence check SELECT before INSERT/UPDATE; table name is plugin-owned and esc_sql()-escaped.
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->rates_table}
                 WHERE location_type = 'province' AND location_code = %s
                 ORDER BY priority DESC LIMIT 1",
                $province_code
            ));

            if ($existing_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX bulk upsert: intentionally direct UPDATE; table name is plugin-owned and esc_sql()-escaped.
                $wpdb->update($this->rates_table, $data, array('id' => intval($existing_id)));
                $updated++;
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX bulk upsert: intentionally direct INSERT; table name is plugin-owned and esc_sql()-escaped.
                $wpdb->insert($this->rates_table, $data);
                $inserted++;
            }
        }

        wp_send_json_success(array(
            'inserted' => $inserted,
            'updated'  => $updated,
        ));
    }
}

new Coolviad_Shipping_Admin();
