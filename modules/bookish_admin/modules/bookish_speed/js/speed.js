(function (Drupal, once) {

  var lastPath = null;
  var prefetchTimer = setTimeout(function (){}, 0);
  var lastTimerUrl = null;

  // Shim for $.extend(true, ...)
  var deepExtend = function (out) {
    out = out || {};

    for (var i = 1; i < arguments.length; i++) {
      var obj = arguments[i];

      if (!obj) { continue;
      }

      for (var key in obj) {
        if (obj.hasOwnProperty(key)) {
          if (typeof obj[key] === "object" && obj[key] !== null) {
            if (obj[key] instanceof Array) { out[key] = obj[key].slice(0);
            } else { out[key] = deepExtend(out[key], obj[key]);
            }
          } else { out[key] = obj[key];
          }
        }
      }
    }

    return out;
  };

  function requestUrl(url, search, hash, scrollTop, context) {
    clearTimeout(prefetchTimer);
    // Do some early precautions to ensure URL is local.
    url = url.replace(/^\/?/, '/').replace(/\/\//g, '/');
    // Fetch the new URL, do not allow requests/redirects to non local origins.
    fetch(url, { redirect: 'follow', mode: 'same-origin' }).then(function (response) {
      // Validate content type to prevent processing links to non-html paths.
      if (!response.headers.get('Content-Type').match(/^text\/html/)) {
        throw 'Invalid content type';
      }
      return response.text();
    }).then(function (html) {
      var domparser = new DOMParser();
      var newDocument = domparser.parseFromString(html, 'text/html');
      // Make sure <main> exists in response.
      var newMain = newDocument.querySelector('main');
      if (!newMain) {
        throw 'Cannot parse response for ' + url;
      }

      // Log the URL to prevent making requests when hash/query params change.
      lastPath = url;

      // Replace the title.
      document.title = newDocument.title;

      // Get drupalSettings.
      var newSettings = newDocument.querySelector('[data-drupal-selector="drupal-settings-json"]');
      var oldSettings = window.drupalSettings;
      if (newSettings) {
        window.drupalSettings = deepExtend({}, window.drupalSettings, JSON.parse(newSettings.textContent));
      }

      // Determine what CSS/JS files are new.
      var newCss = window.drupalSettings.bookishSpeed.css.filter(function (x) { return oldSettings.bookishSpeed.css.indexOf(x) === -1; });
      var newJs = window.drupalSettings.bookishSpeed.js.filter(function (x) { return oldSettings.bookishSpeed.js.indexOf(x) === -1; });

      // Concat the old+new CSS/JS to avoid re-loading files later.
      window.drupalSettings.bookishSpeed.css = oldSettings.bookishSpeed.css.concat(newCss);
      window.drupalSettings.bookishSpeed.js = oldSettings.bookishSpeed.js.concat(newJs);

      var loadedCssAssets = 0;
      var loadedJsAssets = 0;

      var replaced = false;
      var replaceHtml = function () {
        replaced = true;
        var main = document.querySelector('main');
        main.innerHTML = newMain.innerHTML;
        // Special scroll handling for hash links.
        if (hash && context === 'click') {
          var hashElem = document.getElementById(hash.slice(1));
          if (hashElem) {
            setTimeout(function () {
              hashElem.scrollIntoView();
              var oldState = history.state;
              oldState.scrollTop = document.documentElement.scrollTop;
              history.replaceState(oldState, '');
            }, 0);
          }
        }
        else {
          window.scrollTo({ top: scrollTop });
        }
        // Accessibility tweaks.
        var skipLink = document.querySelector('#skip-link');
        if (skipLink) {
          skipLink.classList.remove('focusable');
          skipLink.focus();
        };
        Drupal.announce(Drupal.t('Navigated to "@title"', { '@title': document.title }));
        var event = new CustomEvent('bookish-speed-html', { });
        document.dispatchEvent(event);
      };

      var triggerBehaviors = function () {
        var main = document.querySelector('main');
        Drupal.attachBehaviors(main, window.drupalSettings);
        var event = new CustomEvent('bookish-speed-javascript', { });
        document.dispatchEvent(event);
      };

      // If there are no CSS assets, we can replace now.
      var timeout;
      if (newCss.length === 0) {
        replaceHtml();
      }
      else {
        var timeout = setTimeout(replaceHtml, window.drupalSettings.bookishSpeedSettings ? window.drupalSettings.bookishSpeedSettings.wait_time : 300);
      }

      var cssLoaded = function () {
        loadedCssAssets++;
        if (!replaced && loadedCssAssets >= newCss.length) {
          clearTimeout(timeout);
          replaceHtml();
        }
      };

      // Wait to trigger behaviors until JS is loaded.
      var jsLoaded = function () {
        loadedJsAssets++;
        if (loadedJsAssets >= newJs.length) {
          // Avoid race conditions in JS/CSS loading.
          if (replaced) {
            triggerBehaviors();
          }
          else {
            var interval = setInterval(function () {
              if (replaced) {
                triggerBehaviors();
                clearInterval(interval);
              }
            }, 5);
          }
        }
      };

      if (newJs.length === 0) {
        jsLoaded();
      }

      // Append CSS/JS to head.
      newCss.forEach(function (newUrl) {
        var link = document.createElement('link');
        link.rel = "stylesheet";
        link.type = "text/css";
        link.href = newUrl + (newUrl.indexOf('?') === -1 ? '?' : '&') + window.drupalSettings.bookishSpeed.query_string;
        link.addEventListener('load', cssLoaded);
        link.addEventListener('error', cssLoaded);
        document.head.appendChild(link);
      });
      newJs.forEach(function (newUrl) {
        var script = document.createElement('script');
        script.async = false;
        script.src = newUrl + (newUrl.indexOf('?') === -1 ? '?' : '&') + window.drupalSettings.bookishSpeed.query_string;
        script.addEventListener('load', jsLoaded);
        script.addEventListener('error', jsLoaded);
        document.head.appendChild(script);
      });
    }).catch(function (error) {
      // Fall back to normal navigation.
      console.error('Cannot request ' + url, error);
      window.location = url + search + hash;
    });
  };

  function prefetchUrl(url, search) {
    // Do some early precautions to ensure URL is local.
    url = url.replace(/^\/?/, '/').replace(/\/\//g, '/');
    var link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = url + search;
    document.head.appendChild(link);
  }

  Drupal.behaviors.bookishSpeed = {
    attach: function attach(context, settings) {
      // Default to excluding admin-y paths or links with extensions.
      var exclude_regex = settings.bookishSpeedSettings ? settings.bookishSpeedSettings.exclude_regex : '/(admin|node|user)|\.[a-zA-Z0-9]+$';
      exclude_regex = new RegExp(exclude_regex);
      once('bookish-speed', 'a:not([target]):not(.use-ajax):not(.no-speed)', context).forEach(function (element) {
        // Check if URL is local, a relative anchor, or fails regex check.
        if (element.getAttribute('href')[0] === '#' || element.href.match(exclude_regex) || !Drupal.url.isLocal(element.href)) {
          return;
        }
        var url = new URL(element.href);
        var pathname = url.pathname.replace(/^\/?/, '/').replace(/\/\//g, '/');
        element.addEventListener('click', function (event) {
          // Do nothing if clicking a hash URL.
          if (document.location.pathname === pathname && url.hash) {
            return;
          }
          event.preventDefault();
          history.replaceState({
            scrollTop: document.documentElement.scrollTop,
            fromBookishSpeed: true,
          }, '');
          history.pushState({
            scrollTop: 0,
            fromBookishSpeed: true,
          }, '', pathname + url.search + url.hash);
          requestUrl(pathname, url.search, url.hash, 0, 'click');
        });
        element.addEventListener('mouseover', function () {
          if (lastTimerUrl === pathname + url.search || document.querySelector('link[rel="prefetch"][href="' + pathname + url.search + '"]')) {
            return;
          }
          lastTimerUrl = pathname + url.search;
          clearTimeout(prefetchTimer);
          prefetchTimer = setTimeout(function () {
            prefetchUrl(pathname, url.search);
          }, 65);
        }, { passive: true, capture: true });
        element.addEventListener('mouseout', function () {
          clearTimeout(prefetchTimer);
        }, { passive: true, capture: true });
      });
      once('bookish-speed-history', 'body', context).forEach(function () {
        if (history.scrollRestoration) {
          history.scrollRestoration = 'manual';
        }
        window.addEventListener('popstate', function (event) {
          if (event.state && event.state.fromBookishSpeed && document.location.pathname !== lastPath) {
            var scrollTop = event.state && event.state.scrollTop ? event.state.scrollTop : 0;
            requestUrl(document.location.pathname, document.location.search, document.location.hash, scrollTop, 'popstate');
          }
        });
      });
      once('bookish-speed-skip-link', '#skip-link', context).forEach(function (element) {
        element.addEventListener('blur', function (event) {
          if (event.target !== document.activeElement) {
            element.classList.add('focusable');
          }
        });
      });
    }
  };

})(Drupal, once);
