/**
 * PURPOSE:
 * - Handle the mobile navigation toggle in the shared header.
 *
 * DEPENDS ON:
 * - #hamburger
 * - #mobileMenu
 *
 * NOTES:
 * - Keep IDs unique. Rendering twice = duplicate sections.
 * - Log failures to console for debugging (never silent).
 */
(() => {
  // Mobile nav toggle: tiny on purpose (performance + fewer bugs)
  const initNav = () => {
    const hamburger = document.getElementById("hamburger");
    const mobileMenu = document.getElementById("mobileMenu");
    if (!hamburger || !mobileMenu) return false;

    if (mobileMenu.dataset.navBound === "1") return true;
    mobileMenu.dataset.navBound = "1";

    const closeMenu = () => {
      mobileMenu.classList.remove("open");
      mobileMenu.setAttribute("aria-hidden", "true");
    };

    const toggleMenu = () => {
      mobileMenu.classList.toggle("open");
      const isOpen = mobileMenu.classList.contains("open");
      mobileMenu.setAttribute("aria-hidden", isOpen ? "false" : "true");
    };

    hamburger.addEventListener("click", toggleMenu);
    mobileMenu.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", closeMenu);
    });

    return true;
  };

  const ensureNav = () => {
    if (initNav()) return;
    const observer = new MutationObserver(() => {
      if (initNav()) {
        observer.disconnect();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", ensureNav);
  } else {
    ensureNav();
  }
})();
