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

$custom_classes = ! empty($settings['custom_css_class']) ? preg_split('/\s+/', $settings['custom_css_class']) : array();
$custom_classes = array_filter(array_map('sanitize_html_class', (array) $custom_classes));
$layout_mode = ! empty($settings[$location . '_layout_mode']) ? $settings[$location . '_layout_mode'] : $settings['layout_mode'];
$columns_desktop = ! empty($settings[$location . '_columns_desktop']) ? $settings[$location . '_columns_desktop'] : $settings['columns_desktop'];
$columns_tablet = ! empty($settings[$location . '_columns_tablet']) ? $settings[$location . '_columns_tablet'] : $settings['columns_tablet'];
$columns_mobile = ! empty($settings[$location . '_columns_mobile']) ? $settings[$location . '_columns_mobile'] : $settings['columns_mobile'];
$layout_class = 'rows' === $layout_mode ? 'crw-recommendations--rows' : 'crw-recommendations--columns';
$location_class = 'crw-recommendations--' . sanitize_html_class($location);
$animation_class = ! empty($settings['enable_animation']) ? 'crw-recommendations--animated' : '';
$button_class = ! empty($settings['show_add_button']) ? 'crw-recommendations--has-add-button' : 'crw-recommendations--no-add-button';
$section_classes = array_merge(array('crw-recommendations', $location_class, $layout_class, $animation_class, $button_class), $custom_classes);
$section_classes = array_filter(array_unique($section_classes));
$section_style = sprintf(
	'--crw-primary:%1$s;--crw-columns-desktop:%2$d;--crw-columns-tablet:%3$d;--crw-columns-mobile:%4$d;',
	esc_attr($settings['primary_color']),
	absint($columns_desktop),
	absint($columns_tablet),
	absint($columns_mobile)
);
?>
<section class="<?php echo esc_attr(implode(' ', $section_classes)); ?>" style="<?php echo esc_attr($section_style); ?>" data-crw-location="<?php echo esc_attr($location); ?>">
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
			<?php
			$ajax_product_id = $product->get_id();
			$ajax_addable = $product->is_type(array('simple', 'variation'));

			if (! $ajax_addable && $product->is_type('variable')) {
				$single_variation = null;

				foreach ($product->get_children() as $variation_id) {
					$variation = wc_get_product($variation_id);

					if (! $variation || ! $variation->is_purchasable() || ! $variation->is_in_stock()) {
						continue;
					}

					if ($single_variation) {
						$single_variation = null;
						break;
					}

					$single_variation = $variation;
				}

				if ($single_variation) {
					$ajax_addable = true;
					$ajax_product_id = $single_variation->get_id();
				}
			}

			$button_label = $ajax_addable
				? sprintf(
					/* translators: %s: product name */
					__('Add %s to cart', 'conditional-product-recommendations'),
					$product->get_name()
				)
				: sprintf(
					/* translators: %s: product name */
					__('View options for %s', 'conditional-product-recommendations'),
					$product->get_name()
				);
			?>
			<article class="crw-product-card" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
				<a class="crw-product-card__image-link" href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
					<?php echo wp_kses_post($product->get_image('woocommerce_thumbnail', array('class' => 'crw-product-card__image'))); ?>
				</a>
				<div class="crw-product-card__body">
					<a class="crw-product-card__name" href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
						<?php echo esc_html($product->get_name()); ?>
					</a>
					<?php if (! empty($settings['show_price'])) : ?>
						<div class="crw-product-card__price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
					<?php endif; ?>
				</div>
				<?php if (! empty($settings['show_add_button'])) : ?>
					<button class="crw-product-card__add crw-product-card__add--<?php echo esc_attr($settings['add_button_style']); ?>" type="button" data-product-id="<?php echo esc_attr($ajax_product_id); ?>" data-ajax-addable="<?php echo esc_attr($ajax_addable ? '1' : '0'); ?>" data-product-url="<?php echo esc_url(get_permalink($product->get_id())); ?>" <?php disabled(! $product->is_in_stock()); ?> aria-label="<?php echo esc_attr($button_label); ?>">
						<span class="crw-product-card__add-content">
							<?php if ('custom_icon' === $settings['add_button_style'] && ! empty($settings['add_button_icon_url'])) : ?>
								<img src="<?php echo esc_url($settings['add_button_icon_url']); ?>" alt="">
							<?php elseif ('text' === $settings['add_button_style']) : ?>
								<span><?php echo esc_html($ajax_addable ? __('Add', 'conditional-product-recommendations') : __('Options', 'conditional-product-recommendations')); ?></span>
							<?php else : ?>
								<span aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 16 16">
										<path d="M0 0h16v16H0z" fill="none" />
										<path fill="currentColor" d="M8.75 3.75a.75.75 0 0 0-1.5 0v3.5h-3.5a.75.75 0 0 0 0 1.5h3.5v3.5a.75.75 0 0 0 1.5 0v-3.5h3.5a.75.75 0 0 0 0-1.5h-3.5z" />
									</svg>
								</span>
							<?php endif; ?>
						</span>
						<span class="crw-product-card__add-spinner" aria-hidden="true"></span>
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
