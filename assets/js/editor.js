/**
 * AI Assistant Editor JS - v1.0.53
 * Handles meta box UI, tabs, and AJAX calls for translation, content generation, and image generation.
 */
(function($) {
    'use strict';

    const AIAssistant = {
        init: function() {
            // Ensure we are on a post edit screen with the meta box present
            if (!$('#ai-assistant-meta-box').length) {
                return;
            }
            
            console.log('AI Assistant: Initializing editor script v1.0.53');
            this.initializeTabs();
            this.populateLanguageDropdowns();
            this.bindEvents();
            this.setupTextareaAutoResize();
            this.setupAutoComplete();
            
            // Populate AI model dropdowns with configured models only
            this.populateModelDropdowns();
        },

        bindEvents: function() {
            var container = $('#ai-assistant-meta-box');

            // Intro section toggle
            container.on('click', '.ai-intro-toggle', this.handleIntroToggle);

            // Tab switching - updated for WordPress nav-tab structure
            container.on('click', '.nav-tab', this.handleTabSwitch);

            // Translate Tab Actions
            container.on('click', '.ai-assistant-translate-btn', this.handleTranslateContent);
            container.on('click', '.ai-assistant-populate-btn', this.handlePopulateContent);            // URL Tab Actions
            container.on('click', '.ai-fetch-content-btn', this.handleFetchUrl);
            container.on('click', '.ai-translate-article-btn', this.handleTranslateArticle);
            container.on('click', '.ai-insert-content-btn', this.handleInsertContent);
            
            // Content Tools Tab Actions
            container.on('click', '.ai-use-post-title-btn', this.handleUsePostTitle);
            container.on('click', '.ai-generate-content-btn', this.handleGenerateContent);
            container.on('click', '.ai-insert-generated-btn', this.handleInsertGenerated);
            container.on('click', '.ai-apply-keywords-btn', this.handleApplyKeywords);
            container.on('change', '#ai-content-type', this.handleContentTypeChange);
            
            // Featured Image Tab Actions
            container.on('click', '.ai-generate-prompt-btn', this.handleGenerateImagePrompt);
            container.on('click', '.ai-generate-image-btn', this.handleGenerateImage);
            container.on('click', '.ai-set-featured-image-btn', this.handleSetFeaturedImage);
            container.on('click', '.ai-download-image-btn', this.handleDownloadImage);
            
            // Enable/disable insert button when translated content changes
            container.on('input', '#ai-translated-article', this.toggleInsertButton);
            container.on('input', '#ai-generated-content', this.toggleGeneratedButtons);
            container.on('input', '#ai-image-prompt', this.toggleImageButtons);
            
            // Track user edits in original content textarea
            container.on('input', '#ai-original-article', function() {
                $(this).data('user-edited', true);
                console.log('AI Assistant: User edited original content - will preserve edits');
            });
        },

        handleIntroToggle: function(e) {
            e.preventDefault();
            var $toggle = $(this);
            var $content = $toggle.closest('.ai-assistant-intro').find('.ai-intro-content');
            var isExpanded = $toggle.attr('aria-expanded') === 'true';
            
            if (isExpanded) {
                $content.slideUp(300);
                $toggle.attr('aria-expanded', 'false');
            } else {
                $content.slideDown(300);
                $toggle.attr('aria-expanded', 'true');
            }
        },

        handleTabSwitch: function(e) {
            e.preventDefault();
            var $tab = $(this);
            var tabId = $tab.data('tab');
            var container = $tab.closest('.ai-assistant-meta-box-container');

            console.log('AI Assistant: Switching to tab: ' + tabId);

            // Update nav-tab active states
            container.find('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');

            // Switch tab content
            container.find('.ai-tab-content').removeClass('active').hide();
            container.find('#ai-tab-' + tabId).addClass('active').show();
        },

        handlePopulateContent: function(e) {
            e.preventDefault();
            console.log('AI Assistant: Populating content from editor.');
            const content = AIAssistant.getEditorContent();
            if (content) {
                $('#ai-source-content').val(content);
            } else {
                AIAssistant.showMessage('Editor content is empty.', 'error');
            }
        },

        handleTranslateContent: function(e) {
            e.preventDefault();
            const $button = $(this);
            const content = $('#ai-source-content').val();
            const sourceLang = $('#ai-source-lang').val();
            const targetLang = $('#ai-target-lang').val();
            const model = $('#ai-model-select').val();
            
            // Get post ID from current page
            const postId = $('#post_ID').val() || null;

            if (!content.trim()) {
                AIAssistant.showMessage(aiAssistant.strings.enterContent, 'error');
                return;
            }

            console.log(`AI Assistant: Translating content. Target: ${targetLang}, Model: ${model}, Post ID: ${postId}`);

            AIAssistant.performAjax('ai_assistant_translate', {
                content: content,
                source_language: sourceLang,
                target_language: targetLang,
                model: model,
                post_id: postId
            }, $button, aiAssistant.strings.translating, aiAssistant.strings.translateContent, function(response) {
                console.log('AI Assistant: Translation successful.', response);
                $('#ai-target-content').val(response.data.translation);
                AIAssistant.showMessage('Content translated successfully!', 'success');
            });
        },

        handleFetchUrl: function(e) {
            e.preventDefault();
            const $button = $(this);
            const url = $('#ai-article-url').val();

            if (!url.trim() || !url.startsWith('http')) {
                AIAssistant.showMessage(aiAssistant.strings.enterUrl, 'error');
                return;
            }

            console.log(`AI Assistant: Fetching content from URL: ${url}`);            AIAssistant.performAjax('ai_assistant_fetch_url', {
                url: url
            }, $button, aiAssistant.strings.fetching, aiAssistant.strings.fetchContent, function(response) {
                console.log('AI Assistant: URL content fetched successfully.', response);
                  // Format the content properly (preserve line breaks and structure)
                let formattedContent = response.data.content;
                if (formattedContent) {
                    // Create a temporary div to decode HTML entities properly
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = formattedContent;
                    formattedContent = tempDiv.textContent || tempDiv.innerText || '';
                    
                    // Clean up excessive whitespace while preserving paragraph breaks
                    formattedContent = formattedContent
                        .replace(/\n\s*\n\s*\n/g, '\n\n') // Remove triple+ line breaks
                        .replace(/[ \t]+/g, ' ') // Multiple spaces/tabs to single space
                        .trim();
                }
                
                $('#ai-original-article').val(formattedContent);
                $('#ai-original-article').data('user-edited', false); // Reset edit flag
                AIAssistant.showMessage(aiAssistant.strings.contentFetched, 'success');
            });
        },        handleTranslateArticle: function(e) {
            e.preventDefault();
            const $button = $(this);
            const content = $('#ai-original-article').val();
            const url = $('#ai-article-url').val();
            const sourceLang = $('#ai-url-source-lang').val() || 'auto';
            const targetLang = $('#ai-url-target-lang').val() || 'en';
            const model = $('#ai-url-model-select').val() || 'gemini-2.5-flash';

            if (!content.trim()) {
                AIAssistant.showMessage('Please fetch article content first.', 'error');
                return;
            }

            if (!url.trim()) {
                AIAssistant.showMessage('Please enter article URL first.', 'error');
                return;
            }

            console.log(`AI Assistant: Translating URL article. URL: ${url}, Source: ${sourceLang}, Target: ${targetLang}, Model: ${model}`);

            // Store the current content to preserve user edits
            const currentOriginalContent = content;

            // Use dedicated URL translation AJAX action
            AIAssistant.performAjax('ai_assistant_translate_url', {
                url: url,
                source_language: sourceLang,
                target_language: targetLang,
                model: model
            }, $button, aiAssistant.strings.translating, aiAssistant.strings.translateArticle, function(response) {
                console.log('AI Assistant: URL translation successful.', response);
                
                // Only update original content if user hasn't edited it since fetch
                // (Compare with the response's original content but preserve user edits)
                if (response.data.original_content && 
                    (!$('#ai-original-article').data('user-edited') || 
                     $('#ai-original-article').val().trim() === '')) {
                    $('#ai-original-article').val(response.data.original_content);
                }
                
                $('#ai-translated-article').val(response.data.translated_content);
                AIAssistant.showMessage('Article translated and saved to history!', 'success');
                AIAssistant.toggleInsertButton(); // Enable insert button
            });
        },

        handleUsePostTitle: function(e) {
            e.preventDefault();
            console.log('AI Assistant: Using post title as context.');
            
            // Get post title from WordPress editor
            let title = '';
            if ($('#title').length) {
                title = $('#title').val(); // Classic editor
            } else if ($('.editor-post-title__input').length) {
                title = $('.editor-post-title__input').val(); // Gutenberg
            }
            
            if (title) {
                $('#ai-content-context').val(title);
                AIAssistant.showMessage('Post title added as context!', 'success');
            } else {
                AIAssistant.showMessage('No post title found.', 'error');
            }
        },

        handleGenerateContent: function(e) {
            e.preventDefault();
            const $button = $(this);
            const contentType = $('#ai-content-type').val();
            const context = $('#ai-content-context').val();
            const model = $('#ai-content-model-select').val() || 'gemini-2.5-flash';
            
            if (!context.trim()) {
                AIAssistant.showMessage('Please enter a topic or context.', 'error');
                return;
            }

            console.log(`AI Assistant: Generating ${contentType} content for: ${context} using model: ${model}`);
            
            // Get existing content for context if available
            const existingContent = AIAssistant.getEditorContent();

            AIAssistant.performAjax('ai_assistant_generate_content', {
                content_type: contentType,
                context: context,
                model: model,
                existing_content: existingContent || ''
            }, $button, 'Generating...', 'Generate', function(response) {
                console.log('AI Assistant: Content generation successful.', response);
                $('#ai-generated-content').val(response.data.content);
                
                // Store HTML content info for insertion
                if (response.data.html_cache_key) {
                    $('#ai-generated-content').data('html-cache-key', response.data.html_cache_key);
                    $('#ai-generated-content').data('has-html', true);
                }
                
                AIAssistant.toggleGeneratedButtons();
                AIAssistant.showMessage(`${contentType.charAt(0).toUpperCase() + contentType.slice(1)} generated successfully!`, 'success');
            });
        },

        handleInsertGenerated: function(e) {
            e.preventDefault();
            const $textarea = $('#ai-generated-content');
            const content = $textarea.val();
            
            if (!content.trim()) {
                AIAssistant.showMessage('No generated content to insert.', 'error');
                return;
            }

            console.log('AI Assistant: Inserting generated content to editor.');
            
            // Check if we have HTML content to insert
            const htmlCacheKey = $textarea.data('html-cache-key');
            const hasHtml = $textarea.data('has-html');
            
            if (hasHtml && htmlCacheKey) {
                // Get HTML content for WordPress insertion
                $.ajax({
                    url: aiAssistant.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ai_assistant_get_html_content',
                        nonce: aiAssistant.nonce,
                        html_cache_key: htmlCacheKey
                    },
                    success: function(response) {
                        if (response.success) {
                            AIAssistant.insertToEditor(response.data.html_content);
                            AIAssistant.showMessage('Generated content (with formatting) inserted successfully!', 'success');
                        } else {
                            // Fallback to plain text
                            AIAssistant.insertToEditor(content);
                            AIAssistant.showMessage('Generated content inserted successfully!', 'success');
                        }
                    },
                    error: function() {
                        // Fallback to plain text
                        AIAssistant.insertToEditor(content);
                        AIAssistant.showMessage('Generated content inserted successfully!', 'success');
                    }
                });
            } else {
                // Insert plain text content
                AIAssistant.insertToEditor(content);
                AIAssistant.showMessage('Generated content inserted successfully!', 'success');
            }
        },

        handleApplyKeywords: function(e) {
            e.preventDefault();
            const content = $('#ai-generated-content').val();
            
            if (!content.trim()) {
                AIAssistant.showMessage('No keywords to apply.', 'error');
                return;
            }

            console.log('AI Assistant: Applying keywords to Yoast SEO.');
            
            // Find Yoast SEO focus keyword input
            const $yoastInput = $('#focus-keyword-input-metabox');
            
            if ($yoastInput.length) {
                // Extract the first keyword from the generated content
                const keywords = content.split(',');
                const firstKeyword = keywords[0].replace(/^\d+\.\s*/, '').trim(); // Remove numbering
                
                $yoastInput.val(firstKeyword);
                $yoastInput.trigger('input'); // Trigger Yoast's update
                
                AIAssistant.showMessage(`Focus keyword set to: "${firstKeyword}"`, 'success');
            } else {
                AIAssistant.showMessage('Yoast SEO focus keyword field not found. Make sure Yoast SEO plugin is active.', 'error');
            }
        },

        handleContentTypeChange: function(e) {
            const contentType = $(this).val();
            const $keywordBtn = $('.ai-apply-keywords-btn');
            
            // Show/hide keyword application button based on content type
            if (contentType === 'keywords') {
                $keywordBtn.show();
            } else {
                $keywordBtn.hide();
            }
              // Update button text and placeholder based on type
            const placeholders = {
                'suggestions': 'Content suggestions will appear here...',
                'full-article': 'Full article content will appear here...',
                'keywords': 'SEO keywords will appear here...',
                'meta-description': 'Meta descriptions will appear here...',
                'title-ideas': 'Title ideas will appear here...'
            };
            
            $('#ai-generated-content').attr('placeholder', placeholders[contentType] || 'Generated content will appear here...');
        },

        toggleInsertButton: function() {
            const translatedContent = $('#ai-translated-article').val().trim();
            const $insertBtn = $('.ai-insert-content-btn');
            
            if (translatedContent.length > 0) {
                $insertBtn.prop('disabled', false);
            } else {
                $insertBtn.prop('disabled', true);
            }
        },

        toggleGeneratedButtons: function() {
            const content = $('#ai-generated-content').val();
            const contentType = $('#ai-content-type').val();
            const hasContent = content.trim().length > 0;
            
            $('.ai-insert-generated-btn').prop('disabled', !hasContent);
            
            if (contentType === 'keywords') {
                $('.ai-apply-keywords-btn').prop('disabled', !hasContent);
            }
        },

        handleInsertContent: function(e) {
            e.preventDefault();
            const translatedContent = $('#ai-translated-article').val().trim();
            
            if (!translatedContent) {
                AIAssistant.showMessage('No translated content to insert.', 'error');
                return;
            }

            // Try different WordPress editor interfaces
            let inserted = false;

            // Try Gutenberg editor first
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                try {
                    const currentContent = wp.data.select('core/editor').getEditedPostContent();
                    const newContent = currentContent + '\n\n' + translatedContent;
                    wp.data.dispatch('core/editor').editPost({ content: newContent });
                    inserted = true;
                    console.log('AI Assistant: Content inserted via Gutenberg editor');
                } catch (error) {
                    console.log('AI Assistant: Gutenberg insertion failed:', error);
                }
            }

            // Try Classic editor (TinyMCE) as fallback
            if (!inserted && typeof tinyMCE !== 'undefined') {
                const editor = tinyMCE.get('content');
                if (editor && !editor.isHidden()) {
                    try {
                        const currentContent = editor.getContent();
                        const formattedContent = translatedContent.replace(/\n/g, '<br>');
                        editor.setContent(currentContent + '<br><br>' + formattedContent);
                        inserted = true;
                        console.log('AI Assistant: Content inserted via TinyMCE editor');
                    } catch (error) {
                        console.log('AI Assistant: TinyMCE insertion failed:', error);
                    }
                }
            }

            // Try textarea fallback (classic editor in text mode)
            if (!inserted) {
                const $contentTextarea = $('#content');
                if ($contentTextarea.length) {
                    try {
                        const currentContent = $contentTextarea.val();
                        $contentTextarea.val(currentContent + '\n\n' + translatedContent);
                        inserted = true;
                        console.log('AI Assistant: Content inserted via textarea');
                    } catch (error) {
                        console.log('AI Assistant: Textarea insertion failed:', error);
                    }
                }
            }

            if (inserted) {
                AIAssistant.showMessage('Translated content has been inserted into the editor!', 'success');
                
                // Optional: Clear the translated content or keep it for reference
                // $('#ai-translated-article').val('');
                // AIAssistant.toggleInsertButton();
            } else {
                AIAssistant.showMessage('Could not detect WordPress editor. Please copy the content manually.', 'error');                // As fallback, select all text in the translated textarea for easy copying
                $('#ai-translated-article').select();
            }
        },        setupTextareaAutoResize: function() {
            // Auto-resize textareas based on content
            function autoResize(element) {
                element.style.height = 'auto';
                element.style.height = Math.max(element.scrollHeight + 2, 200) + 'px';
            }
            
            // Apply to all AI textareas
            $('.ai-assistant-textarea, #ai-original-article, #ai-translated-article, #ai-generated-content, #ai-translate-content, #ai-original-content').each(function() {
                const $textarea = $(this);
                const element = this;
                
                // Ensure textarea is resizable
                $textarea.css('resize', 'vertical');
                
                // Initial resize
                autoResize(element);
                
                // Resize on input, paste, and keyup
                $textarea.on('input paste keyup', function() {
                    setTimeout(() => autoResize(element), 1);
                });
                
                // Also resize when content is focused (in case content was set while not visible)
                $textarea.on('focus', function() {
                    setTimeout(() => autoResize(element), 10);
                });
            });
            
            // Override jQuery val() to trigger resize when content is set programmatically
            const originalVal = $.fn.val;
            $.fn.val = function(value) {
                const result = originalVal.apply(this, arguments);
                if (arguments.length > 0 && this.is('.ai-assistant-textarea, #ai-original-article, #ai-translated-article, #ai-generated-content, #ai-translate-content, #ai-original-content')) {
                    const element = this[0];
                    setTimeout(() => {
                        element.style.height = 'auto';
                        element.style.height = Math.max(element.scrollHeight + 2, 200) + 'px';
                    }, 1);
                }
                return result;
            };
              console.log('AI Assistant: Textarea auto-resize setup completed');
        },

        setupAutoComplete: function() {
            var self = this;
            // Set up auto-complete for all AI Assistant textareas
            var $textareas = $('#ai-generated-content, #ai-translate-content, #ai-original-content');
            if (!$textareas.length) return;
            
            console.log('AI Assistant: Setting up auto-complete for textareas:', $textareas.length);
            
            var suggestionTimeout;
            var isShowingSuggestions = false;
            var currentSuggestions = [];
            var selectedSuggestionIndex = -1;
            var activeTextarea = null;
            
            // Create suggestion overlay - make sure it's visible
            var $overlay = $('<div id="ai-suggestion-overlay" style="position: absolute; z-index: 99999;"></div>');
            $('body').append($overlay);
            
            // Hide suggestions function
            function hideSuggestions() {
                $overlay.hide();
                isShowingSuggestions = false;
                selectedSuggestionIndex = -1;
                console.log('AI Assistant: Suggestions hidden');
            }
            
            // Show suggestions function
            function showSuggestions(suggestions, textarea) {
                if (!suggestions || suggestions.length === 0) {
                    hideSuggestions();
                    return;
                }
                
                console.log('AI Assistant: Showing suggestions:', suggestions);
                
                currentSuggestions = suggestions;
                selectedSuggestionIndex = 0;
                isShowingSuggestions = true;
                
                // Get textarea position - fix to use the actual textarea element
                var $currentTextarea = $(textarea);
                var textareaPos = $currentTextarea.offset();
                var textareaHeight = $currentTextarea.outerHeight();
                
                // Position overlay below textarea
                var top = textareaPos.top + textareaHeight + 5;
                var left = textareaPos.left;
                
                // Create suggestion HTML
                var html = '<div class="ai-suggestions-container">';
                html += '<div class="ai-suggestions-header">✨ AI Suggestions (Tab/Enter to accept, Esc to dismiss)</div>';
                suggestions.forEach(function(suggestion, index) {
                    var className = index === 0 ? 'ai-suggestion-item active' : 'ai-suggestion-item';
                    html += '<div class="' + className + '" data-index="' + index + '">' + suggestion + '</div>';
                });
                html += '</div>';
                
                $overlay.html(html).css({
                    position: 'absolute',
                    top: top + 'px',
                    left: left + 'px',
                    zIndex: 99999
                }).show();
                
                console.log('AI Assistant: Suggestions displayed at', {top: top, left: left});
            }
            
            // Get suggestions from AI
            function getSuggestions(currentText) {
                if (currentText.length < 5) {
                    console.log('AI Assistant: Text too short for suggestions:', currentText.length);
                    return;
                }
                
                console.log('AI Assistant: Requesting suggestions for text:', currentText.substring(0, 50) + '...');
                
                var context = $('#ai-content-context').val() || '';
                
                $.ajax({
                    url: aiAssistant.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ai_assistant_get_suggestions',
                        nonce: aiAssistant.nonce,
                        current_text: currentText,
                        context: context
                    },
                    success: function(response) {
                        console.log('AI Assistant: Suggestions response:', response);
                        if (response.success && response.data.suggestions) {
                            showSuggestions(response.data.suggestions, activeTextarea);
                        } else {
                            console.log('AI Assistant: No suggestions returned');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AI Assistant: Suggestions error:', xhr, status, error);
                    }
                });
            }
            
            // Accept suggestion function
            function acceptSuggestion() {
                if (!isShowingSuggestions || selectedSuggestionIndex < 0 || !activeTextarea) return false;
                
                var suggestion = currentSuggestions[selectedSuggestionIndex];
                var $activeTextarea = $(activeTextarea);
                var cursorPos = activeTextarea.selectionStart;
                var currentValue = $activeTextarea.val();
                
                // Insert suggestion at cursor position
                var newValue = currentValue.substring(0, cursorPos) + ' ' + suggestion + currentValue.substring(cursorPos);
                $activeTextarea.val(newValue);
                
                // Move cursor to end of inserted text
                var newCursorPos = cursorPos + suggestion.length + 1;
                activeTextarea.setSelectionRange(newCursorPos, newCursorPos);
                
                hideSuggestions();
                $activeTextarea.focus();
                
                console.log('AI Assistant: Suggestion accepted:', suggestion);
                return true;
            }
            
            // Keyboard handling for all textareas
            $textareas.on('keydown', function(e) {
                activeTextarea = this;
                if (isShowingSuggestions) {
                    switch(e.keyCode) {
                        case 9: // Tab
                            e.preventDefault();
                            acceptSuggestion();
                            return false;
                        case 13: // Enter
                            e.preventDefault();
                            acceptSuggestion();
                            return false;
                        case 27: // Escape
                            e.preventDefault();
                            hideSuggestions();
                            return false;
                        case 38: // Up arrow
                            e.preventDefault();
                            selectedSuggestionIndex = Math.max(0, selectedSuggestionIndex - 1);
                            $overlay.find('.ai-suggestion-item').removeClass('active').eq(selectedSuggestionIndex).addClass('active');
                            return false;
                        case 40: // Down arrow
                            e.preventDefault();
                            selectedSuggestionIndex = Math.min(currentSuggestions.length - 1, selectedSuggestionIndex + 1);
                            $overlay.find('.ai-suggestion-item').removeClass('active').eq(selectedSuggestionIndex).addClass('active');
                            return false;
                    }
                }
            });
            
            // Text input handling for all textareas
            $textareas.on('input', function(e) {
                activeTextarea = this;
                hideSuggestions();
                clearTimeout(suggestionTimeout);
                
                var currentText = $(this).val().trim();
                console.log('AI Assistant: Text input detected, length:', currentText.length);
                
                // Trigger suggestions after typing stops
                if (currentText.length >= 10) {
                    suggestionTimeout = setTimeout(function() {
                        console.log('AI Assistant: Triggering suggestions after delay');
                        getSuggestions(currentText);
                    }, 2000); // Wait 2 seconds after typing stops
                }
            });
            
            // Click on suggestions
            $(document).on('click', '.ai-suggestion-item', function() {
                selectedSuggestionIndex = parseInt($(this).data('index'));
                acceptSuggestion();
            });
            
            // Hide suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#ai-suggestion-overlay, .ai-assistant-textarea, .nav-tab').length) {
                    hideSuggestions();
                }
            });
            
            console.log('AI Assistant: Auto-complete setup completed');
        },

        populateLanguageDropdowns: function() {
            console.log('AI Assistant: Populating language dropdowns');
            
            // Use localized language names if available, fallback to English
            const languages = {
                 'auto': aiAssistant.strings.autoDetect || 'Auto-detect',
                'en': aiAssistant.strings.english || 'English',
                'tr': aiAssistant.strings.turkish || 'Turkish', 
                'ar': aiAssistant.strings.arabic || 'Arabic',
                'es': aiAssistant.strings.spanish || 'Spanish',
                'fr': aiAssistant.strings.french || 'French',
                'de': aiAssistant.strings.german || 'German',
                'ru': aiAssistant.strings.russian || 'Russian',
                'zh': aiAssistant.strings.chinese || 'Chinese',
                'fa': aiAssistant.strings.persian || 'Persian',
                'pt': aiAssistant.strings.portuguese || 'Portuguese',
                'nl': aiAssistant.strings.dutch || 'Dutch',
                'az': aiAssistant.strings.azerbaijani || 'Azerbaijani',
                'da': aiAssistant.strings.danish || 'Danish',
                'uz': aiAssistant.strings.uzbek || 'Uzbek',
                'fi': aiAssistant.strings.finnish || 'Finnish',
                'ky': aiAssistant.strings.kyrgyz || 'Kyrgyz',
                'ug': aiAssistant.strings.uyghur || 'Uyghur',
                'ur': aiAssistant.strings.urdu || 'Urdu',
                'fi': aiAssistant.strings.finnish || 'Finnish',
                'tk': aiAssistant.strings.turkmen || 'Turkmen'
            };

            // Populate all language dropdowns
            const selectors = [
                '#ai-source-lang',
                '#ai-target-lang', 
                '#ai-url-source-lang',
                '#ai-url-target-lang'
            ];

            selectors.forEach(selector => {
                const $select = $(selector);
                if ($select.length) {
                    $select.empty(); // Clear existing options
                    
                    Object.keys(languages).forEach(code => {
                        const $option = $('<option></option>')
                            .attr('value', code)
                            .text(languages[code]);
                        $select.append($option);
                    });
                    
                    // Set default values
                    if (selector.includes('source')) {
                        $select.val('auto');
                    } else {
                        // Use site language for target language, fallback to English
                        const siteLanguage = aiAssistant.siteLanguage || 'en';
                        $select.val(siteLanguage);
                    }
                }
            });
            
            console.log('AI Assistant: Language dropdowns populated successfully');
        },

        populateModelDropdowns: function() {
            console.log('AI Assistant: Populating model dropdowns');
            
            const availableModels = aiAssistant.availableModels || {};
            
            if (Object.keys(availableModels).length === 0) {
                console.warn('AI Assistant: No available models found');
                return;
            }
            
            // Find all model selection dropdowns
            const modelSelectors = [
                '#ai-model-select',         // Translate tab
                '#ai-url-model-select',     // URL tab
                '#ai-content-model-select'  // Content tab
            ];
            
            modelSelectors.forEach(selector => {
                const $select = $(selector);
                if ($select.length) {
                    $select.empty(); // Clear existing options
                    
                    Object.keys(availableModels).forEach(modelId => {
                        const $option = $('<option></option>')
                            .attr('value', modelId)
                            .text(availableModels[modelId]);
                        $select.append($option);
                    });
                    
                    console.log(`AI Assistant: Populated ${selector} with ${Object.keys(availableModels).length} models`);
                }
            });
            
            console.log('AI Assistant: Model dropdowns populated successfully');
        },

        getEditorContent: function() {
            let content = '';
            
            // Try Gutenberg editor first
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                try {
                    content = wp.data.select('core/editor').getEditedPostContent();
                    if (content) {
                        // Remove HTML tags for plain text
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = content;
                        content = tempDiv.textContent || tempDiv.innerText || '';
                        return content.trim();
                    }
                } catch (error) {
                    console.log('AI Assistant: Could not get Gutenberg content:', error);
                }
            }
            
            // Try Classic editor (TinyMCE)
            if (typeof tinyMCE !== 'undefined') {
                const editor = tinyMCE.get('content');
                if (editor && !editor.isHidden()) {
                    try {
                        content = editor.getContent({ format: 'text' });
                        if (content) return content.trim();
                    } catch (error) {
                        console.log('AI Assistant: Could not get TinyMCE content:', error);
                    }
                }
            }
            
            // Try textarea fallback
            const $contentTextarea = $('#content');
            if ($contentTextarea.length) {
                content = $contentTextarea.val();
                if (content) return content.trim();
            }
            
            return '';
        },

        insertToEditor: function(content) {
            let inserted = false;

            // Try Gutenberg editor first
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                try {
                    const currentContent = wp.data.select('core/editor').getEditedPostContent();
                    const newContent = currentContent + '\n\n' + content;
                    wp.data.dispatch('core/editor').editPost({ content: newContent });
                    inserted = true;
                    console.log('AI Assistant: Content inserted via Gutenberg editor');
                } catch (error) {
                    console.log('AI Assistant: Gutenberg insertion failed:', error);
                }
            }

            // Try Classic editor (TinyMCE) as fallback
            if (!inserted && typeof tinyMCE !== 'undefined') {
                const editor = tinyMCE.get('content');
                if (editor && !editor.isHidden()) {
                    try {
                        const currentContent = editor.getContent();
                        const formattedContent = content.replace(/\n/g, '<br>');
                        editor.setContent(currentContent + '<br><br>' + formattedContent);
                        inserted = true;
                        console.log('AI Assistant: Content inserted via TinyMCE editor');
                    } catch (error) {
                        console.log('AI Assistant: TinyMCE insertion failed:', error);
                    }
                }
            }

            // Try textarea fallback
            if (!inserted) {
                const $contentTextarea = $('#content');
                if ($contentTextarea.length) {
                    try {
                        const currentContent = $contentTextarea.val();
                        $contentTextarea.val(currentContent + '\n\n' + content);
                        inserted = true;
                        console.log('AI Assistant: Content inserted via textarea');
                    } catch (error) {
                        console.log('AI Assistant: Textarea insertion failed:', error);
                    }
                }
            }

            return inserted;
        },

        performAjax: function(action, data, $button, loadingText, originalText, successCallback) {
            const originalButtonText = $button.text();
            
            // Disable button and show loading state
            $button.prop('disabled', true).text(loadingText);
            
            // Prepare AJAX data
            const ajaxData = {
                action: action,
                nonce: aiAssistant.nonce,
                ...data
            };
            
            $.ajax({
                url: aiAssistant.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                timeout: 60000, // 60 seconds timeout
                success: function(response) {
                    if (response.success) {
                        if (typeof successCallback === 'function') {
                            successCallback(response);
                        }
                    } else {
                        let errorMessage = 'An error occurred';
                        
                        // Handle different types of error responses
                        if (response.data && typeof response.data === 'string') {
                            errorMessage = response.data;
                        } else if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        } else if (response.data && response.data.error) {
                            errorMessage = response.data.error;
                        }
                        
                        // For translation failures, provide more specific guidance
                        if (action.includes('translate')) {
                            if (errorMessage.toLowerCase().includes('content too long')) {
                                errorMessage = 'Content is too long for translation. Please try with shorter text or break it into smaller parts.';
                            } else if (errorMessage.toLowerCase().includes('timeout')) {
                                errorMessage = 'Translation timed out. The content might be too complex. Please try again or use shorter content.';
                            } else if (errorMessage.toLowerCase().includes('quota') || errorMessage.toLowerCase().includes('limit')) {
                                errorMessage = 'AI service quota exceeded. Please try again later or contact your administrator.';
                            } else if (errorMessage.toLowerCase().includes('api')) {
                                errorMessage = 'AI service temporarily unavailable. Please try again in a few moments.';
                            }
                        }
                        
                        AIAssistant.showMessage(errorMessage, 'error');
                        console.error('AI Assistant AJAX Error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'Connection error occurred';
                    
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data;
                    } else if (error) {
                        errorMessage = error;
                    }
                    
                    AIAssistant.showMessage(errorMessage, 'error');
                    console.error('AI Assistant AJAX Error:', xhr, status, error);
                },
                complete: function() {
                    // Re-enable button and restore original text
                    $button.prop('disabled', false).text(originalText || originalButtonText);
                }
            });
        },

        showMessage: function(message, type) {
            const $messageArea = $('#ai-assistant-message-area');
            
            // Clear previous messages
            $messageArea.empty();
            
            const $message = $('<div></div>')
                .addClass('ai-assistant-notice')
                .addClass(type || 'info')
                .text(message);
            
            $messageArea.append($message);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    $message.fadeOut();
                }, 5000);
            }
        },

        initializeTabs: function() {
            console.log('AI Assistant: Initializing tabs');
            
            // Hide all tab content first
            $('.ai-tab-content').removeClass('active').hide();
            $('.nav-tab').removeClass('nav-tab-active');
            
            // Show only the first tab
            $('.ai-tab-content').first().addClass('active').show();
            $('.nav-tab').first().addClass('nav-tab-active');
            
            console.log('AI Assistant: Tabs initialized - first tab active');
        },

        // Image Generation Functions
        handleGenerateImagePrompt: function(e) {
            e.preventDefault();
            console.log('AI Assistant: Generating image prompt');
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(aiAssistant.strings.generatingPrompt).prop('disabled', true);
            AIAssistant.showMessage('info', aiAssistant.strings.generatingPrompt);
            
            var postId = $('#post_ID').val() || 0;
            var context = $('#ai-content-context').val() || '';
            
            $.ajax({
                url: aiAssistant.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_assistant_generate_image_prompt',
                    nonce: aiAssistant.nonce,
                    post_id: postId,
                    context: context
                },
                success: function(response) {
                    if (response.success) {
                        $('#ai-image-prompt').val(response.data.prompt);
                        AIAssistant.showMessage('success', 'Image prompt generated successfully!');
                        AIAssistant.toggleImageButtons();
                    } else {
                        AIAssistant.showMessage('error', response.data.message || 'Failed to generate prompt');
                    }
                },
                error: function() {
                    AIAssistant.showMessage('error', aiAssistant.strings.connectionError);
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        handleGenerateImage: function(e) {
            e.preventDefault();
            console.log('AI Assistant: Generating image');
            
            var prompt = $('#ai-image-prompt').val().trim();
            if (!prompt) {
                AIAssistant.showMessage('error', aiAssistant.strings.enterImagePrompt);
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            var style = $('#ai-image-style').val();
            var size = $('#ai-image-size').val();
            var model = $('#ai-image-model-select').val();
            
            $button.text(aiAssistant.strings.generatingImage).prop('disabled', true);
            AIAssistant.showMessage('info', aiAssistant.strings.generatingImage);
            
            // Show loading state in preview container
            $('#ai-image-preview-container').html('<div style="padding: 50px; text-align: center;"><div class="spinner is-active" style="float: none; margin: 0 auto;"></div><p style="margin-top: 15px;">' + aiAssistant.strings.generatingImage + '</p></div>');
            
            $.ajax({
                url: aiAssistant.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_assistant_generate_image',
                    nonce: aiAssistant.nonce,
                    prompt: prompt,
                    style: style,
                    size: size,
                    model: model
                },
                success: function(response) {
                    if (response.success) {
                        var imageHtml = '<img src="' + response.data.image_url + '" style="max-width: 100%; height: auto; border: 1px solid #ddd; border-radius: 4px;" alt="Generated image" data-image-url="' + response.data.image_url + '">';
                        $('#ai-image-preview-container').html(imageHtml);
                        AIAssistant.showMessage('success', aiAssistant.strings.imageGenerated);
                        
                        // Enable action buttons
                        $('.ai-set-featured-image-btn, .ai-download-image-btn').prop('disabled', false);
                    } else {
                        $('#ai-image-preview-container').html('<p style="color: #d63638; font-style: italic; text-align: center; padding: 20px;">' + (response.data.message || aiAssistant.strings.imageGenerationFailed) + '</p>');
                        AIAssistant.showMessage('error', response.data.message || aiAssistant.strings.imageGenerationFailed);
                    }
                },
                error: function() {
                    $('#ai-image-preview-container').html('<p style="color: #d63638; font-style: italic; text-align: center; padding: 20px;">' + aiAssistant.strings.connectionError + '</p>');
                    AIAssistant.showMessage('error', aiAssistant.strings.connectionError);
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        handleSetFeaturedImage: function(e) {
            e.preventDefault();
            console.log('AI Assistant: Setting featured image');
            
            var $image = $('#ai-image-preview-container img');
            if (!$image.length) {
                AIAssistant.showMessage('error', 'No image available to set as featured image');
                return;
            }
            
            var imageUrl = $image.data('image-url');
            var prompt = $('#ai-image-prompt').val().trim();
            var postId = $('#post_ID').val() || 0;
            
            if (!postId) {
                AIAssistant.showMessage('error', 'Please save the post first before setting featured image');
                return;
            }
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(aiAssistant.strings.settingFeaturedImage).prop('disabled', true);
            AIAssistant.showMessage('info', aiAssistant.strings.settingFeaturedImage);
            
            $.ajax({
                url: aiAssistant.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_assistant_set_featured_image',
                    nonce: aiAssistant.nonce,
                    post_id: postId,
                    image_url: imageUrl,
                    prompt: prompt
                },
                success: function(response) {
                    if (response.success) {
                        AIAssistant.showMessage('success', aiAssistant.strings.featuredImageSet);
                        // Optionally refresh the featured image metabox if it exists
                        if ($('#postimagediv').length) {
                            setTimeout(function() {
                                location.reload(); // Simple way to refresh and show the new featured image
                            }, 2000);
                        }
                    } else {
                        AIAssistant.showMessage('error', response.data.message || aiAssistant.strings.featuredImageFailed);
                    }
                },
                error: function() {
                    AIAssistant.showMessage('error', aiAssistant.strings.connectionError);
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        handleDownloadImage: function(e) {
            e.preventDefault();
            console.log('AI Assistant: Downloading image');
            
            var $image = $('#ai-image-preview-container img');
            if (!$image.length) {
                AIAssistant.showMessage('error', 'No image available to download');
                return;
            }
            
            var imageUrl = $image.data('image-url');
            
            // Create a temporary link to download the image
            var link = document.createElement('a');
            link.href = imageUrl;
            link.download = 'ai-generated-image-' + Date.now() + '.png';
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            AIAssistant.showMessage('success', 'Image download started');
        },

        toggleImageButtons: function() {
            var hasPrompt = $('#ai-image-prompt').val().trim().length > 0;
            $('.ai-generate-image-btn').prop('disabled', !hasPrompt);
        },
    };

    $(document).ready(function() {
        AIAssistant.init();
    });

})(jQuery);
