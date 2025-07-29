/**
 * AI Assistant Admin JavaScript
 * Version: 1.0.58
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        AIAssistantAdmin.init();
    });

    // Main admin object
    window.AIAssistantAdmin = {
        
        // Initialize all admin functionality
        init: function() {
            this.initLanguageSettings();
            this.initApiTesting();
            this.initTranslationManagement();
            this.initTranslationEditor();
            this.initDashboard();
            this.initCompilationTools();
            console.log('AI Assistant Admin: Initialized');
        },

        // Utility: Debounce function for search input
        debounce: function(func, wait) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        },

        // Language settings functionality
        initLanguageSettings: function() {
            const self = this;
            
            // Handle language form submission with AJAX
            $('form').on('submit', function(e) {
                const $form = $(this);
                const $submitButton = $form.find('input[type="submit"][name="save_language_settings"]');
                
                if ($submitButton.length === 0) return; // Not a language form
                
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('action', 'ai_assistant_save_language');
                formData.append('nonce', ai_assistant_admin.nonce);
                
                // Show loading state
                $submitButton.prop('disabled', true)
                           .val(ai_assistant_admin.strings.saving);
                
                // Make AJAX request
                $.ajax({
                    url: ai_assistant_admin.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            self.showNotice(ai_assistant_admin.strings.saved, 'success');
                            
                            // If language changed, confirm reload
                            if (response.data && response.data.language_changed) {
                                if (confirm(ai_assistant_admin.strings.confirm_reload)) {
                                    window.location.reload();
                                }
                            }
                        } else {
                            self.showNotice(response.data || ai_assistant_admin.strings.error, 'error');
                        }
                    },
                    error: function() {
                        self.showNotice(ai_assistant_admin.strings.error, 'error');
                    },
                    complete: function() {
                        $submitButton.prop('disabled', false)
                                   .val($submitButton.data('original-value') || 'Save Language Settings');
                    }
                });
            });
        },

        // API testing functionality
        initApiTesting: function() {
            const self = this;
            
            $('#test-gemini').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $resultsDiv = $('#test-results');
                
                // Store original text
                const originalText = $button.text();
                
                // Show loading state
                $button.prop('disabled', true)
                       .text(ai_assistant_admin.strings.testing);
                
                $resultsDiv.html('<div class="notice notice-info"><p>Testing API connection...</p></div>');
                
                // Make AJAX request
                $.ajax({
                    url: ai_assistant_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_assistant_test_connection',
                        nonce: ai_assistant_admin.nonce
                    },
                    success: function(response) {
                        let message = '';
                        
                        if (response.success) {
                            message = '<div class="notice notice-success"><p><strong>' + 
                                     ai_assistant_admin.strings.success + '</strong> ' + 
                                     response.data.message;
                                     
                            if (response.data.test_translation) {
                                message += '<br><em>Test translation:</em> ' + response.data.test_translation;
                            }
                            message += '</p></div>';
                        } else {
                            message = '<div class="notice notice-error"><p><strong>Error:</strong> ' + 
                                     response.data.message + '</p></div>';
                        }
                        
                        $resultsDiv.html(message);
                    },
                    error: function() {
                        $resultsDiv.html('<div class="notice notice-error"><p>' + 
                                        ai_assistant_admin.strings.failed + 
                                        '</p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false)
                               .text(originalText);
                    }
                });
            });
        },

        // Translation management functionality
        initTranslationManagement: function() {
            const self = this;
            
            // Language selector change
            $('#language-selector').on('change', function() {
                const selectedLang = $(this).val();
                const url = new URL(window.location.href);
                url.searchParams.set('edit_lang', selectedLang);
                
                // Add loading indicator
                $(this).addClass('ai-assistant-loading');
                
                window.location.href = url.toString();
            });
            
            // Search functionality
            $('#translation-search').on('input', function() {
                self.filterTranslations();
            });
            
            $('#show-untranslated-only').on('change', function() {
                self.filterTranslations();
            });
            
            // Auto-translate functionality for .po file management
            $('#auto-translate').on('click', function(e) {
                e.preventDefault();
                self.handleAutoTranslate($(this));
            });
            
            // Auto-translate all empty strings - bulk functionality
            $('#auto-translate-all').on('click', function(e) {
                e.preventDefault();
                self.handleAutoTranslate($(this));
            });
            
            // Individual auto-translate buttons for single strings
            $(document).on('click', '.auto-translate-single', function(e) {
                e.preventDefault();
                self.handleSingleAutoTranslate($(this));
            });
            
            // Export .po file
            $('#export-po').on('click', function(e) {
                e.preventDefault();
                self.handleExportPo();
            });
            
            // Auto-resize textareas
            $('.translation-input').on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Initialize textarea heights
            $('.translation-input').each(function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        },

        // Dashboard functionality
        initDashboard: function() {
            // Animate stats on load
            $('.stat-item').each(function(index) {
                $(this).delay(index * 100).queue(function() {
                    $(this).addClass('fade-in').dequeue();
                });
            });
            
            // Add hover effects to action buttons
            $('.action-button').hover(
                function() { $(this).addClass('slide-up'); },
                function() { $(this).removeClass('slide-up'); }
            );
        },

        // Initialize compilation tools
        initCompilationTools: function() {
            const self = this;
            
            // Handle compile all .mo files button
            $('#compile-all-mo').on('click', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $status = $('#compile-status');
                
                // Show loading state
                $button.prop('disabled', true);
                $button.find('span.dashicons').addClass('spinning');
                $status.html('<span class="compiling">' + ai_assistant_admin.strings.compiling + '</span>');
                
                // Make AJAX request
                $.ajax({
                    url: ai_assistant_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ai_assistant_handle_compile_mo_files',
                        nonce: ai_assistant_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span class="success">' + response.data.message + '</span>');
                        } else {
                            $status.html('<span class="error">' + response.data.message + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $status.html('<span class="error">AJAX Error: ' + error + '</span>');
                    },
                    complete: function() {
                        // Reset button state
                        $button.prop('disabled', false);
                        $button.find('span.dashicons').removeClass('spinning');
                        
                        // Clear status after 5 seconds
                        setTimeout(function() {
                            $status.fadeOut(function() {
                                $(this).html('').show();
                            });
                        }, 5000);
                    }
                });
            });
        },

        // Filter translations based on search and status
        filterTranslations: function() {
            const searchTerm = $('#translation-search').val().toLowerCase();
            const showUntranslatedOnly = $('#show-untranslated-only').is(':checked');
            
            $('.translation-row').each(function() {
                const $row = $(this);
                const msgid = $row.data('msgid') || '';
                const isTranslated = $row.find('.status-translated').length > 0;
                
                let showRow = true;
                
                // Apply search filter
                if (searchTerm && msgid.indexOf(searchTerm) === -1) {
                    showRow = false;
                }
                
                // Apply untranslated filter
                if (showUntranslatedOnly && isTranslated) {
                    showRow = false;
                }
                
                $row.toggleClass('hidden', !showRow);
            });
            
            // Update visible count
            this.updateTranslationCount();
        },

        // Update translation count display
        updateTranslationCount: function() {
            const total = $('.translation-row').length;
            const visible = $('.translation-row:not(.hidden)').length;
            const translated = $('.translation-row:not(.hidden) .status-translated').length;
            const untranslated = visible - translated;
            
            // Update stats if they exist
            $('.translation-stats .stat-item').each(function() {
                const $stat = $(this);
                const text = $stat.text().toLowerCase();
                
                if (text.includes('total')) {
                    $stat.find('strong').text(visible);
                } else if (text.includes('translated')) {
                    $stat.find('strong').text(translated);
                } else if (text.includes('untranslated')) {
                    $stat.find('strong').text(untranslated);
                }
            });
        },

        // Handle auto-translation for .po file management
        handleAutoTranslate: function($button) {
            const self = this;
            const untranslatedStrings = [];
            
            // Collect untranslated strings from .po file interface
            $('.translation-row:not(.hidden)').each(function() {
                const $row = $(this);
                const $input = $row.find('.translation-input');
                const msgid = $input.closest('tr').find('input[name*="[msgid]"]').val();
                
                if (!$input.val().trim() && msgid) {
                    untranslatedStrings.push(msgid);
                }
            });
            
            console.log('Untranslated strings found:', untranslatedStrings);
            
            if (untranslatedStrings.length === 0) {
                alert('No untranslated strings found.');
                return;
            }
            
            const confirmMessage = 'This will attempt to auto-translate ' + 
                                 untranslatedStrings.length + 
                                 ' empty interface strings using AI. Continue?';
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.prop('disabled', true)
                   .text('Translating...')
                   .addClass('ai-assistant-loading');
            
            // Get current language
            const currentLanguage = $('#language-selector').val() || 
                                  $('input[name="edit_language"]').val();
            
            console.log('Sending translation request for language:', currentLanguage);
            console.log('AJAX URL:', ai_assistant_admin.ajax_url);
            console.log('Nonce:', ai_assistant_admin.nonce);
            
            // Make AJAX request for .po file translation
            $.ajax({
                url: ai_assistant_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_assistant_auto_translate',
                    language: currentLanguage,
                    strings: untranslatedStrings,
                    nonce: ai_assistant_admin.nonce
                },
                success: function(response) {
                    console.log('AJAX success response:', response);
                    if (response.success) {
                        let translatedCount = 0;
                        
                        // Apply translations to .po file form
                        $('.translation-row').each(function() {
                            const $row = $(this);
                            const $input = $row.find('.translation-input');
                            const msgid = $input.closest('tr').find('input[name*="[msgid]"]').val();
                            
                            if (response.data[msgid]) {
                                $input.val(response.data[msgid]);
                                $row.find('.status-badge')
                                    .removeClass('status-untranslated')
                                    .addClass('status-translated')
                                    .text('Translated');
                                translatedCount++;
                                
                                // Trigger auto-resize
                                $input.trigger('input');
                            }
                        });
                        
                        // Update count and show success
                        self.updateTranslationCount();
                        self.showNotice('Successfully translated ' + translatedCount + ' interface strings!', 'success');
                        
                    } else {
                        console.log('AJAX error response:', response);
                        self.showNotice('Translation failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX request error:', xhr, status, error);
                    console.log('Response text:', xhr.responseText);
                    self.showNotice('Translation request failed. Please try again.', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false)
                           .text(originalText)
                           .removeClass('ai-assistant-loading');
                }
            });
        },

        // Handle .po file export
        handleExportPo: function() {
            const currentLanguage = $('#language-selector').val() || 
                                  $('input[name="edit_language"]').val();
            
            const url = ai_assistant_admin.ajax_url + 
                       '?action=ai_assistant_export_po&lang=' + 
                       currentLanguage + 
                       '&nonce=' + ai_assistant_admin.nonce;
                       
            // Open in new tab/window
            window.open(url, '_blank');
        },

        // Show admin notice
        showNotice: function(message, type) {
            type = type || 'info';
            
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible fade-in">' +
                            '<p>' + message + '</p>' +
                            '<button type="button" class="notice-dismiss">' +
                            '<span class="screen-reader-text">Dismiss this notice.</span>' +
                            '</button>' +
                            '</div>');
            
            // Insert after h1 or at the top of wrap
            const $target = $('.wrap h1').first();
            if ($target.length) {
                $target.after($notice);
            } else {
                $('.wrap').prepend($notice);
            }
            
            // Make dismissible
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.slideUp(300, function() {
                    $notice.remove();
                });
            });
            
            // Auto-dismiss success messages
            if (type === 'success') {
                setTimeout(function() {
                    $notice.find('.notice-dismiss').click();
                }, 5000);
            }
        },

        // Translation editor functionality for .po file management
        initTranslationEditor: function() {
            // Language selector change
            $('#language-selector').on('change', function() {
                const selectedLang = $(this).val();
                const url = new URL(window.location.href);
                url.searchParams.set('edit_lang', selectedLang);
                window.location.href = url.toString();
            });
            
            // Search and filter functionality
            $('#translation-search').on('input', this.debounce(this.filterTranslations, 300));
            $('#show-untranslated-only').on('change', this.filterTranslations);
            
            // Export .po file
            $('#export-po').on('click', function() {
                if (typeof ai_assistant_admin !== 'undefined' && ai_assistant_admin.current_language) {
                    const lang = ai_assistant_admin.current_language;
                    const url = ai_assistant_admin.ajax_url + '?action=ai_assistant_export_po&lang=' + lang + '&nonce=' + ai_assistant_admin.export_nonce;
                    window.open(url, '_blank');
                }
            });
            
            // Auto-resize textareas
            $(document).on('input', '.translation-input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Auto-translate all empty strings
            $('#auto-translate-all').on('click', this.handleAutoTranslateAll.bind(this));
            
            // Individual auto-translate buttons
            $(document).on('click', '.auto-translate-single', this.handleAutoTranslateSingle.bind(this));
            
            // Compile all .mo files
            $('#compile-all-mo').on('click', this.handleCompileMoFiles.bind(this));
        },

        // Filter translations based on search and status
        filterTranslations: function() {
            const searchTerm = $('#translation-search').val().toLowerCase();
            const showUntranslatedOnly = $('#show-untranslated-only').is(':checked');
            
            $('.translation-row').each(function() {
                const $row = $(this);
                const msgid = $row.find('.msgid-text').text().toLowerCase();
                const isTranslated = $row.find('.status-translated').length > 0;
                
                let showRow = true;
                
                // Apply search filter
                if (searchTerm && msgid.indexOf(searchTerm) === -1) {
                    showRow = false;
                }
                
                // Apply untranslated filter
                if (showUntranslatedOnly && isTranslated) {
                    showRow = false;
                }
                
                $row.toggleClass('hidden', !showRow);
            });
        },

        // Handle auto-translate all functionality
        handleAutoTranslateAll: function() {
            const $button = $('#auto-translate-all');
            const untranslatedStrings = [];
            
            // Collect untranslated strings
            $('.translation-row').each(function() {
                const $row = $(this);
                const $input = $row.find('.translation-input');
                const msgid = $input.closest('tr').find('input[name*="[msgid]"]').val();
                
                if (!$input.val().trim() && msgid) {
                    untranslatedStrings.push(msgid);
                }
            });
            
            if (untranslatedStrings.length === 0) {
                alert(ai_assistant_admin.strings.no_untranslated_found || 'No untranslated strings found.');
                return;
            }
            
            const confirmMessage = (ai_assistant_admin.strings.auto_translate_confirm_start || 'This will attempt to auto-translate ') + 
                                 untranslatedStrings.length + 
                                 (ai_assistant_admin.strings.auto_translate_confirm_end || ' empty interface strings using AI. Continue?');
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.prop('disabled', true)
                   .text(ai_assistant_admin.strings.translating || 'Translating...')
                   .addClass('ai-assistant-loading');
            
            // Make AJAX request
            $.ajax({
                url: ai_assistant_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_assistant_auto_translate',
                    language: ai_assistant_admin.current_language,
                    strings: untranslatedStrings,
                    nonce: ai_assistant_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        let translatedCount = 0;
                        
                        // Apply translations
                        $('.translation-row').each(function() {
                            const $row = $(this);
                            const $input = $row.find('.translation-input');
                            const msgid = $input.closest('tr').find('input[name*="[msgid]"]').val();
                            
                            if (response.data[msgid]) {
                                $input.val(response.data[msgid]);
                                $row.find('.status-badge')
                                    .removeClass('status-untranslated')
                                    .addClass('status-translated')
                                    .text(ai_assistant_admin.strings.translated || 'Translated');
                                translatedCount++;
                                
                                // Trigger auto-resize
                                $input.trigger('input');
                            }
                        });
                        
                        alert((ai_assistant_admin.strings.success_translated_start || 'Successfully translated ') + 
                              translatedCount + 
                              (ai_assistant_admin.strings.success_translated_end || ' interface strings!'));
                        
                    } else {
                        alert((ai_assistant_admin.strings.translation_failed || 'Translation failed: ') + 
                              (response.data || ai_assistant_admin.strings.unknown_error || 'Unknown error'));
                    }
                },
                error: function() {
                    alert(ai_assistant_admin.strings.translation_request_failed || 'Translation request failed. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false)
                           .text(originalText)
                           .removeClass('ai-assistant-loading');
                }
            });
        },

        // Handle individual string auto-translate
        handleAutoTranslateSingle: function(e) {
            const $button = $(e.currentTarget);
            const msgid = $button.data('msgid');
            const $row = $button.closest('.translation-row');
            const $input = $row.find('.translation-input');
            
            if (!msgid) {
                alert(ai_assistant_admin.strings.no_text_to_translate || 'No text to translate.');
                return;
            }
            
            // Show loading state
            const originalText = $button.text();
            $button.prop('disabled', true)
                   .text(ai_assistant_admin.strings.translating_short || '...');
            
            // Make AJAX request for single string
            $.ajax({
                url: ai_assistant_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_assistant_auto_translate',
                    language: ai_assistant_admin.current_language,
                    strings: [msgid],
                    nonce: ai_assistant_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data[msgid]) {
                        $input.val(response.data[msgid]);
                        $row.find('.status-badge')
                            .removeClass('status-untranslated')
                            .addClass('status-translated')
                            .text(ai_assistant_admin.strings.translated || 'Translated');
                        
                        // Hide the button since it's now translated
                        $button.fadeOut();
                        
                        // Trigger auto-resize
                        $input.trigger('input');
                        
                    } else {
                        alert(ai_assistant_admin.strings.translation_failed_single || 'Translation failed for this string.');
                    }
                },
                error: function() {
                    alert(ai_assistant_admin.strings.translation_request_failed || 'Translation request failed.');
                },
                complete: function() {
                    $button.prop('disabled', false)
                           .text(originalText);
                }
            });
        },

        // Handle .mo file compilation
        handleCompileMoFiles: function() {
            const $button = $('#compile-all-mo');
            
            const confirmMessage = ai_assistant_admin.strings.compile_confirm || 
                                 'This will compile all .po files to .mo files. Required for translations to work in WordPress. Continue?';
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            const originalText = $button.text();
            $button.prop('disabled', true)
                   .text(ai_assistant_admin.strings.compiling || 'Compiling...');
            
            $.ajax({
                url: ai_assistant_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'ai_assistant_handle_compile_mo_files',
                    nonce: ai_assistant_admin.nonce
                },
                success: function(response) {
                    const $status = $('#compile-status');
                    if (response.success) {
                        $status.text(response.data.message).css('color', 'green');
                    } else {
                        const errorMsg = (ai_assistant_admin.strings.compilation_failed || 'Compilation failed: ') + 
                                       (response.data || ai_assistant_admin.strings.unknown_error || 'Unknown error');
                        $status.text(errorMsg).css('color', 'red');
                    }
                },
                error: function() {
                    const errorMsg = ai_assistant_admin.strings.compilation_request_failed || 
                                   'Compilation request failed. Please try again.';
                    $('#compile-status').text(errorMsg).css('color', 'red');
                },
                complete: function() {
                    const buttonText = ai_assistant_admin.strings.compile_button || 'Compile All .mo Files';
                    $button.prop('disabled', false).text(buttonText);
                }
            });
        },

        // Utility: Show loading overlay
        showLoading: function($element) {
            $element.addClass('ai-assistant-loading');
        },

        // Utility: Hide loading overlay
        hideLoading: function($element) {
            $element.removeClass('ai-assistant-loading');
        },

        // Utility: Debounce function calls
        debounce: function(func, wait, immediate) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };

    // Handle AJAX language saving
    $(document).on('wp_ajax_ai_assistant_save_language', function(e, data) {
        // This would be handled server-side, but we can add client-side validation here
    });

    // Add keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save translations
        if ((e.ctrlKey || e.metaKey) && e.which === 83) {
            const $saveButton = $('input[name="save_translations"]');
            if ($saveButton.length && $saveButton.is(':visible')) {
                e.preventDefault();
                $saveButton.closest('form').submit();
            }
        }
        
        // Escape to close notices
        if (e.which === 27) {
            $('.notice-dismiss:visible').first().click();
        }
    });

    // Initialize tooltips if available
    if (typeof $.fn.tooltip === 'function') {
        $('[title]').tooltip({
            position: { my: "left+15 center", at: "right center" }
        });
    }

})(jQuery);

// Console debug info
if (window.console && window.console.log) {
    console.log('AI Assistant Admin JS loaded - Version 1.0.58');
}
