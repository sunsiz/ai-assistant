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
        
        // AJAX handler for auto-translating empty strings (delegated from main plugin)
        add_action('wp_ajax_ai_assistant_auto_translate', array($this, 'ajax_auto_translate'));
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
                $old_language = get_option('ai_assistant_admin_language', get_locale());
                
                // Save the new language
                update_option('ai_assistant_admin_language', $new_language);
                
                // If language changed, reload the page to apply new language
                if ($new_language !== $old_language) {
                    // Force reload textdomain
                    unload_textdomain('ai-assistant');
                    $this->load_plugin_textdomain_custom($new_language);
                    
                    // JavaScript to reload the page
                    echo '<script>setTimeout(function() { window.location.reload(); }, 500);</script>';
                }
                
                add_settings_error('ai_assistant_settings', 'language_updated', __('Language settings saved successfully.', 'ai-assistant'), 'updated');
            }
        }
        
        $current_admin_language = get_option('ai_assistant_admin_language', get_locale());
        ?>
        <div class="wrap">
            <h1><?php _e('AI Assistant Settings', 'ai-assistant'); ?></h1>
            
            <?php settings_errors(); ?>
            
            <!-- Quick System Status -->
            <div class="notice notice-info" style="padding: 15px; margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="margin: 0 0 10px 0;">⚙️ <?php _e('System Status', 'ai-assistant'); ?></h4>
                        <p style="margin: 0; color: #666;">
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
                                    $supported_languages = array(
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
                                    
                                    foreach ($supported_languages as $code => $name) {
                                        $selected = selected($current_admin_language, $code, false);
                                        echo "<option value='{$code}' {$selected}>{$name}</option>";
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php _e('Select the language for the AI Assistant admin interface. This affects the plugin interface language.', 'ai-assistant'); ?></p>
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
                error_log('AI Assistant: Successfully created suggestions table');
            } else {
                error_log('AI Assistant: Failed to create suggestions table');
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
            
            <div class="tab-content" style="margin-top: 20px;">
                <?php if ($current_tab === 'translations'): ?>
                    <?php $this->render_translations_history($translations_table, $translations_table_exists); ?>
                <?php elseif ($current_tab === 'suggestions'): ?>
                    <?php $this->render_suggestions_history($suggestions_table, $suggestions_table_exists); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .tab-content {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-top: none;
            padding: 20px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-completed { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .suggestion-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .input-text-preview {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-style: italic;
            color: #666;
        }
        </style>
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
                                    <span style="color: #999;"><?php _e('Global', 'ai-assistant'); ?></span>
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
            
            <style>
            .ai-assistant-translation-details {
                max-width: 1200px;
            }
            
            .translation-meta-box, .translation-content-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                margin: 20px 0;
                padding: 20px;
            }
            
            .translation-comparison {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 15px;
            }
            
            .original-content h3, .translated-content h3 {
                margin: 0 0 10px 0;
                padding: 10px;
                background: #f1f1f1;
                border-left: 4px solid #0073aa;
            }
            
            .translated-content h3 {
                border-left-color: #00a32a;
            }
            
            .content-box {
                border: 1px solid #ddd;
                padding: 15px;
                background: #fafafa;
                min-height: 200px;
                max-height: 500px;
                overflow-y: auto;
                line-height: 1.6;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            
            .content-stats {
                font-size: 12px;
                color: #666;
                font-weight: normal;
            }
            
            .language-badge {
                background: #0073aa;
                color: white;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
            }
            
            .status-badge {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: uppercase;
            }
            
            .status-completed {
                background: #00a32a;
                color: white;
            }
            
            .status-failed {
                background: #d63638;
                color: white;
            }
            
            .status-pending {
                background: #dba617;
                color: white;
            }
            
            .translation-actions {
                margin: 20px 0;
                padding: 15px;
                background: #f9f9f9;
                border-left: 4px solid #0073aa;
            }
            
            .translation-actions .button {
                margin-right: 10px;
            }
            
            @media (max-width: 768px) {
                .translation-comparison {
                    grid-template-columns: 1fr;
                }
            }
            </style>
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
                                    <span style="color: #999;"><?php _e('Unknown User', 'ai-assistant'); ?></span>
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
                            <td><span style="color: #666;"><?php _e('Global suggestion (not associated with a specific post)', 'ai-assistant'); ?></span></td>
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
            
            <style>
            .ai-assistant-suggestion-details {
                max-width: 1200px;
            }
            
            .suggestion-meta-box, .suggestion-content-box {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                margin: 20px 0;
                padding: 20px;
            }
            
            .suggestion-comparison {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 15px;
            }
            
            .input-content h3, .generated-content h3 {
                margin: 0 0 10px 0;
                padding: 10px;
                background: #f1f1f1;
                border-left: 4px solid #0073aa;
            }
            
            .generated-content h3 {
                border-left-color: #00a32a;
            }
            
            .content-box {
                border: 1px solid #ddd;
                padding: 15px;
                background: #fafafa;
                min-height: 150px;
                max-height: 400px;
                overflow-y: auto;
                line-height: 1.6;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            
            .content-stats {
                font-size: 12px;
                color: #666;
                font-weight: normal;
            }
            
            .suggestion-type-badge {
                background: #0073aa;
                color: white;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: bold;
                text-transform: capitalize;
            }
            
            .suggestion-type-autocomplete {
                background: #0073aa;
            }
            
            .suggestion-type-content-generation,
            .suggestion-type-suggestions,
            .suggestion-type-blog-post,
            .suggestion-type-seo-content {
                background: #00a32a;
            }
            
            .suggestion-type-image-prompt,
            .suggestion-type-image-generation {
                background: #d63638;
            }
            
            .suggestion-type-featured-image-set {
                background: #dba617;
            }
            
            .suggestion-actions {
                margin: 20px 0;
                padding: 15px;
                background: #f9f9f9;
                border-left: 4px solid #0073aa;
            }
            
            .suggestion-actions .button {
                margin-right: 10px;
            }
            
            @media (max-width: 768px) {
                .suggestion-comparison {
                    grid-template-columns: 1fr;
                }
            }
            </style>
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
            'es' => 'Español',
            'it' => 'Italiano',
            'pt' => 'Português',
            'ru' => 'Русский',
            'ja' => '日本語',
            'ko' => '한국어',
            'hi' => 'हिन्दी',
            'ur' => 'اردو',
            'fa' => 'فارسی',
            'ug' => 'ئۇيغۇرچە',
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
        
        $current_language = isset($_GET['edit_lang']) ? sanitize_text_field($_GET['edit_lang']) : get_option('ai_assistant_admin_language', 'en_US');
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
                    </div>
                    
                    <table class="widefat translation-table">
                        <thead>
                            <tr>
                                <th class="col-msgid"><?php _e('English Text (msgid)', 'ai-assistant'); ?></th>
                                <th class="col-msgstr"><?php _e('Translation (msgstr)', 'ai-assistant'); ?></th>
                                <th class="col-status"><?php _e('Status', 'ai-assistant'); ?></th>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="translation-actions">
                        <?php submit_button(__('Save Translations to .po File', 'ai-assistant'), 'primary', 'save_translations'); ?>
                        <button type="button" class="button" id="export-po">
                            <?php _e('Download .po File', 'ai-assistant'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Manual translation approach: Fill in the empty translation fields above, then save to .po file. This ensures accuracy for Islamic/religious content.', 'ai-assistant'); ?>
                        </p>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
        .ai-assistant-translation-management {
            max-width: 1200px;
        }
        
        .language-selector-section {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .language-status {
            margin-left: 15px;
            font-weight: 500;
        }
        
        .translation-stats {
            background: #fff;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            margin-right: 20px;
        }
        
        .translation-search {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .translation-table {
            margin-bottom: 20px;
        }
        
        .translation-table th, 
        .translation-table td {
            padding: 12px;
            vertical-align: top;
        }
        
        .translation-input {
            width: 100%;
            min-height: 40px;
            resize: vertical;
        }
        
        .msgid-text {
            font-size: 13px;
            line-height: 1.4;
        }
        
        .context-text {
            color: #666;
            font-style: italic;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-translated { 
            background: #46b450; 
            color: white; 
        }
        
        .status-untranslated { 
            background: #ffb900; 
            color: white; 
        }
        
        .translation-actions {
            border-top: 1px solid #ddd;
            padding-top: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .status-ok { background: #46b450; }
        .status-warning { background: #ffb900; }
        .status-error { background: #dc3232; }
        
        .translation-row.hidden {
            display: none;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Language selector change
            $('#language-selector').on('change', function() {
                const selectedLang = $(this).val();
                const url = new URL(window.location.href);
                url.searchParams.set('edit_lang', selectedLang);
                window.location.href = url.toString();
            });
            
            // Search functionality
            $('#translation-search').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                filterTranslations();
            });
            
            $('#show-untranslated-only').on('change', function() {
                filterTranslations();
            });
            
            function filterTranslations() {
                const searchTerm = $('#translation-search').val().toLowerCase();
                const showUntranslatedOnly = $('#show-untranslated-only').is(':checked');
                
                $('.translation-row').each(function() {
                    const $row = $(this);
                    const msgid = $row.data('msgid');
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
            }
            
            // Export .po file
            $('#export-po').on('click', function() {
                const lang = '<?php echo esc_js($current_language); ?>';
                const url = ajaxurl + '?action=ai_assistant_export_po&lang=' + lang + '&nonce=<?php echo wp_create_nonce('export_po'); ?>';
                window.open(url, '_blank');
            });
            
            // Auto-resize textareas
            $('.translation-input').on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Compile all .mo files
            $('#compile-all-mo').on('click', function() {
                const $button = $(this);
                
                if (!confirm('<?php _e('This will compile all .po files to .mo files. Required for translations to work in WordPress. Continue?', 'ai-assistant'); ?>')) {
                    return;
                }
                
                $button.prop('disabled', true).text('<?php _e('Compiling...', 'ai-assistant'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ai_assistant_handle_compile_mo_files',
                        nonce: '<?php echo wp_create_nonce('ai_assistant_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#compile-status').text(response.data.message).css('color', 'green');
                        } else {
                            $('#compile-status').text('<?php _e('Compilation failed: ', 'ai-assistant'); ?>' + (response.data || '<?php _e('Unknown error', 'ai-assistant'); ?>')).css('color', 'red');
                        }
                    },
                    error: function() {
                        $('#compile-status').text('<?php _e('Compilation request failed. Please try again.', 'ai-assistant'); ?>').css('color', 'red');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php _e('Compile All .mo Files', 'ai-assistant'); ?>');
                    }
                });
            });
        });
        </script>
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
        
        $custom_lang = get_option('ai_assistant_admin_language');
        $current_locale = get_locale();
        
        echo "<div style='background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 4px;'>";
        echo "<h4>Basic Information</h4>";
        echo "<ul style='margin-left: 20px;'>";
        echo "<li><strong>WordPress Locale:</strong> " . $current_locale . "</li>";
        echo "<li><strong>Custom Language Setting:</strong> " . ($custom_lang ?: 'NOT SET') . "</li>";
        echo "<li><strong>Plugin Textdomain Loaded:</strong> " . (is_textdomain_loaded('ai-assistant') ? 'YES' : 'NO') . "</li>";
        echo "</ul>";
        echo "</div>";
        
        // Test translations
        echo "<div style='background: #f0f8ff; padding: 15px; margin: 10px 0; border-radius: 4px;'>";
        echo "<h4>Translation Tests</h4>";
        echo "<ul style='margin-left: 20px;'>";
        echo "<li><strong>'AI Assistant':</strong> " . __('AI Assistant', 'ai-assistant') . "</li>";
        echo "<li><strong>'Settings':</strong> " . __('Settings', 'ai-assistant') . "</li>";
        echo "<li><strong>'Translate':</strong> " . __('Translate', 'ai-assistant') . "</li>";
        echo "<li><strong>'Translation Management':</strong> " . __('Translation Management', 'ai-assistant') . "</li>";
        echo "</ul>";
        echo "</div>";
        
        // File information
        $languages_dir = plugin_dir_path(dirname(__FILE__)) . 'languages/';
        echo "<div style='background: #fff8dc; padding: 15px; margin: 10px 0; border-radius: 4px;'>";
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
        echo "<ul style='margin-left: 20px; max-height: 150px; overflow-y: auto;'>";
        foreach ($mo_files as $mo_file) {
            $size = $this->format_file_size(filesize($mo_file));
            echo "<li>" . basename($mo_file) . " ({$size})</li>";
        }
        echo "</ul>";
        echo "</div>";
        
        // Manual test
        if ($custom_lang && $custom_lang !== $current_locale) {
            echo "<div style='background: #e8f5e8; padding: 15px; margin: 10px 0; border-radius: 4px;'>";
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
     * AJAX handler for auto-translating empty strings (delegated from main plugin)
     */
    public function ajax_auto_translate() {
        check_ajax_referer('auto_translate', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access.', 'ai-assistant'));
        }
        
        // Manual translation approach - AI auto-translate disabled
        wp_send_json_error([
            'message' => __('AI auto-translate has been disabled in favor of manual translation. This is a one-time translation task that works better with manual translation. Please translate the strings manually using the interface below.', 'ai-assistant'),
            'type' => 'manual_translation_required'
        ]);
    }
    
    /**
     * Get supported languages array
     */
    private function get_supported_languages() {
        // Foundation languages for translation management (English excluded as source language)
        return array(
            'tr_TR' => 'Türkçe (Turkish) ✅',
            'fa_IR' => 'فارسی (Persian/Farsi) ✅',
            'nl_NL' => 'Nederlands (Dutch) ✅',
            'da_DK' => 'Dansk (Danish) ✅',
            'fr_FR' => 'Français (French) ✅',
            'az_AZ' => 'Azərbaycan dili (Azerbaijani) ✅',
            'uz_UZ' => 'O\'zbek tili (Uzbek) ✅',
            'ar' => 'العربية (Arabic) ✅',
            'ky_KG' => 'Кыргыз тили (Kyrgyz) ✅',
            'ru_RU' => 'Русский (Russian) ✅',
            'pt_PT' => 'Português (Portuguese) ✅',
            'es_ES' => 'Español (Spanish) ✅',
            'de_DE' => 'Deutsch (German) ✅',
            'zh_CN' => '简体中文 (Chinese Simplified) ✅',
            'ug_CN' => 'ئۇيغۇرچە (Uyghur) ✅',
            'ur' => 'اردو (Urdu) ✅',
            'fi' => 'Suomi (Finnish) ✅',
            'tk' => 'Türkmen dili (Turkmen) ✅'
        );
    }
    
    /**
     * Get languages with actual translation files (Suleymaniye Foundation websites only)
     */
    private function get_available_translation_languages() {
        return array(
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
            'strings' => array(
                'saving' => __('Saving...', 'ai-assistant'),
                'saved' => __('Saved!', 'ai-assistant'),
                'error' => __('Error saving settings', 'ai-assistant'),
                'confirm_reload' => __('Language changed. The page will reload to apply the new language.', 'ai-assistant'),
                'testing' => __('Testing...', 'ai-assistant'),
                'success' => __('Success!', 'ai-assistant'),
                'failed' => __('Test failed', 'ai-assistant'),
                'compiling' => __('Compiling .mo files...', 'ai-assistant')
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
        error_log('AI Assistant: Suggestions table creation result: ' . print_r($result, true));
        
        // Verify table exists after creation
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        error_log('AI Assistant: Suggestions table exists after creation: ' . ($table_exists ? 'YES' : 'NO'));
    }
    
    /**
     * Get content suggestions count
     */
    private function get_content_suggestions_count() {
        // This would be tracked in a separate table or option
        return get_option('ai_assistant_suggestions_count', 0);
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
        $custom_lang = get_option('ai_assistant_admin_language');
        $current_locale = get_locale();
        
        // Only log once per session/request to avoid log spam
        static $logged_this_request = false;
        if (!$logged_this_request && WP_DEBUG_LOG) {
            error_log("AI Assistant Language Debug: Custom lang = " . ($custom_lang ?: 'NOT SET'));
            error_log("AI Assistant Language Debug: Current locale = " . $current_locale);
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
            
            if ($loaded && WP_DEBUG_LOG) {
                static $force_logged = false;
                if (!$force_logged) {
                    error_log("AI Assistant: Force immediate reload successful for " . $locale);
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
                error_log("AI Assistant: Language file missing for " . $locale);
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
            
            // Ensure our locale takes priority
            add_filter('locale', function($current_locale) use ($locale) {
                if (is_admin() && get_option('ai_assistant_admin_language') === $locale) {
                    return $locale;
                }
                return $current_locale;
            }, 999);
            
            // Only log success once per session/request and only if debug logging is enabled
            static $success_logged = array();
            if (!isset($success_logged[$locale]) && WP_DEBUG_LOG) {
                error_log("AI Assistant: Successfully loaded custom language: " . $locale);
                $success_logged[$locale] = true;
            }
        } else {
            // Only log failures once per session/request and only if debug logging is enabled
            static $failed_logged = array();
            if (!isset($failed_logged[$locale]) && WP_DEBUG_LOG) {
                error_log("AI Assistant: All loading methods failed for: " . $locale);
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
            $custom_lang = get_option('ai_assistant_admin_language');
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
            $custom_lang = get_option('ai_assistant_admin_language');
            if ($custom_lang) {
                error_log("AI Assistant Language Debug: Plugin locale filter applied - returning: " . $custom_lang);
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
            error_log("AI Assistant Language Debug: Looking for .mo file: " . $lang_file);
            error_log("AI Assistant Language Debug: .mo file exists = " . (file_exists($lang_file) ? 'YES' : 'NO'));
            $logged_files[$locale] = true;
        }
        
        // First try to load the .mo file directly
        if (file_exists($lang_file)) {
            $loaded = load_textdomain('ai-assistant', $lang_file);
            if (!isset($logged_files[$locale . '_result'])) {
                error_log("AI Assistant Language Debug: Direct .mo load result = " . ($loaded ? 'SUCCESS' : 'FAILED'));
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
        return str_replace(array('\\"', '\\n', '\\t', '\\\\'), array('"', "\n", "\t", '\\'), $string);
    }
    
    /**
     * Escape strings for .po files
     */
    private function escape_po_string($string) {
        return str_replace(array('\\', '"', "\n", "\t"), array('\\\\', '\\"', '\\n', '\\t'), $string);
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
            error_log("AI Assistant: Cannot compile .mo file - .po file does not exist: {$po_file}");
            return false;
        }
        
        // Try using msgfmt if available (most reliable method)
        if (function_exists('exec')) {
            $output = array();
            $return_var = 0;
            exec("msgfmt -o " . escapeshellarg($mo_file) . " " . escapeshellarg($po_file) . " 2>&1", $output, $return_var);
            
            if ($return_var === 0 && file_exists($mo_file)) {
                error_log("AI Assistant: Successfully compiled .mo file using msgfmt: {$mo_file}");
                return true;
            } else {
                error_log("AI Assistant: msgfmt compilation failed for {$po_file}. Output: " . implode("\n", $output));
            }
        }
        
        // Fallback: basic .mo file generation (simplified)
        $result = $this->simple_mo_compile($po_file, $mo_file);
        if ($result) {
            error_log("AI Assistant: Successfully compiled .mo file using fallback method: {$mo_file}");
        } else {
            error_log("AI Assistant: Failed to compile .mo file using fallback method: {$mo_file}");
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
        
        $custom_lang = get_option('ai_assistant_admin_language');
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
            
            if ($mo_exists && $translation_count > 0) {
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
}
