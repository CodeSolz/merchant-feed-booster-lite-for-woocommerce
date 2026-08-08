# Merchant Feed Booster Lite for WooCommerce

**WordPress plugin slug:** `merchant-feed-booster-lite-for-woocommerce`  
**Requires:** WordPress 5.8+, WooCommerce 6.0+, PHP 7.4+  
**License:** GPL-2.0-or-later

---

## What does this plugin do?

Most Google Shopping feed plugins just export an XML file and leave you guessing why your products are disapproved or getting low impressions. **Merchant Feed Booster Lite** fixes that.

It does two things:

1. **Generates a reliable Google Merchant XML feed** for your WooCommerce store — automatically, on a schedule, written safely to disk.
2. **Audits every product against 27 Google Merchant Center policy rules**, gives each product a health score from 0 to 100, and tells you exactly what to fix and why — before Google rejects it.

The result: you upload a feed that actually passes, and you know which products to fix first.

---

## The problem it solves

You publish your WooCommerce products to Google Shopping. Days later, some products are disapproved. The rejection messages are vague. You have no way to know which product violates which rule, or what to change.

This plugin scans your entire catalog and surfaces those problems upfront — in your WordPress admin — before they reach Google. Each issue comes with a specific rule ID, a plain-English explanation, and a fix hint.

---

## Features

### 1. Google Merchant XML Feed

The plugin writes a properly formatted RSS 2.0 feed with the `g:` Google namespace to:

```
wp-content/uploads/codesolz-feeds/google-products.xml
```

The feed includes all required and recommended fields:

| Field | Source |
|---|---|
| `g:id` | WooCommerce product ID / SKU |
| `g:title` | Product name (with optional prefix) |
| `g:description` | Product description |
| `g:link` | Product permalink |
| `g:image_link` | Featured image URL |
| `g:price` / `g:sale_price` | Regular and sale price with currency |
| `g:availability` | In stock / out of stock |
| `g:brand` | Per-product field or store default |
| `g:gtin` | Per-product GTIN (validated) |
| `g:mpn` | Per-product MPN |
| `g:google_product_category` | Per-product or store default |
| `g:condition` | new / refurbished / used |
| `g:identifier_exists` | Auto-set based on brand + GTIN/MPN |

**Feed reliability features:**
- Atomic file write — the live feed is never half-written during regeneration; the plugin writes to a `.tmp` file first, then renames it
- WP-Cron auto-refresh on your chosen schedule: twice daily, daily, weekly, or a custom interval in hours
- Manual regeneration button on the Dashboard with live product count feedback
- Feed URL shown on the Dashboard with one-click copy for pasting into Google Merchant Center

---

### 2. Per-product Google Fields

New fields are added to every WooCommerce product edit screen (General tab):

| Field | What it does |
|---|---|
| **Brand** | Manufacturer or brand name, sent as `g:brand` |
| **GTIN** | Barcode / EAN / UPC. Non-numeric characters stripped on save. Validated against the GS1 check digit algorithm — shows a warning if the number is structurally invalid, without blocking the save |
| **MPN** | Manufacturer Part Number, used when GTIN is not available |
| **Google Product Category** | Full Google taxonomy path, e.g. `Apparel & Accessories > Clothing` |
| **Condition** | new, refurbished, or used |

Store-wide defaults for brand and Google category can be set in Settings and are used as fallbacks when a product has no value set.

---

### 3. Feed Health — 27 Policy Rules

Every product in your catalog is scored from **0 to 100** and checked against 27 named rules that mirror Google Merchant Center's content requirements.

#### Title rules (T01–T09)
| Rule | What it catches |
|---|---|
| T01 | Title shorter than 25 characters — too short for meaningful ad targeting |
| T02 | Title longer than 150 characters — Google truncates these in ads |
| T03 | Promotional words in title (FREE, SALE, % OFF, etc.) — policy violation |
| T04 | ALL CAPS words — policy violation |
| T05 | HTML tags in title (e.g. `<br>`, `<b>`) — breaks feed parsing |
| T06 | Price mentioned in the title — not allowed by Google |
| T07 | Special characters at the start or end of the title |
| T08 | Repeated words in the title |
| T09 | Same title used by another product in the store — duplicate content |

#### Image rules (I01–I05)
| Rule | What it catches |
|---|---|
| I01 | No product image set |
| I02 | Image smaller than 100×100 px — Google rejects these outright |
| I03 | Image smaller than 250×250 px — Google warns; lower ad quality |
| I04 | Non-standard image format (not JPEG/PNG/GIF/WebP) |
| I05 | Tracking parameters in the image URL (e.g. `?utm_source=`) |

#### Price rules (P01–P04)
| Rule | What it catches |
|---|---|
| P01 | Missing or zero price |
| P02 | Sale price set but no regular price — Google requires both |
| P03 | Sale price is equal to or greater than the regular price |
| P04 | Non-numeric price value |

#### Identifier rules (ID01–ID05)
| Rule | What it catches |
|---|---|
| ID01 | GTIN has wrong digit count (valid lengths: 8, 12, 13, 14) |
| ID02 | GTIN fails the GS1 check digit algorithm — structurally invalid |
| ID03 | Brand is set but neither GTIN nor MPN is provided |
| ID04 | No brand set — required for most product categories |
| ID05 | GTIN or MPN already used by another product in the store |

#### Description rules (D01–D04)
| Rule | What it catches |
|---|---|
| D01 | No product description |
| D02 | Description shorter than 50 characters — too thin for Google |
| D03 | HTML tags present in the description |
| D04 | Promotional language in the description (FREE, SALE, etc.) |

#### Health score tiers

| Score | Tier | Meaning |
|---|---|---|
| 85–100 | Excellent (green) | Product meets all or nearly all Google requirements |
| 70–84 | Good (blue) | Minor issues; product will likely be approved |
| 50–69 | Needs Work (amber) | Several issues; approval risk is moderate |
| 30–49 | Poor (orange) | Major issues; product is likely to underperform |
| 0–29 | Critical (red) | Severe violations; product will probably be disapproved |

Scores are **cached per product** using an MD5 hash of the key fields (title, price, image, brand, GTIN, modified date). The score reloads instantly from cache if nothing changed, and is automatically recalculated the moment a relevant field is updated.

---

### 4. Admin Pages

#### Dashboard
The main overview page shows:
- Store-wide average health score as an animated circular gauge
- Feed status (active / inactive) and last generation time
- Feed URL with one-click copy button
- Stats: products in feed, products scanned, error count, warning count
- Feed quality breakdown bars per category (Title, Image, Price, Identifiers, Description)
- Score distribution chart showing how many products fall in each tier
- 5 lowest-scored products that need immediate attention
- Top issues to fix, ranked by how many products they affect
- Recent activity log

#### Feed Health
A full product table showing every product's health:
- Score badge (color-coded by tier) next to each product name
- Filter tabs to narrow to: All Products, Issues Only, Title issues, Image issues, Price issues, Identifier issues, Description issues
- Expandable detail row per product showing every failing rule, its severity (Error / Warning / Notice), the exact message, and a specific fix hint
- Background scan with a real-time progress bar — scans 50 products per batch so it never times out on large catalogs
- Export to CSV: one row per product, one column per rule ID, for spreadsheet analysis
- Clear Cache button to force a full rescan

#### Feed Preview
A live table view of your feed items as they will appear in the XML file:
- One row per product, one column per feed field (Title, Image, Price, Sale Price, Brand, GTIN, MPN, Availability, Condition, Description)
- Cells highlighted red / amber / blue when a policy rule affects that specific field
- Score gauge in the first column
- Pagination (configurable rows per page)

#### Per-product Meta Box
When editing any WooCommerce product, a "Feed Health Score" box appears in the sidebar showing:
- The product's current score as a color-coded badge
- Up to 5 failing rules with severity and rule ID
- A link to the full Feed Health report

---

### 5. Settings

Found at **Feed Booster → Settings**:

| Setting | Description |
|---|---|
| Enable feed | Master on/off switch. Disables cron and manual regeneration when off. |
| Feed title | The `<title>` element of the RSS feed |
| Product title prefix | Text prepended to every product title in the feed (e.g. your brand name) |
| Default brand | Fallback brand for products that have no brand field filled in |
| Default Google product category | Fallback Google taxonomy path for uncategorized products |
| Include out-of-stock products | When enabled, OOS products are included with `g:availability` set to `out_of_stock` |
| Auto-refresh frequency | Twice daily / daily / weekly / or a custom interval in hours |
| Schedule time | Preferred time of day for the daily refresh |
| Enforce GTIN / MPN | Show warnings for products missing both identifiers |
| Validate image dimensions | Check image sizes against Google's minimum requirements |

---

### 6. WP-CLI Commands

For server-side management and scripting:

```bash
# Regenerate the feed from the command line
wp cs-mfb generate

# List all products with their health scores and issue counts
wp cs-mfb health
wp cs-mfb health --limit=100 --format=csv

# Show score and all issues for a specific product
wp cs-mfb score 42

# Clear all cached scores (next scan will rescore everything)
wp cs-mfb clear-cache
```

---

### 7. REST API

Used internally by the admin UI's background scan progress bar. All endpoints require the `manage_woocommerce` capability and a valid WP REST nonce.

| Endpoint | Method | Purpose |
|---|---|---|
| `/wp-json/cs-mfb/v1/scan/start` | GET | Start a background batch scan |
| `/wp-json/cs-mfb/v1/scan/progress` | GET | Poll current scan progress (0–100%) |
| `/wp-json/cs-mfb/v1/product/{id}/score` | GET | Fetch score and issues for one product |
| `/wp-json/cs-mfb/v1/feed/regenerate` | POST | Trigger feed regeneration |

---

## Installation

1. Upload the `merchant-feed-booster-lite-for-woocommerce` folder to `wp-content/plugins/`
2. Activate **Merchant Feed Booster Lite for WooCommerce** via **Plugins → Installed Plugins**
3. WooCommerce must be installed and active — the plugin checks for this on activation
4. Go to **Feed Booster → Settings**, set your feed title and defaults, and save
5. Click **Regenerate Feed** on the Dashboard to generate the first XML file
6. Copy the feed URL from the Dashboard and add it to Google Merchant Center under **Settings → Data sources → Add file** as a scheduled fetch

---

## Security

- All admin pages, `admin-post` handlers, and REST endpoints verify the `manage_woocommerce` capability
- Feed regeneration, CSV export, and cache clearing are protected by WordPress nonces
- All HTML output is escaped with `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`
- All user input is sanitized on entry with `sanitize_text_field()`, `sanitize_key()`, `absint()`, etc.
- The feed file is written atomically (`.tmp` → rename), so the live file is never corrupted mid-write
- The feed directory is protected with an `index.html` to prevent directory listing
- GTIN is stripped to digits only on save; the GS1 check digit is validated and warns (but does not block)

---

## Developer Hooks

### Filters

| Filter | Parameters | Description |
|---|---|---|
| `cs_mfb_required_capability` | `$cap` | Change the required admin capability (default: `manage_woocommerce`) |
| `cs_mfb_should_include_product` | `$include, $product, $settings` | Return `false` to exclude a product from the feed |
| `cs_mfb_item_data` | `$data, $product, $settings` | Modify any feed field before it is written to XML |
| `cs_mfb_extra_item_fields` | `$fields, $product, $settings` | Add extra `g:*` fields to a feed item |
| `cs_mfb_policy_rules` | `$rules, $product` | Add, remove, or modify the 27 policy rules |
| `cs_mfb_score_weights` | `$weights` | Adjust the weight each rule contributes to the 0–100 score |
| `cs_mfb_health_table_columns` | `$columns` | Add extra columns to the Feed Health table |
| `cs_mfb_include_hidden` | `$bool, $product` | Include catalog-hidden products in the feed (default: false) |

### Actions

| Action | Parameters | Description |
|---|---|---|
| `cs_mfb_ready` | — | Fires after all subsystems are initialized |
| `cs_mfb_register_admin_pages` | — | Fires inside `admin_menu` — use to add sub-pages under Feed Booster |
| `cs_mfb_feed_generated` | `$channel, $written, $skipped, $file` | Fires after the XML feed file is written |
| `cs_mfb_before_item_written` | `$item_data, $product` | Fires before each product item is serialized |
| `cs_mfb_after_product_scored` | `$product_id, $score, $issues` | Fires after a product is scored |
| `cs_mfb_after_health_table` | — | Fires after the Feed Health table is rendered |

---

## Releasing a New Version

1. Bump `Version:` in `merchant-feed-booster-lite.php` and `Stable tag:` in `readme.txt`
2. Add a changelog entry under `== Changelog ==` in `readme.txt`
3. Create a `.release-version` file in the repo root:
   ```
   1.0.1|release: v1.0.1 - brief description
   ```
4. Push to the `lite` branch — GitHub Actions will automatically:
   - Build and package the ZIP + TAR.GZ
   - Run a PHP error check against a live WordPress + WooCommerce environment
   - Deploy to the production GitHub repository and create a tagged GitHub release
   - Commit the new version to the WordPress.org SVN (`/trunk` + `/tags/{version}`)

**Required GitHub repository secrets:**

| Secret | Purpose |
|---|---|
| `PROD_PUSH_TOKEN` | Personal access token with write access to the production repo |
| `WP_SVN_USERNAME` | WordPress.org SVN username |
| `WP_SVN_PASSWORD` | WordPress.org SVN password |

---

## File Layout

```
merchant-feed-booster-lite-for-woocommerce/
├── merchant-feed-booster-lite.php       # Plugin header, constants, requires
├── uninstall.php                        # Runs on plugin deletion
├── readme.txt                           # WordPress.org directory readme
├── README.md                            # This file
├── assets/
│   ├── admin.css                        # Admin UI styles
│   ├── admin.js                         # Dashboard, scan progress, settings JS
│   ├── cs-mfb-swal.css                  # Bundled SweetAlert2 styles
│   └── cs-mfb-swal.js                   # Bundled SweetAlert2 (no CDN)
├── languages/
│   └── index.php                        # Silence is golden
└── includes/
    ├── class-cs-mfb-plugin.php          # Boot, activation hook, DB table creation
    ├── class-cs-mfb-settings.php        # Options read/write, defaults, cron schedules
    ├── class-cs-mfb-admin.php           # Admin menus, page rendering, meta box
    ├── class-cs-mfb-product-fields.php  # Brand/GTIN/MPN/category/condition meta fields
    ├── class-cs-mfb-gtin-validator.php  # GS1 check digit (Luhn-based) validation
    ├── class-cs-mfb-image-checker.php   # Image dimension fetching and caching
    ├── class-cs-mfb-policy-engine.php   # All 27 named policy rules
    ├── class-cs-mfb-health-score.php    # 0–100 scoring, tier labels, audit cache table
    ├── class-cs-mfb-health.php          # Scan orchestration, report building, CSV export
    ├── class-cs-mfb-scan-runner.php     # Background batch scanning via transients
    ├── class-cs-mfb-rest-api.php        # REST endpoints consumed by admin JS
    ├── class-cs-mfb-feed-generator.php  # XML feed writer with atomic file swap
    ├── class-cs-mfb-feed-preview.php    # Feed preview page data and field-to-rule mapping
    ├── class-cs-mfb-cron.php            # WP-Cron event registration and scheduling
    └── class-cs-mfb-cli.php             # WP-CLI commands (loaded only when WP_CLI defined)
```

---

## License

GPL-2.0-or-later — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
