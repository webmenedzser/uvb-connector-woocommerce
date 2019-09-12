(function( $ ) {
	'use strict';

	jQuery(document).ready(function($) {
		$('input[name=billing_email]').change(function() {
			var data = {
				'action': 'check_if_email_is_flagged',
				'email': $(this).val()
			};

			// We can also pass the url value separately from ajaxurl for front end AJAX implementations
			jQuery.post(ajax_object.ajax_url, data, function(response) {
				$('body').trigger('update_checkout');
			});
		});
	});

})( jQuery );
