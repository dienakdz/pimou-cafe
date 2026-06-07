<?php
/**
 * Customizer settings.
 *
 * @package PimouTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pimou_theme_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'pimou_contact', array(
		'title'    => __( 'PIMOU Contact', 'pimou-theme' ),
		'priority' => 30,
	) );

	$wp_customize->add_section( 'pimou_homepage', array(
		'title'    => __( 'PIMOU Homepage', 'pimou-theme' ),
		'priority' => 31,
	) );

	$fields = array(
		'pimou_hotline' => array(
			'label'   => __( 'Hotline', 'pimou-theme' ),
			'default' => '0909 000 999',
		),
		'pimou_zalo_url' => array(
			'label'   => __( 'Zalo URL', 'pimou-theme' ),
			'default' => 'https://zalo.me/0909000999',
		),
		'pimou_address' => array(
			'label'   => __( 'Address', 'pimou-theme' ),
			'default' => 'TP. Ho Chi Minh, Viet Nam',
		),
	);

	foreach ( $fields as $setting => $field ) {
		$wp_customize->add_setting( $setting, array(
			'default'           => $field['default'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( $setting, array(
			'label'   => $field['label'],
			'section' => 'pimou_contact',
			'type'    => 'text',
		) );
	}

	$wp_customize->add_setting( 'pimou_banner_image', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );

	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'pimou_banner_image',
			array(
				'label'   => __( 'Banner image', 'pimou-theme' ),
				'section' => 'pimou_homepage',
			)
		)
	);

	$wp_customize->add_setting( 'pimou_banner_link', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );

	$wp_customize->add_control( 'pimou_banner_link', array(
		'label'       => __( 'Banner link', 'pimou-theme' ),
		'description' => __( 'Optional URL opened when customers click the homepage banner.', 'pimou-theme' ),
		'section'     => 'pimou_homepage',
		'type'        => 'url',
	) );

	$wp_customize->add_setting( 'pimou_home_category_limit', array(
		'default'           => 4,
		'sanitize_callback' => 'absint',
	) );

	$wp_customize->add_control( 'pimou_home_category_limit', array(
		'label'       => __( 'Home category limit', 'pimou-theme' ),
		'description' => __( 'Maximum number of product categories shown on the homepage.', 'pimou-theme' ),
		'section'     => 'pimou_homepage',
		'type'        => 'number',
		'input_attrs' => array(
			'min' => 1,
			'max' => 12,
		),
	) );

	$wp_customize->add_setting( 'pimou_home_products_per_category', array(
		'default'           => 4,
		'sanitize_callback' => 'absint',
	) );

	$wp_customize->add_control( 'pimou_home_products_per_category', array(
		'label'       => __( 'Products per home category', 'pimou-theme' ),
		'description' => __( 'Maximum number of products shown under each homepage category.', 'pimou-theme' ),
		'section'     => 'pimou_homepage',
		'type'        => 'number',
		'input_attrs' => array(
			'min' => 1,
			'max' => 12,
		),
	) );
}
add_action( 'customize_register', 'pimou_theme_customize_register' );
