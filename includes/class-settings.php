<?php
/**
 * Settings Class
 * Handles plugin settings and configuration
 *
 * @package AIAssistant
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Assistant Settings Class
 */
class AI_Assistant_Settings {
      /**
     * Constructor
     */
    public function __construct() {
        // Only initialize settings on admin requests
        if (is_admin()) {
            add_action('admin_init', array($this, 'init_settings'));
        }
    }
      /**
     * Initialize settings
     */
    public function init_settings() {        // Register settings
        register_setting('ai_assistant_settings', 'ai_assistant_enabled');
        register_setting('ai_assistant_settings', 'ai_assistant_default_model');
        register_setting('ai_assistant_settings', 'ai_assistant_preferred_provider');
        register_setting('ai_assistant_settings', 'ai_assistant_api_keys');
        register_setting('ai_assistant_settings', 'ai_assistant_prompts');
        
        // Add settings sections
        add_settings_section(
            'ai_assistant_general',
            __('General Settings', 'ai-assistant'),
            array($this, 'general_section_callback'),
            'ai_assistant_settings'
        );
        
        add_settings_section(
            'ai_assistant_api',
            __('API Keys', 'ai-assistant'),
            array($this, 'api_section_callback'),
            'ai_assistant_settings'
        );
        
        add_settings_section(
            'ai_assistant_prompts',
            __('Custom Prompts', 'ai-assistant'),
            array($this, 'prompts_section_callback'),
            'ai_assistant_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'ai_assistant_enabled',
            __('Enable AI Assistant', 'ai-assistant'),
            array($this, 'enabled_field_callback'),
            'ai_assistant_settings',
            'ai_assistant_general'
        );
        
        add_settings_field(
            'ai_assistant_default_model',
            __('Default AI Model', 'ai-assistant'),
            array($this, 'default_model_field_callback'),
            'ai_assistant_settings',
            'ai_assistant_general'
        );
        
        add_settings_field(
            'ai_assistant_preferred_provider',
            __('Preferred AI Provider', 'ai-assistant'),
            array($this, 'preferred_provider_field_callback'),
            'ai_assistant_settings',
            'ai_assistant_general'
        );
        
        add_settings_field(
            'ai_assistant_openai_key',
            __('OpenAI API Key', 'ai-assistant'),
            array($this, 'openai_key_field_callback'),
            'ai_assistant_settings',
            'ai_assistant_api'
        );
        
        add_settings_field(
            'ai_assistant_anthropic_key',
            __('Anthropic API Key', 'ai-assistant'),
            array($this, 'anthropic_key_field_callback'),
            'ai_assistant_settings',
            'ai_assistant_api'
        );
        
        add_settings_field(
            'ai_assistant_gemini_key',
            __('Gemini API Key', 'ai-assistant'),
            array($this, 'gemini_key_field_callback'),
            'ai_assistant_settings',
            'ai_assistant_api'
        );
        
        add_settings_field(
            'ai_assistant_translation_prompt',
            __('Translation Prompt', 'ai-assistant'),
            array($this, 'translation_prompt_field_callback'),
            'ai_assistant_settings',
            'ai_assistant_prompts'
        );
        
        add_settings_field(
            'ai_assistant_content_prompt',
            __('Content Suggestion Prompt', 'ai-assistant'),
            array($this, 'content_prompt_field_callback'),
            'ai_assistant_settings',
            'ai_assistant_prompts'
        );
        
        add_settings_field(
            'ai_assistant_seo_prompt',
            __('SEO Keywords Prompt', 'ai-assistant'),
            array($this, 'seo_prompt_field_callback'),
            'ai_assistant_settings',
            'ai_assistant_prompts'
        );
    }
      /**
     * Initialize method (fallback)
     */
    public function init() {
        // Only run settings initialization in admin area
        if (is_admin()) {
            $this->init_settings();
        }
    }
    
    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure general AI Assistant settings.', 'ai-assistant') . '</p>';
    }    /**
     * API section callback
     */
    public function api_section_callback() {
        echo '<p>' . __('Configure API keys for different AI providers. Currently using Gemini for translations, but you can configure multiple providers for future use.', 'ai-assistant') . '</p>';
    }
    
    /**
     * Prompts section callback
     */
    public function prompts_section_callback() {
        echo '<p>' . __('Customize the prompts sent to AI models. Use placeholders like {source_language} and {target_language} for translation prompts.', 'ai-assistant') . '</p>';
    }
    
    /**
     * Enabled field callback
     */
    public function enabled_field_callback() {
        $enabled = get_option('ai_assistant_enabled', true);
        echo '<input type="checkbox" id="ai_assistant_enabled" name="ai_assistant_enabled" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="ai_assistant_enabled">' . __('Enable AI Assistant functionality', 'ai-assistant') . '</label>';
    }
      /**
     * Default model field callback
     */
    public function default_model_field_callback() {
        $default_model = get_option('ai_assistant_default_model', 'gemini-2.5-flash');
        
        // Available models array (since AI service might not be available)
        $models = array(
            'OpenAI' => array(
                'gpt-o3' => 'GPT-o3',
                'gpt-40' => 'GPT-40',
                'gpt-4.1' => 'GPT-4.1'
            ),
            'Google' => array(
                'gemini-pro' => 'Gemini Pro',
                'gemini-2.5-flash' => 'Gemini 2.5 Flash'
            ),
            'Anthropic' => array(
                'claude-4-sonnet' => 'Claude 4 Sonnet',
                'claude-3.7-sonnet' => 'Claude 3.7 Sonnet'
            )
        );
        
        echo '<select id="ai_assistant_default_model" name="ai_assistant_default_model">';
        foreach ($models as $provider => $provider_models) {
            echo '<optgroup label="' . esc_html($provider) . '">';
            foreach ($provider_models as $model_id => $model_name) {
                echo '<option value="' . esc_attr($model_id) . '" ' . selected($model_id, $default_model, false) . '>' . esc_html($model_name) . '</option>';
            }
            echo '</optgroup>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Select the default AI model to use for requests.', 'ai-assistant') . '</p>';
    }
      /**
     * Preferred provider field callback
     */
    public function preferred_provider_field_callback() {
        $preferred_provider = get_option('ai_assistant_preferred_provider', 'gemini');
        
        // Available providers array
        $providers = array(
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'gemini' => 'Google Gemini'
        );
        
        echo '<select id="ai_assistant_preferred_provider" name="ai_assistant_preferred_provider">';
        foreach ($providers as $provider_id => $provider_name) {
            echo '<option value="' . esc_attr($provider_id) . '" ' . selected($provider_id, $preferred_provider, false) . '>' . esc_html($provider_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Select your preferred AI provider for requests.', 'ai-assistant') . '</p>';
    }
      /**
     * Gemini API key field callback
     */
    public function gemini_key_field_callback() {
        $api_keys = get_option('ai_assistant_api_keys', array());
        $gemini_key = isset($api_keys['gemini']) ? $api_keys['gemini'] : '';
        
        echo '<input type="password" id="ai_assistant_gemini_key" name="ai_assistant_api_keys[gemini]" value="' . esc_attr($gemini_key) . '" class="regular-text" placeholder="AIza..." />';
        echo '<p class="description">' . __('Enter your Google Gemini API key. Get it from <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>', 'ai-assistant') . '</p>';
    }
    
    /**
     * OpenAI API key field callback
     */
    public function openai_key_field_callback() {
        $api_keys = get_option('ai_assistant_api_keys', array());
        $openai_key = isset($api_keys['openai']) ? $api_keys['openai'] : '';
        
        echo '<input type="password" id="ai_assistant_openai_key" name="ai_assistant_api_keys[openai]" value="' . esc_attr($openai_key) . '" class="regular-text" placeholder="sk-..." />';
        echo '<p class="description">' . __('Enter your OpenAI API key. Get it from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>', 'ai-assistant') . '</p>';
    }
    
    /**
     * Anthropic API key field callback
     */
    public function anthropic_key_field_callback() {
        $api_keys = get_option('ai_assistant_api_keys', array());
        $anthropic_key = isset($api_keys['anthropic']) ? $api_keys['anthropic'] : '';
        
        echo '<input type="password" id="ai_assistant_anthropic_key" name="ai_assistant_api_keys[anthropic]" value="' . esc_attr($anthropic_key) . '" class="regular-text" placeholder="sk-ant-..." />';
        echo '<p class="description">' . __('Enter your Anthropic API key. Get it from <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>', 'ai-assistant') . '</p>';
    }

    /**
     * Translation prompt field callback
     */
    public function translation_prompt_field_callback() {
        $prompts = get_option('ai_assistant_prompts', array());
        $default_prompt = 'Translate the following content from {source_language} to {target_language}. Maintain the original formatting and structure:';
        $translation_prompt = isset($prompts['translation']) ? $prompts['translation'] : $default_prompt;
        
        echo '<textarea id="ai_assistant_translation_prompt" name="ai_assistant_prompts[translation]" rows="3" cols="50" class="large-text">' . esc_textarea($translation_prompt) . '</textarea>';
        echo '<p class="description">' . __('Customize the prompt for translation requests. Use {source_language} and {target_language} as placeholders.', 'ai-assistant') . '</p>';
    }
    
    /**
     * Content prompt field callback
     */
    public function content_prompt_field_callback() {
        $prompts = get_option('ai_assistant_prompts', array());
        $default_prompt = 'Based on the following title and existing content on the website, suggest relevant content:';
        $content_prompt = isset($prompts['content_suggestion']) ? $prompts['content_suggestion'] : $default_prompt;
        
        echo '<textarea id="ai_assistant_content_prompt" name="ai_assistant_prompts[content_suggestion]" rows="3" cols="50" class="large-text">' . esc_textarea($content_prompt) . '</textarea>';
        echo '<p class="description">' . __('Customize the prompt for content suggestions.', 'ai-assistant') . '</p>';
    }
    
    /**
     * SEO prompt field callback
     */
    public function seo_prompt_field_callback() {
        $prompts = get_option('ai_assistant_prompts', array());
        $default_prompt = 'Suggest SEO-friendly keywords for the following content:';
        $seo_prompt = isset($prompts['seo_keywords']) ? $prompts['seo_keywords'] : $default_prompt;
        
        echo '<textarea id="ai_assistant_seo_prompt" name="ai_assistant_prompts[seo_keywords]" rows="3" cols="50" class="large-text">' . esc_textarea($seo_prompt) . '</textarea>';
        echo '<p class="description">' . __('Customize the prompt for SEO keyword suggestions.', 'ai-assistant') . '</p>';
    }
}
