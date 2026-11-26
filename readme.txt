=== PointNet AI Chatbot – Virtual Assistant ===
Contributors: pointnet
Tags: chatbot, ai, virtual assistant, gemini, openai
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.8.4
License: GPLv2 or later

AI-powered virtual assistant using RAG to answer user queries with your WordPress site content. Secure, lightweight, multi-provider.

== Description ==

PointNet RAG Chatbot is a secure, high-performance, and GDPR-compliant AI assistant designed to read and understand your site's public content (Pages, Posts, WooCommerce Products, and Custom Post Types) to deliver accurate, localized customer support and sales assistance.

Rather than relying on expensive and complex external vector databases, this plugin utilizes a highly optimized native WordPress RAG search algorithm to fetch relevant context on the fly and feed it to state-of-the-art AI models.

= External Services =
This plugin connects to external third-party AI services to generate chat responses and retrieve available model lists. Depending on the AI Provider configured in the plugin settings, user prompts and relevant excerpted site content will be transmitted to that service:

* **OpenAI (ChatGPT):**
  - Purpose: Generates assistant responses and retrieves available models when OpenAI is selected as the provider.
  - Data transmitted: User message, recent conversation history (up to last 6 messages), and public site content excerpts relevant to the query.
  - Terms of Use: https://openai.com/policies/terms-of-use/
  - Privacy Policy: https://openai.com/policies/privacy-policy/

* **Google Gemini:**
  - Purpose: Generates assistant responses and retrieves available models when Google Gemini is selected as the provider.
  - Data transmitted: User message, recent conversation history (up to last 6 messages), and public site content excerpts relevant to the query.
  - Terms of Service: https://ai.google.dev/terms
  - Privacy Policy: https://policies.google.com/privacy

* **OpenRouter:**
  - Purpose: Generates assistant responses via configured models and fallback cascades when OpenRouter is selected as the provider.
  - Data transmitted: User message, recent conversation history (up to last 6 messages), and public site content excerpts relevant to the query.
  - Terms of Service: https://openrouter.ai/terms
  - Privacy Policy: https://openrouter.ai/privacy

= Key Features =
* **Multi-Provider Support:** Seamlessly connect with OpenAI, Google Gemini, or OpenRouter.
* **Hybrid Taxonomy-Aware RAG Engine:** Solves WordPress's native search blind spot. The engine scans public taxonomies (categories, tags, custom menus) in parallel with classic text search, resolving issues where custom post types are associated with categories but do not contain the keyword within the post content itself.
* **Dynamic Conversational Memory (Sliding Window):** The chatbot now securely maintains a sliding-window context of the last 3 turns (6 messages) via browser sessionStorage, allowing users to have natural, fluid dialogues and ask follow-up questions.
* **Custom Stop Words & Keyword Anti-Flooding:** Admin-configurable ignored keywords field. Prevents "Keyword Flooding" by ignoring domain-specific words (e.g., 'cocktail, drink, recipe' for mixology) without hardcoding anything into PHP, maintaining the plugin 100% general-purpose.
* **Configurable Search Depth:** Fine-tune RAG performance directly from settings. Adjust the maximum number of retrieved posts (from 1 to 25) to balance between precision and server response latency.
* **Auto-Optimizing Fallback Cascade:** Define your preferred priority free-tier models (such as GPT-OSS, Gemma, or Mistral). If one is down or rate-limited, the system silently cascades to the next working one.
* **Smart "Bouncer" Quality Control:** If all priority models fail, the system triggers OpenRouter's free wildcard (`openrouter/free`). The "Bouncer" filters out low-quality/empty safety responses (like "User Safety: safe") as well as "Thinking Leaks" (models thinking out loud in English), handling errors gracefully without exposing them to users.
* **Persistent Chat Sessions:** Thanks to `sessionStorage` integration, conversations remain active and visible as visitors navigate through different pages of your website.
* **Privacy & Gated Content Compliance:** The extraction engine strictly respects WordPress page passwords, CPT visibility parameters, and membership plugin restrictions (MemberPress, PMPro, WooCommerce Memberships).
* **Multi-Layer Security & Anti-DDoS:** Built-in protection against automated API budget drain (DDoW) with per-minute, daily-IP, and global daily request limits. Fully compatible with Cloudflare proxy headers.
* **Dynamic Markdown Links:** The AI dynamically extracts page permalinks from the database (including a dedicated admin-configured Contact URL) to generate safe, same-tab clickable hyperlinks.
* **Detailed Chat Logging & Latency Tracking:** Track all user interactions, measure exact response times (latency) and the effectiveness of model fallbacks. Export logs as CSV for prompt optimization.
* **Native WordPress UI/UX:** Clean, intuitive admin settings page consolidated into standard horizontal navigation tabs.

== Installation & Usage ==

= 1. Installation =
1. Upload the `pointnet-ai-chatbot` folder to the `/wp-content/plugins/` directory, or install the .zip file directly through the WordPress admin panel.
2. Activate the plugin through the 'Plugins' menu in WordPress.

= 2. Basic Configuration =
1. Go to **Settings > PointNet AI Chatbot** (or click "Settings" directly from the Plugins list).
2. Choose your preferred **AI Provider** (e.g., Google Gemini for free-tier high speed models).
3. Paste the corresponding **API Key**.
4. Click **Fetch Models** to dynamically retrieve and select the latest models (such as `gemini-3.5-flash` or `gemini-3.1-flash-lite`).
5. Enter your exact **Contact Page URL** (e.g., `/contact/` or `/contatti/`). The bot will always use this link to redirect clients for official quotes.

= 3. Knowledge, Training & Custom Stop Words =
1. Fill in the **Global Site Knowledge** box with an overview of your business (who you are, what you offer, contacts). The AI will always know this fundamental context, regardless of the page searched.
2. Customize the **System Prompt** to define your bot's tone of voice (e.g., professional, sales-oriented, concise).
3. Fill in the **Custom Stop Words** field to ignore domain-specific words (e.g., if you sell cars, you might want to ignore words like 'car, vehicle, review' to keep search results hyper-focused on the exact model).

= 4. Safety & Security Settings =
1. Set the numeric limits in the settings panel to protect your API wallet:
   - **Messages per Minute:** (Default: 10) Prevents instant spamming and click flooding.
   - **Daily Messages per IP:** (Default: 100) Stops slow bots from draining your budget over 24 hours.
   - **Daily Global Messages:** (Default: 2000) Shuts down the bot globally for the day if a massive distributed attack (DDoS) occurs.
2. Click **Save Settings**.

= 5. Monitoring & Optimization =
1. Click the **Chat History Logs** tab in the admin panel to view live user queries.
2. Notice the **Time** column (which shows the exact latency of each request) and the **Model Details** column (which shows if any fallback or the Jolly model was triggered).
3. Click **Download Logs (CSV)** to export data and use it in ChatGPT or Claude to refine your prompt or global knowledge.

== Frequently Asked Questions ==

= Does this plugin support WooCommerce? =
Yes! The RAG search dynamically scans all public custom post types on your WordPress site, including WooCommerce products.

= Is it really free to run? =
Yes. If you choose OpenRouter as your provider and use their free-tier models, you do not need to add a credit card or pay any fees.

= Is it GDPR compliant? =
Yes. We do not store plain-text IP addresses. All user rate limits are handled using cryptographically secure hashes linked to your local WordPress salts. The interface also includes a dynamic privacy disclaimer in the chat window.

= If the user changes pages, is the conversation lost? =
No. We integrated `sessionStorage` technology. The conversation remains intact and the chat window remains open or closed depending on how the user left it.

== Roadmap ==

= v1.9 – Per-Provider Critical Rules =
* Allow different Critical Rules per AI provider (OpenAI, Gemini, OpenRouter).
* Some models respond better to different phrasing — enabling provider-specific optimization.
* Default: same rules for all providers, with optional per-provider overrides.

= v2.0 – Semantic Vector Search (Local) =
* Local embedding storage using the already-configured API key (OpenAI or Gemini).
* Cosine similarity ranking in pure PHP — no external vector database needed.
* Auto-sync: embeddings rebuild automatically when content is published or updated.
* One-click "Rebuild All Embeddings" button in admin panel.
* Ideal for sites with 1,000+ pages requiring synonym-aware semantic search.

== Changelog ==

= 1.8.4 – Security Hardening & WordPress.org Quality Compliance =
* SECURITY: Hardened Markdown link formatting in `chat.js` against DOM XSS and attribute breakout; strictly enforced http/https and relative schemes with `rel="noopener noreferrer"`.
* SECURITY: Added 1,000-character input capping on both frontend and server-side for user messages; restricted sliding window history to last 6 messages and 2,000 chars per message.
* SECURITY: Sanitized and prepared SQL placeholders dynamically for Custom Post Types; added graceful error suppression and automatic LIKE fallback if FULLTEXT indexing fails.
* SECURITY: Protected sensitive and internal post meta keys from exposure in RAG context; added `is_protected_meta()` checks and regex filter against credentials/passwords.
* SECURITY: Masked third-party provider API error messages to generic notifications for public visitors; detailed errors remain accessible only to site administrators.
* SECURITY: Hardened CSV history export against CSV Formula Injection across all columns; switched log clearance from GET to secure POST action with nonce and permission checks.
* SECURITY: Hardened IP resolution with official Cloudflare IPv6 CIDR ranges and private range filtering against header spoofing.
* SECURITY: Replaced sliding-window rate limit transients with discrete 1-minute and 24-hour time buckets to prevent infinite visitor lockout.
* REFACTOR: Extracted admin JavaScript from inline script tags to dedicated enqueued asset (`admin/admin.js`) using `wp_localize_script()`, eliminating inline execution and CSP issues.
* COMPLIANCE: Added comprehensive `= External Services =` documentation in `readme.txt` detailing OpenAI, Google Gemini, and OpenRouter data usage and terms.
* COMPLIANCE: Integrated WordPress Core Suggested Privacy Policy Content (`wp_add_privacy_policy_content()`) for site administrators.
* COMPLIANCE: Improved `uninstall.php` cleanup to drop FULLTEXT indexes, options, and transient entries.

= 1.8.3 =
* FIX: Fixed intermittent 403 errors on admin-ajax.php caused by nonce expiration in cached pages.
* FIX: Added a lightweight AJAX endpoint (`pn_ai_chatbot_get_nonce`) that returns a fresh nonce; the frontend caches it in sessionStorage and falls back to the static page nonce if the endpoint is unavailable.
* FIX: Nonce validation failures now return a clean JSON error message ("Session expired. Please reload the page.") instead of a raw 403 HTML response.
* FIX: Frontend now gracefully handles non-JSON or non-OK HTTP responses, showing the connection error message instead of "Notice: undefined".

= 1.8.2 – Critical Rules Protection & UX Improvements =
* FEATURE: Critical AI Behavior Rules textarea is now read-only by default, with a "Enable manual editing" checkbox and a warning message to prevent accidental modifications.
* FEATURE: Added `critical_rules_enabled` boolean option — custom rules are only applied when editing is explicitly enabled.
* FEATURE: Added `pn_ai_chatbot_critical_rules` filter for developers who need programmatic customization.
* FEATURE: Chat scroll behavior improved — when a bot response arrives, the chat scrolls to the beginning of the new message, not to the bottom. Added `position:relative` to messages container and `scrollToTopOfElement()` function.
* FEATURE: Added RAG search depth to chat log model details (e.g. "depth=8"), alongside truncation mode and provider name.
* FIX: LINK INTEGRITY rule updated — added "file names, or any other contextual clues" and "If text resembles a filename but no URL is present, do NOT fabricate one" to prevent Gemini from guessing URLs from PDF filenames.
* ENHANCEMENT: Removed `scrollToBottom()` from `showTypingIndicator()` and `pnAiChatbotFinally()` to prevent override of the scroll-to-top behavior.
* ENHANCEMENT: Asset versioning now uses `filemtime()` — CSS/JS URLs are automatically cache-busted when files change, eliminating "undefined" notices after plugin updates with W3 Total Cache. No more manual cache purge + Ctrl+F5.
* ENHANCEMENT: Version bump from 1.8.1 to 1.8.2.

= 1.8.1 – Adaptive Content Truncation & Link Integrity =
* FEATURE: Introduced Adaptive Content Truncation mode (admin-configurable) — top results get more chars, mid results medium, bottom results less. Captures more relevant content while saving tokens.
* FEATURE: Added Uniform/Adaptive toggle in Knowledge Base settings. In Uniform mode, the admin sets a custom character limit (500–20000).
* FEATURE: Adaptive limits are fully configurable — admin can set three comma-separated values (Top/Mid/Rest) from admin panel, defaulting to 4000,3000,1500.
* FEATURE: Added "PARTIAL DATA WARNING" critical rule — the AI now knows it receives page excerpts only, preventing false negative claims like "the page does not contain" or "does not list".
* FEATURE: Added "LINK INTEGRITY" critical rule — the AI can only use URLs that appear verbatim in the data, preventing it from inventing external links (e.g. google.com, codecademy.com) based on institution names.
* FEATURE: Added truncation mode logging in chat history — model field now shows e.g. " | adaptive(4000,3000,1500)".
* ENHANCEMENT: Default mode changed to Adaptive (4000/3000/1500 chars) — new installations automatically benefit from reduced token consumption while still capturing key content from the most relevant pages.
* ENHANCEMENT: Uniform mode remains available as a configurable option for users who need a fixed per-post limit.
* ENHANCEMENT: Prompt order changed — Critical AI Behavior Rules now sent first, before System Prompt.
* ENHANCEMENT: Admin section Knowledge Base fields reordered to match prompt execution order.
* FIX: LINK INTEGRITY rule updated — prevents AI from using page URL as generic download link for individual items.
* L10N: Added Italian translations for all new strings (Content Truncation, Adaptive Limits, Critical Rules).

= 1.8.0 – FULLTEXT RAG Search =
* FEATURE: Replaced LIKE keyword matching with native MySQL/MariaDB FULLTEXT indexes using MATCH...AGAINST.
* FEATURE: Relevance-scored results (TF-IDF) — pages matching all keywords together rank higher.
* FEATURE: Word-order independent: "tv oled" finds both "TV OLED" and "OLED TV".
* FEATURE: Automatic FULLTEXT index creation on plugin activation — no configuration needed.
* FEATURE: Index self-updates automatically when content changes — zero maintenance.
* ENHANCEMENT: Current frequency-based ranking across multiple search sources (taxonomies + FULLTEXT) is preserved and enhanced with weighted scoring.
* FIX: Fixed PHPCS error — inline `$wpdb->prepare()` inside `get_col()` to satisfy prepared SQL validation.
* FIX: Replaced `echo chr()` UTF-8 BOM output with `fwrite()` on php://output stream for CSV downloads (preserves Excel UTF-8 compatibility).

= 1.7.5 =
* FIX: Fixed PHPCS error — inline `$wpdb->prepare()` inside `get_col()` to satisfy prepared SQL validation.
* FIX: Replaced `echo chr()` UTF-8 BOM output with `fwrite()` on php://output stream for CSV downloads (preserves Excel UTF-8 compatibility).

= 1.7.4 =
* SECURITY: Added sanitize_textarea_field() to $_POST['history'] before JSON decode.
* SECURITY: Added wp_unslash() and sanitize_text_field() to $_SERVER['REMOTE_ADDR'] and $_SERVER['HTTP_X_FORWARDED_FOR'] in IP helper function.
* FIX: Replaced remaining __() with esc_html__() in handle_log_download and handle_log_clear.
* FIX: Replaced date() with gmdate() in CSV filename header.
* FIX: Replaced wp_redirect() with wp_safe_redirect() in handle_log_clear.
* FIX: Removed deprecated load_plugin_textdomain() call (auto-loaded by WP 4.6+).
* FIX: Renamed unprefixed global variables to comply with WordPress naming standards.
* FIX: Plugin name updated to remove trademarked term "WordPress" (both readme.txt and PHP header).
* FIX: Short description shortened to under 150 characters.
* FIX: Replaced fputs() with echo for UTF-8 BOM output to avoid WP_Filesystem false positive.
* FIX: Removed fclose() call before exit() — PHP closes streams automatically on exit.
* FIX: Reduced plugin tags from 6 to 5.
* FIX: Tested up to version corrected from 7.0.2 to 7.0.

= 1.7.3 =
* FEATURE: Migrated chat logs from wp_options to dedicated DB table (`wp_pn_ai_chatbot_logs`) for better performance on high-traffic sites.
* FEATURE: Automatic migration of existing logs to the new table on plugin activation.
* SECURITY: Added `json_last_error()` validation after history JSON decode.
* IMPROVEMENT: uninstall.php now drops the log table if delete_on_uninstall is enabled.

= 1.7.2 =
* SECURITY: Replaced MD5 hashing with wp_hash() for IP rate-limit keys and context cache keys.
* SECURITY: Migrated Gemini API key from URL query parameter to x-goog-api-key header for both model fetch and content generation.
* SECURITY: Added anti-spoofing validation for X-Forwarded-For and Cloudflare Connecting-IP headers.
* SECURITY: Replaced shared admin nonce with unique nonces per AJAX action.
* SECURITY: Replaced esc_attr() with sanitize_text_field() in JSON context in flood analyzer.
* SECURITY: Added wp_suspend_cache_addition() to prevent cache bloat during flood scans.
* SECURITY: Added declare(strict_types=1) to all PHP files.
* REFACTOR: Extracted duplicated CRITICAL RULES string into a class constant.
* REFACTOR: Extracted IP detection logic into standalone helper functions with CIDR validation.
* FIX: Removed duplicate "Stable tag" line from readme.txt.
* IMPROVEMENT: Added XMLHttpRequest fallback for older browsers in chat.js.

= 1.7.1 =
* FIX: Le parole da ignorare vengono ora salvate in un'opzione separata, evitando conflitti con il salvataggio delle impostazioni principali.
* FIX: Added jQuery as script dependency to prevent "jQuery is not defined" error when JS is aggregated.
* IMPROVEMENT: Removed duplicate "Custom Stop Words" field from Settings tab; now managed only in "Parole da Ignorare & Analisi".
* IMPROVEMENT: Clear Italian translations for "Stop Words" and "Flood Words" (now "Parole da Ignorare" and "Parole Ridondanti").
* IMPROVEMENT: Better explanations in admin UI for System Prompt vs Global Knowledge.
* REFACTOR: Complete rebranding to "PointNet AI Chatbot – Virtual Assistant".
* ENHANCEMENT: Internationalized all admin JavaScript error messages.
* FIX: Flood‑word analyzer now searches whole words instead of truncated stems for taxonomy matching.
* SECURITY: Added explicit OpenRouter API key check before attempting requests.
* TWEAK: Improved output buffer handling in AJAX endpoints for cleaner responses.
* FIX: Excluded password‑protected posts from the flood‑word analyzer scan.

= 1.7.0 =
* FEATURE: Added token usage tracking (In / Out / Total) in chat logs – visible in the admin panel.
* SECURITY: Applied `esc_js()` to admin AJAX nonces to prevent potential injection in JS context.
* SECURITY: Flood word analyzer now escapes words with `esc_attr()` before sending them to HTML attributes.
* SECURITY: Chat history restored from sessionStorage now uses a safe JSON array instead of raw innerHTML, preventing DOM‑based XSS.
* SECURITY: SSL verification for model fetch endpoints now defaults to `true` with a filterable override (`pn_rag_ssl_verify`).

= 1.6.9 =
* FIX: Solved a critical 500 Internal Server Error during "Fetch Models" AJAX calls. Upgraded the output buffer cleaner to securely suppress fatal errors on restricted server environments.
* FIX: Applied strict `esc_js()` sanitization to all inline JavaScript strings to prevent syntax breaks caused by localized apostrophes in translation files.

= 1.6.8 =
* FEATURE: Unified Stop Words UI. Manual custom stop words are now forcefully injected at the top of the Analyzer table (highlighted in blue).
* TWEAK: The Analyzer table automatically re-scans and updates its UI dynamically whenever manual words are saved.

= 1.6.7 =
* FEATURE: Interactive Flood Words Management. Added a dedicated manual input box directly inside the Analyzer tab.

= 1.6.6 =
* FEATURE: Flood Word Analyzer (Domain-Specific Stop Words Extraction). Added a brand new, highly optimized mathematical PHP engine inside a dedicated admin tab. It scans up to 500 recent posts/CPTs, tokenizes the text, and calculates Total Frequency (TF), Document Frequency (DF), and Coverage percentage. It then dynamically ranks and suggests domain-specific "Flood Words" (e.g., "cocktail", "recipe" for a mixology blog) that ruin RAG specificity.
* TWEAK: Modular code architecture. Separated the analyzer logic into `admin/flood-analyzer.php` adhering to the Single Responsibility Principle.

= 1.6.5 =
* FEATURE: Dynamic OpenAI Fetch Models. Just like Gemini, administrators can now click a button to retrieve the live list of conversational AI models directly from the OpenAI API (e.g., gpt-4o, o1-mini, o3-mini) avoiding hardcoded values and keeping the plugin future-proof.
* TWEAK: The dynamic UI in the backend now handles the display toggle for the new OpenAI model selection field smoothly.

= 1.6.4 =
* FEATURE: Custom Welcome Message. Added an admin field to set a customized opening greeting.
* FEATURE: Typing Indicator. Replaced the static "Searching" text with a modern CSS-animated 3-dots typing indicator.
* FEATURE: UI Color & Opacity Control. Integrated the native WordPress Color Picker and a custom opacity slider to seamlessly match the chatbot with the site's brand identity.
* UI/UX: Enhanced mobile CSS layout. Fixed chat window overflow issues on narrow mobile screens (e.g., Galaxy S8) with responsive Flexbox properties and max-height clamps.
* UI/UX: Upgraded font legibility inside the chat using adjusted paddings and text-shadows for text and icons.
* FIX: Solved a 500 Internal Server Error occurring during the AJAX fetch of Gemini models, caused by missing output buffer validation.
* TWEAK: All internal comments and strings localized to English to comply strictly with WordPress internationalization standard policies.

= 1.6.3 =
* FEATURE: Hybrid Taxonomy-Aware RAG Engine. Developed a parallel database scanner using raw SQL. If a keyword matches a public taxonomy term, it retrieves all associated object IDs, ranks them by frequency, and merges them with classic text search. This successfully solves WordPress's native search blind spot.
* FEATURE: Dynamic Conversational Memory. Enabled sliding-window memory directly inside the client sessionStorage. The last 3 turns (6 messages) are securely sent via AJAX, formatted natively for each API provider (e.g. alternating user/model for Gemini and user/assistant for OpenAI/OpenRouter) to allow natural follow-up conversations.
* FEATURE: Custom Stop Words. Added a dedicated admin setting to input comma-separated, domain-specific ignored keywords (e.g. 'cocktail, drink, recipe' for mixology) to completely prevent Keyword Flooding, while keeping the PHP core 100% general-purpose.
* FEATURE: RAG Search Depth. Added an admin panel input field (from 1 to 25, default 12) to let administrators balance context precision and API response latency.
* TWEAK: Default Gemini model fallback upgraded to gemini-3.5-flash and added gemini-3.1-flash-lite as a highly recommended rapid responder.
* SECURITY: Re-engineered query security to strictly honor public CPT registration settings, 'exclude_from_search' configs, and WordPress page password protection.

= 1.6.2 =
* FEATURE: Universal Custom Fields Support. The RAG engine now dynamically extracts and reads all public custom fields (ACF, Pods, native meta) across all post types (Products, Custom Post Types) to feed highly detailed, structured context to the AI.
* TWEAK: Modified `WP_Query` to explicitly search across all post types by default.

= 1.6.1 =
* BUGFIX: Implemented the missing `call_openai_api` method to restore functionality for the OpenAI provider.
* SECURITY: Enforced the rate limiting rules (per minute, daily IP, global daily) using transients to prevent API abuse.
* SECURITY: Fixed a CSV injection vulnerability during chat log exports.

= 1.6.0 =
* FEATURE: Enhanced RAG engine. The algorithm now processes full page content (removing the previous 800-character limit) and aggressively strips page builder shortcodes for absolute precision.
* FEATURE: Increased AI output generation limit to 1500 tokens to ensure comprehensive answers are never cut off mid-sentence.
* SECURITY: Introduced the "Anti-Monologue" (Thinking Leak) filter. The Bouncer now actively detects and rejects models that "think out loud" or leak system reasoning in English.
* TWEAK: Updated the default OpenRouter priority models list to ensure maximum stability, speed, and instruction-following capabilities.
* TWEAK: Cleaned up the WordPress admin menu by removing redundant log links, fully consolidating everything under the main Settings page.

= 1.5.0 =
* FEATURE: Implemented "Self-Optimizing System" architecture for OpenRouter.
* FEATURE: Added admin setting to define a comma-separated list of priority models.
* SECURITY: Added the "Bouncer" (Quality Control) to filter out garbage and safety-blocked responses from the wildcard model.
* UX: Implemented delayed auto-scroll on page change and improved the Markdown link regex parser.
* ADMIN: Added Latency Tracking (response times in seconds) and detailed fallback pathing to the Chat Logs.

= 1.4.1 =
* SECURITY: Masked API keys visually in the WordPress backend.
* GDPR: Added automatic GDPR disclaimer in the chat window with dynamic link to the WordPress Privacy Policy page.
* GDPR: Added administrative SaaS compliance notices with privacy policy links.
* Performance: Set `autoload` to `false` on logs storage to prevent database autoload memory bloat.

= 1.4.0 =
* UI/UX: Added "Settings" quick link to the Plugins list table.
* UI/UX: Refactored the admin panel to use native WordPress horizontal tabs, consolidating settings and logs under a single menu.

= 1.3.0 =
* Implemented chat persistence across page loads using `sessionStorage`.
* Added settings field for custom Contact Page URL.
* Redesigned user interface with right-aligned vertical toggle button, SVG icon, and markdown HTML parser.

= 1.2.0 =
* Added internationalization (i18n) support with `.po` and `.mo` language files.
* Introduced initial Chat Logs screen with CSV download capability.
* Developed multi-level rate-limiting protection.

= 1.0.0 =
* Initial release. Basic setup and settings page.