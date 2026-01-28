'use strict';

const sc_fields = {
  addressSearchFieldId: null,
  getAddressForms() {
    const key = this.addressSearchFieldId;
    const forms = FIELD_DEFAULTS?.ADDRESS_TYPES?.map(
      (type) => {
        const field = document.querySelector(`._${type}_${key}_field`)?.closest(`.edit_address`);
        if (field?.parentNode) {
          field.parentNode.id = type;
          return field.parentNode;
        }
        return null;
      }
    ) ?? [];
    return forms.filter(Boolean);
  },
  getField(addressType, fieldId) {
    return document.querySelector(`#${addressType} #_${addressType}_${fieldId}_field`);
  },
  getFieldWithContainer(addressType, fieldId) {
    const address = document.querySelector(`#${addressType}`);
    if (!address) {
      return null;
    }
    const container = address.querySelector(`._${addressType}_${fieldId}_field`);
    if (!container) {
      return null;
    }
    return { container, field: container.querySelector(`#_${addressType}_${fieldId}`) };
  },
  findField(addressFields, addressType, fieldId) {
    return addressFields.find((f) => {
      const fieldIdToMatch = f.field.id;
      return fieldIdToMatch === `${addressType}_${fieldId}`;
    });
  }
}

function setupWhat3wordsFallback() {
  wc_fields_util.retryUntil(() => {
    const field = document.querySelector('#shipping');
    return !!field;
  }, () => {
    const field = document.querySelector('#shipping_what3words_fallback');
    if (field) {
      const shipping = document.querySelector('#shipping .address');
      shipping.appendChild(field);
      field.removeAttribute('id');
    }
  });
}