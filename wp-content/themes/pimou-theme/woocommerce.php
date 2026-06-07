<?php
/**
 * WooCommerce wrapper template.
 *
 * @package PimouTheme
 */

get_header();
?>
<main id="primary" class="site-main pimou-container woo-content">
	<?php woocommerce_content(); ?>
</main>
<?php
get_footer();

