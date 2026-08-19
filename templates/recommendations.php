<?php

/**
 * Recommendations template.
 *
 * @package Conditional_Product_Recommendations
 *
 * @var WC_Product[] $products Products.
 * @var string       $heading Heading.
 * @var string       $location Location.
 * @var array        $settings Display settings.
 */

if (! defined('ABSPATH')) {
	exit;
}

$custom_class = ! empty($settings['custom_css_class']) ? sanitize_html_class($settings['custom_css_class']) : 'crw-recommendations';
$layout_class = 'rows' === $settings['layout_mode'] ? 'crw-recommendations--rows' : 'crw-recommendations--columns';
$animation_class = ! empty($settings['enable_animation']) ? 'crw-recommendations--animated' : '';
$button_class = ! empty($settings['show_add_button']) ? 'crw-recommendations--has-add-button' : 'crw-recommendations--no-add-button';
$section_style = sprintf(
	'--crw-primary:%1$s;--crw-columns-desktop:%2$d;--crw-columns-tablet:%3$d;--crw-columns-mobile:%4$d;',
	esc_attr($settings['primary_color']),
	absint($settings['columns_desktop']),
	absint($settings['columns_tablet']),
	absint($settings['columns_mobile'])
);
?>
<section class="crw-recommendations <?php echo esc_attr($custom_class . ' ' . $layout_class . ' ' . $animation_class . ' ' . $button_class); ?>" style="<?php echo esc_attr($section_style); ?>" data-crw-location="<?php echo esc_attr($location); ?>">
	<div class="crw-recommendations__header">
		<div class="crw-recommendations__title-row">
			<span class="crw-recommendations__icon <?php echo ! empty($settings['heading_icon_url']) ? 'crw-recommendations__icon--custom' : ''; ?>" aria-hidden="true">
				<?php if (! empty($settings['heading_icon_url'])) : ?>
					<img src="<?php echo esc_url($settings['heading_icon_url']); ?>" alt="">
				<?php endif; ?>
			</span>
			<div class="crw-recommendations__copy">
				<h2 class="crw-recommendations__heading"><?php echo esc_html($heading); ?></h2>
				<?php if (! empty($settings['subtitle'])) : ?>
					<p class="crw-recommendations__subtitle"><?php echo esc_html($settings['subtitle']); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<button class="crw-recommendations__hide" type="button" aria-expanded="true">
			<span class="crw-recommendations__hide-text"><?php echo esc_html__('Hide', 'conditional-product-recommendations'); ?></span>
			<span class="crw-recommendations__chevron" aria-hidden="true"></span>
		</button>
	</div>
	<div class="crw-recommendations__grid">
		<?php foreach ($products as $product) : ?>
			<article class="crw-product-card" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
				<a class="crw-product-card__image-link" href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
					<?php echo wp_kses_post($product->get_image('woocommerce_thumbnail', array('class' => 'crw-product-card__image'))); ?>
				</a>
				<div class="crw-product-card__body">
					<a class="crw-product-card__name" href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
						<?php echo esc_html($product->get_name()); ?>
					</a>
					<?php if ($product->get_short_description()) : ?>
						<div class="crw-product-card__description"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($product->get_short_description()), 8)); ?></div>
					<?php endif; ?>
					<?php if (! empty($settings['show_price'])) : ?>
						<div class="crw-product-card__price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
					<?php endif; ?>
				</div>
				<?php if (! empty($settings['show_add_button'])) : ?>
					<button class="crw-product-card__add crw-product-card__add--<?php echo esc_attr($settings['add_button_style']); ?>" type="button" data-product-id="<?php echo esc_attr($product->get_id()); ?>" <?php disabled(! $product->is_in_stock()); ?> aria-label="<?php echo esc_attr(sprintf( /* translators: %s: product name */__('Add %s to cart', 'conditional-product-recommendations'), $product->get_name())); ?>">
						<?php if ('custom_icon' === $settings['add_button_style'] && ! empty($settings['add_button_icon_url'])) : ?>
							<img src="<?php echo esc_url($settings['add_button_icon_url']); ?>" alt="">
						<?php elseif ('text' === $settings['add_button_style']) : ?>
							<span><?php echo esc_html__('Add', 'conditional-product-recommendations'); ?></span>
						<?php else : ?>
							<span class="crw-add-to-cart-default">
								<span class="crw-product-card__add-text" aria-hidden="true"><?php echo esc_html__('Add', 'conditional-product-recommendations'); ?></span>
								<span aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 16 16">
										<path d="M0 0h16v16H0z" fill="none" />
										<path fill="currentColor" d="M8.75 3.75a.75.75 0 0 0-1.5 0v3.5h-3.5a.75.75 0 0 0 0 1.5h3.5v3.5a.75.75 0 0 0 1.5 0v-3.5h3.5a.75.75 0 0 0 0-1.5h-3.5z" />
									</svg>
								</span>
							</span>
						<?php endif; ?>
						<span class="crw-add-to-cart-loading" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
								<path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8a8 8 0 0 1-8 8Z" opacity=".5" />
								<path fill="currentColor" d="M22 12h-4a1 1 0 0 0 0 2h4a1 1 0 0 0 0-2Z">
									<animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate" />
								</path>
							</svg>
						</span>
					</button>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
	<p class="crw-recommendations__note">
		<span class="crw-recommendations__note-icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
				<path d="M0 0h24v24H0z" fill="none" />
				<g fill="none" stroke="#2563eb" stroke-width="2">
					<path stroke-linecap="round" stroke-linejoin="round" d="m8 12.5l3 3l5-6" />
					<circle cx="12" cy="12" r="10" />
				</g>
			</svg>

		</span>
		<?php echo esc_html__('These items are frequently purchased together.', 'conditional-product-recommendations'); ?>
	</p>
</section>