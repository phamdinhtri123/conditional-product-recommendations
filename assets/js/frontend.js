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
			}
		}, 190);
	}

	function addToCart(button) {
		var productId = button.getAttribute('data-product-id');
		var data = new window.FormData();

		data.append('action', 'crw_add_to_cart');
		data.append('nonce', window.crwRecommendations.nonce);
		data.append('product_id', productId);
		data.append('quantity', '1');

		button.disabled = true;
		button.setAttribute('aria-busy', 'true');

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
				removeCard(button);
			})
			.catch(function (error) {
				button.disabled = false;
				button.removeAttribute('aria-busy');
				window.alert(error.message || window.crwRecommendations.i18n.error);
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
})();
