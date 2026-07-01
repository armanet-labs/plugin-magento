define([
  'jquery',
  'Magento_Customer/js/customer-data',
], function ($, customerData) {
  'use strict';

  return function(options) {
    var debug = options && options.debug;
    var pendingCartState = null;

    if (debug) {
      console.log('[Armanet] cart-listener initialized');
    }

    // addedToCart: snapshot cart state before the add, diff after section refreshes
    $(document).on('ajax:addToCart', function (event, data) {
      var cart = customerData.get('cart')();
      pendingCartState = {};
      (cart && cart.items ? cart.items : []).forEach(function (item) {
        pendingCartState[item.item_id] = Number(item.qty);
      });
      if (debug) {
        console.log('[Armanet] ajax:addToCart fired, cart snapshot:', pendingCartState);
      }
    });

    customerData.get('cart').subscribe(function (cart) {
      if (!pendingCartState) return;

      var prevState = pendingCartState;
      pendingCartState = null;

      var items = cart && cart.items ? cart.items : [];
      for (var i = 0; i < items.length; i++) {
        var item = items[i];
        if (!prevState.hasOwnProperty(item.item_id) || Number(item.qty) > prevState[item.item_id]) {
          sendEvent('addedToCart', item);
          return;
        }
      }

      if (debug) {
        console.log('[Armanet] addedToCart: could not find newly added item');
      }
    });

    // removedFromCart (minicart): sidebar POSTs to checkout/sidebar/removeItem via jQuery AJAX
    $(document).on('ajaxSend', function (event, xhr, settings) {
      if (!settings.url || settings.url.indexOf('checkout/sidebar/removeItem') === -1) return;

      if (debug) {
        console.log('[Armanet] minicart removeItem detected:', settings.data);
      }

      var params = {};
      (settings.data || '').split('&').forEach(function (pair) {
        var parts = pair.split('=');
        if (parts.length === 2) {
          params[decodeURIComponent(parts[0])] = decodeURIComponent(parts[1]);
        }
      });

      if (params.item_id) {
        fireRemovalByCartItemId(String(params.item_id));
      }
    });

    // removedFromCart (cart page): href is "#" with data-post JSON — capture phase bypasses
    // any stopPropagation from Magento's data-post widget
    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-post]');
      if (!el) return;

      try {
        var postData = JSON.parse(el.getAttribute('data-post'));
        if (!postData.action || postData.action.indexOf('checkout/cart/delete') === -1) return;

        var itemId = postData.data && String(postData.data.id);
        if (debug) {
          console.log('[Armanet] cart page delete clicked, itemId:', itemId);
        }

        if (itemId) {
          fireRemovalByCartItemId(itemId);
        }
      } catch (err) {
        if (debug) {
          console.log('[Armanet] failed to parse data-post:', err);
        }
      }
    }, true);

    function fireRemovalByCartItemId(itemId) {
      var cart = customerData.get('cart')();
      var items = cart && cart.items ? cart.items : [];

      if (debug) {
        console.log('[Armanet] looking up item_id:', itemId, 'in cart items:', items);
      }

      for (var i = 0; i < items.length; i++) {
        if (String(items[i].item_id) === itemId) {
          sendEvent('removedFromCart', items[i]);
          return;
        }
      }

      if (debug) {
        console.log('[Armanet] item_id not found in cart');
      }
    }

    function sendEvent(eventName, item) {
      var payload = {
        productId: item.product_id,
        sku: item.product_sku,
        upc: item.upc || '',
        price: item.product_price_value,
        quantity: item.qty,
      };

      if (debug) {
        console.log('[Armanet] sending event:', eventName, payload);
      }

      if (typeof Armanet !== 'undefined' && Armanet && typeof Armanet.sendEvent === 'function') {
        Armanet.sendEvent(eventName, payload);
      }
    }
  };
});
