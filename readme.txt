=== Merchant Feed Booster Lite for WooCommerce ===
Contributors: codesolz, m.tuhin
Tags: google shopping, woocommerce, product feed, merchant center, google merchant feed
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate a Google Merchant XML feed for WooCommerce and find out exactly why your products are being rejected — before Google does.

== Description ==

You submit your WooCommerce products to Google Shopping. A few days later, some are disapproved. The error messages are vague. You don't know which product has which problem, or what to change.

**Merchant Feed Booster Lite** solves that.

It generates a reliable Google Merchant XML feed for your store *and* audits every product against 27 of Google Merchant Center's content policy rules. Each product gets a health score from 0 to 100, and every failing rule tells you exactly what the problem is and how to fix it — all inside your WordPress admin, before a single product reaches Google.

= How it works =

1. **Set it up once** — enter your feed title, default brand, and Google product category in Settings. The plugin adds Brand, GTIN, MPN, and Condition fields to each product edit screen.
2. **Generate your feed** — click Regenerate Feed on the Dashboard. The plugin writes a properly formatted Google Merchant XML file to your server. Copy the URL and paste it into Google Merchant Center as a scheduled fetch.
3. **Run a health scan** — go to Feed Health and scan your catalog. The plugin checks every product against 25 policy rules covering titles, images, prices, identifiers, and descriptions.
4. **Fix what's flagged** — each failing rule shows a severity level (Error, Warning, or Notice), a plain-English explanation, and a specific fix hint. Start with the lowest-scored products first.
5. **Let it run** — the feed refreshes automatically on a schedule you choose. Products are rescored automatically whenever you edit them.

= What it checks =

**Titles** — Too short or too long, promotional words like FREE or SALE, ALL CAPS, HTML tags, price mentioned in the title, special characters, repeated words, duplicate title reused on another product.

**Images** — Missing image, too small for Google's requirements, unsupported format, tracking parameters in the URL.

**Prices** — Missing or zero price, sale price set without a regular price, sale price higher than or equal to the regular price.

**Identifiers** — Invalid GTIN digit count, GTIN that fails the GS1 check digit test, brand set but no GTIN or MPN provided, missing brand, duplicate GTIN/MPN reused across products.

**Descriptions** — Missing description, too short, HTML tags, promotional language.

= What you get =

* A health score (0–100) and color-coded tier for every product in your store
* A full breakdown of every failing rule per product with fix hints
* A live feed preview showing your products exactly as they'll appear in the XML
* Automatic feed refresh on a schedule — twice daily, daily, weekly, or a custom interval
* A real reachability check on your feed URL, and a warning if the scheduled refresh stops firing
* A Feed Health column (sortable, filterable) right in your WooCommerce Products list
* A Site Health test so feed problems surface alongside your other WordPress health checks
* CSV export of the full health report for spreadsheet analysis
* WP-CLI commands for server-side management
* Developer filters and actions to customise the feed and scoring rules

= Privacy =

This plugin does not collect or transmit any data to third parties, and there is no tracking or telemetry of any kind. All scoring and feed generation runs on your own server. The one network request the plugin makes is a same-origin check of your own feed URL, to confirm it's reachable before Google tries to fetch it — no data is sent to any external service.

== Installation ==

1. Upload the `merchant-feed-booster-lite-for-woocommerce` folder to `/wp-content/plugins/`.
2. Activate **Merchant Feed Booster Lite for WooCommerce** from the Plugins screen.
3. WooCommerce must be installed and active.
4. Go to **Feed Booster → Settings**, configure your feed title and defaults, and save.
5. Click **Regenerate Feed** on the Dashboard to create the first XML file.
6. Copy the feed URL and add it to Google Merchant Center under **Settings → Data sources → Add file**.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. WooCommerce 6.0 or higher must be installed and active.

= Where is the feed file stored? =

At `wp-content/uploads/codesolz-feeds/google-products.xml`. The URL is shown on the Dashboard with a one-click copy button.

= How often does the feed refresh? =

You choose: twice daily, daily, weekly, or a custom interval in hours. You can also regenerate it manually at any time from the Dashboard.

= Do I have to scan my products manually every time? =

No. Products are rescored automatically when you save changes to them. For a full catalog rescan, use the Scan button on the Feed Health page — it runs in the background in batches of 50, so it never times out on large stores.

= What does the health score mean? =

It tells you how well a product meets Google's content requirements. A score of 85–100 means the product is in good shape. Below 50 means there are issues that are likely to cause disapprovals or low impressions. The Feed Health page shows exactly which rules are failing.

= Is it compatible with WooCommerce HPOS? =

Yes. The plugin only reads and writes product data, never orders. Full HPOS compatibility is declared.

= Can I control which products go into the feed? =

Yes, via the `cs_mfb_should_include_product` filter hook. You can also toggle out-of-stock products on or off in Settings.

= Does this plugin send any data externally? =

No. Everything runs on your server, and there's no tracking or telemetry. The plugin does make one same-origin HTTP request — a self-check of your own feed URL to confirm it's reachable — but no data leaves your site to any third party.

== Screenshots ==

1. Dashboard — store-wide health score gauge, feed status, top issues, and lowest-scored products.
2. Feed Health — score badges, filter tabs, and expandable per-product rule details with fix hints.
3. Feed Preview — live table view of feed items with per-cell issue highlighting.
4. Settings — feed configuration, schedule, and validation options.
5. Product edit screen — Brand, GTIN, MPN, Google Product Category, Condition fields, and health score meta box.

== Changelog ==

= 1.0.2 =
* New: Real feed-URL accessibility check (same-origin HTTP request, cached 15 minutes) — the Dashboard "Accessible" status now reflects an actual check instead of just "file exists".
* New: RULE-T09 — flags a product title that's an exact duplicate of another product's title.
* New: RULE-ID05 — flags a GTIN or MPN that's already used by another product.
* New: Sortable "Feed Health" score column on the WooCommerce Products list table, plus a filter dropdown for "Has feed issues" / "Not yet scanned".
* New: WordPress Site Health test reporting feed/scan status alongside core health checks.
* New: Admin warning when the scheduled feed refresh hasn't run for well beyond its interval (WP-Cron not firing).
* New: Extension hooks (`cs_mfb_health_table_before`, `cs_mfb_health_table_head`, `cs_mfb_health_table_row_start`, `cs_mfb_health_table_colspan`) and a `_cs_mfb_clean_description` postmeta override, used by the optional Merchant Feed Booster Pro add-on to add bulk-select and bulk-fix to the Feed Health table without modifying Lite.
* 25-rule policy engine is now 27 rules.

= 1.0.0 =
* Initial release.
* Google Merchant XML feed generation with atomic file write and WP-Cron auto-refresh.
* Per-product fields: Brand, GTIN, MPN, Google Product Category, Condition.
* 25-rule policy engine covering titles, images, prices, identifiers, and descriptions.
* Health scoring (0–100) with five color-coded tiers and per-product caching.
* Background batch scanner with real-time progress bar.
* Dashboard, Feed Health, Feed Preview, and Settings admin pages.
* CSV health report export.
* WP-CLI commands: generate, health, score, clear-cache.
* Developer filters and actions for feed items, policy rules, and score weights.
* WooCommerce HPOS compatibility.

== Upgrade Notice ==

= 1.0.2 =
Adds 2 new policy rules (duplicate title/GTIN/MPN detection), a real feed-URL reachability check, a Feed Health column on the Products list, and a Site Health test. No settings changes required.

= 1.0.0 =
Initial release. A database table for health score caching is created automatically on activation.
