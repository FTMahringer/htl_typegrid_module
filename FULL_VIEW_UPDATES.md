# Full View Updates - HTL TypeGrid Module

## Changes Made

### 1. New Admin Settings (Admin → Configuration → Content authoring → HTL-Typegrid)

#### View Pages Section
- **Enable view pages for**: Existing - enables `/typegrid/{bundle}` route
- **Show filters on full view pages for**: NEW - per-bundle control of filter visibility
  - Check bundles where you want the filter bar visible
  - If none checked, falls back to global "Show filters" setting

#### View Page Settings Section
- **Items per page**: Existing - pagination size
- **Default view mode**: Existing - grid or list
- **Full view: cards per row**: NEW - controls 3-6 columns for full view grid
- **Show filters**: Existing global fallback
- **Show grid/list toggle**: Existing

### 2. Controller Changes (GridViewController.php)

**Per-bundle filter visibility logic:**
```php
$globalShowFilters = $config->get('show_filters') ?? TRUE;
$filtersEnabledBundles = $config->get('filters_enabled_bundles') ?? [];

if (!empty($filtersEnabledBundles)) {
    // Use per-bundle setting
    $showFilters = in_array($bundle, $filtersEnabledBundles, TRUE);
} else {
    // Fall back to global
    $showFilters = $globalShowFilters;
}
```

**Full view columns:**
```php
$fullViewColumns = (int) ($config->get('full_view_columns') ?? 3);
// Clamped to 3-6 range
```

### 3. Template Changes (htl-grid-view-page.html.twig)

**Initialize variable at top:**
```twig
{% set full_view_columns = full_view_columns|default(3) %}
```

**Use in grid container:**
```twig
<div class="..." style="--cols: {{ current_view_mode == 'list' ? 1 : full_view_columns }}; --ft-grid-cols: {{ current_view_mode == 'list' ? 1 : full_view_columns }};">
```

### 4. Config Schema (htl_typegrid.schema.yml)

Added schema definitions for:
- `full_view_columns` (integer)
- `filters_enabled_bundles` (sequence of strings)
- All other existing settings now properly defined

## Testing Instructions

### Step 1: Clear Drupal Cache
You MUST clear cache for changes to take effect:

**Option A - Via Drupal Admin UI:**
1. Go to: Admin → Configuration → Development → Performance
2. Click "Clear all caches"

**Option B - Via Drush (if available):**
```bash
cd E:\drupal_stuff\Drupal_HTL_Website_Spielwiese\drupalData
vendor\bin\drush cr
```

**Option C - Via PHP directly:**
```bash
cd E:\drupal_stuff\Drupal_HTL_Website_Spielwiese\drupalData\web
php core/rebuild.php
```

### Step 2: Configure Settings

1. Go to: **Admin → Configuration → Content authoring → HTL-Typegrid**

2. Under **View Pages**:
   - Ensure your content type is checked under "Enable view pages for"
   - Check/uncheck bundles under "Show filters on full view pages for"
   
3. Under **View Page Settings**:
   - Set "Full view: cards per row" to a value between 3-6
   - Try different values (3, 4, 5, 6) to see the layout change

4. Save configuration

### Step 3: Test Full View Page

1. Visit: `/typegrid/{your_bundle_name}`
   - Example: `/typegrid/article` or `/typegrid/page`

2. **Test filter visibility:**
   - If bundle is checked in "Show filters on full view pages for": filters should appear
   - If unchecked: filters should be hidden
   - If "Show filters on full view pages for" is empty: falls back to global "Show filters" checkbox

3. **Test cards per row:**
   - In grid view, count cards in a row
   - Should match your "Full view: cards per row" setting (3-6)
   - Switch to list view - should always show 1 column
   - Switch back to grid - should show your configured columns

4. **Test existing features (should still work):**
   - Search filter
   - Sort dropdown (created, changed, title)
   - Direction dropdown (ASC/DESC or "Newest first"/"Oldest first")
   - Date filters (should show native browser calendar picker)
   - Taxonomy filters (if available)
   - Pagination
   - Grid/List toggle

## Troubleshooting

### Filters not showing/hiding correctly:
1. Clear cache again
2. Check both:
   - "Show filters on full view pages for" (per-bundle)
   - "Show filters" global checkbox (fallback)
3. If "Show filters on full view pages for" has ANY bundles checked, only those will show filters

### Cards per row not changing:
1. Clear cache
2. Verify you're looking at the full view page (`/typegrid/{bundle}`)
   - NOT a block on another page
3. Check browser DevTools → Elements:
   - Look for `<div class="htl-grid ...">` 
   - Should have style attribute: `--cols: 4` (or your number)
4. If still 3 columns:
   - Check that `full_view_columns` is actually saved in config
   - Check browser console for JavaScript errors
   - Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)

### "Page not found" on /typegrid/{bundle}:
1. Bundle must be checked under "Enable view pages for"
2. Clear cache
3. Clear routing cache specifically:
   - Admin → Configuration → Development → Performance → Clear all caches

## Database Check (Advanced)

To verify settings are actually saved:

```sql
SELECT * FROM config WHERE name = 'htl_typegrid.settings';
```

The `data` column (blob) should contain serialized config including:
- `full_view_columns: 3` (or your value)
- `filters_enabled_bundles: [...]`

## Files Modified

1. `src/Form/TypeGridSettingsForm.php` - Added form fields and submit handling
2. `src/Controller/GridViewController.php` - Added per-bundle filter logic and full_view_columns
3. `templates/htl-grid-view-page.html.twig` - Fixed structure, use full_view_columns
4. `config/schema/htl_typegrid.schema.yml` - Added schema for new settings

## Rollback (if needed)

If something breaks:

1. The global "Show filters" checkbox still works as before
2. Set "Full view: cards per row" back to 3 (default)
3. Leave "Show filters on full view pages for" empty (uses global setting)
4. Clear cache

This restores original behavior while keeping the code in place.