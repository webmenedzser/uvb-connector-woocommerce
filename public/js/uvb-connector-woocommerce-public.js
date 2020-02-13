(function( $ ) {
    'use strict';

    function checkUVBService(fieldValue) {
        console.log('Connecting to UVB Service.');

        var data = {
            'action': 'check_if_email_is_flagged',
            'email': fieldValue
        };

        // We can also pass the url value separately from ajaxurl for front end AJAX implementations
        jQuery.post(ajax_object.ajax_url, data, function(response) {
            $('body').trigger('update_checkout');
        });
    }

    jQuery(document).ready(function($) {
        var billingEmailField = document.querySelector('input[name=billing_email]');
        if (billingEmailField) {
            var fieldValue = billingEmailField.value;

            if (fieldValue) {
                checkUVBService(fieldValue);
            }
        }

        $('input[name=billing_email]').change(function() {
            checkUVBService($(this).val());
        });
    });

})( jQuery );
