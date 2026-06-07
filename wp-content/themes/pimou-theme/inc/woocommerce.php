<?php
/**
 * WooCommerce integration.
 *
 * @package PimouTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pimou_woocommerce_script_texts() {
	wp_add_inline_script(
		'wc-add-to-cart',
		"if ( window.wc_add_to_cart_params ) { window.wc_add_to_cart_params.i18n_view_cart = 'Xem giỏ hàng'; }",
		'after'
	);
}
add_action( 'wp_enqueue_scripts', 'pimou_woocommerce_script_texts', 20 );

function pimou_cart_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}

	return WC()->cart->get_cart_contents_count();
}

function pimou_cart_link() {
	if ( function_exists( 'wc_get_cart_url' ) ) {
		return wc_get_cart_url();
	}

	return home_url( '/' );
}

function pimou_cart_fragment( $fragments ) {
	$fragments['span.pimou-cart-count'] = '<span class="pimou-cart-count">' . esc_html( pimou_cart_count() ) . '</span>';
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'pimou_cart_fragment' );

function pimou_wc_category_in_loop() {
	global $product;

	if ( ! $product ) {
		return;
	}

	$terms = wc_get_product_category_list( $product->get_id(), ', ' );
	if ( $terms ) {
		echo '<div class="pimou-loop-category">' . wp_kses_post( $terms ) . '</div>';
	}
}
add_action( 'woocommerce_before_shop_loop_item_title', 'pimou_wc_category_in_loop', 8 );

function pimou_wc_loop_wrapper_start() {
	echo '<div class="pimou-shop-toolbar">';
}
add_action( 'woocommerce_before_shop_loop', 'pimou_wc_loop_wrapper_start', 15 );

function pimou_wc_loop_wrapper_end() {
	echo '</div>';
}
add_action( 'woocommerce_before_shop_loop', 'pimou_wc_loop_wrapper_end', 35 );

remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

function pimou_wc_product_actions() {
	echo '<div class="pimou-product-contact">';
	echo '<a class="pimou-btn pimou-btn-outline" href="' . esc_url( pimou_get_hotline_href() ) . '">' . esc_html__( 'Gọi tư vấn', 'pimou-theme' ) . '</a>';
	echo '<a class="pimou-btn pimou-btn-zalo" href="' . esc_url( pimou_get_zalo_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Nhắn Zalo', 'pimou-theme' ) . '</a>';
	echo '</div>';
}
add_action( 'woocommerce_single_product_summary', 'pimou_wc_product_actions', 35 );

add_filter( 'woocommerce_enable_order_notes_field', '__return_true' );
add_filter( 'pre_option_woocommerce_enable_order_comments', '__return_true' );

function pimou_checkout_order_notes_field( $fields ) {
	if ( isset( $fields['order']['order_comments'] ) ) {
		return $fields;
	}

	$fields['order']['order_comments'] = array(
		'type'        => 'textarea',
		'class'       => array( 'form-row-wide' ),
		'label'       => __( 'Ghi chú đơn hàng', 'pimou-theme' ),
		'placeholder' => __( 'Ghi chú về thời gian nhận hàng hoặc yêu cầu khác.', 'pimou-theme' ),
		'required'    => false,
		'priority'    => 10,
	);

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'pimou_checkout_order_notes_field' );

function pimou_woocommerce_order_received_title() {
	return __( 'Đặt hàng thành công', 'pimou-theme' );
}
add_filter( 'woocommerce_endpoint_order-received_title', 'pimou_woocommerce_order_received_title' );

function pimou_woocommerce_order_received_text( $text, $order ) {
	if ( $order ) {
		return __( 'Cảm ơn bạn. Đơn hàng của bạn đã được tiếp nhận.', 'pimou-theme' );
	}

	return $text;
}
add_filter( 'woocommerce_thankyou_order_received_text', 'pimou_woocommerce_order_received_text', 10, 2 );

function pimou_woocommerce_shipped_via_text( $html, $order ) {
	$method = $order ? $order->get_shipping_method() : '';

	if ( ! $method ) {
		return '';
	}

	return '&nbsp;<small class="shipped_via">- ' . esc_html( $method ) . '</small>';
}
add_filter( 'woocommerce_order_shipping_to_display_shipped_via', 'pimou_woocommerce_shipped_via_text', 10, 2 );

function pimou_woocommerce_frontend_texts( $translation, $text, $domain ) {
	if ( 'woocommerce' !== $domain || ( is_admin() && ! wp_doing_ajax() ) ) {
		return $translation;
	}

	$texts = array(
		'Order received'                           => 'Đặt hàng thành công',
		'Thank you. Your order has been received.' => 'Cảm ơn bạn. Đơn hàng của bạn đã được tiếp nhận.',
		'Order number:'                           => 'Mã đơn hàng:',
		'Date:'                                   => 'Ngày đặt:',
		'Total:'                                  => 'Tổng cộng:',
		'Payment method:'                         => 'Phương thức thanh toán:',
		'Order details'                           => 'Chi tiết đơn hàng',
		'Product'                                 => 'Sản phẩm',
		'Subtotal:'                               => 'Tạm tính:',
		'Shipping:'                               => 'Phí giao hàng:',
		'Billing address'                         => 'Địa chỉ thanh toán',
		'Shipping address'                        => 'Địa chỉ giao hàng',
		'Email address'                           => 'Email',
		'Phone'                                   => 'Số điện thoại',
	);

	return isset( $texts[ $text ] ) ? $texts[ $text ] : $translation;
}
add_filter( 'gettext', 'pimou_woocommerce_frontend_texts', 10, 3 );

function pimou_woocommerce_sale_flash() {
	return '<span class="onsale">' . esc_html__( 'Giảm giá', 'pimou-theme' ) . '</span>';
}
add_filter( 'woocommerce_sale_flash', 'pimou_woocommerce_sale_flash' );

function pimou_woocommerce_add_to_cart_text() {
	return __( 'Thêm giỏ', 'pimou-theme' );
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'pimou_woocommerce_add_to_cart_text' );
add_filter( 'woocommerce_product_add_to_cart_text', 'pimou_woocommerce_add_to_cart_text' );

function pimou_woocommerce_variation_dropdown_args( $args ) {
	$args['show_option_none'] = __( 'Chọn tùy chọn', 'pimou-theme' );
	return $args;
}
add_filter( 'woocommerce_dropdown_variation_attribute_options_args', 'pimou_woocommerce_variation_dropdown_args' );

function pimou_woocommerce_product_tabs( $tabs ) {
	if ( isset( $tabs['description'] ) ) {
		$tabs['description']['title'] = __( 'Mô tả', 'pimou-theme' );
	}

	if ( isset( $tabs['additional_information'] ) ) {
		$tabs['additional_information']['title'] = __( 'Thông tin thêm', 'pimou-theme' );
	}

	if ( isset( $tabs['reviews'] ) ) {
		$count = get_comments_number();
		$tabs['reviews']['title'] = sprintf(
			/* translators: %d: review count. */
			__( 'Đánh giá (%d)', 'pimou-theme' ),
			$count
		);
	}

	return $tabs;
}
add_filter( 'woocommerce_product_tabs', 'pimou_woocommerce_product_tabs' );

function pimou_woocommerce_description_heading() {
	return __( 'Mô tả sản phẩm', 'pimou-theme' );
}
add_filter( 'woocommerce_product_description_heading', 'pimou_woocommerce_description_heading' );

function pimou_woocommerce_additional_info_heading() {
	return __( 'Thông tin thêm', 'pimou-theme' );
}
add_filter( 'woocommerce_product_additional_information_heading', 'pimou_woocommerce_additional_info_heading' );

function pimou_related_products_heading() {
	return __( 'Sản phẩm liên quan', 'pimou-theme' );
}
add_filter( 'woocommerce_product_related_products_heading', 'pimou_related_products_heading' );
