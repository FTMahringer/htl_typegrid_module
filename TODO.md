# HTL TypeGrid - Feature Roadmap & TODO

This document tracks planned features, completed work, and future ideas for the HTL TypeGrid module.

---

## ✅ Phase 1: Visual Foundation (COMPLETED)

| Feature | Status | Description |
|---------|--------|-------------|
| Card Layout Presets | ✅ Done | Standard, Compact, Hero, Featured, Horizontal |
| Image Position | ✅ Done | Top, Left, Right, Bottom, Overlay, None |
| Card Spacing | ✅ Done | None, Small, Medium, Large, XLarge |
| Card Border Radius | ✅ Done | None, Small, Medium, Large, Pill |
| Dark Mode Support | ✅ Done | CSS automatically adapts to dark mode |
| Responsive Design | ✅ Done | Graceful adaptation to mobile |
| Motion Reduction | ✅ Done | Respects prefers-reduced-motion |

---

## ✅ Phase 2: Filtering & Pinning (COMPLETED)

| Feature | Status | Description |
|---------|--------|-------------|
| Pin for TypeGrid Field | ✅ Done | Checkbox field added to content types |
| Pinned Block | ✅ Done | Separate "HTL TypeGrid - Pinned" block |
| Source Grid Selection | ✅ Done | Pinned block selects from existing HTL Grid blocks |
| Pinned Label | ✅ Done | Yellow "Pinned" label shown in main grid |
| Pinned Field Selection | ✅ Done | Images, short text, datetime only |
| Image Style Field | ✅ Done | Per-node image style selection |
| Fallback Dummy Image | ✅ Done | Automatic fallback when no image exists |

---

## ✅ Phase 3: Advanced Layout (COMPLETED)

| Feature | Status | Description |
|---------|--------|-------------|
| Taxonomy Filtering | ✅ Done | Filter grid content by taxonomy terms on View page |
| Card Size Variations | ✅ Done | Hero (2x2) and Featured (full-width) presets with proper cell calculation |
| List/Grid Toggle | ✅ Done | Frontend button on View page to switch between grid and list view |
| Date Range Filter | ✅ Done | Filter content by date range on View page |
| Exclude Specific Nodes | ✅ Done | "Show in TypeGrid" checkbox field - only checked nodes appear in grid |

---

## ✅ Phase 4: Views & API (COMPLETED)

| Feature | Status | Description |
|---------|--------|-------------|
| View Page | ✅ Done | Full view page at `/typegrid/{bundle}` with filters and pagination |
| AJAX Load More | ✅ Done | Button to load additional cards without page reload (JS ready) |
| Pager Integration | ✅ Done | Traditional pagination with page numbers |
| REST API Endpoint | ✅ Done | Full CRUD API at `/api/typegrid/{bundle}` |
| "View All" Link | ✅ Done | Auto-generated link in main grid block to view page |

---

## 📡 REST API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/typegrid` | List all bundles with TypeGrid configured |
| GET | `/api/typegrid/{bundle}` | List nodes with filtering & pagination |
| GET | `/api/typegrid/{bundle}/{nid}` | Get a single node |
| POST | `/api/typegrid/{bundle}` | Create a new node |
| PATCH | `/api/typegrid/{bundle}/{nid}` | Update an existing node |
| DELETE | `/api/typegrid/{bundle}/{nid}` | Delete a node |

### API Query Parameters (GET list)
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 12, max: 100)
- `sort` - Sort field (created, changed, title)
- `direction` - Sort direction (ASC, DESC)
- `search` - Search in title
- `date_from` - Filter by created date (YYYY-MM-DD)
- `date_to` - Filter by created date (YYYY-MM-DD)
- `taxonomy[field_name]` - Filter by taxonomy term ID
- `format` - Response format (full, minimal, ids)

---

## 💡 Future Ideas (BACKLOG)

| Feature | Priority | Description |
|---------|----------|-------------|
| Custom View on Install | Medium | Create a pre-configured Drupal View when module is installed |
| Infinite Scroll | Low | Automatically load more as user scrolls |
| WebP Auto-conversion | Low | Custom image style or leverage Drupal's built-in WebP support |
| Live Preview | Medium | AJAX preview in block configuration form |
| Drag & Drop Field Ordering | Medium | Reorder meta fields visually |
| Field Label Customization | Low | Custom labels for each displayed field |
| Conditional Field Display | Low | Show field only if another field has value |
| Schema.org Markup | Low | Add structured data for better SEO |
| Print Styles | Low | Optimized CSS for printing |
| Color Themes | Low | Light/dark/custom color scheme options |
| Title Truncation | Low | Max characters for title with ellipsis |
| Read Time Estimate | Low | Calculate and display estimated read time |
| Content Preview Popup | Low | Show preview tooltip on card hover |
| Skeleton Loading | Medium | Placeholder skeleton while loading |
| Card Flip Effect | Low | Show more info on card hover/click |
| Lightbox Support | Low | Open full content in a modal |
| Entrance Animations | Low | Fade-in, slide-up animations on scroll |

---

## 🛠️ Technical Debt & Improvements

| Task | Priority | Description |
|------|----------|-------------|
| Remove Debug Logging | Medium | Clean up `\Drupal::logger()` calls used during development |
| Add PHPUnit Tests | Medium | Unit tests for services and models |
| Add Functional Tests | Low | Browser tests for block rendering |
| Documentation | Medium | Inline code documentation and README updates |
| Config Schema Validation | Low | Ensure all config has proper schema definitions |

---

## 📝 Notes

### New Files Added (Phase 3 & 4)
- `src/Controller/GridViewController.php` - View page controller
- `src/Controller/GridApiController.php` - REST API controller
- `templates/htl-grid-view-page.html.twig` - View page template
- `templates/htl-grid-card.html.twig` - Standalone card template for AJAX
- `css/htl-view-page.css` - View page styles
- `js/htl-view-page.js` - View page JavaScript

### New Routes
- `htl_typegrid.view` - `/typegrid/{bundle}` - Main view page
- `htl_typegrid.view.ajax` - `/typegrid/{bundle}/ajax` - AJAX endpoint
- `htl_typegrid.api.*` - `/api/typegrid/*` - REST API endpoints

### Implementation Notes
- View page supports both traditional pagination and AJAX load more
- Grid/List toggle updates URL state without page reload
- REST API uses CSRF tokens for write operations
- "Show in TypeGrid" field defaults to TRUE for backwards compatibility
- Hero cards consume 4 grid cells (2x2), Featured cards consume full row
- Grid now limits by cell count, not card count

---

*Last updated: 2025*
