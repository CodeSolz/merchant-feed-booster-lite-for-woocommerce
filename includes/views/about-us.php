<?php
/**
 * About Us – the CodeSolz plugin family.
 *
 * Same page as Better Find & Replace's About Us screen: a short intro to
 * CodeSolz, a card for this plugin, and cards for the other CodeSolz plugins.
 * Every icon is bundled with the plugin, so the page makes no external request.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cs_mfb_about_icons = CS_MFB_PLUGIN_URL . 'assets/img/plugins/';

/*
 * The other CodeSolz plugins. `icon_file` is the plugin's WordPress.org icon,
 * bundled next to this plugin's other images.
 */
$cs_mfb_about_plugins = array(
	array(
		'title'       => 'Better Find & Replace',
		'subtitle'    => 'Change any text across your whole site at once',
		'description' => 'Replace text on the fly as pages load, or permanently in the database. Works with plain text and regular expressions, and swaps media files in your Media Library.',
		'icon_file'   => 'better-find-and-replace.png',
		'features'    => array(
			'Real-time replacement without touching the database',
			'Permanent find & replace in database tables',
			'Plain text and regex rules',
			'Replace media files in the Media Library',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/real-time-auto-find-and-replace/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce',
		'wporg_url'   => 'https://wordpress.org/plugins/real-time-auto-find-and-replace/',
	),
	array(
		'title'       => 'Aisleza – 3D Virtual Store for WooCommerce',
		'subtitle'    => 'Let shoppers walk through your store',
		'description' => 'Turn your WooCommerce catalog into a walk-in 3D store. Categories become aisles, products sit on real shelves with price tags, and shoppers fill a trolley that is your real WooCommerce cart.',
		'icon_file'   => 'aisleza.png',
		'features'    => array(
			'Build aisles from your product categories',
			'Products on shelves with live prices & sale tags',
			'Uses your real WooCommerce cart & checkout',
			'Works with keyboard, mouse, and touch joystick',
		),
		'pro_url'     => 'https://aisleza.com/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce',
		'wporg_url'   => 'https://wordpress.org/plugins/aisleza-3d-virtual-store-for-woocommerce/',
	),
	array(
		'title'       => 'PressHelm AI Site Developer',
		'subtitle'    => 'Your AI developer for WordPress content',
		'description' => 'Give your WordPress site its own AI developer. Ask in plain words from Telegram, Claude, or ChatGPT, and it builds pages, posts, products, menus, forms, and SEO on your real site, with approvals and one-tap undo.',
		'icon_file'   => 'presshelm-ai.png',
		'features'    => array(
			'Edit your site from Telegram, Claude, or ChatGPT',
			'Pages, products, images, menus, forms & SEO',
			'Approve important changes before they run',
			'Undo any change with one tap',
		),
		'pro_url'     => 'https://presshelmai.com/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce',
		'wporg_url'   => 'https://wordpress.org/plugins/presshelm-ai/',
	),
	array(
		'title'       => 'WatchSpire',
		'subtitle'    => 'Your WordPress early warning system',
		'description' => 'Catch silent WordPress failures before they cost you leads or sales. WatchSpire monitors forms, checkout, email delivery, uptime, SSL, and more, then alerts you when something goes wrong.',
		'icon_file'   => 'watchspire.png',
		'features'    => array(
			'Catch failed forms & checkout issues',
			'Know what changed before a failure',
			'Get alerts before problems cost you leads',
			'See which update may have caused a failure',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/watchspire/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce',
		'wporg_url'   => 'https://wordpress.org/plugins/watchspire/',
	),
	array(
		'title'       => 'AI Store Signals for WooCommerce',
		'subtitle'    => 'Get your products ready for AI shopping',
		'description' => 'See how well AI systems can understand your WooCommerce store. Find visibility gaps, improve product data, generate AI-friendly feeds and llms.txt, and track AI bot activity.',
		'icon_file'   => 'ai-store-signals.png',
		'features'    => array(
			'Get an AI readiness score for your store',
			'Find product & schema visibility gaps',
			'Generate AI-friendly feeds & llms.txt',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/ai-store-signals-for-woocommerce/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce',
		'wporg_url'   => 'https://wordpress.org/plugins/ai-store-signals-for-woocommerce/',
	),
	array(
		'title'       => 'WearGraph – AI Virtual Try-On for WooCommerce',
		'subtitle'    => 'Let shoppers see clothes on themselves',
		'description' => 'Shoppers add a photo and see the garment on themselves, with Add to Cart right beside the preview. Runs on your own Gemini, OpenAI, OpenRouter, FLUX or xAI key, with no WearGraph account or subscription.',
		'icon_file'   => 'weargraph.png',
		'features'    => array(
			'AI fitting room on product pages',
			'On-model product images from your own photos',
			'See which products are ready for try-on',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/weargraph/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce',
		'wporg_url'   => 'https://wordpress.org/plugins/weargraph/',
	),
	array(
		'title'       => 'AttributeHub for WooCommerce',
		'subtitle'    => 'Turn messy product attributes into clean filters',
		'description' => 'Clean up confusing supplier and imported attribute values without changing your original product data. Create consistent, customer-friendly filters across your store.',
		'icon_file'   => 'attributehub.png',
		'features'    => array(
			'Turn codes like BK into Black and WH into White',
			'Clean imported attributes automatically',
			'Keep original product data untouched',
		),
		'pro_url'     => 'https://codesolz.net/our-products/wordpress-plugin/attributehub-for-woocommerce/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce',
		'wporg_url'   => 'https://wordpress.org/plugins/attributehub-for-woocommerce/',
	),
);

$cs_mfb_about_docs_url     = '';
$cs_mfb_about_product_url  = 'https://codesolz.net/our-products/wordpress-plugin/merchant-feed-booster-lite-for-woocommerce/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce';
$cs_mfb_about_wporg_url    = 'https://wordpress.org/plugins/merchant-feed-booster-lite-for-woocommerce/';
$cs_mfb_about_support_url  = 'https://wordpress.org/support/plugin/merchant-feed-booster-lite-for-woocommerce/';
$cs_mfb_about_upgrade_url  = 'https://codesolz.net/our-products/wordpress-plugin/merchant-feed-booster-lite-for-woocommerce/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce';
$cs_mfb_about_products_url = 'https://codesolz.net/our-products/wordpress-plugins/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce';
$cs_mfb_about_website_url  = 'https://codesolz.net/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce';
$cs_mfb_about_forum_url    = 'https://codesolz.net/forum/?utm_source=plugin-about&utm_medium=wp-admin&utm_campaign=about-page&utm_content=merchant-feed-booster-lite-for-woocommerce';
$cs_mfb_about_has_pro      = defined( 'MFB_PRO_VERSION' );
?>
<div class="wrap">
<div class="cs-mfb-about-wrap">

	<!-- ================================================
		HERO SECTION
		================================================ -->
	<div class="cs-mfb-about-hero">
		<div class="cs-mfb-about-hero-grid">
			<div class="cs-mfb-about-hero-copy">
				<div class="cs-mfb-about-hero-badge"><?php esc_html_e( 'CodeSolz · WordPress plugins since 2016', 'merchant-feed-booster-lite-for-woocommerce' ); ?></div>
				<h1 class="cs-mfb-about-hero-title"><?php esc_html_e( 'WordPress Tools That Each Fix One Real Problem', 'merchant-feed-booster-lite-for-woocommerce' ); ?></h1>
				<p class="cs-mfb-about-hero-subtitle">
					<?php esc_html_e( 'A contact form that stops sending without any error. A Google Shopping product rejected because its title is too long. Supplier codes like BLK showing up in your shop filters. CodeSolz plugins find problems like these on your own site, show you exactly what is wrong, and help you fix it.', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
				</p>
				<div class="cs-mfb-about-hero-points">
					<span class="cs-mfb-about-hero-point"><?php esc_html_e( 'Every plugin starts free on WordPress.org', 'merchant-feed-booster-lite-for-woocommerce' ); ?></span>
					<span class="cs-mfb-about-hero-point"><?php esc_html_e( 'This page loads nothing from outside your site', 'merchant-feed-booster-lite-for-woocommerce' ); ?></span>
					<span class="cs-mfb-about-hero-point"><?php esc_html_e( 'Plugins for WordPress and WooCommerce', 'merchant-feed-booster-lite-for-woocommerce' ); ?></span>
				</div>
				<div class="cs-mfb-about-hero-actions">
					<a href="<?php echo esc_url( $cs_mfb_about_products_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-hero-cta">
						<?php esc_html_e( 'Browse Our Plugins', 'merchant-feed-booster-lite-for-woocommerce' ); ?> <span aria-hidden="true">&#8599;</span>
					</a>
					<a href="<?php echo esc_url( $cs_mfb_about_website_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-hero-link">
						<?php esc_html_e( 'Visit CodeSolz', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
					</a>
				</div>
			</div>
			<div class="cs-mfb-about-hero-showcase">
				<div class="cs-mfb-about-hero-panel">
					<div class="cs-mfb-about-hero-panel-label"><?php esc_html_e( 'Find the right tool', 'merchant-feed-booster-lite-for-woocommerce' ); ?></div>
					<h2 class="cs-mfb-about-hero-panel-title"><?php esc_html_e( 'Start with the problem you have', 'merchant-feed-booster-lite-for-woocommerce' ); ?></h2>
					<div class="cs-mfb-about-hero-feature-list">
						<div class="cs-mfb-about-hero-feature">
							<strong><?php esc_html_e( 'Sales you may be losing', 'merchant-feed-booster-lite-for-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'WatchSpire catches forms and checkouts that fail without an error. Merchant Feed Booster checks every product against 27 Google Shopping rules.', 'merchant-feed-booster-lite-for-woocommerce' ); ?></span>
						</div>
						<div class="cs-mfb-about-hero-feature">
							<strong><?php esc_html_e( 'A messy catalog or site', 'merchant-feed-booster-lite-for-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'AttributeHub turns codes like BK into Black in your filters. Better Find & Replace changes a word or link on every page at once.', 'merchant-feed-booster-lite-for-woocommerce' ); ?></span>
						</div>
						<div class="cs-mfb-about-hero-feature">
							<strong><?php esc_html_e( 'New ways for shoppers to buy', 'merchant-feed-booster-lite-for-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'Aisleza turns your catalog into a walk-in 3D store, WearGraph adds virtual try-on, and AI Store Signals gets products ready for AI shopping assistants.', 'merchant-feed-booster-lite-for-woocommerce' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- ================================================
		FEATURED PLUGIN – THIS PLUGIN
		================================================ -->
	<div class="cs-mfb-about-section-header">
		<div class="cs-mfb-about-section-label"><?php esc_html_e( 'Currently Installed', 'merchant-feed-booster-lite-for-woocommerce' ); ?></div>
		<h2 class="cs-mfb-about-section-title"><?php esc_html_e( 'Your Active Plugin', 'merchant-feed-booster-lite-for-woocommerce' ); ?></h2>
		<p class="cs-mfb-about-section-desc"><?php esc_html_e( 'What the plugin you are running does, and where to get help with it.', 'merchant-feed-booster-lite-for-woocommerce' ); ?></p>
	</div>

	<div class="cs-mfb-about-featured-card">
		<div class="cs-mfb-about-featured-inner">

			<div class="cs-mfb-about-featured-icon-wrap" aria-hidden="true">
				<img src="<?php echo esc_url( $cs_mfb_about_icons . 'merchant-feed-booster.png' ); ?>" alt="" width="72" height="72" />
			</div>

			<div class="cs-mfb-about-featured-body">
				<div class="cs-mfb-about-featured-meta">
					<span class="cs-mfb-about-badge featured-label"><?php esc_html_e( 'Installed', 'merchant-feed-booster-lite-for-woocommerce' ); ?></span>
					<span class="cs-mfb-about-badge active"><?php esc_html_e( 'Active', 'merchant-feed-booster-lite-for-woocommerce' ); ?></span>
					<?php if ( $cs_mfb_about_has_pro ) : ?>
						<span class="cs-mfb-about-badge premium"><?php esc_html_e( 'Pro', 'merchant-feed-booster-lite-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</div>

				<h2 class="cs-mfb-about-featured-title"><?php esc_html_e( 'Merchant Feed Booster for WooCommerce', 'merchant-feed-booster-lite-for-woocommerce' ); ?></h2>

				<p class="cs-mfb-about-featured-desc">
					<?php esc_html_e( 'Builds your Google Merchant XML feed and checks every product against 27 Google Merchant Center policy rules. Each product gets a health score from 0 to 100, and every failing rule tells you what is wrong and how to fix it.', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
				</p>

				<ul class="cs-mfb-about-feature-list">
							<li><?php esc_html_e( 'Google Merchant XML feed with scheduled refresh', 'merchant-feed-booster-lite-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( '27 policy rules for titles, images, prices and IDs', 'merchant-feed-booster-lite-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( '0–100 health score for every product', 'merchant-feed-booster-lite-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'GTIN check-digit validation', 'merchant-feed-booster-lite-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Brand, GTIN, MPN and category fields per product', 'merchant-feed-booster-lite-for-woocommerce' ); ?></li>
							<li><?php esc_html_e( 'Export every issue to CSV', 'merchant-feed-booster-lite-for-woocommerce' ); ?></li>
				</ul>

				<div class="cs-mfb-about-btn-group">
					<?php if ( '' !== $cs_mfb_about_docs_url ) : ?>
						<a href="<?php echo esc_url( $cs_mfb_about_docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn primary">
							<?php esc_html_e( 'Documentation', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
						</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $cs_mfb_about_product_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn <?php echo '' !== $cs_mfb_about_docs_url ? 'secondary' : 'primary'; ?>">
						<?php esc_html_e( 'Product Page', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
					</a>
					<a href="<?php echo esc_url( $cs_mfb_about_wporg_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn secondary">
						<?php esc_html_e( 'WordPress.org Page', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
					</a>
					<a href="<?php echo esc_url( $cs_mfb_about_support_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn secondary">
						<?php esc_html_e( 'Get Support', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
					</a>
					<?php if ( ! $cs_mfb_about_has_pro ) : ?>
						<a href="<?php echo esc_url( $cs_mfb_about_upgrade_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn outline-purple">
							&#9733; <?php esc_html_e( 'Upgrade to Pro', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- ================================================
		PLUGIN GRID
		================================================ -->
	<div class="cs-mfb-about-section-header">
		<div class="cs-mfb-about-section-label"><?php esc_html_e( 'Plugin Family', 'merchant-feed-booster-lite-for-woocommerce' ); ?></div>
		<h2 class="cs-mfb-about-section-title"><?php esc_html_e( 'More from CodeSolz', 'merchant-feed-booster-lite-for-woocommerce' ); ?></h2>
		<p class="cs-mfb-about-section-desc"><?php esc_html_e( 'Each one solves a different problem, and each one has a free version on WordPress.org you can try first.', 'merchant-feed-booster-lite-for-woocommerce' ); ?></p>
	</div>

	<div class="cs-mfb-about-plugins-grid">
		<?php foreach ( $cs_mfb_about_plugins as $cs_mfb_about_plugin ) : ?>
		<div class="cs-mfb-about-plugin-card">
			<div class="cs-mfb-about-card-top">
				<div class="cs-mfb-about-card-icon" aria-hidden="true">
					<img src="<?php echo esc_url( $cs_mfb_about_icons . $cs_mfb_about_plugin['icon_file'] ); ?>" alt="" width="48" height="48" loading="lazy" />
				</div>
				<div class="cs-mfb-about-card-heading">
					<h3 class="cs-mfb-about-card-title"><?php echo esc_html( $cs_mfb_about_plugin['title'] ); ?></h3>
					<span class="cs-mfb-about-card-subtitle"><?php echo esc_html( $cs_mfb_about_plugin['subtitle'] ); ?></span>
				</div>
			</div>
			<p class="cs-mfb-about-card-desc"><?php echo esc_html( $cs_mfb_about_plugin['description'] ); ?></p>
			<?php if ( ! empty( $cs_mfb_about_plugin['features'] ) ) : ?>
			<ul class="cs-mfb-about-card-features">
				<?php foreach ( $cs_mfb_about_plugin['features'] as $cs_mfb_about_feature ) : ?>
				<li><?php echo esc_html( $cs_mfb_about_feature ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
			<div class="cs-mfb-about-card-footer">
				<a href="<?php echo esc_url( $cs_mfb_about_plugin['pro_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-card-link pro">
					<?php esc_html_e( 'Learn More', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
				</a>
				<a href="<?php echo esc_url( $cs_mfb_about_plugin['wporg_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-card-link wporg">
					<?php esc_html_e( 'Get It Free on WordPress.org', 'merchant-feed-booster-lite-for-woocommerce' ); ?> &#8599;
				</a>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- ================================================
		FOOTER SECTION
		================================================ -->
	<div class="cs-mfb-about-trust-section">
		<div class="cs-mfb-about-trust-logo">Code<span>Solz</span></div>
		<h2 class="cs-mfb-about-trust-tagline"><?php esc_html_e( 'Making WordPress plugins since 2016', 'merchant-feed-booster-lite-for-woocommerce' ); ?></h2>
		<p class="cs-mfb-about-trust-desc">
			<?php esc_html_e( 'Every plugin on this page starts free on WordPress.org, so you can try it on your own site before you pay for anything.', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
		</p>
		<div class="cs-mfb-about-trust-links">
			<a href="<?php echo esc_url( $cs_mfb_about_website_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn primary">
				<?php esc_html_e( 'Visit Website', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
			</a>
			<?php if ( '' !== $cs_mfb_about_docs_url ) : ?>
				<a href="<?php echo esc_url( $cs_mfb_about_docs_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn secondary">
					<?php esc_html_e( 'Documentation', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( $cs_mfb_about_support_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn secondary">
				<?php esc_html_e( 'Get Support', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
			</a>
			<a href="<?php echo esc_url( $cs_mfb_about_forum_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn secondary">
				<?php esc_html_e( 'Our Community', 'merchant-feed-booster-lite-for-woocommerce' ); ?>
			</a>
			<a href="<?php echo esc_url( $cs_mfb_about_products_url ); ?>" target="_blank" rel="noopener noreferrer" class="cs-mfb-about-btn outline-purple">
				<?php esc_html_e( 'All Our Plugins', 'merchant-feed-booster-lite-for-woocommerce' ); ?> &#8599;
			</a>
		</div>
	</div>

</div><!-- .cs-mfb-about-wrap -->
</div><!-- .wrap -->
