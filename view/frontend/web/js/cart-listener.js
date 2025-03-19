define('armanet_cart_listener', [
  'jquery',
  'Magento_Customer/js/customer-data',
], function ($, customerData) {
  'use strict';

  return function() {
    $(document).on('ajax:addToCart', function (event, data) {
      if (data && data.productIds && data.productIds.length > 0) {
        var cart = customerData.get('cart')();

        if (cart && cart.items && cart.items.length > 0) {
          var addedItem = null;
          for (var i = 0; i < cart.items.length; i++) {
            var item = cart.items[i];
            if (item && typeof item.product_id !== 'undefined' && item.product_id == data.productIds[0]) {
              addedItem = item;
              break;
            }
          }

          if (
            addedItem &&
            typeof addedItem.product_id !== 'undefined' &&
            typeof addedItem.product_price_value !== 'undefined' &&
            typeof addedItem.product_sku !== 'undefined' &&
            typeof addedItem.qty !== 'undefined'
          ) {
            sendEventAddedToCart({
              product_id: addedItem.product_id,
              product_price_value: addedItem.product_price_value,
              product_sku: addedItem.product_sku,
              qty: addedItem.qty,
            });
          }
        }
      }
    });

    function sendEventAddedToCart(item) {
      if (typeof Armanet !== 'undefined' && Armanet && typeof Armanet.sendEvent === 'function') {
        var transformedPayload = {
          itemId: item.product_id,
          upc: item.product_sku,
          price: item.product_price_value,
          quantity: item.qty,
        };

        Armanet.sendEvent('addedToCart', transformedPayload);
      }
    }
  };
});
