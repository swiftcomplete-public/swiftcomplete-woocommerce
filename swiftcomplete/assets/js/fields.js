'use strict';

const FIELD_SELECTORS = {
  CHECKOUT_FORM_SELECTOR: () => 'form[name="checkout"].woocommerce-checkout',
  ADDRESS_FORM_SELECTOR: '.woocommerce-billing-fields, .woocommerce-shipping-fields',
};

const sc_fields = {
  what3wordsFieldId: null,
  addressSearchFieldId: null,
  getField(addressType, fieldId) {
    const form = wc_fields_util.getCheckoutForm();
    if (!form) {
      return null;
    }
    const addressForm = form.querySelector(`.woocommerce-${addressType}-fields`);
    if (!addressForm) {
      return null;
    }

    const fieldIdUnderscore = fieldId.replace(/-/g, '_');
    const fieldIdHyphen = fieldId.replace(/_/g, '-');

    let field = addressForm.querySelector(`input[id$='_${fieldIdUnderscore}'], select[id$='_${fieldIdUnderscore}']`);
    if (!field) {
      field = addressForm.querySelector(`input[id$='_${fieldIdHyphen}'], select[id$='_${fieldIdHyphen}']`);
    }
    if (!field) {
      return null;
    }
    if (field.classList.contains(`wc-block-components-address-form__${fieldId}-hidden-input`)) {
      return null;
    }
    return field;
  },
  findField(addressFields, addressType, fieldId) {
    const fieldIdUnderscore = fieldId.replace(/-/g, '_');
    const fieldIdHyphen = fieldId.replace(/_/g, '-');

    return addressFields.find((f) => {
      const fieldIdToMatch = f.field.id;
      return fieldIdToMatch === `${addressType}_${fieldIdUnderscore}` ||
        fieldIdToMatch === `${addressType}_${fieldIdHyphen}` ||
        fieldIdToMatch === `${addressType}-${fieldIdUnderscore}` ||
        fieldIdToMatch === `${addressType}-${fieldIdHyphen}`;
    });
  },
  getFieldWithContainer(addressType, fieldId) {
    const field = this.getField(addressType, fieldId);
    if (!field) {
      return null;
    }

    const selector = `.woocommerce-${addressType}-fields__field-wrapper .form-row#${addressType}_${fieldId}_field`;
    const container = field.closest(selector) || field.closest('.form-row') || field.parentNode;
    if (!container) {
      return null;
    }
    return { container, field };
  },
  getAddressForms() {
    const form = wc_fields_util.getCheckoutForm();
    if (!form) {
      return null;
    }
    const selector = FIELD_SELECTORS.ADDRESS_FORM_SELECTOR;
    const addressForms = form.querySelectorAll(selector);
    if (!addressForms || addressForms.length === 0) {
      return null;
    }
    Array.from(addressForms).forEach((addressForm) => {
      const type = addressForm.classList.contains('woocommerce-billing-fields') ? 'billing' : 'shipping';
      addressForm.id = type;
    });
    return addressForms;
  },
  getWhat3wordsField(addressType) {
    if (!this.what3wordsFieldId) {
      return null;
    }
    return this.getField(addressType, this.what3wordsFieldId);
  },
};
