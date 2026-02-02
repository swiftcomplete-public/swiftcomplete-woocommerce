'use strict';

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

const sc_address_builder = {
    /**
     * Build address field configuration array for Swiftcomplete
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Object} settings - Settings object with searchFor and state_counties
     * @param {Object} config - Full config object
     * @param {HTMLElement} autocompleteField - The autocomplete field element
     * @returns {Array} Array of field configuration objects
     */
    build(addressType, settings, autocompleteField) {
        const fields = [];
        let address1Format = FIELD_FORMATS.ADDRESS_1_BASE;

        const address2 = sc_fields.getFieldWithContainer(addressType, 'address_2');
        if (address2?.field) {
            fields.push({
                container: address2.container,
                field: address2.field,
                format: FIELD_FORMATS.ADDRESS_2,
            });
        } else {
            address1Format = FIELD_FORMATS.ADDRESS_1_WITH_SUBBUILDING;
            this.setupAddress2Toggle(addressType, settings, autocompleteField);
        }

        const company = sc_fields.getFieldWithContainer(addressType, 'company');
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

        const address1 = sc_fields.getFieldWithContainer(addressType, 'address_1');
        if (address1?.field) {
            fields.push({
                container: address1.container,
                field: address1.field,
                format: address1Format,
            });
        }

        const city = sc_fields.getFieldWithContainer(addressType, 'city');
        if (city?.field) {
            fields.push({
                container: city.container,
                field: city.field,
                format: FIELD_FORMATS.CITY,
            });
        }

        const state = sc_fields.getFieldWithContainer(addressType, 'state');
        if (state?.field) {
            const stateFormat = settings.state_counties ? 'STATEABBREVIATION' : '';
            fields.push({
                container: state.container,
                field: state.field,
                format: stateFormat,
            });
        }

        const postcode = sc_fields.getFieldWithContainer(addressType, 'postcode');
        if (postcode?.field) {
            fields.push({
                container: postcode.container,
                field: postcode.field,
                format: FIELD_FORMATS.POSTCODE,
            });
        }

        if (settings.search_for?.indexOf('what3words') !== -1) {
            const what3words = sc_fields.getFieldWithContainer(
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
     * Only applies to blocks checkout - shortcode checkout always shows address_2 if it exists
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Object} config - Full config object
     * @param {HTMLElement} autocompleteField - The autocomplete field element
     */
    setupAddress2Toggle(addressType, config, autocompleteField) {
        if (!config.isBlocks) {
            return;
        }

        const toggle = document.querySelector(
            `#${addressType} .wc-block-components-address-form__address_2-toggle`
        );
        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', () => {
            wc_fields_util.retryUntil(
                () => {
                    const field = sc_fields.getField(addressType, 'address_2');
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

        if (settings.bias_towards) {
            swiftcomplete.controls[addressType].biasTowards(settings.bias_towards);
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

const sc_select_handler = {
    /**
     * Handle address selection from Swiftcomplete
     * @param {Event} event - The selection event
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {Function} buildAddressFields - Function to rebuild address fields
     */
    handleSelected(event, addressType, buildAddressFields) {
        const addressFields = buildAddressFields();
        const postcodeField = sc_fields.findField(addressFields, addressType, 'postcode');
        const stateField = sc_fields.findField(addressFields, addressType, 'state');
        const what3wordsField = sc_fields.findField(
            addressFields,
            addressType,
            'swiftcomplete-what3words'
        );

        if (postcodeField?.field) {
            postcodeField.field.dispatchEvent(new Event('input', { bubbles: true }));
        }

        if (stateField?.field) {
            this.updateStateField(event, stateField.field);
        }

        this.updateFieldVisibility(addressFields, addressType, what3wordsField);

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

            if ([`${addressType}-swiftcomplete-what3words`, `_${addressType}-swiftcomplete-what3words`, `${addressType}_swiftcomplete_what3words`].includes(fieldItem.field?.id)) {
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
        const what3wordsField = sc_fields.findField(
            addressFields,
            addressType,
            'swiftcomplete-what3words'
        );

        if (what3wordsField?.container) {
            what3wordsField.container.style.display = 'none';
        }

        addressFields.forEach((fieldItem) => {
            if (fieldItem.container) {
                fieldItem.container.style.display = 'block';
            }
        });
    },
};

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

        if (countryCode) {
            control.setCountries(countryCode);
        }

        let hasCoverage = false;
        if (countryCode && typeof control.hasAddressAutocompleteCoverageForCountry === 'function') {
            try {
                hasCoverage = control.hasAddressAutocompleteCoverageForCountry(countryCode);
            } catch (e) {
                console.warn('Swiftcomplete: Error checking coverage for country:', countryCode, e);
                hasCoverage = false;
            }
        }

        if (hideFields && hasCoverage) {
            this.updateWithHideFields(addressFields, addressType, isBlocks);
        } else {
            this.updateWithoutHideFields(addressFields, addressType, countryCode, hasCoverage);
        }

        this.updateWhat3wordsField(addressType, countryCode, hasCoverage);

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

            const isWhat3words = [`${addressType}-swiftcomplete-what3words`, `${addressType}_swiftcomplete_what3words`].includes(fieldItem.field.id);
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
    updateWithoutHideFields(addressFields, addressType, countryCode, hasCoverage) {
        addressFields.forEach((fieldItem) => {
            if (!fieldItem.container || !fieldItem.field) {
                return;
            }

            if ([`${addressType}-swiftcomplete-what3words`, `_${addressType}_swiftcomplete-what3words`, `${addressType}_swiftcomplete_what3words`].includes(fieldItem.field.id)) {
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
        const what3words = sc_fields.getFieldWithContainer(addressType, sc_fields.what3wordsFieldId);
        if (!what3words) {
            return;
        }

        if (what3words.field?.defaultValue?.trim().length > 0) {
            what3words.field.value = what3words.field.defaultValue;
        }

        const hasValue = what3words.field?.value?.trim().length > 0;
        const shouldShow = hasValue && (!countryCode || hasCoverage);

        what3words.container.style.display = shouldShow ? 'block' : 'none';
    },

    /**
     * Update address search field visibility
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {string|null} countryCode - Country code
     * @param {boolean} hasCoverage - Whether country has coverage
     */
    updateAddressSearchField(addressType, countryCode, hasCoverage) {
        // Try both field ID formats (hyphen for blocks, underscore for shortcode)
        let addressSearchField = sc_fields.getField(
            addressType,
            'swiftcomplete-address-search'
        );

        // If not found with hyphen, try with underscore
        if (!addressSearchField) {
            addressSearchField = sc_fields.getField(
                addressType,
                'swiftcomplete_address_search'
            );
        }

        if (!addressSearchField) {
            return;
        }

        // Find container - blocks checkout uses .wc-block-components-text-input, shortcode uses .form-row
        const addressSearchContainer =
            addressSearchField.closest('.wc-block-components-text-input') ||
            addressSearchField.closest('.form-row') ||
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
    createHandler(addressType, settings, autocompleteField, buildAddressFields) {
        let isHandling = false;

        return (event) => {
            const target = event.target;
            const fieldId = target.id || '';
            const fieldName = target.name || '';
            const isCountryField =
                fieldId.endsWith(`${addressType}-country`) ||
                fieldId.endsWith(`${addressType}_country`) ||
                fieldId === `${addressType}-country` ||
                fieldId === `${addressType}_country` ||
                fieldName.endsWith(`${addressType}-country`) ||
                fieldName.endsWith(`${addressType}_country`) ||
                fieldName === `${addressType}-country` ||
                fieldName === `${addressType}_country` ||
                (target.tagName === 'SELECT' && (
                    fieldId.includes('country') ||
                    fieldName.includes('country')
                ) && (
                        fieldId.includes(addressType) ||
                        fieldName.includes(addressType)
                    ));

            if (!isCountryField) {
                return;
            }

            if (isHandling) {
                return;
            }
            isHandling = true;

            setTimeout(() => {
                const currentCountryField = sc_fields.getField(addressType, 'country');
                const newCountry = currentCountryField
                    ? currentCountryField.value
                    : target.value || '';
                const addressFields = buildAddressFields();
                const what3wordsField = sc_fields.findField(
                    addressFields,
                    addressType,
                    'swiftcomplete-what3words'
                );

                // Recreate control with new configuration
                sc_control.recreate(
                    addressType,
                    settings,
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
                    settings.hide_fields,
                    newCountry,
                    settings.isBlocks
                );

                isHandling = false;
            }, COMPONENT_DEFAULTS.COUNTRY_CHANGE_DELAY);
        };
    },
};

function initializeControl(addressType, settings, autocompleteField) {
    if (swiftcomplete.controls?.[addressType]) {
        return;
    }
    const isBlocks = settings.isBlocks;

    try {
        const buildAddressFields = () =>
            sc_address_builder.build(addressType, settings, autocompleteField);

        let addressFields = buildAddressFields();

        const populateLineFormat = addressFields.map((f) => ({
            field: f.field,
            format: f.format,
        }));
        sc_control.create(addressType, settings, autocompleteField, populateLineFormat);

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

        const handleCountryChange = sc_country.createHandler(
            addressType,
            settings,
            autocompleteField,
            buildAddressFields
        );

        const attachCountryChangeListener = () => {
            const countryField = sc_fields.getField(addressType, 'country');

            if (countryField) {
                countryField.removeEventListener('change', handleCountryChange, false);

                countryField.addEventListener('change', handleCountryChange, false);

                if (typeof jQuery !== 'undefined' && jQuery(countryField).length) {
                    jQuery(countryField).off('change', handleCountryChange); // Remove existing
                    jQuery(countryField).on('change', handleCountryChange);
                }

                return countryField;
            }
            return null;
        };

        let countryField = attachCountryChangeListener();
        const defaultCountry = countryField ? countryField.value : undefined;

        sc_field_visibility.update(
            addressType,
            addressFields,
            settings.hide_fields,
            defaultCountry,
            isBlocks
        );

        if (!countryField) {
            wc_fields_util.retryUntil(
                () => {
                    const field = sc_fields.getField(addressType, 'country');
                    return !!field;
                },
                () => {
                    countryField = attachCountryChangeListener();
                    if (countryField) {
                        const currentCountry = countryField.value;
                        sc_field_visibility.update(
                            addressType,
                            buildAddressFields(),
                            settings.hide_fields,
                            currentCountry,
                            isBlocks
                        );
                    }
                },
                (attempts) => {
                    console.warn(
                        `Swiftcomplete: Failed to find country field for type "${addressType}" after ${attempts} attempts`
                    );
                },
                { maxAttempts: 10, delay: 100 }
            );
        }

        const checkoutBlock = wc_fields_util.getCheckoutForm();
        const delegateContainer = checkoutBlock || document.body;

        if (delegateContainer) {
            delegateContainer.addEventListener('change', handleCountryChange, false);
            if (typeof jQuery !== 'undefined' && jQuery(delegateContainer).length) {
                jQuery(delegateContainer).on('change', handleCountryChange);
            }
        }

        if (!isBlocks && typeof jQuery !== 'undefined') {
            jQuery(document.body).on('checkout_update', function () {
                const currentCountryField = sc_fields.getField(addressType, 'country');
                if (currentCountryField) {
                    const currentCountry = currentCountryField.value;
                    sc_field_visibility.update(
                        addressType,
                        buildAddressFields(),
                        settings.hide_fields,
                        currentCountry,
                        isBlocks
                    );
                }
            });
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
    return wc_fields_util.retryUntil(
        () => {
            const field = sc_fields.getField(
                addressType,
                sc_fields.addressSearchFieldId
            );
            return !!field;
        },
        () => {
            const autocompleteField = sc_fields.getField(
                addressType,
                sc_fields.addressSearchFieldId
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
 * Blocks checkout can mount/unmount the billing form dynamically (e.g. when
 * "Use shipping address for billing" is toggled). This helper watches for new
 * address forms and initializes SwiftLookup for any newly-added types.
 */
const sc_blocks_component_loader = {
    started: false,
    observer: null,
    unsubscribeUseShippingAsBilling: null,
    timeoutId: null,
    pendingAddressTypes: new Set(),

    getRecheckDelayMs() {
        if (typeof FIELD_DEFAULTS !== 'undefined' && FIELD_DEFAULTS.REINJECTION_DELAY) {
            return FIELD_DEFAULTS.REINJECTION_DELAY;
        }
        return 100;
    },

    getRetryWindowMs() {
        const maxAttempts =
            (typeof FIELD_DEFAULTS !== 'undefined' && FIELD_DEFAULTS.MAX_INJECTION_ATTEMPTS) || 20;
        const delay =
            (typeof FIELD_DEFAULTS !== 'undefined' && FIELD_DEFAULTS.INJECTION_RETRY_DELAY) || 200;
        // Add a small buffer to ensure the final retry completes.
        return maxAttempts * delay + delay;
    },

    scheduleCheck(config) {
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
        }
        this.timeoutId = setTimeout(() => this.checkAndInit(config), this.getRecheckDelayMs());
    },

    checkAndInit(config) {
        const addressForms = sc_fields.getAddressForms();
        if (!addressForms || addressForms.length === 0) {
            return;
        }

        Array.from(addressForms).forEach((addressForm) => {
            const addressType = addressForm?.id;
            if (!addressType) {
                return;
            }
            if (swiftcomplete.controls?.[addressType]) {
                return;
            }
            if (this.pendingAddressTypes.has(addressType)) {
                return;
            }

            this.pendingAddressTypes.add(addressType);
            initialiseSwiftcompleteBlocks(addressType, config);

            // Allow re-attempts after the retry loop window elapses (covers async mounts).
            setTimeout(() => {
                this.pendingAddressTypes.delete(addressType);
            }, this.getRetryWindowMs());
        });
    },

    init(config) {
        if (this.started) {
            return;
        }
        this.started = true;

        // Initial delayed check to handle async block mounting.
        this.scheduleCheck(config);

        const checkoutBlock = wc_fields_util.getCheckoutForm();
        if (checkoutBlock && typeof MutationObserver !== 'undefined') {
            this.observer = new MutationObserver((mutations) => {
                const hasAddressFormNode = mutations.some((mutation) =>
                    Array.from(mutation.addedNodes).some((node) => {
                        if (!node || node.nodeType !== 1) {
                            return false;
                        }
                        return (
                            node.matches?.('.wc-block-components-address-form') ||
                            node.querySelector?.('.wc-block-components-address-form')
                        );
                    })
                );

                if (hasAddressFormNode) {
                    this.scheduleCheck(config);
                }
            });

            this.observer.observe(checkoutBlock, { childList: true, subtree: true });
        }

        // Subscribe to the blocks checkout state so we can react immediately when
        // billing becomes separate from shipping.
        try {
            if (typeof wc_checkout !== 'undefined' && typeof wc_checkout.subscribe === 'function') {
                this.unsubscribeUseShippingAsBilling = wc_checkout.subscribe((currentValue) => {
                    if (currentValue === false) {
                        // Billing form will mount; re-scan and initialize when ready.
                        this.scheduleCheck(config);
                        return;
                    }

                    if (currentValue === true) {
                        // Billing form may unmount; clean up any existing billing control to avoid stale bindings.
                        setTimeout(() => {
                            const block = wc_fields_util.getCheckoutForm(true);
                            const billingForm = block?.querySelector(
                                '#billing.wc-block-components-address-form'
                            );
                            if (!billingForm && swiftcomplete.controls?.billing) {
                                sc_control.destroy('billing');
                            }
                            try {
                                if (typeof w3w_field !== 'undefined' && w3w_field.saveFieldExtensionData) {
                                    w3w_field.saveFieldExtensionData();
                                }
                            } catch (err) {
                                console.warn('Swiftcomplete: Failed to save field extension data after billing unmount', err);
                            }
                        }, this.getRecheckDelayMs());
                    }
                });
            }
        } catch (e) {
            // Non-fatal: wp.data may not be available in some environments.
        }
    },
};

/**
 * Load Swiftcomplete component on checkout forms
 * @param {Object} config - Configuration object
 */
function loadSwiftcompleteComponent(config) {
    if (typeof sc_compat !== 'undefined' && !sc_compat.supported) {
        console.warn('Swiftcomplete: Browser not compatible. Skipping component initialization.');
        return;
    }
    const addressForms = sc_fields.getAddressForms();
    addressForms?.forEach((addressForm) => {
        initialiseSwiftcompleteBlocks(addressForm.id, config);
    });

    if (config?.isBlocks) {
        sc_blocks_component_loader.init(config);
    }
}

function setupLocationBiasedSearch(key) {
    const field = document.getElementById('swiftcomplete_bias_towards');
    swiftcomplete.controls["Places search"] = new swiftcomplete.SwiftLookup({
        field,
        key,
        searchFor: ""
    });
    const biasTowards = document.getElementById('swiftcomplete_bias_towards_lat_lon');
    field.addEventListener('swiftcomplete:swiftlookup:selected', function (e) {
        if (biasTowards) {
            biasTowards.value = e.detail.result.geometry.centre.lat + ',' + e.detail.result.geometry.centre.lon;
        }
    }, false);
}
