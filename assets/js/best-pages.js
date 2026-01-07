import { injectBaseSchema, injectBreadcrumbs, setMeta } from '/assets/js/seo-schema.js';

const CATEGORY_CONTENT = {
  automation: {
    intro: "Automation platforms help teams orchestrate workflows, connect apps, and ship reliable processes at scale.",
    faqs: [
      {
        question: "What should I look for in automation tools?",
        answer: "Prioritize workflow flexibility, pre-built integrations, and observability so you can track every step."
      },
      {
        question: "Do automation tools support non-technical teams?",
        answer: "Yes. Most provide no-code builders and reusable templates so business teams can launch automations quickly."
      },
      {
        question: "How do I compare automation platforms?",
        answer: "Review connector coverage, pricing per run, and how they handle errors or approvals."
      }
    ]
  },
  chatbots: {
    intro: "Chatbot platforms combine AI, workflow rules, and channel integrations to deliver fast, branded customer support.",
    faqs: [
      {
        question: "Are chatbots only for support?",
        answer: "No. Modern chatbots also handle sales qualification, onboarding, and internal knowledge search."
      },
      {
        question: "What makes a chatbot experience great?",
        answer: "Look for accurate intent detection, fast handoff to humans, and robust analytics."
      }
    ]
  },
  "ai-assistant": {
    intro: "AI assistants streamline research, drafting, and task execution so teams move faster across the workday.",
    faqs: [
      {
        question: "How do AI assistants fit into daily work?",
        answer: "They summarize content, draft responses, and automate repetitive tasks inside your existing tools."
      },
      {
        question: "Are AI assistants secure?",
        answer: "Choose vendors that offer data retention controls, SOC 2 compliance, and admin governance features."
      }
    ]
  },
  analytics: {
    intro: "Analytics platforms unify product, revenue, and marketing data so leaders can act on a single source of truth.",
    faqs: [
      {
        question: "What should analytics teams compare?",
        answer: "Focus on data connectors, visualization depth, and governance options like RBAC and audit logs."
      },
      {
        question: "Can analytics tools replace spreadsheets?",
        answer: "Yes. They automate reporting and deliver real-time dashboards so teams stop manual exports."
      }
    ]
  },
  "data-stack": {
    intro: "Data stack tools help teams ingest, warehouse, and activate data across the business with confidence.",
    faqs: [
      {
        question: "How do I pick the right data stack?",
        answer: "Map your pipeline needs first, then evaluate ingestion reliability, warehouse costs, and downstream activation."
      },
      {
        question: "Do data stack tools require engineers?",
        answer: "Many provide low-code connectors, but a technical owner helps maintain data quality long term."
      }
    ]
  },
  "developer-tools": {
    intro: "Developer tools optimize shipping velocity with better observability, testing, and deployment automation.",
    faqs: [
      {
        question: "Which developer tools deliver the fastest ROI?",
        answer: "Start with CI/CD, monitoring, and code quality automation to prevent regressions and outages."
      },
      {
        question: "How do teams evaluate new dev tools?",
        answer: "Run pilot projects, track developer feedback, and compare integration with your existing stack."
      }
    ]
  },
  productivity: {
    intro: "Productivity suites combine planning, documentation, and collaboration so teams stay aligned every day.",
    faqs: [
      {
        question: "What differentiates productivity tools?",
        answer: "Look for flexible workflows, strong integrations, and views that match your team’s process."
      },
      {
        question: "Can productivity tools replace multiple apps?",
        answer: "Yes. Many consolidate docs, tasks, and messaging to reduce tool sprawl."
      }
    ]
  },
  security: {
    intro: "Security platforms reduce risk with continuous monitoring, threat response, and compliance automation.",
    faqs: [
      {
        question: "What is essential for security tools?",
        answer: "Prioritize real-time alerting, clear remediation workflows, and compliance reporting."
      },
      {
        question: "How do I justify security investments?",
        answer: "Track prevented incidents, reduced response time, and compliance audit savings."
      }
    ]
  },
  "voice-ai": {
    intro: "Voice AI tools power call automation, voice agents, and transcription workflows for faster service.",
    faqs: [
      {
        question: "Where is voice AI used most?",
        answer: "Common use cases include call centers, appointment booking, and sales outreach."
      },
      {
        question: "How do I evaluate voice AI quality?",
        answer: "Test accuracy across accents, evaluate latency, and confirm analytics for call outcomes."
      }
    ]
  }
};

const toSlug = (value) =>
  value
    .toString()
    .trim()
    .toLowerCase()
    .replace(/&/g, "and")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/(^-|-$)/g, "");

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
    platforms: Array.isArray(p?.platforms) ? p.platforms : []
  };
};

const productDate = (product) => {
  if (!product.lastUpdated) return 0;
  const time = Date.parse(product.lastUpdated);
  return Number.isNaN(time) ? 0 : time;
};

const sortFeatured = (a, b) => {
  const rankA = a.sponsoredRank ?? Number.POSITIVE_INFINITY;
  const rankB = b.sponsoredRank ?? Number.POSITIVE_INFINITY;
  if (rankA !== rankB) return rankA - rankB;
  if (a.featured !== b.featured) return a.featured ? -1 : 1;
  return productDate(b) - productDate(a);
};

const buildProductCard = (product) => {
  const card = document.createElement("article");
  const flag = product.sponsoredRank !== undefined
    ? '<span class="pc-flag sponsored">Sponsored</span>'
    : product.featured
      ? '<span class="pc-flag featured"><i class="fa-solid fa-thumbtack"></i> Featured</span>'
      : "";
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
    </div>
  `;
  return card;
};

const renderFaqs = (faqList, target) => {
  target.innerHTML = "";
  faqList.forEach((faq) => {
    const item = document.createElement("details");
    item.innerHTML = `
      <summary>${faq.question}</summary>
      <p>${faq.answer}</p>
    `;
    target.appendChild(item);
  });
};

const renderCategoryIndex = (products) => {
  const container = document.getElementById("bestCategories");
  if (!container) return;

  const counts = new Map();
  products.forEach((product) => {
    counts.set(product.category, (counts.get(product.category) || 0) + 1);
  });

  const categories = [...counts.entries()]
    .map(([category, count]) => ({ category, count, slug: toSlug(category) }))
    .sort((a, b) => a.category.localeCompare(b.category));

  container.innerHTML = "";
  categories.forEach(({ category, count, slug }) => {
    const meta = CATEGORY_CONTENT[slug];
    const tile = document.createElement("a");
    tile.href = `/best/${slug}-tools`;
    tile.className = "card category-tile";
    tile.innerHTML = `
      <h3>${category}</h3>
      <div class="category-meta">
        <span><i class="fa-solid fa-layer-group"></i> ${count} tools</span>
        <span><i class="fa-solid fa-arrow-right"></i> Explore</span>
      </div>
      <p>${meta?.intro || `Explore top-rated ${category} tools from our marketplace.`}</p>
    `;
    container.appendChild(tile);
  });
};

const renderCategoryPage = (products) => {
  const params = new URLSearchParams(window.location.search);
  const slug = params.get("c") || "";
  const categories = Array.from(new Set(products.map((p) => p.category)));
  const slugMap = new Map(categories.map((cat) => [toSlug(cat), cat]));
  const category = slugMap.get(slug);
  if (!category) {
    window.location.replace("/404.html");
    return;
  }

  const meta = CATEGORY_CONTENT[slug] || {};
  const categoryTitle = document.getElementById("categoryTitle");
  const categoryIntro = document.getElementById("categoryIntro");
  const grid = document.getElementById("categoryGrid");
  const featuredSection = document.getElementById("featuredSection");
  const featuredStrip = document.getElementById("featuredStrip");
  const faqList = document.getElementById("faqList");

  const items = products.filter((product) => product.category === category).sort(sortFeatured);
  const featured = items.filter((product) => product.featured || product.sponsoredRank !== undefined);

  categoryTitle.textContent = `Best ${category} tools (2026)`;
  categoryIntro.textContent = meta.intro || `Explore the best ${category} tools with verified listings and featured picks.`;

  if (featured.length) {
    featuredSection.style.display = "block";
    featuredStrip.innerHTML = "";
    featured.slice(0, 4).forEach((product) => {
      const card = document.createElement("article");
      card.className = "card featured-card";
      card.innerHTML = `
        <div class="pc-top">
          <div class="pc-badges">
            <span class="badge">${product.category}</span>
            <span class="badge badge-soft">${product.pricingStr}</span>
          </div>
        </div>
        <h3><a href="/p/${encodeURIComponent(product.slug || product.id)}">${product.name}</a></h3>
        <p>${product.tagline}</p>
        <a class="secondary-link" href="/p/${encodeURIComponent(product.slug || product.id)}">View tool</a>
      `;
      featuredStrip.appendChild(card);
    });
  }

  grid.innerHTML = "";
  items.forEach((product) => grid.appendChild(buildProductCard(product)));

  const defaultFaqs = [
    {
      question: `How do I choose ${category} tools?`,
      answer: `Compare feature depth, integrations, and ROI to find the best ${category} tools for your team.`
    },
    {
      question: `Are there free ${category} tools?`,
      answer: `Yes. Many ${category} platforms offer free tiers or trials. Filter by pricing to explore options.`
    }
  ];
  renderFaqs(meta.faqs || defaultFaqs, faqList);

  const siteUrl = window.location.origin;
  const canonicalUrl = `${siteUrl}/best/${slug}-tools`;
  const description = meta.intro || `Discover the best ${category} tools curated by Kama ZenNext.`;

  setMeta({
    title: `Best ${category} tools (2026) | Kama ZenNext`,
    description,
    canonicalUrl,
    ogTitle: `Best ${category} tools (2026)`,
    ogDescription: description,
    ogUrl: canonicalUrl,
    ogType: "website"
  });

  injectBaseSchema({
    siteName: "Kama ZenNext",
    siteUrl,
    logoUrl: `${siteUrl}/assets/favicon.svg`
  });

  injectBreadcrumbs([
    { name: "Home", url: `${siteUrl}/` },
    { name: "Best tools", url: `${siteUrl}/best/` },
    { name: `${category} tools`, url: canonicalUrl }
  ]);

  const crumbsList = document.getElementById("breadcrumbsList");
  if (crumbsList) {
    crumbsList.innerHTML = `
      <li><a href="/">Home</a></li>
      <li><a href="/best/">Best tools</a></li>
      <li aria-current="page">${category} tools</li>
    `;
  }

  const topItems = items.slice(0, 8).map((product, idx) => ({
    "@type": "ListItem",
    position: idx + 1,
    name: product.name,
    url: `${siteUrl}/p/${encodeURIComponent(product.slug || product.id)}`
  }));

  const itemListSchema = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: `Best ${category} tools`,
    itemListElement: topItems
  };

  const script = document.createElement("script");
  script.type = "application/ld+json";
  script.textContent = JSON.stringify(itemListSchema, null, 2);
  document.head.appendChild(script);
};

const init = async () => {
  const page = document.body.dataset.bestPage;
  if (!page) return;

  const res = await fetch("/data/products.json", { cache: "no-store" });
  if (!res.ok) {
    console.error("Products API failed", res.status);
    return;
  }
  const raw = await res.json();
  const products = raw.map(normalizeProduct);

  if (page === "index") {
    renderCategoryIndex(products);
  }

  if (page === "category") {
    renderCategoryPage(products);
  }
};

init();
