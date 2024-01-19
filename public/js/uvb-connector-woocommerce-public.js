(function( $ ) {
    'use strict';

    function checkUVBService(fieldValue) {
        if (!fieldValue) {
          return;
        }

        console.log('[Utánvét Ellenőr] Ellenőrzés...');

        var data = {
            'action': 'check_if_email_is_flagged',
            'email': fieldValue
        };

        // We can also pass the url value separately from ajaxurl for front end AJAX implementations
        jQuery.post(ajax_object.ajax_url, data, function(response) {
            $('body').trigger('update_checkout');
            console.log('[Utánvét Ellenőr] Kész!');
        });
    }

    window.addEventListener('load', function($) {
        var billingEmailField = document.querySelector('input[name=billing_email], .woocommerce-checkout input#email');
        if (!billingEmailField) {
          return;
        }

        checkUVBService(billingEmailField.value);

        billingEmailField.addEventListener('change', function() {
            checkUVBService(billingEmailField.value);
        });
    });

})( jQuery );
