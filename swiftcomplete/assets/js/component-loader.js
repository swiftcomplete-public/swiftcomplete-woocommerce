'use strict';

/**
 * Swiftcomplete WooCommerce Component Loader
 * Handles initialization and management of Swiftcomplete address autocomplete components
 */

/**
 * Field format templates for address population
 */
const FIELD_FORMATS = {
    ADDRESS_1_BASE: 'BuildingName, BuildingNumber SecondaryRoad, Road',
    ADDRESS_1_WITH_SUBBUILDING: 'SubBuilding, BuildingName, BuildingNumber SecondaryRoad, Road',
    ADDRESS_1_WITH_COMPANY: 'Company, BuildingName, BuildingNumber SecondaryRoad, Road',
    ADDRESS_1_WITH_BOTH: 'Company, SubBuilding, BuildingName, BuildingNumber SecondaryRoad, Road',
    ADDRESS_2: 'SubBuilding',
    COMPANY: 'Company',
    CITY: 'TertiaryLocality, SecondaryLocality, PRIMARYLOCALITY',
    POSTCODE: 'POSTCODE',
    WHAT3WORDS: 'what3words',
};

/**
 * Utility object for building address field configurations
 */
const sc_address_builder = {
    /**
     * Build address field configuration array for Swiftcomplete
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Object} settings - Settings object with searchFor and state_counties
     * @param {Object} config - Full config object
     * @param {HTMLElement} autocompleteField - The autocomplete field element
     * @returns {Array} Array of field configuration objects
     */
    build(addressType, settings, config, autocompleteField) {
        const fields = [];
        let address1Format = FIELD_FORMATS.ADDRESS_1_BASE;

        // Handle address_2 field
        const address2 = wc_checkout_field.getFieldWithContainer(addressType, 'address_2');
        if (address2?.field) {
            fields.push({
                container: address2.container,
                field: address2.field,
                format: FIELD_FORMATS.ADDRESS_2,
            });
        } else {
            address1Format = FIELD_FORMATS.ADDRESS_1_WITH_SUBBUILDING;
            this.setupAddress2Toggle(addressType, config, autocompleteField);
        }

        // Handle company field
        const company = wc_checkout_field.getFieldWithContainer(addressType, 'company');
        if (company?.field) {
            fields.push({
                container: company.container,
                field: company.field,
                format: FIELD_FORMATS.COMPANY,
            });
            address1Format = address1Format.includes('SubBuilding')
                ? FIELD_FORMATS.ADDRESS_1_WITH_BOTH
                : FIELD_FORMATS.ADDRESS_1_WITH_COMPANY;
        }

        // Address line 1
        const address1 = wc_checkout_field.getFieldWithContainer(addressType, 'address_1');
        if (address1?.field) {
            fields.push({
                container: address1.container,
                field: address1.field,
                format: address1Format,
            });
        }

        // City
        const city = wc_checkout_field.getFieldWithContainer(addressType, 'city');
        if (city?.field) {
            fields.push({
                container: city.container,
                field: city.field,
                format: FIELD_FORMATS.CITY,
            });
        }

        // State
        const state = wc_checkout_field.getFieldWithContainer(addressType, 'state');
        if (state?.field) {
            const stateFormat = settings.state_counties ? 'STATEABBREVIATION' : '';
            fields.push({
                container: state.container,
                field: state.field,
                format: stateFormat,
            });
        }

        // Postcode
        const postcode = wc_checkout_field.getFieldWithContainer(addressType, 'postcode');
        if (postcode?.field) {
            fields.push({
                container: postcode.container,
                field: postcode.field,
                format: FIELD_FORMATS.POSTCODE,
            });
        }

        // What3words (if enabled)
        if (settings.search_for?.indexOf('what3words') !== -1) {
            const what3words = wc_checkout_field.getFieldWithContainer(
                addressType,
                'swiftcomplete-what3words'
            );
            if (what3words?.field) {
                fields.push({
                    container: what3words.container,
                    field: what3words.field,
                    format: FIELD_FORMATS.WHAT3WORDS,
                });
            }
        }

        return fields;
    },

    /**
     * Setup toggle listener for address_2 field when it's initially hidden
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Object} config - Full config object
     * @param {HTMLElement} autocompleteField - The autocomplete field element
     */
    setupAddress2Toggle(addressType, config, autocompleteField) {
        const toggle = document.querySelector(
            `#${addressType} .wc-block-components-address-form__address_2-toggle`
        );
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', () => {
            wc_checkout_field.retryUntil(
                () => {
                    const field = wc_checkout_field.getField(addressType, 'address_2');
                    return !!field;
                },
                () => {
                    sc_control.destroy(addressType);
                    initializeControl(addressType, config, autocompleteField);
                },
                (attempts) => {
                    console.warn(
                        `Swiftcomplete: Failed to find address_2 field for type "${addressType}" after ${attempts} attempts`
                    );
                }
            );
        });
    },
};

/**
 * Utility object for managing Swiftcomplete control instances
 */
const sc_control = {
    /**
     * Destroy existing Swiftcomplete control for an address type
     * @param {string} addressType - 'billing' or 'shipping'
     */
    destroy(addressType) {
        if (swiftcomplete.controls?.[addressType]) {
            const container = swiftcomplete.controls[addressType].container;
            container?.remove();
            delete swiftcomplete.controls[addressType];
        }
    },

    /**
     * Create a new Swiftcomplete control instance
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Object} settings - Settings object
     * @param {HTMLElement} autocompleteField - The autocomplete field element
     * @param {Array} populateLineFormatConfig - Field configuration array
     */
    create(addressType, settings, autocompleteField, populateLineFormatConfig) {
        this.destroy(addressType);

        const placeholder = settings[`${addressType}_placeholder`];
        swiftcomplete.controls[addressType] = new swiftcomplete.SwiftLookup({
            key: settings.api_key,
            searchFor: settings.search_for,
            field: autocompleteField,
            emptyQueryMode: 'prompt',
            promptText: placeholder,
            noResultsText: 'No addresses found - click here to enter your address manually',
            manualEntryText: "Can't find your address? Click here to enter manually",
            populateReact: true,
            populateLineFormat: populateLineFormatConfig,
        });

        if (settings.bias_lat_lon) {
            swiftcomplete.controls[addressType].biasTowards(settings.bias_lat_lon);
        }
    },

    /**
     * Recreate control with updated field configuration
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Object} settings - Settings object
     * @param {HTMLElement} autocompleteField - The autocomplete field element
     * @param {Array} addressFields - Updated address fields array
     * @param {string|null} countryCode - Country code to set
     */
    recreate(addressType, settings, autocompleteField, addressFields, countryCode) {
        const populateLineFormat = addressFields.map((f) => ({
            field: f.field,
            format: f.format,
        }));

        this.create(addressType, settings, autocompleteField, populateLineFormat);

        if (countryCode && swiftcomplete.controls[addressType]) {
            swiftcomplete.controls[addressType].setCountries(countryCode);
        }
    },
};

/**
 * Utility object for handling address selection events
 */
const sc_select_handler = {
    /**
     * Handle address selection from Swiftcomplete
     * @param {Event} event - The selection event
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Function} buildAddressFields - Function to rebuild address fields
     */
    handleSelected(event, addressType, buildAddressFields) {
        const addressFields = buildAddressFields();
        const postcodeField = wc_checkout_field.findField(addressFields, addressType, 'postcode');
        const stateField = wc_checkout_field.findField(addressFields, addressType, 'state');
        const what3wordsField = wc_checkout_field.findField(
            addressFields,
            addressType,
            'swiftcomplete-what3words'
        );

        // Trigger postcode input event
        if (postcodeField?.field) {
            postcodeField.field.dispatchEvent(new Event('input', { bubbles: true }));
        }

        // Update state field
        if (stateField?.field) {
            this.updateStateField(event, stateField.field);
        }

        // Show/hide fields based on selection
        this.updateFieldVisibility(addressFields, addressType, what3wordsField);

        // Handle what3words field
        if (what3wordsField?.field) {
            this.handleWhat3wordsField(what3wordsField, addressType);
        }
    },

    /**
     * Update state field value from selected address
     * @param {Event} event - The selection event
     * @param {HTMLElement} stateField - The state select field
     */
    updateStateField(event, stateField) {
        setTimeout(() => {
            const selected = event.detail?.result;
            const stateValue =
                selected?.populatedRecord?.data?.admin?.stateProvinceAbbreviation?.text;
            const option =
                stateValue &&
                Array.from(stateField.options).find(
                    (opt) => opt.value === stateValue || opt.textContent === stateValue
                );
            if (option) stateField.value = option.value;
            stateField.dispatchEvent(new Event('change', { bubbles: true }));
        }, COMPONENT_DEFAULTS.STATE_UPDATE_DELAY);
    },

    /**
     * Update visibility of address fields after selection
     * @param {Array} addressFields - Array of field objects
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Object|undefined} what3wordsField - What3words field object
     */
    updateFieldVisibility(addressFields, addressType, what3wordsField) {
        addressFields.forEach((fieldItem) => {
            if (!fieldItem.container) {
                return;
            }

            if (fieldItem.field?.id === `${addressType}-swiftcomplete-what3words`) {
                // Handle what3words visibility with delay
                setTimeout(() => {
                    const hasValue = fieldItem.field?.value?.trim().length > 0;
                    fieldItem.container.style.display = hasValue ? 'block' : 'none';
                }, COMPONENT_DEFAULTS.WHAT3WORDS_VISIBILITY_DELAY);
            } else {
                fieldItem.container.style.display = 'block';
            }
        });
    },

    /**
     * Handle what3words field updates after address selection
     * @param {Object} what3wordsField - What3words field object
     * @param {string} addressType - 'billing' or 'shipping'
     */
    handleWhat3wordsField(what3wordsField, addressType) {
        if (typeof w3w_field === 'undefined' || !w3w_field.saveFieldExtensionData) {
            return;
        }

        setTimeout(() => {
            w3w_field.saveFieldExtensionData();
            if (what3wordsField.field?.value?.trim().length > 0 && what3wordsField.container) {
                what3wordsField.container.style.display = 'block';
            }
        }, COMPONENT_DEFAULTS.WHAT3WORDS_UPDATE_DELAY);
    },

    /**
     * Handle manual entry mode
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Function} buildAddressFields - Function to rebuild address fields
     */
    handleManualEntry(addressType, buildAddressFields) {
        const addressFields = buildAddressFields();
        const what3wordsField = wc_checkout_field.findField(
            addressFields,
            addressType,
            'swiftcomplete-what3words'
        );

        // Hide what3words field
        if (what3wordsField?.container) {
            what3wordsField.container.style.display = 'none';
        }

        // Show all other fields
        addressFields.forEach((fieldItem) => {
            if (fieldItem.container) {
                fieldItem.container.style.display = 'block';
            }
        });
    },
};

/**
 * Utility object for managing field visibility
 */
const sc_field_visibility = {
    /**
     * Show or hide address fields based on coverage and settings
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Array} addressFields - Array of field objects
     * @param {boolean} hideFields - Whether to hide fields when coverage exists
     * @param {string|null} countryCode - Country code to check coverage for
     * @param {boolean} isBlocks - Whether this is blocks checkout
     */
    update(addressType, addressFields, hideFields, countryCode, isBlocks) {
        const control = swiftcomplete.controls[addressType];
        if (!control) {
            return;
        }

        // Set country if provided
        if (countryCode) {
            control.setCountries(countryCode);
        }

        // Check coverage - ensure we have a valid country code before checking
        let hasCoverage = false;
        if (countryCode && typeof control.hasAddressAutocompleteCoverageForCountry === 'function') {
            try {
                hasCoverage = control.hasAddressAutocompleteCoverageForCountry(countryCode);
            } catch (e) {
                console.warn('Swiftcomplete: Error checking coverage for country:', countryCode, e);
                hasCoverage = false;
            }
        }

        // Update address fields visibility
        if (hideFields && hasCoverage) {
            this.updateWithHideFields(addressFields, addressType, isBlocks);
        } else {
            this.updateWithoutHideFields(addressFields, countryCode, hasCoverage);
        }

        // Update what3words field visibility
        this.updateWhat3wordsField(addressType, countryCode, hasCoverage);

        // Update address search field visibility
        this.updateAddressSearchField(addressType, countryCode, hasCoverage);
    },

    /**
     * Update field visibility when hideFields is enabled
     * @param {Array} addressFields - Array of field objects
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {boolean} isBlocks - Whether this is blocks checkout
     */
    updateWithHideFields(addressFields, addressType, isBlocks) {
        const prefix = `${addressType}${isBlocks ? '-' : '_'}`;
        let addressValuesExist = false;

        try {
            const validFields = addressFields.filter(
                (item) => item.container && item.field
            );

            for (const fieldItem of validFields) {
                if (
                    fieldItem.field.value.length > 0 &&
                    fieldItem.field.id !== `${prefix}state`
                ) {
                    addressValuesExist = true;
                    break;
                }
            }
        } catch (err) {
            addressValuesExist = true;
        }

        const fieldsVisible = addressValuesExist;

        addressFields.forEach((fieldItem) => {
            if (!fieldItem.container) {
                return;
            }

            const isWhat3words = fieldItem.field.id === 'swiftcomplete_what3words';
            fieldItem.container.style.display =
                isWhat3words || fieldsVisible ? 'block' : 'none';
        });
    },

    /**
     * Update field visibility when hideFields is disabled
     * @param {Array} addressFields - Array of field objects
     * @param {string|null} countryCode - Country code
     * @param {boolean} hasCoverage - Whether country has coverage
     */
    updateWithoutHideFields(addressFields, countryCode, hasCoverage) {
        addressFields.forEach((fieldItem) => {
            if (!fieldItem.container || !fieldItem.field) {
                return;
            }

            if (fieldItem.field.id === 'swiftcomplete-what3words') {
                const hasValue = fieldItem.field.value?.trim().length > 0;
                const shouldShow = hasValue && (!countryCode || hasCoverage);
                fieldItem.container.style.display = shouldShow ? 'block' : 'none';
            } else {
                fieldItem.container.style.display = 'block';
            }
        });
    },

    /**
     * Update what3words field visibility
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {string|null} countryCode - Country code
     * @param {boolean} hasCoverage - Whether country has coverage
     */
    updateWhat3wordsField(addressType, countryCode, hasCoverage) {
        const what3wordsField = wc_checkout_field.getWhat3wordsField(addressType);
        if (!what3wordsField) {
            return;
        }

        const hasValue = what3wordsField.value?.trim().length > 0;
        const shouldShow = hasValue && (!countryCode || hasCoverage);
        what3wordsField.parentNode.style.display = shouldShow ? 'block' : 'none';
    },

    /**
     * Update address search field visibility
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {string|null} countryCode - Country code
     * @param {boolean} hasCoverage - Whether country has coverage
     */
    updateAddressSearchField(addressType, countryCode, hasCoverage) {
        const addressSearchField = wc_checkout_field.getField(
            addressType,
            'swiftcomplete-address-search'
        );
        if (!addressSearchField) {
            return;
        }

        const addressSearchContainer =
            addressSearchField.closest('.wc-block-components-text-input') ||
            addressSearchField.parentNode;
        if (!addressSearchContainer) {
            return;
        }

        // Determine visibility:
        // - Show if no country is selected (initial state)
        // - Show if country is selected AND has coverage
        // - Hide if country is selected but has NO coverage
        let shouldShow = true;
        if (countryCode) {
            // Country is selected - only show if it has coverage
            shouldShow = hasCoverage === true;
        }
        // If no country, show by default (shouldShow remains true)

        addressSearchContainer.style.display = shouldShow ? 'block' : 'none';
    },
};

/**
 * Utility object for handling country change events
 */
const sc_country = {
    /**
     * Create a country change handler function
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Object} config - Full config object
     * @param {HTMLElement} autocompleteField - The autocomplete field element
     * @param {Function} buildAddressFields - Function to rebuild address fields
     * @returns {Function} Event handler function
     */
    createHandler(addressType, config, autocompleteField, buildAddressFields) {
        let isHandling = false;

        return (event) => {
            const target = event.target;
            const fieldId = target.id || '';
            const fieldName = target.name || '';
            const isCountryField =
                fieldId.endsWith(`${addressType}-country`) ||
                fieldId.endsWith(`${addressType}_country`) ||
                fieldName.endsWith(`${addressType}-country`) ||
                fieldName.endsWith(`${addressType}_country`);

            if (!isCountryField || isHandling) {
                return;
            }

            isHandling = true;

            setTimeout(() => {
                const currentCountryField = wc_checkout_field.getField(addressType, 'country');
                const newCountry = currentCountryField
                    ? currentCountryField.value
                    : target.value || '';

                console.log('Swiftcomplete: Country changed:', newCountry);

                const addressFields = buildAddressFields();
                const what3wordsField = wc_checkout_field.findField(
                    addressFields,
                    addressType,
                    'swiftcomplete-what3words'
                );

                // Recreate control with new configuration
                sc_control.recreate(
                    addressType,
                    config.settings,
                    autocompleteField,
                    addressFields,
                    newCountry
                );

                // Clear what3words field
                if (what3wordsField?.field) {
                    what3wordsField.field.value = '';
                    if (typeof w3w_field !== 'undefined' && w3w_field.removeValue) {
                        w3w_field.removeValue(addressType);
                    }
                }

                // Update field visibility
                sc_field_visibility.update(
                    addressType,
                    addressFields,
                    config.settings.hide_fields,
                    newCountry,
                    config.isBlocks
                );

                isHandling = false;
            }, COMPONENT_DEFAULTS.COUNTRY_CHANGE_DELAY);
        };
    },
};

/**
 * Initialize Swiftcomplete control for an address type
 * @param {string} addressType - 'billing' or 'shipping'
 * @param {Object} config - Configuration object
 * @param {HTMLElement} autocompleteField - The autocomplete field element
 */
function initializeControl(addressType, config, autocompleteField) {
    if (swiftcomplete.controls?.[addressType]) {
        console.log(
            `Swiftcomplete: Control for type "${addressType}" already initialized, skipping`
        );
        return;
    }

    const settings = config.settings;
    const isBlocks = config.isBlocks;

    try {
        // Build address fields configuration
        const buildAddressFields = () =>
            sc_address_builder.build(addressType, settings, config, autocompleteField);

        let addressFields = buildAddressFields();

        // Create Swiftcomplete control
        const populateLineFormat = addressFields.map((f) => ({
            field: f.field,
            format: f.format,
        }));
        sc_control.create(addressType, settings, autocompleteField, populateLineFormat);

        // Setup event listeners
        autocompleteField.addEventListener(
            'swiftcomplete:swiftlookup:selected',
            (e) => {
                sc_select_handler.handleSelected(e, addressType, buildAddressFields);
            },
            false
        );

        autocompleteField.addEventListener(
            'swiftcomplete:swiftlookup:manualentry',
            () => {
                sc_select_handler.handleManualEntry(addressType, buildAddressFields);
            },
            false
        );

        // Setup country change handler
        const countryField = wc_checkout_field.getField(addressType, 'country');
        const defaultCountry = countryField ? countryField.value : undefined;

        sc_field_visibility.update(
            addressType,
            addressFields,
            settings.hide_fields,
            defaultCountry,
            isBlocks
        );

        const handleCountryChange = sc_country.createHandler(
            addressType,
            config,
            autocompleteField,
            buildAddressFields
        );

        const checkoutBlock = wc_checkout_field.getCheckoutBlock();
        const delegateContainer = checkoutBlock || document.body;

        if (delegateContainer) {
            delegateContainer.addEventListener('change', handleCountryChange, false);
        }
    } catch (err) {
        console.error('Swiftcomplete: Error initializing control:', err);
    }
}

/**
 * Initialize Swiftcomplete blocks for an address type
 * @param {string} addressType - 'billing' or 'shipping'
 * @param {Object} config - Configuration object
 */
function initialiseSwiftcompleteBlocks(addressType, config) {
    wc_checkout_field.retryUntil(
        () => {
            const field = wc_checkout_field.getField(
                addressType,
                wc_checkout_field.addressSearchFieldId
            );
            return !!field;
        },
        () => {
            const autocompleteField = wc_checkout_field.getField(
                addressType,
                wc_checkout_field.addressSearchFieldId
            );
            if (autocompleteField) {
                initializeControl(addressType, config, autocompleteField);
            }
        },
        (attempts) => {
            console.warn(
                `Swiftcomplete: Failed to find swiftcomplete-address-search field for type "${addressType}" after ${attempts} attempts`
            );
        }
    );
}

/**
 * Load Swiftcomplete component on checkout forms
 * @param {Object} config - Configuration object
 */
function loadSwiftcompleteComponent(config) {
    if (typeof sc_compat !== 'undefined' && !sc_compat.supported) {
        console.warn('Swiftcomplete: Browser not compatible. Skipping component initialization.');
        return;
    }

    console.log('Swiftcomplete: Load Swiftcomplete component on blocks checkout field', config);

    const addressForms = wc_checkout_field.getAddressForms();
    addressForms?.forEach((addressForm) => {
        initialiseSwiftcompleteBlocks(addressForm.id, config);
    });
}
