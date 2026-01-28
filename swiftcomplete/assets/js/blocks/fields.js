'use strict';

const FIELD_SELECTORS = {
  CHECKOUT_FORM_SELECTOR: () => 'div[data-block-name="woocommerce/checkout"].wc-block-checkout',
  ADDRESS_FORM_SELECTOR: '.wc-block-components-address-form',
};

const wc_checkout = {
  isBillingSameAsShipping() {
    if (typeof wp === 'undefined' || !wp.data?.select) {
      return false;
    }

    try {
      const checkoutStore = wp.data.select('wc/store/checkout');
      if (!checkoutStore) {
        return false;
      }
      if (typeof checkoutStore.getUseShippingAsBilling === 'function') {
        return checkoutStore.getUseShippingAsBilling();
      }
      if (typeof checkoutStore.getBillingSameAsShipping === 'function') {
        return checkoutStore.getBillingSameAsShipping();
      }
      // Fallback to state inspection
      const state = checkoutStore.getState();
      if (state?.useShippingAsBilling !== undefined) {
        return state.useShippingAsBilling;
      }
      return false;
    } catch (e) {
      return false;
    }
  },
  subscribe(callback) {
    if (
      typeof wp === 'undefined' ||
      typeof wp.data === 'undefined' ||
      typeof wp.data.subscribe !== 'function' ||
      typeof callback !== 'function'
    ) {
      return null;
    }

    let previousValue = null;
    let isInitialized = false;
    const self = this;
    const unsubscribe = wp.data.subscribe(function () {
      try {
        const currentValue = self.isBillingSameAsShipping();
        if (isInitialized && previousValue !== currentValue) {
          const oldValue = previousValue;
          previousValue = currentValue;
          try {
            callback(currentValue, oldValue);
          } catch (callbackError) {
            console.warn('Swiftcomplete: Callback error in subscribe:', callbackError);
          }
        } else if (!isInitialized) {
          previousValue = currentValue;
          isInitialized = true;
        }
      } catch (e) {
      }
    });

    return unsubscribe;
  },
};

const sc_fields = {
  what3wordsFieldId: null,
  addressSearchFieldId: null,
  createLabel(fieldId, fieldLabel) {
    const label = document.createElement('label');
    label.setAttribute('for', fieldId);
    label.className = 'wc-block-components-text-input__label';
    label.textContent = fieldLabel;
    return label;
  },
  createInput(fieldId, options = {}) {
    const input = document.createElement('input');
    input.type = options.type || 'text';
    input.id = fieldId;
    input.className = 'wc-block-components-text-input__input';
    input.setAttribute('autocomplete', 'off');

    if (options.dataName) {
      input.setAttribute('data-field-name', options.dataName);
    }

    if (options.readonly === true) {
      input.readOnly = true;
    }

    return input;
  },
  setupInputEventListeners(container, input) {
    const syncActiveState = () => {
      const hasValue = input.value.trim().length > 0;
      const hasFocus = document.activeElement === input;
      container.classList.toggle('is-active', hasFocus || hasValue);
    };

    const events = ['focus', 'blur', 'input'];
    events.forEach((eventType) => {
      input.addEventListener(eventType, syncActiveState);
    });

    syncActiveState();
  },
  createCheckoutField(
    addressType,
    fieldId,
    dataFieldName = null,
    options = {}
  ) {
    const fullFieldId = `${addressType}-${fieldId}`;
    const fieldLabel = options.label || FIELD_DEFAULTS.DEFAULT_SEARCH_FIELD_LABEL;

    const container = document.createElement('div');
    container.className = `wc-block-components-text-input wc-block-components-address-form__${fieldId}`;

    const label = this.createLabel(fullFieldId, fieldLabel);

    const inputOptions = {
      readonly: options.readonly === true,
    };

    if (dataFieldName) {
      inputOptions.dataName = `${addressType}/${dataFieldName}`;
    }

    const input = this.createInput(fullFieldId, inputOptions);

    if (options.value) {
      input.value = options.value;
    }

    this.setupInputEventListeners(container, input);

    container.appendChild(input);
    container.appendChild(label);

    return container;
  },
  getField(addressType, fieldId) {
    const block = wc_fields_util.getCheckoutForm();
    if (!block) {
      return null;
    }
    const addressForm = block.querySelector(`#${addressType}`);
    if (!addressForm) {
      return null;
    }
    const field = addressForm.querySelector(`input[id$='${fieldId}'], select[id$='${fieldId}']`);
    if (!field) {
      return null;
    }
    if (field.classList.contains(`wc-block-components-address-form__${fieldId}-hidden-input`)) {
      return null;
    }
    return field;
  },
  findField(addressFields, addressType, fieldId) {
    return addressFields.find((f) => f.field.id === `${addressType}-${fieldId}`);
  },
  /**
     * Get a field element and its container by address type and field ID
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {string} fieldId - The field ID suffix
     * @returns {Object|null} Object with {container, field} or null if not found
     */
  getFieldWithContainer(addressType, fieldId) {
    const field = this.getField(addressType, fieldId);
    if (!field) {
      return null;
    }

    const autocompleteContainer = field.closest('.wc-block-components-address-autocomplete-container');
    if (autocompleteContainer?.parentNode) {
      return { container: autocompleteContainer, field };
    }
    const selector = `${FIELD_SELECTORS.ADDRESS_FORM_SELECTOR}__${fieldId}`;
    const container = field.closest(selector) || field.parentNode;
    if (!container) {
      return null;
    }
    return { container, field };
  },
  getAddressForms() {
    const checkoutBlock = wc_fields_util.getCheckoutForm();
    if (!checkoutBlock) {
      return null;
    }
    const formClass = FIELD_SELECTORS.ADDRESS_FORM_SELECTOR.substring(1);
    const selector = FIELD_DEFAULTS.ADDRESS_TYPES.map((type) => `#${type}.${formClass}`).join(', ');
    return checkoutBlock.querySelectorAll(selector);
  },
  injectFields(callback, fieldId, addressForms) {
    if (typeof callback !== 'function' || !fieldId || !addressForms || addressForms.length === 0) {
      return false;
    }

    let injected = false;
    Array.from(addressForms).forEach((addressForm) => {
      if (
        !FIELD_DEFAULTS.ADDRESS_TYPES.includes(addressForm.id) ||
        wc_fields_util.fieldExists(addressForm, fieldId)
      ) {
        return;
      }

      if (callback.call(this, addressForm, fieldId)) {
        injected = true;
      }
    });

    return injected;
  },
  onCheckoutFormSubmit(callback, setupState) {
    const block = wc_fields_util.getCheckoutForm();
    if (!block || typeof callback !== 'function') {
      return null;
    }

    const form = block.querySelector('form');
    if (!form) {
      return null;
    }

    if (!setupState.formSubmitListener) {
      form.addEventListener('submit', callback);
      setupState.formSubmitListener = true;
    }

    return function () {
      if (form && setupState.formSubmitListener) {
        form.removeEventListener('submit', callback);
        setupState.formSubmitListener = false;
      }
    };
  },
  injectAddressSearchField(addressForm, fieldId) {
    if (!fieldId || wc_fields_util.fieldExists(addressForm, fieldId)) {
      return !!fieldId;
    }

    const address1 = this.getFieldWithContainer(addressForm.id, 'address_1');
    if (!address1?.container?.parentNode) {
      return false;
    }

    const searchField = this.createCheckoutField(addressForm.id, fieldId);
    address1.container.parentNode.insertBefore(searchField, address1.container);
    return true;
  },

  injectWhat3wordsField(addressForm, fieldId) {
    if (!fieldId || wc_fields_util.fieldExists(addressForm, fieldId)) {
      return !!fieldId;
    }

    const dataFieldName = fieldId.replace(/-/g, '/');
    const what3wordsField = this.createCheckoutField(addressForm.id, fieldId, dataFieldName, {
      label: 'what3words address',
      readonly: true,
      value: '',
    });

    what3wordsField.style.display = 'block';
    addressForm.appendChild(what3wordsField);
    return true;
  },
  getWhat3wordsField(addressType) {
    if (!this.what3wordsFieldId) {
      return null;
    }
    return this.getField(addressType, this.what3wordsFieldId);
  },
  updateWhat3wordsField(addressType, value) {
    if (!this.what3wordsFieldId) {
      return false;
    }

    const field = this.getWhat3wordsField(addressType);
    const container = field?.closest('.wc-block-components-text-input');
    if (!field || !container) {
      return false;
    }

    const trimmedValue = value?.trim();
    if (trimmedValue) {
      field.value = trimmedValue;
      container.style.display = 'block';
    } else {
      field.value = '';
      container.style.display = 'none';
    }
    field.dispatchEvent(new Event('input', { bubbles: true }));

    return true;
  },
  setWhat3wordsValue(addressType, value, syncToBilling = true) {
    if (!FIELD_DEFAULTS.ADDRESS_TYPES.includes(addressType)) {
      return false;
    }

    const success = this.updateWhat3wordsField(addressType, value);

    // Sync shipping to billing when checkbox is checked
    if (success && addressType === 'shipping' && syncToBilling && wc_checkout.isBillingSameAsShipping()) {
      this.setWhat3wordsValue('billing', value, false);
    }

    return success;
  },
  syncWhat3wordsBillingFromShipping() {
    if (!wc_checkout.isBillingSameAsShipping()) {
      return true;
    }

    const shippingField = this.getWhat3wordsField('shipping');
    const shippingValue = shippingField?.value?.trim() || null;
    return this.setWhat3wordsValue('billing', shippingValue, false);
  },
};

const w3w_field = {
  getValue(addressType) {
    const field = sc_fields.getWhat3wordsField(addressType);
    return field?.value?.trim() || null;
  },

  getValues() {
    const shippingValue = this.getValue('shipping');
    let billingValue = this.getValue('billing');
    if (wc_checkout.isBillingSameAsShipping() && shippingValue) {
      billingValue = shippingValue;
    }
    return {
      billing: billingValue,
      shipping: shippingValue,
    };
  },
  setValue(addressType, value) {
    const success = sc_fields.setWhat3wordsValue(addressType, value, true);
    if (success) {
      this.saveFieldExtensionData();
    }
    return success;
  },
  removeValue(addressType) {
    const success = sc_fields.setWhat3wordsValue(addressType, null, true);
    if (success) {
      this.saveFieldExtensionData();
    }
    return success;
  },
  syncBillingFromShipping() {
    const synced = sc_fields.syncWhat3wordsBillingFromShipping();
    if (synced) {
      this.saveFieldExtensionData();
    }
  },
  saveFieldExtensionData() {
    try {
      const block = wc_fields_util.getCheckoutForm();
      const form = block?.querySelector('form');

      if (form && typeof wp !== 'undefined' && wp.data?.dispatch) {
        const w3wValues = this.getValues();
        wp.data.dispatch('wc/store/checkout').setExtensionData('swiftcomplete', {
          billing_what3words: w3wValues.billing || null,
          shipping_what3words: w3wValues.shipping || null,
        });
      }
    } catch (e) {
      console.warn('Swiftcomplete: Failed to save field extension data', e);
    }
  }
};

const sc_init = {
  config: null,
  setupCheckoutSubmission(setupState) {
    const unsubscribeForm = sc_fields.onCheckoutFormSubmit(w3w_field.saveFieldExtensionData, setupState);
    if (unsubscribeForm) {
      setupState.unsubscribeFormSubmit = unsubscribeForm;
    }

    if (!setupState.wpDataSubscription) {
      const unsubscribe = wc_checkout.subscribe((currentValue) => {
        if (currentValue) {
          setTimeout(() => {
            sc_init.setupCheckoutSubmission(setupState);
            w3w_field.syncBillingFromShipping();
          }, FIELD_DEFAULTS.SYNC_DELAY);
        }
      });

      if (unsubscribe) {
        setupState.wpDataSubscription = true;
        setupState.unsubscribeUseShippingAsBilling = unsubscribe;
      }

      if (wc_checkout.isBillingSameAsShipping()) {
        w3w_field.syncBillingFromShipping();
      }
    }
  },
  injectedFields() {
    const injections = [];

    const addressForms = sc_fields.getAddressForms();

    if (sc_fields.addressSearchFieldId) {
      injections.push(
        sc_fields.injectFields(
          sc_fields.injectAddressSearchField,
          sc_fields.addressSearchFieldId,
          addressForms
        )
      );
    }

    if (this.config?.w3wEnabled === true && sc_fields.what3wordsFieldId) {
      injections.push(
        sc_fields.injectFields(
          sc_fields.injectWhat3wordsField,
          sc_fields.what3wordsFieldId,
          addressForms
        )
      );
    }

    const injected = injections.some((result) => result);

    if (injected) {
      this.loadCustomerValues();
    }

    return injected;
  },
  loadCustomerValues() {
    if (!this.config?.customerValues) {
      return;
    }

    const { billing, shipping } = this.config.customerValues;

    setTimeout(() => {
      if (billing) {
        w3w_field.setValue('billing', billing);
      }
      if (shipping) {
        w3w_field.setValue('shipping', shipping);
      }
    }, FIELD_DEFAULTS.WHAT3WORDS_UPDATE_DELAY);
  },
  isAddressFormNode(node) {
    if (node.nodeType !== Node.ELEMENT_NODE) {
      return false;
    }
    const formClass = 'wc-block-components-address-form';
    return (
      node.classList?.contains(formClass) || node.querySelector?.(`.${formClass}`) !== null
    );
  },
  init() {
    const setupState = {
      formSubmitListener: false,
      wpDataSubscription: false,
    };

    wc_fields_util.retryUntil(
      () => sc_init.injectedFields(),
      () => sc_init.setupCheckoutSubmission(setupState)
    );

    const observerOptions = {
      childList: true,
      subtree: true,
    };

    const observer = new MutationObserver((mutations) => {
      const shouldReinject = mutations.some((mutation) =>
        Array.from(mutation.addedNodes).some(sc_init.isAddressFormNode)
      );

      if (shouldReinject) {
        setTimeout(() => {
          if (sc_init.injectedFields()) {
            sc_init.setupCheckoutSubmission(setupState);
            sc_init.loadCustomerValues();
          }
        }, FIELD_DEFAULTS.REINJECTION_DELAY);
      }
    });

    const block = wc_fields_util.getCheckoutForm();
    if (block) {
      observer.observe(block, observerOptions);
    } else {
      const blockObserver = new MutationObserver(() => {
        const foundBlock = wc_fields_util.getCheckoutForm(true);
        if (foundBlock) {
          observer.observe(foundBlock, observerOptions);
          blockObserver.disconnect();
          setTimeout(tryInjection, FIELD_DEFAULTS.REINJECTION_DELAY);
        }
      });

      blockObserver.observe(document.body, observerOptions);
    }
  }
}

function initialiseSwiftcompleteFields(config) {
  if (typeof sc_compat !== 'undefined' && !sc_compat.supported) {
    console.warn('Swiftcomplete: Browser not compatible. Skipping initialization.');
    return;
  }

  if (!config) {
    console.warn('Swiftcomplete: Invalid configuration provided');
    return;
  }
  sc_init.config = config;
  if (typeof COMPONENT_DEFAULTS !== 'undefined') {
    sc_fields.what3wordsFieldId = COMPONENT_DEFAULTS.WHAT3WORDS_FIELD_ID || null;
    sc_fields.addressSearchFieldId = COMPONENT_DEFAULTS.ADDRESS_SEARCH_FIELD_ID || null;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sc_init.init);
  } else {
    sc_init.init();
  }
}
