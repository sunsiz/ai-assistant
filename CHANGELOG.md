# Changelog

All notable changes to AI Assistant for WordPress will be documented in this file.

## [1.0.56] - 2025-07-08

### 🔧 Fixed
- **Diagnostics API Testing**: Resolved HTTP 503 errors and timeout issues in diagnostics page
- **API Connection Tests**: Implemented lightweight endpoint testing instead of heavy translation requests
- **User Language Interface**: Added missing Turkish translations for new user-specific language strings
- **Error Handling**: Enhanced error messages and response codes for better debugging

### 🌍 Added
- **Turkish Translations**: Added "Language settings saved successfully. This is your personal language setting for AI Assistant." and "Select your personal language for the AI Assistant interface..."
- **API Test Optimization**: New lightweight API connection testing methods for faster diagnostics
- **Better User Feedback**: Improved status messages and error reporting in diagnostics

### 🏗️ Technical
- Enhanced diagnostics implementation with proper error handling
- Optimized API testing timeouts (reduced to 10 seconds)
- Improved .mo file compilation system
- Clean project structure and removed temporary files
- Updated all translation files compilation (14 languages, 5,846+ strings)

### 📊 Translation Status
- Turkish (tr_TR): 418 strings (100% complete)
- Chinese (zh_CN): 419 strings (100% complete)  
- Uyghur (ug_CN): 418 strings (100% complete)
- Total compiled: 14 language files

---

## [1.0.55] - 2025-07-07

### 🚀 Major Features
- **User-Specific Language System**: Each user can now select their own preferred language for AI Assistant
- **Individual Language Preferences**: Plugin language selection no longer affects WordPress core or other users
- **User Meta Storage**: Language preferences saved per-user account

### 🔧 Fixed  
- **Global Locale Filter Issue**: Removed problematic global locale filters that were changing entire WordPress site language
- **Translation Loading**: Enhanced custom textdomain loading with fallback mechanisms
- **Language Isolation**: Plugin language changes now isolated to individual users

### 🌍 Translation Updates
- Added user-specific language description strings in Chinese and Uyghur
- Updated all translation files with new user-specific language features
- Complete Chinese (zh_CN) and Uyghur (ug_CN) translations for all new strings

### 🏗️ Technical
- Refactored language loading system in includes/class-admin.php
- Implemented get_user_language() helper method
- Updated settings interface for user-specific language selection
- Enhanced error logging for language system debugging

---

## [1.0.54] - 2025-07-04

### 🌍 Translation Improvements
- **Complete Uyghur Translation**: Fixed all remaining English strings in Uyghur language file
- **Native Text Implementation**: Replaced placeholder English text with proper Uyghur translations
- **API Status Messages**: Added Uyghur translations for diagnostic and system terms

### 🔧 Technical Fixes
- Updated 19 diagnostic and system message translations in Uyghur
- Enhanced .mo file compilation for Uyghur language
- Improved translation quality for technical terminology

---

## [1.0.53] - 2025-07-03

### 🌍 Major Translation Update
- **Complete Chinese Translation**: Achieved 100% translation coverage for Chinese (zh_CN)
- **Uyghur Translation**: Comprehensive Uyghur (ug_CN) translation implementation
- **Professional Terminology**: All technical terms properly translated with native vocabulary

### 🚀 New Features
- **Multilingual Interface**: Full native language support for Chinese and Uyghur users
- **Cultural Adaptation**: Translations adapted for cultural and linguistic accuracy
- **Technical Documentation**: All help text and instructions translated

### 🔧 Technical
- Enhanced .po/.mo file management system
- Automated translation compilation workflow
- Improved character encoding for non-Latin scripts
- Better font and display support for Asian languages

---

## [Earlier Versions]

Previous versions focused on core functionality development, API integrations, and basic multilingual support. For complete version history, see Git commit log.

---

**Note**: This plugin follows [Semantic Versioning](https://semver.org/) principles.
