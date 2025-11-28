# HTL TypeGrid (Drupal Module)

A powerful Drupal 11 module for displaying content in customizable **card grids**. Ideal for creating visually appealing content overviews, news sections, team pages, and more.

- **Core:** Drupal 11
- **License:** MIT
- **Package:** `ftmahringer/htl_typegrid_module`
- **Versioning:** SemVer via Git tags (`vX.Y.Z`)

---

## ✨ Features

### Two Block Types

1. **HTL TypeGrid** – Main grid block with full configuration options
2. **HTL TypeGrid - Pinned** – Displays only pinned content from a source grid

### Layout Options

- **5 Layout Presets:**
  - Standard Grid
  - Compact
  - Hero (first card large)
  - Featured (first card full-width)
  - Horizontal Cards

- **Image Positions:** Top, Left, Right, Bottom, Overlay, or No Image

- **Customizable Styling:**
  - Card spacing (none, small, medium, large, extra large)
  - Card border radius (none, small, medium, large, pill)
  - Configurable columns and rows

### Content Features

- **Pin Content:** Mark specific nodes to appear in the Pinned Grid block
- **Per-Node Image Styles:** Configure how images appear in the grid on a per-node basis
- **Smart Image Detection:** Automatically finds images from image fields or media references
- **Fallback Images:** Uses a dummy image when no image is available

### Sorting & Filtering

- Sort by: Created date, Updated date, Title, Custom field, or Random
- Sort direction: Ascending or Descending

### Field Selection

- Choose which fields to display on cards (up to 3)
- Supports: Images, Media references, Short text, Date/Time fields

---

## 🔧 Requirements

- Drupal 11.x
- PHP 8.2+ (as required by Drupal 11)
- Composer
- Media module (core)
- (Recommended) Drush

---

## 🚀 Installation

> ⚠️ **Important:**
> The repository must be explicitly registered via Composer command.

### 1️⃣ Add VCS Repository

```bash
composer config repositories.ftm vcs https://github.com/FTMahringer/htl_typegrid_module
```

### 2️⃣ Install Module

```bash
# Latest stable 1.x version:
composer require ftmahringer/htl_typegrid_module:^1

# Or a specific version:
# composer require ftmahringer/htl_typegrid_module:v1.2.0
```

> The module will be installed to `web/modules/contrib/htl_typegrid` (standard drupal/recommended-project path).

### 3️⃣ Enable Module

```bash
drush en htl_typegrid -y
drush cr
```

Or via UI:
**Extend → "HTL Typegrid" → Enable → Save → Clear cache**

---

## 🧱 Block Placement

### HTL TypeGrid (Main Block)

1. Navigate to **Structure → Block Layout**
2. Click **Place block** in desired region
3. Select **HTL TypeGrid**
4. Configure:
   - **Content Type:** Select the bundle to display
   - **Layout:** Choose preset, columns, rows, image position, spacing
   - **Filters:** Set sort mode and direction
   - **Fields:** Select which fields to show on cards (via Field Picker)
5. Save

### HTL TypeGrid - Pinned Block

1. Navigate to **Structure → Block Layout**
2. Click **Place block** in desired region
3. Select **HTL TypeGrid - Pinned**
4. Configure:
   - **Source HTL Grid:** Select an existing TypeGrid block to source content from
   - **Layout:** Override layout preset, spacing, and border radius
   - **Fields:** Optionally select specific fields to display (or inherit from source)
5. Save

> **Note:** The Pinned block only displays nodes that have the "Pin for TypeGrid" checkbox enabled.

---

## ⚙️ Configuration

### Block Settings

| Setting | Description | Options |
|---------|-------------|---------|
| Content Type | Node bundle to display | Any content type |
| Columns | Number of grid columns | 1-6 |
| Rows | Number of grid rows | 1-10 |
| Layout Preset | Card layout style | Standard, Compact, Hero, Featured, Horizontal |
| Image Position | Where images appear | Top, Left, Right, Bottom, Overlay, None |
| Card Spacing | Gap between cards | None, Small, Medium, Large, Extra Large |
| Card Radius | Border radius of cards | None, Small, Medium, Large, Pill |
| Sort Mode | How to order content | Created, Updated, Title, Alphabetical, Random |
| Sort Direction | Sort order | Ascending, Descending |

### Per-Node Settings

When a content type is configured for use with HTL TypeGrid, two fields are automatically added:

1. **Pin for TypeGrid** (checkbox) – When enabled, the node appears in Pinned blocks
2. **Grid Image Style** (select) – Choose how the image is styled in the grid

These fields appear in the node edit form and allow content editors to control grid appearance per item.

### Image Selection Priority

The module selects images for cards in this order:

1. **Selected Image Field** – If an image field is chosen via the Field Picker
2. **Auto-Detection** – Scans for the first available:
   - Direct image fields (`type: image`)
   - Media references to media library (`entity_reference` → `media`)
3. **Fallback Dummy Image** – Uses the module's built-in placeholder image

> The fallback image is automatically installed during module installation. Its UUID is stored in `htl_typegrid.settings` (`fallback_media_uuid`).

---

## 🏗️ Architecture

### Services

| Service | Description |
|---------|-------------|
| `htl_grid.query` | Loads and queries nodes for the grid |
| `htl_grid.card_builder` | Builds card render arrays from nodes |
| `htl_grid.config_factory` | Creates GridConfig objects from settings |
| `htl_grid.field_manager` | Manages HTL Grid fields on content types |
| `htl_grid.field_processor` | Extracts and processes field values |
| `htl_grid.field_renderer` | Renders fields for display |
| `htl_grid.image_renderer` | Handles image rendering with styles |
| `htl_grid.meta_builder` | Builds metadata items for cards |
| `htl_grid.block_resolver` | Resolves grid block configurations |
| `htl_grid.cache_helper` | Manages cache tags and contexts |
| `htl_grid.form_builder` | Builds the block configuration form |

### Models

| Model | Description |
|-------|-------------|
| `GridConfig` | Immutable configuration object for a grid |
| `GridFilters` | Filter/sort settings (mode, field, direction) |
| `GridNode` | DTO representing a node in the grid |
| `GridCard` | DTO representing a rendered card |
| `GridFieldValue` | DTO for extracted field values |

### Templates

- `htl-grid-block.html.twig` – Main grid block template
- `htl-grid-pinned-block.html.twig` – Pinned grid block template

---

## 🔄 Updates

```bash
composer update ftmahringer/htl_typegrid_module
drush cr
```

If Composer can't find the package:

```bash
composer clear-cache
composer show -a ftmahringer/htl_typegrid_module
```

---

## 🧪 Development Testing

> For testing only – **not** for production:

```bash
composer require ftmahringer/htl_typegrid_module:dev-main --prefer-source
```

Then switch back to a stable version (`^1`) for production.

---

## ❗ Troubleshooting

| Problem | Solution |
|---------|----------|
| `Package could not be found` | Add repository: `composer config repositories.ftm vcs https://github.com/FTMahringer/htl_typegrid_module` |
| `Invalid version string ^1.x` | Use `^1`, `1.*`, or `v1.2.0` instead |
| `minimum-stability: stable` | Only use stable tags (`v1.2.0`, not `-dev`) |
| Wrong install path | Verify `extra.installer-paths` in your project's `composer.json` |
| Fields not showing | Ensure fields are selected in the Field Picker and are supported types |
| Pinned block empty | Check that nodes have "Pin for TypeGrid" enabled |
| Images not appearing | Verify image fields exist and have content, or check fallback image config |

---

## 🧰 Development & Releases

Releases are automated via GitHub Actions. The commit message or manual trigger determines the version bump:

| Flag | Example | Result |
|------|---------|--------|
| `-upgrade` | `refactor!: API change -upgrade` | **v(X+1).0.0** |
| `-release` | `feat: grid update -release` | **vX.(Y+1).0** |
| `-patch` | `fix: null check -patch` | **vX.Y.(Z+1)** |

The version in `htl_typegrid.info.yml` is automatically synchronized with the Git tag.

---

## 📁 Module Structure

```
htl_typegrid/
├── assets/                  # Fallback dummy image
├── config/                  # Default configuration
├── css/                     # Stylesheets
├── js/                      # JavaScript
├── src/
│   ├── Form/               # Form classes
│   │   └── Section/        # Form section builders
│   ├── Helper/             # Utility helpers
│   ├── Model/              # Data transfer objects
│   ├── Plugin/
│   │   └── Block/          # Block plugins
│   └── Service/            # Service classes
├── templates/              # Twig templates
├── htl_typegrid.info.yml   # Module definition
├── htl_typegrid.install    # Install/update hooks
├── htl_typegrid.libraries.yml
├── htl_typegrid.module     # Hook implementations
├── htl_typegrid.routing.yml
└── htl_typegrid.services.yml
```

---

## 📄 License

MIT – see `LICENSE`.

---

## 🙋 Support

Issues and suggestions via GitHub:
➡️ [FTMahringer/htl_typegrid_module](https://github.com/FTMahringer/htl_typegrid_module)