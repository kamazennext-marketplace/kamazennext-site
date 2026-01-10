import { injectBaseSchema, injectBreadcrumbs, setMeta } from '/assets/js/seo-schema.js';

const compareTitle = document.getElementById('compareTitle');
const compareIntro = document.getElementById('compareIntro');
const compareCards = document.getElementById('compareCards');
const compareTable = document.getElementById('compareTable');
const compareFaq = document.getElementById('compareFaq');
const faqSection = document.getElementById('faqSection');
const compareAlternatives = document.getElementById('compareAlternatives');
const breadcrumbsList = document.getElementById('breadcrumbsList');

const params = new URLSearchParams(window.location.search);
const slugA = params.get('a');
const slugB = params.get('b');

const ensureScript = (id, data) => {
  if (!data) return;
  const existing = document.getElementById(id);
  if (existing) existing.remove();
  const script = document.createElement('script');
  script.type = 'application/ld+json';
  script.id = id;
  script.textContent = JSON.stringify(data, null, 2);
  document.head.appendChild(script);
};

const normalizePricing = (pricing) => {
  if (!pricing) return '—';
  if (typeof pricing === 'string') return pricing;
  const parts = [];
  if (pricing.model) parts.push(pricing.model);
  if (pricing.starting_price !== undefined) parts.push(`$${pricing.starting_price}`);
  if (pricing.free_trial) parts.push('Free trial');
  return parts.join(' • ') || '—';
};

const normalizeProduct = (p) => ({
  id: p?.id || p?.slug || '',
  slug: p?.slug || p?.id || '',
  name: p?.name || 'Unknown',
  category: p?.category || 'Automation',
  tagline: p?.tagline || '—',
  pricing: normalizePricing(p?.pricing),
  api: p?.api === true || p?.api === 'true',
  bestFor: Array.isArray(p?.best_for) ? p.best_for : [],
  pros: Array.isArray(p?.pros) ? p.pros : [],
  cons: Array.isArray(p?.cons) ? p.cons : [],
  website: p?.website || ''
});

const buildBreadcrumbs = (title) => {
  const siteUrl = window.location.origin;
  const crumbs = [
    { name: 'Home', url: `${siteUrl}/` },
    { name: 'Compare', url: `${siteUrl}/compare/` },
    { name: title, url: `${siteUrl}/compare/${slugA}-vs-${slugB}` }
  ];
  if (breadcrumbsList) {
    breadcrumbsList.innerHTML = crumbs
      .map((crumb) => `<li><a href="${crumb.url}">${crumb.name}</a></li>`)
      .join('');
  }
  injectBreadcrumbs(crumbs);
};

const buildFaqs = (productA, productB) => ([
  {
    q: `Which is better for business teams: ${productA.name} or ${productB.name}?`,
    a: 'Compare ease of setup, template depth, integrations, and pricing at your expected volume.'
  },
  {
    q: 'Which option is best for developers?',
    a: 'Look for API coverage, webhooks, retry logic, and the ability to run custom code steps.'
  },
  {
    q: 'What should I test before committing?',
    a: 'Run a real workflow with error handling, logging, and team permissions to validate reliability.'
  }
]);

const renderFaqs = (faqs) => {
  if (!compareFaq || !faqSection) return;
  if (!faqs.length) {
    faqSection.style.display = 'none';
    return;
  }
  faqSection.style.display = 'block';
  compareFaq.innerHTML = faqs
    .map((faq) => `
      <details>
        <summary>${faq.q}</summary>
        <p>${faq.a}</p>
      </details>
    `)
    .join('');
};

const renderCards = (productA, productB) => {
  const cardMarkup = (product) => {
    const identifier = product.slug || product.id;
    const visitUrl = identifier
      ? `/out.php?slug=${encodeURIComponent(identifier)}&from=compare`
      : product.website || '#';
    const bestFor = product.bestFor.length
      ? product.bestFor.map((item) => `<span class="badge badge-soft">${item}</span>`).join('')
      : '<span class="muted">Not specified</span>';
    return `
      <article class="card compare-card">
        <h2>${product.name}</h2>
        <p>${product.tagline}</p>
        <div class="compare-meta">
          <span class="badge">${product.category}</span>
          <span class="badge badge-soft">${product.pricing}</span>
          ${product.api ? '<span class="badge badge-green">API</span>' : ''}
        </div>
        <div class="compare-meta">${bestFor}</div>
        <div class="compare-actions">
          <a class="btn" href="/p/${encodeURIComponent(product.slug || product.id)}">View</a>
          <a class="btn btn-primary" href="${visitUrl}" target="_blank" rel="nofollow sponsored noopener">Visit</a>
        </div>
      </article>
    `;
  };

  compareCards.innerHTML = cardMarkup(productA) + cardMarkup(productB);
};

const renderTable = (productA, productB) => {
  const rows = [
    { label: 'Pricing', valueA: productA.pricing, valueB: productB.pricing },
    { label: 'Category', valueA: productA.category, valueB: productB.category },
    { label: 'API', valueA: productA.api ? 'Yes' : 'No', valueB: productB.api ? 'Yes' : 'No' },
    { label: 'Best for', valueA: productA.bestFor.join(', ') || '—', valueB: productB.bestFor.join(', ') || '—' },
    { label: 'Pros', valueA: productA.pros.join(', ') || '—', valueB: productB.pros.join(', ') || '—' },
    { label: 'Cons', valueA: productA.cons.join(', ') || '—', valueB: productB.cons.join(', ') || '—' }
  ];

  compareTable.innerHTML = `
    <table>
      <thead>
        <tr>
          <th>Criteria</th>
          <th>${productA.name}</th>
          <th>${productB.name}</th>
        </tr>
      </thead>
      <tbody>
        ${rows.map((row) => `
          <tr>
            <th>${row.label}</th>
            <td>${row.valueA}</td>
            <td>${row.valueB}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
};

const renderAlternatives = () => {
  if (!compareAlternatives) return;
  compareAlternatives.innerHTML = `
    <a class="btn" href="/best/zapier-alternatives">Zapier alternatives</a>
    <a class="btn" href="/best/n8n-alternatives">n8n alternatives</a>
    <a class="btn" href="/best/developer-automation-tools">Developer automation tools</a>
  `;
};

const buildSchema = (productA, productB, faqs) => {
  ensureScript('kz-compare-itemlist', {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    itemListElement: [
      {
        '@type': 'ListItem',
        position: 1,
        name: productA.name,
        url: `${window.location.origin}/p/${encodeURIComponent(productA.slug || productA.id)}`
      },
      {
        '@type': 'ListItem',
        position: 2,
        name: productB.name,
        url: `${window.location.origin}/p/${encodeURIComponent(productB.slug || productB.id)}`
      }
    ]
  });

  if (faqs.length) {
    ensureScript('kz-compare-faq', {
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      mainEntity: faqs.map((faq) => ({
        '@type': 'Question',
        name: faq.q,
        acceptedAnswer: {
          '@type': 'Answer',
          text: faq.a
        }
      }))
    });
  }
};

const renderError = (message) => {
  compareCards.innerHTML = `<div class="empty card">${message}</div>`;
  compareTable.innerHTML = '';
  if (faqSection) faqSection.style.display = 'none';
};

if (!slugA || !slugB) {
  renderError('Missing comparison slugs.');
} else {
  fetch('/data/products.json', { cache: 'no-store' })
    .then((res) => res.json())
    .then((data) => {
      const items = (data || []).map(normalizeProduct).filter((item) => item.id);
      const productA = items.find((item) => item.slug === slugA || item.id === slugA);
      const productB = items.find((item) => item.slug === slugB || item.id === slugB);

      if (!productA || !productB) {
        renderError('Unable to find both products for this comparison.');
        return;
      }

      const title = `${productA.name} vs ${productB.name} (2026)`;
      compareTitle.textContent = title;
      compareIntro.textContent = `Compare ${productA.name} and ${productB.name} across pricing, API support, and best-fit use cases.`;
      renderCards(productA, productB);
      renderTable(productA, productB);
      renderAlternatives();

      const faqs = buildFaqs(productA, productB);
      renderFaqs(faqs);
      buildBreadcrumbs(title);
      buildSchema(productA, productB, faqs);

      const siteUrl = window.location.origin;
      setMeta({
        title,
        description: compareIntro.textContent,
        canonicalUrl: `${siteUrl}/compare/${slugA}-vs-${slugB}`,
        ogTitle: title,
        ogDescription: compareIntro.textContent,
        ogUrl: `${siteUrl}/compare/${slugA}-vs-${slugB}`
      });
      injectBaseSchema({
        siteName: 'Kama ZenNext',
        siteUrl,
        logoUrl: `${siteUrl}/assets/favicon.svg`
      });
    })
    .catch(() => {
      renderError('Unable to load comparison data right now.');
    });
}
