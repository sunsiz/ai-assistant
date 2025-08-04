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
     * API endpoint for Gemini (using latest 2.5 Flash model)
     */
    private $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                AIAssistant::log('AI Service not configured - API key missing', true);
            }
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
        
        // Debug logging for translation prompt
        if (defined('WP_DEBUG') && WP_DEBUG) {
            AIAssistant::log('Translation prompt: ' . substr($prompt, 0, 300) . '...', true);
            AIAssistant::log('Source: ' . $source_name . ', Target: ' . $target_name . ', Model: ' . $model, true);
        }
        
        // Check cache
        $cache_key = $this->get_cache_key($prompt, 'translate');
        $cached_result = $this->get_cached_result($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
          // Make API request with model parameter
        $response = $this->make_api_request($prompt, $model);
        
        // Debug logging for API response
        if (defined('WP_DEBUG') && WP_DEBUG) {
            AIAssistant::log('AI Service API response: success=' . ($response['success'] ? 'true' : 'false') . 
                           ', content_length=' . (isset($response['content']) ? strlen($response['content']) : 0), true);
        }
        
        if ($response['success']) {
            $result = array(
                'success' => true,
                'translated_text' => $response['content'],
                'source_language' => $source_name,
                'target_language' => $target_name,
                'provider' => $this->get_current_provider(),
                'model' => $this->get_current_model()
            );
            
            // Cache the result only if successful
            $this->set_cached_result($cache_key, $result);
            
            return $result;
        } else {
            // Don't cache failed responses
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
        $prompt = "IMPORTANT: Translate the COMPLETE text below";
        
        if ($source_name !== 'auto') {
            $prompt .= " from {$source_name}";
        }
        
        $prompt .= " to {$target_name}. ";
        $prompt .= "You must translate the ENTIRE text - do not truncate or summarize. ";
        $prompt .= "Provide only the complete translated text without any additional explanations, prefixes, or formatting. ";
        $prompt .= "Do not add comments, notes, or explanatory text. ";
        $prompt .= "If the original text contains HTML tags, preserve them exactly. ";
        $prompt .= "If the original text is plain text, maintain the original structure and formatting as much as possible. ";
        $prompt .= "For WordPress content, ensure the output is compatible with WordPress editor. ";
        $prompt .= "CRITICAL: Translate every single word and sentence - the output must be complete.\n\n";
        $prompt .= "Text to translate:\n{$text}";
        
        return $prompt;
    }
      /**
     * Make API request based on current provider
     *
     * @param string $prompt The prompt to send
     * @param string $model Optional model to use
     * @param array $options Optional parameters like temperature, max_tokens
     * @return array Response data
     */
    private function make_api_request($prompt, $model = '', $options = array()) {
        $provider = $this->get_current_provider();
        
        switch ($provider) {
            case 'openai':
                return $this->make_openai_request($prompt, $model, $options);
            case 'anthropic':
                return $this->make_anthropic_request($prompt, $model, $options);
            case 'gemini':
            default:
                return $this->make_gemini_request($prompt, $model, $options);
        }
    }
    
    /**
     * Map frontend model names to actual API model names
     * 
     * Updated January 2025 based on https://ai.google.dev/gemini-api/docs/models
     * - Gemini 1.5 models are deprecated
     * - Current recommended models: 2.5 Pro, 2.5 Flash, 2.5 Flash-Lite, 2.0 Flash
     *
     * @param string $model Frontend model name
     * @return string Actual API model name
     */
    private function map_model_name($model) {
        $model_mapping = array(
            // Current recommended models (2025)
            'gemini-2.5-flash' => 'gemini-2.5-flash',
            'gemini-2.5-flash-lite' => 'gemini-2.5-flash-lite',
            'gemini-2.5-pro' => 'gemini-2.5-pro',
            'gemini-2.0-flash' => 'gemini-2.0-flash',
            
            // Legacy mappings for backward compatibility (deprecated models)
            'gemini-1.5-flash' => 'gemini-2.5-flash', // Redirect to current equivalent
            'gemini-1.5-pro' => 'gemini-2.5-pro',     // Redirect to current equivalent
            'gemini-1.5-flash-latest' => 'gemini-2.5-flash',
            'gemini-1.5-pro-latest' => 'gemini-2.5-pro',
            
            // Other legacy mappings
            'gemini-2.0-flash-exp' => 'gemini-2.0-flash',
            'gemini-pro' => 'gemini-2.5-pro',
            'gemini-flash' => 'gemini-2.5-flash'
        );
        
        return isset($model_mapping[$model]) ? $model_mapping[$model] : 'gemini-2.5-flash';
    }
    
    /**
     * Make API request to Gemini
     *
     * @param string $prompt The prompt to send
     * @param string $model Optional model to use
     * @param array $options Optional parameters like temperature, max_tokens
     * @return array Response data
     */
    private function make_gemini_request($prompt, $model = '', $options = array()) {
        // Use provided model or get current default
        $selected_model = !empty($model) ? $model : $this->get_current_model();
        
        // Ensure we have a valid Gemini model
        if (!str_contains($selected_model, 'gemini')) {
            $selected_model = 'gemini-2.5-flash'; // Use latest default
        }
        
        // Map the model name to the actual API model name
        $api_model_name = $this->map_model_name($selected_model);
        
        // Debug logging for model mapping
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = "[AI Assistant] Model mapping: {$selected_model} -> {$api_model_name}";
            
            // Add deprecation notice for 1.5 models
            if (in_array($selected_model, array('gemini-1.5-flash', 'gemini-1.5-pro'))) {
                $log_message .= " (DEPRECATED: Gemini 1.5 models are deprecated, redirected to Gemini 2.5)";
            }
            
            error_log($log_message);
        }
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$api_model_name}:generateContent?key=" . $this->api_key;
        
        // Default generation config
        $generation_config = array(
            'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.3,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => isset($options['max_tokens']) ? $options['max_tokens'] : 16384,
        );
        
        // For simple translations, try to minimize reasoning tokens
        if (isset($options['simple_translation']) && $options['simple_translation']) {
            $generation_config['temperature'] = 0.1;
            $generation_config['topK'] = 1;
            $generation_config['topP'] = 0.1;
        }
        
        // For content enhancement, try to balance creativity with efficiency
        if (isset($options['content_enhancement']) && $options['content_enhancement']) {
            $generation_config['topK'] = 20; // Reduced from default 40 to limit reasoning
            $generation_config['topP'] = 0.8; // Reduced from default 0.95 to focus responses
        }
        
        $contents = array(
            array(
                'parts' => array(
                    array(
                        'text' => $prompt
                    )
                ),
                'role' => 'user'
            )
        );
        
        // Add system instruction for simple translations to reduce reasoning
        if (isset($options['simple_translation']) && $options['simple_translation']) {
            array_unshift($contents, array(
                'parts' => array(
                    array(
                        'text' => 'You are a direct translator. Provide only the translation without reasoning or explanation.'
                    )
                ),
                'role' => 'user'
            ));
        }
        
        $body = array(
            'contents' => $contents,
            'generationConfig' => $generation_config,
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                AIAssistant::log('Gemini API request error: ' . $response->get_error_message(), true);
            }
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
            } elseif (isset($error_data['error']['details'])) {
                // Some APIs provide details in different structure
                $error_message .= ': ' . (is_array($error_data['error']['details']) ? 
                    implode(', ', $error_data['error']['details']) : 
                    $error_data['error']['details']);
            } elseif ($response_code === 429) {
                // Rate limiting - provide specific message
                $error_message = 'Rate limit exceeded. Please wait a few minutes before trying again.';
            } elseif ($response_code === 403) {
                // Quota exceeded
                $error_message = 'API quota exceeded. Please check your API key or try again later.';
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                AIAssistant::log('Gemini API error (code ' . $response_code . '): ' . $response_body, true);
            }
            
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        $data = json_decode($response_body, true);
        
        // Debug logging for API response
        if (defined('WP_DEBUG') && WP_DEBUG) {
            AIAssistant::log('Gemini API response code: ' . $response_code, true);
            AIAssistant::log('Gemini API full response: ' . $response_body, true);
            AIAssistant::log('Gemini API response data structure: ' . print_r(array_keys($data), true), true);
            
            // Check for finish reason
            if (isset($data['candidates'][0]['finishReason'])) {
                AIAssistant::log('Gemini API finish reason: ' . $data['candidates'][0]['finishReason'], true);
            }
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $response_text = $data['candidates'][0]['content']['parts'][0]['text'];
                AIAssistant::log('Gemini API response text length: ' . strlen($response_text), true);
                AIAssistant::log('Gemini API response preview: ' . substr($response_text, 0, 200), true);
            } else {
                AIAssistant::log('Gemini API response missing expected text field', true);
            }
        }
        
        // Check if response structure is valid
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return array(
                'success' => false,
                'error' => 'Invalid response format from API'
            );
        }
        
        $response_text = trim($data['candidates'][0]['content']['parts'][0]['text']);
        
        // Check for MAX_TOKENS finish reason with empty content
        if (isset($data['candidates'][0]['finishReason']) && 
            $data['candidates'][0]['finishReason'] === 'MAX_TOKENS' && 
            empty($response_text)) {
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                AIAssistant::log('Gemini API hit MAX_TOKENS with empty response - treating as failure', true);
            }
            
            return array(
                'success' => false,
                'error' => 'Response truncated due to token limit (MAX_TOKENS)'
            );
        }
        
        return array(
            'success' => true,
            'content' => $response_text
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
     * @param array $options Optional parameters like temperature, max_tokens
     * @return array Response data
     */
    private function make_openai_request($prompt, $model = '', $options = array()) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        // Use provided model or default
        $selected_model = !empty($model) ? $model : 'gpt-4.1';
        
        $body = array(
            'model' => $selected_model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.3,
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : 2000
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
     * @param array $options Optional parameters like temperature, max_tokens
     * @return array Response data
     */
    private function make_anthropic_request($prompt, $model = '', $options = array()) {
        $url = 'https://api.anthropic.com/v1/messages';
        
        // Use provided model or default
        $selected_model = !empty($model) ? $model : 'claude-opus-4-20250514';
        
        $body = array(
            'model' => $selected_model,
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : 2000,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        // Add temperature if specified (Anthropic API supports it)
        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }
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
        
        // Create the prompt based on content type with language detection
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
            
            // Cache the result only if successful
            $this->set_cached_result($cache_key, $result);
            
            return $result;
        } else {
            // Don't cache failed responses
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
        // Detect language from context and existing content
        $detected_language = $this->detect_content_language($context, $existing_content);
        
        // Get user's preferred language
        $user_language = $this->get_user_language();
        
        // Determine response language
        $response_language = $this->determine_response_language($detected_language, $user_language);
        
        // Debug logging for language detection
        if (defined('WP_DEBUG') && WP_DEBUG) {
            AIAssistant::log("Content suggestion language detection - Context: '{$context}', Detected: {$detected_language}, User: {$user_language}, Response: {$response_language}", true);
        }
        
        $base_context = !empty($existing_content) ? 
            "Based on this existing content: \"{$existing_content}\" and the topic: \"{$context}\"" : 
            "Based on the topic: \"{$context}\"";
            
        // Add language instruction
        $language_instruction = "";
        if ($response_language !== 'en') {
            // Universal approach: Let the AI determine the language name based on the detected code
            // This supports all languages dynamically without hardcoding specific mappings
            $language_instruction = " \n\nCRITICAL LANGUAGE REQUIREMENT: You MUST respond COMPLETELY and ENTIRELY in the same language as the user's input (detected as '{$response_language}'). This includes:\n- ALL titles and headings\n- ALL explanations and descriptions\n- ALL introductory text\n- ALL body content\n- ALL examples\n- EVERY SINGLE WORD must be in the detected language\n\nDO NOT mix languages. DO NOT include English translations in parentheses. DO NOT provide English explanations. The user has provided input in their native language, so they understand it and expect the complete response in that same language only. Use the natural, native form of the language without language codes or technical references.";
        } else {
            // Even for English, be explicit about language consistency
            $language_instruction = " \n\nProvide all content and explanations in clear, consistent English.";
        }
              
        switch ($type) {
            case 'suggestions':
                $format_instruction = "Format as an HTML ordered list (&lt;ol&gt;&lt;li&gt;) with brief explanations. Use proper HTML formatting for WordPress.";
                if ($response_language !== 'en') {
                    return "{$base_context}, provide 5-7 specific content suggestions or ideas for blog posts, articles, or content pieces. Each suggestion should be practical and actionable. {$format_instruction}{$language_instruction}";
                } else {
                    return "{$base_context}, provide 5-7 specific content suggestions or ideas for blog posts, articles, or content pieces. Each suggestion should be practical and actionable. {$format_instruction}{$language_instruction}";
                }
                
            case 'full-article':
                return "{$base_context}, write a complete, comprehensive article suitable for WordPress. The article should be well-structured with an engaging introduction, detailed body sections with subheadings, practical examples or insights, and a strong conclusion. Aim for 800-1500 words. Use clear, engaging language and include actionable advice where appropriate. Format with proper HTML headings (&lt;h2&gt; for main headings, &lt;h3&gt; for subheadings), paragraphs (&lt;p&gt;), and lists (&lt;ul&gt;&lt;li&gt; or &lt;ol&gt;&lt;li&gt;) as needed. Maintain a professional yet accessible tone. Output clean HTML that works well in WordPress editor.{$language_instruction}";
                
            case 'keywords':
                return "{$base_context}, generate 10-15 relevant SEO keywords and phrases. Include both short-tail (1-2 words) and long-tail (3-5 words) keywords. Focus on search intent and relevance. Format as an HTML unordered list (&lt;ul&gt;&lt;li&gt;) ordered by relevance, with each keyword as a separate list item.{$language_instruction}";
                
            case 'meta-description':
                return "{$base_context}, write 2-3 compelling meta descriptions (each 150-160 characters) that would encourage clicks from search results. Focus on benefits, urgency, and clear value proposition. Format as an HTML ordered list (&lt;ol&gt;&lt;li&gt;) with each option as a separate list item.{$language_instruction}";
                
            case 'title-ideas':
                return "{$base_context}, generate 8-10 engaging title ideas that would work well for blog posts, articles, or web pages. Include a mix of question-based, list-based, how-to, and benefit-driven titles. Make them compelling and SEO-friendly. Format as an HTML unordered list (&lt;ul&gt;&lt;li&gt;) with each title as a separate list item.{$language_instruction}";
                
            default:
                return "{$base_context}, provide helpful content suggestions related to this topic. Format using proper HTML for WordPress.{$language_instruction}";
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
            AIAssistant::log('Using cached result for key: ' . $cache_key, true);
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
     * Get language names mapping (universal)
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
            'fa' => 'Persian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'az' => 'Azerbaijani',
            'uz' => 'Uzbek',
            'ky' => 'Kyrgyz',
            'ug' => 'Uyghur',
            'ur' => 'Urdu',
            'tk' => 'Turkmen',
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
                'models' => array('gpt-4.1', 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo')
            );
        }
        
        if (!empty($api_keys['anthropic'])) {
            $configured['anthropic'] = array(
                'name' => 'Anthropic',
                'models' => array('claude-4-sonnet', 'claude-4-opus')
            );
        }
        
        if (!empty($api_keys['gemini']) || !empty($api_keys['google'])) {
            $configured['gemini'] = array(
                'name' => 'Google Gemini',
                'models' => array(
                    'gemini-2.5-flash',
                    'gemini-2.5-flash-lite',
                    'gemini-2.5-pro',  
                    'gemini-2.0-flash'
                )
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
                return 'gpt-4.1'; // Latest flagship model
            case 'anthropic':
                return 'claude-opus-4-20250514';
            case 'gemini':
            default:
                return 'gemini-2.5-flash'; // Latest recommended default
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
            'max_tokens' => 1024
        );
        
        $options = array_merge($default_options, $options);
        
        // Create appropriate cache key based on usage context
        $cache_context = 'suggestion';
        if (strpos($prompt, 'ALL original') !== false || strpos($prompt, 'COMPLETE meaning') !== false) {
            $cache_context = 'enhancement';
        } elseif (strpos($prompt, 'translate') !== false) {
            $cache_context = 'translation';
        }
        
        // Check cache with context-specific key
        $cache_key = $this->get_cache_key($prompt, $cache_context);
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        try {
            $model = isset($options['model']) ? $options['model'] : '';
            $response = $this->make_api_request($prompt, $model, $options);
            
            // Debug: Log the raw API response
            if (defined('WP_DEBUG') && WP_DEBUG) {
                AIAssistant::log("make_api_request_public raw response: " . wp_json_encode($response), true);
            }
            
            // Check if response indicates an error first
            if (isset($response['success']) && $response['success'] === false) {
                // Return the error response directly
                return $response;
            }
            
            // Cache successful results with context-specific timeout - BUT ONLY if content is not empty
            if ($response && isset($response['content']) && !empty(trim($response['content']))) {
                $success_response = array(
                    'success' => true,
                    'content' => $response['content']
                );
                
                // Different cache times for different contexts
                $cache_timeout = 300; // Default 5 minutes for suggestions
                if ($cache_context === 'enhancement') {
                    $cache_timeout = 600; // 10 minutes for enhancements (more expensive)
                } elseif ($cache_context === 'translation') {
                    $cache_timeout = 1800; // 30 minutes for translations (most expensive)
                }
                
                // Debug: Log successful caching
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    AIAssistant::log("Caching successful response with context '{$cache_context}' for {$cache_timeout}s. Content length: " . strlen($response['content']), true);
                }
                
                set_transient($cache_key, $success_response, $cache_timeout);
                return $success_response;
            }
            
            // Debug: Log when content is missing or empty
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (!isset($response['content'])) {
                    AIAssistant::log("make_api_request_public: No content field in response - NOT caching", true);
                } elseif (empty(trim($response['content']))) {
                    AIAssistant::log("make_api_request_public: Content is empty - NOT caching", true);
                } else {
                    AIAssistant::log("make_api_request_public: Content exists but response structure invalid - NOT caching", true);
                }
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
     * Detect content language from input text
     *
     * @param string $context Topic or context text
     * @param string $existing_content Optional existing content
     * @return string Language code or 'auto' if undetermined
     */
    private function detect_content_language($context, $existing_content = '') {
        $combined_text = trim($context . ' ' . $existing_content);
        
        if (empty($combined_text)) {
            return 'auto';
        }
        
        // Simple language detection patterns
        $language_patterns = array(
            // Uyghur - distinctive Unicode ranges and common words
            'ug' => array(
                '/[\x{0626}-\x{06FF}]/u', // Arabic-based script used by Uyghur
                '/[\x{FE70}-\x{FEFF}]/u', // Arabic presentation forms
                '/(بىر|ئىككى|ئۈچ|تۆت|بەش|ئالتە|يەتتە|سەككىز|توققۇز|ئون)/u', // Uyghur numbers
                '/(بولۇش|قىلىش|ئېيتىش|كۆرۈش|بىلىش)/u' // Common Uyghur verbs
            ),
            // Arabic
            'ar' => array(
                '/[\x{0600}-\x{06FF}]/u',
                '/(هذا|هذه|ذلك|تلك|في|من|إلى|على|أن)/u'
            ),
            // Chinese
            'zh' => array(
                '/[\x{4E00}-\x{9FFF}]/u',
                '/(的|是|在|有|一|个|我|你|他|她)/u'
            ),
            // Russian
            'ru' => array(
                '/[\x{0400}-\x{04FF}]/u',
                '/(это|что|как|где|когда|почему|который)/u'
            ),
            // Turkish
            'tr' => array(
                '/[ıİğĞüÜşŞöÖçÇ]/u',
                '/(bir|iki|üç|dört|beş|altı|yedi|sekiz|dokuz|on)/u'
            ),
            // Spanish
            'es' => array(
                '/[ñáéíóúü]/ui',
                '/(que|con|por|para|una|del|las|los|como)/u'
            ),
            // French
            'fr' => array(
                '/[àâäéèêëïîôöùûüÿç]/ui',
                '/(que|avec|pour|dans|par|sur|comme|mais)/u'
            ),
            // German
            'de' => array(
                '/[äöüßÄÖÜ]/u',
                '/(und|oder|aber|mit|für|von|zu|bei|nach)/u'
            )
        );
        
        // Check each language pattern
        foreach ($language_patterns as $lang_code => $patterns) {
            $matches = 0;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $combined_text)) {
                    $matches++;
                }
            }
            // If we have multiple pattern matches, consider it detected
            if ($matches >= 2) {
                return $lang_code;
            }
        }
        
        return 'auto';
    }
    
    /**
     * Get user's preferred language
     *
     * @return string Language code
     */
    private function get_user_language() {
        $current_user_id = get_current_user_id();
        
        if ($current_user_id > 0) {
            $user_language = get_user_meta($current_user_id, 'ai_assistant_language', true);
            if (!empty($user_language)) {
                return $this->convert_locale_to_language_name($user_language);
            }
        }
        
        // Fallback to site language
        $site_locale = get_locale();
        return $this->convert_locale_to_language_name($site_locale);
    }
    
    /**
     * Determine response language based on detection and user preference
     *
     * @param string $detected_language
     * @param string $user_language
     * @return string
     */
    private function determine_response_language($detected_language, $user_language) {
        // If we detected a specific language in the input, use that
        if ($detected_language !== 'auto') {
            return $this->convert_code_to_language_name($detected_language);
        }
        
        // Otherwise use user's preferred language
        return $user_language;
    }
    
    /**
     * Convert locale code to language name
     *
     * @param string $locale
     * @return string
     */
    private function convert_locale_to_language_name($locale) {
        $language_map = array(
            'ug' => 'Uyghur',
            'ug_CN' => 'Uyghur',
            'ar' => 'Arabic',
            'ar_SA' => 'Arabic', 
            'zh_CN' => 'Chinese',
            'zh_TW' => 'Chinese',
            'ru_RU' => 'Russian',
            'tr_TR' => 'Turkish',
            'es_ES' => 'Spanish',
            'fr_FR' => 'French',
            'de_DE' => 'German',
            'en_US' => 'en',
            'en_GB' => 'en'
        );
        
        if (isset($language_map[$locale])) {
            return $language_map[$locale];
        }
        
        // Extract base language code
        $base_code = substr($locale, 0, 2);
        if (isset($language_map[$base_code])) {
            return $language_map[$base_code];
        }
        
        return 'en';
    }
    
    /**
     * Convert language code to language name
     *
     * @param string $code
     * @return string
     */
    private function convert_code_to_language_name($code) {
        $code_map = array(
            'ug' => 'Uyghur',
            'ar' => 'Arabic',
            'zh' => 'Chinese',
            'ru' => 'Russian',
            'tr' => 'Turkish',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German'
        );
        
        return isset($code_map[$code]) ? $code_map[$code] : 'en';
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
        
        AIAssistant::log('Cleared ' . $count . ' cache entries of type: ' . $type, true);
        return $count;
    }

    /**
     * Get available models for configured providers
     *
     * @return array
     */
    public function get_available_models() {
        $models = array();
        
        $configured_providers = array_keys($this->get_configured_providers());
        
        foreach ($configured_providers as $provider) {
            switch ($provider) {
                case 'gemini':
                    $models['gemini-2.5-flash'] = 'Gemini 2.5 Flash (Latest & Fast - Best Price-Performance)';
                    $models['gemini-2.5-flash-lite'] = 'Gemini 2.5 Flash-Lite (Most Cost-Efficient)';
                    $models['gemini-2.5-pro'] = 'Gemini 2.5 Pro (Most Advanced - Thinking Model)';
                    $models['gemini-2.0-flash'] = 'Gemini 2.0 Flash (Next Generation Features)';
                    break;
                    
                case 'openai':
                    $models['gpt-4.1'] = 'GPT-4.1 (Latest Flagship Model)';
                    $models['gpt-4o'] = 'GPT-4o (Advanced Reasoning Model)';
                    $models['gpt-4o-mini'] = 'GPT-4o Mini (Fast Reasoning Model)';
                    $models['gpt-4-turbo'] = 'GPT-4 Turbo (Legacy - High Performance)';
                    $models['gpt-4'] = 'GPT-4 (Legacy - Reliable)';
                    break;
                    
                case 'anthropic':
                    $models['claude-sonnet-4-20250514'] = 'Claude Sonnet 4 (Smart, efficient model for everyday use)';
                    $models['claude-opus-4-20250514'] = 'Claude Opus 4 (Most Capable)';
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
     * Get available models for image generation only
     *
     * @return array
     */
    public function get_available_image_models() {
        $models = array();
        
        $configured_providers = array_keys($this->get_configured_providers());
        
        foreach ($configured_providers as $provider) {
            switch ($provider) {
                case 'openai':
                    // OpenAI supports DALL-E for image generation
                    $models['gpt-4.1'] = 'DALL-E via GPT-4.1 (Latest Flagship)';
                    $models['gpt-4o'] = 'DALL-E via GPT-4o (Advanced Reasoning)';
                    $models['gpt-4o-mini'] = 'DALL-E via GPT-4o Mini (Fast Reasoning)';
                    break;
                    
                case 'gemini':
                    // Gemini 2.0 has native image generation (free tier)
                    $models['gemini-2.0-flash'] = 'Gemini 2.0 Flash (Native Image Generation - Free)';
                    $models['gemini-2.5-flash'] = 'Gemini 2.5 Flash (Text + Fallback Image)';
                    $models['gemini-2.5-pro'] = 'Gemini 2.5 Pro (Text + Fallback Image)';
                    break;
                    
                case 'anthropic':
                    // Anthropic Claude with image generation capabilities
                    $models['claude-sonnet-4-20250514'] = 'Claude Sonnet 4 (Image Generation)';
                    $models['claude-opus-4-20250514'] = 'Claude Opus 4 (Advanced Image Generation)';
                    break;
            }
        }
        
        // If no providers configured, return empty array with message
        if (empty($models)) {
            return array();
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
        
        // If specific model is provided, use the corresponding provider
        if (!empty($model)) {
            // OpenAI models
            if (in_array($model, array('gpt-4.1', 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4', 'dall-e-3', 'dall-e-2'))) {
                if (!empty($api_keys['openai'])) {
                    return $this->generate_image_openai($prompt, $size, $api_keys['openai']);
                } else {
                    return array(
                        'success' => false,
                        'error' => __('OpenAI API key not configured for the selected model.', 'ai-assistant')
                    );
                }
            }
            // Anthropic models
            elseif (in_array($model, array('claude-sonnet-4-20250514', 'claude-opus-4-20250514'))) {
                if (!empty($api_keys['anthropic'])) {
                    return $this->generate_image_anthropic($prompt, $size, $api_keys['anthropic']);
                } else {
                    return array(
                        'success' => false,
                        'error' => __('Anthropic API key not configured for the selected model.', 'ai-assistant')
                    );
                }
            }
            // Gemini models (current and legacy) - Note: Imagen is now paid-only
            elseif (in_array($model, array('gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.5-flash-lite', 'gemini-2.0-flash', 'gemini-1.5-flash', 'gemini-1.5-pro'))) {
                if (!empty($api_keys['gemini'])) {
                    $gemini_result = $this->generate_image_gemini($prompt, $size, $api_keys['gemini']);
                    
                    // If Gemini fails and OpenAI is available, fallback to OpenAI with a notice
                    if (!$gemini_result['success'] && !empty($api_keys['openai'])) {
                        $openai_result = $this->generate_image_openai($prompt, $size, $api_keys['openai']);
                        if ($openai_result['success']) {
                            // Add a notice about the fallback
                            $openai_result['fallback_notice'] = __('Gemini image generation is not available. Used OpenAI DALL-E instead.', 'ai-assistant');
                        }
                        return $openai_result;
                    }
                    
                    return $gemini_result;
                } else {
                    return array(
                        'success' => false,
                        'error' => __('Google Gemini API key not configured for the selected model.', 'ai-assistant')
                    );
                }
            }
            // Unknown model
            else {
                return array(
                    'success' => false,
                    'error' => sprintf(__('Unknown model "%s" specified for image generation.', 'ai-assistant'), $model)
                );
            }
        }
        
        // No model specified - try providers in order of preference: OpenAI (DALL-E), then Gemini, then Anthropic
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
        // Use Gemini 2.0 Flash Preview Image Generation (free tier)
        $model = 'gemini-2.0-flash-preview-image-generation';
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
        
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
                'responseModalities' => array('TEXT', 'IMAGE'),
                'temperature' => 0.4,
                'topK' => 32,
                'topP' => 0.95,
                'maxOutputTokens' => 8192
            )
        );
        
        $args = array(
            'body' => wp_json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 60,
            'sslverify' => true
        );
        
        $response = wp_remote_post($endpoint, $args);
        
        if (is_wp_error($response)) {
            AIAssistant::log('Gemini 2.0 image generation error: ' . $response->get_error_message(), true);
            return array(
                'success' => false,
                'error' => 'Network error: ' . $response->get_error_message(),
                'provider' => 'gemini'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Enhanced debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            AIAssistant::log('Gemini 2.0 image generation response code: ' . $response_code, true);
            AIAssistant::log('Gemini 2.0 image generation response preview: ' . substr($response_body, 0, 500), true);
        }
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
            
            AIAssistant::log('Gemini 2.0 image generation API error: ' . $error_message, true);
            
            return array(
                'success' => false,
                'error' => sprintf(__('Gemini 2.0 API error (%d): %s', 'ai-assistant'), $response_code, $error_message),
                'provider' => 'gemini'
            );
        }
        
        $data = json_decode($response_body, true);
        
        // Check for candidates in response
        if (!isset($data['candidates'][0]['content']['parts'])) {
            AIAssistant::log('Gemini 2.0 image generation: No content parts in response', true);
            return array(
                'success' => false,
                'error' => 'Gemini 2.0 returned an unexpected response format. Check API key permissions for image generation.',
                'provider' => 'gemini'
            );
        }
        
        // Look for inline image data in response parts
        $image_data = null;
        $text_content = '';
        
        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                $image_data = $part['inlineData']['data'];
                AIAssistant::log('Gemini 2.0: Found image data in response', true);
            }
            if (isset($part['text'])) {
                $text_content .= $part['text'] . ' ';
            }
        }
        
        if (!$image_data) {
            AIAssistant::log('Gemini 2.0: No image data found in response. Text content: ' . substr($text_content, 0, 200), true);
            return array(
                'success' => false,
                'error' => 'Gemini 2.0 did not generate an image. The model responded with text only: ' . substr(trim($text_content), 0, 200) . '... Try asking more explicitly for image generation.',
                'provider' => 'gemini',
                'text_response' => trim($text_content)
            );
        }
        
        // Convert base64 image data to downloadable URL
        $image_url = $this->convert_base64_to_url($image_data, 'gemini-2.0');
        
        if (!$image_url) {
            return array(
                'success' => false,
                'error' => 'Failed to process generated image from Gemini 2.0',
                'provider' => 'gemini'
            );
        }
        
        // Cache the result
        $cache_key = 'ai_image_gemini_' . md5($prompt . $size);
        set_transient($cache_key, $image_url, 3600); // Cache for 1 hour
        
        return array(
            'success' => true,
            'image_url' => $image_url,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash-preview-image-generation',
            'text_response' => trim($text_content)
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
        
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-generate-preview-06-06-generate-001:generateImages';
        
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
            AIAssistant::log('Imagen Error: ' . $response->get_error_message(), true);
            return array(
                'success' => false,
                'error' => 'Imagen network error - will try Gemini 2.5 Flash',
                'provider' => 'imagen'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Log the response for debugging
        AIAssistant::log('Imagen Response Code: ' . $response_code, true);
        AIAssistant::log('Imagen Response Body: ' . substr($response_body, 0, 500), true);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = 'Imagen API not available';
            
            if (isset($error_data['error']['message'])) {
                $error_message = $error_data['error']['message'];
                AIAssistant::log('Imagen API Error: ' . $error_message, true);
            }
            
            // Return failure to trigger fallback to Gemini 2.5 Flash
            return array(
                'success' => false,
                'error' => 'Imagen unavailable: ' . $error_message,
                'provider' => 'imagen'
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['generatedImages'][0]['imageData'])) {
            AIAssistant::log('Imagen: Invalid response structure', true);
            return array(
                'success' => false,
                'error' => 'Imagen response invalid - trying Gemini 2.5 Flash',
                'provider' => 'imagen'
            );
        }
        
        // Convert base64 image data to downloadable URL
        $image_data = $data['generatedImages'][0]['imageData'];
        $image_url = $this->convert_base64_to_url($image_data, 'imagen');
        
        if (!$image_url) {
            return array(
                'success' => false,
                'error' => 'Failed to save Imagen image - trying Gemini 2.5 Flash',
                'provider' => 'imagen'
            );
        }
        
        $result = array(
            'success' => true,
            'image_url' => $image_url,
            'provider' => 'imagen',
            'model' => 'imagen-4.0-generate-preview-06-06'
        );
        
        // Cache for 1 hour
        $cache_key = 'ai_image_imagen_' . md5($prompt . $size);
        set_transient($cache_key, $result, 3600);
        
        return $result;
    }
    
    /**
     * Generate image using Gemini 2.5 Flash with image generation capabilities
     *
     * @param string $prompt Image description prompt
     * @param string $size Image size
     * @param string $api_key Gemini API key
     * @return array Result with success status and image_url or error
     */
    private function generate_image_with_gemini_flash($prompt, $size, $api_key) {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
        
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
            AIAssistant::log('Gemini Flash Error: ' . $response->get_error_message(), true);
            return array(
                'success' => false,
                'error' => __('Network error: ', 'ai-assistant') . $response->get_error_message(),
                'provider' => 'gemini-flash'
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Log the response for debugging
        AIAssistant::log('Gemini Flash Response Code: ' . $response_code, true);
        AIAssistant::log('Gemini Flash Response Body: ' . substr($response_body, 0, 500), true);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown API error';
            
            AIAssistant::log('Gemini Flash API Error: ' . $error_message, true);
            
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
            AIAssistant::log('Gemini Flash: No content parts in response', true);
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
            AIAssistant::log('Gemini Flash: No image data found in response', true);
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
            'model' => 'gemini-2.5-flash'
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
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 1024,
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
