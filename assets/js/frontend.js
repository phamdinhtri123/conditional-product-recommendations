(function () {
	'use strict';

	function updateFragments(fragments) {
		if (!fragments) {
			return;
		}

		Object.keys(fragments).forEach(function (selector) {
			var targets = document.querySelectorAll(selector);
			targets.forEach(function (target) {
				var wrapper = document.createElement('div');
				wrapper.innerHTML = fragments[selector];
				var replacement = wrapper.firstElementChild;

				if (replacement) {
					target.replaceWith(replacement);
				}
			});
		});

		document.body.dispatchEvent(new Event('wc_fragments_refreshed'));
	}

	function showNotice(section, message, type) {
		var notice;

		if (!section || !message) {
			return;
		}

		notice = section.querySelector('.crw-recommendations__notice');

		if (!notice) {
			notice = document.createElement('p');
			notice.className = 'crw-recommendations__notice';
			notice.setAttribute('role', 'status');
			section.insertBefore(notice, section.querySelector('.crw-recommendations__grid'));
		}

		notice.classList.toggle('crw-recommendations__notice--error', type === 'error');
		notice.textContent = message;
	}

	function refreshWooCommerceCart(data, button) {
		var detail = data || {};

		if (window.jQuery) {
			window.jQuery(document.body).trigger('added_to_cart', [
				detail.fragments || {},
				detail.cart_hash || '',
				window.jQuery(button)
			]);
			window.jQuery(document.body).trigger('wc_fragment_refresh');
			window.jQuery(document.body).trigger('update_checkout');
		}

		document.body.dispatchEvent(new CustomEvent('crw_added_to_cart', {
			detail: detail
		}));
	}

	function usesCheckoutBlock() {
		return !!document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout');
	}

	function usesCartBlock() {
		return !!document.querySelector('.wp-block-woocommerce-cart, .wc-block-cart');
	}

	function getActiveLayout(section) {
		var width = window.innerWidth || document.documentElement.clientWidth;
		var breakpoint = width <= 520 ? 'mobile' : (width <= 760 ? 'tablet' : 'desktop');
		var modes = ['slider', 'rows', 'columns'];
		var index;

		for (index = 0; index < modes.length; index++) {
			if (section.classList.contains('crw-recommendations--' + breakpoint + '-' + modes[index])) {
				return modes[index];
			}
		}

		return 'columns';
	}

	function initRecommendationSlider(section) {
		var slider = section.querySelector('.crw-recommendations__slider');

		if (!slider || !window.Swiper) {
			return;
		}

		if (getActiveLayout(section) !== 'slider') {
			if (section.crwSwiper) {
				section.crwSwiper.destroy(true, true);
				section.crwSwiper = null;
			}
			return;
		}

		if (section.crwSwiper) {
			section.crwSwiper.update();
			return;
		}

		section.crwSwiper = new window.Swiper(slider, {
			slidesPerView: 'auto',
			spaceBetween: 16,
			watchOverflow: true,
			grabCursor: true,
			pagination: {
				el: section.querySelector('.crw-recommendations__slider-pagination'),
				clickable: true
			},
			navigation: {
				prevEl: section.querySelector('.crw-recommendations__slider-button--prev'),
				nextEl: section.querySelector('.crw-recommendations__slider-button--next')
			}
		});
	}

	function initRecommendationSliders() {
		document.querySelectorAll('.crw-recommendations--has-slider').forEach(initRecommendationSlider);
	}

	function debounce(callback, delay) {
		var timer;

		return function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(callback, delay);
		};
	}

	function removeCard(button) {
		var card = button.closest('.crw-product-card');
		var section = button.closest('.crw-recommendations');

		if (!card || !section) {
			return;
		}

		card.classList.add('is-removing');

		window.setTimeout(function () {
			card.remove();

			if (!section.querySelector('.crw-product-card')) {
				section.remove();
			} else if (section.crwSwiper) {
				section.crwSwiper.update();
			}
		}, 190);
	}

	function addToCart(button) {
		var productId = button.getAttribute('data-product-id');
		var data = new window.FormData();
		var section = button.closest('.crw-recommendations');

		if (button.classList.contains('is-loading')) {
			return;
		}

		button.disabled = true;
		button.classList.add('is-loading');
		button.setAttribute('aria-busy', 'true');

		if (button.getAttribute('data-ajax-addable') !== '1') {
			window.location.href = button.getAttribute('data-product-url');
			return;
		}

		data.append('action', 'crw_add_to_cart');
		data.append('nonce', window.crwRecommendations.nonce);
		data.append('product_id', productId);
		data.append('quantity', '1');

		window.fetch(window.crwRecommendations.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (response) {
				if (!response || !response.success) {
					throw new Error(response && response.data && response.data.message ? response.data.message : window.crwRecommendations.i18n.error);
				}

				updateFragments(response.data.fragments);
				refreshWooCommerceCart(response.data, button);
				showNotice(section, response.data.message || window.crwRecommendations.i18n.added, 'success');

				if (section && (
					(section.getAttribute('data-crw-location') === 'checkout' && usesCheckoutBlock())
					|| (section.getAttribute('data-crw-location') === 'cart' && usesCartBlock())
				)) {
					window.setTimeout(function () {
						window.location.reload();
					}, 500);
					return;
				}

				removeCard(button);
			})
			.catch(function (error) {
				button.disabled = false;
				button.classList.remove('is-loading');
				button.removeAttribute('aria-busy');
				showNotice(section, error.message || window.crwRecommendations.i18n.error, 'error');
			});
	}

	document.addEventListener('click', function (event) {
		var toggle = event.target.closest('.crw-recommendations__hide');
		var button = event.target.closest('.crw-product-card__add');

		if (toggle) {
			var section = toggle.closest('.crw-recommendations');
			var text = toggle.querySelector('.crw-recommendations__hide-text');
			var collapsed = section.classList.toggle('is-collapsed');

			toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

			if (text) {
				text.textContent = collapsed ? 'Show' : 'Hide';
			}

			return;
		}

		if (!button) {
			return;
		}

		event.preventDefault();
		addToCart(button);
	});

	document.addEventListener('DOMContentLoaded', initRecommendationSliders);
	window.addEventListener('load', initRecommendationSliders);
	window.addEventListener('resize', debounce(initRecommendationSliders, 160));
})();
