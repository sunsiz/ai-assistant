<?php
/**
 * Debug script for Gemini image generation
 * This will help identify the exact issue with your Gemini API setup
 */

// Simulated WordPress environment for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Add your Gemini API key here for testing
$gemini_api_key = 'YOUR_GEMINI_API_KEY_HERE'; // Replace with your actual API key

/**
 * Test Gemini text generation first (to verify API key works)
 */
function test_gemini_text($api_key) {
    echo "=== Testing Gemini Text Generation ===\n";
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
    
    $body = array(
        'contents' => array(
            array(
                'parts' => array(
                    array(
                        'text' => 'Hello, please respond with "API working correctly"'
                    )
                )
            )
        )
    );
    
    $response = file_get_contents($url, false, stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($body)
        )
    )));
    
    if ($response === false) {
        echo "❌ Text generation failed - network error\n";
        return false;
    }
    
    $data = json_decode($response, true);
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        echo "✅ Text generation works: " . $data['candidates'][0]['content']['parts'][0]['text'] . "\n";
        return true;
    } else {
        echo "❌ Text generation failed\n";
        echo "Response: " . $response . "\n";
        return false;
    }
}

/**
 * Test Imagen 3.0 generation
 */
function test_imagen($api_key) {
    echo "\n=== Testing Imagen 3.0 Image Generation ===\n";
    
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-001:generateImages';
    
    $body = array(
        'prompt' => 'A beautiful sunset over mountains',
        'config' => array(
            'numberOfImages' => 1,
            'aspectRatio' => '1:1'
        )
    );
    
    $response = file_get_contents($url, false, stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nx-goog-api-key: $api_key\r\n",
            'content' => json_encode($body)
        )
    )));
    
    if ($response === false) {
        echo "❌ Imagen failed - network error\n";
        return false;
    }
    
    $data = json_decode($response, true);
    if (isset($data['generatedImages'][0]['imageData'])) {
        echo "✅ Imagen 3.0 works!\n";
        return true;
    } else {
        echo "❌ Imagen 3.0 failed\n";
        echo "Response: " . $response . "\n";
        return false;
    }
}

/**
 * Test Gemini 2.0 Flash with image generation
 */
function test_gemini_flash($api_key) {
    echo "\n=== Testing Gemini 2.0 Flash Image Generation ===\n";
    
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';
    
    $body = array(
        'contents' => array(
            array(
                'parts' => array(
                    array(
                        'text' => 'Generate a high-quality image: A beautiful sunset over mountains'
                    )
                )
            )
        ),
        'generationConfig' => array(
            'responseModalities' => array('TEXT', 'IMAGE'),
            'temperature' => 0.4
        )
    );
    
    $response = file_get_contents($url, false, stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nx-goog-api-key: $api_key\r\n",
            'content' => json_encode($body)
        )
    )));
    
    if ($response === false) {
        echo "❌ Gemini Flash failed - network error\n";
        return false;
    }
    
    $data = json_decode($response, true);
    
    // Check for image data
    if (isset($data['candidates'][0]['content']['parts'])) {
        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                echo "✅ Gemini 2.0 Flash image generation works!\n";
                return true;
            }
        }
    }
    
    echo "❌ Gemini 2.0 Flash image generation failed\n";
    echo "Response: " . $response . "\n";
    return false;
}

// Run tests
if ($gemini_api_key === 'YOUR_GEMINI_API_KEY_HERE') {
    echo "Please edit this file and add your Gemini API key to test.\n";
    exit;
}

echo "Gemini Image Generation Debug Tool\n";
echo "==================================\n\n";

// Test basic text generation first
$text_works = test_gemini_text($gemini_api_key);

if (!$text_works) {
    echo "\n❌ Basic Gemini API is not working. Check your API key and region.\n";
    exit;
}

// Test image generation methods
$imagen_works = test_imagen($gemini_api_key);
$flash_works = test_gemini_flash($gemini_api_key);

echo "\n=== Summary ===\n";
echo "Text Generation: " . ($text_works ? "✅ Working" : "❌ Failed") . "\n";
echo "Imagen 3.0: " . ($imagen_works ? "✅ Working" : "❌ Failed") . "\n";
echo "Gemini 2.0 Flash: " . ($flash_works ? "✅ Working" : "❌ Failed") . "\n";

if (!$imagen_works && !$flash_works) {
    echo "\n🔍 Diagnosis:\n";
    echo "Your Gemini API key works for text generation but not for image generation.\n";
    echo "This usually means:\n";
    echo "1. Image generation is not available in your region\n";
    echo "2. Your account doesn't have access to image generation features\n";
    echo "3. You need to upgrade to a paid tier\n";
    echo "\nRecommendation: Use OpenAI DALL-E for image generation instead.\n";
} elseif ($imagen_works) {
    echo "\n✅ Imagen 3.0 is working - your plugin should work with Gemini!\n";
} elseif ($flash_works) {
    echo "\n✅ Gemini 2.0 Flash is working - your plugin should work with Gemini!\n";
}
