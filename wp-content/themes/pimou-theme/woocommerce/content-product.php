<?php
/**
 * Product content in loops.
 *
 * @package PimouTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

pimou_product_card( $product->get_id() );

