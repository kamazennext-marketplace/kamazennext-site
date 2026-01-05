fetch('/partials/header.html')
  .then((response) => response.text())
  .then((html) => {
    const container = document.getElementById('site-header');
    if (container) {
      container.innerHTML = html;
    }
  });
