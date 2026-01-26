'use strict';

/**
 * Swiftcomplete WooCommerce Blocks Checkout Fields
 */

/**
 * Constants for configuration values
 */
const FIELD_DEFAULTS = {
  MAX_INJECTION_ATTEMPTS: 20,
  INJECTION_RETRY_DELAY: 200,
  REINJECTION_DELAY: 100,
  SYNC_DELAY: 100,
  CHECKOUT_BLOCK_SELECTOR: '.wc-block-checkout',
  ADDRESS_FORM_SELECTOR: '.wc-block-components-address-form',
  DEFAULT_SEARCH_FIELD_LABEL: 'Type your address or postcode...',
  ADDRESS_TYPES: ['billing', 'shipping'],
};

/**
 * Utility object for interacting with WordPress API
 */
const wc_checkout = {
  /**
   * Check if "Use same address for billing" checkbox is checked
   * @returns {boolean} True if checkbox is checked
   */
  isBillingSameAsShipping() {
    if (typeof wp === 'undefined' || !wp.data?.select) {
      return false;
    }

    try {
      const checkoutStore = wp.data.select('wc/store/checkout');
      if (!checkoutStore) {
        return false;
      }

      // Try multiple method names for compatibility
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

  /**
   * Subscribe to changes in the "Use same address for billing" state
   * @param {Function} callback - Function to call when state changes. Receives (currentValue, previousValue) as arguments
   * @returns {Function|null} Unsubscribe function or null if subscription failed
   */
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
        // Silently handle expected errors during initial page load when wp.data is not fully initialized
      }
    });

    return unsubscribe;
  },
};

/**
 * Utility object for creating and managing checkout form fields
 */
const wc_checkout_field = {
  // Cache DOM queries (can be invalidated if DOM changes)
  checkoutBlockCache: null,
  // Field IDs - initialized once, accessed directly
  what3wordsFieldId: null,
  addressSearchFieldId: null,

  /**
   * Get or cache the checkout block element
   * @param {boolean} forceRefresh - Force refresh of the cache
   * @returns {HTMLElement|null} The checkout block element
   */
  getCheckoutBlock(forceRefresh = false) {
    if (forceRefresh || !this.checkoutBlockCache) {
      this.checkoutBlockCache = document.querySelector(FIELD_DEFAULTS.CHECKOUT_BLOCK_SELECTOR);
    }
    return this.checkoutBlockCache;
  },

  /**
   * Create the label HTML element
   * @param {string} fieldId - The field ID to associate with
   * @param {string} fieldLabel - The label text
   * @returns {HTMLElement} The label element
   */
  createLabel(fieldId, fieldLabel) {
    const label = document.createElement('label');
    label.setAttribute('for', fieldId);
    label.className = 'wc-block-components-text-input__label';
    label.textContent = fieldLabel;
    return label;
  },

  /**
   * Create the input HTML element
   * @param {string} fieldId - The field ID
   * @param {Object} options - Input options (type, dataName, readonly)
   * @returns {HTMLElement} The input element
   */
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

  /**
   * Setup the event listeners for the input element
   * @param {HTMLElement} container - The container element
   * @param {HTMLElement} input - The input element
   */
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

    // Set initial state
    syncActiveState();
  },
  /**
   * Create the address search field HTML element
   * @param {string} addressType - 'billing' or 'shipping'
   * @param {string} fieldId - The field ID suffix
   * @param {string|null} dataFieldName - Optional data field name suffix
   * @param {Object} options - Additional options (label, readonly, value)
   * @returns {HTMLElement} The field container element
   */
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

  /**
   * Check if field already exists in the form
   * @param {HTMLElement} addressForm - The address form container
   * @param {string} fieldId - The field ID suffix to check
   * @returns {boolean} True if field exists
   */
  fieldExists(addressForm, fieldId) {
    if (!addressForm?.id) {
      return false;
    }
    const selector = `#${addressForm.id}-${fieldId}`;
    return addressForm.querySelector(selector) !== null;
  },

  /**
   * Get a field element by address type
   * @param {string} addressType - 'billing' or 'shipping'
   * @param {string} fieldId - The field ID suffix
   * @returns {HTMLElement|null} The field element
   */
  getField(addressType, fieldId) {
    const block = this.getCheckoutBlock();
    if (!block) {
      return null;
    }
    const addressForm = block.querySelector(`#${addressType}`);
    if (!addressForm) {
      return null;
    }
    // Query for both input and select elements
    const field = addressForm.querySelector(`input[id$='${fieldId}'], select[id$='${fieldId}']`);
    if (!field) {
      return null;
    }
    if (field.classList.contains(`wc-block-components-address-form__${fieldId}-hidden-input`)) {
      return null;
    }
    return field;
  },
  /**
     * Find a field in address fields array by field ID
     * @param {Array} addressFields - Array of field objects
     * @param {string} addressType - 'billing' or 'shipping'
     * @param {string} fieldId - The field ID suffix
     * @returns {Object|undefined} Field object or undefined if not found
     */
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

    const selector = `${COMPONENT_DEFAULTS.ADDRESS_FORM_SELECTOR}${fieldId}`;
    // Check for existing autocomplete container first
    const autocompleteContainer = field.closest('.wc-block-components-address-autocomplete-container');
    if (autocompleteContainer?.parentNode) {
      return { container: autocompleteContainer, field };
    }
    const container = field.closest(selector) || field.parentNode;
    if (!container) {
      return null;
    }
    return { container, field };
  },

  /**
   * Get all address forms from the checkout block
   * @returns {NodeList|null} List of address form elements or null if checkout block not found
   */
  getAddressForms() {
    const checkoutBlock = this.getCheckoutBlock();
    if (!checkoutBlock) {
      return null;
    }
    const formClass = FIELD_DEFAULTS.ADDRESS_FORM_SELECTOR.substring(1);
    const selector = FIELD_DEFAULTS.ADDRESS_TYPES.map((type) => `#${type}.${formClass}`).join(', ');
    return checkoutBlock.querySelectorAll(selector);
  },

  /**
   * Find the container element for a field
   * @param {HTMLElement} addressForm - The address form container
   * @param {string} fieldId - The field ID suffix
   * @returns {HTMLElement|null} The container element or null if not found
   */
  findFieldContainer(addressForm, fieldId) {
    if (!addressForm || !fieldId) {
      return null;
    }

    // Check for existing autocomplete container first
    const autocompleteContainer = addressForm.querySelector('.wc-block-components-address-autocomplete-container');
    if (autocompleteContainer?.parentNode) {
      return autocompleteContainer;
    }

    const field = addressForm.querySelector(`input[id$='${fieldId}']`);
    if (!field) {
      return null;
    }

    // Find the container using multiple selector strategies
    const containerSelectors = [
      `.wc-block-components-address-form__${fieldId}`,
      `.wc-block-components-text-input.wc-block-components-address-form__${fieldId}`,
      '.wc-block-components-text-input',
    ];

    return containerSelectors.map((selector) => field.closest(selector)).find(Boolean) || field.parentElement;
  },

  /**
   * Inject fields into address forms using a callback function
   * @param {Function} callback - Function to inject fields (receives addressForm, fieldId)
   * @param {string} fieldId - Field ID to check for existence and pass to callback
   * @returns {boolean} True if any field was injected
   */
  injectFields(callback, fieldId) {
    if (typeof callback !== 'function' || !fieldId) {
      return false;
    }

    const addressForms = this.getAddressForms();
    if (!addressForms || addressForms.length === 0) {
      return false;
    }

    let injected = false;
    Array.from(addressForms).forEach((addressForm) => {
      if (
        !FIELD_DEFAULTS.ADDRESS_TYPES.includes(addressForm.id) ||
        this.fieldExists(addressForm, fieldId)
      ) {
        return;
      }

      if (callback.call(this, addressForm, fieldId)) {
        injected = true;
      }
    });

    return injected;
  },
  /**
   * Retry a condition check until it succeeds or max attempts are reached
   * @param {Function} conditionFn - Function that returns true when condition is met
   * @param {Function} successCallback - Function to call when condition is met
   * @param {Function|null} failureCallback - Optional function to call when max attempts reached. Receives (attempts) as argument
   * @param {Object} options - Optional configuration (maxAttempts, delay, errorMessage)
   * @returns {Function} Cancel function to stop retrying
   */
  retryUntil(conditionFn, successCallback, failureCallback = null, options = {}) {
    const maxAttempts = options.maxAttempts || FIELD_DEFAULTS.MAX_INJECTION_ATTEMPTS;
    const delay = options.delay || FIELD_DEFAULTS.INJECTION_RETRY_DELAY;
    const errorMessage = options.errorMessage || null;

    let attempts = 0;
    let cancelled = false;
    let timeoutId = null;

    function tryAttempt() {
      if (cancelled) {
        return;
      }

      attempts++;

      if (conditionFn()) {
        if (typeof successCallback === 'function') {
          successCallback();
        }
        return;
      }

      if (attempts < maxAttempts) {
        timeoutId = setTimeout(tryAttempt, delay);
      } else if (typeof failureCallback === 'function') {
        failureCallback(attempts);
      } else if (errorMessage) {
        console.warn(errorMessage);
      }
    }

    // Start the retry loop
    tryAttempt();

    // Return cancel function
    return function cancel() {
      cancelled = true;
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
  },

  /**
   * Set up checkout form submit listener
   * @param {Function} callback - Function to call on form submit
   * @param {Object} setupState - Object to track setup state
   * @returns {Function|null} Unsubscribe function or null
   */
  onCheckoutFormSubmit(callback, setupState) {
    const block = this.getCheckoutBlock();
    if (!block || typeof callback !== 'function') {
      return null;
    }

    const form = block.querySelector('form');
    if (!form) {
      return null;
    }

    // Only set up listener once
    if (!setupState.formSubmitListener) {
      form.addEventListener('submit', callback);
      setupState.formSubmitListener = true;
    }

    // Return unsubscribe function
    return function () {
      if (form && setupState.formSubmitListener) {
        form.removeEventListener('submit', callback);
        setupState.formSubmitListener = false;
      }
    };
  },
  /**
   * Inject the address search field into an address form
   * @param {HTMLElement} addressForm - The address form container
   * @param {string} fieldId - The field ID suffix
   * @returns {boolean} True if field was injected or already exists
   */
  injectAddressSearchField(addressForm, fieldId) {
    if (!fieldId || this.fieldExists(addressForm, fieldId)) {
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

  /**
   * Inject the what3words field into an address form (auto-initialization)
   * @param {HTMLElement} addressForm - The address form container
   * @param {string} fieldId - The field ID suffix
   * @returns {boolean} True if field was injected or already exists
   */
  injectWhat3wordsField(addressForm, fieldId) {
    if (!fieldId || this.fieldExists(addressForm, fieldId)) {
      return !!fieldId;
    }

    const dataFieldName = fieldId.replace(/-/g, '/');
    const what3wordsField = this.createCheckoutField(addressForm.id, fieldId, dataFieldName, {
      label: 'what3words address',
      readonly: true,
      value: '',
    });

    what3wordsField.style.display = 'none';
    addressForm.appendChild(what3wordsField);
    return true;
  },

  /**
   * Get what3words field element by address type
   * @param {string} addressType - 'billing' or 'shipping'
   * @returns {HTMLElement|null} The what3words field element
   */
  getWhat3wordsField(addressType) {
    if (!this.what3wordsFieldId) {
      return null;
    }
    return this.getField(addressType, this.what3wordsFieldId);
  },

  /**
   * Update what3words field value and visibility
   * Field is auto-injected, so we just update its value and show/hide it
   * @param {string} addressType - 'billing' or 'shipping'
   * @param {string|null} value - The what3words value to set (null/empty to hide)
   * @returns {boolean} True if field was updated successfully
   */
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

    return true;
  },

  /**
   * Set what3words value for a specific address type (billing or shipping)
   * Updates field value and visibility, respects isBillingSameAsShipping state
   * @param {string} addressType - 'billing' or 'shipping'
   * @param {string|null} value - The what3words value (null/empty to hide field)
   * @param {boolean} syncToBilling - If true and addressType is 'shipping', sync to billing when checkbox is checked
   * @returns {boolean} True if value was set successfully
   */
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

  /**
   * Sync what3words billing field from shipping field
   * Called when isBillingSameAsShipping checkbox is checked
   * @returns {boolean} True if sync was successful or no action needed
   */
  syncWhat3wordsBillingFromShipping() {
    if (!wc_checkout.isBillingSameAsShipping()) {
      return true;
    }

    const shippingField = this.getWhat3wordsField('shipping');
    const shippingValue = shippingField?.value?.trim() || null;
    return this.setWhat3wordsValue('billing', shippingValue, false);
  },
};

/**
   * What3words field manager
   * Handles value updates and syncing of what3words fields
   * Exposed globally for use by address selection handlers
   */
const w3w_field = {
  /**
   * Get what3words value for an address type
   * @param {string} addressType - 'billing' or 'shipping'
   * @returns {string|null} The what3words value or null if not set
   */
  getValue(addressType) {
    const field = wc_checkout_field.getWhat3wordsField(addressType);
    return field?.value?.trim() || null;
  },

  /**
   * Get all what3words values
   * @returns {Object} Object with billing and shipping values
   */
  getValues() {
    const shippingValue = this.getValue('shipping');
    let billingValue = this.getValue('billing');

    // Sync billing from shipping if checkbox is checked
    if (wc_checkout.isBillingSameAsShipping() && shippingValue) {
      billingValue = shippingValue;
    }

    return {
      billing: billingValue,
      shipping: shippingValue,
    };
  },

  /**
   * Set what3words value for an address type
   * Updates field value and visibility
   * @param {string} addressType - 'billing' or 'shipping'
   * @param {string|null} value - The what3words value (e.g., '///filled.count.soap'), or null to clear
   * @returns {boolean} True if value was set successfully
   */
  setValue(addressType, value) {
    const success = wc_checkout_field.setWhat3wordsValue(addressType, value, true);
    if (success) {
      this.saveFieldExtensionData();
    }
    return success;
  },

  /**
   * Clear what3words value for an address type (hides the field)
   * @param {string} addressType - 'billing' or 'shipping'
   * @returns {boolean} True if value was cleared successfully
   */
  removeValue(addressType) {
    const success = wc_checkout_field.setWhat3wordsValue(addressType, null, true);
    if (success) {
      this.saveFieldExtensionData();
    }
    return success;
  },

  /**
   * Sync billing what3words from shipping
   * Called when isBillingSameAsShipping checkbox changes
   * Handles cases where fields may not exist in DOM
   */
  syncBillingFromShipping() {
    const synced = wc_checkout_field.syncWhat3wordsBillingFromShipping();
    if (synced) {
      this.saveFieldExtensionData();
    }
  },
  /**
   * Save field values to the extension data
   */
  saveFieldExtensionData() {
    try {
      const block = wc_checkout_field.getCheckoutBlock();
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
  /**
   * Hook into checkout submission to capture field values
   * @param {Object} setupState - Object to track setup state
   */
  setupCheckoutSubmission(setupState) {
    // Setup form submit listener
    const unsubscribeForm = wc_checkout_field.onCheckoutFormSubmit(w3w_field.saveFieldExtensionData, setupState);
    if (unsubscribeForm) {
      setupState.unsubscribeFormSubmit = unsubscribeForm;
    }

    // Setup wp.data subscription only once
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

      // Initial sync if billing is already same as shipping
      if (wc_checkout.isBillingSameAsShipping()) {
        w3w_field.syncBillingFromShipping();
      }
    }
  },
  /**
   * Attempt to inject fields into the checkout forms
   * @returns {boolean} True if any field was injected
   */
  injectedFields() {
    const injections = [];

    if (wc_checkout_field.addressSearchFieldId) {
      injections.push(
        wc_checkout_field.injectFields(
          wc_checkout_field.injectAddressSearchField,
          wc_checkout_field.addressSearchFieldId
        )
      );
    }

    if (this.config?.w3wEnabled === true && wc_checkout_field.what3wordsFieldId) {
      injections.push(
        wc_checkout_field.injectFields(
          wc_checkout_field.injectWhat3wordsField,
          wc_checkout_field.what3wordsFieldId
        )
      );
    }

    return injections.some((result) => result);
  },

  /**
   * Check if a node is or contains an address form
   * @param {Node} node - The node to check
   * @returns {boolean} True if node is or contains an address form
   */
  isAddressFormNode(node) {
    if (node.nodeType !== Node.ELEMENT_NODE) {
      return false;
    }
    const formClass = 'wc-block-components-address-form';
    return (
      node.classList?.contains(formClass) || node.querySelector?.(`.${formClass}`) !== null
    );
  },
  /**
   * Initialize the address search field injection
   */
  init() {
    const setupState = {
      formSubmitListener: false,
      wpDataSubscription: false,
    };

    // Try to inject fields with retry logic
    wc_checkout_field.retryUntil(
      () => sc_init.injectedFields(),
      () => sc_init.setupCheckoutSubmission(setupState)
    );

    /**
     * Handle mutations in the checkout block
     */
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
          }
        }, FIELD_DEFAULTS.REINJECTION_DELAY);
      }
    });

    // Setup observer for checkout block
    const block = wc_checkout_field.getCheckoutBlock();
    if (block) {
      observer.observe(block, observerOptions);
    } else {
      // If checkout block doesn't exist yet, watch for it
      const blockObserver = new MutationObserver(() => {
        const foundBlock = wc_checkout_field.getCheckoutBlock(true);
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

/**
 * Initialise Swiftcomplete fields (e.g.: address search field, what3words)
 * - Blocks checkout specific code
 * @param {Object} config - Configuration object with fieldId, and w3wEnabled
 */
function initialiseSwiftcompleteFields(config) {
  // Check browser compatibility before initializing
  if (typeof sc_compat !== 'undefined' && !sc_compat.supported) {
    console.warn('Swiftcomplete: Browser not compatible. Skipping initialization.');
    return;
  }

  if (!config) {
    console.warn('Swiftcomplete: Invalid configuration provided');
    return;
  }
  sc_init.config = config;
  // Initialize field IDs once - store them on the objects that need them
  if (typeof COMPONENT_DEFAULTS !== 'undefined') {
    wc_checkout_field.what3wordsFieldId = COMPONENT_DEFAULTS.WHAT3WORDS_FIELD_ID || null;
    wc_checkout_field.addressSearchFieldId = COMPONENT_DEFAULTS.ADDRESS_SEARCH_FIELD_ID || null;
  }
  console.log('Swiftcomplete: Setting up fields', config);

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sc_init.init);
  } else {
    sc_init.init();
  }
}
