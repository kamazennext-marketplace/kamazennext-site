export async function loadProducts() {
  const res = await fetch('/data/products.json');
  if (!res.ok) throw new Error('Failed to load products');
  return res.json();
}

export function getCategories(products = []) {
  const map = new Map();
  products.forEach((p) => {
    if (!p?.category) return;
    const count = map.get(p.category) || 0;
    map.set(p.category, count + 1);
  });
  return Array.from(map.entries())
    .map(([name, count]) => ({ name, count }))
    .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name));
}

export function getTrending(products = []) {
  return [...products]
    .sort((a, b) => new Date(b.last_updated) - new Date(a.last_updated))
    .slice(0, 8);
}

export function groupByCategory(products = []) {
  return products.reduce((acc, product) => {
    if (!product?.category) return acc;
    if (!acc[product.category]) acc[product.category] = [];
    acc[product.category].push(product);
    return acc;
  }, {});
}

export function pickBestInCategory(products = [], categoryName, n = 3) {
  return products
    .filter((p) => p.category === categoryName)
    .sort((a, b) => new Date(b.last_updated) - new Date(a.last_updated))
    .slice(0, n);
}

const slugify = (value = '') =>
  value
    .toString()
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)+/g, '');

export const urlForCategory = (name) => `/category/${slugify(name)}`;
export const urlForProduct = (id) => `/p/${encodeURIComponent(id || '')}`;
