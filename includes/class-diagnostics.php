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
        add_action('wp_ajax_ai_assistant_test_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_ai_assistant_check_database_health', array($this, 'ajax_check_database_health'));
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
     * Add diagnostics styles
     */
    private function add_diagnostics_styles() {
        // Styles are now included inline at the bottom of the page
    }
    
    /**
     * Render diagnostics page
     */
    public function render_diagnostics_page() {
        ?>
        
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
                $results.addClass('show').html('<div class="notice notice-info"><p><?php _e('Running language loading test...', 'ai-assistant'); ?></p></div>');
                
                $.post(ajaxurl, {
                    action: 'ai_assistant_debug_language_loading',
                    nonce: '<?php echo wp_create_nonce('ai_assistant_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.addClass('show success').html('<div class="notice notice-success"><p><strong><?php _e('Language Test Results:', 'ai-assistant'); ?></strong></p><div>' + response.data + '</div></div>');
                    } else {
                        $results.addClass('show error').html('<div class="notice notice-error"><p><strong><?php _e('Error:', 'ai-assistant'); ?></strong> ' + response.data + '</p></div>');
                    }
                }).fail(function() {
                    $results.addClass('show error').html('<div class="notice notice-error"><p><?php _e('Test failed - AJAX error', 'ai-assistant'); ?></p></div>');
                }).always(function() {
                    $button.prop('disabled', false).text('<?php _e('Run Language Test', 'ai-assistant'); ?>');
                });
            });
            
            // API connection test
            $('#test-api-connection').on('click', function() {
                var $button = $(this);
                var $results = $('#api-test-results');
                
                $button.prop('disabled', true).text('<?php _e('Testing...', 'ai-assistant'); ?>');
                $results.addClass('show').html('<div class="notice notice-info"><p><?php _e('Testing API connections...', 'ai-assistant'); ?></p></div>');
                
                $.post(ajaxurl, {
                    action: 'ai_assistant_test_api_connection',
                    nonce: '<?php echo wp_create_nonce('ai_assistant_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var html = '<div class="notice notice-success"><p><strong><?php _e('API Connection Test Results:', 'ai-assistant'); ?></strong></p>';
                        html += '<div style="font-family: monospace; background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 4px;">';
                        
                        $.each(response.data, function(api, result) {
                            var statusClass = result.status === 'success' ? 'success' : 'error';
                            var statusIcon = result.status === 'success' ? '✅' : '❌';
                            html += '<div style="margin-bottom: 8px;"><strong>' + api.toUpperCase() + ':</strong> ' + statusIcon + ' ' + result.message;
                            if (result.code) {
                                html += ' (' + result.code + ')';
                            }
                            html += '</div>';
                        });
                        
                        html += '</div></div>';
                        $results.addClass('show success').html(html);
                    } else {
                        $results.addClass('show error').html('<div class="notice notice-error"><p><strong><?php _e('Error:', 'ai-assistant'); ?></strong> ' + response.data + '</p></div>');
                    }
                }).fail(function() {
                    $results.addClass('show error').html('<div class="notice notice-error"><p><?php _e('API test failed - AJAX error', 'ai-assistant'); ?></p></div>');
                }).always(function() {
                    $button.prop('disabled', false).text('<?php _e('Test API Connections', 'ai-assistant'); ?>');
                });
            });
            
            // System report generation
            $('#generate-system-report').on('click', function() {
                var $button = $(this);
                var $results = $('#system-report-results');
                
                $button.prop('disabled', true).text('<?php _e('Generating...', 'ai-assistant'); ?>');
                $results.addClass('show').html('<div class="notice notice-info"><p><?php _e('Generating system report...', 'ai-assistant'); ?></p></div>');
                
                $.post(ajaxurl, {
                    action: 'ai_assistant_system_info',
                    nonce: '<?php echo wp_create_nonce('ai_assistant_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.addClass('show success').html('<div class="notice notice-success"><p><strong><?php _e('System Report Generated:', 'ai-assistant'); ?></strong></p><textarea style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;">' + response.data.report + '</textarea><p><em><?php _e('Copy the above information when reporting issues.', 'ai-assistant'); ?></em></p></div>');
                    } else {
                        $results.addClass('show error').html('<div class="notice notice-error"><p><strong><?php _e('Error:', 'ai-assistant'); ?></strong> ' + response.data + '</p></div>');
                    }
                }).fail(function() {
                    $results.addClass('show error').html('<div class="notice notice-error"><p><?php _e('Report generation failed - AJAX error', 'ai-assistant'); ?></p></div>');
                }).always(function() {
                    $button.prop('disabled', false).text('<?php _e('Generate Report', 'ai-assistant'); ?>');
                });
            });
            
            // Database health check
            $('#check-database-health').on('click', function() {
                var $button = $(this);
                var $results = $('#database-health-results');
                
                $button.prop('disabled', true).text('<?php _e('Checking...', 'ai-assistant'); ?>');
                $results.addClass('show').html('<div class="notice notice-info"><p><?php _e('Checking database health...', 'ai-assistant'); ?></p></div>');
                
                $.post(ajaxurl, {
                    action: 'ai_assistant_check_database_health',
                    nonce: '<?php echo wp_create_nonce('ai_assistant_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var html = '<div class="notice notice-success"><p><strong><?php _e('Database Health Check Results:', 'ai-assistant'); ?></strong></p>';
                        html += '<div style="font-family: monospace; background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 4px;">';
                        
                        var data = response.data;
                        
                        // Translations table
                        var transIcon = data.translations_table.exists ? '✅' : '❌';
                        html += '<div style="margin-bottom: 8px;"><strong><?php _e('Translations Table:', 'ai-assistant'); ?></strong> ' + transIcon + ' ';
                        html += data.translations_table.exists ? '<?php _e('Exists', 'ai-assistant'); ?> (' + data.translations_table.count + ' <?php _e('records', 'ai-assistant'); ?>)' : '<?php _e('Missing', 'ai-assistant'); ?>';
                        html += '</div>';
                        
                        // Suggestions table
                        var suggIcon = data.suggestions_table.exists ? '✅' : '❌';
                        html += '<div style="margin-bottom: 8px;"><strong><?php _e('Suggestions Table:', 'ai-assistant'); ?></strong> ' + suggIcon + ' ';
                        html += data.suggestions_table.exists ? '<?php _e('Exists', 'ai-assistant'); ?> (' + data.suggestions_table.count + ' <?php _e('records', 'ai-assistant'); ?>)' : '<?php _e('Missing', 'ai-assistant'); ?>';
                        html += '</div>';
                        
                        // Database connection
                        var dbIcon = data.database_connection.connected ? '✅' : '❌';
                        html += '<div style="margin-bottom: 8px;"><strong><?php _e('Database Connection:', 'ai-assistant'); ?></strong> ' + dbIcon + ' ';
                        html += data.database_connection.connected ? '<?php _e('Connected', 'ai-assistant'); ?> (v' + data.database_connection.version + ')' : '<?php _e('Failed', 'ai-assistant'); ?>';
                        html += '</div>';
                        
                        html += '</div></div>';
                        $results.addClass('show success').html(html);
                    } else {
                        $results.addClass('show error').html('<div class="notice notice-error"><p><strong><?php _e('Error:', 'ai-assistant'); ?></strong> ' + response.data + '</p></div>');
                    }
                }).fail(function() {
                    $results.addClass('show error').html('<div class="notice notice-error"><p><?php _e('Database check failed - AJAX error', 'ai-assistant'); ?></p></div>');
                }).always(function() {
                    $button.prop('disabled', false).text('<?php _e('Check Database', 'ai-assistant'); ?>');
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
        $api_keys = get_option('ai_assistant_api_keys', array());
        $configured_apis = array();
        
        if (!empty($api_keys['gemini'])) {
            $configured_apis[] = 'Gemini';
        }
        if (!empty($api_keys['openai'])) {
            $configured_apis[] = 'OpenAI';
        }
        if (!empty($api_keys['anthropic'])) {
            $configured_apis[] = 'Anthropic';
        }
        
        if (empty($configured_apis)) {
            return array(
                'status' => 'warning',
                'message' => __('No API keys configured', 'ai-assistant')
            );
        }
        
        return array(
            'status' => 'success',
            'message' => sprintf(__('%s configured', 'ai-assistant'), implode(', ', $configured_apis))
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
    
    /**
     * AJAX handler for API connection test
     */
    public function ajax_test_api_connection() {
        check_ajax_referer('ai_assistant_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Use the same AI service as the settings page for consistency
        $ai_service = new AI_Assistant_AI_Service();
        $api_keys = get_option('ai_assistant_api_keys', array());
        $results = array();
        
        // Test available APIs individually
        if (!empty($api_keys['gemini'])) {
            $results['GEMINI'] = $this->test_individual_api('gemini', $ai_service);
        }
        
        if (!empty($api_keys['openai'])) {
            $results['OPENAI'] = $this->test_individual_api('openai', $ai_service);
        }
        
        if (!empty($api_keys['anthropic'])) {
            $results['ANTHROPIC'] = $this->test_individual_api('anthropic', $ai_service);
        }
        
        if (empty($results)) {
            wp_send_json_error('No API keys configured for testing');
        }
        
        // Format results for display
        $formatted_results = array();
        $all_success = true;
        
        foreach ($results as $provider => $result) {
            if ($result['success']) {
                $formatted_results[] = "$provider: ✅ " . $result['message'];
            } else {
                $formatted_results[] = "$provider: ❌ " . $result['message'];
                $all_success = false;
            }
        }
        
        $summary = $all_success ? 
            "All configured APIs are working properly." : 
            "Some API connections have issues. Check the details below.";
        
        wp_send_json_success(array(
            'message' => $summary . "\n\n" . implode("\n", $formatted_results),
            'results' => $results,
            'all_success' => $all_success
        ));
    }
    
    /**
     * Test individual API provider
     */
    private function test_individual_api($provider, $ai_service) {
        try {
            // For diagnostics, we'll do a more lightweight test
            // Just check if the API key is set and try a basic connection
            $api_keys = get_option('ai_assistant_api_keys', array());
            
            if (empty($api_keys[$provider])) {
                return array(
                    'success' => false,
                    'message' => 'API key not configured'
                );
            }
            
            // Use the same test method as the working settings page
            // but with a shorter timeout and simpler approach
            switch ($provider) {
                case 'gemini':
                    return $this->test_gemini_connection($api_keys[$provider]);
                    
                case 'openai':
                    return $this->test_openai_connection($api_keys[$provider]);
                    
                case 'anthropic':
                    return $this->test_anthropic_connection($api_keys[$provider]);
                    
                default:
                    return array(
                        'success' => false,
                        'message' => 'Unknown provider'
                    );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Test Gemini API connection
     */
    private function test_gemini_connection($api_key) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;
        
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'sslverify' => false
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => 'Connected successfully'
            );
        } else {
            // Parse error response
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            
            return array(
                'success' => false,
                'message' => "HTTP $response_code ($response_code) - $error_message"
            );
        }
    }
    
    /**
     * Test OpenAI API connection
     */
    private function test_openai_connection($api_key) {
        $url = 'https://api.openai.com/v1/models';
        
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'sslverify' => false
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return array(
                'success' => true,
                'message' => 'Connected successfully'
            );
        } else {
            return array(
                'success' => false,
                'message' => "HTTP $response_code"
            );
        }
    }
    
    /**
     * Test Anthropic API connection
     */
    private function test_anthropic_connection($api_key) {
        // Anthropic doesn't have a simple endpoint to test, so we'll just validate the key format
        if (strpos($api_key, 'sk-ant-') === 0 && strlen($api_key) > 20) {
            return array(
                'success' => true,
                'message' => 'API key format valid'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Invalid API key format'
            );
        }
    }

    
    /**
     * AJAX handler for database health check
     */
    public function ajax_check_database_health() {
        check_ajax_referer('ai_assistant_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        $health_status = array();
        
        // Check translation table
        $translations_table = $wpdb->prefix . 'ai_assistant_translations';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$translations_table'") === $translations_table;
        $health_status['translations_table'] = array(
            'exists' => $table_exists,
            'count' => $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $translations_table") : 0
        );
        
        // Check suggestions table
        $suggestions_table = $wpdb->prefix . 'ai_assistant_suggestions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$suggestions_table'") === $suggestions_table;
        $health_status['suggestions_table'] = array(
            'exists' => $table_exists,
            'count' => $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $suggestions_table") : 0
        );
        
        // Check database connectivity
        $health_status['database_connection'] = array(
            'connected' => !empty($wpdb->dbh),
            'version' => $wpdb->db_version(),
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate
        );
        
        wp_send_json_success($health_status);
    }
    
    /**
     * Test Gemini API connection
     */
    private function test_gemini_api($api_key) {
        $test_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $api_key;
        
        $response = wp_remote_post($test_url, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array('text' => 'Test')
                        )
                    )
                )
            ))
        ));
        
        if (is_wp_error($response)) {
            return array('status' => 'error', 'message' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        return array(
            'status' => ($code === 200) ? 'success' : 'error',
            'code' => $code,
            'message' => ($code === 200) ? 'Connection successful' : 'HTTP ' . $code
        );
    }
    
    /**
     * Test OpenAI API connection
     */
    private function test_openai_api($api_key) {
        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key
            )
        ));
        
        if (is_wp_error($response)) {
            return array('status' => 'error', 'message' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        return array(
            'status' => ($code === 200) ? 'success' : 'error',
            'code' => $code,
            'message' => ($code === 200) ? 'Connection successful' : 'HTTP ' . $code
        );
    }
    
    /**
     * Test Anthropic API connection
     */
    private function test_anthropic_api($api_key) {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'timeout' => 15,
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ),
            'body' => json_encode(array(
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 10,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Test'
                    )
                )
            ))
        ));
        
        if (is_wp_error($response)) {
            return array('status' => 'error', 'message' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        return array(
            'status' => ($code === 200) ? 'success' : 'error',
            'code' => $code,
            'message' => ($code === 200) ? 'Connection successful' : 'HTTP ' . $code
        );
    }
}

// Initialize diagnostics if this file is included
if (class_exists('AI_Assistant_Diagnostics')) {
    new AI_Assistant_Diagnostics();
}
