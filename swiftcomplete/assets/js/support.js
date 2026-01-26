'use strict';

/**
 * Swiftcomplete Browser Compatibility Check
 *
 * Browser Compatibility:
 * - Chrome 45+ (2015)
 * - Firefox 25+ (2013)
 * - Safari 9+ (2015)
 * - Edge 12+ (2015)
 * - iOS Safari 9+
 * - Chrome Android 45+
 *
 * NOT supported:
 * - Internet Explorer 11 and below
 * - Older mobile browsers without ES6 support
 *
 * This file performs feature detection at runtime and sets a compatibility flag.
 * It should be loaded before all other Swiftcomplete scripts.
 */

/**
 * Feature detection for required browser APIs
 * Ensures compatibility with modern browsers (Chrome 45+, Firefox 25+, Safari 9+, Edge 12+)
 * Excludes legacy browsers like IE11
 */
const sc_compat = {
  supported: false,
};

(function checkBrowserCompatibility() {
  // Critical features that will cause the plugin to fail if missing
  const criticalFeatures = [
    { name: 'Array.from', test: typeof Array.from === 'function' },
    { name: 'Array.find', test: typeof Array.prototype.find === 'function' },
    { name: 'Array.some', test: typeof Array.prototype.some === 'function' },
    { name: 'WeakSet', test: typeof WeakSet === 'function' },
    { name: 'MutationObserver', test: typeof MutationObserver === 'function' },
    { name: 'querySelector', test: typeof document.querySelector === 'function' },
    { name: 'addEventListener', test: typeof document.addEventListener === 'function' },
  ];

  const missingFeatures = criticalFeatures.filter((feature) => !feature.test);
  sc_compat.supported = missingFeatures.length === 0;
  // Set global compatibility flag for other scripts to check
  if (!sc_compat.supported) {
    const missingFeatureNames = missingFeatures.map((f) => f.name).join(', ');
    console.error(
      'Swiftcomplete: Browser not compatible. Missing critical features:',
      missingFeatureNames,
      '\nSwiftcomplete features will not be available. Please use a modern browser.'
    );
  }
})();
