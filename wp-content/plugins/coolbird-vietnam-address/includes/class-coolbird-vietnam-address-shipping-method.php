<?php

/**
 * Coolviad Shipping Method
 *
 * Calculates shipping fees based on Vietnam province/district/ward, region,
 * order total, and weight.
 *
 * Rate lookup priority (most-specific wins):
 *   ward  >  district  >  province  >  region  >  default rate
 *
 * Within each level the row with the highest `priority` value is used.
 */

if (!defined('ABSPATH')) {
    exit;
}

function coolviad_shipping_method_init()
{
    if (class_exists('Coolviad_Shipping_Method')) {
        return;
    }

    class Coolviad_Shipping_Method extends WC_Shipping_Method
    {
        public function __construct($instance_id = 0)
        {
            $this->id                 = 'coolbird_vietnam_address_shipping';
            $this->instance_id        = absint($instance_id);
            $this->method_title       = __('Vietnam Shipping Calculator', 'coolbird-vietnam-address');
            $this->method_description = __(
                'Shipping fees based on Vietnam province/district/ward, order total, and weight.',
                'coolbird-vietnam-address'
            );
            $this->supports = array('shipping-zones', 'instance-settings');

            $this->init();
        }

        private function init(): void
        {
            $this->init_form_fields();
            $this->init_settings();

            $this->title   = $this->get_option('title');
            $this->enabled = $this->get_option('enabled');

            add_action(
                'woocommerce_update_options_shipping_' . $this->id,
                array($this, 'process_admin_options')
            );
        }

        public function init_form_fields(): void
        {
            $rates_url = admin_url('admin.php?page=coolviad-shipping-rates');

            $this->instance_form_fields = array(
                'enabled' => array(
                    'title'   => __('Enable/Disable', 'coolbird-vietnam-address'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable this shipping method', 'coolbird-vietnam-address'),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title'       => __('Method Title', 'coolbird-vietnam-address'),
                    'type'        => 'text',
                    'description' => __('Title displayed during checkout.', 'coolbird-vietnam-address'),
                    'default'     => __('Vietnam Shipping', 'coolbird-vietnam-address'),
                    'desc_tip'    => true,
                ),
                'default_rate' => array(
                    'title'       => __('Default Shipping Rate', 'coolbird-vietnam-address'),
                    'type'        => 'text',
                    'description' => __('Fallback rate (VND) when no location rule matches.', 'coolbird-vietnam-address'),
                    'default'     => '30000',
                    'desc_tip'    => true,
                ),
                'manage_rates_link' => array(
                    'title'       => __('Manage Rates', 'coolbird-vietnam-address'),
                    'type'        => 'title',
                    'description' => sprintf(
                        /* translators: %s: URL to the rate manager page */
                        __('Configure shipping rates by region, province, district, and ward on the <a href="%s" style="font-weight:600">Shipping Rates</a> page (Woo → Shipping Rates).', 'coolbird-vietnam-address'),
                        esc_url($rates_url)
                    ),
                ),
            );
        }

        // ------------------------------------------------------------------ //
        // Main calculation entry-point
        // ------------------------------------------------------------------ //

        public function calculate_shipping($package = array()): void
        {
            $province = $package['destination']['state']   ?? '';
            $district = $package['destination']['city']    ?? '';
            $ward     = $package['destination']['address_2'] ?? '';

            $total_weight = $this->calculate_total_weight($package['contents']);
            $order_total  = WC()->cart ? WC()->cart->get_subtotal() : 0;

            $cost = $this->resolve_shipping_cost($province, $district, $ward, $total_weight, $order_total);

            $this->add_rate(array(
                'id'       => $this->get_rate_id(),
                'label'    => $this->title,
                'cost'     => $cost,
                'calc_tax' => 'per_order',
            ));
        }

        // ------------------------------------------------------------------ //
        // Cascading rate resolution
        // ------------------------------------------------------------------ //

        private function resolve_shipping_cost(
            string $province,
            string $district,
            string $ward,
            float  $weight,
            float  $order_total
        ): float {
            // 1. Ward
            if ($ward) {
                $rate = $this->find_rate('ward', $ward);
                if ($rate) {
                    return $this->apply_rules($rate, $weight, $order_total);
                }
            }

            // 2. District
            if ($district) {
                $rate = $this->find_rate('district', $district);
                if ($rate) {
                    return $this->apply_rules($rate, $weight, $order_total);
                }
            }

            // 3. Province
            if ($province) {
                $rate = $this->find_rate('province', $province);
                if ($rate) {
                    return $this->apply_rules($rate, $weight, $order_total);
                }
            }

            // 4. Region (resolve via Coolviad_Region_Manager)
            if ($province && class_exists('Coolviad_Region_Manager')) {
                $region_code = Coolviad_Region_Manager::get_region_for_province($province);
                if ($region_code) {
                    $rate = $this->find_rate('region', $region_code);
                    if ($rate) {
                        return $this->apply_rules($rate, $weight, $order_total);
                    }
                }
            }

            // 5. Default fallback
            return floatval($this->get_option('default_rate', 30000));
        }

        /**
         * Fetch the highest-priority rate row for a given location type + code.
         *
         * @return array|null
         */
        private function find_rate(string $type, string $code): ?array
        {
            global $wpdb;
            $table = $wpdb->prefix . 'coolviad_shipping_rates';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only SELECT on plugin-owned rates table; table name is plugin-controlled and escaped with esc_sql().
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM " . esc_sql($table) . "
                     WHERE location_type = %s AND location_code = %s
                     ORDER BY priority DESC, id DESC
                     LIMIT 1",
                    $type,
                    $code
                ),
                ARRAY_A
            );

            return $row ?: null;
        }

        // ------------------------------------------------------------------ //
        // Cost calculation helpers
        // ------------------------------------------------------------------ //

        /**
         * Apply weight tiers and order-total rules to arrive at the final cost.
         *
         * weight_calc_type:
         *   'replace' – weight tier price replaces base_rate entirely (default / legacy)
         *   'per_kg'  – weight tier price is a per-kg surcharge added on top of base_rate
         *
         * order_total_rules are applied AFTER weight tiers and can override the cost.
         * A rule matches when order_total is within [min_total, max_total].
         * max_total == 0 means "no upper bound".
         */
        private function apply_rules(array $rate, float $weight, float $order_total): float
        {
            $cost            = floatval($rate['base_rate']);
            $weight_calc     = $rate['weight_calc_type'] ?? 'replace';

            // --- Weight tiers ---
            $weight_tiers = !empty($rate['weight_tiers'])
                ? json_decode($rate['weight_tiers'], true)
                : array();

            if (is_array($weight_tiers)) {
                foreach ($weight_tiers as $tier) {
                    $min = floatval($tier['min'] ?? 0);
                    $max = floatval($tier['max'] ?? 0);

                    if ($weight >= $min && ($max == 0 || $weight <= $max)) {
                        $tier_price = floatval($tier['price'] ?? 0);
                        if ($weight_calc === 'per_kg') {
                            $cost += $weight * $tier_price;
                        } else {
                            $cost = $tier_price;
                        }
                        break;
                    }
                }
            }

            // --- Order total rules ---
            $order_total_rules = !empty($rate['order_total_rules'])
                ? json_decode($rate['order_total_rules'], true)
                : array();

            if (is_array($order_total_rules)) {
                foreach ($order_total_rules as $rule) {
                    $min_total = floatval($rule['min_total'] ?? 0);
                    $max_total = floatval($rule['max_total'] ?? 0);

                    if (
                        $order_total >= $min_total
                        && ($max_total == 0 || $order_total <= $max_total)
                    ) {
                        $cost = floatval($rule['shipping_fee'] ?? 0);
                        break;
                    }
                }
            }

            return max(0, $cost);
        }

        // ------------------------------------------------------------------ //
        // Weight conversion
        // ------------------------------------------------------------------ //

        private function calculate_total_weight(array $contents): float
        {
            $total = 0.0;
            foreach ($contents as $item) {
                $product = $item['data'];
                $weight  = $product->get_weight();
                if (!empty($weight)) {
                    $total += $this->to_kg(floatval($weight)) * intval($item['quantity']);
                }
            }
            return $total;
        }

        private function to_kg(float $weight): float
        {
            switch (get_option('woocommerce_weight_unit', 'kg')) {
                case 'g':
                    return $weight / 1000;
                case 'lbs':
                    return $weight * 0.453592;
                case 'oz':
                    return $weight * 0.0283495;
                default:
                    return $weight;
            }
        }
    }
}

add_action('woocommerce_shipping_init', 'coolviad_shipping_method_init');
