import { injectBaseSchema, injectBreadcrumbs, setMeta } from '/assets/js/seo-schema.js';

/**
 * REVIEW: COLLECTIONS
 * - Defines SEO landing pages.
 * - If a tool slug listed isn’t in products.json, skip silently.
 * - Keep copy helpful (not keyword stuffing).
 */
const COLLECTIONS = {
  "zapier-alternatives": {
    title: "Zapier Alternatives (2026)",
    h1: "Best Zapier alternatives (2026)",
    intro: "Compare workflow automation platforms for no-code teams and growing businesses. Filter by pricing, integrations, and API support, then pick the best fit.",
    supportsAudienceToggle: true,
    includeSlugs: ["make", "n8n", "pipedream", "power-automate", "zoho-flow", "integrately", "pabbly-connect", "ifttt"],
    fallbackFilter: { category: "Automation" },
    compareLinks: [
      { href: "/compare/zapier-vs-make", label: "Zapier vs Make" }
    ],
    faqs: [
      { q: "Why do people look for Zapier alternatives?", a: "Pricing, more complex workflows, self-hosting needs, and stronger control/logging are common reasons." },
      { q: "Which is best for business teams?", a: "Choose tools with strong templates, integrations, role permissions, and clear error logs." },
      { q: "Which is best for developers?", a: "Pick API-first tools with webhooks, retries, logs, and the ability to run custom code steps." }
    ]
  },
  "n8n-alternatives": {
    title: "n8n Alternatives (2026)",
    h1: "Best n8n alternatives (2026)",
    intro: "Explore automation platforms similar to n8n for teams that want flexibility, APIs, and reliable workflow execution.",
    supportsAudienceToggle: true,
    includeSlugs: ["pipedream", "make", "zapier", "node-red", "power-automate", "tray-io", "workato"],
    fallbackFilter: { category: "Automation" },
    compareLinks: [
      { href: "/compare/n8n-vs-make", label: "n8n vs Make" }
    ],
    faqs: [
      { q: "What is n8n best for?", a: "API workflows, webhooks, custom logic, and self-hosted automation setups." },
      { q: "What should I compare vs n8n?", a: "Hosting options, security, retries/logs, integrations, pricing, and support." }
    ]
  },
  "developer-automation-tools": {
    title: "Developer Automation Tools (2026)",
    h1: "Best developer automation tools (2026)",
    intro: "Tools for engineers and agencies building automation with webhooks, APIs, code steps, and reliable execution.",
    supportsAudienceToggle: false,
    fallbackFilter: { category: "Automation", aud: "builder" },
    includeSlugs: ["n8n", "pipedream", "node-red", "airflow", "prefect"],
    faqs: [
      { q: "Do developers still use no-code tools?", a: "Yes—many teams mix no-code flows with API and code steps for speed." },
      { q: "What matters most for reliability?", a: "Retries, idempotency, logs, alerting, and clear failure handling." }
    ]
  }
};

const AUDIENCE_OPTIONS = [
  { value: "all", label: "All" },
  { value: "business", label: "Business" },
  { value: "builder", label: "Builder" }
];

const grid = document.getElementById("bestGrid");
if (grid) {
  const titleEl = document.getElementById("bestTitle");
  const introEl = document.getElementById("bestIntro");
  const faqEl = document.getElementById("bestFaq");
  const faqSection = document.getElementById("faqSection");
  const audienceToggle = document.getElementById("audienceToggle");
  const compareLinks = document.getElementById("compareLinks");
  const breadcrumbsList = document.getElementById("breadcrumbsList");

  const params = new URLSearchParams(window.location.search);
  const slug = params.get("s") || "";
  const collection = COLLECTIONS[slug];

  const ensureScript = (id, data) => {
    if (!data) return;
    const existing = document.getElementById(id);
    if (existing) existing.remove();
    const script = document.createElement("script");
    script.type = "application/ld+json";
    script.id = id;
    script.textContent = JSON.stringify(data, null, 2);
    document.head.appendChild(script);
  };

  const normalizeApi = (value) => {
    if (value === true) return true;
    if (typeof value === "string") {
      return ["1", "true", "yes"].includes(value.toLowerCase());
    }
    return false;
  };

  const normalizePricingBucket = (pricingStr) => {
    const raw = (pricingStr || "").toString().toLowerCase();
    if (!raw) return "unknown";
    if (raw.includes("freemium")) return "freemium";
    if (raw.includes("free")) return "free";
    if (raw.includes("subscription") || raw.includes("paid") || raw.includes("usage")) {
      return "paid";
    }
    return raw;
  };

  const normalizeAudience = (value) => {
    if (Array.isArray(value) && value.length) {
      return value.map((entry) => entry.toLowerCase());
    }
    return ["business", "builder"];
  };

  const normalizeProduct = (p) => {
    const pricingStr = p?.pricing?.model || p?.pricing || "—";
    return {
      id: p?.id || p?.slug || "",
      slug: p?.slug || p?.id || "",
      name: p?.name || "Untitled",
      tagline: p?.tagline || "",
      category: p?.category || "Other",
      pricingStr,
      pricingBucket: normalizePricingBucket(pricingStr),
      api: p?.api === true || normalizeApi(p?.api),
      featured: Boolean(p?.featured),
      sponsoredRank: p?.sponsored_rank,
      lastUpdated: p?.last_updated || "",
      platforms: Array.isArray(p?.platforms) ? p.platforms : [],
      website: p?.website || "",
      audience: normalizeAudience(p?.audience)
    };
  };

  const buildBreadcrumbs = (title) => {
    const siteUrl = window.location.origin;
    const crumbs = [
      { name: "Home", url: `${siteUrl}/` },
      { name: "Best", url: `${siteUrl}/best/` },
      { name: title, url: `${siteUrl}/best/${slug}` }
    ];
    if (breadcrumbsList) {
      breadcrumbsList.innerHTML = crumbs
        .map((crumb, idx) => `<li><a href="${crumb.url}">${crumb.name}</a>${idx < crumbs.length - 1 ? "" : ""}</li>`)
        .join("");
    }
    injectBreadcrumbs(crumbs);
  };

  const updateMeta = (title, description) => {
    const siteUrl = window.location.origin;
    setMeta({
      title,
      description,
      canonicalUrl: `${siteUrl}/best/${slug}`,
      ogTitle: title,
      ogDescription: description,
      ogUrl: `${siteUrl}/best/${slug}`
    });
  };

  const buildCard = (product) => {
    const card = document.createElement("article");
    const flag = product.sponsoredRank !== undefined
      ? '<span class="pc-flag sponsored">Sponsored</span>'
      : product.featured
        ? '<span class="pc-flag featured"><i class="fa-solid fa-thumbtack"></i> Featured</span>'
        : "";
    const identifier = product.slug || product.id;
    const visitUrl = identifier
      ? `/out.php?slug=${encodeURIComponent(identifier)}&from=best`
      : product.website || "#";
    card.className = "product-card card";
    card.innerHTML = `
      <div class="pc-top">
        <div class="pc-badges">
          <span class="badge">${product.category}</span>
          <span class="badge badge-soft">${product.pricingStr}</span>
          ${product.api ? '<span class="badge badge-green">API</span>' : ""}
        </div>
        ${flag}
      </div>
      <h3><a href="/p/${encodeURIComponent(product.slug || product.id)}">${product.name}</a></h3>
      <p class="muted">${product.tagline}</p>
      <div class="meta-row">
        <span class="meta"><i class="fa-regular fa-calendar"></i> Updated ${product.lastUpdated || "—"}</span>
        ${product.platforms.length ? `<span class="meta"><i class="fa-solid fa-laptop"></i> ${product.platforms.join(", ")}</span>` : ""}
      </div>
      <div class="pc-actions">
        <a class="btn" href="/p/${encodeURIComponent(product.slug || product.id)}">View</a>
        <a class="btn btn-primary" href="${visitUrl}" target="_blank" rel="nofollow sponsored noopener">Visit</a>
      </div>
    `;
    return card;
  };

  const renderFaqs = (faqs = []) => {
    if (!faqEl || !faqSection) return;
    if (!faqs.length) {
      faqSection.style.display = "none";
      return;
    }
    faqSection.style.display = "block";
    faqEl.innerHTML = faqs
      .map((faq) => {
        const question = faq.q || faq.question || "";
        const answer = faq.a || faq.answer || "";
        if (!question || !answer) return "";
        return `
          <details>
            <summary>${question}</summary>
            <p>${answer}</p>
          </details>
        `;
      })
      .join("");
  };

  const renderCompareLinks = (links = []) => {
    if (!compareLinks) return;
    if (!links.length) {
      compareLinks.innerHTML = "";
      return;
    }
    compareLinks.innerHTML = links
      .map((link) => `<a class="btn" href="${link.href}">${link.label}</a>`)
      .join("");
  };

  const renderAudienceToggle = (aud, enabled) => {
    if (!audienceToggle) return;
    if (!enabled) {
      audienceToggle.innerHTML = "";
      return;
    }
    audienceToggle.innerHTML = AUDIENCE_OPTIONS.map((option) => {
      const active = option.value === aud ? " active" : "";
      return `<button class="chip${active}" data-aud="${option.value}" type="button">${option.label}</button>`;
    }).join("");

    audienceToggle.onclick = (event) => {
      const button = event.target.closest("[data-aud]");
      if (!button) return;
      const nextAud = button.dataset.aud;
      const nextParams = new URLSearchParams(window.location.search);
      if (nextAud === "all") {
        nextParams.delete("aud");
      } else {
        nextParams.set("aud", nextAud);
      }
      const qs = nextParams.toString();
      const url = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
      window.history.replaceState({}, "", url);
      load(nextAud);
    };
  };

  const buildItemListSchema = (items) => {
    const listItems = items.slice(0, 10).map((item, idx) => ({
      "@type": "ListItem",
      position: idx + 1,
      name: item.name,
      url: `${window.location.origin}/p/${encodeURIComponent(item.slug || item.id)}`
    }));
    ensureScript("kz-best-itemlist", {
      "@context": "https://schema.org",
      "@type": "ItemList",
      itemListElement: listItems
    });
  };

  const buildFaqSchema = (faqs = []) => {
    if (!faqs.length) return;
    const mainEntity = faqs
      .map((faq) => ({
        "@type": "Question",
        name: faq.q || faq.question,
        acceptedAnswer: {
          "@type": "Answer",
          text: faq.a || faq.answer
        }
      }))
      .filter((entity) => entity.name && entity.acceptedAnswer.text);
    if (!mainEntity.length) return;
    ensureScript("kz-best-faq", {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      mainEntity
    });
  };

  const applyAudienceFilter = (items, aud) => {
    if (!aud || aud === "all") return items;
    return items.filter((item) => (item.audience || ["business", "builder"]).includes(aud));
  };

  const resolveProducts = (items, aud) => {
    const seen = new Set();
    let results = [];

    if (collection.includeSlugs?.length) {
      collection.includeSlugs.forEach((includeSlug) => {
        const match = items.find(
          (item) => item.slug === includeSlug || item.id === includeSlug
        );
        if (match && !seen.has(match.id)) {
          seen.add(match.id);
          results.push(match);
        }
      });
    }

    const targetCount = Math.max(6, collection.includeSlugs?.length || 0);
    if (collection.fallbackFilter && results.length < targetCount) {
      const { category, aud: fallbackAud } = collection.fallbackFilter;
      const fallbackList = items.filter((item) => {
        const categoryMatch = !category || item.category === category;
        const audMatch = fallbackAud
          ? (item.audience || ["business", "builder"]).includes(fallbackAud)
          : true;
        return categoryMatch && audMatch && !seen.has(item.id);
      });
      fallbackList.forEach((item) => {
        if (!seen.has(item.id)) {
          seen.add(item.id);
          results.push(item);
        }
      });
    }

    return applyAudienceFilter(results, aud);
  };

  const load = (audienceOverride) => {
    if (!collection) {
      grid.innerHTML = '<div class="empty card">This best page is not available yet.</div>';
      return;
    }

    const audParam = audienceOverride
      || (collection.supportsAudienceToggle ? params.get("aud") || "all" : "all");
    const aud = ["business", "builder"].includes(audParam) ? audParam : "all";

    titleEl.textContent = collection.h1;
    introEl.textContent = collection.intro;
    renderCompareLinks(collection.compareLinks || []);
    renderAudienceToggle(aud, collection.supportsAudienceToggle);
    renderFaqs(collection.faqs || []);
    buildBreadcrumbs(collection.title || collection.h1);
    updateMeta(collection.title || collection.h1, collection.intro);

    fetch("/data/products.json", { cache: "no-store" })
      .then((res) => res.json())
      .then((data) => {
        const items = (data || []).map(normalizeProduct).filter((item) => item.id);
        const selected = resolveProducts(items, aud);
        grid.innerHTML = "";
        if (!selected.length) {
          grid.innerHTML = '<div class="empty card">No tools found for this page yet.</div>';
          return;
        }
        selected.forEach((product) => grid.appendChild(buildCard(product)));
        buildItemListSchema(selected);
        buildFaqSchema(collection.faqs || []);
        const siteUrl = window.location.origin;
        injectBaseSchema({
          siteName: "Kama ZenNext",
          siteUrl,
          logoUrl: `${siteUrl}/assets/favicon.svg`
        });
      })
      .catch(() => {
        grid.innerHTML = '<div class="empty card">Unable to load tools right now.</div>';
      });
  };

  load();
}
