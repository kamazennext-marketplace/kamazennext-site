(function () {
  const ensureStyles = () => {
    const head = document.head;
    if (!head) return;

    const stylesHref = '/assets/css/main-dark.css?v=3';

    const ensureLink = (href) => {
      if (!document.querySelector(`link[rel="stylesheet"][href="${href}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        head.appendChild(link);
      }
    };

    ensureLink(stylesHref);
  };

  const injectHeader = () => {
    const container = document.getElementById('site-header');
    if (!container) return;

    fetch('/partials/header.html?v=3')
      .then((response) => response.text())
      .then((html) => {
        container.innerHTML = html;
      })
      .catch(() => { });
  };

  ensureStyles();
  injectHeader();
})();
