<?php
/**
 * Front page template.
 *
 * @package PimouTheme
 */

get_header();

$shop_url              = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$banner_image          = pimou_get_banner_image_url();
$banner_link           = pimou_get_banner_link_url();
$home_categories       = pimou_home_product_categories( pimou_get_home_category_limit() );
$products_per_category = pimou_get_home_products_per_category();
$feature_items         = pimou_feature_items();
?>
<main id="primary" class="site-main">
	<section class="hero">
		<div class="pimou-container">
			<?php if ( $banner_link ) : ?>
				<a class="home-banner<?php echo $banner_image ? ' home-banner-has-image' : ''; ?>" href="<?php echo esc_url( $banner_link ); ?>" aria-label="<?php esc_attr_e( 'Banner khuyến mãi', 'pimou-theme' ); ?>">
					<?php if ( $banner_image ) : ?>
						<img src="<?php echo esc_url( $banner_image ); ?>" alt="<?php esc_attr_e( 'Banner khuyến mãi', 'pimou-theme' ); ?>">
					<?php endif; ?>
				</a>
			<?php else : ?>
				<div class="home-banner<?php echo $banner_image ? ' home-banner-has-image' : ''; ?>" role="img" aria-label="<?php esc_attr_e( 'Banner khuyến mãi', 'pimou-theme' ); ?>">
					<?php if ( $banner_image ) : ?>
						<img src="<?php echo esc_url( $banner_image ); ?>" alt="<?php esc_attr_e( 'Banner khuyến mãi', 'pimou-theme' ); ?>">
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $home_categories ) : ?>
		<?php foreach ( $home_categories as $index => $category ) : ?>
			<?php $category_query = pimou_product_category_query( $category->term_id, $products_per_category ); ?>
			<?php if ( ! $category_query->have_posts() ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<?php
			$category_url = get_term_link( $category );
			if ( is_wp_error( $category_url ) ) {
				$category_url = $shop_url;
			}
			?>
			<section class="home-section product-category-section<?php echo 0 !== $index % 2 ? ' product-category-section-alt' : ''; ?>">
				<div class="pimou-container">
					<div class="section-heading section-heading-row">
						<h2><?php echo esc_html( $category->name ); ?></h2>
						<a class="text-link" href="<?php echo esc_url( $category_url ); ?>"><?php esc_html_e( 'Xem tất cả', 'pimou-theme' ); ?></a>
					</div>
					<div class="pimou-product-grid">
						<?php
						while ( $category_query->have_posts() ) :
							$category_query->the_post();
							pimou_product_card();
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</div>
			</section>
		<?php endforeach; ?>
	<?php else : ?>
		<section class="home-section product-category-section">
			<div class="pimou-container">
				<div class="empty-products">
					<p><?php esc_html_e( 'Thêm danh mục và sản phẩm WooCommerce để khu vực này tự hiển thị.', 'pimou-theme' ); ?></p>
					<a class="pimou-btn pimou-btn-primary" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ) ); ?>"><?php esc_html_e( 'Thêm danh mục', 'pimou-theme' ); ?></a>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="home-section promise-section">
		<div class="pimou-container promise-grid">
			<?php foreach ( $feature_items as $item ) : ?>
				<div class="promise-item">
					<div class="promise-icon"><?php echo esc_html( strtoupper( substr( $item['title'], 0, 1 ) ) ); ?></div>
					<h3><?php echo esc_html( $item['title'] ); ?></h3>
					<p><?php echo esc_html( $item['text'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="home-section about-section">
		<div class="pimou-container about-grid">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Về PIMOU', 'pimou-theme' ); ?></p>
				<h2><?php esc_html_e( 'Một cửa hàng cà phê sạch sẽ, thân thiện và dễ mua.', 'pimou-theme' ); ?></h2>
			</div>
			<p><?php esc_html_e( 'PIMOU tập trung vào trải nghiệm mua cà phê trực tuyến rõ ràng: danh mục dễ hiểu, biến thể đóng gói và dạng xay đặt ngay trên trang sản phẩm, cùng các kênh tư vấn nhanh khi khách cần chọn đúng gu.', 'pimou-theme' ); ?></p>
		</div>
	</section>
</main>
<?php
get_footer();
