=== Omnium Translate ===
Contributors: omniumdigital
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Translate rendered WordPress pages with your chosen AI provider and API key. Translations are stored on your site; no Omnium account or subscription is required, and no data is sent to Omnium.

== Description ==

Omnium Translate translates rendered page content and stores translations in your WordPress database. It sends translation requests directly to the AI provider you select, using your own API key. New translation work is processed in the background.

Supported integrations include Gutenberg, Elementor, Oxygen 6, and optional WooCommerce support. The language selector is available as a shortcode, Gutenberg block, Elementor widget, Oxygen element, WordPress menu item, or PHP function.

Requirements: WordPress 6.9 or later, PHP 8.1 or later, and the Sodium PHP extension. WooCommerce 9.0–11.2 is optional.

== Installation ==

1. Download omnium-translate.zip from the GitHub Release for the version you want.
2. In WordPress, go to Plugins > Add New Plugin > Upload Plugin, upload the ZIP, and activate the plugin.
3. Open Omnium Translate in the administration area and follow the setup wizard.
4. Choose source and target languages, select an AI provider, add and test its API key, review the estimate, and start the translation.

Create an API key with your chosen provider:

* Anthropic / Claude: https://console.anthropic.com/settings/keys (outside the EU; no free tier)
* OpenAI: https://platform.openai.com/api-keys (outside the EU; no free tier)
* Mistral: https://console.mistral.ai/api-keys (EU; limited free tier)
* Google Gemini: https://aistudio.google.com/apikey (outside the EU; limited free tier)

Provider regions, free tiers, and data terms may change. Free tiers may allow the provider to reuse submitted data under its terms; review the provider's current terms before use.

API keys are encrypted with Sodium. You can define a dedicated encryption key in wp-config.php:

    define( 'OMTR_ENCRYPTION_KEY', 'replace-with-a-long-random-secret-value' );

If this constant is not defined, the plugin derives its encryption key from the WordPress AUTH_KEY and SECURE_AUTH_SALT values.

Add a selector with [omtr_switcher]. Optional attributes: variant="dropdown|inline|floating" and display="flag-name|flag|name|code". You can also use the Omnium Translate block, Elementor widget, Oxygen 6 element, menu item, or call omtr_switcher() from a theme template.

== Frequently Asked Questions ==

= What content is sent to the AI provider? =

Public rendered text, the source and target languages, and (when configured) glossary rules and site tone/context are sent to the selected provider. Detected email addresses, phone numbers, IBANs, and payment card numbers are filtered out. The plugin sends no data to Omnium.

= Does the plugin set cookies? =

No cookie is set by default. The language is represented in the URL. An optional functional language preference cookie can be enabled in settings.

= How should I configure my page cache? =

Language versions use URL path prefixes such as /en/ and /es/, while the source language has no prefix. Configure WP Rocket, LiteSpeed Cache, or another page cache to vary by the full URL so each language URL has its own cached page.

= What happens when I uninstall the plugin? =

The encrypted API key and scheduled tasks are removed. Translations and settings are kept by default. Enable the full purge option in the plugin settings before uninstalling to remove the plugin's tables, options, transients, and metadata too.

= How are plugin updates delivered? =

The plugin checks public GitHub Releases using Plugin Update Checker. Update checks can be disabled in the plugin settings.

== Changelog ==

= 1.0.0 =
* Initial release: configure languages and an AI provider, then translate public site content in the background.
* Add a language selector through shortcode, block, builder integrations, menus, or PHP.
* Include glossary tools, translated slugs, and SEO language metadata.
* Add optional WooCommerce catalog, fragment, and email-template integrations.
