/**
 * software-page.js
 * PURPOSE:
 * - Render the marketplace list on /software.html with filters, sorting, and pagination.
 *
 * DEPENDS ON:
 * - #productsGrid, #filtersForm (or filter inputs), category chips container
 * - /data/products.json
 *
 * REVIEW NOTES:
 * - Keep URL params as source of truth (shareable links).
 * - Guard against double-render if script is included twice.
 * - Never fail silently: log non-OK responses to console.
 */
(() => {
  const grid = document.getElementById("productsGrid");
  if (!grid) return;

  // REVIEW: prevents duplicate sections if script accidentally included twice.
  if (grid.dataset.rendered === "1") {
    console.warn("productsGrid already rendered; skipping duplicate run.");
    return;
  }
  grid.dataset.rendered = "1";

  const filtersForm = document.getElementById("filtersForm");
  const searchInput = document.getElementById("searchInput");
  const categorySelect = document.getElementById("categorySelect");
  const pricingSelect = document.getElementById("pricingSelect");
  const apiToggle = document.getElementById("apiToggle");
  const sortSelect = document.getElementById("sortSelect");
  const perSelect = document.getElementById("perSelect");
  const clearBtn = document.getElementById("clearFilters");
  const chipsContainer = document.getElementById("categoryChips");
  const bestCategoryLink = document.getElementById("bestCategoryLink");
  const countEl = document.getElementById("resultsCount");
  const pagerTop = document.getElementById("pagerTop");
  const emptyState = document.getElementById("emptyState");

  const defaultState = {
    q: "",
    cat: "all",
    pricing: "all",
    api: false,
    sort: "featured",
    page: 1,
    per: 12,
  };

  const state = { ...defaultState };

  const toNumber = (value, fallback) => {
    const parsed = Number.parseInt(value, 10);
    return Number.isNaN(parsed) ? fallback : parsed;
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

  const toSlug = (value) =>
    value
      .toString()
      .trim()
      .toLowerCase()
      .replace(/&/g, "and")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/(^-|-$)/g, "");

  const updateBestCategoryLink = () => {
    if (!bestCategoryLink) return;
    if (state.cat !== "Automation") {
      bestCategoryLink.style.display = "none";
      return;
    }
    bestCategoryLink.textContent = "View best automation tools";
    bestCategoryLink.href = "/best/automation-tools";
    bestCategoryLink.style.display = "inline-flex";
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
    };
  };

  const params = new URLSearchParams(window.location.search);
  state.q = params.get("q") || defaultState.q;
  state.cat = params.get("cat") || defaultState.cat;
  state.pricing = params.get("pricing") || defaultState.pricing;
  state.api = normalizeApi(params.get("api"));
  state.sort = params.get("sort") || defaultState.sort;
  state.page = Math.max(1, toNumber(params.get("page"), defaultState.page));
  const per = toNumber(params.get("per"), defaultState.per);
  state.per = [12, 24, 48].includes(per) ? per : defaultState.per;

  searchInput.value = state.q;
  categorySelect.value = state.cat;
  pricingSelect.value = state.pricing;
  apiToggle.checked = state.api;
  sortSelect.value = state.sort;
  perSelect.value = state.per;
  updateBestCategoryLink();

  const getCompareIds = () => {
    try {
      return JSON.parse(localStorage.getItem("kz_compare_ids")) || [];
    } catch {
      return [];
    }
  };

  const saveCompareIds = (ids) =>
    localStorage.setItem("kz_compare_ids", JSON.stringify(ids.slice(0, 4)));

  const toggleCompare = (id) => {
    const ids = getCompareIds();
    const idx = ids.indexOf(id);
    if (idx >= 0) {
      ids.splice(idx, 1);
    } else {
      if (ids.length >= 4) {
        alert("You can only compare up to 4 products.");
        return;
      }
      ids.push(id);
    }
    saveCompareIds(ids);
    render();
  };

  const updateQuery = () => {
    const next = new URLSearchParams();
    if (state.q) next.set("q", state.q);
    if (state.cat !== defaultState.cat) next.set("cat", state.cat);
    if (state.pricing !== defaultState.pricing) next.set("pricing", state.pricing);
    if (state.api) next.set("api", "1");
    if (state.sort !== defaultState.sort) next.set("sort", state.sort);
    if (state.page !== defaultState.page) next.set("page", String(state.page));
    if (state.per !== defaultState.per) next.set("per", String(state.per));
    const qs = next.toString();
    const url = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
    history.replaceState({}, "", url);
  };

  const productDate = (product) => {
    if (!product.lastUpdated) return 0;
    const time = Date.parse(product.lastUpdated);
    return Number.isNaN(time) ? 0 : time;
  };

  const sorters = {
    featured: (a, b) => {
      const rankA = a.sponsoredRank ?? Number.POSITIVE_INFINITY;
      const rankB = b.sponsoredRank ?? Number.POSITIVE_INFINITY;
      if (rankA !== rankB) return rankA - rankB;
      if (a.featured !== b.featured) return a.featured ? -1 : 1;
      return productDate(b) - productDate(a);
    },
    newest: (a, b) => productDate(b) - productDate(a),
    name_az: (a, b) => a.name.localeCompare(b.name),
  };

  const applyFilters = (items) => {
    const q = state.q.toLowerCase();
    return items
      .filter(
        (p) =>
          !q ||
          p.name.toLowerCase().includes(q) ||
          p.tagline.toLowerCase().includes(q),
      )
      .filter((p) => state.cat === "all" || p.category === state.cat)
      .filter((p) => state.pricing === "all" || p.pricingBucket === state.pricing)
      .filter((p) => !state.api || p.api)
      .sort(sorters[state.sort] || sorters.featured);
  };

  const setActiveChip = (value) => {
    if (!chipsContainer) return;
    chipsContainer.querySelectorAll(".chip").forEach((chip) => {
      chip.classList.toggle("active", chip.dataset.value === value);
    });
  };

  const setEmpty = (message) => {
    emptyState.style.display = "block";
    emptyState.textContent = message;
  };

  const renderError = (message) => {
    grid.innerHTML = "";
    setEmpty(message);
  };

  const renderPager = (totalPages) => {
    if (!pagerTop) return;
    pagerTop.innerHTML = "";
    if (totalPages <= 1) return;

    const addBtn = (label, page, disabled = false, isActive = false) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = `btn${isActive ? " btn-primary" : ""}`;
      btn.textContent = label;
      btn.disabled = disabled;
      btn.addEventListener("click", () => {
        state.page = page;
        updateQuery();
        render();
      });
      pagerTop.appendChild(btn);
    };

    addBtn("Prev", Math.max(1, state.page - 1), state.page === 1);

    const windowSize = 4;
    const start = Math.max(1, state.page - windowSize);
    const end = Math.min(totalPages, state.page + windowSize);
    for (let i = start; i <= end; i += 1) {
      addBtn(String(i), i, false, i === state.page);
    }

    addBtn("Next", Math.min(totalPages, state.page + 1), state.page === totalPages);
  };

  const render = () => {
    const filtered = applyFilters(products);
    if (!filtered.length) {
      countEl.textContent = "Showing 0 of 0 tools";
      grid.innerHTML = "";
      setEmpty("No products found. Try adjusting your filters.");
      renderPager(0);
      return;
    }

    emptyState.style.display = "none";

    const totalPages = Math.max(1, Math.ceil(filtered.length / state.per));
    state.page = Math.min(state.page, totalPages);
    const startIdx = (state.page - 1) * state.per;
    const pageItems = filtered.slice(startIdx, startIdx + state.per);

    countEl.textContent = `Showing ${pageItems.length} of ${filtered.length} tools`;
    renderPager(totalPages);

    const compareIds = getCompareIds();
    grid.innerHTML = "";
    pageItems.forEach((product) => {
      const card = document.createElement("article");
      const compareActive = compareIds.includes(product.id);
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
          <button class="btn compare-btn ${compareActive ? "btn-secondary" : "btn-primary"}" data-id="${product.id}">
            ${compareActive ? "Remove Compare" : "Add Compare"}
          </button>
        </div>
      `;
      card.querySelector(".compare-btn").addEventListener("click", () => toggleCompare(product.id));
      grid.appendChild(card);
    });
  };

  const bindFilters = () => {
    filtersForm.addEventListener("submit", (event) => event.preventDefault());
    searchInput.addEventListener("input", (event) => {
      state.q = event.target.value.trim();
      state.page = 1;
      updateQuery();
      render();
    });
    categorySelect.addEventListener("change", (event) => {
      state.cat = event.target.value;
      state.page = 1;
      updateQuery();
      setActiveChip(state.cat);
      updateBestCategoryLink();
      render();
    });
    pricingSelect.addEventListener("change", (event) => {
      state.pricing = event.target.value;
      state.page = 1;
      updateQuery();
      render();
    });
    apiToggle.addEventListener("change", (event) => {
      state.api = event.target.checked;
      state.page = 1;
      updateQuery();
      render();
    });
    sortSelect.addEventListener("change", (event) => {
      state.sort = event.target.value;
      state.page = 1;
      updateQuery();
      render();
    });
    perSelect.addEventListener("change", (event) => {
      const perValue = toNumber(event.target.value, defaultState.per);
      state.per = [12, 24, 48].includes(perValue) ? perValue : defaultState.per;
      state.page = 1;
      updateQuery();
      render();
    });
    clearBtn.addEventListener("click", () => {
      Object.assign(state, defaultState);
      searchInput.value = state.q;
      categorySelect.value = state.cat;
      pricingSelect.value = state.pricing;
      apiToggle.checked = state.api;
      sortSelect.value = state.sort;
      perSelect.value = state.per;
      updateQuery();
      setActiveChip(state.cat);
      updateBestCategoryLink();
      render();
    });
  };

  const buildChips = (items) => {
    if (!chipsContainer) return;
    const counts = new Map();
    items.forEach((product) => {
      counts.set(product.category, (counts.get(product.category) || 0) + 1);
    });
    const sortedCats = [...counts.entries()]
      .sort((a, b) => b[1] - a[1])
      .map(([cat]) => cat);
    const popular = sortedCats.slice(0, 8);
    const finalCats = ["all", ...popular];
    if (state.cat !== "all" && !finalCats.includes(state.cat)) {
      finalCats.push(state.cat);
    }

    chipsContainer.innerHTML = "";
    finalCats.forEach((cat) => {
      const chip = document.createElement("button");
      chip.type = "button";
      chip.className = `chip${state.cat === cat ? " active" : ""}`;
      chip.dataset.value = cat;
      chip.textContent = cat === "all" ? "All" : cat;
      chip.addEventListener("click", () => {
        state.cat = cat;
        categorySelect.value = cat;
        state.page = 1;
        updateQuery();
        setActiveChip(cat);
        render();
      });
      chipsContainer.appendChild(chip);
    });
  };

  const populateCategories = (items) => {
    const categories = Array.from(new Set(items.map((p) => p.category))).sort();
    categories.forEach((cat) => {
      const option = document.createElement("option");
      option.value = cat;
      option.textContent = cat;
      categorySelect.appendChild(option);
    });
    if (state.cat !== "all" && !categories.includes(state.cat)) {
      state.cat = "all";
      categorySelect.value = "all";
      updateQuery();
    }
  };

  let products = [];

  fetch("/data/products.json", { cache: "no-store" })
    .then(async (res) => {
      if (!res.ok) {
        console.error("Products API failed", {
          status: res.status,
          body: await res.text(),
        });
        throw new Error("Failed to load products");
      }
      return res.json();
    })
    .then((data) => {
      products = (data || []).map(normalizeProduct).filter((p) => p.id);
      populateCategories(products);
      buildChips(products);
      setActiveChip(state.cat);
      bindFilters();
      render();
    })
    .catch((err) => {
      console.error(err);
      renderError("Unable to load products right now. Please try again soon.");
    });
})();
