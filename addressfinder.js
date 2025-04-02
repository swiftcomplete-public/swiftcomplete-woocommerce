function launchAddressLookup(
  type,
  key,
  searchFor,
  hideFields,
  biasTowards,
  placeholder,
  returnStateCounty
) {
  function initialiseSwiftcomplete() {
    swiftcomplete.runWhenReady(function () {
      var autocompleteField = document.getElementById(
        'swiftcomplete_' + type + '_address_autocomplete'
      );

      if (autocompleteField) {
        var address1Format = 'BuildingName, BuildingNumber SecondaryRoad, Road';
        var addressFields = [];

        if (document.getElementById(type + '_address_2'))
          addressFields.push({
            container: document.getElementById(type + '_address_2_field'),
            field: document.getElementById(type + '_address_2'),
            format: 'SubBuilding',
          });
        else address1Format = 'SubBuilding, ' + address1Format;

        if (document.getElementById(type + '_company'))
          addressFields.push({
            container: document.getElementById(type + '_company_field'),
            field: document.getElementById(type + '_company'),
            format: 'Company',
          });
        else address1Format = 'Company, ' + address1Format;

        addressFields.push({
          container: document.getElementById(type + '_address_1_field'),
          field: document.getElementById(type + '_address_1'),
          format: address1Format,
        });
        addressFields.push({
          container: document.getElementById(type + '_city_field'),
          field: document.getElementById(type + '_city'),
          format: 'TertiaryLocality, SecondaryLocality, PRIMARYLOCALITY',
        });

        if (document.getElementById(type + '_state') && returnStateCounty)
          addressFields.push({
            container: document.getElementById(type + '_state_field'),
            field: document.getElementById(type + '_state'),
            format: 'CeremonialCounty STATEABBREVIATION',
          });
        else
          addressFields.push({
            container: document.getElementById(type + '_state_field'),
            field: document.getElementById(type + '_state'),
            format: '',
          });

        addressFields.push({
          container: document.getElementById(type + '_postcode_field'),
          field: document.getElementById(type + '_postcode'),
          format: 'POSTCODE',
        });

        if (searchFor && searchFor.indexOf('what3words') != -1)
          addressFields.push({
            container: document.getElementById(
              'swiftcomplete_what3words_field'
            ),
            field: document.getElementById('swiftcomplete_what3words'),
            format: 'what3words',
          });

        swiftcomplete.controls[type] = new swiftcomplete.PlaceAutoComplete({
          key,
          searchFor: searchFor,
          field: autocompleteField,
          emptyQueryMode: 'prompt',
          promptText: placeholder,
          noResultsText:
            'No addresses found - click here to enter your address manually',
          manualEntryText:
            "Can't find your address? Click here to enter manually",
          populateLineFormat: addressFields.map((f) => ({
            field: f.field,
            format: f.format,
          })),
        });

        swiftcomplete.controls[type].biasTowards(biasTowards);

        autocompleteField.addEventListener(
          'swiftcomplete:place:selected',
          function (e) {
            for (var i = 0; i < addressFields.length; i++) {
              addressFields[i].container.style.display = 'block';

              if (
                addressFields[i].container &&
                addressFields[i].container.classList &&
                addressFields[i].container.classList.contains(
                  'validate-required'
                )
              ) {
                addressFields[i].container.classList.remove(
                  'woocommerce-invalid',
                  'woocommerce-invalid-required-field'
                );
                addressFields[i].container.classList.add(
                  'woocommerce-validated'
                );
              }
            }
          },
          false
        );

        autocompleteField.addEventListener(
          'swiftcomplete:place:manualentry',
          function (e) {
            for (var i = 0; i < addressFields.length; i++) {
              document.getElementById(
                'swiftcomplete_' + type + '_address_autocomplete_field'
              ).style.display = 'none';
              addressFields[i].container.style.display = 'block';
            }
          },
          false
        );

        jQuery(function ($) {
          showOrHideFields(
            type,
            addressFields,
            hideFields,
            $('select[name=' + type + '_country]').val(),
            false
          );

          $(document.body).on(
            'change',
            'select[name=' + type + '_country]',
            function () {
              showOrHideFields(
                type,
                addressFields,
                hideFields,
                $(this).val(),
                false
              );
            }
          );
        });
      } else {
        // Workaround for WooCommerce Blocks
        initialiseSwiftcompleteBlocks(
          type,
          key,
          searchFor,
          hideFields,
          biasTowards,
          placeholder,
          returnStateCounty
        );
      }
    });
  }

  setTimeout(function () {
    jQuery(document).ready(initialiseSwiftcomplete);
  }, 300);
}

function launchAdminAddressLookup(
  type,
  key,
  searchFor,
  hideFields,
  biasTowards,
  placeholder,
  returnStateCounty
) {
  function initialiseSwiftcomplete() {
    swiftcomplete.runWhenReady(function () {
      var autocompleteField = document.getElementById(
        'swiftcomplete_' + type + '_address_autocomplete'
      );

      if (autocompleteField) {
        var address1Format = 'BuildingName, BuildingNumber SecondaryRoad, Road';
        var addressFields = [];

        if (document.getElementById('_' + type + '_address_2'))
          addressFields.push({
            field: document.getElementById('_' + type + '_address_2'),
            format: 'SubBuilding',
          });
        else address1Format = 'SubBuilding, ' + address1Format;

        if (document.getElementById('_' + type + '_company'))
          addressFields.push({
            field: document.getElementById('_' + type + '_company'),
            format: 'Company',
          });
        else address1Format = 'Company, ' + address1Format;

        addressFields.push({
          field: document.getElementById('_' + type + '_address_1'),
          format: address1Format,
        });
        addressFields.push({
          field: document.getElementById('_' + type + '_city'),
          format: 'TertiaryLocality, SecondaryLocality, PRIMARYLOCALITY',
        });

        if (document.getElementById('_' + type + '_state') && returnStateCounty)
          addressFields.push({
            field: document.getElementById('_' + type + '_state'),
            format: 'CeremonialCounty STATEABBREVIATION',
          });
        else
          addressFields.push({
            field: document.getElementById('_' + type + '_state'),
            format: '',
          });

        addressFields.push({
          field: document.getElementById('_' + type + '_postcode'),
          format: 'POSTCODE',
        });

        swiftcomplete.controls[type] = new swiftcomplete.PlaceAutoComplete({
          key,
          searchFor: searchFor,
          field: autocompleteField,
          emptyQueryMode: 'prompt',
          promptText: placeholder,
          noResultsText:
            'No addresses found - click here to enter your address manually',
          manualEntryText:
            "Can't find your address? Click here to enter manually",
          populateLineFormat: addressFields.map((f) => ({
            field: f.field,
            format: f.format,
          })),
        });

        swiftcomplete.controls[type].biasTowards(biasTowards);

        jQuery(function ($) {
          swiftcomplete.controls[type].setCountries(
            $('select[name=_' + type + '_country]').val()
          );

          $(document.body).on(
            'change',
            'select[name=_' + type + '_country]',
            function () {
              console.log($('select[name=_' + type + '_country]').val());
              swiftcomplete.controls[type].setCountries(
                $('select[name=_' + type + '_country]')
                  .val()
                  .toLowerCase()
              );
            }
          );
        });
      }
    });
  }

  setTimeout(function () {
    jQuery(document).ready(initialiseSwiftcomplete);
  }, 300);
}

function initialiseSwiftcompleteBlocks(
  type,
  key,
  searchFor,
  hideFields,
  biasTowards,
  placeholder,
  returnStateCounty
) {
  if (!document.getElementById(type + '-address_1')) {
    setTimeout(function () {
      initialiseSwiftcompleteBlocks(
        type,
        key,
        searchFor,
        hideFields,
        biasTowards,
        placeholder,
        returnStateCounty
      );
    }, 1000);
    return;
  }
  try {
    var newFieldContainer = document.createElement('div');
    newFieldContainer.className = 'wc-block-components-text-input';
    newFieldContainer.setAttribute(
      'id',
      'swiftcomplete_' + type + '_address_autocomplete_field'
    );

    var newField = document.createElement('input');
    newField.setAttribute('type', 'text');
    newField.setAttribute(
      'id',
      'swiftcomplete_' + type + '_address_autocomplete'
    );
    newField.setAttribute('placeholder', placeholder);
    newFieldContainer.appendChild(newField);

    document
      .getElementById(type + '-address_1')
      .parentNode.parentNode.insertBefore(
        newFieldContainer,
        document.getElementById(type + '-address_1').parentNode
      );

    autocompleteField = document.getElementById(
      'swiftcomplete_' + type + '_address_autocomplete'
    );

    var address1Format = 'BuildingName, BuildingNumber SecondaryRoad, Road';
    var addressFields = [];

    if (document.getElementById(type + '-address_2'))
      addressFields.push({
        container: document.getElementById(type + '-address_2').parentNode,
        field: document.getElementById(type + '-address_2'),
        format: 'SubBuilding',
      });
    else address1Format = 'SubBuilding, ' + address1Format;

    if (document.getElementById(type + '-company'))
      addressFields.push({
        container: document.getElementById(type + '-company').parentNode,
        field: document.getElementById(type + '-company'),
        format: 'Company',
      });
    else address1Format = 'Company, ' + address1Format;

    addressFields.push({
      container: document.getElementById(type + '-address_1').parentNode,
      field: document.getElementById(type + '-address_1'),
      format: address1Format,
    });
    addressFields.push({
      container: document.getElementById(type + '-city').parentNode,
      field: document.getElementById(type + '-city'),
      format: 'TertiaryLocality, SecondaryLocality, PRIMARYLOCALITY',
    });

    if (document.getElementById(type + '-state') && returnStateCounty)
      addressFields.push({
        container: document.getElementById(type + '-state').parentNode,
        field: document.getElementById(type + '-state'),
        format: 'County',
      });
    else
      addressFields.push({
        container: document.getElementById(type + '-state').parentNode,
        field: document.getElementById(type + '-state'),
        format: '',
      });

    addressFields.push({
      container: document.getElementById(type + '-postcode').parentNode,
      field: document.getElementById(type + '-postcode'),
      format: 'POSTCODE',
    });

    if (searchFor && searchFor.indexOf('what3words') != -1)
      addressFields.push({
        field: document.getElementById('swiftcomplete_what3words'),
        format: 'what3words',
      });

    swiftcomplete.controls[type] = new swiftcomplete.PlaceAutoComplete({
      key,
      searchFor: searchFor,
      field: autocompleteField,
      emptyQueryMode: 'prompt',
      promptText: placeholder,
      noResultsText:
        'No addresses found - click here to enter your address manually',
      manualEntryText: "Can't find your address? Click here to enter manually",
      populateReact: true,
      populateLineFormat: addressFields.map((f) => ({
        field: f.field,
        format: f.format,
      })),
    });

    swiftcomplete.controls[type].biasTowards(biasTowards);

    autocompleteField.addEventListener(
      'swiftcomplete:place:selected',
      function (e) {
        if (document.getElementById(type + '-postcode'))
          document
            .getElementById(type + '-postcode')
            .dispatchEvent(new Event('input', { bubbles: true }));

        for (var i = 0; i < addressFields.length; i++)
          addressFields[i].container.style.display = 'block';
      },
      false
    );

    autocompleteField.addEventListener(
      'swiftcomplete:place:manualentry',
      function (e) {
        for (var i = 0; i < addressFields.length; i++) {
          document.getElementById(
            'swiftcomplete_' + type + '_address_autocomplete_field'
          ).style.display = 'none';
          addressFields[i].container.style.display = 'block';
        }
      },
      false
    );

    jQuery(function ($) {
      showOrHideFields(type, addressFields, hideFields, undefined, true);

      $(document.body).on(
        'change',
        'select[name=' + type + '_country]',
        function () {
          showOrHideFields(
            type,
            addressFields,
            hideFields,
            $(this).val(),
            true
          );
        }
      );
    });
  } catch (err) {
    console.error(err);
  }
}

function showOrHideFields(
  type,
  addressFields,
  hideFields,
  countryCode,
  isBlocks
) {
  if (countryCode) swiftcomplete.controls[type].setCountries(countryCode);

  var fieldsVisible = true;

  if (
    hideFields &&
    swiftcomplete.controls[type].hasAddressAutocompleteCoverageForCountry(
      countryCode
    )
  ) {
    var addressValuesExist = false;

    try {
      for (var i = 0; i < addressFields.length; i++) {
        if (!addressFields[i].container || !addressFields[i].field) {
          addressFields.splice(i, 1);
          i--;
          continue;
        }

        if (
          addressFields[i].field.value.length > 0 &&
          !(
            addressFields[i].field.id == 'billing_state' ||
            addressFields[i].field.id == 'shipping_state' ||
            addressFields[i].field.id == 'billing-state' ||
            addressFields[i].field.id == 'shipping-state'
          )
        )
          addressValuesExist = true;
      }
    } catch (err) {
      addressValuesExist = true;
      fieldsVisible = true;
    }

    if (!addressValuesExist) fieldsVisible = false;

    for (var i = 0; i < addressFields.length; i++)
      addressFields[i].container.style.display =
        addressFields[i].field.id == 'swiftcomplete_what3words' || fieldsVisible
          ? 'block'
          : 'none';
  }

  if (
    document.getElementById(
      'swiftcomplete_' + type + '_address_autocomplete_field'
    )
  )
    document.getElementById(
      'swiftcomplete_' + type + '_address_autocomplete_field'
    ).style.display =
      !countryCode ||
      swiftcomplete.controls[type].hasAddressAutocompleteCoverageForCountry(
        countryCode
      )
        ? 'block'
        : 'none';
}
