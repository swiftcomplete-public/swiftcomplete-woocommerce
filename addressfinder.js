function launchAddressLookup(type, key, searchFor, hideFields, biasTowards, placeholder) {
    const woocommercefields = {
        billing: {
            field: "swiftcomplete_billing_address_autocomplete",
            populateLineFormat: [
                { field: "billing_company", format: "Company" },
                { field: "billing_address_1", format: "BuildingName, BuildingNumber SecondaryRoad, Road" },
                { field: "billing_address_2", format: "SubBuilding" },
                { field: "billing_city", format: "TertiaryLocality, SecondaryLocality, PRIMARYLOCALITY" },
                { field: "billing_state", format: "" },
                { field: "billing_postcode", format: "POSTCODE" },
                { field: "swiftcomplete_what3words", format: "what3words" },
            ]
        },
        shipping: {
            field: "swiftcomplete_shipping_address_autocomplete",
            populateLineFormat: [
                { field: "shipping_company", format: "Company" },
                { field: "shipping_address_1", format: "BuildingName, BuildingNumber SecondaryRoad, Road" },
                { field: "shipping_address_2", format: "SubBuilding" },
                { field: "shipping_city", format: "TertiaryLocality, SecondaryLocality, PRIMARYLOCALITY" },
                { field: "shipping_state", format: "" },
                { field: "shipping_postcode", format: "POSTCODE" },
                { field: "swiftcomplete_what3words", format: "what3words" }
            ]
        }
    }

    function initialiseSwiftcomplete() {
        swiftcomplete.runWhenReady(function () {
            if (document.getElementById(woocommercefields[type].field)) {
                swiftcomplete.controls[type] = new swiftcomplete.PlaceAutoComplete({
                    key,
                    searchFor: searchFor,
                    field: document.getElementById(woocommercefields[type].field),
                    emptyQueryMode: 'prompt',
                    promptText: placeholder,
                    noResultsText: 'No addresses found - click here to enter your address manually',
                    manualEntryText: 'Can\'t find your address? Click here to enter manually',
                    populateLineFormat: woocommercefields[type].populateLineFormat.map(f => ({
                        field: document.getElementById(f.field),
                        format: f.format
                    }))
                });

                swiftcomplete.controls[type].biasTowards(biasTowards);

                var addressFields = [
                    { container: document.getElementById(type + '_company_field'), field: document.getElementById(type + '_company') },
                    { container: document.getElementById(type + '_address_1_field'), field: document.getElementById(type + '_address_1') },
                    { container: document.getElementById(type + '_address_2_field'), field: document.getElementById(type + '_address_2') },
                    { container: document.getElementById(type + '_city_field'), field: document.getElementById(type + '_city') },
                    { container: document.getElementById(type + '_state_field'), field: document.getElementById(type + '_state') },
                    { container: document.getElementById(type + '_postcode_field'), field: document.getElementById(type + '_postcode') }
                ];

                document.getElementById(woocommercefields[type].field).addEventListener('swiftcomplete:place:selected', function (e) {
                    for (var i = 0; i < addressFields.length; i++)
                        addressFields[i].container.style.display = 'block';
                }, false);

                document.getElementById(woocommercefields[type].field).addEventListener('swiftcomplete:place:manualentry', function (e) {
                    for (var i = 0; i < addressFields.length; i++) {
                        document.getElementById('swiftcomplete_' + type + '_address_autocomplete_field').style.display = 'none';
                        addressFields[i].container.style.display = 'block';
                    }
                }, false);

                jQuery(function ($) {
                    showOrHideFields(type, addressFields, hideFields, $('select[name=' + type + '_country]').val());

                    $(document.body).on('change', 'select[name=' + type + '_country]', function () {
                        showOrHideFields(type, addressFields, hideFields, $(this).val());
                    });
                });
            }
        });
    }

    jQuery(document).ready(initialiseSwiftcomplete)
}

function showOrHideFields(type, addressFields, hideFields, countryCode) {
    if (countryCode)
        swiftcomplete.controls[type].setCountries(countryCode);

    var fieldsVisible = true;

    if (hideFields) {
        var addressValuesExist = false;

        try {
            for (var i = 0; i < addressFields.length; i++) {
                if (!addressFields[i].container || !addressFields[i].field) {
                    addressFields.splice(i, 1);
                    i--;
                    continue;
                }

                if (addressFields[i].field.value.length > 0)
                    addressValuesExist = true;
            }
        } catch (err) {
            addressValuesExist = true;
            fieldsVisible = true;
        }

        if (countryCode && swiftcomplete.controls[type].hasAddressAutocompleteCoverageForCountry(countryCode) && !addressValuesExist)
            fieldsVisible = false;

        for (var i = 0; i < addressFields.length; i++)
            addressFields[i].container.style.display = fieldsVisible ? 'block' : 'none';
    }

    document.getElementById('swiftcomplete_' + type + '_address_autocomplete_field').style.display = ((!countryCode || swiftcomplete.controls[type].hasAddressAutocompleteCoverageForCountry(countryCode)) ? 'block' : 'none');
}