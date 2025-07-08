/**
 * AI Assistant Admin JavaScript
 * Version: 1.0.57
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
            this.initDashboard();
            this.initCompilationTools();
            console.log('AI Assistant Admin: Initialized');
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
            
            // Auto-translate functionality
            $('#auto-translate').on('click', function(e) {
                e.preventDefault();
                self.handleAutoTranslate($(this));
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

        // Handle auto-translation
        handleAutoTranslate: function($button) {
            const self = this;
            const untranslatedStrings = [];
            
            // Collect untranslated strings
            $('.translation-row:not(.hidden)').each(function() {
                const $row = $(this);
                const $input = $row.find('.translation-input');
                const msgid = $input.closest('tr').find('input[name*="[msgid]"]').val();
                
                if (!$input.val().trim() && msgid) {
                    untranslatedStrings.push(msgid);
                }
            });
            
            if (untranslatedStrings.length === 0) {
                alert('No untranslated strings found.');
                return;
            }
            
            const confirmMessage = 'This will attempt to auto-translate ' + 
                                 untranslatedStrings.length + 
                                 ' empty strings using AI. Continue?';
            
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
            
            // Make AJAX request
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
                    if (response.success) {
                        let translatedCount = 0;
                        
                        // Apply translations to form
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
                        self.showNotice('Successfully translated ' + translatedCount + ' strings!', 'success');
                        
                    } else {
                        self.showNotice('Translation failed: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
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
    console.log('AI Assistant Admin JS loaded - Version 1.0.33');
}
