=== Mockomatic ===
Contributors: pbalazs
Tags: ai, content, openai, gemini, replicate
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate realistic demo sites with AI. Create posts, pages, categories, tags, and featured images in one run using your own OpenAI, Google Gemini, or Replicate API keys.

== Description ==

Mockomatic is a clean, free WordPress plugin that builds believable demo content directly inside wp-admin. Create titles, full Gutenberg block content, taxonomies, and optional featured images in a single pass—perfect for staging sites, client prototypes, and theme previews.

**Key capabilities**
- Generate posts and pages with structured Gutenberg block markup
- Auto-create categories and tags that stay coherent across generated content
- Optional featured images via Replicate image models
- Progress log with stop-friendly handling and quick links to edit results
- BYOK (Bring Your Own Key) for OpenAI, Google Gemini, and Replicate

Unlike many AI plugins, Mockomatic has:
- **No ads or upsells**
- **Native-like UI** that lives under the Mockomatic menu in wp-admin
- **Structured outputs** ready for immediate editing
- **Transparent code** that follows WordPress best practices

### Features

- **AI Content Generation**
  - Batch-generate titles and full Gutenberg content for posts and pages
  - Per-run controls for counts, text models, taxonomy creation, and illustration prompts
  - Uses clean block markup (paragraphs, headings, lists, quotes, buttons, columns, etc.)

- **Featured Images (Replicate)**
  - Optional featured images per post
  - Choosable Replicate models; images upload to the Media Library automatically

- **Taxonomy Awareness**
  - Generates categories and tags and assigns them to posts
  - Cleans up “Uncategorized” when new categories are set

- **Progress & Control**
  - Live progress meter and log
  - Stop generation mid-run without losing created items
  - Inline edit links to jump back into the WordPress editor

### Supported AI Models

**Text (OpenAI):** `gpt-5.1`, `gpt-5`, `gpt-5-mini`, `gpt-5-nano`, `gpt-5-chat-latest`, `gpt-4.5-preview`, `gpt-4.1`, `gpt-4.1-mini`, `gpt-4.1-nano`, `gpt-4o`, `gpt-4o-mini`, `chatgpt-4o-latest`  
**Text (Google Gemini/Gemma):** `gemini-3-pro-preview`, `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-2.5-flash-lite`, `gemma-3-27b-it`  
**Images (Replicate):** `google/nano-banana-pro`, `google/gemini-2.5-flash-image`, `google/imagen-4`, `google/imagen-4-ultra`, `google/imagen-4-fast`, `google/imagen-3`, `google/imagen-3-fast`, `black-forest-labs/flux-1.1-pro`, `black-forest-labs/flux-dev`, `black-forest-labs/flux-schnell`, `black-forest-labs/flux-pro`, `recraft-ai/recraft-v3`, `ideogram-ai/ideogram-v3-turbo`, `ideogram-ai/ideogram-v3-quality`, `ideogram-ai/ideogram-v3-balanced`, `bytedance/seedream-4.5`

### External Services

The plugin calls third-party APIs for AI generation. No data is sent until you add your credentials in settings.

**OpenAI**  
- Used for text generation (`gpt-*`).  
- Data sent: prompts and optional site context.  
- [Terms of Use](https://openai.com/policies/terms-of-use)  
- [Privacy Policy](https://openai.com/policies/privacy-policy)  

**Google Generative AI**  
- Used for text generation (`gemini-*`, `gemma-*`).  
- Data sent: prompts and optional site context.  
- [Terms of Service](https://policies.google.com/terms)  
- [Privacy Policy](https://policies.google.com/privacy)  

**Replicate API**  
- Used for image generation (Flux, Recraft, Ideogram, Imagen, Nano Banana, Seedream, etc.).  
- Data sent: prompts and optional illustration descriptions.  
- [Terms of Service](https://replicate.com/terms)  
- [Privacy Policy](https://replicate.com/privacy)  

---

== Installation ==

1. Upload the `mockomatic` folder to `/wp-content/plugins/`, or install it via **Plugins → Add New**.
2. Activate **Mockomatic** from the Plugins menu.
3. Go to **Mockomatic → Settings** and enter your API keys (OpenAI, Google Gemini, Replicate).
4. Optional: Set default text and image models.
5. Open **Mockomatic → Generate Content** and start generating posts/pages.

---

== Frequently Asked Questions ==

= Do I need API keys? =  
Yes. Provide your own keys for OpenAI, Google Gemini, and/or Replicate in the settings page.

= Can I disable image generation? =  
Yes. Leave featured images off or select “Off” in the image model selector; text generation continues to work.

= Where are generated items stored? =  
Posts/pages are published normally. Featured images are uploaded to the Media Library and attached to the posts.

= Can I stop a run midway? =  
Yes. Use the “Stop generating” button; completed items stay in WordPress, and remaining queued items are marked as stopped.

---

== Screenshots ==

1. Generate demo content inside wp-admin  
2. Live progress log and task list  
3. AI model and prompt configuration  
4. Settings page for API keys and defaults  

---

== Changelog ==

= 0.1.0 =  
* Initial release with AI-generated posts/pages, taxonomy creation, Gutenberg block output, and optional featured images via Replicate.

---

== License ==

This plugin is licensed under the GPLv2 or later.  
https://www.gnu.org/licenses/gpl-2.0.html
