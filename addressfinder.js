function launchAddressLookup(type, key, searchFor, hideFields, biasTowards, placeholder, returnStateCounty) {
    function initialiseSwiftcomplete() {
        swiftcomplete.runWhenReady(function () {
            var autocompleteField = document.getElementById('swiftcomplete_' + type + '_address_autocomplete');

            if (autocompleteField) {
                var address1Format = 'BuildingName, BuildingNumber SecondaryRoad, Road';
                var addressFields = [];

                if (document.getElementById(type + '_address_2'))
                    addressFields.push({ container: document.getElementById(type + '_address_2_field'), field: document.getElementById(type + '_address_2'), format: "SubBuilding" });
                else
                    address1Format = 'SubBuilding, ' + address1Format;

                if (document.getElementById(type + '_company'))
                    addressFields.push({ container: document.getElementById(type + '_company_field'), field: document.getElementById(type + '_company'), format: "Company" });
                else
                    address1Format = 'Company, ' + address1Format;

                addressFields.push({ container: document.getElementById(type + '_address_1_field'), field: document.getElementById(type + '_address_1'), format: address1Format });
                addressFields.push({ container: document.getElementById(type + '_city_field'), field: document.getElementById(type + '_city'), format: "TertiaryLocality, SecondaryLocality, PRIMARYLOCALITY" });

                if (document.getElementById(type + '_state') && returnStateCounty)
                    addressFields.push({ container: document.getElementById(type + '_state_field'), field: document.getElementById(type + '_state'), format: "County" });
                else
                    addressFields.push({ container: document.getElementById(type + '_state_field'), field: document.getElementById(type + '_state'), format: "" });

                addressFields.push({ container: document.getElementById(type + '_postcode_field'), field: document.getElementById(type + '_postcode'), format: "POSTCODE" });

                if (searchFor && searchFor.indexOf('what3words') != -1)
                    addressFields.push({ container: document.getElementById('swiftcomplete_what3words_field'), field: document.getElementById("swiftcomplete_what3words"), format: "what3words" });

                swiftcomplete.controls[type] = new swiftcomplete.PlaceAutoComplete({
                    key,
                    searchFor: searchFor,
                    field: autocompleteField,
                    emptyQueryMode: 'prompt',
                    promptText: placeholder,
                    noResultsText: 'No addresses found - click here to enter your address manually',
                    manualEntryText: 'Can\'t find your address? Click here to enter manually',
                    populateLineFormat: addressFields.map(f => ({
                        field: f.field,
                        format: f.format
                    }))
                });

                swiftcomplete.controls[type].biasTowards(biasTowards);

                autocompleteField.addEventListener('swiftcomplete:place:selected', function (e) {
                    for (var i = 0; i < addressFields.length; i++)
                        addressFields[i].container.style.display = 'block';
                }, false);

                autocompleteField.addEventListener('swiftcomplete:place:manualentry', function (e) {
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

                if (addressFields[i].field.value.length > 0 && !(addressFields[i].field.id == 'billing_state' || addressFields[i].field.id == 'shipping_state'))
                    addressValuesExist = true;
            }
        } catch (err) {
            addressValuesExist = true;
            fieldsVisible = true;
        }

        if (countryCode && swiftcomplete.controls[type].hasAddressAutocompleteCoverageForCountry(countryCode) && !addressValuesExist)
            fieldsVisible = false;


        for (var i = 0; i < addressFields.length; i++)
            addressFields[i].container.style.display = (addressFields[i].field.id == 'swiftcomplete_what3words' || fieldsVisible) ? 'block' : 'none';
    }

    document.getElementById('swiftcomplete_' + type + '_address_autocomplete_field').style.display = ((!countryCode || swiftcomplete.controls[type].hasAddressAutocompleteCoverageForCountry(countryCode)) ? 'block' : 'none');
}