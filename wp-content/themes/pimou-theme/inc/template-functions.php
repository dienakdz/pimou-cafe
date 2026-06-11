<?php
/**
 * Template helpers.
 *
 * @package PimouTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pimou_get_hotline() {
	return get_theme_mod( 'pimou_hotline', '0909 000 999' );
}

function pimou_get_hotline_href() {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', pimou_get_hotline() );
}

function pimou_get_zalo_url() {
	return get_theme_mod( 'pimou_zalo_url', 'https://zalo.me/0909000999' );
}

function pimou_get_address() {
	return get_theme_mod( 'pimou_address', 'TP. Ho Chi Minh, Viet Nam' );
}

function pimou_get_banner_image_url() {
	return get_theme_mod( 'pimou_banner_image', '' );
}

function pimou_get_banner_link_url() {
	return get_theme_mod( 'pimou_banner_link', '' );
}

function pimou_get_home_banners() {
	$banners = array();

	for ( $banner_index = 1; $banner_index <= 5; $banner_index++ ) {
		$image_setting = 1 === $banner_index ? 'pimou_banner_image' : 'pimou_banner_image_' . $banner_index;
		$link_setting  = 1 === $banner_index ? 'pimou_banner_link' : 'pimou_banner_link_' . $banner_index;
		$image         = get_theme_mod( $image_setting, '' );

		if ( ! $image ) {
			continue;
		}

		$banners[] = array(
			'image' => $image,
			'link'  => get_theme_mod( $link_setting, '' ),
		);
	}

	return $banners;
}

function pimou_get_home_category_limit() {
	return max( 1, min( 12, absint( get_theme_mod( 'pimou_home_category_limit', 4 ) ) ) );
}

function pimou_get_home_products_per_category() {
	return max( 1, min( 12, absint( get_theme_mod( 'pimou_home_products_per_category', 4 ) ) ) );
}

function pimou_site_logo( $class = '' ) {
	$classes = trim( 'pimou-site-logo ' . $class );

	if ( has_custom_logo() ) {
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		$logo           = wp_get_attachment_image(
			$custom_logo_id,
			'full',
			false,
			array(
				'class' => $classes,
				'alt'   => get_bloginfo( 'name' ),
			)
		);

		if ( $logo ) {
			echo wp_kses_post( $logo );
			return;
		}
	}

	echo '<img class="' . esc_attr( $classes ) . '" src="' . esc_url( get_template_directory_uri() . '/assets/images/logo.png' ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
}

function pimou_primary_menu_fallback() {
	$items = array(
		array( __( 'Trang chủ', 'pimou-theme' ), home_url( '/' ) ),
		array( __( 'Cửa hàng', 'pimou-theme' ), function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ),
		array( __( 'Cà phê', 'pimou-theme' ), pimou_get_product_category_link_by_name( 'Sản phẩm cà phê' ) ),
		array( __( 'Máy pha', 'pimou-theme' ), pimou_get_product_category_link_by_name( 'Sản phẩm máy pha cà phê' ) ),
	);

	echo '<ul class="pimou-menu">';
	foreach ( $items as $item ) {
		echo '<li><a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a></li>';
	}
	echo '</ul>';
}

function pimou_get_product_category_link_by_name( $name ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return home_url( '/' );
	}

	$term = get_term_by( 'name', $name, 'product_cat' );
	if ( ! $term || is_wp_error( $term ) ) {
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	}

	$link = get_term_link( $term );
	if ( is_wp_error( $link ) ) {
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	}

	return $link;
}

function pimou_product_card( $product_id = null ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return;
	}

	$product = wc_get_product( $product_id ? $product_id : get_the_ID() );
	if ( ! $product ) {
		return;
	}

	$GLOBALS['product'] = $product;
	get_template_part( 'template-parts/product-card', null, array( 'product' => $product ) );
}

function pimou_home_product_categories( $limit = 4 ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}

	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'orderby'    => 'menu_order',
		'order'      => 'ASC',
		'number'     => $limit,
	) );

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	return $terms;
}

function pimou_product_category_query( $term_id, $posts_per_page = 4 ) {
	return new WP_Query( array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => $posts_per_page,
		'ignore_sticky_posts' => true,
		'orderby'             => 'menu_order title',
		'order'               => 'ASC',
		'tax_query'           => array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => array( absint( $term_id ) ),
			),
		),
	) );
}

function pimou_feature_items() {
	return array(
		array( 'icon' => 'bean', 'title' => __( 'Cà phê chọn lọc', 'pimou-theme' ), 'text' => __( 'Tập trung vào hương vị ổn định, dễ pha và phù hợp gu thưởng thức hằng ngày.', 'pimou-theme' ) ),
		array( 'icon' => 'truck', 'title' => __( 'Giao hàng rõ ràng', 'pimou-theme' ), 'text' => __( 'Quy trình đặt hàng đơn giản, hỗ trợ COD và chuyển khoản ngân hàng.', 'pimou-theme' ) ),
		array( 'icon' => 'phone', 'title' => __( 'Tư vấn nhanh', 'pimou-theme' ), 'text' => __( 'Hotline và Zalo luôn hiện ở các điểm mua hàng quan trọng.', 'pimou-theme' ) ),
	);
}
