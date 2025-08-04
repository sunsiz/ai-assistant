<?php
/**
 * Content Analyzer class for AI-powered content analysis and suggestions
 *
 * @package AIAssistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Assistant_Content_Analyzer {
    
    /**
     * AI Service instance
     */
    private $ai_service;
      /**
     * Constructor
     */
    public function __construct() {
        // We'll initialize AI service when needed to avoid dependency issues
    }
    
    /**
     * Initialize AI service if not already initialized
     */
    private function ensure_ai_service() {
        if (!$this->ai_service) {
            global $ai_assistant;
            if ($ai_assistant && property_exists($ai_assistant, 'ai_service')) {
                $this->ai_service = $ai_assistant->ai_service;
            } else {
                // Fallback: create new instance if available
                if (class_exists('AI_Assistant_AI_Service')) {
                    $this->ai_service = new AI_Assistant_AI_Service();
                }
            }
        }
    }
    
    /**
     * Suggest content based on title
     *
     * @param string $title Post title
     * @param string $model AI model to use
     * @return array Content suggestions
     */
    public function suggest_content($title, $model) {
        // Ensure AI service is available
        $this->ensure_ai_service();
        if (!$this->ai_service) {
            return array('success' => false, 'message' => 'AI service not available');
        }
        // Get site language for localized suggestions
        $site_language = $this->get_site_language();
        $language_name = $this->get_language_name($site_language);
        
        // Get existing content from site for context
        $context = $this->get_site_content_context($title);
        
        // Create enhanced prompt with site language context
        $prompts = get_option('ai_assistant_prompts', array());
        $base_prompt = isset($prompts['content_suggestion']) ? $prompts['content_suggestion'] : 'Based on the title "{title}" and analyzing similar content on {language} websites about this topic, suggest comprehensive article content that would be valuable for readers. Focus on practical information and insights. Please respond in {language}:';
        
        $prompt = str_replace(array('{title}', '{language}'), array($title, $language_name), $base_prompt);
        
        if (!empty($context)) {
            $prompt .= "\n\nContext from existing site content:\n" . $context;
        }
        
        $prompt .= "\n\nPlease provide a detailed article outline and content structure for: " . $title . " (respond in " . $language_name . ")";
        
        $result = $this->ai_service->send_request($model, $prompt, array('max_tokens' => 3000));
        
        if ($result['success']) {
            return array(
                'success' => true,
                'content' => $result['content'],
                'title' => $title,
                'model' => $model,
                'usage' => isset($result['usage']) ? $result['usage'] : null
            );
        }
        
        return $result;
    }
    
    /**
     * Analyze content and provide improvement suggestions
     *
     * @param string $content Content to analyze
     * @param string $model AI model to use
     * @return array Analysis results
     */
    public function analyze_content($content, $model) {
        // Ensure AI service is available
        $this->ensure_ai_service();
        if (!$this->ai_service) {
            return array('success' => false, 'message' => 'AI service not available');
        }
        
        // Sanitize inputs
        $content = wp_strip_all_tags($content);
        $model = sanitize_text_field($model);
        
        if (empty($content)) {
            return array('success' => false, 'message' => __('Content cannot be empty', 'ai-assistant'));
        }
        
        // Get site language for localized analysis
        $site_language = $this->get_site_language();
        $language_name = $this->get_language_name($site_language);
        
        $prompts = get_option('ai_assistant_prompts', array());
        $prompt_template = isset($prompts['content_analysis']) ? $prompts['content_analysis'] : 'Analyze the following content and provide suggestions for improvement, including readability, structure, SEO optimization, and engagement. Please respond in {language}:';
        
        $prompt = str_replace('{language}', $language_name, $prompt_template) . "\n\n" . $content;
        
        $result = $this->ai_service->send_request($model, $prompt, array('max_tokens' => 2500));
        
        if ($result['success']) {
            return array(
                'success' => true,
                'content' => $result['content'],
                'model' => $model,
                'usage' => isset($result['usage']) ? $result['usage'] : null
            );
        }
        
        return $result;
    }
    
    /**
     * Generate SEO keywords for content
     *
     * @param string $content Content to analyze
     * @param string $model AI model to use
     * @return array SEO keywords
     */
    public function generate_seo_keywords($content, $model) {
        // Ensure AI service is available
        $this->ensure_ai_service();
        if (!$this->ai_service) {
            return array('success' => false, 'message' => 'AI service not available');
        }
        
        $result = $this->ai_service->generate_seo_keywords($content, $model);
        
        if ($result['success']) {
            // Parse keywords from the response
            $keywords = $this->parse_keywords_from_response($result['content']);
            
            return array(
                'success' => true,
                'keywords' => $keywords,
                'raw_response' => $result['content'],
                'model' => $model,
                'usage' => isset($result['usage']) ? $result['usage'] : null
            );
        }
        
        return $result;
    }
    
    /**
     * Improve existing content
     *
     * @param string $content Original content
     * @param string $improvement_type Type of improvement (readability, seo, engagement, etc.)
     * @param string $model AI model to use
     * @return array Improved content
     */
    public function improve_content($content, $improvement_type, $model) {
        // Ensure AI service is available
        $this->ensure_ai_service();
        if (!$this->ai_service) {
            return array('success' => false, 'message' => 'AI service not available');
        }
        
        // Sanitize inputs
        $content = wp_strip_all_tags($content);
        $improvement_type = sanitize_text_field($improvement_type);
        $model = sanitize_text_field($model);
        
        if (empty($content)) {
            return array('success' => false, 'error' => __('Content cannot be empty', 'ai-assistant'));
        }
        
        // Get site language for localized improvements
        $site_language = $this->get_site_language();
        $language_name = $this->get_language_name($site_language);
        
        $improvement_prompts = array(
            'readability' => 'Please improve the readability of this content while keeping all the original information and length. Make it clearer and easier to understand. Respond only with the improved content in {language}:',
            'seo' => 'Please optimize this content for SEO by improving keywords, structure, and readability while maintaining all original information. Respond only with the optimized content in {language}:',
            'engagement' => 'Please rewrite this content to be more engaging and compelling while preserving all original information and topics. Respond only with the improved content in {language}:',
            'structure' => 'Please improve the structure and organization of this content while maintaining all original information. Respond only with the restructured content in {language}:',
            'tone-adjustment' => 'Please adjust the tone of this content to be more professional while preserving all original information and topics. Respond only with the improved content in {language}:',
            'general' => 'Please improve this content for better clarity, readability, and engagement while preserving all original information. Respond only with the improved content in {language}:'
        );
        
        $prompt_template = isset($improvement_prompts[$improvement_type]) ? $improvement_prompts[$improvement_type] : $improvement_prompts['general'];
        $prompt = str_replace('{language}', $language_name, $prompt_template);
        $prompt .= "\n\n" . $content;
        
        // Calculate appropriate max_tokens based on content length
        $content_length = strlen($content);
        $estimated_input_tokens = ceil($content_length / 3.5); // More accurate estimate: ~3.5 chars per token
        
        // Set max_tokens dynamically but more conservatively to avoid timeouts
        // Gemini 2.5 Flash supports up to 1M input tokens and 8192 output tokens
        if ($content_length > 15000) {
            $max_tokens = 7000; // Increased from 6000 for very large content
        } elseif ($content_length > 10000) {
            $max_tokens = 6000; // Large content
        } elseif ($content_length > 5000) {
            $max_tokens = 5000; // Medium content
        } elseif ($content_length > 2000) {
            $max_tokens = 4000; // Small-medium content
        } else {
            $max_tokens = 3000; // Small content
        }
        
        // Ensure we don't exceed reasonable limits to prevent timeouts
        $total_estimated_tokens = $estimated_input_tokens + $max_tokens;
        if ($total_estimated_tokens > 20000) { // More conservative limit
            $max_tokens = max(2000, 20000 - $estimated_input_tokens); // Ensure minimum 2000 output tokens
        }
        
        // Log content enhancement parameters for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            AIAssistant::log("Content Enhancement Debug - Length: {$content_length} chars, Est. Input Tokens: {$estimated_input_tokens}, Max Output Tokens: {$max_tokens}, Type: {$improvement_type}", true);
        }
        
        $start_time = microtime(true);
        $result = $this->ai_service->make_api_request_public($prompt, array(
            'max_tokens' => $max_tokens * 2, // Double the tokens to account for excessive thoughts tokens
            'temperature' => 0.7,  // Good balance for creative improvement
            'top_p' => 0.9,        // Focus on high-probability tokens
            'content_enhancement' => true // Flag for content enhancement optimization
        ));
        $end_time = microtime(true);
        
        // Debug: Log the API response
        if (defined('WP_DEBUG') && WP_DEBUG) {
            AIAssistant::log("Content Enhancement API Response: " . wp_json_encode($result), true);
        }
        
        // Log processing time for large content debugging
        if ($content_length > 5000 && defined('WP_DEBUG') && WP_DEBUG) {
            $processing_time = round($end_time - $start_time, 2);
            AIAssistant::log("Content Enhancement Timing - {$content_length} chars processed in {$processing_time}s", true);
        }
        
        if ($result['success']) {
            // Check for empty response with better validation
            $enhanced_content = isset($result['content']) ? trim($result['content']) : '';
            
            // More thorough empty response check
            if (empty($enhanced_content) || strlen($enhanced_content) < 10) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    AIAssistant::log("Content Enhancement Warning: Empty or too short response received. Length: " . strlen($enhanced_content), true);
                    AIAssistant::log("Content Enhancement Response raw: " . print_r($result, true), true);
                }
                
                // Clear any cached failed response before returning error
                $this->clear_enhancement_cache($improvement_type, $content);
                
                return array(
                    'success' => false,
                    'error' => __('The AI service returned an empty response. Please try again in a moment.', 'ai-assistant')
                );
            }
            
            // Validate that enhanced content is not significantly shorter than original
            $enhanced_length = strlen($enhanced_content);
            $length_ratio = $enhanced_length > 0 ? $enhanced_length / $content_length : 0;
            
            // Debug log the result for large content
            if ($content_length > 5000 && defined('WP_DEBUG') && WP_DEBUG) {
                AIAssistant::log("Content Enhancement Result - Input: {$content_length} chars, Output: {$enhanced_length} chars, Ratio: " . round($length_ratio, 2) . ", Type: {$improvement_type}", true);
                AIAssistant::log("Content Enhancement Output Preview: " . substr($enhanced_content, 0, 200) . "...", true);
            }
            
            // If enhanced content is less than 50% of original length, warn but still return result
            $warning_message = null;
            if ($length_ratio < 0.5 && $content_length > 1000) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    AIAssistant::log("Content Enhancement Warning: Output significantly shorter than input (Ratio: " . round($length_ratio, 2) . "). Original: {$content_length} chars, Enhanced: {$enhanced_length} chars", true);
                }
                $warning_message = __('Enhanced content appears shorter than original. You may want to try again.', 'ai-assistant');
            }
            
            return array(
                'success' => true,
                'improved_content' => $enhanced_content,
                'improvement_type' => $improvement_type,
                'model' => $model,
                'usage' => isset($result['usage']) ? $result['usage'] : null,
                'warning' => $warning_message
            );
        } else {
            // Handle specific error cases for better user experience
            $error_message = isset($result['error']) ? $result['error'] : __('Failed to enhance content', 'ai-assistant');
            
            // Check if this looks like a timeout or rate limit error
            if (stripos($error_message, 'timeout') !== false || stripos($error_message, 'time') !== false) {
                $error_message = __('Request timed out. Please try with shorter content or try again later.', 'ai-assistant');
            } elseif (stripos($error_message, 'rate') !== false || stripos($error_message, 'limit') !== false) {
                $error_message = __('Rate limit reached. Please try again in a few minutes.', 'ai-assistant');
            } elseif (stripos($error_message, 'quota') !== false) {
                $error_message = __('API quota exceeded. Please try again later.', 'ai-assistant');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                AIAssistant::log("Content Enhancement Failed - Error: " . $error_message . ", Content Length: {$content_length}", true);
            }
            
            return array(
                'success' => false,
                'error' => $error_message
            );
        }
        
        return $result;
    }
    
    /**
     * Get content context from existing site posts
     *
     * @param string $title Post title for context
     * @return string Content context
     */
    private function get_site_content_context($title) {
        // Search for related posts based on title keywords
        $keywords = $this->extract_keywords_from_title($title);
        
        if (empty($keywords)) {
            return '';
        }
        
        // Get recent posts that might be related
        $related_posts = get_posts(array(
            'numberposts' => 5,
            'post_status' => 'publish',
            's' => implode(' ', $keywords),
            'meta_query' => array(
                array(
                    'key' => '_ai_assistant_analyzed',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        $context = '';
        foreach ($related_posts as $post) {
            $excerpt = wp_trim_words($post->post_content, 50);
            $context .= "Title: " . $post->post_title . "\n";
            $context .= "Excerpt: " . $excerpt . "\n\n";
        }
        
        return substr($context, 0, 1500); // Limit context size
    }
    
    /**
     * Extract keywords from title
     *
     * @param string $title Post title
     * @return array Keywords
     */
    private function extract_keywords_from_title($title) {
        // Remove common words
        $common_words = array(
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'bir', 've', 'veya', 'ama', 'için', 'ile', 'bu', 'şu', 'o', 'da', 'de', 'ki'
        );
        
        $words = explode(' ', strtolower($title));
        $keywords = array();
        
        foreach ($words as $word) {
            $word = trim($word, '.,!?;:"()[]{}');
            if (strlen($word) > 2 && !in_array($word, $common_words)) {
                $keywords[] = $word;
            }
        }
        
        return $keywords;
    }
    
    /**
     * Parse keywords from AI response
     *
     * @param string $response AI response containing keywords
     * @return array Parsed keywords
     */
    private function parse_keywords_from_response($response) {
        $keywords = array();
        
        // Try to extract keywords from various formats
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and headers
            if (empty($line) || strpos($line, ':') === false) {
                continue;
            }
            
            // Look for lines that contain keywords
            if (preg_match('/^\d+\.?\s*(.+)$/', $line, $matches)) {
                $keywords[] = trim($matches[1]);
            } elseif (preg_match('/^[-\*]\s*(.+)$/', $line, $matches)) {
                $keywords[] = trim($matches[1]);
            }
        }
        
        // If no structured keywords found, try comma-separated
        if (empty($keywords)) {
            $keywords = array_map('trim', explode(',', $response));
        }
        
        // Clean up keywords
        $keywords = array_filter($keywords, function($keyword) {
            return !empty($keyword) && strlen($keyword) > 2;
        });
        
        return array_values($keywords);
    }
    
    /**
     * Get content readability score
     *
     * @param string $content Content to analyze
     * @return array Readability metrics
     */
    public function get_readability_score($content) {
        $word_count = str_word_count(strip_tags($content));
        $sentence_count = preg_match_all('/[.!?]+/', $content);
        $paragraph_count = substr_count($content, "\n\n") + 1;
        
        $avg_words_per_sentence = $sentence_count > 0 ? $word_count / $sentence_count : 0;
        $avg_sentences_per_paragraph = $paragraph_count > 0 ? $sentence_count / $paragraph_count : 0;
        
        // Simple readability assessment based on averages
        $readability_score = 100;
        
        if ($avg_words_per_sentence > 20) {
            $readability_score -= 20;
        } elseif ($avg_words_per_sentence > 15) {
            $readability_score -= 10;
        }
        
        if ($avg_sentences_per_paragraph > 5) {
            $readability_score -= 15;
        } elseif ($avg_sentences_per_paragraph > 3) {
            $readability_score -= 5;
        }
        
        return array(
            'score' => max(0, min(100, $readability_score)),
            'word_count' => $word_count,
            'sentence_count' => $sentence_count,
            'paragraph_count' => $paragraph_count,
            'avg_words_per_sentence' => round($avg_words_per_sentence, 1),
            'avg_sentences_per_paragraph' => round($avg_sentences_per_paragraph, 1)
        );
    }
    
    /**
     * Store content analysis results
     *
     * @param int $post_id Post ID
     * @param array $analysis_data Analysis results
     * @return bool Success status
     */
    public function store_analysis_results($post_id, $analysis_data) {
        return update_post_meta($post_id, '_ai_assistant_analysis', $analysis_data);
    }
    
    /**
     * Get stored analysis results
     *
     * @param int $post_id Post ID
     * @return array Analysis results
     */
    public function get_analysis_results($post_id) {
        return get_post_meta($post_id, '_ai_assistant_analysis', true);
    }
      /**
     * Fetch and extract content from URL
     *
     * @param string $url URL to fetch content from
     * @return string|WP_Error Extracted content or error
     */
    public function fetch_and_extract($url) {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid URL provided.');
        }
        
        // Check if DOMDocument is available
        if (!class_exists('DOMDocument')) {
            return new WP_Error('missing_dom', 'DOMDocument extension is required but not available.');
        }
          // Use WordPress HTTP API with longer timeout
        $response = wp_remote_get($url, array(
            'timeout' => 60, // Increased from 30 to 60 seconds
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
            )
        ));
        
        if (is_wp_error($response)) {
            AIAssistant::log("wp_remote_get error: " . $response->get_error_message(), true);
            return new WP_Error('fetch_error', 'Failed to fetch URL: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            return new WP_Error('http_error', 'HTTP Error: ' . $status_code);
        }
        
        if (empty($body)) {
            return new WP_Error('empty_content', 'No content found at the URL.');
        }
        
        // Extract content from HTML
        $content = $this->extract_content_from_html($body);
        
        if (empty($content)) {
            return new WP_Error('extraction_error', 'Could not extract meaningful content from the page.');
        }
        
        return $content;
    }
    
    /**
     * Extract meaningful content from HTML
     *
     * @param string $html HTML content
     * @return string Extracted text content
     */
    private function extract_content_from_html($html) {
        // Remove script and style elements
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Try to find main content areas
        $content_selectors = array(
            'article',
            '.content',
            '.post-content',
            '.entry-content',
            '.article-content',
            'main',
            '#content',
            '.main-content'
        );
        
        $extracted_content = '';
        
        // Use DOMDocument to parse HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
          // Try content selectors
        foreach ($content_selectors as $selector) {
            $elements = $xpath->query("//*[contains(@class, '" . trim($selector, '.') . "') or local-name() = '" . trim($selector, '.') . "' or @id = '" . trim($selector, '#') . "']");
            
            if ($elements->length > 0) {
                foreach ($elements as $element) {
                    $formatted_text = $this->extract_formatted_text($element);
                    if (strlen($formatted_text) > 100) { // Only consider substantial content
                        $extracted_content .= $formatted_text . "\n\n";
                    }
                }
                if (!empty($extracted_content)) {
                    break;
                }
            }
        }
        
        // Fallback: extract all text from body
        if (empty($extracted_content)) {
            $body_elements = $xpath->query('//body');
            if ($body_elements->length > 0) {
                $extracted_content = $this->extract_formatted_text($body_elements->item(0));
            }
        }          // Clean up the extracted content
        $extracted_content = preg_replace('/\n{3,}/', "\n\n", $extracted_content); // Max 2 consecutive newlines
        $extracted_content = preg_replace('/[ \t]+/', ' ', $extracted_content); // Normalize spaces
        $extracted_content = trim($extracted_content);
        
        // Limit content length but increase the limit
        if (strlen($extracted_content) > 15000) {
            $extracted_content = substr($extracted_content, 0, 15000) . '...';
        }
        
        return $extracted_content;
    }
    
    /**
     * Extract text while preserving basic formatting and structure
     *
     * @param DOMNode $element The DOM element to extract from
     * @return string Formatted text content
     */
    private function extract_formatted_text($element) {
        $text = '';
        
        foreach ($element->childNodes as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text .= trim($node->textContent);
            } elseif ($node->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($node->nodeName);
                
                switch ($tag) {
                    case 'p':
                    case 'div':
                    case 'h1':
                    case 'h2':
                    case 'h3':
                    case 'h4':
                    case 'h5':
                    case 'h6':
                        $text .= "\n\n" . trim($this->extract_formatted_text($node)) . "\n\n";
                        break;
                    case 'br':
                        $text .= "\n";
                        break;
                    case 'li':
                        $text .= "\n- " . trim($this->extract_formatted_text($node));
                        break;
                    case 'ul':
                    case 'ol':
                        $text .= "\n" . $this->extract_formatted_text($node) . "\n";
                        break;
                    case 'blockquote':
                        $text .= "\n\n> " . trim($this->extract_formatted_text($node)) . "\n\n";
                        break;                    case 'strong':
                    case 'b':
                        $text .= $this->extract_formatted_text($node);
                        break;
                    case 'em':
                    case 'i':
                        $text .= $this->extract_formatted_text($node);
                        break;
                    case 'script':
                    case 'style':
                    case 'nav':
                    case 'header':
                    case 'footer':
                    case 'aside':
                        // Skip these elements
                        break;
                    default:
                        $text .= $this->extract_formatted_text($node);
                        break;
                }
            }
        }
        
        return $text;
    }
    
    /**
     * Get the site language code
     *
     * @return string Language code (e.g., 'tr', 'en', 'fr')
     */
    private function get_site_language() {
        // First check AI Assistant plugin setting
        $ai_language = get_option('ai_assistant_language', '');
        if (!empty($ai_language)) {
            return $ai_language;
        }
        
        // Fallback to WordPress locale
        $locale = get_locale();
        
        // Universal language code extraction using the same method as main plugin
        return $this->extract_language_code($locale);
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
     * Get the human-readable language name
     *
     * @param string $language_code Language code
     * @return string Language name
     */
    private function get_language_name($language_code) {
        // Universal language names
        $language_names = $this->get_language_names();
        
        return isset($language_names[$language_code]) ? $language_names[$language_code] : 'English';
    }
    
    /**
     * Get universal language names
     *
     * @return array Language code to name mapping
     */
    private function get_language_names() {
        return array(
            'en' => 'English',
            'tr' => 'Turkish',
            'fr' => 'French',
            'de' => 'German',
            'es' => 'Spanish',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'ar' => 'Arabic',
            'ug' => 'Uyghur',
            'fa' => 'Persian',
            'nl' => 'Dutch',
            'da' => 'Danish',
            'fi' => 'Finnish',
            'az' => 'Azerbaijani',
            'uz' => 'Uzbek',
            'ky' => 'Kyrgyz',
            'ur' => 'Urdu',
            'tk' => 'Turkmen'
        );
    }
    
    /**
     * Clear cached enhancement results for failed responses
     *
     * @param string $improvement_type Type of improvement
     * @param string $content Original content
     */
    private function clear_enhancement_cache($improvement_type, $content) {
        // Generate the same cache key that would be used
        $cache_key = 'ai_assistant_enhancement_' . md5($improvement_type . '_' . $content);
        delete_transient($cache_key);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            AIAssistant::log("Cleared enhancement cache for key: " . $cache_key, true);
        }
    }
}
