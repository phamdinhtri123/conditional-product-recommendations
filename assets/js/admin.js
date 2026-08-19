(function ($) {
	'use strict';

	$(function () {
		$(document.body).trigger('wc-enhanced-select-init');
		$('.crw-color-field').wpColorPicker();

		window.setTimeout(function () {
			$('.crw-product-search').each(function () {
				var $field = $(this);

				if ($field.data('select2') || $field.data('selectWoo')) {
					$field.addClass('enhanced');
				}
			});
		}, 50);

		$(document).on('click', '.crw-media-upload', function (event) {
			event.preventDefault();

			var $button = $(this);
			var $field = $button.closest('.crw-media-field').find('.crw-media-url');
			var frame = window.wp.media({
				title: 'Select image or SVG',
				button: {
					text: 'Use this file'
				},
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();

				if (attachment && attachment.url) {
					$field.val(attachment.url).trigger('change');
				}
			});

			frame.open();
		});

		$(document).on('click', '.crw-media-clear', function (event) {
			event.preventDefault();
			$(this).closest('.crw-media-field').find('.crw-media-url').val('').trigger('change');
		});
	});
})(jQuery);
