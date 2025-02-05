define('checkout_success', [
  'jquery',
], function($) {
  'use strict';

  return function(data) {
    sendEventPurchased(data);

    function sendEventPurchased(payload) {
      if (typeof Armanet !== 'undefined' && Armanet && typeof Armanet.sendEvent === 'function') {
        Armanet.sendEvent('purchased', payload);
      }
    }
  };
});
