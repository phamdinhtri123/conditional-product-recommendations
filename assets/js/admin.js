(function ($) {
	'use strict';

	$(function () {
		$(document.body).trigger('wc-enhanced-select-init');

		window.setTimeout(function () {
			$('.crw-product-search').each(function () {
				var $field = $(this);

				if ($field.data('select2') || $field.data('selectWoo')) {
					$field.addClass('enhanced');
				}
			});
		}, 50);
	});
})(jQuery);
