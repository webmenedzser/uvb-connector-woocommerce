(function( $ ) {
    'use strict';

    var lastSentPayload = null;
    var debounceTimer = null;
    var pollerTimer = null;
    var pollerCount = 0;
    var maxPolls = 20;
    var pollIntervalMs = 1000;

    function getFieldValue(selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var element = document.querySelector(selectors[i]);
            if (element && element.value && element.value.trim()) {
                return element.value.trim();
            }
        }

        return '';
    }

    var FIELD_SELECTORS = {
        email: [
            'input[name=billing_email]',
            '#billing_email',
            '.woocommerce-checkout input#email',
            'input[name=email]',
            '#email',
            'input[name*=email]'
        ],
        phone: [
            'input[name=shipping_phone]',
            '#shipping_phone',
            'input[name=billing_phone]',
            '#billing_phone',
            'input[name=phone]',
            '#phone',
            'input[name*=phone]',
            'input[type=tel]'
        ],
        country: [
            'select[name=shipping_country]',
            'input[name=shipping_country]',
            '#shipping_country',
            '#shipping-country',
            'select[name=billing_country]',
            'input[name=billing_country]',
            '#billing_country',
            '#billing-country',
            'select[name=country]',
            'input[name=country]',
            '#country',
            'select[name*=country]',
            'input[name*=country]'
        ],
        postal: [
            'input[name=shipping_postcode]',
            '#shipping_postcode',
            'input[name=billing_postcode]',
            '#billing_postcode',
            'input[name=postcode]',
            '#postcode',
            'input[name*=postcode]',
            'input[name*=postal]',
            'input[name*=zip]'
        ],
        address1: [
            'input[name=shipping_address_1]',
            '#shipping_address_1',
            'textarea[name=shipping_address_1]',
            'input[name=billing_address_1]',
            '#billing_address_1',
            'textarea[name=billing_address_1]',
            'input[name=address_1]',
            '#address_1',
            'textarea[name=address_1]',
            'input[name*=address_1]',
            'input[name*=address-1]',
            'input[name*=address1]',
            'input[name*=address-line-1]'
        ],
        address2: [
            'input[name=shipping_address_2]',
            '#shipping_address_2',
            'textarea[name=shipping_address_2]',
            'input[name=billing_address_2]',
            '#billing_address_2',
            'textarea[name=billing_address_2]',
            'input[name=address_2]',
            '#address_2',
            'textarea[name=address_2]',
            'input[name*=address_2]',
            'input[name*=address-2]',
            'input[name*=address2]',
            'input[name*=address-line-2]'
        ]
    };

    function buildPayload() {
        var email = getFieldValue(FIELD_SELECTORS.email);
        var phoneNumber = getFieldValue(FIELD_SELECTORS.phone);
        var countryCode = getFieldValue(FIELD_SELECTORS.country);
        var postalCode = getFieldValue(FIELD_SELECTORS.postal);
        var addressLine1 = getFieldValue(FIELD_SELECTORS.address1);
        var addressLine2 = getFieldValue(FIELD_SELECTORS.address2);
        var addressLine = [addressLine1, addressLine2].filter(Boolean).join(' ').trim();

        return {
            email: email,
            phone_number: phoneNumber,
            country_code: countryCode,
            postal_code: postalCode,
            address_line: addressLine
        };
    }

    function isFieldRequired(selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var element = document.querySelector(selectors[i]);
            if (!element) {
                continue;
            }

            if (element.required) {
                return true;
            }

            if (element.getAttribute('required') !== null) {
                return true;
            }

            if (element.getAttribute('aria-required') === 'true') {
                return true;
            }

            if (element.getAttribute('data-required') === 'true') {
                return true;
            }

            if (element.closest && element.closest('.validate-required')) {
                return true;
            }
        }

        return false;
    }

    function isCompletePayload(payload) {
        var phoneRequired = isFieldRequired(FIELD_SELECTORS.phone);

        return !!(
            payload.email &&
            payload.country_code &&
            payload.postal_code &&
            payload.address_line &&
            (payload.phone_number || !phoneRequired)
        );
    }

    function checkUVBService(payload) {
        if (!isCompletePayload(payload)) {
            return;
        }

        var payloadKey = JSON.stringify(payload);
        if (payloadKey === lastSentPayload) {
            return;
        }

        lastSentPayload = payloadKey;
        console.log('[Utánvét Ellenőr] Ellenőrzés...');

        var data = {
            'action': 'check_if_email_is_flagged',
            'email': payload.email,
            'phone_number': payload.phone_number,
            'country_code': payload.country_code,
            'postal_code': payload.postal_code,
            'address_line': payload.address_line
        };

        // We can also pass the url value separately from ajaxurl for front end AJAX implementations
        var ajaxUrl = ajax_object.ajax_url ?? ajax_object.ajaxurl;
        jQuery.post(ajaxUrl, data, function(response) {
            $('body').trigger('update_checkout');
            console.log('[Utánvét Ellenőr] Kész!');
        });
    }

    function handleFieldChange() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(function() {
            var payload = buildPayload();
            checkUVBService(payload);
        }, 250);
    }

    function startPoller() {
        if (pollerTimer) {
            clearInterval(pollerTimer);
        }

        pollerCount = 0;
        pollerTimer = setInterval(function() {
            pollerCount += 1;
            handleFieldChange();

            if (pollerCount >= maxPolls) {
                clearInterval(pollerTimer);
                pollerTimer = null;
            }
        }, pollIntervalMs);

        handleFieldChange();
    }

    window.addEventListener('load', function($) {
        startPoller();
    });

    $(document).on(
        'change input',
        'input[name=billing_email], #billing_email, .woocommerce-checkout input#email, input[name=email], #email, input[name*=email],' +
        ' input[name=shipping_phone], #shipping_phone, input[name=billing_phone], #billing_phone, input[name=phone], #phone, input[name*=phone], input[type=tel],' +
        ' select[name=shipping_country], input[name=shipping_country], #shipping_country, #shipping-country, select[name=billing_country], input[name=billing_country], #billing_country, #billing-country, select[name=country], input[name=country], #country, select[name*=country], input[name*=country],' +
        ' select[name=shipping_state], input[name=shipping_state], #shipping_state, #shipping-state, select[name=billing_state], input[name=billing_state], #billing_state, #billing-state, select[name=state], input[name=state], #state, select[name*=state], input[name*=state], select[name*=region], input[name*=region],' +
        ' input[name=shipping_postcode], #shipping_postcode, input[name=billing_postcode], #billing_postcode, input[name=postcode], #postcode, input[name*=postcode], input[name*=postal], input[name*=zip],' +
        ' input[name=shipping_address_1], #shipping_address_1, textarea[name=shipping_address_1], input[name=billing_address_1], #billing_address_1, textarea[name=billing_address_1], input[name=address_1], #address_1, textarea[name=address_1], input[name*=address_1], input[name*=address-1], input[name*=address1], input[name*=address-line-1],' +
        ' input[name=shipping_address_2], #shipping_address_2, textarea[name=shipping_address_2], input[name=billing_address_2], #billing_address_2, textarea[name=billing_address_2], input[name=address_2], #address_2, textarea[name=address_2], input[name*=address_2], input[name*=address-2], input[name*=address2], input[name*=address-line-2]',
        handleFieldChange
    );

    $(document).on(
        'select2:select select2:clear',
        'select[name=shipping_country], #shipping_country, select[name=billing_country], #billing_country, select[name*=country],' +
        ' select[name=shipping_state], #shipping_state, select[name=billing_state], #billing_state, select[name*=state], select[name*=region]',
        handleFieldChange
    );

    $(document.body).on('updated_checkout updated_shipping_method country_to_state_changed wc_fragments_refreshed', startPoller);

})( jQuery );
