/**
 * PURPOSE:
 * - Handle the legacy mobile drawer open/close interactions.
 *
 * DEPENDS ON:
 * - #hamburger
 * - #menuClose
 * - #mobileMenu
 *
 * NOTES:
 * - Keep IDs unique. Rendering twice = duplicate sections.
 * - Log failures to console for debugging (never silent).
 */
(function(){
  let initialized = false;

  const initNav = () => {
    if (initialized) return true;

    const menuBtn = document.getElementById('hamburger');
    const menuClose = document.getElementById('menuClose');
    const drawer = document.getElementById('mobileMenu');
    if (!drawer || !menuBtn || !menuClose) return false;

    const openDrawer = () => {
      drawer.classList.add('open');
      drawer.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    };

    const closeDrawer = () => {
      drawer.classList.remove('open');
      drawer.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    };

    menuBtn.addEventListener('click', openDrawer);
    menuClose.addEventListener('click', closeDrawer);

    drawer.addEventListener('click', (event) => {
      if (event.target === drawer) {
        closeDrawer();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drawer.getAttribute('aria-hidden') === 'false') {
        closeDrawer();
      }
    });

    initialized = true;
    return true;
  };

  const tryInit = () => {
    if (initNav()) return;

    const observer = new MutationObserver(() => {
      if (initNav()) {
        observer.disconnect();
      }
    });

    observer.observe(document.body, { childList: true, subtree: true });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tryInit);
  } else {
    tryInit();
  }
})();
