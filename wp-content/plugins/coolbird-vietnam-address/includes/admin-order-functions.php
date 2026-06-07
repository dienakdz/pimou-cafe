<?php

/**
 * Admin Order Functions - Order Filtering
 *
 * Adds order filtering by date range and province/city to Woo Orders list.
 * Supports both Classic Editor and HPOS (High-Performance Order Storage).
 */

if (! defined('ABSPATH')) {
    exit;
}

class Coolviad_Order_Filter
{
    private $post_type_allow = array('shop_order');

    public function __construct()
    {
        // Only load if feature is enabled
        if (!coolviad_vietnam_shipping()->get_options('active_filter_order')) {
            return;
        }

        if (coolviad_vietnam_shipping()->hpos_enabled()) {
            // HPOS: Use Woo 7.3+ filters
            add_action('woocommerce_order_list_table_restrict_manage_orders', array($this, 'render_hpos_filters'), 10, 2);
            add_filter('woocommerce_shop_order_list_table_prepare_items_query_args', array($this, 'filter_hpos_query'));
        } else {
            // Classic Editor
            add_filter('months_dropdown_results', array($this, 'remove_month_filter'), 10, 2);
            add_action('restrict_manage_posts', array($this, 'render_classic_filters'));
            add_action('pre_get_posts', array($this, 'filter_classic_query'));
        }
    }

    /**
     * Remove month dropdown filter (we have our own date filter)
     */
    public function remove_month_filter($months, $post_type)
    {
        if (in_array($post_type, $this->post_type_allow)) {
            return array();
        }
        return $months;
    }

    /**
     * Render filters for Classic Editor (edit.php)
     */
    public function render_classic_filters()
    {
        global $typenow;
        if (!in_array($typenow, $this->post_type_allow)) {
            return;
        }

        $this->render_filter_html();
    }

    /**
     * Render filters for HPOS (admin.php?page=wc-orders)
     */
    public function render_hpos_filters($order_type, $which)
    {
        if ($order_type !== 'shop_order') {
            return;
        }

        if ('top' === $which) {
            $this->render_filter_html();
        }
    }

    /**
     * Render the filter HTML (shared between Classic and HPOS)
     */
    private function render_filter_html()
    {
        $from = isset($_GET['coolviad_date_from']) ? sanitize_text_field($_GET['coolviad_date_from']) : '';
        $to = isset($_GET['coolviad_date_to']) ? sanitize_text_field($_GET['coolviad_date_to']) : '';
        $billing_state = isset($_GET['coolviad_billing_state']) ? sanitize_text_field($_GET['coolviad_billing_state']) : '';
        $billing_city = isset($_GET['coolviad_billing_city']) ? sanitize_text_field($_GET['coolviad_billing_city']) : '';
?>

        <div class="coolviad-filter-row">
            <input type="date" name="coolviad_date_from" value="<?php echo esc_attr($from); ?>"
                placeholder="<?php esc_attr_e('From Date', 'coolbird-vietnam-address'); ?>" class="date-picker"
                style="width: 130px;">
        </div>

        <div class="coolviad-filter-row">
            <input type="date" name="coolviad_date_to" value="<?php echo esc_attr($to); ?>"
                placeholder="<?php esc_attr_e('To Date', 'coolbird-vietnam-address'); ?>" class="date-picker"
                style="width: 130px;">
        </div>

        <?php
        $country = new WC_Countries;
        $vn_states = $country->get_states('VN');
        if ($vn_states && is_array($vn_states)) :
        ?>
            <div class="coolviad-filter-row">
                <select name="coolviad_billing_state" id="coolviad_billing_state" style="width: 160px;">
                    <option value=""><?php esc_html_e('All Provinces', 'coolbird-vietnam-address'); ?></option>
                    <?php foreach ($vn_states as $code => $name) : ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($code, $billing_state); ?>>
                            <?php echo esc_html($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
<?php
    }

    /**
     * Filter query for Classic Editor
     */
    public function filter_classic_query($query)
    {
        global $pagenow, $typenow;

        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if (!in_array($pagenow, array('edit.php'))) {
            return;
        }

        if (!in_array($typenow, $this->post_type_allow)) {
            return;
        }

        // Date filter
        $date_from = isset($_GET['coolviad_date_from']) ? sanitize_text_field($_GET['coolviad_date_from']) : '';
        $date_to = isset($_GET['coolviad_date_to']) ? sanitize_text_field($_GET['coolviad_date_to']) : '';

        if ($date_from || $date_to) {
            $query->set('date_query', array(
                'after' => $date_from ? $date_from : '',
                'before' => $date_to ? $date_to : '',
                'inclusive' => true,
                'column' => 'post_date',
            ));
        }

        // Province filter
        $billing_state = isset($_GET['coolviad_billing_state']) ? sanitize_text_field($_GET['coolviad_billing_state']) : '';

        if ($billing_state) {
            $meta_query = $query->get('meta_query') ?: array();
            $meta_query['relation'] = 'AND';
            $meta_query[] = array(
                'key' => '_billing_state',
                'value' => $billing_state,
            );
            $query->set('meta_query', $meta_query);
        }
    }

    /**
     * Filter query for HPOS
     */
    public function filter_hpos_query($query_args)
    {
        // Date filter
        $date_from = isset($_GET['coolviad_date_from']) ? sanitize_text_field($_GET['coolviad_date_from']) : '';
        $date_to = isset($_GET['coolviad_date_to']) ? sanitize_text_field($_GET['coolviad_date_to']) : '';

        if ($date_from) {
            $query_args['date_after'] = $date_from;
        }
        if ($date_to) {
            $query_args['date_before'] = $date_to;
        }

        // Province filter
        $billing_state = isset($_GET['coolviad_billing_state']) ? sanitize_text_field($_GET['coolviad_billing_state']) : '';

        if ($billing_state) {
            $query_args['billing_state'] = $billing_state;

            // Also filter by city if selected
            $billing_city = isset($_GET['coolviad_billing_city']) ? sanitize_text_field($_GET['coolviad_billing_city']) : '';
            if ($billing_city) {
                $query_args['billing_city'] = $billing_city;
            }
        }

        return $query_args;
    }
}

new Coolviad_Order_Filter();
