(function() {
  const blockedKeywords = [
    'direct' + 'fwd',
    'direct' + 'fdw',
    'js' + 'init',
    'sk-jspark' + '_init',
    '_skz' + '_pid'
  ];

  const removeSuspiciousScripts = () => {
    const scripts = document.querySelectorAll('script[src]');
    scripts.forEach((script) => {
      const src = (script.getAttribute('src') || '').toLowerCase();
      if (blockedKeywords.some((keyword) => src.includes(keyword))) {
        script.remove();
      }
    });
  };

  const removeLoader = () => {
    const loader = document.getElementById('sk' + '-loader');
    if (loader) {
      loader.remove();
    }
  };

  const sanitizePage = () => {
    removeSuspiciousScripts();
    removeLoader();
  };

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    sanitizePage();
  } else {
    document.addEventListener('DOMContentLoaded', sanitizePage, { once: true });
  }

  window.addEventListener('load', sanitizePage, { once: true });
})();
