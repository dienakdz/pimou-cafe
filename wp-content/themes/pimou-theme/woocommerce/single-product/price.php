<?php
/**
 * Single product price.
 *
 * @package PimouTheme
 */

defined( 'ABSPATH' ) || exit;

global $product;

?>
<p class="<?php echo esc_attr( apply_filters( 'woocommerce_product_price_class', 'price pimou-single-product-price' ) ); ?>">
	<?php echo wp_kses_post( $product ? $product->get_price_html() : '' ); ?>
</p>
