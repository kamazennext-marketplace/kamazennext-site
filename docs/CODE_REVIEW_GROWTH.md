# Growth Automation Pages — Code Review Notes

## Summary of changes
- Added audience tagging support to `/data/products.json` (optional, backward compatible) and seeded automation comparison entries for Zapier, Make, and n8n.
- Added audience chips to `/software.html` plus filtering logic in `/assets/js/software-page.js`.
- Introduced new SEO landing templates: `/best/page.html` with `/assets/js/best-page.js`, and `/compare/page.html` with `/assets/js/compare-page.js`.
- Updated `/best/index.html` to surface the new best pages.
- Added rewrite rules in `/public_html/.htaccess` for `/best/*` and `/compare/*` slugs.

## Review comment
- `audience` is used only for UI grouping + filters; it is never required for rendering.

## Risks
- **Missing slugs in include list:** best pages will fallback to category filters, but missing products could reduce relevance.
- **Schema correctness:** JSON-LD must remain valid when FAQs or items are empty.
- **Rewrite conflicts:** ensure existing file/dir bypass stays above new rewrite rules.

## QA checklist
- `/best/zapier-alternatives` loads and renders.
- `/best/n8n-alternatives` loads and renders.
- `/best/developer-automation-tools` loads and renders.
- `/compare/zapier-vs-make` renders A/B properly.
- `/software.html?aud=business` audience filter works and URL updates.
- `/software.html?aud=builder` audience filter works and URL updates.

## Deployment note
- Do NOT deploy the `docs/` folder to `public_html`.
