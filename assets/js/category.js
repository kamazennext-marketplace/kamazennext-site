import { injectBaseSchema, injectBreadcrumbs, setMeta } from '/assets/js/seo-schema.js';

(() => {
  const productsGrid = document.getElementById('productsGrid');
  if (!productsGrid) return;

  const emptyState = document.getElementById('emptyState');
  const countEl = document.getElementById('count');
  const titleEl = document.getElementById('categoryTitle');
  const descriptionEl = document.getElementById('categoryDescription');
  const crumbEl = document.getElementById('categoryCrumb');

  const slugify = (str) =>
    (str || '')
      .toString()
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)+/g, '');

  const titleize = (slug) => {
    if (!slug) return '';
    const keepUpper = new Set(['ai', 'saas', 'crm', 'erp', 'seo', 'hr', 'ui', 'ux', 'api', 'llm']);
    return slug
      .split('-')
      .map((word) => (keepUpper.has(word) ? word.toUpperCase() : word.charAt(0).toUpperCase() + word.slice(1)))
      .join(' ');
  };

  const getSlug = () => {
    const params = new URLSearchParams(window.location.search);
    const paramSlug = params.get('slug');
    if (paramSlug) return slugify(paramSlug);

    const parts = window.location.pathname.split('/').filter(Boolean);
    if (parts[0] === 'category' && parts[1]) return slugify(parts[1]);
    return '';
  };

  const compareIds = () => {
    try {
      return JSON.parse(localStorage.getItem('kz_compare_ids')) || [];
    } catch {
      return [];
    }
  };
  const saveCompareIds = (ids) => localStorage.setItem('kz_compare_ids', JSON.stringify(ids.slice(0, 4)));

  const promoLabel = (product) => {
    if (product?.sponsored_rank !== undefined) {
      return '<span class="badge sponsored-label">Sponsored</span>';
    }
    if (product?.featured) return '<span class="badge featured-label">Featured</span>';
    return '';
  };

  const formatPricing = (p) => {
    if (!p) return 'N/A';
    const base = p.model ? p.model.replace('-', ' ') : 'pricing';
    const price =
      p.starting_price !== undefined
        ? `$${p.starting_price}${p.model === 'usage-based' ? '/unit' : '/mo'}`
        : '';
    const trial = p.free_trial ? ' • Free trial' : '';
    return `${base.charAt(0).toUpperCase() + base.slice(1)} ${price}${trial}`;
  };

  const setNotFound = (slug) => {
    const label = slug ? titleize(slug) : 'Category';
    titleEl.textContent = 'Category not found';
    descriptionEl.textContent = `We couldn't find the ${label} category. Try browsing the full catalog.`;
    if (crumbEl) crumbEl.textContent = 'Category not found';
    countEl.textContent = '';
    productsGrid.innerHTML = '';
    emptyState.style.display = 'block';

    const canonicalUrl = slug
      ? `https://kamazennext.com/category/${slug}`
      : 'https://kamazennext.com/software.html';

    setMeta({
      title: 'Category not found | Kama ZenNext',
      description: `We couldn't find the ${label} category on Kama ZenNext.`,
      canonicalUrl,
      ogTitle: 'Category not found | Kama ZenNext',
      ogDescription: `We couldn't find the ${label} category on Kama ZenNext.`,
      ogUrl: canonicalUrl,
      ogType: 'website'
    });
  };

  const slug = getSlug();
  const baseUrl = 'https://kamazennext.com';
  injectBaseSchema({
    siteName: 'Kama ZenNext',
    siteUrl: baseUrl,
    logoUrl: `${baseUrl}/assets/favicon.svg`
  });

  if (!slug) {
    setNotFound('');
    return;
  }

  fetch('/data/products.json')
    .then((res) => res.json())
    .then((list) => {
      const products = Array.isArray(list) ? list : [];
      const filtered = products.filter((p) => slugify(p.category) === slug);

      if (!filtered.length) {
        setNotFound(slug);
        return;
      }

      const categoryName = filtered[0]?.category || titleize(slug);
      const description = `Explore ${categoryName} tools and software on Kama ZenNext. Compare pricing, features, and reviews to find the right fit.`;
      const canonicalUrl = `${baseUrl}/category/${slug}`;

      titleEl.textContent = `${categoryName} tools`;
      descriptionEl.textContent = description;
      if (crumbEl) crumbEl.textContent = categoryName;

      setMeta({
        title: `${categoryName} Tools & Software | Kama ZenNext`,
        description,
        canonicalUrl,
        ogTitle: `${categoryName} Tools & Software | Kama ZenNext`,
        ogDescription: description,
        ogUrl: canonicalUrl,
        ogType: 'website'
      });

      injectBreadcrumbs([
        { name: 'Home', url: `${baseUrl}/` },
        { name: 'Products', url: `${baseUrl}/software.html` },
        { name: categoryName, url: canonicalUrl }
      ]);

      countEl.textContent = `${filtered.length} product${filtered.length === 1 ? '' : 's'} found`;
      emptyState.style.display = 'none';

      const renderProducts = () => {
        const activeCompareIds = compareIds();
        productsGrid.innerHTML = '';

        filtered.forEach((p) => {
          const compareActive = activeCompareIds.includes(p.id);
          const card = document.createElement('article');
          card.className = 'product-card card';
          const catLink = `/category/${slugify(p.category)}`;
          card.innerHTML = `
            <div class="pc-top">
              <a class="badge" href="${catLink}">${p.category}</a>
              <span class="badge badge-soft">${p.pricing ? formatPricing(p.pricing) : 'Pricing'}</span>
              <span class="badge badge-soft">API: ${p.api ? 'Yes' : 'No'}</span>
              ${promoLabel(p)}
            </div>
            <h3><a href="/p/${encodeURIComponent(p.id)}">${p.name}</a></h3>
            <p class="muted">${p.tagline || ''}</p>
            <div class="meta-row">
              <span class="meta"><i class="fa-solid fa-laptop"></i> ${
                p.platforms ? p.platforms.join(', ') : 'N/A'
              }</span>
              <span class="meta"><i class="fa-regular fa-calendar"></i> Updated ${p.last_updated}</span>
            </div>
            <div class="pc-actions">
              <a class="btn" href="/p/${encodeURIComponent(p.id)}">View</a>
              <button class="btn compare-btn ${
                compareActive ? 'btn-secondary remove' : 'btn-primary'
              }" data-id="${p.id}">
                ${compareActive ? 'Remove Compare' : 'Add Compare'}
              </button>
            </div>
          `;
          card.querySelector('.compare-btn').addEventListener('click', () => {
            const ids = compareIds();
            const idx = ids.indexOf(p.id);
            if (idx >= 0) {
              ids.splice(idx, 1);
            } else {
              if (ids.length >= 4) {
                alert('You can only compare up to 4 products.');
                return;
              }
              ids.push(p.id);
            }
            saveCompareIds(ids);
            renderProducts();
          });
          productsGrid.appendChild(card);
        });
      };

      renderProducts();
    })
    .catch(() => {
      setNotFound(slug);
    });
})();
