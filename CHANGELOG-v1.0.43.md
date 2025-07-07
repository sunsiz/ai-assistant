# AI Assistant Plugin v1.0.43 - Changelog

## 🚀 Major Improvements & Fixes

### 🎯 Metabox Language Default Fix
- **Fixed**: Metabox translation dropdowns now default to the site language instead of hardcoded English
- **Enhanced**: Added `get_site_language_code()` method to map WordPress locales to plugin language codes
- **Supports**: 20+ languages including Chinese, Turkish, Arabic, Uyghur, and more

### 💾 Database Translation History
- **Fixed**: Translations are now properly saved to the database for history tracking
- **Enhanced**: Made `save_translation_history()` method public for broader use
- **Added**: Post ID association for better translation tracking
- **Improved**: Translation history page will now show all translation activities
- **CRITICAL FIX**: Database table structure mismatches causing save failures
- **Added**: Automatic table creation and column updates for existing installations
- **Enhanced**: Better error handling and validation for translation data
- **Improved**: Translation history page shows URLs, posts, and manual translations distinctly
- **Added**: Column addition order fix (post_id before source_url)
- **Added**: Automatic table recreation with backup for corrupted structures
- **Added**: Self-healing database errors with retry mechanism

### 🧹 Code Optimization & Cleanup
- **Removed**: All redundant test, debug, and fix files (15+ files cleaned up)
  - `test-mo-compilation.php`
  - `metabox-fix-test.php` 
  - `chinese-translation-test.php`
  - `debug-language-loading.php`
  - `fix-chinese-site.php`
  - And many more...
- **Removed**: Unused `translate_from_url()` method
- **Removed**: Unused `store_translation()` method
- **Optimized**: Reduced excessive debug logging while keeping important error logs

### 🌍 Translation System Enhancements
- **Enhanced**: Universal translation test tool with better error handling
- **Improved**: Site language detection and mapping
- **Fixed**: JavaScript properly sends post_id with translation requests
- **Maintained**: All existing language loading mechanisms

### 🔧 Version Management
- **Updated**: Plugin version from 1.0.42 to 1.0.43
- **Updated**: Version constant and plugin header

## 📁 File Structure Improvements

### ✅ Kept Essential Files
- `ai-assistant.php` - Main plugin file
- `includes/class-admin.php` - Admin interface
- `includes/class-translator.php` - Translation logic
- `assets/js/editor.js` - Frontend functionality
- `universal-translation-test.php` - Diagnostic tool

### 🗑️ Removed Redundant Files
- All test-*.php files
- All debug-*.php files  
- All fix-*.php files
- All diagnose-*.php files

## 🎯 Technical Details

### Language Code Mapping
```php
// Supports comprehensive locale mapping
'zh_CN' => 'zh'    // Chinese Simplified
'zh_TW' => 'zh'    // Chinese Traditional  
'tr_TR' => 'tr'    // Turkish
'ug' => 'ug'       // Uyghur
// And 20+ more languages...
```

### Database Integration
```php
// Translations now save with full context
$this->save_translation_history(array(
    'post_id' => $post_id,
    'source_language' => $source_lang,
    'target_language' => $target_lang,
    'original_content' => $content,
    'translated_content' => $result,
    'model' => $model
));
```

### JavaScript Enhancement
```javascript
// Site language detection for dropdowns
const siteLanguage = aiAssistant.siteLanguage || 'en';
$select.val(siteLanguage);
```

## 🔍 Testing & Validation

### Diagnostic Tool
The enhanced `universal-translation-test.php` now provides:
- Complete translation system analysis
- .mo file content verification
- Force reload testing for problematic languages
- Chinese character detection in translation files
- Comprehensive error handling

### Translation History Debug Tool
New `debug-translation-history.php` provides:
- Database table structure verification
- Translation history entry analysis
- Manual database testing capabilities
- Error diagnostics and troubleshooting
- **Force table recreation** with automatic backup
- Self-healing table structure verification

### Usage
- Translation diagnostics: Add `?ai_test_translations=1` to any admin page URL
- History diagnostics: Add `?debug_translation_history=1` to any admin page URL
- Test database insert: Add `&test_insert=1` to history diagnostic URL
- **Force table recreation**: Add `&force_recreate=1` to history diagnostic URL

## 🚨 Breaking Changes
None - All changes are backward compatible.

## 📋 Next Steps for Users

1. **Test the metabox**: Create/edit a post and verify target language defaults to your site language
2. **Check translation history**: Perform translations and verify they appear in the admin translation history page
3. **Run diagnostics**: Use `?ai_test_translations=1` if any issues persist
4. **Clear caches**: Clear any caching plugins if translations don't update immediately

## 🔗 Related Issues Resolved

- Metabox target language defaulting to English instead of site language
- Translations not being saved to database/history
- Translation history page showing empty results
- Excessive test files cluttering the plugin directory
- Redundant functions and methods
- Over-aggressive debug logging

---

**Version**: 1.0.43  
**Release Date**: July 2, 2025  
**Compatibility**: WordPress 5.0+ / PHP 7.4+
