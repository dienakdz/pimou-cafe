<?php
/**
 * PIMOU Theme functions.
 *
 * @package PimouTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PIMOU_THEME_VERSION', '1.0.26' );

$pimou_theme_includes = array(
	'inc/theme-setup.php',
	'inc/customizer.php',
	'inc/template-functions.php',
	'inc/woocommerce.php',
);

foreach ( $pimou_theme_includes as $pimou_theme_file ) {
	require_once get_template_directory() . '/' . $pimou_theme_file;
}
