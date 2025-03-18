define('armanet_cart_listener', [
  'jquery',
  'Magento_Customer/js/customer-data',
], function ($, customerData) {
  'use strict';

  return function() {
    $(document).on('ajax:addToCart', function (event, data) {
      if (data && data.productIds) {
        var cart = customerData.get('cart')();

        if (cart && cart.items && cart.items.length) {
          var addedItem = cart.items.find(item => item.product_id == data.productIds[0]);

          if (addedItem) {
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
