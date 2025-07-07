<?php
/**
 * Translator class for handling translation operations
 *
 * @package AIAssistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Assistant_Translator {
    
    /**
     * AI Service instance
     */
    private $ai_service;
      /**
     * Constructor
     */
    public function __construct($ai_service = null) {
        $this->ai_service = $ai_service ?: new AI_Assistant_AI_Service();
    }
    
    /**
     * Translate content from URL with history saving
     *
     * @param string $url The URL to fetch content from
     * @param string $source_lang Source language code  
     * @param string $target_lang Target language code
     * @param string $model AI model to use
     * @return array Translation result
     */
    public function translate_url($url, $source_lang, $target_lang, $model) {
        // Fetch content from URL
        $content_data = $this->fetch_content_from_url($url);
        
        if (!$content_data['success']) {
            return $content_data;
        }
        
        // Extract relevant content
        $content = $content_data['content'];
        $title = isset($content_data['title']) ? $content_data['title'] : '';
        
        // Translate the content
        $translation_result = $this->ai_service->translate($content, $source_lang, $target_lang, $model);
        
        if (!$translation_result['success']) {
            return $translation_result;
        }
        
        $translated_content = isset($translation_result['translated_text']) ? $translation_result['translated_text'] : '';
        
        // Save to translation history
        $this->save_translation_history(array(
            'source_url' => $url,
            'source_content' => $content,
            'translated_content' => $translated_content,
            'source_language' => $source_lang,
            'target_language' => $target_lang,
            'model' => $model,
            'title' => $title,
            'status' => 'completed'
        ));
        
        return array(
            'success' => true,
            'original_content' => $content,
            'translated_content' => $translated_content,
            'source_url' => $url,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'model' => $model,
            'title' => $title
        );
    }
    
    /**
     * Save translation to history
     */
    public function save_translation_history($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        
        // Ensure table exists with correct structure
        $this->ensure_translations_table_exists();
        
        // Validate required fields
        if (empty($data['source_language']) || empty($data['target_language']) || empty($data['translated_content'])) {
            error_log('AI Assistant ERROR: Missing required fields for translation history');
            return false;
        }
        
        // Prepare data with proper defaults and validation
        $insert_data = array(
            'post_id' => !empty($data['post_id']) ? intval($data['post_id']) : null,
            'source_url' => !empty($data['source_url']) ? sanitize_url($data['source_url']) : null,
            'source_language' => sanitize_text_field($data['source_language']),
            'target_language' => sanitize_text_field($data['target_language']),
            'original_content' => !empty($data['original_content']) ? wp_kses_post($data['original_content']) : 
                                 (!empty($data['source_content']) ? wp_kses_post($data['source_content']) : ''),
            'translated_content' => wp_kses_post($data['translated_content']),
            'model' => !empty($data['model']) ? sanitize_text_field($data['model']) : 'gemini-2.5-flash',
            'status' => !empty($data['status']) ? sanitize_text_field($data['status']) : 'completed',
            'created_at' => current_time('mysql')
        );
        
        
        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('AI Assistant ERROR: Failed to save translation history: ' . $wpdb->last_error);
            
            // Check if it's a column error and try to fix table
            if (strpos($wpdb->last_error, 'Unknown column') !== false) {
                $this->ensure_translations_table_exists();
                
                // Try insert again
                $result = $wpdb->insert(
                    $table_name,
                    $insert_data,
                    array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );
                
                if ($result === false) {
                    error_log('AI Assistant ERROR: Still failed after table fix: ' . $wpdb->last_error);
                }
            }
            
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Ensure translations table exists with correct structure
     */
    private function ensure_translations_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            // Create new table with correct structure
            $this->create_translations_table();
        } else {
            // Check if table has correct columns
            $columns = $wpdb->get_col("DESCRIBE $table_name", 0);
            $required_columns = ['id', 'post_id', 'source_url', 'source_language', 'target_language', 'original_content', 'translated_content', 'model', 'status', 'created_at'];
            
            $missing_columns = array_diff($required_columns, $columns);
            
            if (!empty($missing_columns)) {
                error_log("AI Assistant ERROR: Missing columns detected: " . implode(', ', $missing_columns));
                
                // Try to update table structure
                $this->update_translations_table();
                
                // Verify the update worked
                $columns_after_update = $wpdb->get_col("DESCRIBE $table_name", 0);
                $still_missing = array_diff($required_columns, $columns_after_update);
                
                if (!empty($still_missing)) {
                    error_log("AI Assistant ERROR: Table update failed, recreating table. Still missing: " . implode(', ', $still_missing));
                    $this->recreate_translations_table();
                }
            }
        }
    }
    
    /**
     * Create translations table
     */
    private function create_translations_table() {
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
     * Update existing table structure
     */
    private function update_translations_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        
        // Get current columns
        $columns = $wpdb->get_col("DESCRIBE $table_name", 0);
        
        // Add missing columns in correct order
        if (!in_array('post_id', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN post_id bigint(20) DEFAULT NULL AFTER id");
        }
        
        if (!in_array('source_url', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN source_url text DEFAULT NULL AFTER post_id");
        }
        
        if (!in_array('model', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN model varchar(50) DEFAULT NULL AFTER translated_content");
        }
        
        if (!in_array('status', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN status varchar(20) DEFAULT 'completed' AFTER model");
        }
        
        // Add indexes if they don't exist
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
        $index_names = array_column($indexes, 'Key_name');
        
        if (!in_array('idx_post_id', $index_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_post_id (post_id)");
        }
        
        if (!in_array('idx_languages', $index_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_languages (source_language, target_language)");
        }
        
        if (!in_array('idx_created_at', $index_names)) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX idx_created_at (created_at)");
        }
    }
    
    /**
     * Recreate translations table (backup and recreate)
     */
    private function recreate_translations_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        $backup_table = $table_name . '_backup_' . date('Y_m_d_H_i_s');
        
        // Create backup if table has data
        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($row_count > 0) {
            $wpdb->query("CREATE TABLE $backup_table AS SELECT * FROM $table_name");
        }
        
        // Drop and recreate table
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        $this->create_translations_table();
    }
    
    /**
     * Fetch content from URL
     *
     * @param string $url The URL to fetch content from
     * @return array Fetched content data
     */
    private function fetch_content_from_url($url) {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return array(
                'success' => false,
                'error' => __('Invalid URL provided', 'ai-assistant')
            );
        }
        
        // Fetch the page
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'AI Assistant WordPress Plugin'
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            return array(
                'success' => false,
                'error' => __('No content found at the URL', 'ai-assistant')
            );
        }
        
        // Extract content from HTML
        $content = $this->extract_content_from_html($body);
        
        if (empty($content)) {
            return array(
                'success' => false,
                'error' => __('Could not extract content from the URL', 'ai-assistant')
            );
        }
        
        return array(
            'success' => true,
            'content' => $content,
            'url' => $url
        );
    }
    
    /**
     * Extract content from HTML
     *
     * @param string $html HTML content
     * @return string Extracted content
     */
    private function extract_content_from_html($html) {
        // Load HTML into DOMDocument
        $dom = new DOMDocument();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        
        // Load HTML with UTF-8 encoding
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Restore error handling
        libxml_clear_errors();
        
        $content = '';
        
        // Try to find the main content area
        $content_selectors = array(
            'article',
            '.post-content',
            '.entry-content',
            '.content',
            'main',
            '.main-content',
            '#content',
            '.post-body'
        );
        
        $xpath = new DOMXPath($dom);
        
        foreach ($content_selectors as $selector) {
            $nodes = $xpath->query("//*[contains(@class, '" . ltrim($selector, '.') . "') or local-name() = '" . ltrim($selector, '.#') . "']");
            
            if ($nodes->length > 0) {
                $content_node = $nodes->item(0);
                $content = $this->get_text_content($content_node);
                break;
            }
        }
        
        // If no specific content area found, try to get title and meta description
        if (empty($content)) {
            $title_nodes = $xpath->query('//title');
            $meta_nodes = $xpath->query('//meta[@name="description"]');
            
            if ($title_nodes->length > 0) {
                $content .= $title_nodes->item(0)->textContent . "\n\n";
            }
            
            if ($meta_nodes->length > 0) {
                $content .= $meta_nodes->item(0)->getAttribute('content');
            }
        }
        
        return trim($content);
    }
    
    /**
     * Get text content from DOM node
     *
     * @param DOMNode $node DOM node
     * @return string Text content
     */
    private function get_text_content($node) {
        $content = '';
        
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_TEXT_NODE) {
                    $content .= $child->textContent;
                } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                    $tag_name = strtolower($child->nodeName);
                    
                    // Skip script and style tags
                    if (in_array($tag_name, array('script', 'style', 'nav', 'footer', 'header'))) {
                        continue;
                    }
                    
                    // Add line breaks for block elements
                    if (in_array($tag_name, array('p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'br'))) {
                        $content .= "\n" . $this->get_text_content($child) . "\n";
                    } else {
                        $content .= $this->get_text_content($child);
                    }
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Get translation history
     *
     * @param array $args Query arguments
     * @return array Translation history
     */
    public function get_translation_history($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'source_lang' => '',
            'target_lang' => '',
            'status' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        $where_clauses = array();
        $values = array();
        
        if (!empty($args['source_lang'])) {
            $where_clauses[] = 'source_language = %s';
            $values[] = $args['source_lang'];
        }
        
        if (!empty($args['target_lang'])) {
            $where_clauses[] = 'target_language = %s';
            $values[] = $args['target_lang'];
        }
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        $sql = "SELECT * FROM $table_name $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $values));
    }
    
    /**
     * Delete translation
     *
     * @param int $translation_id Translation ID
     * @return bool Success status
     */
    public function delete_translation($translation_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_assistant_translations';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $translation_id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Auto-detect language from content
     *
     * @param string $content Content to analyze
     * @param string $model AI model to use
     * @return array Detection result
     */
    public function detect_language($content, $model) {
        $prompt = "Detect the language of the following content and return only the language code (e.g., 'en', 'tr', 'fr', 'de', 'zh'):\n\n" . substr($content, 0, 500);
        
        $result = $this->ai_service->send_request($model, $prompt, array('max_tokens' => 10));
        
        if ($result['success']) {
            $detected_lang = trim(strtolower($result['content']));
            
            // Validate the detected language
            $supported_languages = array('tr', 'en', 'fr', 'de', 'zh');
            
            if (in_array($detected_lang, $supported_languages)) {
                return array(
                    'success' => true,
                    'language' => $detected_lang
                );
            }
        }
        
        return array(
            'success' => false,
            'error' => __('Could not detect language', 'ai-assistant')
        );
    }
    
    /**
     * Translate content directly
     *
     * @param string $content Content to translate
     * @param string $target_lang Target language code
     * @param string $source_lang Source language code (default: 'auto')
     * @param string $model AI model to use
     * @return string|WP_Error Translated content or error
     */    public function translate($content, $target_lang, $source_lang = 'auto', $model = 'gemini-2.5-flash') {
        if (empty($content) || empty($target_lang)) {
            return new WP_Error('missing_params', 'Content and target language are required.');
        }
        
        // Use real AI service for translation
        if ($this->ai_service && $this->ai_service->is_configured()) {
            $result = $this->ai_service->translate($content, $source_lang, $target_lang, $model);
            
            if ($result['success']) {
                return $result['translated_text'];
            } else {
                return new WP_Error('translation_failed', $result['error']);
            }
        } else {
            // Fallback to mock translation if API not configured
            $mock_translation = $this->generate_mock_translation($content, $source_lang, $target_lang);
            return $mock_translation;
        }
    }
      /**
     * Generate a mock translation for testing purposes
     *
     * @param string $content Original content
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @return string Mock translated content
     */
    private function generate_mock_translation($content, $source_lang, $target_lang) {
        $language_names = array(
            'en' => 'English',
            'tr' => 'Turkish',
            'ar' => 'Arabic',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'ru' => 'Russian',
            'zh' => 'Chinese',
            'fa' => 'Persian'
        );
        
        $source_name = isset($language_names[$source_lang]) ? $language_names[$source_lang] : $source_lang;
        $target_name = isset($language_names[$target_lang]) ? $language_names[$target_lang] : $target_lang;
        
        // Generate a more realistic mock translation based on target language
        if ($target_lang === 'en') {
            $mock_content = "This is a mock English translation of the Turkish content about Islamic marriage law (nikah) and the concept of mahr (dower). In a production environment with configured API keys, this would be the actual English translation of the provided Turkish text about Islamic jurisprudence regarding marriage contracts and the obligatory mahr payment from husband to wife.";
        } elseif ($target_lang === 'tr') {
            $mock_content = "Bu, İslam evlilik hukuku (nikah) ve mehir kavramı hakkındaki içeriğin sahte Türkçe çevirisidir. Yapılandırılmış API anahtarları olan bir üretim ortamında, bu, evlilik sözleşmeleri ve kocadan karıya zorunlu mehir ödemesi ile ilgili İslam hukuku hakkında sağlanan metnin gerçek Türkçe çevirisi olacaktır.";
        } else {
            $mock_content = "This is a mock translation to " . $target_name . ". In a production environment with configured API keys, this would be the actual translation of the source content.";
        }
        
        return sprintf(
            "[MOCK TRANSLATION - v1.0.11]\n\nSource: %s → Target: %s\nModel: %s\n\n%s\n\n[Note: This is a demonstration. Configure API keys in WordPress Admin → AI Assistant → Settings to enable real translations.]",
            $source_name,
            $target_name,
            'gemini-2.5-flash',
            $mock_content
        );
    }
}
