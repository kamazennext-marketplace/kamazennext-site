/**
 * home-products.js
 * Homepage “Featured tools” renderer.
 *
 * WHY:
 * - Avoid hardcoding featured items in HTML.
 * - Reuse the same product data source as /software.html.
 *
 * RULES:
 * - Only one container with id="homeProducts" should exist on the page.
 * - Render only a small subset (6) to keep homepage fast.
 */
(() => {
  const grid = document.getElementById("homeProducts");
  if (!grid) return;

  const esc = (s = "") =>
    String(s).replace(/[&<>"']/g, (c) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    })[c]);

  const render = (items) => {
    grid.innerHTML = items
      .slice(0, 6)
      .map((p) => {
        const name = esc(p.name || "Tool");
        const cat = esc(p.category || "General");
        const pricing = esc(p.pricing || "—");
        const api =
          p.api === true || p.api === "Yes" || p.api === "yes"
            ? "API: Yes"
            : "API: No";
        const href = p.slug
          ? `/p/${esc(p.slug)}`
          : p.id
            ? `/product.html?id=${esc(p.id)}`
            : "/software.html";

        return `
        <article class="product-card card">
          <div class="pc-top">
            <span class="badge">${cat}</span>
            <span class="badge badge-soft">${pricing}</span>
            <span class="badge badge-soft">${api}</span>
          </div>
          <h3>${name}</h3>
          <p class="muted">${esc(p.tagline || p.description || "")}</p>
          <div class="pc-actions">
            <a class="btn" href="${href}">View</a>
            <a class="btn btn-ghost" href="/software.html?cat=${encodeURIComponent(
              p.category || "",
            )}">More like this</a>
          </div>
        </article>
      `;
      })
      .join("");
  };

  if (Array.isArray(window.PRODUCTS)) {
    render(window.PRODUCTS);
    return;
  }
  if (Array.isArray(window.products)) {
    render(window.products);
    return;
  }

  // Fallback order: try common JSON locations used by the site.
  // Keep this list short; homepage must remain fast.
  const urls = ["/data/products.json", "/assets/data/products.json", "/products.json"];
  (async () => {
    for (const u of urls) {
      try {
        const r = await fetch(u, { cache: "no-store" });
        if (!r.ok) continue;
        const data = await r.json();
        const items = Array.isArray(data)
          ? data
          : Array.isArray(data.products)
            ? data.products
            : [];
        if (items.length) {
          render(items);
          return;
        }
      } catch (e) {
        // Fail silently and try next URL so the homepage doesn't break if one path is missing.
      }
    }
    grid.innerHTML =
      '<div class="card" style="padding:16px">No products loaded. Open <a href="/software.html">Products</a>.</div>';
  })();
})();
