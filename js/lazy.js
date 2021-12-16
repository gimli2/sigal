/*! lazy.js v1.0.0 | (c) 2015 @vishnurs | https://github.com/vishnurs/lazy */
(function(root) {
'use strict';
var delay, timer, eventCount = 0, callback = function() { };
root.lazy = {
  init : function(options) {
    options = options || {};
    delay = options.delay || 0;
    callback = options.callback || callback;
    if(document.addEventListener) {
      root.addEventListener('scroll', lazy.engine, false);
      root.addEventListener('load', lazy.engine, false);
    } else {
      root.attachEvent('onscroll', lazy.engine);
      root.attachEvent('onload', lazy.engine);
    }
  },
  engine : function() {
    timer = setTimeout(function(){
      lazy.loadImage();
    }, delay);
  },
  loadImage : function(){
    var elements = document.querySelectorAll('img[data-lazy]');
    for(var i = 0; i<elements.length; i++) {
      if(lazy.isVisible(elements[i])) {
        if(elements[i].getAttribute("data-lazy") !== null) {
          if(elements[i].getAttribute("data-lazy") !== elements[i].getAttribute("src")) {
            elements[i].src = elements[i].getAttribute("data-lazy");
            eventCount++; 
          }
        }
        callback(elements[i]);
      }
    }
    if(!elements.length || eventCount == elements.length ) {
      lazy.releaseEvents();
    }
  },
  isVisible : function(elem) {
    var viewport = elem.getBoundingClientRect();
    var visible1 = !!( elem.offsetWidth || elem.offsetHeight || elem.getClientRects().length );
    return visible1 && (viewport.top >=0 && viewport.top < window.innerHeight || viewport.bottom >=0 && viewport.bottom < window.innerHeight) && (viewport.left >=0 && viewport.left < window.innerWidth || viewport.right >=0 && viewport.right < window.innerWidth);
  },
  releaseEvents : function() {
    if (document.removeEventListener) {
      root.removeEventListener('scroll', lazy.engine);
    } else {
      root.detachEvent('onscroll', lazy.engine);
    }
    clearTimeout(timer);
  } 
};
})(this)
