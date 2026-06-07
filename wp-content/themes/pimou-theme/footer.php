<?php
/**
 * Site footer.
 *
 * @package PimouTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="site-footer">
	<div class="pimou-container footer-grid">
		<div class="footer-brand">
			<?php pimou_site_logo( 'footer-logo' ); ?>
			<p><?php esc_html_e( 'PIMOU mang đến trải nghiệm cà phê dễ chọn, dễ mua và phù hợp nhịp sống hiện đại.', 'pimou-theme' ); ?></p>
		</div>
		<div>
			<h2><?php esc_html_e( 'Mua hàng', 'pimou-theme' ); ?></h2>
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'footer-menu',
					'fallback_cb'    => false,
				) );
			} else {
				echo '<ul class="footer-menu">';
				echo '<li><a href="' . esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ) . '">' . esc_html__( 'Cửa hàng', 'pimou-theme' ) . '</a></li>';
				echo '<li><a href="' . esc_url( pimou_cart_link() ) . '">' . esc_html__( 'Giỏ hàng', 'pimou-theme' ) . '</a></li>';
				echo '</ul>';
			}
			?>
		</div>
		<div>
			<h2><?php esc_html_e( 'Liên hệ', 'pimou-theme' ); ?></h2>
			<ul class="footer-menu">
				<li><a href="<?php echo esc_url( pimou_get_hotline_href() ); ?>"><?php echo esc_html( pimou_get_hotline() ); ?></a></li>
				<li><a href="<?php echo esc_url( pimou_get_zalo_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Nhắn Zalo', 'pimou-theme' ); ?></a></li>
				<li><?php echo esc_html( pimou_get_address() ); ?></li>
			</ul>
		</div>
		<div>
			<h2><?php esc_html_e( 'Thanh toán', 'pimou-theme' ); ?></h2>
			<p><?php esc_html_e( 'Hỗ trợ COD và chuyển khoản ngân hàng theo cấu hình WooCommerce hiện tại.', 'pimou-theme' ); ?></p>
		</div>
	</div>
	<div class="footer-bottom">
		<div class="pimou-container">
			&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
