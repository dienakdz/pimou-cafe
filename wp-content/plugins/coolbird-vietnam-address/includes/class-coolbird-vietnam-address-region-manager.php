<?php

/**
 * Coolviad Region Manager
 *
 * Manages shipping regions (groups of provinces) for Vietnam shipping calculations.
 * Rate lookup priority: ward > district > province > region > default.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coolviad_Region_Manager
{
    /** @var string DB table name */
    private static $table_name;

    /**
     * Return the full table name, initialised lazily.
     */
    private static function table(): string
    {
        global $wpdb;
        if (empty(self::$table_name)) {
            self::$table_name = $wpdb->prefix . 'coolviad_shipping_regions';
        }
        return self::$table_name;
    }

    // -------------------------------------------------------------------------
    // Public read helpers
    // -------------------------------------------------------------------------

    /**
     * Get all regions (predefined + custom).
     *
     * @return array
     */
    public static function get_regions(): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only SELECT on plugin-owned table; table name escaped with esc_sql().
        $rows = $wpdb->get_results(
            'SELECT * FROM ' . esc_sql(self::table()) . ' ORDER BY is_predefined DESC, region_name ASC',
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row['province_codes'] = json_decode($row['province_codes'], true) ?: array();
            $row['is_predefined']  = (bool) $row['is_predefined'];
        }
        unset($row);

        return $rows ?: array();
    }

    /**
     * Get a single region by code.
     *
     * @param string $region_code
     * @return array|null
     */
    public static function get_region(string $region_code): ?array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only SELECT by unique key; table name escaped with esc_sql().
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . esc_sql(self::table()) . ' WHERE region_code = %s', $region_code),
            ARRAY_A
        );
        if (!$row) {
            return null;
        }
        $row['province_codes'] = json_decode($row['province_codes'], true) ?: array();
        $row['is_predefined']  = (bool) $row['is_predefined'];
        return $row;
    }

    /**
     * Given a province code, return the region_code that contains it (or null).
     *
     * Used by the shipping rate engine to fall back from province → region.
     *
     * @param string $province_code
     * @return string|null
     */
    public static function get_region_for_province(string $province_code): ?string
    {
        global $wpdb;

        // Fetch all regions; the dataset is small so a full scan is acceptable.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only SELECT on small plugin table; table name escaped with esc_sql().
        $rows = $wpdb->get_results(
            'SELECT region_code, province_codes FROM ' . esc_sql(self::table()),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $codes = json_decode($row['province_codes'], true) ?: array();
            if (in_array($province_code, $codes, true)) {
                return $row['region_code'];
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // CRUD
    // -------------------------------------------------------------------------

    /**
     * Save (insert or update) a region.
     *
     * @param array $data  Keys: region_name, region_code, province_codes (array), id (optional)
     * @return array|WP_Error Saved region row or WP_Error on failure.
     */
    public static function save_region(array $data)
    {
        global $wpdb;

        $region_name    = sanitize_text_field($data['region_name'] ?? '');
        $region_code    = sanitize_key($data['region_code'] ?? '');
        $province_codes = is_array($data['province_codes']) ? $data['province_codes'] : array();

        if (!$region_name || !$region_code) {
            return new WP_Error('missing_fields', __('Region name and code are required.', 'coolbird-vietnam-address'));
        }

        $row = array(
            'region_name'    => $region_name,
            'region_code'    => $region_code,
            'province_codes' => json_encode(array_values(array_map('sanitize_text_field', $province_codes))),
            'is_predefined'  => 0,
            'updated_at'     => current_time('mysql'),
        );

        $id = isset($data['id']) ? intval($data['id']) : 0;

        if ($id) {
            // Prevent editing predefined regions
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Validation SELECT before UPDATE; table name escaped with esc_sql().
            $existing = $wpdb->get_row(
                $wpdb->prepare('SELECT is_predefined FROM ' . esc_sql(self::table()) . ' WHERE id = %d', $id),
                ARRAY_A
            );
            if ($existing && $existing['is_predefined']) {
                return new WP_Error('readonly', __('Predefined regions cannot be modified.', 'coolbird-vietnam-address'));
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentionally direct UPDATE on plugin-owned table; no caching needed.
            $wpdb->update(self::table(), $row, array('id' => $id));
        } else {
            // Prevent duplicate region_code
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Validation SELECT before INSERT; table name escaped with esc_sql().
            $dup = $wpdb->get_var(
                $wpdb->prepare('SELECT id FROM ' . esc_sql(self::table()) . ' WHERE region_code = %s', $region_code)
            );
            if ($dup) {
                return new WP_Error('duplicate', __('A region with that code already exists.', 'coolbird-vietnam-address'));
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Intentionally direct INSERT for new region.
            $wpdb->insert(self::table(), $row);
            $id = $wpdb->insert_id;
        }

        return self::get_region($region_code);
    }

    /**
     * Delete a custom region by ID.
     *
     * @param int $id
     * @return true|WP_Error
     */
    public static function delete_region(int $id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Validation SELECT before DELETE; table name escaped with esc_sql().
        $existing = $wpdb->get_row(
            $wpdb->prepare('SELECT is_predefined FROM ' . esc_sql(self::table()) . ' WHERE id = %d', $id),
            ARRAY_A
        );

        if (!$existing) {
            return new WP_Error('not_found', __('Region not found.', 'coolbird-vietnam-address'));
        }

        if ($existing['is_predefined']) {
            return new WP_Error('readonly', __('Predefined regions cannot be deleted.', 'coolbird-vietnam-address'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentionally direct DELETE on plugin-owned table; no caching needed.
        $wpdb->delete(self::table(), array('id' => $id));

        return true;
    }
}
