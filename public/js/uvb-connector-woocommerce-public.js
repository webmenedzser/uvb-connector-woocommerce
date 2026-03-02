(function( $ ) {
    'use strict';

    var lastSentPayload = null;
    var debounceTimer = null;
    var pollerTimer = null;
    var pollerCount = 0;
    var maxPolls = 20;
    var pollIntervalMs = 1000;

    function isUsableElement(element) {
        if (!element) {
            return false;
        }

        if (element.disabled) {
            return false;
        }

        var type = (element.getAttribute('type') || '').toLowerCase();
        if (type === 'hidden') {
            return false;
        }

        if (typeof element.getClientRects === 'function' && element.getClientRects().length === 0) {
            return false;
        }

        return true;
    }

    function getPrimaryFieldElement(selectors) {
        var fallback = null;
        var firstUsable = null;
        var firstWithValue = null;

        for (var i = 0; i < selectors.length; i++) {
            var element = document.querySelector(selectors[i]);
            if (!element) {
                continue;
            }

            if (!fallback) {
                fallback = element;
            }

            if (isUsableElement(element)) {
                if (!firstUsable) {
                    firstUsable = element;
                }

                if (!firstWithValue && getElementValue(element)) {
                    firstWithValue = element;
                }
            }
        }

        return firstWithValue || firstUsable || fallback;
    }

    function getFieldValue(selectors) {
        var element = getPrimaryFieldElement(selectors);
        return element ? getElementValue(element) : '';
    }

    var SELECTORS = {
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

    function buildSelector(keys) {
        var selectors = [];

        for (var i = 0; i < keys.length; i++) {
            selectors = selectors.concat(SELECTORS[keys[i]]);
        }

        return selectors.join(', ');
    }

    function getElementValue(element) {
        if (!element || typeof element.value !== 'string') {
            return '';
        }

        return element.value.trim();
    }

    function isTextLikeField(element) {
        if (!element || !element.tagName) {
            return false;
        }

        var tagName = element.tagName.toLowerCase();
        if (tagName === 'textarea') {
            return true;
        }

        if (tagName !== 'input') {
            return false;
        }

        var type = (element.getAttribute('type') || 'text').toLowerCase();
        return ['text', 'email', 'search', 'tel', 'url', 'password', 'number'].indexOf(type) !== -1;
    }

    function buildPayload() {
        var email = getFieldValue(SELECTORS.email);
        var phoneNumber = getFieldValue(SELECTORS.phone);
        var countryCode = getFieldValue(SELECTORS.country);
        var postalCode = getFieldValue(SELECTORS.postal);
        var addressLine1 = getFieldValue(SELECTORS.address1);
        var addressLine2 = getFieldValue(SELECTORS.address2);
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
        var phoneRequired = isFieldRequired(SELECTORS.phone);
        var emailElement = getPrimaryFieldElement(SELECTORS.email);
        var isEmailValid = !!payload.email;

        if (isEmailValid) {
            if (emailElement && typeof emailElement.checkValidity === 'function' && (emailElement.type || '').toLowerCase() === 'email') {
                isEmailValid = emailElement.checkValidity();
            } else {
                isEmailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email);
            }
        }

        return !!(
            isEmailValid &&
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

    var LISTENERS_SELECTOR = buildSelector(['email', 'phone', 'country', 'postal', 'address1', 'address2']);
    var SELECT2_SELECTOR = buildSelector(['country']);

    function isTrackedFieldFocused() {
        if (!document.activeElement || !document.activeElement.matches) {
            return false;
        }

        return document.activeElement.matches(LISTENERS_SELECTOR);
    }

    function scheduleCheck(skipIfFocused) {
        if (skipIfFocused && isTrackedFieldFocused()) {
            return;
        }

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(function() {
            var payload = buildPayload();
            checkUVBService(payload);
        }, 250);
    }

    function handleFieldFocus(event) {
        if (!event || !isTextLikeField(event.target)) {
            return;
        }

        event.target.setAttribute('data-uvb-focus-value', getElementValue(event.target));
    }

    function handleTextFieldBlur(event) {
        if (!event || !isTextLikeField(event.target)) {
            return;
        }

        var previousValue = event.target.getAttribute('data-uvb-focus-value');
        var currentValue = getElementValue(event.target);

        if (previousValue !== null && previousValue === currentValue) {
            return;
        }

        event.target.setAttribute('data-uvb-focus-value', currentValue);
        scheduleCheck(false);
    }

    function handleNonTextChange(event) {
        if (!event || isTextLikeField(event.target)) {
            return;
        }

        scheduleCheck(false);
    }

    function startPoller() {
        if (pollerTimer) {
            clearInterval(pollerTimer);
        }

        pollerCount = 0;
        pollerTimer = setInterval(function() {
            pollerCount += 1;
            scheduleCheck(true);

            if (pollerCount >= maxPolls) {
                clearInterval(pollerTimer);
                pollerTimer = null;
            }
        }, pollIntervalMs);

        scheduleCheck(true);
    }

    window.addEventListener('load', function($) {
        startPoller();
    });

    $(document).on(
        'change',
        LISTENERS_SELECTOR,
        handleNonTextChange
    );

    $(document).on('focusin', LISTENERS_SELECTOR, handleFieldFocus);
    $(document).on('blur', LISTENERS_SELECTOR, handleTextFieldBlur);

    $(document).on(
        'select2:select select2:clear',
        SELECT2_SELECTOR,
        function() {
            scheduleCheck(false);
        }
    );

    $(document.body).on('updated_checkout updated_shipping_method country_to_state_changed wc_fragments_refreshed', startPoller);

})( jQuery );
