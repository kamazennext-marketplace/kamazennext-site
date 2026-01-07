/**
 * PURPOSE:
 * - Render and filter the /software.html product listing.
 *
 * DEPENDS ON:
 * - #productsGrid, #emptyState, #count, #search, #category, #pricing, #api, #sort, #featuredOnly
 * - #categoryChips
 * - /data/products.json
 *
 * NOTES:
 * - Keep IDs unique. Rendering twice = duplicate sections.
 * - Log failures to console for debugging (never silent).
 */
(() => {
  const root = document.getElementById("productsGrid");
  if (!root) return;

  // Guard against accidental double-inclusion causing duplicate sections.
  if (root.dataset.rendered === "1") {
    console.warn("productsGrid already rendered; skipping duplicate run.");
    return;
  }
  root.dataset.rendered = "1";

  const productsGrid = root;
  const emptyState = document.getElementById("emptyState");
  const countEl = document.getElementById("count");
  const searchInput = document.getElementById("search");
  const categorySelect = document.getElementById("category");
  const categoryChips = document.getElementById("categoryChips");
  const pricingSelect = document.getElementById("pricing");
  const apiSelect = document.getElementById("api");
  const sortSelect = document.getElementById("sort");
  const featuredCheckbox = document.getElementById("featuredOnly");

  const slugify = (str) =>
    (str || "")
      .toString()
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/(^-|-$)+/g, "");

  const params = new URLSearchParams(window.location.search);
  const state = {
    q: params.get("q") || "",
    cat: params.get("cat") || "all",
    pricing: params.get("pricing") || "all",
    api: params.get("api") || "all",
    sort: params.get("sort") || "recent",
    featured: params.get("featured") === "1",
  };

  const setActiveChip = (value) => {
    if (!categoryChips) return;
    categoryChips.querySelectorAll(".chip").forEach((chip) => {
      chip.classList.toggle("active", chip.dataset.value === value);
    });
  };

  searchInput.value = state.q;
  categorySelect.value = state.cat;
  pricingSelect.value = state.pricing;
  apiSelect.value = state.api;
  sortSelect.value = state.sort;
  featuredCheckbox.checked = state.featured;
  setActiveChip(state.cat);

  let products = [];

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
    renderProducts();
  };

  const updateQuery = () => {
    const newParams = new URLSearchParams();
    if (state.q) newParams.set("q", state.q);
    if (state.cat !== "all") newParams.set("cat", state.cat);
    if (state.pricing !== "all") newParams.set("pricing", state.pricing);
    if (state.api !== "all") newParams.set("api", state.api);
    if (state.sort !== "recent") newParams.set("sort", state.sort);
    if (state.featured) newParams.set("featured", "1");
    const qs = newParams.toString();
    const newUrl = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
    history.replaceState({}, "", newUrl);
  };

  const promoLabel = (product) => {
    if (product?.sponsored_rank !== undefined)
      return '<span class="badge sponsored-label">Sponsored</span>';
    if (product?.featured) return '<span class="badge featured-label">Featured</span>';
    return "";
  };

  const formatPricing = (p) => {
    if (!p) return "N/A";
    const base = p.model ? p.model.replace("-", " ") : "pricing";
    const price =
      p.starting_price !== undefined
        ? `$${p.starting_price}${p.model === "usage-based" ? "/unit" : "/mo"}`
        : "";
    const trial = p.free_trial ? " • Free trial" : "";
    return `${base.charAt(0).toUpperCase() + base.slice(1)} ${price}${trial}`;
  };

  const applyFilters = () => {
    const q = state.q.toLowerCase();
    return products
      .filter(
        (p) =>
          !q ||
          p.name.toLowerCase().includes(q) ||
          (p.tagline || "").toLowerCase().includes(q),
      )
      .filter((p) => state.cat === "all" || p.category === state.cat)
      .filter((p) => state.pricing === "all" || (p.pricing && p.pricing.model === state.pricing))
      .filter((p) => state.api === "all" || (state.api === "yes" ? p.api : !p.api))
      .filter((p) => !state.featured || p.featured || p.sponsored_rank !== undefined)
      .sort((a, b) => {
        if (state.sort === "name") return a.name.localeCompare(b.name);
        return new Date(b.last_updated) - new Date(a.last_updated);
      });
  };

  const renderProducts = () => {
    const filtered = applyFilters();
    productsGrid.innerHTML = "";
    if (!filtered.length) {
      emptyState.style.display = "block";
      countEl.textContent = "No products match your filters.";
      return;
    }
    emptyState.style.display = "none";
    countEl.textContent = `${filtered.length} product${filtered.length === 1 ? "" : "s"} found`;
    const compareIds = getCompareIds();
    filtered.forEach((p) => {
      const card = document.createElement("article");
      const compareActive = compareIds.includes(p.id);
      card.className = "product-card card";
      const catLink = `/category/${slugify(p.category)}`;
      card.innerHTML = `
          <div class="pc-top">
            <a class="badge" href="${catLink}">${p.category}</a>
            <span class="badge badge-soft">${
              p.pricing ? formatPricing(p.pricing) : "Pricing"
            }</span>
            <span class="badge badge-soft">API: ${p.api ? "Yes" : "No"}</span>
            ${promoLabel(p)}
          </div>
          <h3><a href="/p/${encodeURIComponent(p.id)}">${p.name}</a></h3>
          <p class="muted">${p.tagline || ""}</p>
          <div class="meta-row">
            <span class="meta"><i class="fa-solid fa-laptop"></i> ${
              p.platforms ? p.platforms.join(", ") : "N/A"
            }</span>
            <span class="meta"><i class="fa-regular fa-calendar"></i> Updated ${
              p.last_updated
            }</span>
          </div>
          <div class="pc-actions">
            <a class="btn" href="/p/${encodeURIComponent(p.id)}">View</a>
            <button class="btn compare-btn ${
              compareActive ? "btn-secondary remove" : "btn-primary"
            }" data-id="${p.id}">
              ${compareActive ? "Remove Compare" : "Add Compare"}
            </button>
          </div>
        `;
      card.querySelector(".compare-btn").addEventListener("click", () => toggleCompare(p.id));
      productsGrid.appendChild(card);
    });
  };

  const bindFilters = () => {
    searchInput.addEventListener("input", (e) => {
      state.q = e.target.value.trim();
      updateQuery();
      renderProducts();
    });
    categorySelect.addEventListener("change", (e) => {
      state.cat = e.target.value;
      updateQuery();
      setActiveChip(state.cat);
      renderProducts();
    });
    pricingSelect.addEventListener("change", (e) => {
      state.pricing = e.target.value;
      updateQuery();
      renderProducts();
    });
    apiSelect.addEventListener("change", (e) => {
      state.api = e.target.value;
      updateQuery();
      renderProducts();
    });
    sortSelect.addEventListener("change", (e) => {
      state.sort = e.target.value;
      updateQuery();
      renderProducts();
    });
    featuredCheckbox.addEventListener("change", (e) => {
      state.featured = e.target.checked;
      updateQuery();
      renderProducts();
    });
  };

  const populateCategories = () => {
    const cats = Array.from(new Set(products.map((p) => p.category))).sort();
    if (categoryChips) {
      categoryChips.innerHTML = "";
      const addChip = (label, value) => {
        const chip = document.createElement("button");
        chip.type = "button";
        chip.className = `chip${state.cat === value ? " active" : ""}`;
        chip.dataset.value = value;
        chip.textContent = label;
        chip.addEventListener("click", () => {
          state.cat = value;
          categorySelect.value = value;
          updateQuery();
          setActiveChip(value);
          renderProducts();
        });
        categoryChips.appendChild(chip);
      };
      addChip("All", "all");
      cats.forEach((cat) => addChip(cat, cat));
    }
    cats.forEach((cat) => {
      const opt = document.createElement("option");
      opt.value = cat;
      opt.textContent = cat;
      opt.selected = cat === state.cat;
      categorySelect.appendChild(opt);
    });
  };

  fetch("/data/products.json")
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
      products = data || [];
      populateCategories();
      renderProducts();
      bindFilters();
    })
    .catch((err) => {
      console.error(err);
      emptyState.style.display = "block";
      emptyState.textContent = "Unable to load products right now.";
    });
})();
