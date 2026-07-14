=== Merchant Feed Booster Lite for WooCommerce ===
Contributors: codesolz, m.tuhin
Tags: google shopping, woocommerce, product feed, merchant center, google merchant feed
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Know exactly why your WooCommerce products are rejected or underperforming on Google Shopping — then fix them with specific, per-rule guidance.

== Description ==

**Merchant Feed Booster Lite** goes beyond a simple XML export: it audits every product against 25 Google Merchant Center policy rules, assigns a health score (0–100), and tells you exactly what to fix and why.

**The core problem it solves:** You upload a feed, products get disapproved or show low impressions, and you have no idea which product has what issue. This plugin surfaces those issues before they cost you.

= What's included in the free version =

**Google Merchant XML feed**
- RSS 2.0 with `g:` namespace written to `wp-content/uploads/codesolz-feeds/google-products.xml`
- Atomic write (`.tmp` → rename) so the live feed is never half-written during regeneration
- WP-Cron refresh: twice daily, daily, weekly, or a custom interval (in hours)
- Manual regeneration with live product count feedback
- Configurable: feed title, title prefix, default brand, default Google product category, out-of-stock toggle

**Per-product fields added to WooCommerce**
- Brand, GTIN, MPN, Google Product Category, Condition (new / refurbished / used)
- GTIN validated using the GS1 check digit algorithm — warns you if the number is structurally invalid

**Feed Health — 25 policy rules**

Every product is scored 0–100 and checked against named rules:

*Title (T01–T08):* Too short (<25 chars), too long (>150 chars), promotional words (FREE/SALE/% OFF), ALL CAPS words, HTML tags, price in title, special characters at start/end, repeated words.

*Image (I01–I05):* Missing image, below 100×100 px (feed rejection threshold), below 250×250 px (warning), non-standard MIME type, tracking parameters in URL.

*Price (P01–P04):* Missing or zero price, sale price with no regular price set, sale price ≥ regular price, non-numeric value.

*Identifiers (ID01–ID04):* Wrong GTIN digit count, invalid GS1 check digit, brand set but no GTIN/MPN, missing brand.

*Description (D01–D04):* Missing description, under 50 characters, HTML tags present, promotional language.

**Health score tiers**

* Excellent (85–100) — green
* Good (70–84) — blue
* Needs Work (50–69) — amber
* Poor (30–49) — orange
* Critical (0–29) — red

Scores are cached per-product using an MD5 hash of key fields. If nothing changed, the score loads from cache instantly. If a product's title, price, image, brand, GTIN, or modified date changes, it is automatically rescored.

**Admin UI**
- **Dashboard:** animated store-wide health score gauge, feed status with live pulsing indicator, feed URL with one-click copy, quick actions
- **Feed Health:** score badge per product, filter tabs (All / Issues Only / Title / Image / Price / Identifiers / Description), expandable accordion rows with per-rule fix hints, pagination (50/page), background scan progress bar
- **Feed Preview:** live view of your feed items as a styled table with per-cell severity highlighting; paginated (20/page)
- **Settings:** fully AJAX-driven settings form — no page reloads

**WP-CLI support**

`wp cs-mfb generate` — regenerate the feed
`wp cs-mfb health` — list all products with scores
`wp cs-mfb score <id>` — score and issues for one product
`wp cs-mfb clear-cache` — clear all cached scores

**Developer hooks**

Filters: `cs_mfb_policy_rules`, `cs_mfb_item_data`, `cs_mfb_extra_item_fields`, `cs_mfb_score_weights`, `cs_mfb_health_table_columns`, `cs_mfb_should_include_product`, `cs_mfb_required_capability`

Actions: `cs_mfb_ready`, `cs_mfb_feed_generated`, `cs_mfb_after_product_scored`, `cs_mfb_before_item_written`, `cs_mfb_after_health_table`

= Privacy =

This plugin does not collect, transmit, or store any personal data. All feed processing runs entirely on your server. No external API calls are made by the free version.

== Installation ==

1. Upload the `merchant-feed-booster` folder to `/wp-content/plugins/`.
2. Activate **Merchant Feed Booster Lite for WooCommerce** via the **Plugins** menu.
3. WooCommerce must be installed and active.
4. Go to **Feed Booster → Settings**, configure the feed title and defaults, and save.
5. Click **Regenerate Feed** on the Dashboard to create the first XML file.
6. Copy the feed URL into Google Merchant Center as a scheduled fetch (Settings → Data sources → Add file).

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. WooCommerce 6.0 or higher must be installed and active. The plugin shows an admin notice if WooCommerce is missing.

= Where is the XML feed file stored? =

At `wp-content/uploads/codesolz-feeds/google-products.xml`. The public URL is shown on the Dashboard and can be copied with one click.

= How often does the feed refresh automatically? =

You can choose twice daily, daily, weekly, or a custom interval (in hours) in Settings. You can also regenerate manually at any time from the Dashboard.

= Does the health score re-run every time? =

No. Scores are cached per-product using a hash of key product fields. The score is only recalculated when the product's title, price, image, brand, GTIN, or modified date changes. You can force a full rescan from the Feed Health page.

= What does a low health score mean? =

It means the product has fields that violate one or more of Google Merchant Center's content requirements. Products with low scores are at higher risk of being disapproved or appearing with low impressions. The Feed Health page shows exactly which rules are failing and what to fix.

= Does this plugin contact any external servers? =

No. All processing — feed generation, health scoring, GTIN validation, image dimension checking — runs entirely on your WordPress server. No data is sent externally.

= Is it compatible with WooCommerce High-Performance Order Storage (HPOS)? =

Yes. This plugin only reads and writes product data, never orders. Full HPOS compatibility is declared.

= Can I filter which products appear in the feed? =

Yes, via the `cs_mfb_should_include_product` filter. See the plugin README for all available filters and actions.

== Screenshots ==

1. Dashboard with animated health score gauge, feed status, and quick actions.
2. Feed Health table with per-product score badges, filter tabs, and expandable rule details.
3. Feed Preview table with per-cell severity highlighting.
4. Settings page with AJAX save.

== Changelog ==

= 2.0.0 =
* Complete rewrite with a 25-rule policy engine covering Title, Image, Price, Identifiers, and Description.
* New health scoring algorithm (0–100) with five color-coded tiers and product-hash-based caching.
* Background scanning via transient state with REST polling and JS progress bar.
* New REST API: scan/start, scan/progress, product/{id}/score, feed/regenerate, cache/clear.
* New admin UI: animated SVG score gauge, dark gradient dashboard hero, inner banners on all sub-pages.
* Feed Health pagination (50/page) with filter tabs and accordion fix hints.
* Feed Preview page with per-cell issue highlighting and pagination (20/page).
* GTIN validation using GS1 check digit algorithm (Luhn-based).
* WooCommerce HPOS compatibility declaration.
* WP-CLI commands: generate, health, score, clear-cache.
* All JS bundled in-plugin — no external CDN dependencies.

= 1.0.0 =
* Initial release. Basic Google Merchant XML feed generation with WP-Cron refresh.

== Upgrade Notice ==

= 2.0.0 =
Major version. A new database table (`{prefix}cs_mfb_audit_cache`) is created automatically on activation. No data migration is needed from v1.0.
