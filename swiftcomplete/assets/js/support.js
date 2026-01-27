'use strict';

/**
 * Browser compatibility + shared checkout utilities.
 * Must load before other Swiftcomplete scripts.
 */

const sc_compat = {
  supported: false,
};

(function checkBrowserCompatibility() {
  const criticalFeatures = [
    { name: 'Array.from', test: typeof Array.from === 'function' },
    { name: 'Array.find', test: typeof Array.prototype.find === 'function' },
    { name: 'Array.some', test: typeof Array.prototype.some === 'function' },
    { name: 'WeakSet', test: typeof WeakSet === 'function' },
    { name: 'MutationObserver', test: typeof MutationObserver === 'function' },
    { name: 'querySelector', test: typeof document.querySelector === 'function' },
    { name: 'addEventListener', test: typeof document.addEventListener === 'function' },
  ];

  const missingFeatures = criticalFeatures.filter((feature) => !feature.test);
  sc_compat.supported = missingFeatures.length === 0;
  if (!sc_compat.supported) {
    const missingFeatureNames = missingFeatures.map((f) => f.name).join(', ');
    console.error(
      'Swiftcomplete: Browser not compatible. Missing critical features:',
      missingFeatureNames,
      '\nSwiftcomplete features will not be available. Please use a modern browser.'
    );
  }
})();

const FIELD_DEFAULTS = {
  MAX_INJECTION_ATTEMPTS: 20,
  INJECTION_RETRY_DELAY: 200,
  REINJECTION_DELAY: 100,
  SYNC_DELAY: 100,
  ADDRESS_TYPES: ['billing', 'shipping'],
  DEFAULT_SEARCH_FIELD_LABEL: 'Type your address or postcode...',
};

const wc_fields_util = {
  checkoutFormCache: null,
  /**
   * Retry a condition until it succeeds or max attempts are reached.
   * Returns a cancel function.
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

    tryAttempt();

    return function cancel() {
      cancelled = true;
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
  },
  /**
   * Get the checkout form element (blocks or shortcode).
   */
  getCheckoutForm(forceRefresh = false) {
    if (forceRefresh || !this.checkoutFormCache) {
      const blocksForm = document.querySelector('div[data-block-name="woocommerce/checkout"].wc-block-checkout');
      if (blocksForm) {
        this.checkoutFormCache = blocksForm;
        return this.checkoutFormCache;
      }

      const shortcodeForm = document.querySelector('form[name="checkout"].woocommerce-checkout #customer_details');
      if (shortcodeForm) {
        this.checkoutFormCache = shortcodeForm;
        return this.checkoutFormCache;
      }

      this.checkoutFormCache = null;
    }
    return this.checkoutFormCache;
  },
  /**
   * Check if a field exists in an address form (blocks: hyphen, shortcode: underscore).
   */
  fieldExists(addressForm, fieldId) {
    if (!addressForm?.id) {
      return false;
    }
    const selectorHyphen = `#${addressForm.id}-${fieldId}`;
    const selectorUnderscore = `#${addressForm.id}_${fieldId}`;
    return addressForm.querySelector(selectorHyphen) !== null ||
      addressForm.querySelector(selectorUnderscore) !== null;
  },
};