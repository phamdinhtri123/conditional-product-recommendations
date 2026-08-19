<?php
/**
 * Recommendations template.
 *
 * @package Conditional_Product_Recommendations
 *
 * @var WC_Product[] $products Products.
 * @var string       $heading Heading.
 * @var string       $location Location.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="crw-recommendations" data-crw-location="<?php echo esc_attr( $location ); ?>">
	<div class="crw-recommendations__header">
		<div class="crw-recommendations__title-row">
			<span class="crw-recommendations__icon" aria-hidden="true"></span>
			<div class="crw-recommendations__copy">
				<h2 class="crw-recommendations__heading"><?php echo esc_html( $heading ); ?></h2>
				<p class="crw-recommendations__subtitle"><?php echo esc_html__( 'Make sure you have everything you need.', 'conditional-product-recommendations' ); ?></p>
			</div>
		</div>
		<button class="crw-recommendations__hide" type="button" aria-expanded="true">
			<span class="crw-recommendations__hide-text"><?php echo esc_html__( 'Hide', 'conditional-product-recommendations' ); ?></span>
			<span class="crw-recommendations__chevron" aria-hidden="true"></span>
		</button>
	</div>
	<div class="crw-recommendations__grid">
		<?php foreach ( $products as $product ) : ?>
			<article class="crw-product-card" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
				<a class="crw-product-card__image-link" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
					<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'crw-product-card__image' ) ) ); ?>
				</a>
				<div class="crw-product-card__body">
					<a class="crw-product-card__name" href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
						<?php echo esc_html( $product->get_name() ); ?>
					</a>
					<?php if ( $product->get_short_description() ) : ?>
						<div class="crw-product-card__description"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $product->get_short_description() ), 8 ) ); ?></div>
					<?php endif; ?>
					<div class="crw-product-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
				</div>
				<button class="crw-product-card__add" type="button" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'Add %s to cart', 'conditional-product-recommendations' ), $product->get_name() ) ); ?>">
					<span aria-hidden="true">+</span>
				</button>
			</article>
		<?php endforeach; ?>
	</div>
	<p class="crw-recommendations__note">
		<span class="crw-recommendations__note-icon" aria-hidden="true"></span>
		<?php echo esc_html__( 'These items are frequently purchased together.', 'conditional-product-recommendations' ); ?>
	</p>
</section>
