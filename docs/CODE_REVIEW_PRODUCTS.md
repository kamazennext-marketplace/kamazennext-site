# Products Page Code Review Notes

## Summary of changes
- Rebuilt `/software.html` into a marketplace layout with a sticky filter bar, popular category chips, cards, and pagination UI.
- Added a dedicated renderer module for filters, sorting, and pagination that keeps URL parameters shareable.
- Updated styling with semi-vibrant light theme tokens plus marketplace-specific layout polish.

## Risk assessment
- **P0: URL params syncing**
  - If query params drift from UI state, share links can render unexpected results.
- **P1: Missing fields in JSON**
  - Products with missing `id`, `slug`, or pricing fields may display incomplete labels or links.
- **P2: Double-render guard**
  - If the guard fails or the script is included twice, duplicate cards can appear.

## QA checklist
- Mobile filter stacking works and avoids horizontal overflow.
- Pagination reflects total results and updates with filters.
- Compare selections persist across reloads.
- View links resolve to `/p/<id>` or `/p/<slug>`.
