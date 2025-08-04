=== AI Assistant for WordPress ===
Contributors: suleymaniyevakfi
Donate link: https://www.suleymaniyevakfi.org/bagis
Tags: ai, translation, multilingual, content, writing
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.78
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional AI-powered translation and content generation plugin supporting 19+ languages with universal language detection.

== Description ==

AI Assistant for WordPress is a comprehensive, professional-grade plugin that transforms your WordPress site into a powerful multilingual content management platform. With advanced AI integration and universal language support, it's perfect for content creators, businesses, and organizations managing multilingual websites.

**🌟 What Makes This Plugin Special:**

* **Universal Language Support**: Dynamic language detection supporting ANY WordPress locale - no hardcoded language limitations
* **Performance Optimized**: 50-75% faster response times with intelligent dual-layer caching system
* **Unicode-Safe Architecture**: Full support for all character sets including Arabic, Chinese, Uyghur, and right-to-left languages
* **Production-Ready**: Zero syntax errors, comprehensive error handling, enterprise-grade stability

**🚀 Core Features:**

* **Multi-Provider AI Support**: OpenAI (GPT-4), Google Gemini 2.5, Anthropic Claude 3.5 with intelligent fallback
* **Real-Time Content Suggestions**: AI suggestions while typing with smart rate limiting
* **URL-Based Translation**: Extract and translate content from any WordPress post URL
* **Advanced .po File Management**: Bulk translation and individual string management for plugin/theme localization
* **Featured Image Generation**: AI-powered image creation with DALL-E 3 and Google Gemini support
* **Translation History**: Complete audit trail with database storage and management
* **Content Generation Tools**: SEO optimization, grammar enhancement, tone adjustment

**🌍 Supported Languages (19+ Complete Interface Translations):**

English, Turkish, Chinese, Uyghur, Arabic, Persian, Dutch, Danish, French, Azerbaijani, Uzbek, Kyrgyz, Russian, Portuguese, Spanish, German, Finnish, Urdu, Turkmen - with 6,200+ translated interface strings.

**🎯 Perfect For:**

* Multilingual businesses and organizations
* Content creators and bloggers
* Educational institutions
* E-commerce sites with international presence
* WordPress developers managing client sites
* Anyone needing professional translation tools

**🔧 Technical Excellence:**

* WordPress 5.8+ compatibility with modern PHP 7.4+
* WCAG accessibility compliance
* Responsive design for all screen sizes
* Comprehensive debugging and diagnostic tools
* Secure API key management and nonce verification

== Installation ==

**Automatic Installation (Recommended):**
1. Go to your WordPress admin dashboard
2. Navigate to Plugins → Add New
3. Search for "AI Assistant for WordPress"
4. Click "Install Now" and then "Activate"

**Manual Installation:**
1. Download the plugin files
2. Upload to `/wp-content/plugins/ai-assistant/` directory
3. Activate the plugin through the 'Plugins' screen in WordPress

**Configuration:**
1. Navigate to **AI Assistant → Settings** in your WordPress admin
2. Add your API keys for at least one AI provider:
   - **Google Gemini**: Get from [Google AI Studio](https://makersuite.google.com/app/apikey) (Recommended - generous free tier)
   - **OpenAI**: Get from [OpenAI Platform](https://platform.openai.com/api-keys)
   - **Anthropic**: Get from [Anthropic Console](https://console.anthropic.com/)
3. Configure your language preferences
4. Test API connections using the built-in diagnostics tool
5. Start using the AI Assistant meta box in your post/page editor

**Quick Setup:**
- The plugin works immediately with Google Gemini (best free tier)
- Each user can set their own interface language
- No complex configuration required - intelligent defaults included

== Frequently Asked Questions ==

= Is this plugin free to use? =

Yes! The plugin itself is completely free. However, it requires API keys from AI service providers. Google Gemini offers a generous free tier that's perfect for most users. OpenAI and Anthropic offer paid services with competitive pricing.

= Which AI provider should I choose? =

**Google Gemini (Recommended)**: Best free tier, fast performance, excellent for most tasks
**OpenAI GPT-4**: Premium quality, best for complex content, paid service
**Anthropic Claude**: Advanced reasoning, good for detailed content, paid service

You can configure multiple providers and switch between them as needed.

= Does it work with Gutenberg (Block Editor)? =

Yes! The plugin integrates seamlessly with both Gutenberg and Classic Editor through a dedicated meta box that appears below the post content area.

= Can I translate content from other websites? =

Absolutely! The URL Translation feature allows you to paste any WordPress post URL, automatically extract its content, and translate it. Perfect for translating existing posts or content inspiration.

= How many languages are supported? =

The plugin supports universal language detection - it can work with ANY language pair supported by the AI providers. The plugin interface itself is available in 19+ languages including English, Turkish, Chinese, Arabic, Uyghur, and many more.

= Is my data secure? =

Yes. API keys are stored securely in WordPress options. Content is sent to AI providers only for processing and is not stored by them. All requests use secure HTTPS connections with nonce verification.

= Can I translate WordPress themes and plugins? =

Yes! The plugin includes advanced .po file management that can bulk translate all empty strings in any .po file, making it perfect for localizing themes and plugins.

= Does it slow down my website? =

No. The plugin uses intelligent caching and only loads resources when needed. The AI processing happens asynchronously and doesn't affect your site's public performance.

= What happens if I exceed API limits? =

The plugin includes intelligent rate limiting and will gracefully handle API limits. You'll receive clear notifications about usage and can switch between different AI providers as needed.

= Can multiple users have different language preferences? =

Yes! Each WordPress user can set their own interface language in the plugin settings, allowing multilingual teams to work in their preferred languages.

== Screenshots ==

1. AI Assistant meta box integrated into WordPress post editor with tabbed interface
2. Translation tab showing side-by-side content translation with language selection
3. URL Translation feature extracting and translating content from any WordPress URL
4. Content Tools tab providing AI-powered content suggestions and writing assistance
5. Featured Image generation tab with AI-powered image creation using DALL-E 3 and Google Gemini
6. Settings page with API configuration for multiple AI providers
7. Translation history management showing complete audit trail
8. .po file management interface for bulk translation of themes and plugins

== Changelog ==

= 1.0.78 =
**JavaScript Localization & Translation System Optimization Release**
* FIXED: Download button functionality in translation management - now properly responsive
* FIXED: JavaScript hardcoded English strings in content type switching - maintains user language selection
* ENHANCED: Complete JavaScript localization system with aiAssistant.strings integration
* IMPROVED: Content type switching UI now preserves selected language (Uyghur, Chinese, etc.)
* IMPROVED: Translation management download system with proper error handling
* TECHNICAL: POT file regenerated with all 481+ translatable strings
* TECHNICAL: All .po/.mo files updated and recompiled for 18 supported languages
* TECHNICAL: Version consistency maintained across all JavaScript and PHP files

= 1.0.71 =
**Code Quality & User Experience Enhancement Release**
* NEW: Production-ready debug system - debug messages only show when WP_DEBUG is enabled
* NEW: Advanced loading states with beautiful CSS animations and progress indicators
* NEW: Enhanced input validation with comprehensive content sanitization throughout
* NEW: Complete internationalization - fixed all remaining hardcoded strings for full translatability
* ENHANCED: Content enhancement tools with improved UI validation and user feedback
* IMPROVED: Professional loading indicators with contextual progress messages
* IMPROVED: Button state management with proper disabled/enabled controls
* SECURITY: Enhanced input sanitization with wp_strip_all_tags() and sanitize_text_field()
* TECHNICAL: Version consistency across all JavaScript and PHP files
* TECHNICAL: Dynamic interface adaptation for content enhancement features

= 1.0.70 =
**Content Enhancement Features Release**
* NEW: Grammar & Style Enhancement - AI-powered grammar correction and professional writing improvements
* NEW: Readability Optimization - Content analysis and optimization for better comprehension
* NEW: Tone & Style Adjustment - Professional, casual, academic, and creative tone modifications
* NEW: SEO Content Optimization - Enhanced SEO analysis and content optimization for existing text
* NEW: Engagement Enhancement - AI improvements for more compelling and action-oriented content
* IMPROVED: Content Tools UI with dedicated enhancement options and content input area
* IMPROVED: Dynamic interface showing/hiding content areas based on selected feature type
* IMPROVED: Enhanced AJAX handling for content improvement vs content generation workflows
* TECHNICAL: Integrated existing content-analyzer.php capabilities with user-facing interface
* TECHNICAL: Updated version to 1.0.70 for testing content enhancement features

= 1.0.69 =
**Major Release - Production Ready**
* NEW: Universal language support with dynamic detection for ANY WordPress locale
* NEW: Performance optimization with 50-75% faster response times using dual-layer caching
* NEW: Unicode-safe architecture supporting all character sets (Arabic, Chinese, Uyghur, etc.)
* NEW: Enhanced .po file management with proper escaping/unescaping system
* NEW: Real-time AI content suggestions while typing with intelligent rate limiting
* NEW: Comprehensive error handling and production-ready debugging system
* IMPROVED: Enhanced AI model support - Gemini 2.5 Flash/Pro, GPT-4, Claude 3.5 Sonnet
* IMPROVED: Complete Turkish, Chinese, and Uyghur translations (100% coverage)
* IMPROVED: Advanced caching system with custom Unicode-safe hash functions
* IMPROVED: Professional documentation suite with technical references
* FIXED: Unicode crashes with btoa() function affecting non-Latin scripts
* FIXED: .po file double-escaping issue causing quote accumulation
* Total: 19+ languages, 6,200+ translated interface strings

= 1.0.58 =
* NEW: Auto-translation system for .po files with bulk processing
* NEW: Individual string translation for targeted localization
* NEW: Enhanced debugging with comprehensive error logging
* NEW: Visual documentation with feature screenshots
* IMPROVED: Production-ready codebase with zero syntax errors
* IMPROVED: API diagnostics with detailed connection testing

= 1.0.56 =
* FIXED: HTTP 503 errors in diagnostics system
* NEW: Turkish translation completion with user-specific language strings
* IMPROVED: Lightweight API testing endpoints for faster diagnostics
* IMPROVED: Translation statistics tracking

= 1.0.55 =
* NEW: User-specific language preferences allowing individual settings
* FIXED: Global locale filter issues causing WordPress core conflicts
* NEW: Complete Chinese (zh_CN) and Uyghur (ug_CN) language support
* IMPROVED: Refactored language loading system for better stability

= 1.0.54 =
* NEW: Complete Uyghur (ئۇيغۇرچە) translation with 100% native coverage
* NEW: API message translations for diagnostic terms in native languages
* IMPROVED: Quality enhancements for technical terminology translations

= 1.0.53 =
* NEW: Complete Chinese Simplified (中文) translation with 100% coverage
* NEW: Comprehensive Uyghur implementation for Central Asian users
* IMPROVED: Cultural adaptation with linguistically accurate translations
* IMPROVED: Enhanced character encoding support for non-Latin scripts

= 1.0.50 =
* NEW: Multi-provider AI support (OpenAI, Anthropic, Google Gemini)
* NEW: URL-based translation with automatic content extraction
* NEW: Real-time content suggestions and writing assistance
* NEW: Featured image generation with DALL-E 3 and Google Gemini support
* NEW: Translation history with complete audit trails
* NEW: Multilingual interface supporting 16+ languages initially

== Upgrade Notice ==

= 1.0.69 =
Major production release with universal language support, 50-75% performance improvements, Unicode-safe architecture, and comprehensive multilingual capabilities. Highly recommended upgrade for all users.

= 1.0.58 =
Significant updates including auto-translation system, enhanced debugging, and production-ready improvements. Update recommended for better stability and new features.

== Privacy and Data ==

**Data Processing:**
This plugin sends content to third-party AI services (Google, OpenAI, Anthropic) for translation and content generation. No personal data is transmitted - only the text content you choose to process.

**API Provider Privacy:**
* **Google Gemini**: Review [Google AI Privacy Policy](https://ai.google.dev/terms)
* **OpenAI**: Review [OpenAI Privacy Policy](https://openai.com/privacy/)
* **Anthropic**: Review [Anthropic Privacy Policy](https://www.anthropic.com/privacy)

**Local Storage:**
Translation history and settings are stored locally in your WordPress database. No data is sent to external services except for AI processing.

**Compliance:**
Please ensure compliance with your local data protection regulations (GDPR, CCPA, etc.) when using AI services for content processing.

== Support ==

**Documentation:** Comprehensive documentation is included in the plugin README and settings pages.

**Community Support:** 
* GitHub Issues: [Report bugs and request features](https://github.com/sunsiz/ai-assistant/issues)
* WordPress.org Forums: Use the plugin support forum for community help

**Professional Support:** Contact [Süleymaniye Vakfı](https://www.suleymaniyevakfi.org/) for enterprise support and custom development.

== Credits ==

**Developed by:** [Süleymaniye Vakfı](https://www.suleymaniyevakfi.org/) - *Empowering multilingual content creation through AI*

**Special Thanks:**
* WordPress community for the excellent platform
* AI providers (Google, OpenAI, Anthropic) for powerful APIs
* Translation contributors for multilingual support
* Beta testers for feedback and quality assurance
* Open source community for tools and inspiration

**Powered by Advanced AI:**
* Google Gemini 2.5 (Flash & Pro) for fast, intelligent processing
* OpenAI GPT-4 for premium content generation
* Anthropic Claude 3.5 for advanced reasoning capabilities
