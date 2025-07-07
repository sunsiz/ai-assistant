<?php
/**
 * AI Service class for handling Google Gemini API interactions
 *
 * @package AIAssistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Assistant_AI_Service {
    
    /**
     * API endpoint for Gemini
     */
    private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent';
    
    /**
     * API key for Gemini
     */
    private $api_key;
    
    /**
     * Cache duration in seconds (30 minutes)
     */
    private $cache_duration = 1800;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = $this->get_api_key();
    }    /**
     * Get API key from settings based on preferred provider
     *
     * @return string|false
     */
    private function get_api_key() {
        $preferred_provider = get_option('ai_assistant_preferred_provider', 'gemini');
        $api_keys = get_option('ai_assistant_api_keys', array());
        
        // Try preferred provider first
        if ($this->is_provider_configured($preferred_provider)) {
            switch ($preferred_provider) {
                case 'openai':
                    return trim($api_keys['openai']);
                case 'anthropic':
                    return trim($api_keys['anthropic']);
                case 'gemini':
                default:
                    // For Gemini, check both 'gemini' and 'google' keys
                    if (isset($api_keys['gemini']) && !empty(trim($api_keys['gemini']))) {
                        return trim($api_keys['gemini']);
                    }
                    if (isset($api_keys['google']) && !empty(trim($api_keys['google']))) {
                        return trim($api_keys['google']);
                    }
                    break;
            }
        }
        
        // Fallback: Check Gemini-specific setting (backward compatibility)
        if (isset($api_keys['gemini']) && !empty(trim($api_keys['gemini']))) {
            return trim($api_keys['gemini']);
        }
        
        // Fallback to old Google key for backward compatibility
        if (isset($api_keys['google']) && !empty(trim($api_keys['google']))) {
            return trim($api_keys['google']);
        }
        
        // Fallback to old settings format
        $options = get_option('ai_assistant_settings', array());
        if (isset($options['gemini_api_key']) && !empty(trim($options['gemini_api_key']))) {
            return trim($options['gemini_api_key']);
        }
        
        return false;
    }
    
    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Translate text using AI API
     *
     * @param string $text Text to translate
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @param string $model AI model to use
     * @return array Response with translated text or error
     */
    public function translate($text, $source_lang = 'auto', $target_lang = 'en', $model = '') {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'Gemini API key not configured. Please configure it in Settings.'
            );
        }
        
        if (empty($text)) {
            return array(
                'success' => false,
                'error' => 'No text provided for translation.'
            );
        }
        
        // Language mapping
        $language_names = $this->get_language_names();
        $source_name = isset($language_names[$source_lang]) ? $language_names[$source_lang] : $source_lang;
        $target_name = isset($language_names[$target_lang]) ? $language_names[$target_lang] : $target_lang;
        
        // Create the prompt for AI
        $prompt = $this->create_translation_prompt($text, $source_name, $target_name);
        
        // Check cache
        $cache_key = $this->get_cache_key($prompt, 'translate');
        $cached_result = $this->get_cached_result($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
          // Make API request with model parameter
        $response = $this->make_api_request($prompt, $model);
          if ($response['success']) {
            $result = array(
                'success' => true,
                'translated_text' => $response['content'],
                'source_language' => $source_name,
                'target_language' => $target_name,
                'provider' => $this->get_current_provider(),
                'model' => $this->get_current_model()
            );
            
            // Cache the result
            $this->set_cached_result($cache_key, $result);
            
            return $result;
        } else {
            return $response;
        }
    }
    
    /**
     * Create translation prompt for Gemini
     *
     * @param string $text Text to translate
     * @param string $source_name Source language name
     * @param string $target_name Target language name
     * @return string
     */
    private function create_translation_prompt($text, $source_name, $target_name) {
        $prompt = "Please translate the following text";
        
        if ($source_name !== 'auto') {
            $prompt .= " from {$source_name}";
        }
        
        $prompt .= " to {$target_name}. ";
        $prompt .= "Provide only the translated text without any additional explanations, prefixes, or formatting. ";
        $prompt .= "If the original text contains HTML tags, preserve them exactly. ";
        $prompt .= "If the original text is plain text, maintain the original structure and formatting as much as possible. ";
        $prompt .= "For WordPress content, ensure the output is compatible with WordPress editor.\n\n";
        $prompt .= "Text to translate:\n{$text}";
        
        return $prompt;
    }
      /**
     * Make API request based on current provider
     *
     * @param string $prompt The prompt to send
     * @param string $model Optional model to use
     * @return array Response data
     */
    private function make_api_request($prompt, $model = '') {
        $provider = $this->get_current_provider();
        
        switch ($provider) {
            case 'openai':
                return $this->make_openai_request($prompt, $model);
            case 'anthropic':
                return $this->make_anthropic_request($prompt, $model);
            case 'gemini':
            default:
                return $this->make_gemini_request($prompt, $model);
        }
    }
    
    /**
     * Make API request to Gemini
     *
     * @param string $prompt The prompt to send
     * @param string $model Optional model to use
     * @return array Response data
     */
    private function make_gemini_request($prompt, $model = '') {
        // Use provided model or get current default
        $selected_model = !empty($model) ? $model : $this->get_current_model();
        
        // Ensure we have a valid Gemini model
        if (!str_contains($selected_model, 'gemini')) {
            $selected_model = 'gemini-2.5-flash';
        }
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$selected_model}:generateContent?key=" . $this->api_key;
        
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.3,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 8192,
            ),
            'safetySettings' => array(
                array(
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ),
                array(
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                )
            )
        );
          $args = array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 60, // Increased timeout for Gemini API
            'sslverify' => true
        );
        
        $response = $this->make_http_request_with_retry($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'API request failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = 'API request failed with code ' . $response_code;
            
            if (isset($error_data['error']['message'])) {
                $error_message .= ': ' . $error_data['error']['message'];
            }
            
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return array(
                'success' => false,
                'error' => 'Invalid response format from API'
            );
        }
        
        return array(
            'success' => true,
            'content' => trim($data['candidates'][0]['content']['parts'][0]['text'])
        );
    }
    
    /**
     * Make HTTP request with retry logic
     *
     * @param string $url Request URL
     * @param array $args Request arguments
     * @param int $max_retries Maximum number of retries
     * @return array|WP_Error
     */
    private function make_http_request_with_retry($url, $args, $max_retries = 2) {
        $retry_count = 0;
        
        while ($retry_count <= $max_retries) {
            $response = wp_remote_post($url, $args);
            
            if (!is_wp_error($response)) {
                return $response;
            }
            
            $error_message = $response->get_error_message();
            
            // Check if it's a timeout or connection error that might be retryable
            if (strpos($error_message, 'timeout') !== false || 
                strpos($error_message, 'Connection') !== false ||
                strpos($error_message, 'cURL error 28') !== false) {
                
                $retry_count++;
                if ($retry_count <= $max_retries) {
                    // Wait a bit before retrying (exponential backoff)
                    sleep(pow(2, $retry_count - 1));
                    continue;
                }
            }
            
            // If it's not a retryable error or we've exhausted retries, return the error
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Make API request to OpenAI
     *
     * @param string $prompt The prompt to send
     * @param string $model Optional model to use
     * @return array Response data
     */
    private function make_openai_request($prompt, $model = '') {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        // Use provided model or default
        $selected_model = !empty($model) ? $model : 'gpt-3.5-turbo';
        
        $body = array(
            'model' => $selected_model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.3,
            'max_tokens' => 2000
        );
          $args = array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'timeout' => 60, // Increased timeout for OpenAI API
            'sslverify' => true
        );
          $response = $this->make_http_request_with_retry($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'OpenAI API request failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = 'OpenAI API request failed with code ' . $response_code;
            
            if (isset($error_data['error']['message'])) {
                $error_message .= ': ' . $error_data['error']['message'];
            }
            
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'error' => 'Invalid response format from OpenAI API'
            );
        }
        
        return array(
            'success' => true,
            'content' => trim($data['choices'][0]['message']['content'])
        );
    }
    
    /**
     * Make API request to Anthropic
     *
     * @param string $prompt The prompt to send
     * @param string $model Optional model to use
     * @return array Response data
     */
    private function make_anthropic_request($prompt, $model = '') {
        $url = 'https://api.anthropic.com/v1/messages';
        
        // Use provided model or default
        $selected_model = !empty($model) ? $model : 'claude-3-haiku-20240307';
        
        $body = array(
            'model' => $selected_model,
            'max_tokens' => 2000,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
          $args = array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'timeout' => 60, // Increased timeout for Anthropic API
            'sslverify' => true
        );
          $response = $this->make_http_request_with_retry($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Anthropic API request failed: ' . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = 'Anthropic API request failed with code ' . $response_code;
            
            if (isset($error_data['error']['message'])) {
                $error_message .= ': ' . $error_data['error']['message'];
            }
            
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['content'][0]['text'])) {
            return array(
                'success' => false,
                'error' => 'Invalid response format from Anthropic API'
            );
        }
        
        return array(
            'success' => true,
            'content' => trim($data['content'][0]['text'])
        );
    }
    
    /**
     * Generate content suggestions based on type and context
     *
     * @param string $type Content type (suggestions, keywords, meta-description, title-ideas)
     * @param string $context Topic or context
     * @param string $existing_content Optional existing content for context
     * @return array Response with generated content or error
     */
    public function generate_content($type, $context, $existing_content = '', $model = '') {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'AI API key not configured. Please configure it in Settings.'
            );
        }
        
        if (empty($context)) {
            return array(
                'success' => false,
                'error' => 'No context provided for content generation.'
            );
        }
        
        // Create the prompt based on content type
        $prompt = $this->create_content_prompt($type, $context, $existing_content);
        
        // Check cache
        $cache_key = $this->get_cache_key($prompt, 'content');
        $cached_result = $this->get_cached_result($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Make API request with model parameter
        $response = $this->make_api_request($prompt, $model);
        
        if ($response['success']) {
            $html_content = $response['content'];
            
            // Generate unique cache key for this content
            $html_cache_key = md5($html_content . time());
            
            // Store original HTML for WordPress insertion
            $this->store_html_for_insertion($html_content, $html_cache_key);
            
            // Strip HTML for textarea display
            $display_content = $this->strip_html_for_display($html_content);
            
            $result = array(
                'success' => true,
                'content' => $display_content,
                'html_content' => $html_content,
                'html_cache_key' => $html_cache_key,
                'type' => $type,
                'context' => $context,
                'provider' => $this->get_current_provider(),
                'model' => $this->get_current_model()
            );
            
            // Set cache
            $this->set_cached_result($cache_key, $result);
            
            return $result;
        } else {
            return $response;
        }
    }
    
    /**
     * Create content generation prompt based on type
     *
     * @param string $type Content type
     * @param string $context Topic or context
     * @param string $existing_content Optional existing content
     * @return string
     */
    private function create_content_prompt($type, $context, $existing_content = '') {
        $base_context = !empty($existing_content) ? 
            "Based on this existing content: \"{$existing_content}\" and the topic: \"{$context}\"" : 
            "Based on the topic: \"{$context}\"";
              switch ($type) {
            case 'suggestions':
                return "{$base_context}, provide 5-7 specific content suggestions or ideas for blog posts, articles, or content pieces. Each suggestion should be practical and actionable. Format as an HTML ordered list (&lt;ol&gt;&lt;li&gt;) with brief explanations. Use proper HTML formatting for WordPress.";
                
            case 'full-article':
                return "{$base_context}, write a complete, comprehensive article suitable for WordPress. The article should be well-structured with an engaging introduction, detailed body sections with subheadings, practical examples or insights, and a strong conclusion. Aim for 800-1500 words. Use clear, engaging language and include actionable advice where appropriate. Format with proper HTML headings (&lt;h2&gt; for main headings, &lt;h3&gt; for subheadings), paragraphs (&lt;p&gt;), and lists (&lt;ul&gt;&lt;li&gt; or &lt;ol&gt;&lt;li&gt;) as needed. Maintain a professional yet accessible tone. Output clean HTML that works well in WordPress editor.";
                
            case 'keywords':
                return "{$base_context}, generate 10-15 relevant SEO keywords and phrases. Include both short-tail (1-2 words) and long-tail (3-5 words) keywords. Focus on search intent and relevance. Format as an HTML unordered list (&lt;ul&gt;&lt;li&gt;) ordered by relevance, with each keyword as a separate list item.";
                
            case 'meta-description':
                return "{$base_context}, write 2-3 compelling meta descriptions (each 150-160 characters) that would encourage clicks from search results. Focus on benefits, urgency, and clear value proposition. Format as an HTML ordered list (&lt;ol&gt;&lt;li&gt;) with each option as a separate list item.";
                
            case 'title-ideas':
                return "{$base_context}, generate 8-10 engaging title ideas that would work well for blog posts, articles, or web pages. Include a mix of question-based, list-based, how-to, and benefit-driven titles. Make them compelling and SEO-friendly. Format as an HTML unordered list (&lt;ul&gt;&lt;li&gt;) with each title as a separate list item.";
                
            default:
                return "{$base_context}, provide helpful content suggestions related to this topic. Format using proper HTML for WordPress.";
        }
    }
    
    /**
     * Get cache key for a request
     *
     * @param string $prompt The prompt
     * @param string $type The request type (translate, content, etc.)
     * @return string
     */
    private function get_cache_key($prompt, $type = 'default') {
        return 'ai_assistant_' . $type . '_' . md5($prompt . $this->get_current_provider() . $this->get_current_model());
    }
    
    /**
     * Get cached result
     *
     * @param string $cache_key
     * @return mixed|false
     */
    private function get_cached_result($cache_key) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            error_log('AI Assistant: Using cached result for key: ' . $cache_key);
            return $cached;
        }
        return false;
    }
    
    /**
     * Set cached result
     *
     * @param string $cache_key
     * @param mixed $data
     * @return bool
     */
    private function set_cached_result($cache_key, $data) {
        return set_transient($cache_key, $data, $this->cache_duration);
    }

    /**
     * Get language names mapping
     *
     * @return array
     */
    private function get_language_names() {
        return array(
            'auto' => 'Auto-detect',
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
    }
    
    /**
     * Test API connection
     *
     * @return array Test results
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'error' => 'API key not configured'
            );
        }
        
        // Test with a simple translation
        $test_result = $this->translate('Hello, world!', 'en', 'es');
        
        if ($test_result['success']) {
            return array(
                'success' => true,
                'message' => 'API connection successful',
                'test_translation' => $test_result['translated_text']
            );
        } else {
            return $test_result;
        }
    }
      /**
     * Legacy method for compatibility - generate simple content
     */
    public function generate_simple_content($prompt, $model = '') {
        $response = $this->make_api_request($prompt);
        return $response['success'] ? $response['content'] : 'Error: ' . $response['error'];
    }
    
    /**
     * Debug method to check API key configuration
     * Remove this method in production
     *
     * @return array Debug information
     */
    public function debug_api_key() {
        $api_keys = get_option('ai_assistant_api_keys', array());
        $options = get_option('ai_assistant_settings', array());
        
        return array(
            'api_keys_option' => $api_keys,
            'settings_option' => $options,
            'retrieved_key' => $this->get_api_key(),
            'is_configured' => $this->is_configured()
        );
    }
    
    /**
     * Get all configured API keys
     *
     * @return array Array of configured providers and their keys
     */
    public function get_configured_providers() {
        $api_keys = get_option('ai_assistant_api_keys', array());
        $configured = array();
        
        if (!empty($api_keys['openai'])) {
            $configured['openai'] = array(
                'name' => 'OpenAI',
                'models' => array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo')
            );
        }
        
        if (!empty($api_keys['anthropic'])) {
            $configured['anthropic'] = array(
                'name' => 'Anthropic',
                'models' => array('claude-3-sonnet', 'claude-3-haiku')
            );
        }
        
        if (!empty($api_keys['gemini']) || !empty($api_keys['google'])) {
            $configured['gemini'] = array(
                'name' => 'Google Gemini',
                'models' => array('gemini-pro', 'gemini-1.5-flash-latest')
            );
        }
        
        return $configured;
    }
    
    /**
     * Check if a specific provider is configured
     *
     * @param string $provider Provider name (openai, anthropic, gemini)
     * @return bool
     */
    public function is_provider_configured($provider) {
        $api_keys = get_option('ai_assistant_api_keys', array());
        
        switch ($provider) {
            case 'openai':
                return !empty($api_keys['openai']);
            case 'anthropic':
                return !empty($api_keys['anthropic']);
            case 'gemini':
            case 'google':
                return !empty($api_keys['gemini']) || !empty($api_keys['google']);
            default:
                return false;
        }
    }
    
    /**
     * Get current active provider
     *
     * @return string
     */
    public function get_current_provider() {
        $preferred_provider = get_option('ai_assistant_preferred_provider', 'gemini');
        
        // Check if preferred provider is configured
        if ($this->is_provider_configured($preferred_provider)) {
            return $preferred_provider;
        }
        
        // Fallback to any configured provider
        $configured = $this->get_configured_providers();
        if (!empty($configured)) {
            return array_keys($configured)[0]; // Return first configured provider
        }
        
        return 'gemini'; // Default fallback
    }
    
    /**
     * Get current model based on provider
     *
     * @return string
     */
    public function get_current_model() {
        $provider = $this->get_current_provider();
        $default_model = get_option('ai_assistant_default_model', '');
        
        // If a specific model is set, use it
        if (!empty($default_model)) {
            return $default_model;
        }
        
        // Otherwise, use provider defaults
        switch ($provider) {
            case 'openai':
                return 'gpt-4o-mini';
            case 'anthropic':
                return 'claude-3-5-haiku-20241022';
            case 'gemini':
            default:
                return 'gemini-2.5-flash';
        }
    }
    
    /**
     * Public method to make API request (for suggestions, auto-translation and other features)
     *
     * @param string $prompt The prompt to send
     * @param array $options Optional parameters like temperature, max_tokens
     * @return array Response data
     */
    public function make_api_request_public($prompt, $options = array()) {
        $default_options = array(
            'temperature' => 0.1,
            'max_tokens' => 200
        );
        
        $options = array_merge($default_options, $options);
        
        // Check cache for suggestions (with shorter cache time for real-time feel)
        $cache_key = $this->get_cache_key($prompt, 'suggestion');
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        try {
            $response = $this->make_api_request($prompt, '', $options);
            
            // Cache successful results for 5 minutes only for suggestions
            if ($response && isset($response['content'])) {
                $success_response = array(
                    'success' => true,
                    'content' => $response['content']
                );
                set_transient($cache_key, $success_response, 300);
                return $success_response;
            }
            
            return array(
                'success' => false,
                'error' => 'No valid response from AI service'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Clear AI Assistant cache
     *
     * @param string $type Optional cache type to clear (translate, content, suggestion, or 'all')
     * @return int Number of cache entries cleared
     */
    public function clear_cache($type = 'all') {
        global $wpdb;
        
        if ($type === 'all') {
            // Clear all AI Assistant cache entries
            $count = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_ai_assistant_%'
                )
            );
        } else {
            // Clear specific type cache entries
            $count = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_ai_assistant_' . $type . '_%'
                )
            );
        }
        
        error_log('AI Assistant: Cleared ' . $count . ' cache entries of type: ' . $type);
        return $count;
    }

    /**
     * Get available models for configured providers
     *
     * @return array
     */
    public function get_available_models() {
        $models = array();
        
        $configured_providers = $this->get_configured_providers();
        
        foreach ($configured_providers as $provider) {
            switch ($provider) {
                case 'gemini':
                    $models['gemini-2.5-flash'] = 'Gemini 2.5 Flash (Latest & Fast)';
                    $models['gemini-2.5-pro'] = 'Gemini 2.5 Pro (Most Advanced)';
                    $models['gemini-1.5-flash'] = 'Gemini 1.5 Flash (Stable)';
                    $models['gemini-1.5-pro'] = 'Gemini 1.5 Pro (Advanced)';
                    break;
                    
                case 'openai':
                    $models['gpt-4o'] = 'GPT-4o (Latest)';
                    $models['gpt-4o-mini'] = 'GPT-4o Mini (Fast & Efficient)';
                    $models['gpt-4-turbo'] = 'GPT-4 Turbo (Advanced)';
                    $models['gpt-3.5-turbo'] = 'GPT-3.5 Turbo (Economical)';
                    break;
                    
                case 'anthropic':
                    $models['claude-3-5-sonnet-20241022'] = 'Claude 3.5 Sonnet (Latest)';
                    $models['claude-3-5-haiku-20241022'] = 'Claude 3.5 Haiku (Fast)';
                    $models['claude-3-opus-20240229'] = 'Claude 3 Opus (Most Capable)';
                    $models['claude-3-sonnet-20240229'] = 'Claude 3 Sonnet (Balanced)';
                    $models['claude-3-haiku-20240307'] = 'Claude 3 Haiku (Economical)';
                    break;
            }
        }
        
        // If no providers configured, return latest default
        if (empty($models)) {
            $models['gemini-2.5-flash'] = 'Gemini 2.5 Flash (Default)';
        }
        
        return $models;
    }

    /**
     * Strip HTML for textarea display while preserving structure
     *
     * @param string $html_content HTML content from AI
     * @return string Plain text for textarea display
     */
    public function strip_html_for_display($html_content) {
        // Pre-processing: Handle markdown-style formatting that might be converted to HTML
        $patterns = array(
            // Remove any extra whitespace and tabs first
            '/\s{4,}/' => ' ',
            '/\t+/' => ' ',
            
            // Convert headings to plain text with separators
            '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is' => "\n\n$1\n" . str_repeat('=', 50) . "\n\n",
            
            // Convert paragraphs
            '/<p[^>]*>(.*?)<\/p>/is' => "$1\n\n",
            
            // Convert line breaks
            '/<br\s*\/?>/i' => "\n",
            
            // Convert list items
            '/<li[^>]*>(.*?)<\/li>/is' => "• $1\n",
            
            // Remove list containers
            '/<\/ul>|<\/ol>/i' => "\n",
            '/<ul[^>]*>|<ol[^>]*>/i' => "",
            
            // Convert bold/strong - clean removal without asterisks
            '/<(strong|b)[^>]*>(.*?)<\/(strong|b)>/is' => "$2",
            
            // Convert italic/em - clean removal without asterisks
            '/<(em|i)[^>]*>(.*?)<\/(em|i)>/is' => "$2",
            
            // Remove any remaining HTML tags
            '/<[^>]*>/' => '',
        );
        
        $plain_text = $html_content;
        
        // First, handle any markdown-style asterisks that might be in the original content
        $plain_text = preg_replace('/\*{2,}([^*]+)\*{2,}/', '$1', $plain_text); // Remove **bold**
        $plain_text = preg_replace('/\*([^*]+)\*/', '$1', $plain_text); // Remove *italic*
        
        // Then apply HTML conversion patterns
        foreach ($patterns as $pattern => $replacement) {
            $plain_text = preg_replace($pattern, $replacement, $plain_text);
        }
        
        // Post-processing cleanup
        $plain_text = preg_replace('/\*{2,}/', '', $plain_text); // Remove any remaining asterisks
        $plain_text = preg_replace('/\n{3,}/', "\n\n", $plain_text); // Max 2 line breaks
        $plain_text = preg_replace('/[ \t]+/', ' ', $plain_text); // Multiple spaces to single
        $plain_text = preg_replace('/[ \t]*\n[ \t]*/', "\n", $plain_text); // Clean line breaks
        $plain_text = trim($plain_text);
        
        return $plain_text;
    }

    /**
     * Store original HTML for WordPress insertion
     *
     * @param string $html_content Original HTML content
     * @param string $cache_key Unique identifier
     */
    public function store_html_for_insertion($html_content, $cache_key) {
        set_transient("ai_html_content_{$cache_key}", $html_content, 3600); // Store for 1 hour
    }

    /**
     * Retrieve original HTML for WordPress insertion
     *
     * @param string $cache_key Unique identifier
     * @return string|false Original HTML content or false if not found
     */
    public function get_html_for_insertion($cache_key) {
        return get_transient("ai_html_content_{$cache_key}");
    }
    
    /**
     * Generate image using available AI service
     *
     * @param string $prompt Image description prompt
     * @param string $size Image size (e.g., '1024x1024')
     * @param string $model AI model to use for generation
     * @return array Result with success status and image_url or error
     */
    public function generate_image($prompt, $size = '1024x1024', $model = '') {
        $api_keys = get_option('ai_assistant_api_keys', array());
        
        // Determine which provider to use based on model or availability
        if (!empty($model)) {
            if (strpos($model, 'gpt') !== false || strpos($model, 'dall-e') !== false) {
                if (!empty($api_keys['openai'])) {
                    return $this->generate_image_openai($prompt, $size, $api_keys['openai']);
                }
            } elseif (strpos($model, 'claude') !== false) {
                if (!empty($api_keys['anthropic'])) {
                    return $this->generate_image_anthropic($prompt, $size, $api_keys['anthropic']);
                }
            } elseif (strpos($model, 'gemini') !== false) {
                if (!empty($api_keys['gemini'])) {
                    return $this->generate_image_gemini($prompt, $size, $api_keys['gemini']);
                }
            }
        }
        
        // Try providers in order of preference: OpenAI (DALL-E), then Gemini, then Anthropic
        if (!empty($api_keys['openai'])) {
            return $this->generate_image_openai($prompt, $size, $api_keys['openai']);
        }
        
        if (!empty($api_keys['gemini'])) {
            return $this->generate_image_gemini($prompt, $size, $api_keys['gemini']);
        }
        
        if (!empty($api_keys['anthropic'])) {
            return $this->generate_image_anthropic($prompt, $size, $api_keys['anthropic']);
        }
        
        // If no providers available, return error with helpful message
        return array(
            'success' => false,
            'error' => __('Image generation requires at least one configured AI provider (OpenAI, Google Gemini, or Anthropic Claude). Please configure your API keys in plugin settings.', 'ai-assistant')
        );
    }
    
    /**
     * Generate image using OpenAI DALL-E
     *
     * @param string $prompt Image description prompt
     * @param string $size Image size
     * @param string $api_key OpenAI API key
     * @return array Result with success status and image_url or error
     */
    private function generate_image_openai($prompt, $size, $api_key) {
        $cache_key = 'ai_image_' . md5($prompt . $size);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $endpoint = 'https://api.openai.com/v1/images/generations';
        
        $body = array(
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => 'standard',
            'response_format' => 'url'
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => __('Network error: ', 'ai-assistant') . $response->get_error_message()
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
            
            return array(
                'success' => false,
                'error' => sprintf(__('API Error (%d): %s', 'ai-assistant'), $response_code, $error_message)
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['data'][0]['url'])) {
            return array(
                'success' => false,
                'error' => __('Invalid response from image generation API', 'ai-assistant')
            );
        }
        
        $result = array(
            'success' => true,
            'image_url' => $data['data'][0]['url'],
            'revised_prompt' => isset($data['data'][0]['revised_prompt']) ? $data['data'][0]['revised_prompt'] : $prompt
        );
        
        // Cache for 1 hour
        set_transient($cache_key, $result, 3600);
        
        return $result;
    }
    
    /**
     * Generate image using Google Gemini or Imagen
     *
     * @param string $prompt Image description prompt
     * @param string $size Image size
     * @param string $api_key Gemini API key
     * @return array Result with success status and image_url or error
     */
    private function generate_image_gemini($prompt, $size, $api_key) {
        $cache_key = 'ai_image_gemini_' . md5($prompt . $size);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Try Imagen models first (specialized for image generation)
        $imagen_result = $this->generate_image_with_imagen($prompt, $size, $api_key);
        if ($imagen_result['success']) {
            return $imagen_result;
        }
        
        // Log that Imagen failed
        error_log('AI Assistant: Imagen failed, trying Gemini 2.0 Flash: ' . $imagen_result['error']);
        
        // Fallback to Gemini 2.0 Flash with image generation
        $flash_result = $this->generate_image_with_gemini_flash($prompt, $size, $api_key);
        if ($flash_result['success']) {
            return $flash_result;
        }
        
        // Log that both failed
        error_log('AI Assistant: Both Imagen and Gemini Flash failed');
        
        // If both Gemini methods fail, return helpful error message
        return array(
            'success' => false,
            'error' => __('Google Gemini image generation is not available in your region or requires special access. This feature may be limited to certain accounts or geographic areas. Please use OpenAI (DALL-E) for reliable image generation.', 'ai-assistant'),
            'provider' => 'gemini',
            'details' => array(
                'imagen_error' => $imagen_result['error'],
                'flash_error' => $flash_result['error']
            )
        );
    }
    
    /**
     * Generate image using Imagen models (specialized image generation)
     *
     * @param string $prompt Image description prompt
     * @param string $size Image size
     * @param string $api_key Gemini API key
     * @return array Result with success status and image_url or error
     */
    private function generate_image_with_imagen($prompt, $size, $api_key) {
        // Convert WordPress size format to Imagen aspect ratio
        $aspect_ratio = $this->convert_size_to_aspect_ratio($size);
        
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-001:generateImages';
        
        $body = array(
            'prompt' => $prompt,
            'config' => array(
                'numberOfImages' => 1,
                'aspectRatio' => $aspect_ratio,
                'personGeneration' => 'allow_adult'
            )
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $api_key,
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Assistant Imagen Error: ' . $response->get_error_message());
            return array(
                'success' => false,
                'error' => 'Imagen network error - will try Gemini 2.0 Flash',
                'provider' => 'imagen'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Log the response for debugging
        error_log('AI Assistant Imagen Response Code: ' . $response_code);
        error_log('AI Assistant Imagen Response Body: ' . substr($response_body, 0, 500));
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = 'Imagen API not available';
            
            if (isset($error_data['error']['message'])) {
                $error_message = $error_data['error']['message'];
                error_log('AI Assistant Imagen API Error: ' . $error_message);
            }
            
            // Return failure to trigger fallback to Gemini 2.0 Flash
            return array(
                'success' => false,
                'error' => 'Imagen unavailable: ' . $error_message,
                'provider' => 'imagen'
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['generatedImages'][0]['imageData'])) {
            error_log('AI Assistant Imagen: Invalid response structure');
            return array(
                'success' => false,
                'error' => 'Imagen response invalid - trying Gemini 2.0 Flash',
                'provider' => 'imagen'
            );
        }
        
        // Convert base64 image data to downloadable URL
        $image_data = $data['generatedImages'][0]['imageData'];
        $image_url = $this->convert_base64_to_url($image_data, 'imagen');
        
        if (!$image_url) {
            return array(
                'success' => false,
                'error' => 'Failed to save Imagen image - trying Gemini 2.0 Flash',
                'provider' => 'imagen'
            );
        }
        
        $result = array(
            'success' => true,
            'image_url' => $image_url,
            'provider' => 'imagen',
            'model' => 'imagen-3.0'
        );
        
        // Cache for 1 hour
        $cache_key = 'ai_image_imagen_' . md5($prompt . $size);
        set_transient($cache_key, $result, 3600);
        
        return $result;
    }
    
    /**
     * Generate image using Gemini 2.0 Flash with image generation capabilities
     *
     * @param string $prompt Image description prompt
     * @param string $size Image size
     * @param string $api_key Gemini API key
     * @return array Result with success status and image_url or error
     */
    private function generate_image_with_gemini_flash($prompt, $size, $api_key) {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';
        
        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => "Generate a high-quality image: " . $prompt
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'responseModalities' => array('TEXT', 'IMAGE'),
                'temperature' => 0.4,
                'topK' => 32,
                'topP' => 1.0,
                'maxOutputTokens' => 8192
            )
        );
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $api_key,
            ),
            'body' => json_encode($body),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            error_log('AI Assistant Gemini Flash Error: ' . $response->get_error_message());
            return array(
                'success' => false,
                'error' => __('Network error: ', 'ai-assistant') . $response->get_error_message(),
                'provider' => 'gemini-flash'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Log the response for debugging
        error_log('AI Assistant Gemini Flash Response Code: ' . $response_code);
        error_log('AI Assistant Gemini Flash Response Body: ' . substr($response_body, 0, 500));
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
            
            error_log('AI Assistant Gemini Flash API Error: ' . $error_message);
            
            // If this is also failing, return a helpful error message
            return array(
                'success' => false,
                'error' => sprintf(__('Gemini image generation is not available in your region or requires special access. API Error (%d): %s. Please use OpenAI (DALL-E) for image generation.', 'ai-assistant'), $response_code, $error_message),
                'provider' => 'gemini-flash'
            );
        }
        
        $data = json_decode($response_body, true);
        
        // Look for image data in the response
        if (!isset($data['candidates'][0]['content']['parts'])) {
            error_log('AI Assistant Gemini Flash: No content parts in response');
            return array(
                'success' => false,
                'error' => __('Gemini image generation not available. Please use OpenAI (DALL-E) for image generation.', 'ai-assistant'),
                'provider' => 'gemini-flash'
            );
        }
        
        $image_data = null;
        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                $image_data = $part['inlineData']['data'];
                break;
            }
        }
        
        if (!$image_data) {
            error_log('AI Assistant Gemini Flash: No image data found in response');
            return array(
                'success' => false,
                'error' => __('Gemini image generation feature is not enabled for your API key. Please use OpenAI (DALL-E) for image generation or try requesting access to Gemini image generation.', 'ai-assistant'),
                'provider' => 'gemini-flash'
            );
        }
        
        // Convert base64 image data to downloadable URL
        $image_url = $this->convert_base64_to_url($image_data, 'gemini-flash');
        
        if (!$image_url) {
            return array(
                'success' => false,
                'error' => __('Failed to process generated image', 'ai-assistant'),
                'provider' => 'gemini-flash'
            );
        }
        
        $result = array(
            'success' => true,
            'image_url' => $image_url,
            'provider' => 'gemini-flash',
            'model' => 'gemini-2.0-flash-exp'
        );
        
        // Cache for 1 hour
        $cache_key = 'ai_image_gemini_flash_' . md5($prompt . $size);
        set_transient($cache_key, $result, 3600);
        
        return $result;
    }
    
    /**
     * Convert WordPress image size to Imagen aspect ratio
     *
     * @param string $size WordPress size format (e.g., '1024x1024')
     * @return string Imagen aspect ratio format
     */
    private function convert_size_to_aspect_ratio($size) {
        switch ($size) {
            case '1024x1024':
            case '512x512':
                return '1:1';
            case '1024x1792':
            case '512x896':
                return '9:16';
            case '1792x1024':
            case '896x512':
                return '16:9';
            case '1024x1365':
                return '3:4';
            case '1365x1024':
                return '4:3';
            default:
                return '1:1'; // Default to square
        }
    }
    
    /**
     * Convert base64 image data to a downloadable URL
     *
     * @param string $base64_data Base64 encoded image data
     * @param string $provider Provider name for filename
     * @return string|false Image URL or false on failure
     */
    private function convert_base64_to_url($base64_data, $provider) {
        // Create unique filename
        $filename = 'ai-generated-' . $provider . '-' . time() . '-' . wp_generate_password(8, false) . '.png';
        
        // WordPress uploads directory
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        // Decode base64 data
        $image_content = base64_decode($base64_data);
        if ($image_content === false) {
            return false;
        }
        
        // Save image to uploads directory
        if (file_put_contents($file_path, $image_content) === false) {
            return false;
        }
        
        // Return public URL
        return $upload_dir['url'] . '/' . $filename;
    }
    
    /**
     * Generate image using Anthropic Claude (enhanced text-to-image descriptions)
     *
     * @param string $prompt Image description prompt
     * @param string $size Image size
     * @param string $api_key Anthropic API key
     * @return array Result with success status and image_url or error
     */
    private function generate_image_anthropic($prompt, $size, $api_key) {
        $cache_key = 'ai_image_anthropic_' . md5($prompt . $size);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Anthropic Claude doesn't have direct image generation capabilities yet
        // But we can enhance the prompt and provide detailed guidance for other tools
        
        $enhanced_prompt = "Create an extremely detailed, professional image description for generating: " . $prompt;
        $enhanced_prompt .= " Provide specific details about composition, lighting, colors, style, mood, and technical specifications that would help an AI image generator create the perfect image.";
        
        $url = 'https://api.anthropic.com/v1/messages';
        
        $body = array(
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 1000,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $enhanced_prompt
                )
            )
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode($body),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => __('Network error: ', 'ai-assistant') . $response->get_error_message(),
                'provider' => 'anthropic'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $data = json_decode($response_body, true);
            
            if (isset($data['content'][0]['text'])) {
                $enhanced_description = $data['content'][0]['text'];
                
                // Return detailed description with suggestion to use other providers
                return array(
                    'success' => false,
                    'error' => __('Anthropic Claude does not support direct image generation yet. However, Claude enhanced your prompt: ', 'ai-assistant') . "\n\n" . $enhanced_description . "\n\n" . __('Please use OpenAI (DALL-E) or Google Gemini for actual image generation.', 'ai-assistant'),
                    'provider' => 'anthropic',
                    'enhanced_prompt' => $enhanced_description
                );
            }
        }
        
        // Fallback error message
        return array(
            'success' => false,
            'error' => __('Anthropic Claude does not support direct image generation. Please use OpenAI (DALL-E) or Google Gemini for image generation.', 'ai-assistant'),
            'provider' => 'anthropic'
        );
    }
}
