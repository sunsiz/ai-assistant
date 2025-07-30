<?php
/**
 * Plugin Name: AI Assistant for WordPress
 * Plugin URI: https://www.suleymaniyevakfi.org/
 * Description: AI-powered translation and content writing assistant for multilingual WordPress websites.
 * Version: 1.0.69
 * Author: Süleymaniye Vakfı
 * Author URI: https://www.suleymaniyevakfi.org/
 * Text Domain: ai-assistant
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: true
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * * @package AIAssistant
 * @version 1.0.58
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Define plugin constants
if (!defined('AI_ASSISTANT_VERSION')) {
    define('AI_ASSISTANT_VERSION', '1.0.69');
}
if (!defined('AI_ASSISTANT_PLUGIN_FILE')) {
    define('AI_ASSISTANT_PLUGIN_FILE', __FILE__);
}
if (!defined('AI_ASSISTANT_PLUGIN_DIR')) {
    define('AI_ASSISTANT_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('AI_ASSISTANT_PLUGIN_URL')) {
    define('AI_ASSISTANT_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * Main AIAssistant Class
 */
class AIAssistant {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Components
     */
    private $admin;
    private $ai_service;
    private $translator;
    private $content_analyzer;
    private $settings;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Load plugin components
        $this->load_dependencies();
        
        // Initialize plugin with higher priority for language loading
        add_action('plugins_loaded', array($this, 'early_init'), 1);
        add_action('init', array($this, 'init'), 1);
        
        // Admin initialization
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            add_action('admin_notices', array($this, 'admin_notices'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('save_post', array($this, 'save_post_meta'));
        }
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        // AJAX hooks
        add_action('wp_ajax_ai_assistant_translate', array($this, 'ajax_translate'));
        add_action('wp_ajax_ai_assistant_translate_url', array($this, 'ajax_translate_url'));
        add_action('wp_ajax_ai_assistant_fetch_url', array($this, 'ajax_fetch_url'));
        add_action('wp_ajax_ai_assistant_generate_content', array($this, 'ajax_generate_content'));
        add_action('wp_ajax_ai_assistant_get_suggestions', array($this, 'ajax_get_suggestions'));
        add_action('wp_ajax_ai_assistant_get_html_content', array($this, 'ajax_get_html_content'));
        
        // Image generation AJAX handlers
        add_action('wp_ajax_ai_assistant_generate_image_prompt', array($this, 'ajax_generate_image_prompt'));
        add_action('wp_ajax_ai_assistant_generate_image', array($this, 'ajax_generate_image'));
        add_action('wp_ajax_ai_assistant_set_featured_image', array($this, 'ajax_set_featured_image'));
        add_action('wp_ajax_ai_assistant_get_image_models', array($this, 'ajax_get_image_models'));
        
        // Language switching AJAX handler
        add_action('wp_ajax_ai_assistant_save_language', array($this, 'ajax_save_language'));
    }
      /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        $includes_dir = AI_ASSISTANT_PLUGIN_DIR . 'includes/';
        
        // Load required files first
        $required_files = array(
            'class-settings.php',
            'class-ai-service.php', 
            'class-translator.php',
            'class-content-analyzer.php',
            'class-admin.php',
            'class-diagnostics.php'
        );
        
        // Include required files with error handling
        foreach ($required_files as $file) {
            $file_path = $includes_dir . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // Only log missing files if debugging is enabled
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $this->log("Missing required file: {$file}");
                }
            }
        }
        
        // Initialize components with error handling
        try {
            if (class_exists('AI_Assistant_Settings')) {
                $this->settings = new AI_Assistant_Settings();
            }
            
            if (class_exists('AI_Assistant_AI_Service')) {
                $this->ai_service = new AI_Assistant_AI_Service();
            }
            
            if (class_exists('AI_Assistant_Translator')) {
                $this->translator = new AI_Assistant_Translator($this->ai_service);
            }
            
            if (class_exists('AI_Assistant_Content_Analyzer')) {
                $this->content_analyzer = new AI_Assistant_Content_Analyzer();
            }
            
            if (class_exists('AI_Assistant_Admin')) {
                $this->admin = new AI_Assistant_Admin($this->settings);
            }
            
        } catch (Exception $e) {
            // Only log errors if debugging is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->log("Error loading dependencies: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Centralized logging utility
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    public static function log($message, $level = 'info') {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        // Optional: Only log if WP_DEBUG_LOG is also enabled
        if (defined('WP_DEBUG_LOG') && !WP_DEBUG_LOG) {
            return;
        }
        
        $prefix = '[AI Assistant]';
        if ($level !== 'info') {
            $prefix .= ' [' . strtoupper($level) . ']';
        }
        
        error_log($prefix . ' ' . $message);
    }
      /**
     * Early initialization for language loading
     */
    public function early_init() {
        // Universal language setting for all sites
        $this->ensure_language_setting();
    }
    
    /**
     * Initialize plugin
     */    public function init() {
        // Auto-set language based on WordPress locale if not already set
        $this->auto_set_language();
        
        // Load text domain
        load_plugin_textdomain('ai-assistant', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Check for version updates and set notices
        $this->check_version_update();
    }
    
    /**
     * Ensure language setting is properly configured for any locale
     */
    private function ensure_language_setting() {
        $wp_locale = get_locale();
        $current_setting = get_option('ai_assistant_admin_language');
        
        // Auto-set plugin language to match WordPress locale if not configured
        if (empty($current_setting) || $current_setting !== $wp_locale) {
            // Check if we have a translation file for this locale
            $lang_file = AI_ASSISTANT_PLUGIN_DIR . 'languages/ai-assistant-' . $wp_locale . '.mo';
            if (file_exists($lang_file)) {
                update_option('ai_assistant_admin_language', $wp_locale);
            } else {
                // Fallback to base language if specific variant doesn't exist
                $base_locale = substr($wp_locale, 0, 2); // e.g., 'zh' from 'zh_CN'
                $base_lang_file = AI_ASSISTANT_PLUGIN_DIR . 'languages/ai-assistant-' . $base_locale . '.mo';
                if (file_exists($base_lang_file)) {
                    update_option('ai_assistant_admin_language', $base_locale);
                }
            }
        }
    }
    
    /**
     * Auto-set language based on WordPress locale if not already set
     */
    private function auto_set_language() {
        // Get user-specific language setting first
        $current_user_id = get_current_user_id();
        $user_language = get_user_meta($current_user_id, 'ai_assistant_language', true);
        
        // If user has no specific setting, check global option as fallback
        if (empty($user_language)) {
            $current_setting = get_option('ai_assistant_admin_language');
            $wp_locale = get_locale();
            
            // If no global language is set, use WordPress locale
            if (empty($current_setting)) {
                update_option('ai_assistant_admin_language', $wp_locale);
            }
            
            // Set user language to match global setting for first time
            if ($current_user_id > 0) {
                $user_language = !empty($current_setting) ? $current_setting : $wp_locale;
                update_user_meta($current_user_id, 'ai_assistant_language', $user_language);
            }
        }
        
        // Load user's preferred language if set and different from default
        if (!empty($user_language) && $user_language !== 'en_US') {
            $this->load_custom_textdomain($user_language);
        }
    }
    
    /**
     * Load custom textdomain for specific language
     */
    private function load_custom_textdomain($locale) {
        $plugin_dir = plugin_dir_path(__FILE__);
        $lang_file = $plugin_dir . 'languages/ai-assistant-' . $locale . '.mo';
        
        if (file_exists($lang_file)) {
            // Enhanced loading for all languages
            unload_textdomain('ai-assistant');
            
            // Try multiple loading methods
            $loaded = false;
            
            // Method 1: Direct load
            $loaded = load_textdomain('ai-assistant', $lang_file);
            
            if (!$loaded) {
                // Method 2: Use WordPress's native loading with locale override
                add_filter('plugin_locale', function($current_locale, $domain) use ($locale) {
                    if ($domain === 'ai-assistant') {
                        return $locale;
                    }
                    return $current_locale;
                }, 10, 2);
                
                $loaded = load_plugin_textdomain('ai-assistant', false, dirname(plugin_basename(__FILE__)) . '/languages');
            }
            
            if ($loaded) {
                // DO NOT add global locale filter - this was causing site-wide language changes
                // Plugin translations will be handled by the plugin_locale filter above
                
                // Only log success once per session to reduce log noise
                static $logged_languages = array();
                if (!isset($logged_languages[$locale]) && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    self::log("Successfully loaded custom language: " . $locale);
                    $logged_languages[$locale] = true;
                }
            } else {
                // Fallback to default loading
                load_plugin_textdomain('ai-assistant', false, dirname(plugin_basename(__FILE__)) . '/languages');
            }
        }
    }
    
    /**
     * Check for plugin version updates and set appropriate notices
     */
    private function check_version_update() {
        $current_version = get_option('ai_assistant_version', '1.0.0');
        
        // Dynamic version update handling
        if (version_compare($current_version, AI_ASSISTANT_VERSION, '<')) {
            // Determine if this is a major update based on version difference
            $version_parts_current = explode('.', $current_version);
            $version_parts_new = explode('.', AI_ASSISTANT_VERSION);
            
            $is_major_update = false;
            
            // Check for major version changes (first two digits)
            if (count($version_parts_current) >= 2 && count($version_parts_new) >= 2) {
                $current_major = (int)$version_parts_current[0];
                $current_minor = (int)$version_parts_current[1];
                $new_major = (int)$version_parts_new[0];
                $new_minor = (int)$version_parts_new[1];
                
                // Major update if major version changes or minor version jumps significantly
                $is_major_update = ($new_major > $current_major) || 
                                  ($new_major === $current_major && ($new_minor - $current_minor) >= 5);
            }
            
            // Set appropriate notice based on update type
            if ($is_major_update) {
                set_transient('ai_assistant_update_notice', array(
                    'type' => 'major',
                    'from_version' => $current_version,
                    'to_version' => AI_ASSISTANT_VERSION
                ), DAY_IN_SECONDS);
            } else {
                set_transient('ai_assistant_update_notice', array(
                    'type' => 'minor',
                    'from_version' => $current_version,
                    'to_version' => AI_ASSISTANT_VERSION
                ), 6 * HOUR_IN_SECONDS); // Shorter duration for minor updates
            }
            
            update_option('ai_assistant_version', AI_ASSISTANT_VERSION);
        }
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Initialize admin settings - only in admin area
        if ($this->settings && is_admin()) {
            $this->settings->init();
        }    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        // Show activation success notice
        if (get_transient('ai_assistant_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('AI Assistant for WordPress', 'ai-assistant'); ?></strong> 
                    <?php _e('has been successfully activated!', 'ai-assistant'); ?>
                </p>
            </div>
            <?php
            delete_transient('ai_assistant_activation_notice');
        }
        
        // Show dynamic update notices
        $update_notice = get_transient('ai_assistant_update_notice');
        if ($update_notice && is_array($update_notice)) {
            $notice_class = ($update_notice['type'] === 'major') ? 'notice-success' : 'notice-info';
            $notice_title = ($update_notice['type'] === 'major') ? 
                sprintf(__('AI Assistant v%s - Major Enhancement!', 'ai-assistant'), $update_notice['to_version']) :
                sprintf(__('AI Assistant Updated to v%s', 'ai-assistant'), $update_notice['to_version']);
            
            $notice_message = ($update_notice['type'] === 'major') ?
                __('Enhanced features, improved performance, better error handling, and new capabilities. Your AI Assistant is more powerful than ever!', 'ai-assistant') :
                __('Bug fixes, improvements, and optimizations for better performance.', 'ai-assistant');
            ?>
            <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible">
                <p>
                    <strong><?php echo esc_html($notice_title); ?></strong> 
                    <?php echo esc_html($notice_message); ?>
                </p>
            </div>
            <?php
            delete_transient('ai_assistant_update_notice');
        }
        
        // Show configuration notice if not configured
        if (!$this->is_configured() && current_user_can('manage_options')) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('AI Assistant', 'ai-assistant'); ?>:</strong> 
                    <?php _e('Please configure your AI provider API keys to start using the plugin.', 'ai-assistant'); ?>
                    <a href="<?php echo admin_url('admin.php?page=ai-assistant-settings'); ?>">
                        <?php _e('Go to Settings', 'ai-assistant'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
      /**
     * Check if plugin is configured
     */
    private function is_configured() {
        $api_keys = get_option('ai_assistant_api_keys', array());
        return !empty($api_keys['openai']) || !empty($api_keys['anthropic']) || !empty($api_keys['gemini']);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        // Load on post edit screens
        if (in_array($hook, array('post.php', 'post-new.php', 'page.php', 'page-new.php'))) {
            wp_enqueue_style(
                'ai-assistant-editor',
                AI_ASSISTANT_PLUGIN_URL . 'assets/css/editor.css',
                array(),
                AI_ASSISTANT_VERSION
            );
            
            wp_enqueue_script(
                'ai-assistant-editor',
                AI_ASSISTANT_PLUGIN_URL . 'assets/js/editor.js',
                array('jquery'),
                AI_ASSISTANT_VERSION,
                true
            );
            
            wp_localize_script('ai-assistant-editor', 'aiAssistant', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_assistant_nonce'),
                'availableModels' => $this->ai_service ? $this->ai_service->get_available_models() : array(),
                'siteLanguage' => $this->get_current_language_code(),
                'strings' => array(
                    'error' => __('Error', 'ai-assistant'),
                    'translating' => __('Translating...', 'ai-assistant'),
                    'fetching' => __('Fetching...', 'ai-assistant'),
                    'generating' => __('Generating...', 'ai-assistant'),
                    'translateContent' => __('Translate Content', 'ai-assistant'),
                    'fetchContent' => __('Fetch Content', 'ai-assistant'),
                    'translateArticle' => __('Translate Article', 'ai-assistant'),
                    'generateContent' => __('Generate', 'ai-assistant'),
                    'contentFetched' => __('Content fetched successfully!', 'ai-assistant'),
                    'fetchFailed' => __('Failed to fetch content', 'ai-assistant'),
                    'translationFailed' => __('Translation failed', 'ai-assistant'),
                    'generationFailed' => __('Content generation failed', 'ai-assistant'),
                    'connectionError' => __('A connection error occurred.', 'ai-assistant'),
                    'enterContent' => __('Please enter content to translate.', 'ai-assistant'),
                    'enterUrl' => __('Please enter a URL to fetch.', 'ai-assistant'),
                    'enterContext' => __('Please enter a topic or context.', 'ai-assistant'),
                    // Image generation strings
                    'generatingPrompt' => __('Generating prompt...', 'ai-assistant'),
                    'generatingImage' => __('Generating image...', 'ai-assistant'),
                    'imageGenerated' => __('Image generated successfully!', 'ai-assistant'),
                    'imageGenerationFailed' => __('Image generation failed', 'ai-assistant'),
                    'settingFeaturedImage' => __('Setting featured image...', 'ai-assistant'),
                    'featuredImageSet' => __('Featured image set successfully!', 'ai-assistant'),
                    'featuredImageFailed' => __('Failed to set featured image', 'ai-assistant'),
                    'enterImagePrompt' => __('Please enter an image description.', 'ai-assistant'),
                    // Language names for dropdowns
                    'autoDetect' => __('Auto-detect', 'ai-assistant'),
                    'english' => __('English', 'ai-assistant'),
                    'turkish' => __('Turkish', 'ai-assistant'),
                    'arabic' => __('Arabic', 'ai-assistant'),
                    'spanish' => __('Spanish', 'ai-assistant'),
                    'french' => __('French', 'ai-assistant'),
                    'german' => __('German', 'ai-assistant'),
                    'russian' => __('Russian', 'ai-assistant'),
                    'chinese' => __('Chinese', 'ai-assistant'),
                    'persian' => __('Persian', 'ai-assistant'),
                    'portuguese' => __('Portuguese', 'ai-assistant'),
                    'dutch' => __('Dutch', 'ai-assistant'),
                    'danish' => __('Danish', 'ai-assistant'),
                    'azerbaijani' => __('Azerbaijani', 'ai-assistant'),
                    'uzbek' => __('Uzbek', 'ai-assistant'),
                    'kyrgyz' => __('Kyrgyz', 'ai-assistant'),
                    'uyghur' => __('Uyghur', 'ai-assistant'),
                    'urdu' => __('Urdu', 'ai-assistant'),
                    'finnish' => __('Finnish', 'ai-assistant'),
                    'turkmen' => __('Turkmen', 'ai-assistant'),
                )
            ));
        }
        
        // Also load on AI Assistant admin pages
        if (strpos($hook, 'ai-assistant') !== false) {
            wp_enqueue_style(
                'ai-assistant-admin',
                AI_ASSISTANT_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                AI_ASSISTANT_VERSION
            );
            
            wp_enqueue_script(
                'ai-assistant-admin',
                AI_ASSISTANT_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                AI_ASSISTANT_VERSION,
                true
            );
        }
    }
    
    /**
     * Add meta boxes for classic editor
     */
    public function add_meta_boxes() {
        add_meta_box(
            'ai-assistant-meta-box',
            __('AI Assistant', 'ai-assistant'),
            array($this, 'render_meta_box'),
            'post',
            'normal',
            'high'
        );
        
        add_meta_box(
            'ai-assistant-meta-box',
            __('AI Assistant', 'ai-assistant'),
            array($this, 'render_meta_box'),
            'page',
            'normal',
            'high'
        );
        
        // Also add to side for users who prefer it there
        add_meta_box(
            'ai-assistant-meta-box-side',
            __('AI Assistant (Compact)', 'ai-assistant'),
            array($this, 'render_meta_box_compact'),
            'post',
            'side',
            'high'
        );
        
        add_meta_box(
            'ai-assistant-meta-box-side',
            __('AI Assistant (Compact)', 'ai-assistant'),
            array($this, 'render_meta_box_compact'),
            'page',
            'side',
            'high'
        );
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        wp_nonce_field('ai_assistant_meta_box', 'ai_assistant_meta_box_nonce');
        $default_model = get_option('ai_assistant_default_model', 'gemini-2.5-flash');
        ?>
        <div class="ai-assistant-meta-box-container">
            <div class="ai-assistant-intro">
                <div class="ai-intro-header">
                    <h4><?php _e('AI Assistant Quick Guide', 'ai-assistant'); ?></h4>
                    <button type="button" class="ai-intro-toggle button-link" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php _e('Show/Hide Tips', 'ai-assistant'); ?>
                    </button>
                </div>
                <div class="ai-intro-content" style="display: none;">
                    <div class="ai-intro-grid">
                        <div class="ai-intro-tab">
                            <span class="ai-intro-icon">🔄</span>
                            <strong><?php _e('Translate', 'ai-assistant'); ?></strong>
                            <p><?php _e('Translate any text content. Paste your text, choose languages, and get instant translations.', 'ai-assistant'); ?></p>
                        </div>
                        <div class="ai-intro-tab">
                            <span class="ai-intro-icon">🌐</span>
                            <strong><?php _e('URL Translation', 'ai-assistant'); ?></strong>
                            <p><?php _e('Translate entire articles from URLs. Enter a web address to fetch and translate content automatically.', 'ai-assistant'); ?></p>
                        </div>
                        <div class="ai-intro-tab">
                            <span class="ai-intro-icon">✍️</span>
                            <strong><?php _e('Content Tools', 'ai-assistant'); ?></strong>
                            <p><?php _e('Generate content ideas, SEO keywords, meta descriptions, or complete articles based on your topic.', 'ai-assistant'); ?></p>
                        </div>
                        <div class="ai-intro-tab">
                            <span class="ai-intro-icon">🖼️</span>
                            <strong><?php _e('Featured Image', 'ai-assistant'); ?></strong>
                            <p><?php _e('Generate custom images using AI. Describe what you want and get professional images for your posts.', 'ai-assistant'); ?></p>
                        </div>
                    </div>
                    <div class="ai-intro-workflow">
                        <h5><?php _e('Quick Workflow Tips:', 'ai-assistant'); ?></h5>
                        <ul>
                            <li><?php _e('💡 Use "Use Post Content" to quickly populate the translator with your existing content', 'ai-assistant'); ?></li>
                            <li><?php _e('🔄 Review and edit translations before inserting them into your post', 'ai-assistant'); ?></li>
                            <li><?php _e('⚙️ Configure your API keys in Settings for enhanced translation quality', 'ai-assistant'); ?></li>
                            <li><?php _e('📝 Generated content can be directly inserted into your WordPress editor', 'ai-assistant'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="ai-assistant-tabs">
                <!-- WordPress-style nav tabs -->
                <nav class="nav-tab-wrapper wp-clearfix">
                    <a href="javascript:void(0)" class="nav-tab nav-tab-active" data-tab="translate">
                        <?php _e('Translate', 'ai-assistant'); ?>
                    </a>
                    <a href="javascript:void(0)" class="nav-tab" data-tab="url">
                        <?php _e('URL Translation', 'ai-assistant'); ?>
                    </a>
                    <a href="javascript:void(0)" class="nav-tab" data-tab="content">
                        <?php _e('Content Tools', 'ai-assistant'); ?>
                    </a>
                    <a href="javascript:void(0)" class="nav-tab" data-tab="image">
                        <?php _e('Featured Image', 'ai-assistant'); ?>
                    </a>
                </nav>

                <div id="ai-tab-translate" class="ai-tab-content active">
                    <div class="ai-tab-help">
                        <p class="ai-help-text">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Translate text content between languages. Use "Use Post Content" to quickly populate with your current post content, or paste any text to translate.', 'ai-assistant'); ?>
                        </p>
                    </div>
                    <div class="ai-assistant-controls">                        
                        <div class="ai-control-row">
                            <div class="ai-control-group"><label for="ai-source-lang"><?php _e('Source Language', 'ai-assistant'); ?></label><select id="ai-source-lang" name="ai_source_lang"></select></div>
                            <div class="ai-control-group"><label for="ai-target-lang"><?php _e('Target Language', 'ai-assistant'); ?></label><select id="ai-target-lang" name="ai_target_lang"></select></div>
                            <div class="ai-control-group"><label for="ai-model-select"><?php _e('AI Model', 'ai-assistant'); ?></label><select id="ai-model-select" name="ai_model_select">
                                <option value="gemini-2.5-flash" <?php selected($default_model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                <option value="gpt-4" <?php selected($default_model, 'gpt-4'); ?>>GPT-4</option>
                            </select></div>
                            <div class="ai-control-group"><button type="button" class="button button-secondary ai-assistant-populate-btn"><?php _e('Use Post Content', 'ai-assistant'); ?></button></div>
                            <div class="ai-control-group"><button type="button" class="button button-primary ai-assistant-translate-btn"><?php _e('Translate Content', 'ai-assistant'); ?></button></div>
                        </div>
                    </div>
                    <div class="ai-assistant-workspace">
                        <div class="ai-workspace-left">
                            <h5><?php echo strtoupper(__('Source Content', 'ai-assistant')) . ' (' . __('Editable', 'ai-assistant') . ')'; ?></h5>
                            <textarea id="ai-source-content" placeholder="<?php _e('Enter or paste content to translate...', 'ai-assistant'); ?>"></textarea>
                        </div>
                        <div class="ai-workspace-right">
                            <h5><?php echo strtoupper(__('Translated Content', 'ai-assistant')) . ' (' . __('Editable', 'ai-assistant') . ')'; ?></h5>
                            <textarea id="ai-target-content" placeholder="<?php _e('Translation will appear here...', 'ai-assistant'); ?>" readonly></textarea>
                        </div>
                    </div>
                </div>                <div id="ai-tab-url" class="ai-tab-content">
                    <div class="ai-tab-help">
                        <p class="ai-help-text">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Translate entire articles from web URLs. Enter any article URL, fetch the content automatically, then translate it to your target language.', 'ai-assistant'); ?>
                        </p>
                    </div>
                    <div class="ai-assistant-controls">
                        <div class="ai-control-row">
                            <div class="ai-control-group ai-url-input"><label for="ai-article-url"><?php _e('Article URL', 'ai-assistant'); ?></label><input type="url" id="ai-article-url" placeholder="https://example.com/article"></div>
                            <div class="ai-control-group"><label for="ai-url-source-lang"><?php _e('Source Language', 'ai-assistant'); ?></label><select id="ai-url-source-lang" name="ai_url_source_lang"></select></div>
                            <div class="ai-control-group"><label for="ai-url-target-lang"><?php _e('Target Language', 'ai-assistant'); ?></label><select id="ai-url-target-lang" name="ai_url_target_lang"></select></div>
                            <div class="ai-control-group"><label for="ai-url-model-select"><?php _e('AI Model', 'ai-assistant'); ?></label><select id="ai-url-model-select" name="ai_url_model_select">
                                <option value="gemini-2.5-flash" <?php selected($default_model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                <option value="gpt-4" <?php selected($default_model, 'gpt-4'); ?>>GPT-4</option>
                            </select></div>
                            <div class="ai-control-group"><button type="button" class="button button-primary ai-fetch-content-btn"><?php _e('Fetch Content', 'ai-assistant'); ?></button></div>
                            <div class="ai-control-group"><button type="button" class="button button-secondary ai-translate-article-btn"><?php _e('Translate Article', 'ai-assistant'); ?></button></div>
                        </div>
                    </div>                    <div class="ai-assistant-workspace">
                        <div class="ai-workspace-left">
                            <h5><?php _e('Original Article', 'ai-assistant'); ?></h5>
                            <textarea id="ai-original-article" placeholder="<?php _e('Fetched content will appear here...', 'ai-assistant'); ?>"></textarea>
                        </div>
                        <div class="ai-workspace-right">
                            <h5><?php _e('Translated Article', 'ai-assistant'); ?></h5>
                            <textarea id="ai-translated-article" placeholder="<?php _e('Translated article will appear here...', 'ai-assistant'); ?>"></textarea>
                        </div>
                    </div>                    <div class="ai-assistant-actions">
                        <button type="button" class="button button-primary ai-insert-content-btn" disabled>
                            <?php _e('Insert Translated Content', 'ai-assistant'); ?>
                        </button>
                        <span class="ai-action-help"><?php _e('Review and edit the translation above, then click to insert into WordPress editor', 'ai-assistant'); ?></span>
                    </div>
                    <div class="ai-workflow-tip">
                        <strong><?php _e('Next Steps:', 'ai-assistant'); ?></strong> 
                        <?php _e('Once translation is complete, you can insert it directly into your post content or copy it for use elsewhere.', 'ai-assistant'); ?>
                    </div>
                </div>

                <div id="ai-tab-content" class="ai-tab-content">
                    <div class="ai-tab-help">
                        <p class="ai-help-text">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Generate content using AI. Choose content type (articles, keywords, descriptions), enter your topic, and let AI create professional content for your posts.', 'ai-assistant'); ?>
                        </p>
                    </div>
                    <div class="ai-assistant-controls">
                        <div class="ai-control-row">
                            <div class="ai-control-group">
                                <label for="ai-content-type"><?php _e('Content Type', 'ai-assistant'); ?></label>                                <select id="ai-content-type">
                                    <option value="suggestions"><?php _e('Content Suggestions', 'ai-assistant'); ?></option>
                                    <option value="full-article"><?php _e('Full Article', 'ai-assistant'); ?></option>
                                    <option value="keywords"><?php _e('SEO Keywords', 'ai-assistant'); ?></option>
                                    <option value="meta-description"><?php _e('Meta Description', 'ai-assistant'); ?></option>
                                    <option value="title-ideas"><?php _e('Title Ideas', 'ai-assistant'); ?></option>
                                </select>
                            </div>
                            <div class="ai-control-group">
                                <label for="ai-content-context"><?php _e('Topic/Context', 'ai-assistant'); ?></label>
                                <input type="text" id="ai-content-context" placeholder="<?php _e('Enter topic or context...', 'ai-assistant'); ?>" class="text">
                            </div>
                            <div class="ai-control-group"><label for="ai-content-model-select"><?php _e('AI Model', 'ai-assistant'); ?></label><select id="ai-content-model-select" name="ai_content_model_select">
                                <option value="gemini-2.5-flash" <?php selected($default_model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                <option value="gpt-4" <?php selected($default_model, 'gpt-4'); ?>>GPT-4</option>
                            </select></div>
                            <div class="ai-control-group">
                                <button type="button" class="button button-secondary ai-use-post-title-btn"><?php _e('Use Post Title', 'ai-assistant'); ?></button>
                            </div>
                            <div class="ai-control-group">
                                <button type="button" class="button button-primary ai-generate-content-btn"><?php _e('Generate', 'ai-assistant'); ?></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ai-assistant-workspace">
                        <div class="ai-workspace-full">
                            <h5><?php _e('Generated Content', 'ai-assistant'); ?></h5>
                            <textarea id="ai-generated-content" placeholder="<?php _e('Generated content will appear here...', 'ai-assistant'); ?>"></textarea>
                        </div>
                    </div>
                    
                    <div class="ai-assistant-actions">
                        <button type="button" class="button button-primary ai-insert-generated-btn" disabled>
                            <?php _e('Insert to Editor', 'ai-assistant'); ?>
                        </button>
                        <button type="button" class="button button-secondary ai-apply-keywords-btn" disabled style="display:none;">
                            <?php _e('Apply to Yoast SEO', 'ai-assistant'); ?>
                        </button>
                        <span class="ai-action-help"><?php _e('Review the generated content above, then choose an action', 'ai-assistant'); ?></span>
                    </div>
                    <div class="ai-workflow-tip">
                        <strong><?php _e('Pro Tip:', 'ai-assistant'); ?></strong> 
                        <?php _e('For SEO keywords, the generated keywords will automatically populate Yoast SEO fields if the plugin is active.', 'ai-assistant'); ?>
                    </div>
                </div>

                <div id="ai-tab-image" class="ai-tab-content">
                    <div class="ai-tab-help">
                        <p class="ai-help-text">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Generate custom images using AI. Describe the image you want in detail, choose a style, and create professional images for your posts.', 'ai-assistant'); ?>
                        </p>
                    </div>
                    <div class="ai-assistant-controls">
                        <div class="ai-control-row">
                            <div class="ai-control-group">
                                <label for="ai-image-prompt"><?php _e('Image Description/Prompt', 'ai-assistant'); ?></label>
                                <textarea id="ai-image-prompt" placeholder="<?php _e('Describe the image you want to generate (e.g., A modern office workspace with laptop and coffee)...', 'ai-assistant'); ?>" rows="3" class="text"></textarea>
                            </div>
                        </div>
                        <div class="ai-control-row">
                            <div class="ai-control-group">
                                <label for="ai-image-style"><?php _e('Image Style', 'ai-assistant'); ?></label>
                                <select id="ai-image-style">
                                    <option value="photorealistic"><?php _e('Photorealistic', 'ai-assistant'); ?></option>
                                    <option value="illustration"><?php _e('Illustration', 'ai-assistant'); ?></option>
                                    <option value="digital-art"><?php _e('Digital Art', 'ai-assistant'); ?></option>
                                    <option value="minimalist"><?php _e('Minimalist', 'ai-assistant'); ?></option>
                                    <option value="professional"><?php _e('Professional/Business', 'ai-assistant'); ?></option>
                                    <option value="abstract"><?php _e('Abstract', 'ai-assistant'); ?></option>
                                </select>
                            </div>
                            <div class="ai-control-group">
                                <label for="ai-image-size"><?php _e('Image Size', 'ai-assistant'); ?></label>
                                <select id="ai-image-size">
                                    <option value="1024x1024"><?php _e('Square (1024x1024)', 'ai-assistant'); ?></option>
                                    <option value="1792x1024"><?php _e('Landscape (1792x1024)', 'ai-assistant'); ?></option>
                                    <option value="1024x1792"><?php _e('Portrait (1024x1792)', 'ai-assistant'); ?></option>
                                </select>
                            </div>
                            <div class="ai-control-group">
                                <label for="ai-image-model-select"><?php _e('AI Model', 'ai-assistant'); ?></label>
                                <select id="ai-image-model-select" name="ai_image_model_select">
                                    <option value="dall-e-3" <?php selected($default_model, 'dall-e-3'); ?>>DALL-E 3 (OpenAI)</option>
                                    <option value="gemini-2.5-flash" <?php selected($default_model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash</option>
                                    <option value="claude-3-5-sonnet-20241022" <?php selected($default_model, 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
                                    <option value="gpt-4" <?php selected($default_model, 'gpt-4'); ?>>GPT-4</option>
                                </select>
                            </div>
                            <div class="ai-control-group">
                                <button type="button" class="button button-secondary ai-generate-prompt-btn"><?php _e('Auto-Generate Prompt', 'ai-assistant'); ?></button>
                            </div>
                            <div class="ai-control-group">
                                <button type="button" class="button button-primary ai-generate-image-btn"><?php _e('Generate Image', 'ai-assistant'); ?></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ai-assistant-workspace">
                        <div class="ai-workspace-full">
                            <h5><?php _e('Generated Image Preview', 'ai-assistant'); ?></h5>
                            <div id="ai-image-preview-container" style="text-align: center; min-height: 200px; border: 2px dashed #ddd; padding: 20px; margin: 10px 0;">
                                <p style="color: #666; font-style: italic;"><?php _e('Generated image will appear here...', 'ai-assistant'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ai-assistant-actions">
                        <button type="button" class="button button-primary ai-set-featured-image-btn" disabled>
                            <?php _e('Set as Featured Image', 'ai-assistant'); ?>
                        </button>
                        <button type="button" class="button button-secondary ai-download-image-btn" disabled>
                            <?php _e('Download Image', 'ai-assistant'); ?>
                        </button>
                        <span class="ai-action-help"><?php _e('Generate an image, then use the buttons above to set it as featured image or download', 'ai-assistant'); ?></span>
                    </div>
                    <div class="ai-workflow-tip">
                        <strong><?php _e('Image Tips:', 'ai-assistant'); ?></strong> 
                        <?php _e('Be descriptive in your prompt for best results. Include style, colors, mood, and composition details.', 'ai-assistant'); ?>
                    </div>
                </div>
            </div>
            <div id="ai-assistant-message-area" style="margin-top: 15px;"></div>
        </div>
        <?php
    }

    /**
     * Render meta box compact
     */
    public function render_meta_box_compact($post) {
        // A simplified version for the sidebar
        ?>
        <div class="ai-assistant-compact">
            <div class="ai-compact-info">
                <p><strong><?php _e('AI Assistant Tools Available:', 'ai-assistant'); ?></strong></p>
                <ul class="ai-compact-features">
                    <li><?php _e('✓ Content Translation', 'ai-assistant'); ?></li>
                    <li><?php _e('✓ URL Article Translation', 'ai-assistant'); ?></li>
                    <li><?php _e('✓ Content Generation', 'ai-assistant'); ?></li>
                    <li><?php _e('✓ AI Image Creation', 'ai-assistant'); ?></li>
                </ul>
            </div>
            <div class="ai-compact-buttons">
                <button type="button" class="button button-primary" onclick="document.getElementById('ai-assistant-meta-box').scrollIntoView({ behavior: 'smooth' });"><?php _e('Open AI Assistant Panel', 'ai-assistant'); ?></button>
            </div>
            <p class="description"><?php _e('Access the full-featured AI tools in the main content area below.', 'ai-assistant'); ?></p>
        </div>
        <?php
    }
      /**
     * AJAX handler for translating content
     */
    public function ajax_translate() {
        check_ajax_referer('ai_assistant_nonce', 'nonce');

        // Ensure translator is available
        if (!isset($this->translator) || !$this->translator) {
            // Try to load it directly if not available
            if (class_exists('AI_Assistant_Translator')) {
                $this->translator = new AI_Assistant_Translator();
            } else {
                wp_send_json_error('Translator class not found. Please check plugin installation.');
            }
        }

        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $source_lang = isset($_POST['source_language']) ? sanitize_text_field($_POST['source_language']) : 'auto';
        $target_lang = isset($_POST['target_language']) ? sanitize_text_field($_POST['target_language']) : 'en';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gemini-2.5-flash';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;

        if (empty($content) || empty($target_lang)) {
            wp_send_json_error('Missing required fields for translation.');
        }

        $result = $this->translator->translate($content, $target_lang, $source_lang, $model);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Save translation to database for history
        if (method_exists($this->translator, 'save_translation_history')) {
            $save_result = $this->translator->save_translation_history(array(
                'post_id' => $post_id,
                'source_language' => $source_lang,
                'target_language' => $target_lang,
                'original_content' => $content,
                'translated_content' => $result,
                'model' => $model
            ));
            if (!$save_result) {
                // Only log translation history errors if debugging is enabled
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    self::log('Translation history save failed', 'warning');
                }
            }
        }

        wp_send_json_success(array('translation' => $result));
    }
    
    /**
     * AJAX handler for translating URL content
     */
    public function ajax_translate_url() {
        try {
            check_ajax_referer('ai_assistant_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_die(__('You do not have permission to perform this action.', 'ai-assistant'));
            }

            // Ensure AI service is available first
            if (!isset($this->ai_service) || !$this->ai_service) {
                if (class_exists('AI_Assistant_AI_Service')) {
                    $this->ai_service = new AI_Assistant_AI_Service();
                } else {
                    self::log('AI Service class not found', 'error');
                    wp_send_json_error('AI Service class not found. Please check plugin installation.');
                }
            }

            // Ensure translator is available
            if (!isset($this->translator) || !$this->translator) {
                if (class_exists('AI_Assistant_Translator')) {
                    $this->translator = new AI_Assistant_Translator($this->ai_service);
                } else {
                    self::log('Translator class not found', 'error');
                    wp_send_json_error('Translator class not found. Please check plugin installation.');
                }
            }

            $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
            $source_lang = isset($_POST['source_language']) ? sanitize_text_field($_POST['source_language']) : 'auto';
            $target_lang = isset($_POST['target_language']) ? sanitize_text_field($_POST['target_language']) : 'en';
            $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gemini-2.5-flash';

            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                self::log('Invalid URL provided: ' . $url, 'warning');
                wp_send_json_error('Invalid or missing URL.');
            }

            if (empty($target_lang)) {
                self::log('Missing target language', 'warning');
                wp_send_json_error('Missing target language.');
            }

            // Use the translator's translate_url method which saves to history
            $result = $this->translator->translate_url($url, $source_lang, $target_lang, $model);

            // Debug logging to track the translation result
            if (defined('WP_DEBUG') && WP_DEBUG) {
                self::log('Translation result: ' . print_r($result, true), 'info');
            }

            if (is_wp_error($result)) {
                self::log('Translation error: ' . $result->get_error_message(), 'error');
                wp_send_json_error($result->get_error_message());
            }

            if (!$result['success']) {
                self::log('Translation failed: ' . (isset($result['message']) ? $result['message'] : 'Unknown error'), 'error');
                wp_send_json_error(isset($result['message']) ? $result['message'] : 'Translation failed');
            }

            // Debug logging to see what we're sending back
            if (defined('WP_DEBUG') && WP_DEBUG) {
                self::log('Sending translation response with translated_content length: ' . strlen($result['translated_content']), 'info');
            }

            wp_send_json_success(array(
                'original_content' => $result['original_content'],
                'translated_content' => $result['translated_content'],
                'source_url' => $result['source_url'],
                'source_lang' => $result['source_lang'],
                'target_lang' => $result['target_lang'],
                'model' => $result['model']
            ));
            
        } catch (Exception $e) {
            self::log('Exception in ajax_translate_url: ' . $e->getMessage(), 'error');
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        } catch (Error $e) {
            self::log('Fatal error in ajax_translate_url: ' . $e->getMessage(), 'error');
            wp_send_json_error('A fatal error occurred: ' . $e->getMessage());
        }
    }
    /**
     * AJAX handler for fetching URL content
     */
    public function ajax_fetch_url() {
        check_ajax_referer('ai_assistant_nonce', 'nonce');

        // Ensure content analyzer is available
        if (!isset($this->content_analyzer) || !$this->content_analyzer) {
            // Try to load it directly if not available
            if (class_exists('AI_Assistant_Content_Analyzer')) {
                $this->content_analyzer = new AI_Assistant_Content_Analyzer();
            } else {
                wp_send_json_error('Content analyzer class not found. Please check plugin installation.');
            }
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid or missing URL.');
        }

        $result = $this->content_analyzer->fetch_and_extract($url);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('content' => $result));
    }
    
    /**
     * Save post meta
     */
    public function save_post_meta($post_id) {
        if (!isset($_POST['ai_assistant_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['ai_assistant_meta_box_nonce'], 'ai_assistant_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }    /**
     * AJAX: Generate content
     */
    public function ajax_generate_content() {
        check_ajax_referer('ai_assistant_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to perform this action.', 'ai-assistant'));
        }
        
        $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : 'suggestions';
        $context = isset($_POST['context']) ? sanitize_textarea_field($_POST['context']) : '';
        $existing_content = isset($_POST['existing_content']) ? sanitize_textarea_field($_POST['existing_content']) : '';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'gemini-2.5-flash';
        
        if (empty($context)) {
            wp_send_json_error(array(
                'message' => __('Please provide a topic or context for content generation.', 'ai-assistant')
            ));
        }
        
        // Initialize AI service if not already done
        if (!isset($this->ai_service)) {
            $this->ai_service = new AI_Assistant_AI_Service();
        }
        
        $result = $this->ai_service->generate_content($content_type, $context, $existing_content, $model);
        
        if ($result['success']) {
            // Store content generation in history
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
            $this->store_content_generation_history($content_type, $context, $result['content'], $post_id, $model);
            
            wp_send_json_success(array(
                'content' => $result['content'],
                'html_content' => isset($result['html_content']) ? $result['html_content'] : '',
                'html_cache_key' => isset($result['html_cache_key']) ? $result['html_cache_key'] : '',
                'type' => $result['type'],
                'context' => $result['context'],
                'provider' => $result['provider'],
                'model' => $result['model']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error']
            ));
        }
    }
    
    /**
     * AJAX: Get content suggestions for auto-complete
     */
    public function ajax_get_suggestions() {
        check_ajax_referer('ai_assistant_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to perform this action.', 'ai-assistant'));
        }
        
        $current_text = isset($_POST['current_text']) ? sanitize_textarea_field($_POST['current_text']) : '';
        $context = isset($_POST['context']) ? sanitize_textarea_field($_POST['context']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
        
        if (empty($current_text)) {
            wp_send_json_error(array(
                'message' => __('No text provided for suggestions.', 'ai-assistant')
            ));
        }
        
        // Quick cache check for recent suggestions (shorter cache time for more variety)
        $cache_key = 'ai_suggestions_' . md5(substr($current_text, -100) . $context . strlen($current_text));
        $cached_suggestions = get_transient($cache_key);
        
        if ($cached_suggestions !== false) {
            wp_send_json_success(array(
                'suggestions' => $cached_suggestions,
                'cached' => true
            ));
            return;
        }
        
        // Initialize AI service if not already done
        if (!isset($this->ai_service)) {
            $this->ai_service = new AI_Assistant_AI_Service();
        }
        
        // Create a prompt for content continuation
        $prompt = "Based on the following partial text, provide 3 contextually relevant suggestions to continue the sentence or paragraph. Each suggestion should be at least 5-15 words that would naturally follow the text. Format as a simple list, one suggestion per line:\n\nText: \"{$current_text}\"";
        
        if (!empty($context)) {
            $prompt .= "\nContext/Topic: \"{$context}\"";
        }
        
        $response = $this->ai_service->make_api_request_public($prompt);
        
        if ($response['success']) {
            // Parse suggestions from the response
            $suggestions = array_filter(array_map('trim', explode("\n", $response['content'])));
            // Clean up numbered list format if present
            $suggestions = array_map(function($suggestion) {
                return preg_replace('/^\d+\.\s*/', '', $suggestion);
            }, $suggestions);
            
            $final_suggestions = array_slice($suggestions, 0, 3); // Limit to 3 suggestions
            
            // Cache successful results for 2 minutes for faster subsequent requests (reduced from 5 minutes for more variety)
            set_transient($cache_key, $final_suggestions, 120);
            
            // Store suggestions in database
            $this->store_suggestion_history($current_text, $final_suggestions, $post_id);
            
            wp_send_json_success(array(
                'suggestions' => $final_suggestions,
                'cached' => false
            ));
        } else {
            wp_send_json_error(array(
                'message' => $response['error']
            ));
        }
    }
    
    /**
     * Store suggestion in history
     */
    private function store_suggestion_history($input_text, $suggestions, $post_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_assistant_suggestions';
        $current_user_id = get_current_user_id();
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            return;
        }
        
        // Get current AI model from settings
        $settings = get_option('ai_assistant_settings', array());
        $api_keys = get_option('ai_assistant_api_keys', array());
        $preferred_provider = get_option('ai_assistant_preferred_provider', 'gemini');
        
        // Determine model name based on current provider
        $model_name = $preferred_provider;
        if (!empty($api_keys['openai']) && $preferred_provider === 'openai') {
            $model_name = 'gpt-4o-mini';
        } elseif (!empty($api_keys['anthropic']) && $preferred_provider === 'anthropic') {
            $model_name = 'claude-3-5-haiku';
        } elseif (!empty($api_keys['gemini']) && $preferred_provider === 'gemini') {
            $model_name = 'gemini-2.5-flash';
        }
        
        // Store each suggestion as a separate record
        $stored_count = 0;
        foreach ($suggestions as $suggestion) {
            if (!empty(trim($suggestion))) {
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'post_id' => $post_id,
                        'user_id' => $current_user_id,
                        'input_text' => wp_kses_post($input_text),
                        'suggestion_text' => wp_kses_post($suggestion),
                        'suggestion_type' => 'autocomplete',
                        'model' => sanitize_text_field($model_name),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
                );
                
                if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
                    self::log('Failed to insert suggestion: ' . $wpdb->last_error, 'error');
                } else {
                    $stored_count++;
                }
            }
        }
    }
    
    /**
     * Store content generation in history
     */
    private function store_content_generation_history($content_type, $input_text, $generated_content, $post_id = null, $model = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_assistant_suggestions';
        $current_user_id = get_current_user_id();
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                self::log('Suggestions table does not exist: ' . $table_name, 'warning');
            }
            return;
        }
        
        // Determine model name
        $api_keys = get_option('ai_assistant_api_keys', array());
        $preferred_provider = get_option('ai_assistant_preferred_provider', 'gemini');
        
        // Get dynamic model names based on available configurations
        $default_models = array(
            'openai' => 'gpt-4.1',
            'anthropic' => 'claude-4-sonnet',
            'gemini' => 'gemini-2.5-flash'
        );
        
        $model_name = !empty($model) ? $model : $preferred_provider;
        if (!empty($api_keys[$preferred_provider]) && isset($default_models[$preferred_provider])) {
            $model_name = $default_models[$preferred_provider];
        }
        
        // Store the content generation
        $result = $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'user_id' => $current_user_id,
                'input_text' => wp_kses_post($input_text),
                'suggestion_text' => wp_kses_post($generated_content),
                'suggestion_type' => sanitize_text_field($content_type),
                'model' => sanitize_text_field($model_name),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
            self::log('Failed to insert content generation: ' . $wpdb->last_error, 'error');
        }
    }
    
    /**
     * AJAX: Get HTML content for WordPress insertion
     */
    public function ajax_get_html_content() {
        check_ajax_referer('ai_assistant_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to perform this action.', 'ai-assistant'));
        }
        
        $cache_key = isset($_POST['html_cache_key']) ? sanitize_text_field($_POST['html_cache_key']) : '';
        
        if (empty($cache_key)) {
            wp_send_json_error(array(
                'message' => __('No cache key provided.', 'ai-assistant')
            ));
        }
        
        // Initialize AI service if not already done
        if (!isset($this->ai_service)) {
            $this->ai_service = new AI_Assistant_AI_Service();
        }
        
        $html_content = $this->ai_service->get_html_for_insertion($cache_key);
        
        if ($html_content) {
            wp_send_json_success(array(
                'html_content' => $html_content
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('HTML content not found or expired.', 'ai-assistant')
            ));
        }
    }
    
    /**
     * AJAX handler for saving language settings
     */
    public function ajax_save_language() {
        check_ajax_referer('ai_assistant_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $new_language = isset($_POST['admin_language']) ? sanitize_text_field($_POST['admin_language']) : '';
        $current_user_id = get_current_user_id();
        
        // Get current user's language setting
        $old_language = get_user_meta($current_user_id, 'ai_assistant_language', true);
        if (empty($old_language)) {
            // Fallback to global setting for comparison
            $old_language = get_option('ai_assistant_admin_language', get_locale());
        }
        
        // Debug logging (reduced verbosity)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log("Language change: User #{$current_user_id}: {$old_language} → {$new_language}");
        }
        
        if (empty($new_language)) {
            wp_send_json_error('No language specified');
        }
        
        // Save the new language for this specific user
        $update_result = update_user_meta($current_user_id, 'ai_assistant_language', $new_language);
        
        $language_changed = ($new_language !== $old_language);
        
        // Force reload textdomain if language changed
        if ($language_changed) {
            $unloaded = unload_textdomain('ai-assistant');
            
            // Load new language for this user
            if ($new_language !== 'en_US') {
                $this->load_custom_textdomain($new_language);
            } else {
                // Load default English
                load_plugin_textdomain('ai-assistant', false, dirname(plugin_basename(__FILE__)) . '/languages');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                self::log("Textdomain reloaded for user language: {$new_language}");
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Language settings saved successfully.', 'ai-assistant'),
            'language_changed' => $language_changed,
            'new_language' => $new_language,
            'user_specific' => true,
            'debug_info' => array(
                'user_id' => $current_user_id,
                'old_language' => $old_language,
                'update_result' => $update_result,
                'textdomain_loaded' => is_textdomain_loaded('ai-assistant')
            )
        ));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_database_tables();
        
        // Set default options
        $defaults = array(
            'ai_assistant_enabled' => true,
            'ai_assistant_default_model' => 'gemini-2.5-flash',
            'ai_assistant_api_keys' => array(),
        );
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
        
        // Set language based on WordPress locale if not already set
        if (get_option('ai_assistant_admin_language') === false) {
            update_option('ai_assistant_admin_language', get_locale());
        }
        
        // Set activation notice
        set_transient('ai_assistant_activation_notice', true, 30);
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create translations table
        $translations_table = $wpdb->prefix . 'ai_assistant_translations';
        $sql_translations = "CREATE TABLE $translations_table (
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
        
        // Create suggestions table
        $suggestions_table = $wpdb->prefix . 'ai_assistant_suggestions';
        $sql_suggestions = "CREATE TABLE $suggestions_table (
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
        dbDelta($sql_translations);
        dbDelta($sql_suggestions);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        delete_transient('ai_assistant_activation_notice');
    }

    /**
     * Get current language code for frontend use
     */
    private function get_current_language_code() {
        $locale = get_locale();
        
        // Universal language code extraction - works for any locale format
        $language_code = $this->extract_language_code($locale);
        
        return !empty($language_code) ? $language_code : 'en';
    }
    
    /**
     * Universal language code extraction method
     * Handles any locale format dynamically
     */
    private function extract_language_code($locale) {
        if (empty($locale)) {
            return 'en';
        }
        
        // Handle common locale formats: en_US, pt_BR, zh_CN, etc.
        if (strpos($locale, '_') !== false) {
            $parts = explode('_', $locale);
            return strtolower($parts[0]);
        }
        
        // Handle dash format: en-US, pt-BR, etc.
        if (strpos($locale, '-') !== false) {
            $parts = explode('-', $locale);
            return strtolower($parts[0]);
        }
        
        // Handle plain language codes: en, fr, de, etc.
        return strtolower(substr($locale, 0, 2));
    }
    
    /**
     * AJAX handler for generating image prompts
     */
    public function ajax_generate_image_prompt() {
        check_ajax_referer('ai_assistant_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to perform this action.', 'ai-assistant'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $context = isset($_POST['context']) ? sanitize_textarea_field($_POST['context']) : '';
        $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : 'photorealistic';
        
        // Get post content and title for context
        $post_title = '';
        $post_content = '';
        
        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                $post_title = $post->post_title;
                $post_content = wp_strip_all_tags($post->post_content);
                $post_content = wp_trim_words($post_content, 100); // Increased to 100 words for better context
            }
        }
        
        // Style-specific prompting guidance
        $style_guidance = array(
            'photorealistic' => 'professional photography style, realistic lighting, high-quality composition',
            'illustration' => 'digital illustration style, artistic interpretation, clean vector-like design',
            'digital-art' => 'digital art style, creative artistic expression, modern design elements',
            'minimalist' => 'minimalist design, clean lines, simple composition, elegant and uncluttered',
            'professional' => 'professional business style, corporate aesthetic, clean and polished',
            'abstract' => 'abstract artistic interpretation, creative visual metaphors, artistic composition'
        );
        
        $selected_style_guidance = isset($style_guidance[$style]) ? $style_guidance[$style] : $style_guidance['photorealistic'];
        
        // Build comprehensive prompt for AI
        $ai_prompt = "Create a detailed, professional image prompt for generating a featured image with {$selected_style_guidance}. The image should visually represent the following content:\n\n";
        
        // Enhanced logic: Use both title and content together for richer context
        $content_context = '';
        
        if (!empty($post_title)) {
            $content_context .= "**Title:** {$post_title}\n";
        }
        
        if (!empty($post_content) && strlen($post_content) > 30) {
            $content_context .= "**Content Summary:** {$post_content}\n";
        }
        
        // Add user-provided context if available
        if (!empty($context)) {
            $content_context .= "**Additional Context:** {$context}\n";
        }
        
        // Analyze the combination for better image generation
        if (!empty($post_title) && !empty($post_content) && strlen($post_content) > 50) {
            $ai_prompt .= $content_context;
            $ai_prompt .= "\n**INSTRUCTION:** Analyze both the title and content summary above to create an image that represents the main theme and key concepts. The title provides the topic focus, while the content provides additional context and details to make the image more specific and relevant.\n";
        } elseif (!empty($post_title) && (empty($post_content) || strlen($post_content) <= 50)) {
            // Title exists but little content
            $ai_prompt .= $content_context;
            $ai_prompt .= "\n**INSTRUCTION:** Focus primarily on the title, but use any available content hints to add relevance and specificity to the image.\n";
        } elseif (empty($post_title) && !empty($post_content) && strlen($post_content) > 100) {
            // No title but substantial content
            $ai_prompt .= $content_context;
            $ai_prompt .= "\n**INSTRUCTION:** Since no title is provided, extract the main theme and key concepts from the content summary to create a representative image that captures the essence of the article.\n";
        } elseif (!empty($context)) {
            // Primarily user context
            $ai_prompt .= $content_context;
            $ai_prompt .= "\n**INSTRUCTION:** Focus on the additional context provided by the user, supplemented by any available title or content information.\n";
        } else {
            // Minimal content scenario
            $ai_prompt = "Based on the limited content provided, create a professional, generic image prompt suitable for a blog post featured image. Use {$selected_style_guidance}. The prompt should describe a visually appealing image that could work as a header image for web content.\n\n";
            if (!empty($content_context)) {
                $ai_prompt .= $content_context;
            }
            $ai_prompt .= "\n**INSTRUCTION:** Create a versatile, professional image that would work well as a blog header, drawing from any available content hints.\n";
        }
        
        $ai_prompt .= "\n**Generation Instructions:**\n";
        $ai_prompt .= "- Generate a concise, descriptive prompt (2-3 sentences maximum)\n";
        $ai_prompt .= "- Focus on visual elements: composition, colors, mood, and style\n";
        $ai_prompt .= "- Combine information from title AND content for comprehensive understanding\n";
        $ai_prompt .= "- Ensure the image would work well as a blog post header\n";
        $ai_prompt .= "- Style requirement: {$selected_style_guidance}\n";
        $ai_prompt .= "- Avoid text or typography in the image\n";
        $ai_prompt .= "- Create a prompt that represents the core message and themes from both title and content\n";
        $ai_prompt .= "- If title and content seem unrelated, find the connecting theme or focus on the stronger indicator\n\n";
        $ai_prompt .= "Return only the image generation prompt, nothing else.";
        
        // Initialize AI service if not already done
        if (!isset($this->ai_service)) {
            $this->ai_service = new AI_Assistant_AI_Service();
        }
        
        $result = $this->ai_service->make_api_request_public($ai_prompt);
        
        if ($result['success']) {
            // Store prompt generation in history with enhanced context
            $input_context = '';
            if (!empty($post_title)) $input_context .= "Title: " . $post_title;
            if (!empty($post_content) && strlen($post_content) > 30) {
                $content_preview = wp_trim_words($post_content, 15);
                $input_context .= (!empty($input_context) ? " | " : "") . "Content: " . $content_preview;
            }
            if (!empty($context)) $input_context .= (!empty($input_context) ? " | " : "") . "User Context: " . $context;
            if (!empty($style)) $input_context .= (!empty($input_context) ? " | " : "") . "Style: " . $style;
            if (empty($input_context)) $input_context = "Auto-generate prompt from available content";
            
            $this->store_content_generation_history('image-prompt', $input_context, trim($result['content']), $post_id, 'prompt-generator');
            
            wp_send_json_success(array(
                'prompt' => trim($result['content'])
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error']
            ));
        }
    }
    
    /**
     * AJAX handler for generating images
     */
    public function ajax_generate_image() {
        check_ajax_referer('ai_assistant_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to perform this action.', 'ai-assistant'));
        }
        
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : 'photorealistic';
        $size = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : '1024x1024';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
        
        if (empty($prompt)) {
            wp_send_json_error(array(
                'message' => __('Please provide an image description.', 'ai-assistant')
            ));
        }
        
        // Enhance prompt with style
        $style_modifiers = array(
            'photorealistic' => 'photorealistic, high quality, professional photography',
            'illustration' => 'digital illustration, artistic, clean lines',
            'digital-art' => 'digital art, creative, modern design',
            'minimalist' => 'minimalist design, clean, simple, elegant',
            'professional' => 'professional, business style, corporate, clean',
            'abstract' => 'abstract art, creative, artistic interpretation'
        );
        
        $enhanced_prompt = $prompt;
        if (isset($style_modifiers[$style])) {
            $enhanced_prompt .= ', ' . $style_modifiers[$style];
        }
        
        // Initialize AI service if not already done
        if (!isset($this->ai_service)) {
            $this->ai_service = new AI_Assistant_AI_Service();
        }
        
        // Check if AI service has image generation capability
        if (!method_exists($this->ai_service, 'generate_image')) {
            wp_send_json_error(array(
                'message' => __('Image generation is not available. Please check your AI service configuration.', 'ai-assistant')
            ));
        }
        
        $result = $this->ai_service->generate_image($enhanced_prompt, $size, $model);
        
        if ($result['success']) {
            // Store image generation in history
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : null;
            $image_details = sprintf("Generated %s image (%s) - %s", $style, $size, $result['image_url']);
            $this->store_content_generation_history('image-generation', $enhanced_prompt, $image_details, $post_id, $model);
            
            wp_send_json_success(array(
                'image_url' => $result['image_url'],
                'prompt' => $enhanced_prompt,
                'size' => $size,
                'model' => $model
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error']
            ));
        }
    }
    
    /**
     * AJAX handler for setting featured image
     */
    public function ajax_set_featured_image() {
        check_ajax_referer('ai_assistant_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to perform this action.', 'ai-assistant'));
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        
        if (!$post_id || !$image_url) {
            wp_send_json_error(array(
                'message' => __('Missing post ID or image URL.', 'ai-assistant')
            ));
        }
        
        // Download and save the image
        $upload_result = $this->download_and_save_image($image_url, $post_id, $prompt);
        
        if (is_wp_error($upload_result)) {
            wp_send_json_error(array(
                'message' => $upload_result->get_error_message()
            ));
        }
        
        // Set as featured image
        $attachment_id = $upload_result;
        $set_result = set_post_thumbnail($post_id, $attachment_id);
        
        if ($set_result) {
            // Store featured image setting in history
            $featured_details = sprintf("Set featured image (ID: %d) from generated image - %s", $attachment_id, $image_url);
            $this->store_content_generation_history('featured-image-set', $prompt, $featured_details, $post_id, 'featured-image');
            
            wp_send_json_success(array(
                'message' => __('Featured image set successfully!', 'ai-assistant'),
                'attachment_id' => $attachment_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to set featured image.', 'ai-assistant')
            ));
        }
    }
    
    /**
     * Download and save image to WordPress media library
     */
    private function download_and_save_image($image_url, $post_id, $prompt = '') {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download the image
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        // Prepare file array for wp_handle_sideload
        $file_array = array(
            'name' => 'ai-generated-featured-image-' . time() . '.png',
            'tmp_name' => $temp_file
        );
        
        // Move the file to WordPress uploads directory
        $sideload_result = wp_handle_sideload($file_array, array('test_form' => false));
        
        // Clean up temp file
        @unlink($temp_file);
        
        if (isset($sideload_result['error'])) {
            return new WP_Error('upload_error', $sideload_result['error']);
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => $sideload_result['type'],
            'post_title' => !empty($prompt) ? wp_trim_words($prompt, 8) : 'AI Generated Featured Image',
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $post_id
        );
        
        $attachment_id = wp_insert_attachment($attachment, $sideload_result['file'], $post_id);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Generate attachment metadata
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $sideload_result['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        return $attachment_id;
    }
    
    /**
     * AJAX handler for getting available image models
     */
    public function ajax_get_image_models() {
        check_ajax_referer('ai_assistant_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to perform this action.', 'ai-assistant'));
        }
        
        // Initialize AI service if not already done
        if (!isset($this->ai_service)) {
            $this->ai_service = new AI_Assistant_AI_Service();
        }
        
        // Check if AI service has the get_available_image_models method
        if (method_exists($this->ai_service, 'get_available_image_models')) {
            $image_models = $this->ai_service->get_available_image_models();
        } else {
            // Fallback to regular models
            $image_models = $this->ai_service->get_available_models();
        }
        
        if (!empty($image_models)) {
            wp_send_json_success($image_models);
        } else {
            wp_send_json_error(array(
                'message' => __('No image generation models available. Please configure at least one AI provider.', 'ai-assistant')
            ));
        }
    }
}

/**
 * Initialize the plugin
 */
function ai_assistant_init() {
    AIAssistant::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'ai_assistant_init');
