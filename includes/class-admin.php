<?php
/**
 * Admin Class
 * Handles admin interface and functionality
 *
 * @package AIAssistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Assistant_Admin {
    
    /**
     * Get current user's preferred language for AI Assistant
     * Falls back to global setting, then WordPress locale
     */
    private function get_user_language() {
        $current_user_id = get_current_user_id();
        
        // Get user-specific setting first
        $user_language = get_user_meta($current_user_id, 'ai_assistant_language', true);
        
        if (!empty($user_language)) {
            return $user_language;
        }
        
        // Fallback to global setting
        $global_language = get_option('ai_assistant_admin_language');
        if (!empty($global_language)) {
            return $global_language;
        }
        
        // Final fallback to WordPress locale
        return get_locale();
    }
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_ai_assistant_test_connection', array($this, 'test_api_connection'));
        
        // Load custom language if set - higher priority to ensure it loads after WordPress locale
        add_action('init', array($this, 'load_custom_language'), 5);
        add_action('admin_init', array($this, 'load_custom_language'), 5);
        
        // Handle AJAX request to compile .mo files
        add_action('wp_ajax_ai_assistant_handle_compile_mo_files', array($this, 'handle_compile_mo_files'));
        
        // AJAX handler for debugging language loading
        add_action('wp_ajax_ai_assistant_debug_language_loading', array($this, 'ajax_debug_language_loading'));
        
        // AJAX handler for auto-translating empty .po strings in translation management
        add_action('wp_ajax_ai_assistant_auto_translate', array($this, 'ajax_auto_translate'));
        
        // AJAX handler for exporting .po files
        add_action('wp_ajax_ai_assistant_export_po', array($this, 'ajax_export_po'));
    }
    
    /**
     * Add admin pages
     */
    public function add_admin_pages() {
        // Main dashboard page
        add_menu_page(
            __('AI Assistant', 'ai-assistant'),
            __('AI Assistant', 'ai-assistant'),
            'manage_options',
            'ai-assistant',
            array($this, 'dashboard_page'),
            'dashicons-translation',
            30
        );
        
        // Settings page
        add_submenu_page(
            'ai-assistant',
            __('Settings', 'ai-assistant'),
            __('Settings', 'ai-assistant'),
            'manage_options',
            'ai-assistant-settings',
            array($this, 'settings_page')
        );
        
        // Translation history page
        add_submenu_page(
            'ai-assistant',
            __('Translation History', 'ai-assistant'),
            __('Translation History', 'ai-assistant'),
            'edit_posts',
            'ai-assistant-history',
            array($this, 'history_page')
        );
        
        // Translation management page
        add_submenu_page(
            'ai-assistant',
            __('Translation Management', 'ai-assistant'),
            __('Translation Management', 'ai-assistant'),
            'manage_options',
            'ai-assistant-translations',
            array($this, 'translation_management_page')
        );
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('AI Assistant Dashboard', 'ai-assistant'); ?></h1>
            
            <div class="ai-assistant-dashboard">
                <div class="ai-assistant-stats">
                    <h2><?php _e('Usage Statistics', 'ai-assistant'); ?></h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <h3><?php echo $this->get_translation_count(); ?></h3>
                            <p><?php _e('Translations this month', 'ai-assistant'); ?></p>
                        </div>
                        <div class="stat-item">
                            <h3><?php echo $this->get_content_suggestions_count(); ?></h3>
                            <p><?php _e('Content suggestions', 'ai-assistant'); ?></p>
                        </div>
                        <div class="stat-item">
                            <h3><?php echo $this->get_active_models_count(); ?></h3>
                            <p><?php _e('Active AI models', 'ai-assistant'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="ai-assistant-quick-actions">
                    <h2><?php _e('Quick Actions', 'ai-assistant'); ?></h2>
                    <div class="actions-grid">
                        <a href="<?php echo admin_url('admin.php?page=ai-assistant-settings'); ?>" class="action-button">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Configure Settings', 'ai-assistant'); ?>
                        </a>
                        <a href="<?php echo admin_url('post-new.php'); ?>" class="action-button">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Create New Post', 'ai-assistant'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=ai-assistant-history'); ?>" class="action-button">
                            <span class="dashicons dashicons-clock"></span>
                            <?php _e('View History', 'ai-assistant'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="ai-assistant-status">
                    <h2><?php _e('System Status', 'ai-assistant'); ?></h2>
                    <div class="status-items">
                        <?php $this->display_system_status(); ?>
                    </div>
                </div>
                
                <div class="ai-assistant-multilingual-status">
                    <h2><?php _e('Multilingual Status', 'ai-assistant'); ?></h2>
                    <?php $this->display_multilingual_status(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Save settings if form submitted
        if (isset($_POST['save_language_settings'])) {
            if (isset($_POST['admin_language'])) {
                $new_language = sanitize_text_field($_POST['admin_language']);
                $current_user_id = get_current_user_id();
                $old_language = $this->get_user_language();
                
                // Save the new language for this specific user
                update_user_meta($current_user_id, 'ai_assistant_language', $new_language);
                
                // If language changed, reload the page to apply new language
                if ($new_language !== $old_language) {
                    // Force reload textdomain
                    unload_textdomain('ai-assistant');
                    if ($new_language !== 'en_US') {
                        $this->load_plugin_textdomain_custom($new_language);
                    }
                    
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         __('Language settings saved successfully. This is your personal language setting for AI Assistant.', 'ai-assistant') . 
                         '</p></div>';
                    
                    // JavaScript to reload the page
                    echo '<script>setTimeout(function() { window.location.reload(); }, 500);</script>';
                }
                
                add_settings_error('ai_assistant_settings', 'language_updated', __('Language settings saved successfully.', 'ai-assistant'), 'updated');
            }
        }
        
        $current_admin_language = $this->get_user_language();
        ?>
        <div class="wrap">
            <h1><?php _e('AI Assistant Settings', 'ai-assistant'); ?></h1>
            
            <?php settings_errors(); ?>
            
            <!-- Quick System Status -->
            <div class="notice notice-info ai-system-status-notice">
                <div class="ai-system-status-flex">
                    <div>
                        <h4 class="ai-system-status-title">⚙️ <?php _e('System Status', 'ai-assistant'); ?></h4>
                        <p class="ai-system-status-description">
                            <?php 
                            $api_keys = get_option('ai_assistant_api_keys', array());
                            $has_api = !empty($api_keys['openai']) || !empty($api_keys['anthropic']) || !empty($api_keys['gemini']);
                            if ($has_api) {
                                echo '✅ ' . __('API configured', 'ai-assistant') . ' • ';
                            } else {
                                echo '⚠️ ' . __('API not configured', 'ai-assistant') . ' • ';
                            }
                            
                            $lang_dir = plugin_dir_path(dirname(__FILE__)) . 'languages/';
                            $mo_files = glob($lang_dir . '*.mo');
                            echo count($mo_files) . ' ' . __('languages available', 'ai-assistant');
                            
                            if (is_textdomain_loaded('ai-assistant')) {
                                echo ' • ✅ ' . __('Translations loaded', 'ai-assistant');
                            }
                            ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo admin_url('admin.php?page=ai-assistant-diagnostics'); ?>" class="button button-secondary">
                            🔍 <?php _e('View Diagnostics', 'ai-assistant'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Language Selection Section -->
            <div class="ai-assistant-language-section">
                <h2><?php _e('Language Settings', 'ai-assistant'); ?></h2>
                <form method="post" action="">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Admin Interface Language', 'ai-assistant'); ?></th>
                            <td>
                                <select name="admin_language" class="regular-text">
                                    <?php
                                    // Get available languages dynamically from WordPress
                                    $available_languages = get_available_languages();
                                    $current_locale = get_locale();
                                    
                                    // Build supported languages array with current locale first
                                    $supported_languages = array();
                                    $supported_languages[$current_locale] = $this->get_language_display_name($current_locale);
                                    
                                    // Add commonly used languages
                                    $common_languages = array('en_US', 'tr_TR', 'fa_IR', 'nl_NL', 'da_DK', 'fr_FR', 'az_AZ', 'uz_UZ', 'ar', 'ky_KG', 'ru_RU', 'pt_PT', 'es_ES', 'de_DE', 'zh_CN', 'ug_CN', 'ur', 'fi', 'tk');
                                    foreach ($common_languages as $lang_code) {
                                        if (!isset($supported_languages[$lang_code])) {
                                            $supported_languages[$lang_code] = $this->get_language_display_name($lang_code);
                                        }
                                    }
                                    
                                    // Add any additional available languages from WordPress
                                    foreach ($available_languages as $lang_code) {
                                        if (!isset($supported_languages[$lang_code])) {
                                            $supported_languages[$lang_code] = $this->get_language_display_name($lang_code);
                                        }
                                    }
                                    
                                    foreach ($supported_languages as $code => $name) {
                                        $selected = selected($this->get_user_language(), $code, false);
                                        echo "<option value='{$code}' {$selected}>{$name}</option>";
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    <?php _e('Select your personal language for the AI Assistant interface. This setting is specific to your user account and won\'t affect other users or the WordPress site language.', 'ai-assistant'); ?>
                                    <br><em><?php _e('Note: Each user can choose their own preferred language for the AI Assistant plugin.', 'ai-assistant'); ?></em>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Language Settings', 'ai-assistant'), 'primary', 'save_language_settings'); ?>
                </form>
            </div>
            
            <hr>
            
            <!-- API Settings Section -->
            <form method="post" action="options.php">
                <h2><?php _e('API Configuration', 'ai-assistant'); ?></h2>
                <?php
                settings_fields('ai_assistant_settings');
                do_settings_sections('ai_assistant_settings');
                submit_button(__('Save API Settings', 'ai-assistant'), 'primary', 'save_api_settings');
                ?>
            </form>
            
            <!-- Test API Section -->
              <div class="ai-assistant-test-section">
                <h2><?php _e('Test API Connection', 'ai-assistant'); ?></h2>
                <p><?php _e('Test your Gemini API key to ensure it is working correctly.', 'ai-assistant'); ?></p>
                
                <button type="button" id="test-gemini" class="button button-primary test-api-button">
                    <?php _e('Test Gemini API', 'ai-assistant'); ?>
                </button>
                
                <div id="test-results"></div>
            </div>
            
            <!-- Compile Tools Section -->
            <div class="compile-tools-section">
                <h3><?php _e('Compilation Tools', 'ai-assistant'); ?></h3>
                <p><?php _e('Manage .mo file compilation for all languages.', 'ai-assistant'); ?></p>
                
                <div class="compile-buttons">
                    <button type="button" id="compile-all-mo" class="button button-primary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Compile All .mo Files', 'ai-assistant'); ?>
                    </button>
                    <button type="button" id="clear-ai-cache" class="button button-secondary" style="margin-left: 10px;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clear AI Cache', 'ai-assistant'); ?>
                    </button>
                    <span class="compile-status" id="compile-status"></span>
                </div>
                
                <div class="compile-info">
                    <p><small><?php _e('This will compile all .po files to .mo files. Required for translations to work in WordPress.', 'ai-assistant'); ?></small></p>
                </div>
            </div>

            <!-- Current Language Section -->
        </div>
        <?php
    }
    
    /**
     * History page
     */
    public function history_page() {
        global $wpdb;
        
        // Handle view parameter for detailed view
        if (isset($_GET['view']) && is_numeric($_GET['view'])) {
            $this->view_translation_details(intval($_GET['view']));
            return;
        }
        
        // Handle view_suggestion parameter for detailed suggestion view
        if (isset($_GET['view_suggestion']) && is_numeric($_GET['view_suggestion'])) {
            $this->view_suggestion_details(intval($_GET['view_suggestion']));
            return;
        }
        
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'translations';
        
        $translations_table = $wpdb->prefix . 'ai_assistant_translations';
        $suggestions_table = $wpdb->prefix . 'ai_assistant_suggestions';
        
        // Check if tables exist
        $translations_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$translations_table'") === $translations_table;
        $suggestions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$suggestions_table'") === $suggestions_table;
        
        if (!$translations_table_exists) {
            $this->ensure_translation_table_exists();
            $translations_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$translations_table'") === $translations_table;
        }
        
        if (!$suggestions_table_exists) {
            $this->ensure_suggestions_table_exists();
            $suggestions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$suggestions_table'") === $suggestions_table;
            
            // Log table creation attempt
            if ($suggestions_table_exists) {
                AIAssistant::log('Successfully created suggestions table', true);
            } else {
                AIAssistant::log('Failed to create suggestions table', true);
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('AI Assistant History', 'ai-assistant'); ?></h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=ai-assistant-history&tab=translations'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'translations' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Translations', 'ai-assistant'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ai-assistant-history&tab=suggestions'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'suggestions' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Suggestions', 'ai-assistant'); ?>
                </a>
            </nav>
            
            <div class="tab-content ai-tab-content-margin">
                <?php if ($current_tab === 'translations'): ?>
                    <?php $this->render_translations_history($translations_table, $translations_table_exists); ?>
                <?php elseif ($current_tab === 'suggestions'): ?>
                    <?php $this->render_suggestions_history($suggestions_table, $suggestions_table_exists); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
    }
    
    /**
     * Render translations history table
     */
    private function render_translations_history($table_name, $table_exists) {
        global $wpdb;
        
        $translations = array();
        $error_message = '';
        
        if ($table_exists) {
            $translations = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 50");
            if ($wpdb->last_error) {
                $error_message = $wpdb->last_error;
            }
        }
        
        ?>
        <h2><?php _e('Translation History', 'ai-assistant'); ?></h2>
        
        <?php if (!$table_exists): ?>
            <div class="notice notice-error">
                <p><?php _e('Translation history table does not exist. Please contact your administrator.', 'ai-assistant'); ?></p>
            </div>
        <?php elseif ($error_message): ?>
            <div class="notice notice-error">
                <p><?php echo sprintf(__('Database error: %s', 'ai-assistant'), esc_html($error_message)); ?></p>
            </div>
        <?php endif; ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'ai-assistant'); ?></th>
                    <th><?php _e('Post/URL', 'ai-assistant'); ?></th>
                    <th><?php _e('From', 'ai-assistant'); ?></th>
                    <th><?php _e('To', 'ai-assistant'); ?></th>
                    <th><?php _e('Model', 'ai-assistant'); ?></th>
                    <th><?php _e('Status', 'ai-assistant'); ?></th>
                    <th><?php _e('Actions', 'ai-assistant'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($translations)): ?>
                    <tr>
                        <td colspan="7">
                            <?php 
                            if ($table_exists) {
                                _e('No translations found. Try performing some translations to see them here.', 'ai-assistant');
                            } else {
                                _e('Translation history table not available.', 'ai-assistant');
                            }
                            ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($translations as $translation): ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($translation->created_at))); ?></td>
                            <td>
                                <?php 
                                if (!empty($translation->post_id)) {
                                    $post = get_post($translation->post_id);
                                    if ($post) {
                                        echo '<a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html($post->post_title) . '</a>';
                                    } else {
                                        echo sprintf(__('Post #%d (deleted)', 'ai-assistant'), $translation->post_id);
                                    }
                                } elseif (!empty($translation->source_url)) {
                                    echo '<a href="' . esc_url($translation->source_url) . '" target="_blank">' . esc_html($translation->source_url) . '</a>';
                                } else {
                                    _e('Manual Translation', 'ai-assistant');
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(strtoupper($translation->source_language)); ?></td>
                            <td><?php echo esc_html(strtoupper($translation->target_language)); ?></td>
                            <td><?php echo esc_html($translation->model); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($translation->status); ?>">
                                    <?php echo esc_html(ucfirst($translation->status)); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=ai-assistant-history&view=' . $translation->id); ?>" class="button button-small">
                                    <?php _e('View', 'ai-assistant'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render suggestions history table
     */
    private function render_suggestions_history($table_name, $table_exists) {
        global $wpdb;
        
        $suggestions = array();
        $error_message = '';
        
        if ($table_exists) {
            $suggestions = $wpdb->get_results("
                SELECT s.*, u.display_name as user_name, p.post_title 
                FROM $table_name s 
                LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID 
                LEFT JOIN {$wpdb->posts} p ON s.post_id = p.ID 
                ORDER BY s.created_at DESC 
                LIMIT 100
            ");
            if ($wpdb->last_error) {
                $error_message = $wpdb->last_error;
            }
        }
        
        ?>
        <h2><?php _e('Content Suggestions History', 'ai-assistant'); ?></h2>
        
        <?php if (!$table_exists): ?>
            <div class="notice notice-error">
                <p><?php _e('Suggestions history table does not exist. Please contact your administrator.', 'ai-assistant'); ?></p>
            </div>
        <?php elseif ($error_message): ?>
            <div class="notice notice-error">
                <p><?php echo sprintf(__('Database error: %s', 'ai-assistant'), esc_html($error_message)); ?></p>
            </div>
        <?php endif; ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'ai-assistant'); ?></th>
                    <th><?php _e('User', 'ai-assistant'); ?></th>
                    <th><?php _e('Input Text', 'ai-assistant'); ?></th>
                    <th><?php _e('Suggestion', 'ai-assistant'); ?></th>
                    <th><?php _e('Type', 'ai-assistant'); ?></th>
                    <th><?php _e('Model', 'ai-assistant'); ?></th>
                    <th><?php _e('Post', 'ai-assistant'); ?></th>
                    <th><?php _e('Actions', 'ai-assistant'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suggestions)): ?>
                    <tr>
                        <td colspan="8">
                            <?php 
                            if ($table_exists) {
                                _e('No suggestions found. Start using the AI Assistant to generate content suggestions.', 'ai-assistant');
                            } else {
                                _e('Suggestions history table not available.', 'ai-assistant');
                            }
                            ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($suggestions as $suggestion): ?>
                        <tr>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($suggestion->created_at))); ?></td>
                            <td><?php echo esc_html($suggestion->user_name ?: 'Unknown User'); ?></td>
                            <td>
                                <span class="input-text-preview" title="<?php echo esc_attr($suggestion->input_text); ?>">
                                    <?php echo esc_html(wp_trim_words($suggestion->input_text, 8, '...')); ?>
                                </span>
                            </td>
                            <td>
                                <span class="suggestion-preview" title="<?php echo esc_attr($suggestion->suggestion_text); ?>">
                                    <?php echo esc_html(wp_trim_words($suggestion->suggestion_text, 10, '...')); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(ucfirst($suggestion->suggestion_type)); ?></td>
                            <td><?php echo esc_html($suggestion->model); ?></td>
                            <td>
                                <?php if (!empty($suggestion->post_id) && !empty($suggestion->post_title)): ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($suggestion->post_id)); ?>" target="_blank">
                                        <?php echo esc_html($suggestion->post_title); ?>
                                    </a>
                                <?php elseif (!empty($suggestion->post_id)): ?>
                                    <?php echo sprintf(__('Post #%d', 'ai-assistant'), $suggestion->post_id); ?>
                                <?php else: ?>
                                    <span class="ai-global-status"><?php _e('Global', 'ai-assistant'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=ai-assistant-history&tab=suggestions&view_suggestion=' . $suggestion->id); ?>" class="button button-small">
                                    <?php _e('View Details', 'ai-assistant'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * View detailed translation information
     */
    public function view_translation_details($translation_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        
        // Get translation details
        $translation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $translation_id
        ));
        
        if (!$translation) {
            wp_die(__('Translation not found.', 'ai-assistant'));
        }
        
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Translation Details', 'ai-assistant'); ?>
                <a href="<?php echo admin_url('admin.php?page=ai-assistant-history'); ?>" class="page-title-action">
                    <?php _e('← Back to History', 'ai-assistant'); ?>
                </a>
            </h1>
            
            <div class="ai-assistant-translation-details">
                <div class="translation-meta-box">
                    <h2><?php _e('Translation Information', 'ai-assistant'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Translation ID', 'ai-assistant'); ?></th>
                            <td><?php echo esc_html($translation->id); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Date & Time', 'ai-assistant'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($translation->created_at))); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Source Language', 'ai-assistant'); ?></th>
                            <td>
                                <span class="language-badge">
                                    <?php echo esc_html($this->get_language_name($translation->source_language)); ?>
                                    (<?php echo esc_html($translation->source_language); ?>)
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Target Language', 'ai-assistant'); ?></th>
                            <td>
                                <span class="language-badge">
                                    <?php echo esc_html($this->get_language_name($translation->target_language)); ?>
                                    (<?php echo esc_html($translation->target_language); ?>)
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('AI Model', 'ai-assistant'); ?></th>
                            <td><code><?php echo esc_html($translation->model ?: 'N/A'); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Status', 'ai-assistant'); ?></th>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($translation->status); ?>">
                                    <?php echo esc_html(ucfirst($translation->status)); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if ($translation->post_id): ?>
                        <tr>
                            <th scope="row"><?php _e('Associated Post', 'ai-assistant'); ?></th>
                            <td>
                                <a href="<?php echo get_edit_post_link($translation->post_id); ?>" target="_blank">
                                    <?php echo esc_html(get_the_title($translation->post_id) ?: "Post ID: {$translation->post_id}"); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($translation->source_url): ?>
                        <tr>
                            <th scope="row"><?php _e('Source URL', 'ai-assistant'); ?></th>
                            <td>
                                <a href="<?php echo esc_url($translation->source_url); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($translation->source_url); ?>
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <div class="translation-content-box">
                    <h2><?php _e('Content Comparison', 'ai-assistant'); ?></h2>
                    <div class="translation-comparison">
                        <div class="original-content">
                            <h3>
                                <?php _e('Original Content', 'ai-assistant'); ?>
                                <span class="content-stats">
                                    (<?php echo number_format(strlen($translation->original_content)); ?> <?php _e('characters', 'ai-assistant'); ?>)
                                </span>
                            </h3>
                            <div class="content-box">
                                <?php echo wp_kses_post(nl2br(esc_html($translation->original_content))); ?>
                            </div>
                        </div>
                        
                        <div class="translated-content">
                            <h3>
                                <?php _e('Translated Content', 'ai-assistant'); ?>
                                <span class="content-stats">
                                    (<?php echo number_format(strlen($translation->translated_content)); ?> <?php _e('characters', 'ai-assistant'); ?>)
                                </span>
                            </h3>
                            <div class="content-box">
                                <?php echo wp_kses_post(nl2br(esc_html($translation->translated_content))); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="translation-actions">
                    <a href="<?php echo admin_url('admin.php?page=ai-assistant-history'); ?>" class="button">
                        <?php _e('← Back to History', 'ai-assistant'); ?>
                    </a>
                    <?php if ($translation->post_id): ?>
                    <a href="<?php echo get_edit_post_link($translation->post_id); ?>" class="button button-secondary" target="_blank">
                        <?php _e('Edit Associated Post', 'ai-assistant'); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * View detailed suggestion information
     */
    public function view_suggestion_details($suggestion_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_assistant_suggestions';
        
        // Get suggestion details with user and post info
        $suggestion = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, u.display_name as user_name, u.user_email, p.post_title, p.post_status 
             FROM $table_name s 
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID 
             LEFT JOIN {$wpdb->posts} p ON s.post_id = p.ID 
             WHERE s.id = %d",
            $suggestion_id
        ));
        
        if (!$suggestion) {
            wp_die(__('Suggestion not found.', 'ai-assistant'));
        }
        
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Suggestion Details', 'ai-assistant'); ?>
                <a href="<?php echo admin_url('admin.php?page=ai-assistant-history&tab=suggestions'); ?>" class="page-title-action">
                    <?php _e('← Back to Suggestions', 'ai-assistant'); ?>
                </a>
            </h1>
            
            <div class="ai-assistant-suggestion-details">
                <div class="suggestion-meta-box">
                    <h2><?php _e('Suggestion Information', 'ai-assistant'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Suggestion ID', 'ai-assistant'); ?></th>
                            <td><?php echo esc_html($suggestion->id); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Date & Time', 'ai-assistant'); ?></th>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($suggestion->created_at))); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('User', 'ai-assistant'); ?></th>
                            <td>
                                <?php if (!empty($suggestion->user_name)): ?>
                                    <strong><?php echo esc_html($suggestion->user_name); ?></strong>
                                    <br><small><?php echo esc_html($suggestion->user_email); ?></small>
                                <?php else: ?>
                                    <span class="ai-unknown-user"><?php _e('Unknown User', 'ai-assistant'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Suggestion Type', 'ai-assistant'); ?></th>
                            <td>
                                <span class="suggestion-type-badge suggestion-type-<?php echo esc_attr($suggestion->suggestion_type); ?>">
                                    <?php echo esc_html(ucfirst(str_replace('-', ' ', $suggestion->suggestion_type))); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('AI Model', 'ai-assistant'); ?></th>
                            <td><code><?php echo esc_html($suggestion->model ?: 'N/A'); ?></code></td>
                        </tr>
                        <?php if ($suggestion->post_id): ?>
                        <tr>
                            <th scope="row"><?php _e('Associated Post', 'ai-assistant'); ?></th>
                            <td>
                                <?php if (!empty($suggestion->post_title)): ?>
                                    <a href="<?php echo get_edit_post_link($suggestion->post_id); ?>" target="_blank">
                                        <strong><?php echo esc_html($suggestion->post_title); ?></strong>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                    <br><small>Status: <?php echo esc_html(ucfirst($suggestion->post_status)); ?></small>
                                <?php else: ?>
                                    <a href="<?php echo get_edit_post_link($suggestion->post_id); ?>" target="_blank">
                                        <?php echo sprintf(__('Post ID: %d', 'ai-assistant'), $suggestion->post_id); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <th scope="row"><?php _e('Context', 'ai-assistant'); ?></th>
                            <td><span class="ai-global-suggestion"><?php _e('Global suggestion (not associated with a specific post)', 'ai-assistant'); ?></span></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <div class="suggestion-content-box">
                    <h2><?php _e('Content Details', 'ai-assistant'); ?></h2>
                    <div class="suggestion-comparison">
                        <div class="input-content">
                            <h3>
                                <?php _e('Input/Context', 'ai-assistant'); ?>
                                <span class="content-stats">
                                    (<?php echo number_format(strlen($suggestion->input_text)); ?> <?php _e('characters', 'ai-assistant'); ?>)
                                </span>
                            </h3>
                            <div class="content-box">
                                <?php echo wp_kses_post(nl2br(esc_html($suggestion->input_text))); ?>
                            </div>
                        </div>
                        
                        <div class="generated-content">
                            <h3>
                                <?php _e('Generated Suggestion', 'ai-assistant'); ?>
                                <span class="content-stats">
                                    (<?php echo number_format(strlen($suggestion->suggestion_text)); ?> <?php _e('characters', 'ai-assistant'); ?>)
                                </span>
                            </h3>
                            <div class="content-box">
                                <?php echo wp_kses_post(nl2br(esc_html($suggestion->suggestion_text))); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="suggestion-actions">
                    <a href="<?php echo admin_url('admin.php?page=ai-assistant-history&tab=suggestions'); ?>" class="button">
                        <?php _e('← Back to Suggestions', 'ai-assistant'); ?>
                    </a>
                    <?php if ($suggestion->post_id): ?>
                    <a href="<?php echo get_edit_post_link($suggestion->post_id); ?>" class="button button-secondary" target="_blank">
                        <?php _e('Edit Associated Post', 'ai-assistant'); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Get language name from code
     */
    private function get_language_name($code) {
        $languages = array(
            'en' => 'English',
            'tr' => 'Türkçe',
            'ar' => 'العربية',
            'zh' => '中文',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'nl' => 'Nederlands',
            'es' => 'Español',
            'pt' => 'Português',
            'da' => 'Dansk',
            'fi' => 'Suomi',
            'ru' => 'Русский',
            'fa' => 'فارسی',
            'ug' => 'ئۇيغۇرچە',
            'az' => 'Azərbaycan dili',
            'uz' => 'O\'zbek tili',
            'ky' => 'Кыргыз тили',
            'ur' => 'اردو',
            'auto' => 'Auto-detect'
        );
        
        return isset($languages[$code]) ? $languages[$code] : $code;
    }
    
    /**
     * Translation Management page
     */
    public function translation_management_page() {
        // Handle form submissions
        if (isset($_POST['save_translations'])) {
            $this->save_po_translations();
        }
        
        $current_language = isset($_GET['edit_lang']) ? sanitize_text_field($_GET['edit_lang']) : $this->get_user_language();
        $supported_languages = $this->get_supported_languages();
        
        // Load current .po file translations
        $po_translations = $this->load_po_file_translations($current_language);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Translation Management', 'ai-assistant'); ?></h1>
            
            <?php settings_errors('translation_management'); ?>
            
            <div class="ai-assistant-translation-management">
                <p><?php _e('Edit the plugin interface translations directly from .po files. Changes are saved to the language files and compiled automatically.', 'ai-assistant'); ?></p>
                
                <!-- Language Selector -->
                <div class="language-selector-section">
                    <h3><?php _e('Select Language to Edit', 'ai-assistant'); ?></h3>
                    <select id="language-selector" class="regular-text">
                        <?php foreach ($supported_languages as $code => $name): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($current_language, $code); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="language-status">
                        <?php 
                        $po_file = $this->get_po_file_path($current_language);
                        if (file_exists($po_file)): ?>
                            <span class="status-indicator status-ok"></span>
                            <?php _e('Translation file exists', 'ai-assistant'); ?>
                        <?php else: ?>
                            <span class="status-indicator status-warning"></span>
                            <?php _e('No translation file found - will create new', 'ai-assistant'); ?>
                        <?php endif; ?>
                    </span>
                </div>
                
                <form method="post" action="">
                    <?php wp_nonce_field('ai_assistant_po_translations', 'po_translations_nonce'); ?>
                    <input type="hidden" name="edit_language" value="<?php echo esc_attr($current_language); ?>" />
                    
                    <h3><?php echo sprintf(__('Editing: %s', 'ai-assistant'), $supported_languages[$current_language]); ?></h3>
                    
                    <div class="translation-stats">
                        <span class="stat-item">
                            <strong><?php echo count($po_translations); ?></strong> <?php _e('total strings', 'ai-assistant'); ?>
                        </span>
                        <span class="stat-item">
                            <strong><?php echo count(array_filter($po_translations, function($t) { return !empty($t['msgstr']); })); ?></strong> <?php _e('translated', 'ai-assistant'); ?>
                        </span>
                        <span class="stat-item">
                            <strong><?php echo count(array_filter($po_translations, function($t) { return empty($t['msgstr']); })); ?></strong> <?php _e('untranslated', 'ai-assistant'); ?>
                        </span>
                    </div>
                    
                    <div class="translation-search">
                        <input type="text" id="translation-search" placeholder="<?php _e('Search translations...', 'ai-assistant'); ?>" class="regular-text" />
                        <label>
                            <input type="checkbox" id="show-untranslated-only" />
                            <?php _e('Show untranslated only', 'ai-assistant'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="show-translate-buttons" />
                            <?php _e('Show translate buttons for all strings', 'ai-assistant'); ?>
                        </label>
                    </div>
                    
                    <table class="widefat translation-table">
                        <thead>
                            <tr>
                                <th class="col-msgid"><?php _e('English Text (msgid)', 'ai-assistant'); ?></th>
                                <th class="col-msgstr"><?php _e('Translation (msgstr)', 'ai-assistant'); ?></th>
                                <th class="col-status"><?php _e('Status', 'ai-assistant'); ?></th>
                                <th class="col-actions"><?php _e('Actions', 'ai-assistant'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($po_translations as $index => $translation): ?>
                                <tr class="translation-row" data-msgid="<?php echo esc_attr(strtolower($translation['msgid'])); ?>">
                                    <td>
                                        <strong class="msgid-text"><?php echo esc_html($translation['msgid']); ?></strong>
                                        <?php if (!empty($translation['context'])): ?>
                                            <br><small class="context-text">Context: <?php echo esc_html($translation['context']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <textarea name="translations[<?php echo $index; ?>][msgstr]" 
                                                  class="translation-input regular-text" 
                                                  rows="2" 
                                                  placeholder="<?php _e('Enter translation...', 'ai-assistant'); ?>"><?php echo esc_textarea($translation['msgstr']); ?></textarea>
                                        <input type="hidden" name="translations[<?php echo $index; ?>][msgid]" value="<?php echo esc_attr($translation['msgid']); ?>" />
                                        <?php if (!empty($translation['context'])): ?>
                                            <input type="hidden" name="translations[<?php echo $index; ?>][context]" value="<?php echo esc_attr($translation['context']); ?>" />
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($translation['msgstr'])): ?>
                                            <span class="status-badge status-translated"><?php _e('Translated', 'ai-assistant'); ?></span>
                                        <?php else: ?>
                                            <span class="status-badge status-untranslated"><?php _e('Untranslated', 'ai-assistant'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions-cell">
                                        <button type="button" class="button button-small auto-translate-single" data-index="<?php echo $index; ?>" data-msgid="<?php echo esc_attr($translation['msgid']); ?>" style="<?php echo empty($translation['msgstr']) ? '' : 'display: none;'; ?>">
                                            🤖 <?php _e('AI Translate', 'ai-assistant'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="translation-actions">
                        <?php submit_button(__('Save Translations to .po File', 'ai-assistant'), 'primary', 'save_translations'); ?>
                        <button type="button" class="button button-secondary" id="auto-translate-all">
                            🤖 <?php _e('Auto Translate All Empty Strings', 'ai-assistant'); ?>
                        </button>
                        <button type="button" class="button" id="export-po">
                            <?php _e('Download .po File', 'ai-assistant'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Use AI to translate empty interface strings automatically, or fill them manually for better accuracy.', 'ai-assistant'); ?>
                        </p>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle AJAX request to compile .mo files
     *
     * @since 1.0.38
     */
    public function handle_compile_mo_files() {
        // Check nonce and capabilities
        if (!check_ajax_referer('ai_assistant_admin_nonce', 'nonce', false)) {
            wp_die(__('Security check failed.', 'ai-assistant'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'ai-assistant'));
        }

        $compiled_count = $this->compile_all_mo_files();

        wp_send_json_success(array(
            'message' => sprintf(__('Successfully compiled %d .mo files', 'ai-assistant'), $compiled_count)
        ));
    }
    
    /**
     * AJAX handler for debugging language loading
     */
    public function ajax_debug_language_loading() {
        check_ajax_referer('ai_assistant_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        ob_start();
        
        echo "<div class='notice notice-info'>";
        echo "<h3>🔍 Language Loading Debug Test Results</h3>";
        
        $custom_lang = $this->get_user_language();
        $current_locale = get_locale();
        
        echo "<div class='ai-debug-panel'>";
        echo "<h4>Basic Information</h4>";
        echo "<ul class='ai-debug-list'>";
        echo "<li><strong>WordPress Locale:</strong> " . $current_locale . "</li>";
        echo "<li><strong>Custom Language Setting:</strong> " . ($custom_lang ?: 'NOT SET') . "</li>";
        echo "<li><strong>Plugin Textdomain Loaded:</strong> " . (is_textdomain_loaded('ai-assistant') ? 'YES' : 'NO') . "</li>";
        echo "</ul>";
        echo "</div>";
        
        // Test translations
        echo "<div class='ai-debug-panel-info'>";
        echo "<h4>Translation Tests</h4>";
        echo "<ul class='ai-debug-list'>";
        echo "<li><strong>'AI Assistant':</strong> " . __('AI Assistant', 'ai-assistant') . "</li>";
        echo "<li><strong>'Settings':</strong> " . __('Settings', 'ai-assistant') . "</li>";
        echo "<li><strong>'Translate':</strong> " . __('Translate', 'ai-assistant') . "</li>";
        echo "<li><strong>'Translation Management':</strong> " . __('Translation Management', 'ai-assistant') . "</li>";
        echo "</ul>";
        echo "</div>";
        
        // File information
        $languages_dir = plugin_dir_path(dirname(__FILE__)) . 'languages/';
        echo "<div class='ai-debug-panel-warning'>";
        echo "<h4>File Information</h4>";
        
        if ($custom_lang) {
            $mo_file = $languages_dir . 'ai-assistant-' . $custom_lang . '.mo';
            $po_file = $languages_dir . 'ai-assistant-' . $custom_lang . '.po';
            
            echo "<ul style='margin-left: 20px;'>";
            echo "<li><strong>Expected .mo file:</strong> " . basename($mo_file) . "</li>";
            echo "<li><strong>.mo file exists:</strong> " . (file_exists($mo_file) ? 'YES (' . $this->format_file_size(filesize($mo_file)) . ')' : 'NO') . "</li>";
            echo "<li><strong>.po file exists:</strong> " . (file_exists($po_file) ? 'YES (' . $this->format_file_size(filesize($po_file)) . ')' : 'NO') . "</li>";
            echo "</ul>";
        }
        
        // Available files
        $mo_files = glob($languages_dir . 'ai-assistant-*.mo');
        echo "<h5>Available .mo files (" . count($mo_files) . "):</h5>";
        echo "<ul class='ai-debug-list-scroll'>";
        foreach ($mo_files as $mo_file) {
            $size = $this->format_file_size(filesize($mo_file));
            echo "<li>" . basename($mo_file) . " ({$size})</li>";
        }
        echo "</ul>";
        echo "</div>";
        
        // Manual test
        if ($custom_lang && $custom_lang !== $current_locale) {
            echo "<div class='ai-debug-panel-success'>";
            echo "<h4>Manual Language Loading Test</h4>";
            
            // Unload current textdomain
            $unloaded = unload_textdomain('ai-assistant');
            echo "<p><strong>Unload result:</strong> " . ($unloaded ? 'SUCCESS' : 'FAILED') . "</p>";
            
            // Try to load custom language
            $loaded = $this->load_plugin_textdomain_custom($custom_lang);
            echo "<p><strong>Custom load result:</strong> " . ($loaded ? 'SUCCESS' : 'FAILED') . "</p>";
            
            // Test translation after manual load
            echo "<p><strong>Test translation after manual load:</strong> " . __('AI Assistant Dashboard', 'ai-assistant') . "</p>";
            echo "</div>";
        }
        
        echo "</div>";
        
        $debug_output = ob_get_clean();
        
        wp_send_json_success($debug_output);
    }
    
    /**
     * AJAX handler for auto-translating empty strings in .po files
     * This is specifically for translation management, not content translation
     */
    public function ajax_auto_translate() {
        // Debug: Log all POST data first
        AIAssistant::log('Auto-translate AJAX called with POST data: ' . print_r($_POST, true), true);
        
        check_ajax_referer('ai_assistant_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            AIAssistant::log('Auto-translate failed: Unauthorized access for user: ' . get_current_user_id(), true);
            wp_send_json_error(__('Unauthorized access.', 'ai-assistant'));
        }
        
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
        $strings = isset($_POST['strings']) ? array_map('sanitize_text_field', $_POST['strings']) : array();
        
        // Enhanced debug logging
        AIAssistant::log('Auto-translate processing - Language: "' . $language . '", Strings count: ' . count($strings), true);
        AIAssistant::log('Auto-translate strings array: ' . print_r($strings, true), true);
        
        // Filter out invalid strings (actual error messages from system, not legitimate strings)
        $valid_strings = array();
        foreach ($strings as $string) {
            $string = trim($string);
            if (!empty($string) && 
                strlen($string) >= 3 &&
                // Only reject actual system error messages, not legitimate translatable strings
                !stripos($string, 'could not detect') &&
                !stripos($string, 'could not extract') &&
                !preg_match('/^(error|failed|invalid|missing):/i', $string) && // Only reject if starts with error/failed patterns
                $string !== 'error' && $string !== 'failed') { // Don't reject single words that might be legitimate
                $valid_strings[] = $string;
            } else {
                AIAssistant::log('Rejected invalid string: "' . $string . '"', true);
            }
        }
        
        if (empty($language) || empty($valid_strings)) {
            AIAssistant::log('VALIDATION FAILED - Empty language or no valid strings. Language: "' . $language . '", Valid strings count: ' . count($valid_strings), true);
            wp_send_json_error(__('Missing language or no valid strings to translate found.', 'ai-assistant'));
        }
        
        // Update strings array to use only valid strings
        $strings = $valid_strings;
        AIAssistant::log('Proceeding with ' . count($strings) . ' valid strings', true);
        
        // Initialize AI service
        if (!class_exists('AI_Assistant_AI_Service')) {
            AIAssistant::log('AI_Assistant_AI_Service class not found', true);
            wp_send_json_error(__('AI service not available.', 'ai-assistant'));
        }
        
        $ai_service = new AI_Assistant_AI_Service();
        $translated_strings = array();
        $failed_count = 0;
        
        // Check if AI service is properly configured
        $api_keys = get_option('ai_assistant_api_keys', array());
        $has_api_key = !empty($api_keys['openai']) || !empty($api_keys['anthropic']) || !empty($api_keys['gemini']);
        
        if (!$has_api_key) {
            AIAssistant::log('No API keys configured for translation', true);
            wp_send_json_error(__('No AI service API keys configured. Please check your settings.', 'ai-assistant'));
        }
        
        AIAssistant::log('Starting translation for ' . count($strings) . ' strings to ' . $language, true);
        
        // Get target language name for better context
        $target_language_name = $this->get_language_display_name($language);
        
        // Rate limiting tracking
        $last_request_time = 0;
        
        // Track API errors for better error reporting
        $api_errors = array();
        
        // Adaptive rate limiting based on batch size
        $batch_size = count($strings);
        $rate_limit_delay = 6; // Default 6 seconds for large batches
        
        if ($batch_size <= 3) {
            $rate_limit_delay = 3; // Faster processing for small batches (3 seconds)
        } elseif ($batch_size <= 5) {
            $rate_limit_delay = 4; // Medium processing for medium batches (4 seconds)
        }
        
        AIAssistant::log('Processing batch of ' . $batch_size . ' strings with ' . $rate_limit_delay . ' second rate limit', true);
        
        foreach ($strings as $string) {
            if (empty(trim($string))) continue;
            
            // Adaptive rate limiting based on batch size
            $time_since_last = microtime(true) - $last_request_time;
            if ($time_since_last < $rate_limit_delay && $last_request_time > 0) {
                $sleep_time = $rate_limit_delay - $time_since_last;
                AIAssistant::log('Rate limiting: sleeping for ' . round($sleep_time, 2) . ' seconds', true);
                usleep(intval($sleep_time * 1000000)); // Fix: cast to int to avoid deprecation warning
            }
            
            $last_request_time = microtime(true);
            
            // Skip empty strings and strings that are too short
            if (strlen($string) < 2) {
                AIAssistant::log('Skipping string too short: "' . $string . '"', true);
                $failed_count++;
                continue;
            }
            
            AIAssistant::log('Translating string: "' . $string . '" to ' . $language, true);
            
            // Create a very direct prompt to minimize reasoning tokens
            $prompt = sprintf(
                "Translate to %s: %s",
                $target_language_name,
                $string
            );
            
            // Try up to 3 times for reliability
            $max_attempts = 3;
            $translation_successful = false;
            
            for ($attempt = 1; $attempt <= $max_attempts && !$translation_successful; $attempt++) {
                if ($attempt > 1) {
                    AIAssistant::log('Retry attempt ' . $attempt . ' for string: "' . $string . '"', true);
                    usleep(1000000); // 1 second delay between retries
                }
                
                $result = $ai_service->make_api_request_public($prompt, array(
                    'max_tokens' => 300,  // Much higher limit to account for excessive thoughts tokens
                    'temperature' => 0.1, // Very low temperature for consistent translations
                    'simple_translation' => true, // Minimize reasoning tokens for simple translations
                    'model' => 'gemini-2.5-flash-lite' // Try lighter model for simple translations
                ));
                
                AIAssistant::log('AI service result for "' . $string . '" (attempt ' . $attempt . '): ' . print_r($result, true), true);
                
                if ($result['success'] && !empty($result['content'])) {
                    // Aggressively clean up the translation to extract only the translated text
                    $translation = trim($result['content']);
                    
                    // Remove quotes, asterisks, and other formatting
                    $translation = trim($translation, '"\'*');
                    
                    // If response contains multiple lines, take only the first meaningful line
                    $lines = explode("\n", $translation);
                    $translation = trim($lines[0]);
                    
                    // Remove any explanatory text patterns
                    $translation = preg_replace('/^(The translation is|Translation:|Translated:)\s*/i', '', $translation);
                    $translation = preg_replace('/\s*\([^)]*\)\s*$/', '', $translation); // Remove parenthetical explanations
                    
                    // Final cleanup
                    $translation = strip_tags($translation);
                    $translation = trim($translation, '"\'*');
                    
                    // More strict validation - translation should be different and not empty
                    if (!empty($translation) && $translation !== $string && strlen($translation) > 0) {
                        $translated_strings[$string] = $translation;
                        $translation_successful = true;
                        AIAssistant::log('Translation successful: "' . $string . '" -> "' . $translation . '"', true);
                        break; // Exit the retry loop
                    } else {
                        AIAssistant::log('Translation result invalid - empty, same as input, or too short for: "' . $string . '" -> "' . $translation . '"', true);
                    }
                } else {
                    $error_msg = isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : 'Unknown error');
                    AIAssistant::log('Translation API failed for "' . $string . '" (attempt ' . $attempt . '): ' . $error_msg, true);
                    
                    // Store API error for final error message
                    if (!empty($error_msg)) {
                        $api_errors[] = $error_msg;
                    }
                    
                    // Check for rate limiting and add longer delay
                    if (stripos($error_msg, '429') !== false || stripos($error_msg, 'quota') !== false || stripos($error_msg, 'rate') !== false) {
                        AIAssistant::log('Rate limiting detected for "' . $string . '", adding longer delay', true);
                        if ($attempt < $max_attempts) {
                            usleep(5000000); // 5 second delay for rate limiting
                        }
                    }
                }
            }
            
            if (!$translation_successful) {
                $failed_count++;
                AIAssistant::log('Translation completely failed for: "' . $string . '" after ' . $max_attempts . ' attempts', true);
            }
            
            // Longer delay to avoid API rate limits
            usleep(300000); // 0.3 seconds between strings
        }
        
        if (!empty($translated_strings)) {
            $success_count = count($translated_strings);
            $total_count = count($strings);
            AIAssistant::log('Translation completed. Translated ' . $success_count . ' of ' . $total_count . ' strings, failed ' . $failed_count, true);
            wp_send_json_success($translated_strings);
        } else {
            AIAssistant::log('Translation failed completely. Failed count: ' . $failed_count . ' out of ' . count($strings), true);
            
            // Get the most recent API error message to provide more specific feedback
            $last_error = '';
            if (!empty($api_errors)) {
                // Use the most recent error
                $last_error = end($api_errors);
            }
            
            // Provide more specific error message based on API response
            $error_message = __('No strings could be translated. ', 'ai-assistant');
            
            if (!empty($last_error)) {
                // Check for common API errors and provide user-friendly messages
                if (stripos($last_error, '429') !== false || stripos($last_error, 'quota') !== false || stripos($last_error, 'rate limit') !== false) {
                    $error_message .= __('API rate limit exceeded. Please wait a few minutes before trying again.', 'ai-assistant');
                } elseif (stripos($last_error, 'MAX_TOKENS') !== false || stripos($last_error, 'token') !== false) {
                    $error_message .= __('Content too long for API processing. Try translating fewer strings at once.', 'ai-assistant');
                } elseif (stripos($last_error, 'API key') !== false || stripos($last_error, 'authentication') !== false) {
                    $error_message .= __('API authentication failed. Please check your API key configuration.', 'ai-assistant');
                } else {
                    // Show the actual error message for other errors
                    $error_message .= sprintf(__('API Error: %s', 'ai-assistant'), $last_error);
                }
            } else {
                if ($failed_count > 0) {
                    $error_message .= sprintf(__('All %d translation attempts failed. ', 'ai-assistant'), $failed_count);
                }
                $error_message .= __('Please check your API configuration and try again.', 'ai-assistant');
            }
            
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * AJAX handler for exporting .po files
     */
    public function ajax_export_po() {
        // Debug logging
        AIAssistant::log('Export PO handler called. GET params: ' . print_r($_GET, true), true);
        
        // Check nonce and capabilities - use more permissive check
        $nonce_check = check_ajax_referer('ai_assistant_export_nonce', 'nonce', false);
        if (!$nonce_check) {
            AIAssistant::log('Export PO nonce check failed', true);
            wp_die(__('Security check failed.', 'ai-assistant'));
        }

        if (!current_user_can('manage_options')) {
            AIAssistant::log('Export PO insufficient permissions for user: ' . get_current_user_id(), true);
            wp_die(__('Insufficient permissions.', 'ai-assistant'));
        }

        $language = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : '';
        
        if (empty($language)) {
            AIAssistant::log('Export PO no language parameter provided', true);
            wp_die(__('Language parameter missing.', 'ai-assistant'));
        }

        $po_file_path = AI_ASSISTANT_PLUGIN_DIR . 'languages/ai-assistant-' . $language . '.po';
        
        AIAssistant::log('Export PO looking for file: ' . $po_file_path, true);
        
        if (!file_exists($po_file_path)) {
            AIAssistant::log('Export PO file not found: ' . $po_file_path, true);
            wp_die(__('Translation file not found.', 'ai-assistant'));
        }

        AIAssistant::log('Export PO file found, sending headers and file content', true);

        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="ai-assistant-' . $language . '.po"');
        header('Content-Length: ' . filesize($po_file_path));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output file content
        readfile($po_file_path);
        exit;
    }
    
    /**
     * Get supported languages array
     */
    private function get_supported_languages() {
        // Get available languages dynamically
        $available_languages = get_available_languages();
        $current_locale = get_locale();
        
        // Build array with current locale and common languages (English excluded as source language)
        $languages = array();
        if ($current_locale !== 'en_US') {
            $languages[$current_locale] = $this->get_language_display_name($current_locale) . ' ✅';
        }
        
        $common_languages = array('tr_TR', 'fa_IR', 'nl_NL', 'da_DK', 'fr_FR', 'az_AZ', 'uz_UZ', 'ar', 'ky_KG', 'ru_RU', 'pt_PT', 'es_ES', 'de_DE', 'zh_CN', 'ug_CN', 'ur', 'fi', 'tk');
        foreach ($common_languages as $lang_code) {
            if (!isset($languages[$lang_code])) {
                $languages[$lang_code] = $this->get_language_display_name($lang_code) . ' ✅';
            }
        }
        
        return $languages;
    }
    
    /**
     * Get languages with actual translation files (universally compatible)
     */
    private function get_available_translation_languages() {
        // Get available languages dynamically
        $available_languages = get_available_languages();
        $current_locale = get_locale();
        
        // Build array with current locale and common languages
        $languages = array();
        if ($current_locale !== 'en_US') {
            $languages[$current_locale] = $this->get_language_display_name($current_locale);
        }
        
        $common_languages = array('tr_TR', 'fa_IR', 'nl_NL', 'da_DK', 'fr_FR', 'az_AZ', 'uz_UZ', 'ar', 'ky_KG', 'ru_RU', 'pt_PT', 'es_ES', 'de_DE', 'zh_CN', 'ug_CN', 'ur', 'fi', 'tk');
        foreach ($common_languages as $lang_code) {
            if (!isset($languages[$lang_code])) {
                $languages[$lang_code] = $this->get_language_display_name($lang_code);
            }
        }
        
        return $languages;
    }
    
    /**
     * Save custom translations
     */
    private function save_custom_translations() {
        if (!wp_verify_nonce($_POST['translations_nonce'], 'ai_assistant_translations')) {
            add_settings_error('translation_management', 'nonce_error', __('Security check failed.', 'ai-assistant'), 'error');
            return;
        }
        
        $custom_translations = get_option('ai_assistant_custom_translations', array());
        
        if (isset($_POST['custom_translations'])) {
            foreach ($_POST['custom_translations'] as $lang => $translations) {
                foreach ($translations as $key => $value) {
                    if (!empty($value)) {
                        $custom_translations[$lang][$key] = sanitize_text_field($value);
                    }
                }
            }
        }
        
        // Add new custom translation
        if (!empty($_POST['custom_key']) && !empty($_POST['custom_value'])) {
            $current_lang = get_option('ai_assistant_admin_language', get_locale());
            $custom_translations[$current_lang][sanitize_text_field($_POST['custom_key'])] = sanitize_text_field($_POST['custom_value']);
        }
        
        update_option('ai_assistant_custom_translations', $custom_translations);
        add_settings_error('translation_management', 'translations_saved', __('Translations saved successfully!', 'ai-assistant'), 'updated');
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on AI Assistant admin pages
        if (strpos($hook, 'ai-assistant') === false) {
            return;
        }
        
        // Enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Enqueue admin styles
        wp_enqueue_style(
            'ai-assistant-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css',
            array(),
            AI_ASSISTANT_VERSION
        );
        
        // Enqueue admin scripts
        wp_enqueue_script(
            'ai-assistant-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
            array('jquery'),
            AI_ASSISTANT_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('ai-assistant-admin', 'ai_assistant_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_assistant_admin_nonce'),
            'export_nonce' => wp_create_nonce('ai_assistant_export_nonce'),
            'current_language' => $this->get_current_editing_language(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => array(
                'saving' => __('Saving...', 'ai-assistant'),
                'saved' => __('Saved!', 'ai-assistant'),
                'processing' => __('Processing...', 'ai-assistant'),
                'error' => __('Error saving settings', 'ai-assistant'),
                'saveLanguageSettings' => __('Save Language Settings', 'ai-assistant'),
                'confirm_reload' => __('Language changed. The page will reload to apply the new language.', 'ai-assistant'),
                'testing' => __('Testing...', 'ai-assistant'),
                'success' => __('Success!', 'ai-assistant'),
                'failed' => __('Test failed', 'ai-assistant'),
                'compiling' => __('Compiling .mo files...', 'ai-assistant'),
                'no_untranslated_found' => __('No untranslated strings found.', 'ai-assistant'),
                'auto_translate_confirm_start' => __('This will attempt to auto-translate ', 'ai-assistant'),
                'auto_translate_confirm_end' => __(' empty interface strings using AI. Continue?', 'ai-assistant'),
                'translating' => __('Translating...', 'ai-assistant'),
                'translating_short' => __('...', 'ai-assistant'),
                'translated' => __('Translated', 'ai-assistant'),
                'translation_failed' => __('Translation failed: ', 'ai-assistant'),
                'translation_failed_single' => __('Translation failed for this string.', 'ai-assistant'),
                'translation_request_failed' => __('Translation request failed. Please try again.', 'ai-assistant'),
                'unknown_error' => __('Unknown error', 'ai-assistant'),
                'success_translated_start' => __('Successfully translated ', 'ai-assistant'),
                'success_translated_end' => __(' interface strings!', 'ai-assistant'),
                'no_text_to_translate' => __('No text to translate.', 'ai-assistant'),
                'compile_confirm' => __('This will compile all .po files to .mo files. Required for translations to work in WordPress. Continue?', 'ai-assistant'),
                'compilation_failed' => __('Compilation failed: ', 'ai-assistant'),
                'compilation_request_failed' => __('Compilation request failed. Please try again.', 'ai-assistant'),
                'compile_button' => __('Compile All .mo Files', 'ai-assistant')
            )
        ));
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        check_ajax_referer('ai_assistant_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $ai_service = new AI_Assistant_AI_Service();
        
        // Test the connection
        $result = $ai_service->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'test_translation' => isset($result['test_translation']) ? $result['test_translation'] : ''
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error']
            ));
        }
    }
    
    /**
     * Get translation count
     */
    private function get_translation_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            // Create the table if it doesn't exist
            $this->create_translation_table();
            return 0; // Return 0 for now
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        return (int) $count;
    }
    
    /**
     * Ensure translation table exists (for admin page)
     */
    private function ensure_translation_table_exists() {
        $this->create_translation_table();
    }
    
    /**
     * Ensure suggestions table exists
     */
    private function ensure_suggestions_table_exists() {
        $this->create_suggestions_table();
    }
    
    /**
     * Create translation table if it doesn't exist
     */
    private function create_translation_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            source_url text DEFAULT NULL,
            source_language varchar(10) NOT NULL,
            target_language varchar(10) NOT NULL,
            original_content longtext NOT NULL,
            translated_content longtext NOT NULL,
            model varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'completed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_languages (source_language, target_language),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Create suggestions table if it doesn't exist
     */
    private function create_suggestions_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_assistant_suggestions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            input_text longtext NOT NULL,
            suggestion_text longtext NOT NULL,
            suggestion_type varchar(50) DEFAULT 'autocomplete',
            model varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_user_id (user_id),
            KEY idx_suggestion_type (suggestion_type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Log table creation result
        AIAssistant::log('Suggestions table creation result: ' . print_r($result, true), true);
        
        // Verify table exists after creation
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        AIAssistant::log('Suggestions table exists after creation: ' . ($table_exists ? 'YES' : 'NO'), true);
    }
    
    /**
     * Get content suggestions count
     */
    private function get_content_suggestions_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_assistant_suggestions';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            // Create the table if it doesn't exist
            $this->create_suggestions_table();
            return 0; // Return 0 for now
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)");
        return (int) $count;
        // This would be tracked in a separate table or option
        //return get_option('ai_assistant_suggestions_count', 0);
    }
    
    /**
     * Get active models count
     */
    private function get_active_models_count() {
        $api_keys = get_option('ai_assistant_api_keys', array());
        return count(array_filter($api_keys));
    }
    
    /**
     * Display system status
     */
    private function display_system_status() {
        $status_items = array();
        
        // Check if plugin is enabled
        $enabled = get_option('ai_assistant_enabled', true);
        $status_items[] = array(
            'label' => __('Plugin Status', 'ai-assistant'),
            'status' => $enabled ? 'ok' : 'error',
            'message' => $enabled ? __('Enabled', 'ai-assistant') : __('Disabled', 'ai-assistant')
        );
        
        // Check API keys
        $api_keys = get_option('ai_assistant_api_keys', array());
        $has_api_key = !empty(array_filter($api_keys));
        $status_items[] = array(
            'label' => __('API Configuration', 'ai-assistant'),
            'status' => $has_api_key ? 'ok' : 'error',
            'message' => $has_api_key ? __('Configured', 'ai-assistant') : __('No API keys configured', 'ai-assistant')
        );
        
        // Check database table
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $status_items[] = array(
            'label' => __('Database', 'ai-assistant'),
            'status' => $table_exists ? 'ok' : 'error',
            'message' => $table_exists ? __('Tables created', 'ai-assistant') : __('Tables missing', 'ai-assistant')
        );
        
        foreach ($status_items as $item) {
            ?>
            <div class="status-item">
                <span class="status-indicator status-<?php echo esc_attr($item['status']); ?>"></span>
                <strong><?php echo esc_html($item['label']); ?>:</strong>
                <?php echo esc_html($item['message']); ?>
            </div>
            <?php
        }
    }
    
    /**
     * Load custom language for AI Assistant
     */
    public function load_custom_language() {
        $custom_lang = $this->get_user_language();
        $current_locale = get_locale();
        
        // Only log once per session/request to avoid log spam
        static $logged_this_request = false;
        if (!$logged_this_request && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // Only log once per request to avoid spam
            $logged_this_request = true;
        }
        
        // Always load custom translation if set, even if it matches current locale
        // This is crucial because we need our plugin translations to override WordPress defaults
        if ($custom_lang) {
            // Critical: Always add the override filters FIRST
            add_filter('gettext', array($this, 'force_plugin_translations'), 999, 3);
            add_filter('gettext_with_context', array($this, 'force_plugin_translations_with_context'), 999, 4);
            
            // Unload current textdomain first
            $unloaded = unload_textdomain('ai-assistant');
            
            // Switch locale for this plugin only
            add_filter('plugin_locale', array($this, 'custom_plugin_locale'), 10, 2);
            
            // Load the specific translation with enhanced method
            $loaded = $this->load_plugin_textdomain_enhanced($custom_lang);
            
            // Force immediate reload to ensure our translations take precedence
            $this->force_immediate_translation_reload($custom_lang);
        }
    }
    
    /**
     * Force immediate translation reload to override any WordPress caching
     */
    private function force_immediate_translation_reload($locale) {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $mo_file = $plugin_dir . 'languages/ai-assistant-' . $locale . '.mo';
        
        if (file_exists($mo_file)) {
            // Ensure clean slate
            unload_textdomain('ai-assistant');
            
            // Load with maximum priority
            $loaded = load_textdomain('ai-assistant', $mo_file);
            
            if ($loaded && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                static $force_logged = false;
                if (!$force_logged) {
                    // Log only once per session when debug is specifically enabled
                    $force_logged = true;
                }
            }
        }
    }
    
    /**
     * Enhanced plugin textdomain loading with fallback methods
     */
    public function load_plugin_textdomain_enhanced($locale) {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $mo_file = $plugin_dir . 'languages/ai-assistant-' . $locale . '.mo';
        
        if (!file_exists($mo_file)) {
            // Only log missing files once and only if debug logging is enabled
            static $missing_logged = array();
            if (!isset($missing_logged[$locale]) && WP_DEBUG_LOG) {
                AIAssistant::log("Language file missing for " . $locale, true);
                $missing_logged[$locale] = true;
            }
            return false;
        }
        
        // Method 1: Direct textdomain loading
        $loaded = load_textdomain('ai-assistant', $mo_file);
        
        if (!$loaded) {
            // Method 2: Use WordPress plugin textdomain with specific locale
            add_filter('plugin_locale', function($current_locale, $domain) use ($locale) {
                if ($domain === 'ai-assistant') {
                    return $locale;
                }
                return $current_locale;
            }, 999, 2);
            
            $loaded = load_plugin_textdomain('ai-assistant', false, dirname(plugin_basename(dirname(__FILE__))) . '/languages');
        }
        
        if (!$loaded) {
            // Method 3: Override textdomain loading completely
            add_filter('override_load_textdomain', function($override, $domain, $mofile) use ($mo_file, $locale) {
                if ($domain === 'ai-assistant') {
                    unload_textdomain('ai-assistant');
                    $result = load_textdomain('ai-assistant', $mo_file);
                    return $result;
                }
                return $override;
            }, 999, 3);
            
            // Try loading again
            $loaded = load_plugin_textdomain('ai-assistant', false, dirname(plugin_basename(dirname(__FILE__))) . '/languages');
        }
        
        if ($loaded) {
            // Critical: Add gettext filter to force translations
            add_filter('gettext', array($this, 'force_plugin_translations'), 999, 3);
            add_filter('gettext_with_context', array($this, 'force_plugin_translations_with_context'), 999, 4);
            
            // DO NOT add global locale filter - this was causing site-wide language changes
            // Plugin translations will be handled by the plugin_locale filter instead
            
            // Only log success once per session/request and only if debug logging is specifically enabled
            static $success_logged = array();
            if (!isset($success_logged[$locale]) && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                $success_logged[$locale] = true;
            }
        } else {
            // Only log failures once per session/request and only if debug logging is enabled
            static $failed_logged = array();
            if (!isset($failed_logged[$locale]) && WP_DEBUG_LOG) {
                AIAssistant::log("All loading methods failed for: " . $locale, true);
                $failed_logged[$locale] = true;
            }
        }
        
        return $loaded;
    }
    
    /**
     * Force plugin translations through gettext filter
     * This ensures translations display even if WordPress caching interferes
     */
    public function force_plugin_translations($translation, $text, $domain) {
        if ($domain !== 'ai-assistant') {
            return $translation;
        }
        
        // Always try to get translation from our loaded textdomain
        global $l10n;
        
        if (isset($l10n['ai-assistant'])) {
            // Try to get translation from the loaded MO object
            if (method_exists($l10n['ai-assistant'], 'translate')) {
                $mo_translation = $l10n['ai-assistant']->translate($text);
                if ($mo_translation && $mo_translation !== $text) {
                    return $mo_translation;
                }
            }
            
            // Fallback: try to access entries directly
            if (method_exists($l10n['ai-assistant'], 'get_entries')) {
                $entries = $l10n['ai-assistant']->get_entries();
                if (isset($entries[$text])) {
                    return $entries[$text];
                }
            }
        }
        
        // If we still don't have a translation, try direct file loading
        if ($translation === $text) {
            $custom_lang = $this->get_user_language();
            if ($custom_lang) {
                static $direct_translations = array();
                
                if (!isset($direct_translations[$custom_lang])) {
                    $plugin_dir = plugin_dir_path(dirname(__FILE__));
                    $mo_file = $plugin_dir . 'languages/ai-assistant-' . $custom_lang . '.mo';
                    
                    if (file_exists($mo_file)) {
                        // Force reload the textdomain
                        unload_textdomain('ai-assistant');
                        load_textdomain('ai-assistant', $mo_file);
                        $direct_translations[$custom_lang] = true;
                    }
                }
                
                // Try again after force reload
                if (isset($l10n['ai-assistant']) && method_exists($l10n['ai-assistant'], 'translate')) {
                    $mo_translation = $l10n['ai-assistant']->translate($text);
                    if ($mo_translation && $mo_translation !== $text) {
                        return $mo_translation;
                    }
                }
            }
        }
        
        return $translation;
    }
    
    /**
     * Force plugin translations with context
     */
    public function force_plugin_translations_with_context($translation, $text, $context, $domain) {
        if ($domain !== 'ai-assistant') {
            return $translation;
        }
        
        // Try the same approach as the main translation method
        global $l10n;
        
        if (isset($l10n['ai-assistant'])) {
            if (method_exists($l10n['ai-assistant'], 'translate')) {
                $mo_translation = $l10n['ai-assistant']->translate($text, $context);
                if ($mo_translation && $mo_translation !== $text) {
                    return $mo_translation;
                }
            }
        }
        
        return $translation;
    }
    
    /**
     * Custom plugin locale filter
     */
    public function custom_plugin_locale($locale, $domain) {
        if ($domain === 'ai-assistant') {
            $custom_lang = $this->get_user_language();
            if ($custom_lang) {
                return $custom_lang;
            }
        }
        return $locale;
    }
    
    /**
     * Load plugin textdomain with custom language
     */
    public function load_plugin_textdomain_custom($locale) {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $lang_file = $plugin_dir . 'languages/ai-assistant-' . $locale . '.mo';
        
        // Only log once per request to avoid spam
        static $logged_files = array();
        if (!isset($logged_files[$locale])) {
            $logged_files[$locale] = true;
        }
        
        // First try to load the .mo file directly
        if (file_exists($lang_file)) {
            $loaded = load_textdomain('ai-assistant', $lang_file);
            if (!isset($logged_files[$locale . '_result'])) {
                $logged_files[$locale . '_result'] = true;
            }
            if ($loaded) {
                return true;
            }
        }
        
        // Fallback: try using WordPress's load_plugin_textdomain with custom path
        $languages_path = dirname(plugin_basename(dirname(__FILE__))) . '/languages/';
        
        $loaded = load_plugin_textdomain(
            'ai-assistant',
            false,
            $languages_path
        );
        
        return $loaded;
    }
    
    /**
     * Get the path to a .po file for a specific language
     */
    public function get_po_file_path($language) {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        return $plugin_dir . 'languages/ai-assistant-' . $language . '.po';
    }
    
    /**
     * Get the path to a .mo file for a specific language
     */
    private function get_mo_file_path($language) {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        return $plugin_dir . 'languages/ai-assistant-' . $language . '.mo';
    }
    
    /**
     * Load translations from a .po file
     */
    private function load_po_file_translations($language) {
        $po_file = $this->get_po_file_path($language);
        $translations = array();
        
        if (!file_exists($po_file)) {
            // If .po file doesn't exist, load from .pot template
            $pot_file = plugin_dir_path(dirname(__FILE__)) . 'languages/ai-assistant.pot';
            if (file_exists($pot_file)) {
                return $this->parse_po_file($pot_file);
            }
            return array();
        }
        
        return $this->parse_po_file($po_file);
    }
    
    /**
     * Parse a .po/.pot file and extract translation strings
     */
    private function parse_po_file($file_path) {
        $content = file_get_contents($file_path);
        if (!$content) {
            return array();
        }
        
        $translations = array();
        $lines = explode("\n", $content);
        $current_entry = array();
        $in_multiline = false;
        $current_field = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and headers
            if (empty($line) || $line[0] === '#') {
                // If we have a complete entry, save it
                if (!empty($current_entry['msgid'])) {
                    $translations[] = array(
                        'msgid' => $current_entry['msgid'],
                        'msgstr' => isset($current_entry['msgstr']) ? $current_entry['msgstr'] : '',
                        'context' => isset($current_entry['msgctxt']) ? $current_entry['msgctxt'] : ''
                    );
                }
                $current_entry = array();
                $in_multiline = false;
                continue;
            }
            
            // Handle msgctxt (context)
            if (preg_match('/^msgctxt\s+"(.*)"\s*$/', $line, $matches)) {
                $current_entry['msgctxt'] = $this->unescape_po_string($matches[1]);
                $current_field = 'msgctxt';
                $in_multiline = false;
            }
            // Handle msgid
            elseif (preg_match('/^msgid\s+"(.*)"\s*$/', $line, $matches)) {
                $current_entry['msgid'] = $this->unescape_po_string($matches[1]);
                $current_field = 'msgid';
                $in_multiline = false;
            }
            // Handle msgstr
            elseif (preg_match('/^msgstr\s+"(.*)"\s*$/', $line, $matches)) {
                $current_entry['msgstr'] = $this->unescape_po_string($matches[1]);
                $current_field = 'msgstr';
                $in_multiline = false;
            }
            // Handle multiline strings
            elseif (preg_match('/^"(.*)"\s*$/', $line, $matches) && !empty($current_field)) {
                $current_entry[$current_field] .= $this->unescape_po_string($matches[1]);
            }
        }
        
        // Don't forget the last entry
        if (!empty($current_entry['msgid'])) {
            $translations[] = array(
                'msgid' => $current_entry['msgid'],
                'msgstr' => isset($current_entry['msgstr']) ? $current_entry['msgstr'] : '',
                'context' => isset($current_entry['msgctxt']) ? $current_entry['msgctxt'] : ''
            );
        }
        
        // Filter out empty msgids (headers)
        return array_filter($translations, function($t) {
            return !empty($t['msgid']);
        });
    }
    
    /**
     * Unescape strings from .po files
     */
    private function unescape_po_string($string) {
        // Unescape in reverse order of escaping to prevent issues
        $string = str_replace('\\\\', "\x00TEMP_BACKSLASH\x00", $string); // Temporarily replace double backslashes
        $string = str_replace(array('\\"', '\\n', '\\t'), array('"', "\n", "\t"), $string);
        $string = str_replace("\x00TEMP_BACKSLASH\x00", '\\', $string); // Restore single backslashes
        return $string;
    }
    
    /**
     * Escape strings for .po files
     */
    private function escape_po_string($string) {
        // Escape in proper order to prevent double-escaping
        $string = str_replace('\\', '\\\\', $string);     // Escape backslashes first
        $string = str_replace('"', '\\"', $string);       // Then escape quotes
        $string = str_replace(array("\n", "\t"), array('\\n', '\\t'), $string); // Finally newlines and tabs
        return $string;
    }
    
    /**
     * Save translations to .po file
     */
    private function save_po_translations() {
        if (!isset($_POST['po_translations_nonce']) || !wp_verify_nonce($_POST['po_translations_nonce'], 'ai_assistant_po_translations')) {
            add_settings_error('translation_management', 'nonce_error', __('Security check failed.', 'ai-assistant'), 'error');
            return;
        }
        
        $language = sanitize_text_field($_POST['edit_language']);
        $translations = isset($_POST['translations']) ? $_POST['translations'] : array();
        
        // Important: Unescape the incoming POST data to prevent double-escaping
        // The data comes from HTML forms already escaped, but we need clean data for proper .po formatting
        foreach ($translations as &$translation) {
            if (isset($translation['msgid'])) {
                $original = $translation['msgid'];
                $translation['msgid'] = $this->unescape_po_string($translation['msgid']);
                
                // Debug logging for escaping issues
                if (defined('WP_DEBUG') && WP_DEBUG && $original !== $translation['msgid']) {
                    error_log("AI Assistant: Unescaped msgid - Original: {$original} | Clean: {$translation['msgid']}");
                }
            }
            if (isset($translation['msgstr'])) {
                $original = $translation['msgstr'];
                $translation['msgstr'] = $this->unescape_po_string($translation['msgstr']);
                
                // Debug logging for escaping issues
                if (defined('WP_DEBUG') && WP_DEBUG && $original !== $translation['msgstr']) {
                    error_log("AI Assistant: Unescaped msgstr - Original: {$original} | Clean: {$translation['msgstr']}");
                }
            }
            if (isset($translation['context'])) {
                $translation['context'] = $this->unescape_po_string($translation['context']);
            }
        }
        unset($translation); // Clear reference
        
        $po_file = $this->get_po_file_path($language);
        $mo_file = $this->get_mo_file_path($language);
        
        // Create directory if it doesn't exist
        $dir = dirname($po_file);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        // Generate .po file content
        $po_content = $this->generate_po_file_content($language, $translations);
        
        // Save .po file
        if (file_put_contents($po_file, $po_content) !== false) {
            // Try to compile to .mo file
            $mo_compiled = $this->compile_mo_file($po_file, $mo_file);
            
            if ($mo_compiled) {
                add_settings_error('translation_management', 'save_success', 
                    sprintf(__('Translations saved successfully to %s and compiled to %s. Changes are now active in the plugin interface.', 'ai-assistant'), 
                    basename($po_file), basename($mo_file)), 'updated');
            } else {
                add_settings_error('translation_management', 'save_success', 
                    sprintf(__('Translations saved successfully to %s. Warning: Failed to compile .mo file - translations may not appear in plugin interface.', 'ai-assistant'), 
                    basename($po_file)), 'updated');
            }
        } else {
            add_settings_error('translation_management', 'save_error', 
                __('Failed to save translations. Please check file permissions.', 'ai-assistant'), 'error');
        }
    }
    
    /**
     * Generate .po file content
     */
    private function generate_po_file_content($language, $translations) {
        $supported_languages = $this->get_supported_languages();
        $language_name = isset($supported_languages[$language]) ? $supported_languages[$language] : $language;
        
        $content = '# AI Assistant WordPress Plugin Translation' . "\n";
        $content .= '# Language: ' . $language_name . ' (' . $language . ')' . "\n";
        $content .= '# Generated: ' . date('Y-m-d H:i:s O') . "\n";
        $content .= 'msgid ""' . "\n";
        $content .= 'msgstr ""' . "\n";
        $content .= '"Project-Id-Version: AI Assistant\\n"' . "\n";
        $content .= '"Report-Msgid-Bugs-To: \\n"' . "\n";
        $content .= '"POT-Creation-Date: ' . date('Y-m-d H:i:s O') . '\\n"' . "\n";
        $content .= '"PO-Revision-Date: ' . date('Y-m-d H:i:s O') . '\\n"' . "\n";
        $content .= '"Language: ' . $language . '\\n"' . "\n";
        $content .= '"MIME-Version: 1.0\\n"' . "\n";
        $content .= '"Content-Type: text/plain; charset=UTF-8\\n"' . "\n";
        $content .= '"Content-Transfer-Encoding: 8bit\\n"' . "\n";
        $content .= '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"' . "\n";
        $content .= '"X-Generator: AI Assistant Translation Manager\\n"' . "\n";
        $content .= "\n";
        
        foreach ($translations as $translation) {
            if (empty($translation['msgid'])) {
                continue;
            }
            
            // Add context if present
            if (!empty($translation['context'])) {
                $content .= 'msgctxt "' . $this->escape_po_string($translation['context']) . '"' . "\n";
            }
            
            // Add msgid
            $content .= 'msgid "' . $this->escape_po_string($translation['msgid']) . '"' . "\n";
            
            // Add msgstr
            $msgstr = isset($translation['msgstr']) ? $translation['msgstr'] : '';
            $content .= 'msgstr "' . $this->escape_po_string($msgstr) . '"' . "\n";
            $content .= "\n";
        }
        
        return $content;
    }
    
    /**
     * Compile .po file to .mo file (binary format)
     */
    private function compile_mo_file($po_file, $mo_file) {
        if (!file_exists($po_file)) {
            AIAssistant::log("Cannot compile .mo file - .po file does not exist: {$po_file}", true);
            return false;
        }
        
        // Try using msgfmt if available (most reliable method)
        if (function_exists('exec')) {
            $output = array();
            $return_var = 0;
            exec("msgfmt -o " . escapeshellarg($mo_file) . " " . escapeshellarg($po_file) . " 2>&1", $output, $return_var);
            
            if ($return_var === 0 && file_exists($mo_file)) {
                AIAssistant::log("Successfully compiled .mo file using msgfmt: {$mo_file}", true);
                return true;
            } else {
                AIAssistant::log("msgfmt compilation failed for {$po_file}. Output: " . implode("\n", $output), true);
            }
        }
        
        // Fallback: basic .mo file generation (simplified)
        $result = $this->simple_mo_compile($po_file, $mo_file);
        if ($result) {
            AIAssistant::log("Successfully compiled .mo file using fallback method: {$mo_file}", true);
        } else {
            AIAssistant::log("Failed to compile .mo file using fallback method: {$mo_file}", true);
        }
        return $result;
    }
    
    /**
     * Simple .mo file compilation (fallback method)
     */
    private function simple_mo_compile($po_file, $mo_file) {
        $translations = $this->parse_po_file($po_file);
        if (empty($translations)) {
            return false;
        }
        
        // This is a very basic .mo file generator
        // For production use, consider using a proper library like php-mo or gettext tools
        $entries = array();
        foreach ($translations as $translation) {
            if (!empty($translation['msgstr'])) {
                $key = $translation['msgid'];
                if (!empty($translation['context'])) {
                    $key = $translation['context'] . "\004" . $key;
                }
                $entries[$key] = $translation['msgstr'];
            }
        }
        
        if (empty($entries)) {
            return false;
        }
        
        // Generate binary .mo file content
        $keys = array_keys($entries);
        $values = array_values($entries);
        
        $key_offsets = array();
        $value_offsets = array();
        $klen = array();
        $vlen = array();
        
        $key_table = '';
        $value_table = '';
        
        foreach ($keys as $key) {
            $klen[] = strlen($key);
            $key_table .= $key . "\0";
        }
        
        foreach ($values as $value) {
            $vlen[] = strlen($value);
            $value_table .= $value . "\0";
        }
        
        $count = count($entries);
        $key_start = 7 * 4 + 16 * $count;
        $value_start = $key_start + strlen($key_table);
        
        $key_offset = 0;
        $value_offset = 0;
        
        for ($i = 0; $i < $count; $i++) {
            $key_offsets[] = $key_offset;
            $key_offset += $klen[$i] + 1;
            $value_offsets[] = $value_offset;
            $value_offset += $vlen[$i] + 1;
        }
        
        // Build the .mo file
        $mo = pack('Iiiiiii', 0x950412de, 0, $count, 7 * 4, 7 * 4 + $count * 8, 0, $key_start);
        
        for ($i = 0; $i < $count; $i++) {
            $mo .= pack('ii', $klen[$i], $key_start + $key_offsets[$i]);
        }
        
        for ($i = 0; $i < $count; $i++) {
            $mo .= pack('ii', $vlen[$i], $value_start + $value_offsets[$i]);
        }
        
        $mo .= $key_table . $value_table;
        
        return file_put_contents($mo_file, $mo) !== false;
    }
    
    /**
     * Compile all .po files to .mo files
     */
    public function compile_all_mo_files() {
        $languages_dir = plugin_dir_path(dirname(__FILE__)) . 'languages/';
        $po_files = glob($languages_dir . 'ai-assistant-*.po');
        
        $compiled_count = 0;
        foreach ($po_files as $po_file) {
            $mo_file = str_replace('.po', '.mo', $po_file);
            if ($this->compile_mo_file($po_file, $mo_file)) {
                $compiled_count++;
            }
        }
        
        return $compiled_count;
    }
    
    /**
     * Initialize .mo files if they don't exist
     */
    public function init_mo_files() {
        static $initialized = false;
        if ($initialized) {
            return;
        }
        
        $languages_dir = plugin_dir_path(dirname(__FILE__)) . 'languages/';
        $po_files = glob($languages_dir . 'ai-assistant-*.po');
        
        foreach ($po_files as $po_file) {
            $mo_file = str_replace('.po', '.mo', $po_file);
            if (!file_exists($mo_file)) {
                $this->compile_mo_file($po_file, $mo_file);
            }
        }
        
        $initialized = true;
    }
    
    /**
     * Debug language loading (for testing purposes)
     */
    public function debug_language_loading() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo "<div class='notice notice-info'>";
        echo "<h3>🔍 Language Loading Debug Test</h3>";
        
        $custom_lang = $this->get_user_language();
        $current_locale = get_locale();
        
        echo "<p><strong>WordPress Locale:</strong> " . $current_locale . "</p>";
        echo "<p><strong>Custom Language Setting:</strong> " . ($custom_lang ?: 'NOT SET') . "</p>";
        echo "<p><strong>Textdomain Loaded:</strong> " . (is_textdomain_loaded('ai-assistant') ? 'YES' : 'NO') . "</p>";
        
        // Test loading the language manually
        if ($custom_lang && $custom_lang !== $current_locale) {
            echo "<p><strong>Testing manual language load...</strong></p>";
            
            unload_textdomain('ai-assistant');
            $loaded = $this->load_plugin_textdomain_custom($custom_lang);
            
            echo "<p><strong>Manual load result:</strong> " . ($loaded ? 'SUCCESS' : 'FAILED') . "</p>";
            echo "<p><strong>Test translation:</strong> " . __('AI Assistant Dashboard', 'ai-assistant') . "</p>";
        }
        
        // List available .mo files
        $languages_dir = plugin_dir_path(dirname(__FILE__)) . 'languages/';
        $mo_files = glob($languages_dir . 'ai-assistant-*.mo');
        
        echo "<p><strong>Available .mo files:</strong></p>";
        echo "<ul>";
        foreach ($mo_files as $mo_file) {
            $size = number_format(filesize($mo_file));
            echo "<li>" . basename($mo_file) . " ({$size} bytes)</li>";
        }
        echo "</ul>";
        
        echo "</div>";
    }
    
    /**
     * Get translation statistics for a language
     */
    private function get_translation_stats($language_code) {
        $po_file = $this->get_po_file_path($language_code);
        if (!file_exists($po_file)) {
            return array('total' => 0, 'translated' => 0);
        }
        
        $translations = $this->load_po_file_translations($language_code);
        $total = count($translations);
        $translated = count(array_filter($translations, function($t) { 
            return !empty($t['msgstr']); 
        }));
        
        return array('total' => $total, 'translated' => $translated);
    }
    
    /**
     * Render translation table for current language
     */
    private function render_translation_table($language_code, $translations) {
        if (empty($translations)) {
            echo '<p>' . __('No translations found for this language.', 'ai-assistant') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped translation-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width: 40%;">' . __('English Text (msgid)', 'ai-assistant') . '</th>';
        echo '<th style="width: 40%;">' . __('Translation (msgstr)', 'ai-assistant') . '</th>';
        echo '<th style="width: 10%;">' . __('Status', 'ai-assistant') . '</th>';
        echo '<th style="width: 10%;">' . __('Actions', 'ai-assistant') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $index = 0;
        foreach ($translations as $translation) {
            $is_translated = !empty($translation['msgstr']);
            $row_class = $is_translated ? 'translated' : 'untranslated';
            
            echo '<tr class="translation-row ' . esc_attr($row_class) . '" data-index="' . $index . '">';
            
            // English text (msgid)
            echo '<td class="msgid-cell">';
            echo '<div class="msgid-text">' . esc_html($translation['msgid']) . '</div>';
            if (!empty($translation['context'])) {
                echo '<div class="context-text">Context: ' . esc_html($translation['context']) . '</div>';
            }
            echo '</td>';
            
            // Translation input (msgstr)
            echo '<td class="msgstr-cell">';
            echo '<textarea name="translations[' . $index . '][msgstr]" class="translation-input" rows="2" placeholder="' . __('Enter translation...', 'ai-assistant') . '">' . esc_textarea($translation['msgstr']) . '</textarea>';
            echo '<input type="hidden" name="translations[' . $index . '][msgid]" value="' . esc_attr($translation['msgid']) . '" />';
            echo '<input type="hidden" name="translations[' . $index . '][context]" value="' . esc_attr($translation['context']) . '" />';
            echo '</td>';
            
            // Status
            echo '<td class="status-cell">';
            echo '<span class="status-badge ' . ($is_translated ? 'status-translated' : 'status-untranslated') . '">';
            echo $is_translated ? __('Translated', 'ai-assistant') : __('Missing', 'ai-assistant');
            echo '</span>';
            echo '</td>';
            
            // Actions
            echo '<td class="actions-cell">';
            if (!$is_translated) {
                echo '<button type="button" class="button button-small auto-translate-single" data-index="' . $index . '" data-msgid="' . esc_attr($translation['msgid']) . '">';
                echo __('AI Translate', 'ai-assistant');
                echo '</button>';
            }
            echo '</td>';
            
            echo '</tr>';
            $index++;
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    /**
     * Display multilingual status on dashboard
     */
    private function display_multilingual_status() {
        $available_languages = $this->get_available_translation_languages();
        $plugin_dir = dirname(dirname(__FILE__));
        $languages_dir = $plugin_dir . '/languages';
        
        echo '<div class="multilingual-status-grid">';
        
        foreach ($available_languages as $lang_code => $lang_name) {
            $po_file = $languages_dir . "/ai-assistant-{$lang_code}.po";
            $mo_file = $languages_dir . "/ai-assistant-{$lang_code}.mo";
            
            $po_exists = file_exists($po_file);
            $mo_exists = file_exists($mo_file);
            $po_size = $po_exists ? filesize($po_file) : 0;
            $mo_size = $mo_exists ? filesize($mo_file) : 0;
            
            // Count translations
            $translation_count = 0;
            if ($po_exists) {
                $content = file_get_contents($po_file);
                $translation_count = $this->count_translations_in_po($content);
            }
            
            // Determine status
            $status_class = 'status-none';
            $status_text = __('Not Available', 'ai-assistant');
            
            if ($mo_exists && $translation_count > 300) {
                $status_class = 'status-complete';
                $status_text = __('Complete', 'ai-assistant');
            } elseif ($po_exists) {
                $status_class = 'status-partial';
                $status_text = __('In Progress', 'ai-assistant');
            }
            
            echo '<div class="language-status-card ' . $status_class . '">';
            echo '<h4>' . esc_html($lang_name) . '</h4>';
            echo '<div class="status-indicator">';
            echo '<span class="status-dot"></span>';
            echo '<span class="status-label">' . $status_text . '</span>';
            echo '</div>';
            
            if ($translation_count > 0) {
                echo '<div class="translation-stats">';
                echo '<div class="stat-row">';
                echo '<span class="stat-label">' . __('Strings:', 'ai-assistant') . '</span>';
                echo '<span class="stat-value">' . number_format($translation_count) . '</span>';
                echo '</div>';
                
                if ($po_exists) {
                    echo '<div class="stat-row">';
                    echo '<span class="stat-label">' . __('.po Size:', 'ai-assistant') . '</span>';
                    echo '<span class="stat-value">' . $this->format_file_size($po_size) . '</span>';
                    echo '</div>';
                }
                
                if ($mo_exists) {
                    echo '<div class="stat-row">';
                    echo '<span class="stat-label">' . __('.mo Size:', 'ai-assistant') . '</span>';
                    echo '<span class="stat-value">' . $this->format_file_size($mo_size) . '</span>';
                    echo '</div>';
                }
                echo '</div>';
            }
            
            echo '<div class="action-buttons">';
            if ($lang_code !== 'en_US') {
                echo '<a href="' . admin_url('admin.php?page=ai-assistant-translations&language=' . $lang_code) . '" class="button button-small">';
                echo __('Manage', 'ai-assistant');
                echo '</a>';
            }
            echo '</div>';
            
            echo '</div>';
        }
        
        echo '</div>';
        
        // Summary stats
        $total_languages = count($available_languages);
        $active_languages = 0;
        
        foreach ($available_languages as $lang_code => $lang_name) {
            $mo_file = $languages_dir . "/ai-assistant-{$lang_code}.mo";
            if (file_exists($mo_file) && filesize($mo_file) > 100) {
                $active_languages++;
            }
        }
        
        echo '<div class="multilingual-summary">';
        echo '<h4>' . __('Summary', 'ai-assistant') . '</h4>';
        echo '<p>';
        echo sprintf(
            __('%d of %d languages are active with compiled translations.', 'ai-assistant'),
            $active_languages,
            $total_languages
        );
        echo '</p>';
        
        if ($active_languages < $total_languages) {
            echo '<p class="description">';
            echo __('Complete missing translations and compile .mo files to activate more languages.', 'ai-assistant');
            echo '</p>';
        }
        echo '</div>';
    }
    
    /**
     * Count translations in .po file content
     */
    private function count_translations_in_po($content) {
        $count = 0;
        $lines = explode("\n", $content);
        $in_translation = false;
        $has_msgid = false;
        $has_msgstr = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'msgid ') === 0) {
                // Save previous count
                if ($has_msgid && $has_msgstr) {
                    $count++;
                }
                
                $has_msgid = true;
                $has_msgstr = false;
                $msgid = substr($line, 7, -1);
                
                // Skip empty msgid (header)
                if (empty($msgid)) {
                    $has_msgid = false;
                }
            } elseif (strpos($line, 'msgstr ') === 0) {
                $msgstr = substr($line, 8, -1);
                $has_msgstr = !empty($msgstr);
            }
        }
        
        // Count last translation
        if ($has_msgid && $has_msgstr) {
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Format file size for display
     */
    private function format_file_size($bytes) {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        } else {
            return round($bytes / 1048576, 1) . ' MB';
        }
    }

    /**
     * Get display name for a language code
     *
     * @param string $locale Language locale code
     * @return string Display name
     */
    private function get_language_display_name($locale) {
        // Use WordPress built-in language names when available
        $wp_locale_obj = new WP_Locale();
        if (method_exists($wp_locale_obj, 'get_language_names')) {
            $wp_languages = $wp_locale_obj->get_language_names();
            if (isset($wp_languages[$locale])) {
                return $wp_languages[$locale];
            }
        }

        // Fallback to common language mappings
        $language_names = array(
            'en_US' => 'English (United States)',
            'tr_TR' => 'Türkçe (Turkish)',
            'fa_IR' => 'فارسی (Persian/Farsi)',
            'nl_NL' => 'Nederlands (Dutch)',
            'da_DK' => 'Dansk (Danish)',
            'fr_FR' => 'Français (French)',
            'az_AZ' => 'Azərbaycan dili (Azerbaijani)',
            'uz_UZ' => 'O\'zbek tili (Uzbek)',
            'ar' => 'العربية (Arabic)',
            'ky_KG' => 'Кыргыз тили (Kyrgyz)',
            'ru_RU' => 'Русский (Russian)',
            'pt_PT' => 'Português (Portuguese)',
            'es_ES' => 'Español (Spanish)',
            'de_DE' => 'Deutsch (German)',
            'zh_CN' => '简体中文 (Chinese Simplified)',
            'ug_CN' => 'ئۇيغۇرچە (Uyghur)',
            'ur' => 'اردو (Urdu)',
            'fi' => 'Suomi (Finnish)',
            'tk' => 'Türkmen dili (Turkmen)'
        );

        return isset($language_names[$locale]) ? $language_names[$locale] : $locale;
    }
    
    /**
     * Get current editing language from various sources
     */
    private function get_current_editing_language() {
        // Check URL parameter first
        if (isset($_GET['edit_lang']) && !empty($_GET['edit_lang'])) {
            return sanitize_text_field($_GET['edit_lang']);
        }
        
        // Check POST data
        if (isset($_POST['edit_language']) && !empty($_POST['edit_language'])) {
            return sanitize_text_field($_POST['edit_language']);
        }
        
        // Check if we're on a translation page
        if (isset($_GET['page']) && $_GET['page'] === 'ai-assistant-translation-editor' && isset($_GET['language'])) {
            return sanitize_text_field($_GET['language']);
        }
        
        // Get from current translation settings
        $translation_settings = get_option('ai_assistant_translation_settings', array());
        if (!empty($translation_settings['selected_language'])) {
            return $translation_settings['selected_language'];
        }
        
        // Fallback to site locale
        $locale = get_locale();
        return !empty($locale) ? $locale : 'en_US';
    }
}
