# AI Assistant for WordPress v1.0.56

🚀 **Professional AI-powered translation and content generation plugin for WordPress**

[![Version](https://img.shields.io/badge/version-1.0.56-blue.svg)](https://github.com/sunsiz/ai-assistant)
[![License](https://img.shields.io/badge/license-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org)

## 🌟 What's New in v1.0.56

### 🔧 **Fixed Issues**
- **✅ Diagnostics API Testing**: Fixed HTTP 503 errors and long timeouts in diagnostics page
- **✅ User-Specific Languages**: Added missing Turkish translations for new user-specific language features
- **✅ API Connection Testing**: Optimized connection tests with lightweight endpoints and better error handling

### 🌍 **Translation Updates**
- **Turkish (Türkçe)**: 418 strings (100% complete) 🇹🇷
- **Chinese (中文)**: 419 strings (100% complete) 🇨🇳  
- **Uyghur (ئۇيغۇرچە)**: 418 strings (100% complete)
- **Total**: 14 languages with 5,846+ translated strings

### 🏗️ **Technical Improvements**
- Enhanced API connection diagnostics with faster, more reliable testing
- Better error handling and user feedback
- Optimized .mo file compilation system
- Clean project structure and organized codebase

A comprehensive AI-powered translation and content writing assistant plugin for multilingual WordPress websites.

## Features

### 🌐 Translation Capabilities
- **URL-based Translation**: Paste a post URL and translate content from source to target language
- **Direct Translation**: Translate content directly within the editor
- **Auto Language Detection**: Automatically detect source language
- **Side-by-side Editing**: Review and edit translations with original content comparison
- **Multiple AI Models**: Support for OpenAI GPT, Anthropic Claude, and Google Gemini

### ✍️ Writing Assistant
- **Content Suggestions**: AI-powered content suggestions based on title and website history
- **Real-time Assistance**: Get suggestions while writing
- **Content Improvement**: Enhance existing content for better readability, SEO, grammar, or tone
- **SEO Keywords**: Generate SEO-friendly keywords for better search performance
- **Smart Context**: Suggestions based on your website's existing content

### ⚙️ Configuration & Management
- **Multiple AI Providers**: Configure API keys for different AI services
- **Custom Prompts**: Customize AI prompts for different tasks
- **Model Selection**: Choose different AI models for different tasks
- **Translation History**: Track and manage all translations
- **Usage Statistics**: Monitor plugin usage and performance

### 🎨 User Interface
- **Gutenberg Blocks**: Native WordPress block editor integration
- **Admin Dashboard**: Comprehensive settings and management interface
- **Responsive Design**: Works on all device sizes
- **Accessibility**: Full accessibility support

## Supported Languages

- Turkish (tr) - Main language
- English (en)
- French (fr)
- German (de)
- Chinese (zh)

## Supported AI Models

### OpenAI
- GPT-4
- GPT-4 Turbo
- GPT-3.5 Turbo

### Anthropic
- Claude 3 Opus
- Claude 3 Sonnet
- Claude 3 Haiku

### Google
- Gemini Pro
- Gemini Pro Vision

## Installation

1. **Download** the plugin files
2. **Upload** to your WordPress `/wp-content/plugins/` directory
3. **Activate** the plugin through the WordPress admin
4. **Configure** API keys in Settings > AI Assistant
5. **Start using** the AI blocks in your posts and pages

## Configuration

### API Keys Setup

1. Go to **AI Assistant > Settings**
2. Add your API keys for desired providers:
   - **OpenAI**: Get from [OpenAI API Keys](https://platform.openai.com/api-keys)
   - **Anthropic**: Get from [Anthropic Console](https://console.anthropic.com/)
   - **Google**: Get from [Google AI Studio](https://makersuite.google.com/app/apikey)

### Custom Prompts

Customize AI prompts for different tasks:
- **Translation Prompt**: Use `{source_language}` and `{target_language}` placeholders
- **Content Suggestion Prompt**: Customize how AI suggests content
- **SEO Keywords Prompt**: Customize keyword generation

## Usage

### Translation Block

1. Add the **AI Translation** block to your post
2. Choose to translate from URL or direct content
3. Select source and target languages
4. Click translate and review results
5. Edit the translation as needed

### Content Suggestions Block

1. Add the **AI Content Suggestions** block
2. Enter your article title
3. Get AI-powered content suggestions
4. Copy or apply suggestions to your content

### Writing Assistant Block

1. Add the **AI Writing Assistant** block
2. Write or paste your content
3. Choose improvement type (general, SEO, grammar, tone)
4. Get AI suggestions for improvement
5. Apply or copy suggestions

## Development

### Prerequisites
- Node.js 16+
- npm 7+
- WordPress 5.8+
- PHP 7.4+

### Setup
```bash
# Install dependencies
npm install

# Start development
npm run dev

# Build for production
npm run build

# Run tests
npm test

# Create plugin zip
npm run zip
```

### File Structure
```
ai-assistant/
├── ai-assistant.php          # Main plugin file
├── includes/                 # PHP classes
│   ├── class-admin.php
│   ├── class-api.php
│   ├── class-ai-service.php
│   ├── class-blocks.php
│   ├── class-content-analyzer.php
│   ├── class-settings.php
│   └── class-translator.php
├── assets/                   # Frontend assets
│   ├── js/
│   │   ├── blocks.js
│   │   └── admin.js
│   └── css/
│       ├── blocks.css
│       └── admin.css
├── languages/               # Translation files
└── README.md
```

## Hooks and Filters

### Actions
- `ai_assistant_translation_complete` - Fired after translation completion
- `ai_assistant_content_suggested` - Fired after content suggestion
- `ai_assistant_api_error` - Fired on API errors

### Filters
- `ai_assistant_supported_languages` - Modify supported languages
- `ai_assistant_available_models` - Modify available AI models
- `ai_assistant_default_prompts` - Modify default prompts

## Troubleshooting

### Common Issues

**API Key Errors**
- Verify API keys are correct
- Check API key permissions
- Ensure sufficient API credits

**Translation Not Working**
- Check internet connection
- Verify source content is not empty
- Try different AI model

**Content Suggestions Empty**
- Ensure website has published content
- Try different titles
- Check AI model availability

### Debug Mode

Enable WordPress debug mode to see detailed error logs:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Security

- All API keys are stored securely in WordPress options
- User capabilities are checked for all operations
- Nonce verification for all AJAX requests
- Content sanitization and validation
- SQL injection prevention

## Performance

- Efficient database queries with proper indexing
- Caching of AI responses where appropriate
- Async loading of non-critical resources
- Optimized for large content volumes

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## Changelog

### Version 1.0.0
- Initial release
- Translation functionality
- Content suggestions
- Writing assistant
- Multiple AI provider support
- Admin dashboard and settings

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support, please:
1. Check this documentation
2. Search existing issues
3. Create a new issue with detailed information

## Credits

- WordPress team for the excellent platform
- AI service providers for their APIs
- Contributors and testers

---

**Note**: This plugin requires API keys from AI service providers. Usage costs depend on your chosen provider and usage volume.
