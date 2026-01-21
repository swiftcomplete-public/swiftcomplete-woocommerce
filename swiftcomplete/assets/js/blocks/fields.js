'use strict';

/**
 * Initialise Swiftcomplete fields (e.g.: address search field, what3words)
 * - Blocks checkout specific code
 * @param {object} config - Configuration object
 */
function initialiseSwiftcompleteFields(config) {
  console.log('Swiftcomplete: Setting up fields');
  /**
   * Create the label HTML element
   * @param {*} fieldId
   * @param {*} fieldLabel
   * @returns {HTMLElement} The label element
   */
  function createLabel(fieldId, fieldLabel) {
    const label = document.createElement('label');
    label.setAttribute('for', fieldId);
    label.className = 'wc-block-components-text-input__label';
    label.textContent = fieldLabel;
    return label;
  }

  /**
   * Create the input HTML element
   * @param {*} fieldId
   * @param {*} dataFieldName
   * @returns {HTMLElement} The input element
   */
  function createInput(fieldId, dataFieldName) {
    const input = document.createElement('input');
    input.type = 'text';
    input.id = fieldId;
    input.className = 'wc-block-components-text-input__input';
    input.setAttribute('data-field-name', dataFieldName);
    input.setAttribute('autocomplete', 'off');
    return input;
  }

  /**
   * Setup the event listeners for the input element
   * @param {HTMLElement} input - The input element
   */
  function setupInputEventListeners(container, input) {
    const syncActiveState = () => {
      const hasValue = input.value.trim().length > 0;
      const hasFocus = document.activeElement === input;
      container.classList.toggle('is-active', hasFocus || hasValue);
    };
    input.addEventListener('focus', syncActiveState);
    input.addEventListener('blur', syncActiveState);
    input.addEventListener('input', syncActiveState);

    syncActiveState();
  }

  /**
   * Create the address search field HTML element
   * @param {string} addressType - 'billing' or 'shipping'
   * @returns {HTMLElement} The field container element
   */
  function createAddressSearchField(addressType) {
    const fieldId = `${addressType}-${config.fieldId}`;
    const dataFieldName = `${addressType}/${config.dataFieldNameSuffix}`;
    const fieldLabel = 'Type your address or postcode...';

    const container = document.createElement('div');
    container.className =
      'wc-block-components-text-input wc-block-components-address-form__swiftcomplete-address-search';

    const label = createLabel(fieldId, fieldLabel);

    const input = createInput(fieldId, dataFieldName);

    setupInputEventListeners(container, input);

    container.appendChild(input);
    container.appendChild(label);

    return container;
  }

  /**
   * Check if field already exists in the form
   * @param {HTMLElement} addressForm - The address form container
   * @param {string} addressType - 'billing' or 'shipping'
   * @returns {boolean} True if field exists
   */
  function fieldExists(addressForm, addressType) {
    const fieldId = `${addressType}-${config.fieldId}`;
    return addressForm.querySelector(`#${fieldId}`) !== null;
  }

  /**
   * Find the address_1 field in the form
   * @param {HTMLElement} addressForm - The address form container
   * @returns {HTMLElement|null} The address_1 field or null
   */
  function findAddress1Field(addressForm) {
    let address1Field = addressForm.querySelector(
      'input[autocomplete="address-line1"]'
    );

    if (!address1Field) {
      address1Field = addressForm.querySelector('input[id*="address_1"]');
    }

    if (!address1Field) {
      address1Field = addressForm.querySelector('input[name*="address_1"]');
    }

    if (!address1Field) {
      address1Field = addressForm.querySelector(
        '[data-field-name*="address_1"]'
      );
    }
    return address1Field;
  }

  /**
   * Find the container of the address_1 field
   * @param {HTMLElement} address1Field - The address_1 field
   * @returns {HTMLElement|null} The container of the address_1 field or null
   */
  function findAddress1Container(address1Field) {
    if (!address1Field) {
      return null;
    }

    let address1Container = address1Field.closest(
      '.wc-block-components-address-form__address_1'
    );

    if (!address1Container) {
      address1Container = address1Field.closest(
        '.wc-block-components-text-input.wc-block-components-address-form__address_1'
      );
    }

    if (!address1Container) {
      address1Container = address1Field.closest(
        '.wc-block-components-text-input'
      );
    }

    if (!address1Container) {
      address1Container = address1Field.closest(
        '.wc-block-components-address-form__field'
      );
    }

    if (!address1Container) {
      address1Container = address1Field.parentElement;
    }
    return address1Container;
  }

  /**
   * Find the target container (address_1 field) to position our field before it
   * @param {HTMLElement} addressForm - The address form container
   * @returns {HTMLElement|null} The target container or null
   */
  function findTargetContainer(addressForm) {
    const autocompleteContainer = addressForm.querySelector(
      '.wc-block-components-address-autocomplete-container'
    );
    if (autocompleteContainer && autocompleteContainer.parentNode) {
      return autocompleteContainer;
    }
    const address1Field = findAddress1Field(addressForm);
    return findAddress1Container(address1Field);
  }

  /**
   * Inject address search fields into checkout forms
   */
  function injectAddressSearchFields() {
    const checkoutBlock = document.querySelector('.wc-block-checkout');
    if (!checkoutBlock) {
      return false;
    }

    const addressForms = checkoutBlock.querySelectorAll(
      '.wc-block-components-address-form'
    );

    if (addressForms.length === 0) {
      return false;
    }

    let injected = false;

    addressForms.forEach((addressForm) => {
      let addressType = 'billing';
      if (
        addressForm.closest('.wc-block-components-shipping-address') ||
        addressForm.querySelector('input[id*="shipping"]') ||
        addressForm.querySelector('input[name*="shipping"]')
      ) {
        addressType = 'shipping';
      }

      if (fieldExists(addressForm, addressType)) {
        return;
      }

      const targetContainer = findTargetContainer(addressForm);
      if (!targetContainer || !targetContainer.parentNode) {
        return;
      }
      const fieldContainer = createAddressSearchField(addressType);
      targetContainer.parentNode.insertBefore(fieldContainer, targetContainer);
      injected = true;
    });

    return injected;
  }

  /**
   * Check if "Use same address for billing" checkbox is checked
   * Uses WooCommerce Blocks Store API instead of DOM selectors for better maintainability
   * @returns {boolean} True if checkbox is checked
   */
  function isBillingSameAsShipping() {
    if (typeof wp === 'undefined' || !wp.data || !wp.data.select) {
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

      const state = checkoutStore.getState();
      if (state && typeof state.useShippingAsBilling !== 'undefined') {
        return state.useShippingAsBilling;
      }

      return false;
    } catch (e) {
      return false;
    }
  }

  /**
   * Get current field values
   * @returns {Object} Object with billing and shipping values
   */
  function getFieldValues() {
    const checkoutBlock = document.querySelector('.wc-block-checkout');
    if (!checkoutBlock) {
      return { billing: null, shipping: null };
    }

    const billingField = checkoutBlock.querySelector(
      `#billing-${config.fieldId}`
    );
    const shippingField = checkoutBlock.querySelector(
      `#shipping-${config.fieldId}`
    );

    const shippingValue = shippingField ? shippingField.value.trim() : null;
    let billingValue = billingField ? billingField.value.trim() : null;

    if (isBillingSameAsShipping() && shippingValue) {
      billingValue = shippingValue;
    }

    return {
      billing: billingValue,
      shipping: shippingValue,
    };
  }

  /**
   * Save field values to the extension data
   */
  function saveFieldExtensionData() {
    const values = getFieldValues();
    const checkoutBlock = document.querySelector('.wc-block-checkout');
    if (checkoutBlock) {
      const form = checkoutBlock.querySelector('form');
      if (form) {
        wp.data
          .dispatch('wc/store/checkout')
          .setExtensionData('swiftcomplete', {
            billing_address_search: values.billing || null,
            shipping_address_search: values.shipping || null,
          });
      }
    }
  }

  /**
   * Hook into checkout submission to capture field values
   * @param {WeakSet} fieldsWithListeners - Set to track fields with listeners
   * @param {Object} setupState - Object to track setup state (formSubmitListener, wpDataSubscription)
   */
  function setupCheckoutSubmission(fieldsWithListeners, setupState) {
    const checkoutBlock = document.querySelector('.wc-block-checkout');
    if (!checkoutBlock) {
      return;
    }

    const form = checkoutBlock.querySelector('form');
    if (form && !setupState.formSubmitListener) {
      form.addEventListener('submit', () => {
        saveFieldExtensionData();
      });
      setupState.formSubmitListener = true;
    }

    const billingField = checkoutBlock.querySelector(
      `#billing-${config.fieldId}`
    );
    const shippingField = checkoutBlock.querySelector(
      `#shipping-${config.fieldId}`
    );

    const syncBillingFromShipping = () => {
      const currentBillingField = checkoutBlock.querySelector(
        `#billing-${config.fieldId}`
      );
      const currentShippingField = checkoutBlock.querySelector(
        `#shipping-${config.fieldId}`
      );
      if (
        isBillingSameAsShipping() &&
        currentShippingField &&
        currentBillingField
      ) {
        currentBillingField.value = currentShippingField.value;
        currentBillingField.dispatchEvent(
          new Event('input', { bubbles: true })
        );
      }
      saveFieldExtensionData();
    };

    // Setup wp.data subscription only once
    if (
      wp &&
      typeof wp.data !== 'undefined' &&
      typeof wp.data.subscribe === 'function' &&
      !setupState.wpDataSubscription
    ) {
      setupState.wpDataSubscription = true;
      let previousUseShippingAsBilling = null;
      wp.data.subscribe(() => {
        try {
          const currentUseShippingAsBilling = isBillingSameAsShipping();

          if (previousUseShippingAsBilling !== currentUseShippingAsBilling) {
            previousUseShippingAsBilling = currentUseShippingAsBilling;
            if (currentUseShippingAsBilling) {
              // When billing form appears, setup listeners for the billing field
              setTimeout(() => {
                setupCheckoutSubmission(fieldsWithListeners, setupState);
                syncBillingFromShipping();
              }, 100);
            }
          }
        } catch (e) {
          // This is expected during initial page load when wp.data is not fully initialized
        }
      });

      if (isBillingSameAsShipping()) {
        syncBillingFromShipping();
      }
    }

    // Setup billing field listeners if field exists and doesn't have listeners yet
    if (billingField && !fieldsWithListeners.has(billingField)) {
      fieldsWithListeners.add(billingField);
      billingField.addEventListener('input', () => {
        saveFieldExtensionData();
      });
      billingField.addEventListener('change', () => {
        saveFieldExtensionData();
      });
    }

    // Setup shipping field listeners if field exists and doesn't have listeners yet
    if (shippingField && !fieldsWithListeners.has(shippingField)) {
      fieldsWithListeners.add(shippingField);
      shippingField.addEventListener('input', () => {
        const currentBillingField = checkoutBlock.querySelector(
          `#billing-${config.fieldId}`
        );
        if (isBillingSameAsShipping() && currentBillingField) {
          currentBillingField.value = shippingField.value;
          currentBillingField.dispatchEvent(
            new Event('input', { bubbles: true })
          );
        }
        saveFieldExtensionData();
      });
      shippingField.addEventListener('change', () => {
        const currentBillingField = checkoutBlock.querySelector(
          `#billing-${config.fieldId}`
        );
        if (isBillingSameAsShipping() && currentBillingField) {
          currentBillingField.value = shippingField.value;
          currentBillingField.dispatchEvent(
            new Event('input', { bubbles: true })
          );
        }
        saveFieldExtensionData();
      });
    }
  }

  /**
   * Initialize the address search field injection
   */
  function init() {
    // Track which fields have event listeners attached to prevent duplicates
    const fieldsWithListeners = new WeakSet();
    // Track setup state to prevent duplicate listeners
    const setupState = {
      formSubmitListener: false,
      wpDataSubscription: false,
    };

    let attempts = 0;
    const maxAttempts = 20;

    function tryInjection() {
      attempts++;
      const success = injectAddressSearchFields();

      if (!success && attempts < maxAttempts) {
        setTimeout(tryInjection, 200);
      } else if (success) {
        setupCheckoutSubmission(fieldsWithListeners, setupState);
      }
    }

    tryInjection();

    const observer = new MutationObserver(function (mutations) {
      let shouldReinject = false;
      mutations.forEach(function (mutation) {
        if (mutation.addedNodes.length > 0) {
          mutation.addedNodes.forEach((node) => {
            if (
              node.nodeType === 1 &&
              (node.classList.contains('wc-block-components-address-form') ||
                node.querySelector('.wc-block-components-address-form'))
            ) {
              shouldReinject = true;
            }
          });
        }
      });
      if (shouldReinject) {
        setTimeout(() => {
          const injected = injectAddressSearchFields();
          if (injected) {
            setupCheckoutSubmission(fieldsWithListeners, setupState);
          }
        }, 100);
      }
    });

    const checkoutBlock = document.querySelector('.wc-block-checkout');
    if (checkoutBlock) {
      observer.observe(checkoutBlock, {
        childList: true,
        subtree: true,
      });
    } else {
      const blockObserver = new MutationObserver(function () {
        const block = document.querySelector('.wc-block-checkout');
        if (block) {
          observer.observe(block, {
            childList: true,
            subtree: true,
          });
          blockObserver.disconnect();
          setTimeout(() => {
            tryInjection();
          }, 100);
        }
      });

      blockObserver.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}
