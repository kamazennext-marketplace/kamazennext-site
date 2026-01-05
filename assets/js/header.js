(function(){
  const ensureStyles = () => {
    const head = document.head;
    if (!head) return;

    const stylesHref = '/assets/css/styles.css?v=20260106';
    const themeHref = '/assets/css/theme-light.css?v=20260106';

    const ensureLink = (href) => {
      if (!document.querySelector(`link[rel="stylesheet"][href="${href}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        head.appendChild(link);
      }
    };

    ensureLink(stylesHref);
    ensureLink(themeHref);
  };

  const injectHeader = () => {
    const container = document.getElementById('site-header');
    if (!container) return;

    fetch('/partials/header.html')
      .then((response) => response.text())
      .then((html) => {
        container.innerHTML = html;
      })
      .catch(() => {});
  };

  ensureStyles();
  injectHeader();
})();
