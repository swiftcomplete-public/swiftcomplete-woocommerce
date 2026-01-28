
const sc_what3words = {
  getAddressField(addressType) {
    let address = document.querySelector(
      `.woocommerce-order .woocommerce-customer-details [class*="${addressType}-address"]`
    )?.querySelector('address');
    if (!address) {
      address = document.querySelector(
        `.wp-block-woocommerce-order-confirmation-${addressType}-address`
      )?.querySelector('address');
    }
    if (!address) {
      return;
    }
    return address;
  },
  getWhat3wordsField(addressType) {
    const field = document.querySelector(`#what3words-${addressType}`);
    if (!field) {
      return;
    }
    return field;
  }
}

function repositionConfirmationFields() {
  const billingAddress = sc_what3words.getAddressField('billing');
  const shippingAddress = sc_what3words.getAddressField('shipping');
  if (!billingAddress || !shippingAddress) {
    return;
  }
  const billingWhat3words = sc_what3words.getWhat3wordsField('billing');
  const shippingWhat3words = sc_what3words.getWhat3wordsField('shipping');
  if (billingWhat3words) {
    billingAddress.appendChild(billingWhat3words);
  }
  if (shippingWhat3words) {
    shippingAddress.appendChild(shippingWhat3words);
  }
}