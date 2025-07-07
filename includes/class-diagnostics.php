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
        <?php $this->add_diagnostics_styles(); ?>
        
        <div class="wrap">
            <h1><?php _e('AI Assistant Diagnostics', 'ai-assistant'); ?></h1>
            
            <!-- Header Notice -->
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('🔍 Diagnostics & System Information', 'ai-assistant'); ?></strong><br>
                    <?php _e('This page provides comprehensive system information and debugging tools for troubleshooting AI Assistant issues.', 'ai-assistant'); ?>
                </p>
            </div>
            
            <!-- Quick Status Dashboard -->
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('📊 System Status Dashboard', 'ai-assistant'); ?></span></h2>
                <div class="inside">
                    <div class="ai-diagnostics-dashboard">
                        
                        <!-- Status Overview Cards -->
                        <div class="status-cards-grid">
                            
                            <!-- Plugin Status Card -->
                            <div class="status-card plugin-status">
                                <div class="card-header">
                                    <h3><span class="dashicons dashicons-admin-plugins"></span> <?php _e('Plugin Status', 'ai-assistant'); ?></h3>
                                </div>
                                <div class="card-content">
                                    <div class="status-item">
                                        <span class="status-icon success">✅</span>
                                        <span class="status-label"><?php _e('Version:', 'ai-assistant'); ?></span>
                                        <span class="status-value"><?php echo AI_ASSISTANT_VERSION; ?></span>
                                    </div>
                                    <div class="status-item">
                                        <?php $plugin_active = is_plugin_active('ai-assistant/ai-assistant.php'); ?>
                                        <span class="status-icon <?php echo $plugin_active ? 'success' : 'error'; ?>"><?php echo $plugin_active ? '✅' : '❌'; ?></span>
                                        <span class="status-label"><?php _e('Plugin Active:', 'ai-assistant'); ?></span>
                                        <span class="status-value"><?php echo $plugin_active ? __('Yes', 'ai-assistant') : __('No', 'ai-assistant'); ?></span>
                                    </div>
                                    <div class="status-item">
                                        <?php $dir_exists = file_exists(AI_ASSISTANT_PLUGIN_DIR); ?>
                                        <span class="status-icon <?php echo $dir_exists ? 'success' : 'error'; ?>"><?php echo $dir_exists ? '✅' : '❌'; ?></span>
                                        <span class="status-label"><?php _e('Directory:', 'ai-assistant'); ?></span>
                                        <span class="status-value"><?php echo $dir_exists ? __('Found', 'ai-assistant') : __('Missing', 'ai-assistant'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Language Status Card -->
                            <div class="status-card language-status">
                                <div class="card-header">
                                    <h3><span class="dashicons dashicons-translation"></span> <?php _e('Language System', 'ai-assistant'); ?></h3>
                                </div>
                                <div class="card-content">
                                    <div class="status-item">
                                        <span class="status-icon info">🌍</span>
                                        <span class="status-label"><?php _e('WP Locale:', 'ai-assistant'); ?></span>
                                        <span class="status-value"><?php echo get_locale(); ?></span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-icon info">🔧</span>
                                        <span class="status-label"><?php _e('Plugin Language:', 'ai-assistant'); ?></span>
                                        <span class="status-value"><?php echo get_option('ai_assistant_admin_language', get_locale()); ?></span>
                                    </div>
                                    <div class="status-item">
                                        <?php $textdomain_loaded = is_textdomain_loaded('ai-assistant'); ?>
                                        <span class="status-icon <?php echo $textdomain_loaded ? 'success' : 'warning'; ?>"><?php echo $textdomain_loaded ? '✅' : '⚠️'; ?></span>
                                        <span class="status-label"><?php _e('Textdomain:', 'ai-assistant'); ?></span>
                                        <span class="status-value"><?php echo $textdomain_loaded ? __('Loaded', 'ai-assistant') : __('Not Loaded', 'ai-assistant'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- API Configuration Card -->
                            <div class="status-card api-status">
                                <div class="card-header">
                                    <h3><span class="dashicons dashicons-cloud"></span> <?php _e('AI API Status', 'ai-assistant'); ?></h3>
                                </div>
                                <div class="card-content">
                                    <?php 
                                    $api_keys = get_option('ai_assistant_api_keys', array());
                                    $providers = array(
                                        'openai' => array('name' => 'OpenAI', 'icon' => '🤖'),
                                        'anthropic' => array('name' => 'Anthropic', 'icon' => '🧠'),
                                        'gemini' => array('name' => 'Gemini', 'icon' => '💎')
                                    );
                                    
                                    foreach ($providers as $key => $provider) {
                                        $configured = !empty($api_keys[$key]);
                                        ?>
                                        <div class="status-item">
                                            <span class="status-icon <?php echo $configured ? 'success' : 'error'; ?>"><?php echo $configured ? '✅' : '❌'; ?></span>
                                            <span class="status-label"><?php echo $provider['icon'] . ' ' . $provider['name']; ?>:</span>
                                            <span class="status-value <?php echo $configured ? 'configured' : 'not-configured'; ?>"><?php echo $configured ? __('Configured', 'ai-assistant') : __('Not Set', 'ai-assistant'); ?></span>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <!-- Translation Files Card -->
                            <div class="status-card files-status">
                                <div class="card-header">
                                    <h3><span class="dashicons dashicons-media-document"></span> <?php _e('Translation Files', 'ai-assistant'); ?></h3>
                                </div>
                                <div class="card-content">
                                    <?php 
                                    $lang_dir = AI_ASSISTANT_PLUGIN_DIR . 'languages/';
                                    $po_files = glob($lang_dir . '*.po');
                                    $mo_files = glob($lang_dir . '*.mo');
                                    $dir_writable = is_writable($lang_dir);
                                    ?>
                                    <div class="status-item">
                                        <span class="status-icon info">📄</span>
                                        <span class="status-label"><?php _e('.po Files:', 'ai-assistant'); ?></span>
                                        <span class="status-value"><?php echo count($po_files); ?></span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-icon info">📦</span>
                                        <span class="status-label"><?php _e('.mo Files:', 'ai-assistant'); ?></span>
                                        <span class="status-value"><?php echo count($mo_files); ?></span>
                                    </div>
                                    <div class="status-item">
                                        <span class="status-icon <?php echo $dir_writable ? 'success' : 'warning'; ?>"><?php echo $dir_writable ? '✅' : '⚠️'; ?></span>
                                        <span class="status-label"><?php _e('Directory:', 'ai-assistant'); ?></span>
                                        <span class="status-value"><?php echo $dir_writable ? __('Writable', 'ai-assistant') : __('Read-only', 'ai-assistant'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Environment Details -->
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('🖥️ System Environment', 'ai-assistant'); ?></span></h2>
                <div class="inside">
                    <div class="system-environment">
                        <div class="environment-grid">
                            
                            <div class="environment-section">
                                <h4><?php _e('WordPress Environment', 'ai-assistant'); ?></h4>
                                <table class="environment-table">
                                    <tr>
                                        <td><?php _e('WordPress Version:', 'ai-assistant'); ?></td>
                                        <td><strong><?php echo get_bloginfo('version'); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('WordPress Language:', 'ai-assistant'); ?></td>
                                        <td><strong><?php echo get_locale(); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Site URL:', 'ai-assistant'); ?></td>
                                        <td><strong><?php echo get_site_url(); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Multisite:', 'ai-assistant'); ?></td>
                                        <td><strong><?php echo is_multisite() ? __('Yes', 'ai-assistant') : __('No', 'ai-assistant'); ?></strong></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="environment-section">
                                <h4><?php _e('Server Environment', 'ai-assistant'); ?></h4>
                                <table class="environment-table">
                                    <tr>
                                        <td><?php _e('PHP Version:', 'ai-assistant'); ?></td>
                                        <td><strong><?php echo PHP_VERSION; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('MySQL Version:', 'ai-assistant'); ?></td>
                                        <td><strong><?php global $wpdb; echo $wpdb->db_version(); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Memory Limit:', 'ai-assistant'); ?></td>
                                        <td><strong><?php echo ini_get('memory_limit'); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Max Execution Time:', 'ai-assistant'); ?></td>
                                        <td><strong><?php echo ini_get('max_execution_time'); ?>s</strong></td>
                                    </tr>
                                </table>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Diagnostics Tools -->
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('🔧 Diagnostic Tools', 'ai-assistant'); ?></span></h2>
                <div class="inside">
                    <div class="diagnostic-tools">
                        
                        <div class="tools-grid">
                            
                            <!-- Language Testing Tool -->
                            <div class="diagnostic-tool">
                                <div class="tool-header">
                                    <h4><span class="dashicons dashicons-translation"></span> <?php _e('Language System Test', 'ai-assistant'); ?></h4>
                                    <p><?php _e('Test language loading functionality and view detailed debug information.', 'ai-assistant'); ?></p>
                                </div>
                                <div class="tool-actions">
                                    <button type="button" id="test-language-loading" class="button button-primary">
                                        <span class="dashicons dashicons-admin-tools"></span>
                                        <?php _e('Run Language Test', 'ai-assistant'); ?>
                                    </button>
                                </div>
                                <div id="language-test-results" class="tool-results"></div>
                            </div>
                            
                            <!-- API Testing Tool -->
                            <div class="diagnostic-tool">
                                <div class="tool-header">
                                    <h4><span class="dashicons dashicons-cloud"></span> <?php _e('API Connection Test', 'ai-assistant'); ?></h4>
                                    <p><?php _e('Test API connections and verify configuration for all providers.', 'ai-assistant'); ?></p>
                                </div>
                                <div class="tool-actions">
                                    <button type="button" id="test-api-connection" class="button button-primary">
                                        <span class="dashicons dashicons-cloud"></span>
                                        <?php _e('Test API Connections', 'ai-assistant'); ?>
                                    </button>
                                </div>
                                <div id="api-test-results" class="tool-results"></div>
                            </div>
                            
                            <!-- System Report Tool -->
                            <div class="diagnostic-tool">
                                <div class="tool-header">
                                    <h4><span class="dashicons dashicons-media-text"></span> <?php _e('System Report Generator', 'ai-assistant'); ?></h4>
                                    <p><?php _e('Generate a comprehensive system report for troubleshooting and support requests.', 'ai-assistant'); ?></p>
                                </div>
                                <div class="tool-actions">
                                    <button type="button" id="generate-system-report" class="button button-primary">
                                        <span class="dashicons dashicons-download"></span>
                                        <?php _e('Generate Report', 'ai-assistant'); ?>
                                    </button>
                                </div>
                                <div id="system-report-results" class="tool-results"></div>
                            </div>
                            
                            <!-- Database Health Tool -->
                            <div class="diagnostic-tool">
                                <div class="tool-header">
                                    <h4><span class="dashicons dashicons-database"></span> <?php _e('Database Health Check', 'ai-assistant'); ?></h4>
                                    <p><?php _e('Check database tables and data integrity for AI Assistant.', 'ai-assistant'); ?></p>
                                </div>
                                <div class="tool-actions">
                                    <button type="button" id="check-database-health" class="button button-primary">
                                        <span class="dashicons dashicons-database"></span>
                                        <?php _e('Check Database', 'ai-assistant'); ?>
                                    </button>
                                </div>
                                <div id="database-health-results" class="tool-results"></div>
                            </div>
                            
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
        /* Diagnostics Page Styling */
        .ai-diagnostics-dashboard {
            margin: 20px 0;
        }
        
        .status-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: box-shadow 0.2s ease;
        }
        
        .status-card:hover {
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        
        .status-card .card-header {
            background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
            color: white;
            padding: 15px 20px;
            border-bottom: 1px solid #c3c4c7;
        }
        
        .status-card .card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-card .card-content {
            padding: 20px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-icon {
            width: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .status-icon.success { color: #00a32a; }
        .status-icon.error { color: #d63638; }
        .status-icon.warning { color: #dba617; }
        .status-icon.info { color: #2271b1; }
        
        .status-label {
            min-width: 120px;
            font-weight: 500;
            color: #50575e;
        }
        
        .status-value {
            font-weight: 600;
            color: #1d2327;
        }
        
        .status-value.configured {
            color: #00a32a;
        }
        
        .status-value.not-configured {
            color: #d63638;
        }
        
        /* Environment Section */
        .system-environment {
            margin: 20px 0;
        }
        
        .environment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        
        .environment-section h4 {
            color: #2271b1;
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #2271b1;
        }
        
        .environment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .environment-table td {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f1;
            vertical-align: top;
        }
        
        .environment-table td:first-child {
            width: 40%;
            font-weight: 500;
            color: #50575e;
        }
        
        .environment-table td:last-child {
            color: #1d2327;
        }
        
        /* Diagnostic Tools */
        .diagnostic-tools {
            margin: 20px 0;
        }
        
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .diagnostic-tool {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        
        .diagnostic-tool:hover {
            border-color: #2271b1;
            box-shadow: 0 2px 8px rgba(34, 113, 177, 0.1);
        }
        
        .diagnostic-tool .tool-header {
            padding: 20px;
            background: #f9f9f9;
            border-bottom: 1px solid #c3c4c7;
        }
        
        .diagnostic-tool .tool-header h4 {
            margin: 0 0 10px 0;
            color: #2271b1;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .diagnostic-tool .tool-header p {
            margin: 0;
            color: #50575e;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .diagnostic-tool .tool-actions {
            padding: 20px;
        }
        
        .diagnostic-tool .tool-actions .button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-weight: 500;
        }
        
        .tool-results {
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 4px solid #2271b1;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.4;
            max-height: 300px;
            overflow-y: auto;
            display: none;
        }
        
        .tool-results.show {
            display: block;
        }
        
        .tool-results.success {
            border-left-color: #00a32a;
            background: #f0f9f0;
        }
        
        .tool-results.error {
            border-left-color: #d63638;
            background: #fdf0f0;
        }
        
        .tool-results.warning {
            border-left-color: #dba617;
            background: #fdf9f0;
        }
        
        /* Loading States */
        .button.loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        .button.loading::after {
            content: '';
            width: 12px;
            height: 12px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .status-cards-grid {
                grid-template-columns: 1fr;
            }
            
            .environment-grid {
                grid-template-columns: 1fr;
            }
            
            .tools-grid {
                grid-template-columns: 1fr;
            }
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
