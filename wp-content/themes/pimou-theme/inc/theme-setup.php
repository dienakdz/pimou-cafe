<?php
/**
 * Theme setup and asset loading.
 *
 * @package PimouTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pimou_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 180,
		'width'       => 420,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus( array(
		'primary' => __( 'Primary menu', 'pimou-theme' ),
		'footer'  => __( 'Footer menu', 'pimou-theme' ),
	) );
}
add_action( 'after_setup_theme', 'pimou_theme_setup' );

function pimou_theme_enqueue_assets() {
	wp_enqueue_style(
		'pimou-fonts',
		'https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'pimou-main',
		get_template_directory_uri() . '/assets/css/main.css',
		array( 'pimou-fonts' ),
		PIMOU_THEME_VERSION
	);

	wp_enqueue_script(
		'pimou-main',
		get_template_directory_uri() . '/assets/js/main.js',
		array( 'jquery' ),
		PIMOU_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'pimou_theme_enqueue_assets' );

function pimou_body_classes( $classes ) {
	$classes[] = 'pimou-site';
	return $classes;
}
add_filter( 'body_class', 'pimou_body_classes' );
