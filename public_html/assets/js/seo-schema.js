const BASE_SCHEMA_ID = 'kz-base-schema';
const BREADCRUMBS_SCHEMA_ID = 'kz-breadcrumbs-schema';

const ensureScript = (id, data) => {
  if (!data) return;
  const existing = document.getElementById(id);
  if (existing) {
    existing.remove();
  }
  const script = document.createElement('script');
  script.type = 'application/ld+json';
  script.id = id;
  script.textContent = JSON.stringify(data, null, 2);
  document.head.appendChild(script);
};

export const injectBaseSchema = ({ siteName, siteUrl, logoUrl, sameAs }) => {
  if (!siteName || !siteUrl) return;
  const graph = [];

  const organization = {
    '@type': 'Organization',
    name: 'Kama ZenNext',
    url: siteUrl
  };
  if (logoUrl) organization.logo = logoUrl;
  if (Array.isArray(sameAs) && sameAs.length) organization.sameAs = sameAs;
  graph.push(organization);

  const website = {
    '@type': 'WebSite',
    name: siteName,
    url: siteUrl,
    potentialAction: {
      '@type': 'SearchAction',
      target: `${siteUrl}/software.html?q={search_term_string}`,
      'query-input': 'required name=search_term_string'
    }
  };
  graph.push(website);

  ensureScript(BASE_SCHEMA_ID, {
    '@context': 'https://schema.org',
    '@graph': graph
  });
};

export const injectBreadcrumbs = (items) => {
  if (!Array.isArray(items) || !items.length) return;
  const itemListElement = items.map((item, idx) => ({
    '@type': 'ListItem',
    position: idx + 1,
    name: item.name,
    item: item.url
  }));

  ensureScript(BREADCRUMBS_SCHEMA_ID, {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement
  });
};

const upsertMeta = (name, content, isProperty = false) => {
  if (!content) return;
  const selector = isProperty ? `meta[property="${name}"]` : `meta[name="${name}"]`;
  let tag = document.head.querySelector(selector);
  if (!tag) {
    tag = document.createElement('meta');
    tag.setAttribute(isProperty ? 'property' : 'name', name);
    document.head.appendChild(tag);
  }
  tag.setAttribute('content', content);
};

const setCanonical = (url) => {
  if (!url) return;
  let link = document.head.querySelector('link[rel="canonical"]');
  if (!link) {
    link = document.createElement('link');
    link.setAttribute('rel', 'canonical');
    document.head.appendChild(link);
  }
  link.setAttribute('href', url);
};

export const setMeta = ({
  title,
  description,
  canonicalUrl,
  ogTitle,
  ogDescription,
  ogUrl,
  ogType
}) => {
  if (title) {
    document.title = title;
    const titleTag = document.head.querySelector('title') || document.createElement('title');
    titleTag.textContent = title;
    if (!titleTag.parentElement) document.head.appendChild(titleTag);
  }
  if (description) upsertMeta('description', description);
  if (canonicalUrl) setCanonical(canonicalUrl);

  const resolvedOgTitle = ogTitle || title;
  const resolvedOgDescription = ogDescription || description;
  const resolvedOgUrl = ogUrl || canonicalUrl;
  const resolvedOgType = ogType || 'website';

  if (resolvedOgTitle) upsertMeta('og:title', resolvedOgTitle, true);
  if (resolvedOgDescription) upsertMeta('og:description', resolvedOgDescription, true);
  if (resolvedOgUrl) upsertMeta('og:url', resolvedOgUrl, true);
  if (resolvedOgType) upsertMeta('og:type', resolvedOgType, true);
};
