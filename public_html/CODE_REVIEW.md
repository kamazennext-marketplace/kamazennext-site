# Code Review - /public_html

## Summary
**What’s good**
- Pages already include viewport meta and a consistent CSS pipeline that supports responsive layout.
- Contact API includes validation, a honeypot, and basic rate limiting.

**What’s risky**
- Public diagnostic/test endpoints were exposed (probe/contact test), which can leak environment info.
- Asset references pointed at files missing from `/public_html`, causing plain-text rendering and console errors.
- Category slug routes were not rewritten, so `/category/<slug>` could fail depending on the server.

## Priority fixes
**P0**
- Block public access to diagnostic/test endpoints and prevent directory listing.

**P1**
- Ship the referenced CSS/JS assets in `/public_html/assets` so pages don’t render without styling or throw JS errors.
- Add rewrite rules so `/category/<slug>` resolves to the category template.

**P2**
- Reduce information disclosure on health checks (e.g., remove PHP version).

## Security checklist
- [x] Keys are not hard-coded in `/public_html`.
- [x] Admin surfaces are protected or not present in `/public_html`.
- [x] Backups/data directories are not publicly listed (directory listing disabled).
- [x] Mixed content (http://) avoided.
- [ ] Security headers configured (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy).

## Performance checklist
- [x] CSS/JS loaded from local assets (no missing files).
- [ ] CSS/JS loading optimized (preload critical CSS, defer non-critical scripts).
- [ ] Cache headers configured for static assets.
- [ ] Image sizes optimized and responsive variants provided.

## SEO checklist
- [x] Canonical present on category page.
- [ ] Robots.txt verified for production crawl policy.
- [ ] Sitemap links validated (XML served with correct content type).
- [ ] Structured data (schema.org) present on key pages.
- [ ] 404 handling verified.

## Mobile UX checklist
- [x] Viewport meta included.
- [ ] Header overflow checked for narrow screens.
- [ ] Spacing verified for tap targets (>=44px).
- [ ] Forms and inputs tested for mobile keyboards.
