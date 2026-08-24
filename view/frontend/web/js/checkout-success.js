define([], function() {
  'use strict';

  return function(data, debug) {
    if (debug) {
      console.log('[Armanet] sending event: purchased', data);
    }

    if (typeof Armanet !== 'undefined' && Armanet && typeof Armanet.sendEvent === 'function') {
      Armanet.sendEvent('purchased', data);
    }
  };
});
