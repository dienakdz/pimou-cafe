<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$flra_options = wp_parse_args(get_option($this->_optionName),$this->_defaultOptions);
if($flra_options['to_vnd']) {
    add_filter('woocommerce_currency_symbol', 'coolviad_change_existing_currency_symbol', 10, 2);
    function coolviad_change_existing_currency_symbol($currency_symbol, $currency)
    {
        switch ($currency) {
            case 'VND':
                $currency_symbol = 'VND';
                break;
        }
        return $currency_symbol;
    }
}
if($flra_options['remove_methob_title']){
    add_filter( 'woocommerce_cart_shipping_method_full_label', 'coolviad_remove_shipping_label', 10, 2 );
    function coolviad_remove_shipping_label($label, $method) {
        $new_label = preg_replace( '/^.+:/', '', $label );
        return $new_label;
    }
}
if($flra_options['freeship_remove_other_methob']){
    function coolviad_hide_shipping_when_free_is_available( $rates ) {
        $free = array();
        foreach ( $rates as $rate_id => $rate ) {
            if ( 'free_shipping' === $rate->method_id ) {
                $free[ $rate_id ] = $rate;
                break;
            }
        }
        return ! empty( $free ) ? $free : $rates;
    }
    add_filter( 'woocommerce_package_rates', 'coolviad_hide_shipping_when_free_is_available', 100 );
}
if($flra_options['active_vnd2usd']){
    include( __DIR__ . '/class-coolbird-vietnam-address-vnd-paypal-standard.php' );
    new Coolviad_Vnd_Paypal_Standard(
        $flra_options['vnd_usd_rate'],
        $flra_options['vnd2usd_currency']
    );
}
