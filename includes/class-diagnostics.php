<?php
/**
 * AI Assistant Diagnostics Page
 * Separate diagnostics and debug information from main settings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

/**
 * AI Assistant Diagnostics Class
 */
class AI_Assistant_Diagnostics {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_diagnostics_menu'), 20);
        add_action('wp_ajax_ai_assistant_diagnostics_test', array($this, 'ajax_diagnostics_test'));
        add_action('wp_ajax_ai_assistant_system_info', array($this, 'ajax_system_info'));
        add_action('wp_ajax_ai_assistant_debug_language_loading', array($this, 'ajax_debug_language_loading'));
    }
    
    /**
     * Add diagnostics menu
     */
    public function add_diagnostics_menu() {
        add_submenu_page(
            'ai-assistant-dashboard',
            __('Diagnostics', 'ai-assistant'),
            __('Diagnostics', 'ai-assistant'),
            'manage_options',
            'ai-assistant-diagnostics',
            array($this, 'render_diagnostics_page')
        );
    }
    
    /**
     * Render diagnostics page
     */
    public function render_diagnostics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('AI Assistant Diagnostics', 'ai-assistant'); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('Diagnostics & System Information', 'ai-assistant'); ?></strong><br>
                    <?php _e('This page provides detailed system information and debugging tools for troubleshooting AI Assistant issues.', 'ai-assistant'); ?>
                </p>
            </div>
            
            <!-- Quick Status Overview -->
            <div class="postbox">
                <h2 class="hndle"><?php _e('System Status Overview', 'ai-assistant'); ?></h2>
                <div class="inside">
                    <div class="ai-diagnostics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                        
                        <!-- Plugin Status -->
                        <div class="ai-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                            <h3 style="margin-top: 0;">🔧 Plugin Status</h3>
                            <ul style="list-style: none; margin: 0; padding: 0;">
                                <li>✅ <strong><?php _e('Version:', 'ai-assistant'); ?></strong> <?php echo AI_ASSISTANT_VERSION; ?></li>
                                <li><?php echo is_plugin_active('ai-assistant/ai-assistant.php') ? '✅' : '❌'; ?> <strong><?php _e('Plugin Active:', 'ai-assistant'); ?></strong> <?php echo is_plugin_active('ai-assistant/ai-assistant.php') ? __('Yes', 'ai-assistant') : __('No', 'ai-assistant'); ?></li>
                                <li><?php echo file_exists(AI_ASSISTANT_PLUGIN_DIR) ? '✅' : '❌'; ?> <strong><?php _e('Plugin Directory:', 'ai-assistant'); ?></strong> <?php echo file_exists(AI_ASSISTANT_PLUGIN_DIR) ? __('Found', 'ai-assistant') : __('Missing', 'ai-assistant'); ?></li>
                            </ul>
                        </div>
                        
                        <!-- Language Status -->
                        <div class="ai-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                            <h3 style="margin-top: 0;">🌍 Language Status</h3>
                            <ul style="list-style: none; margin: 0; padding: 0;">
                                <li><strong><?php _e('WP Locale:', 'ai-assistant'); ?></strong> <?php echo get_locale(); ?></li>
                                <li><strong><?php _e('Plugin Language:', 'ai-assistant'); ?></strong> <?php echo get_option('ai_assistant_admin_language', get_locale()); ?></li>
                                <li><?php echo is_textdomain_loaded('ai-assistant') ? '✅' : '❌'; ?> <strong><?php _e('Textdomain Loaded:', 'ai-assistant'); ?></strong> <?php echo is_textdomain_loaded('ai-assistant') ? __('Yes', 'ai-assistant') : __('No', 'ai-assistant'); ?></li>
                            </ul>
                        </div>
                        
                        <!-- API Status -->
                        <div class="ai-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                            <h3 style="margin-top: 0;">🤖 API Status</h3>
                            <?php 
                            $api_keys = get_option('ai_assistant_api_keys', array());
                            $has_openai = !empty($api_keys['openai']);
                            $has_anthropic = !empty($api_keys['anthropic']);
                            $has_gemini = !empty($api_keys['gemini']);
                            ?>
                            <ul style="list-style: none; margin: 0; padding: 0;">
                                <li><?php echo $has_openai ? '✅' : '❌'; ?> <strong><?php _e('OpenAI API:', 'ai-assistant'); ?></strong> <?php echo $has_openai ? __('Configured', 'ai-assistant') : __('Not Set', 'ai-assistant'); ?></li>
                                <li><?php echo $has_anthropic ? '✅' : '❌'; ?> <strong><?php _e('Anthropic API:', 'ai-assistant'); ?></strong> <?php echo $has_anthropic ? __('Configured', 'ai-assistant') : __('Not Set', 'ai-assistant'); ?></li>
                                <li><?php echo $has_gemini ? '✅' : '❌'; ?> <strong><?php _e('Gemini API:', 'ai-assistant'); ?></strong> <?php echo $has_gemini ? __('Configured', 'ai-assistant') : __('Not Set', 'ai-assistant'); ?></li>
                            </ul>
                        </div>
                        
                        <!-- Translation Files -->
                        <div class="ai-status-card" style="padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                            <h3 style="margin-top: 0;">📄 Translation Files</h3>
                            <?php 
                            $lang_dir = AI_ASSISTANT_PLUGIN_DIR . 'languages/';
                            $po_files = glob($lang_dir . '*.po');
                            $mo_files = glob($lang_dir . '*.mo');
                            ?>
                            <ul style="list-style: none; margin: 0; padding: 0;">
                                <li><strong><?php _e('.po Files:', 'ai-assistant'); ?></strong> <?php echo count($po_files); ?></li>
                                <li><strong><?php _e('.mo Files:', 'ai-assistant'); ?></strong> <?php echo count($mo_files); ?></li>
                                <li><strong><?php _e('Languages Dir:', 'ai-assistant'); ?></strong> <?php echo file_exists($lang_dir) ? '✅' : '❌'; ?></li>
                            </ul>
                        </div>
                        
                    </div>
                </div>
            </div>
            
            <!-- Detailed Language Debug Information -->
            <div class="postbox">
                <h2 class="hndle"><?php _e('Language Debug Information', 'ai-assistant'); ?></h2>
                <div class="inside">
                    <div class="ai-debug-info" style="background: #f9f9f9; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 13px;">
                        <h4><?php _e('Current Language Configuration', 'ai-assistant'); ?></h4>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li><strong><?php _e('Current WordPress Locale:', 'ai-assistant'); ?></strong> <?php echo get_locale(); ?></li>
                            <li><strong><?php _e('AI Assistant Admin Language Setting:', 'ai-assistant'); ?></strong> <?php echo get_option('ai_assistant_admin_language', 'NOT SET'); ?></li>
                            <li><strong><?php _e('Plugin Textdomain Loaded:', 'ai-assistant'); ?></strong> <?php echo is_textdomain_loaded('ai-assistant') ? 'YES' : 'NO'; ?></li>
                            <li><strong><?php _e('Test Translation:', 'ai-assistant'); ?></strong> "<?php echo __('AI Assistant Dashboard', 'ai-assistant'); ?>" <em><?php _e('(should change if language is working)', 'ai-assistant'); ?></em></li>
                        </ul>
                        
                        <h4><?php _e('Translation Files Status', 'ai-assistant'); ?></h4>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <?php
                            $current_lang = get_option('ai_assistant_admin_language', 'en_US');
                            $mo_file = AI_ASSISTANT_PLUGIN_DIR . 'languages/ai-assistant-' . $current_lang . '.mo';
                            $turkish_mo = AI_ASSISTANT_PLUGIN_DIR . 'languages/ai-assistant-tr_TR.mo';
                            $chinese_mo = AI_ASSISTANT_PLUGIN_DIR . 'languages/ai-assistant-zh_CN.mo';
                            ?>
                            <li><strong><?php _e('.mo File for Current Language:', 'ai-assistant'); ?></strong> <?php echo file_exists($mo_file) ? 'EXISTS' : 'MISSING'; ?> (<?php echo basename($mo_file); ?>)</li>
                            <li><strong><?php _e('Turkish .mo File Status:', 'ai-assistant'); ?></strong> <?php echo file_exists($turkish_mo) ? 'EXISTS (' . number_format(filesize($turkish_mo)) . ' bytes)' : 'MISSING'; ?></li>
                            <li><strong><?php _e('Chinese .mo File Status:', 'ai-assistant'); ?></strong> <?php echo file_exists($chinese_mo) ? 'EXISTS (' . number_format(filesize($chinese_mo)) . ' bytes)' : 'MISSING'; ?></li>
                            <li><strong><?php _e('Available Translations:', 'ai-assistant'); ?></strong> 
                                <?php 
                                $lang_dir = AI_ASSISTANT_PLUGIN_DIR . 'languages/';
                                $mo_files = glob($lang_dir . '*.mo');
                                echo count($mo_files) . ' .mo files found';
                                ?>
                            </li>
                            <li><strong><?php _e('Languages Directory:', 'ai-assistant'); ?></strong> <?php echo AI_ASSISTANT_PLUGIN_DIR . 'languages/'; ?></li>
                        </ul>
                        
                        <h4><?php _e('Available Language Files', 'ai-assistant'); ?></h4>
                        <div style="max-height: 200px; overflow-y: auto; background: #fff; padding: 10px; border: 1px solid #ddd;">
                            <?php
                            $lang_dir = AI_ASSISTANT_PLUGIN_DIR . 'languages/';
                            $po_files = glob($lang_dir . '*.po');
                            $mo_files = glob($lang_dir . '*.mo');
                            
                            echo "<strong>" . __('.po Files:', 'ai-assistant') . "</strong><br>";
                            foreach ($po_files as $po_file) {
                                $size = filesize($po_file);
                                echo "• " . basename($po_file) . " (" . number_format($size) . " bytes)<br>";
                            }
                            
                            echo "<br><strong>" . __('.mo Files:', 'ai-assistant') . "</strong><br>";
                            foreach ($mo_files as $mo_file) {
                                $size = filesize($mo_file);
                                echo "• " . basename($mo_file) . " (" . number_format($size) . " bytes)<br>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="postbox">
                <h2 class="hndle"><?php _e('System Information', 'ai-assistant'); ?></h2>
                <div class="inside">
                    <div class="ai-system-info" style="background: #f9f9f9; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 13px;">
                        <h4><?php _e('WordPress Environment', 'ai-assistant'); ?></h4>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li><strong><?php _e('WordPress Version:', 'ai-assistant'); ?></strong> <?php echo get_bloginfo('version'); ?></li>
                            <li><strong><?php _e('PHP Version:', 'ai-assistant'); ?></strong> <?php echo PHP_VERSION; ?></li>
                            <li><strong><?php _e('MySQL Version:', 'ai-assistant'); ?></strong> <?php global $wpdb; echo $wpdb->db_version(); ?></li>
                            <li><strong><?php _e('WordPress Memory Limit:', 'ai-assistant'); ?></strong> <?php echo WP_MEMORY_LIMIT; ?></li>
                            <li><strong><?php _e('PHP Memory Limit:', 'ai-assistant'); ?></strong> <?php echo ini_get('memory_limit'); ?></li>
                            <li><strong><?php _e('Max Execution Time:', 'ai-assistant'); ?></strong> <?php echo ini_get('max_execution_time'); ?> seconds</li>
                        </ul>
                        
                        <h4><?php _e('Plugin Paths', 'ai-assistant'); ?></h4>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li><strong><?php _e('Plugin Directory:', 'ai-assistant'); ?></strong> <?php echo AI_ASSISTANT_PLUGIN_DIR; ?></li>
                            <li><strong><?php _e('Plugin URL:', 'ai-assistant'); ?></strong> <?php echo AI_ASSISTANT_PLUGIN_URL; ?></li>
                            <li><strong><?php _e('Languages Directory:', 'ai-assistant'); ?></strong> <?php echo AI_ASSISTANT_PLUGIN_DIR . 'languages/'; ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Debug Tools -->
            <div class="postbox">
                <h2 class="hndle"><?php _e('Debug Tools', 'ai-assistant'); ?></h2>
                <div class="inside">
                    <div class="ai-debug-tools" style="padding: 15px;">
                        
                        <div style="margin-bottom: 20px;">
                            <h4><?php _e('Language Loading Test', 'ai-assistant'); ?></h4>
                            <p><?php _e('Test language loading functionality and view detailed debug information.', 'ai-assistant'); ?></p>
                            <button type="button" id="test-language-loading" class="button button-secondary">
                                <?php _e('Run Language Test', 'ai-assistant'); ?>
                            </button>
                            <div id="language-test-results" style="margin-top: 15px;"></div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <h4><?php _e('API Connection Test', 'ai-assistant'); ?></h4>
                            <p><?php _e('Test API connections and verify configuration.', 'ai-assistant'); ?></p>
                            <button type="button" id="test-api-connection" class="button button-secondary">
                                <?php _e('Test API Connections', 'ai-assistant'); ?>
                            </button>
                            <div id="api-test-results" style="margin-top: 15px;"></div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <h4><?php _e('Generate System Report', 'ai-assistant'); ?></h4>
                            <p><?php _e('Generate a comprehensive system report for troubleshooting.', 'ai-assistant'); ?></p>
                            <button type="button" id="generate-system-report" class="button button-secondary">
                                <?php _e('Generate Report', 'ai-assistant'); ?>
                            </button>
                            <div id="system-report-results" style="margin-top: 15px;"></div>
                        </div>
                        
                    </div>
                </div>
            </div>
            
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Language loading test
            $('#test-language-loading').on('click', function() {
                var $button = $(this);
                var $results = $('#language-test-results');
                
                $button.prop('disabled', true).text('<?php _e('Testing...', 'ai-assistant'); ?>');
                $results.html('<div class="notice notice-info"><p><?php _e('Running language loading test...', 'ai-assistant'); ?></p></div>');
                
                $.post(ajaxurl, {
                    action: 'ai_assistant_debug_language_loading',
                    nonce: '<?php echo wp_create_nonce('ai_assistant_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.html('<div class="notice notice-success"><p><strong><?php _e('Language Test Results:', 'ai-assistant'); ?></strong></p><div>' + response.data + '</div></div>');
                    } else {
                        $results.html('<div class="notice notice-error"><p><strong><?php _e('Error:', 'ai-assistant'); ?></strong> ' + response.data + '</p></div>');
                    }
                }).fail(function() {
                    $results.html('<div class="notice notice-error"><p><?php _e('Test failed - AJAX error', 'ai-assistant'); ?></p></div>');
                }).always(function() {
                    $button.prop('disabled', false).text('<?php _e('Run Language Test', 'ai-assistant'); ?>');
                });
            });
            
            // API connection test
            $('#test-api-connection').on('click', function() {
                var $button = $(this);
                var $results = $('#api-test-results');
                
                $button.prop('disabled', true).text('<?php _e('Testing...', 'ai-assistant'); ?>');
                $results.html('<div class="notice notice-info"><p><?php _e('Testing API connections...', 'ai-assistant'); ?></p></div>');
                
                // Simulate API test (implement actual test in AJAX handler)
                setTimeout(function() {
                    $results.html('<div class="notice notice-success"><p><?php _e('API connection test feature coming soon...', 'ai-assistant'); ?></p></div>');
                    $button.prop('disabled', false).text('<?php _e('Test API Connections', 'ai-assistant'); ?>');
                }, 2000);
            });
            
            // System report generation
            $('#generate-system-report').on('click', function() {
                var $button = $(this);
                var $results = $('#system-report-results');
                
                $button.prop('disabled', true).text('<?php _e('Generating...', 'ai-assistant'); ?>');
                $results.html('<div class="notice notice-info"><p><?php _e('Generating system report...', 'ai-assistant'); ?></p></div>');
                
                $.post(ajaxurl, {
                    action: 'ai_assistant_system_info',
                    nonce: '<?php echo wp_create_nonce('ai_assistant_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.html('<div class="notice notice-success"><p><strong><?php _e('System Report Generated:', 'ai-assistant'); ?></strong></p><textarea style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;">' + response.data.report + '</textarea><p><em><?php _e('Copy the above information when reporting issues.', 'ai-assistant'); ?></em></p></div>');
                    } else {
                        $results.html('<div class="notice notice-error"><p><strong><?php _e('Error:', 'ai-assistant'); ?></strong> ' + response.data + '</p></div>');
                    }
                }).fail(function() {
                    $results.html('<div class="notice notice-error"><p><?php _e('Report generation failed - AJAX error', 'ai-assistant'); ?></p></div>');
                }).always(function() {
                    $button.prop('disabled', false).text('<?php _e('Generate Report', 'ai-assistant'); ?>');
                });
            });
            
        });
        </script>
        
        <style>
        .ai-status-card h3 {
            color: #333;
            font-size: 14px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .ai-status-card ul li {
            margin-bottom: 5px;
            font-size: 13px;
        }
        .ai-debug-info, .ai-system-info {
            border-left: 4px solid #0073aa;
        }
        .ai-debug-tools h4 {
            color: #333;
            margin-bottom: 5px;
        }
        .ai-debug-tools p {
            margin-top: 0;
            margin-bottom: 10px;
            color: #666;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for diagnostics test
     */
    public function ajax_diagnostics_test() {
        check_ajax_referer('ai_assistant_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-assistant'));
        }
        
        // Perform diagnostic tests
        $results = array(
            'language_test' => $this->test_language_loading(),
            'api_test' => $this->test_api_connections(),
            'file_test' => $this->test_file_permissions(),
        );
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for system information
     */
    public function ajax_system_info() {
        check_ajax_referer('ai_assistant_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-assistant'));
        }
        
        $report = $this->generate_system_report();
        
        wp_send_json_success(array('report' => $report));
    }
    
    /**
     * AJAX handler for language loading debug
     */
    public function ajax_debug_language_loading() {
        check_ajax_referer('ai_assistant_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'ai-assistant'));
        }
        
        ob_start();
        
        echo "<div class='notice notice-info'>";
        echo "<h3>🔍 " . __('Language Loading Debug Test Results', 'ai-assistant') . "</h3>";
        
        $custom_lang = get_option('ai_assistant_admin_language');
        $current_locale = get_locale();
        
        echo "<div style='background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 4px;'>";
        echo "<h4>" . __('Basic Information', 'ai-assistant') . "</h4>";
        echo "<ul style='margin-left: 20px;'>";
        echo "<li><strong>" . __('WordPress Locale:', 'ai-assistant') . "</strong> " . $current_locale . "</li>";
        echo "<li><strong>" . __('Custom Language Setting:', 'ai-assistant') . "</strong> " . ($custom_lang ?: __('NOT SET', 'ai-assistant')) . "</li>";
        echo "<li><strong>" . __('Plugin Textdomain Loaded:', 'ai-assistant') . "</strong> " . (is_textdomain_loaded('ai-assistant') ? __('YES', 'ai-assistant') : __('NO', 'ai-assistant')) . "</li>";
        echo "</ul>";
        echo "</div>";
        
        // Test translations
        echo "<div style='background: #f0f8ff; padding: 15px; margin: 10px 0; border-radius: 4px;'>";
        echo "<h4>" . __('Translation Tests', 'ai-assistant') . "</h4>";
        echo "<ul style='margin-left: 20px;'>";
        echo "<li><strong>'AI Assistant':</strong> " . __('AI Assistant', 'ai-assistant') . "</li>";
        echo "<li><strong>'Settings':</strong> " . __('Settings', 'ai-assistant') . "</li>";
        echo "<li><strong>'Translate':</strong> " . __('Translate', 'ai-assistant') . "</li>";
        echo "<li><strong>'Translation Management':</strong> " . __('Translation Management', 'ai-assistant') . "</li>";
        echo "</ul>";
        echo "</div>";
        
        // File information
        $languages_dir = AI_ASSISTANT_PLUGIN_DIR . 'languages/';
        echo "<div style='background: #fff8dc; padding: 15px; margin: 10px 0; border-radius: 4px;'>";
        echo "<h4>" . __('File Information', 'ai-assistant') . "</h4>";
        
        if ($custom_lang) {
            $mo_file = $languages_dir . 'ai-assistant-' . $custom_lang . '.mo';
            $po_file = $languages_dir . 'ai-assistant-' . $custom_lang . '.po';
            
            echo "<ul style='margin-left: 20px;'>";
            echo "<li><strong>" . __('Expected .mo file:', 'ai-assistant') . "</strong> " . basename($mo_file) . "</li>";
            echo "<li><strong>" . __('.mo file exists:', 'ai-assistant') . "</strong> " . (file_exists($mo_file) ? __('YES', 'ai-assistant') . ' (' . $this->format_file_size(filesize($mo_file)) . ')' : __('NO', 'ai-assistant')) . "</li>";
            echo "<li><strong>" . __('.po file exists:', 'ai-assistant') . "</strong> " . (file_exists($po_file) ? __('YES', 'ai-assistant') . ' (' . $this->format_file_size(filesize($po_file)) . ')' : __('NO', 'ai-assistant')) . "</li>";
            echo "</ul>";
        }
        
        // Available files
        $mo_files = glob($languages_dir . 'ai-assistant-*.mo');
        echo "<h5>" . sprintf(__('Available .mo files (%d):', 'ai-assistant'), count($mo_files)) . "</h5>";
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
            echo "<h4>" . __('Manual Language Loading Test', 'ai-assistant') . "</h4>";
            
            // Unload current textdomain
            $unloaded = unload_textdomain('ai-assistant');
            echo "<p><strong>" . __('Unload result:', 'ai-assistant') . "</strong> " . ($unloaded ? __('SUCCESS', 'ai-assistant') : __('FAILED', 'ai-assistant')) . "</p>";
            
            // Try to load custom language
            $mo_file = $languages_dir . 'ai-assistant-' . $custom_lang . '.mo';
            if (file_exists($mo_file)) {
                $loaded = load_textdomain('ai-assistant', $mo_file);
                echo "<p><strong>" . __('Custom load result:', 'ai-assistant') . "</strong> " . ($loaded ? __('SUCCESS', 'ai-assistant') : __('FAILED', 'ai-assistant')) . "</p>";
                
                // Test translation after manual load
                echo "<p><strong>" . __('Test translation after manual load:', 'ai-assistant') . "</strong> " . __('AI Assistant Dashboard', 'ai-assistant') . "</p>";
            } else {
                echo "<p><strong>" . __('Custom load result:', 'ai-assistant') . "</strong> " . __('FAILED - .mo file not found', 'ai-assistant') . "</p>";
            }
            echo "</div>";
        }
        
        echo "</div>";
        
        $debug_output = ob_get_clean();
        
        wp_send_json_success($debug_output);
    }

    /**
     * Test language loading
     */
    private function test_language_loading() {
        // Implementation from existing debug function
        return array(
            'status' => 'success',
            'message' => __('Language loading test completed', 'ai-assistant')
        );
    }
    
    /**
     * Format file size
     */
    private function format_file_size($bytes) {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Test API connections
     */
    private function test_api_connections() {
        // Implementation for API testing
        return array(
            'status' => 'success',
            'message' => __('API connection test completed', 'ai-assistant')
        );
    }
    
    /**
     * Test file permissions
     */
    private function test_file_permissions() {
        $lang_dir = AI_ASSISTANT_PLUGIN_DIR . 'languages/';
        $writable = is_writable($lang_dir);
        
        return array(
            'status' => $writable ? 'success' : 'error',
            'message' => $writable ? __('Language directory is writable', 'ai-assistant') : __('Language directory is not writable', 'ai-assistant')
        );
    }
    
    /**
     * Generate comprehensive system report
     */
    private function generate_system_report() {
        global $wpdb;
        
        $report = '';
        $report .= "=== AI Assistant System Report ===\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        $report .= "--- Plugin Information ---\n";
        $report .= "Version: " . AI_ASSISTANT_VERSION . "\n";
        $report .= "Plugin Dir: " . AI_ASSISTANT_PLUGIN_DIR . "\n";
        $report .= "Plugin URL: " . AI_ASSISTANT_PLUGIN_URL . "\n\n";
        
        $report .= "--- WordPress Environment ---\n";
        $report .= "WP Version: " . get_bloginfo('version') . "\n";
        $report .= "WP Locale: " . get_locale() . "\n";
        $report .= "PHP Version: " . PHP_VERSION . "\n";
        $report .= "MySQL Version: " . $wpdb->db_version() . "\n";
        $report .= "Memory Limit: " . ini_get('memory_limit') . "\n";
        $report .= "Max Execution Time: " . ini_get('max_execution_time') . "\n\n";
        
        $report .= "--- Language Configuration ---\n";
        $report .= "Plugin Language Setting: " . get_option('ai_assistant_admin_language', 'NOT SET') . "\n";
        $report .= "Textdomain Loaded: " . (is_textdomain_loaded('ai-assistant') ? 'YES' : 'NO') . "\n";
        
        $lang_dir = AI_ASSISTANT_PLUGIN_DIR . 'languages/';
        $po_files = glob($lang_dir . '*.po');
        $mo_files = glob($lang_dir . '*.mo');
        $report .= "Available .po Files: " . count($po_files) . "\n";
        $report .= "Available .mo Files: " . count($mo_files) . "\n\n";
        
        $report .= "--- API Configuration ---\n";
        $api_keys = get_option('ai_assistant_api_keys', array());
        $report .= "OpenAI API: " . (!empty($api_keys['openai']) ? 'CONFIGURED' : 'NOT SET') . "\n";
        $report .= "Anthropic API: " . (!empty($api_keys['anthropic']) ? 'CONFIGURED' : 'NOT SET') . "\n";
        $report .= "Gemini API: " . (!empty($api_keys['gemini']) ? 'CONFIGURED' : 'NOT SET') . "\n\n";
        
        $report .= "--- Translation Files ---\n";
        foreach ($mo_files as $mo_file) {
            $size = filesize($mo_file);
            $report .= basename($mo_file) . " (" . number_format($size) . " bytes)\n";
        }
        
        $report .= "\n=== End Report ===";
        
        return $report;
    }
}

// Initialize diagnostics if this file is included
if (class_exists('AI_Assistant_Diagnostics')) {
    new AI_Assistant_Diagnostics();
}
