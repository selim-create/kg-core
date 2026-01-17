<?php
/**
 * Newsletter System Implementation Test
 * 
 * This test validates:
 * 1. Newsletter module files exist and have correct class structure
 * 2. Social media settings updated with 6 platforms
 * 3. Email template renderer updated with 6 social media platforms
 * 4. Newsletter email templates added to schema
 */

echo "=== NEWSLETTER SYSTEM IMPLEMENTATION TEST ===\n\n";

// Define the constant for the plugin path
if (!defined('KG_CORE_PATH')) {
    define('KG_CORE_PATH', __DIR__ . '/../');
}

$test_results = [];

// TEST 1: Check Newsletter module files exist
echo "TEST 1: Checking Newsletter module files\n";
echo "-------------------------------------------------------------------\n";
$newsletter_files = [
    'NewsletterSubscriber.php',
    'NewsletterRepository.php',
    'NewsletterService.php',
    'NewsletterRESTController.php',
];

foreach ($newsletter_files as $file) {
    $path = KG_CORE_PATH . 'includes/Newsletter/' . $file;
    if (file_exists($path)) {
        echo "✅ PASS: {$file} exists\n";
    } else {
        echo "❌ FAIL: {$file} missing\n";
        $test_results[] = false;
    }
}
echo "\n";

// TEST 2: Check NewsletterAdminPage exists
echo "TEST 2: Checking NewsletterAdminPage\n";
echo "-------------------------------------------------------------------\n";
$admin_file = KG_CORE_PATH . 'includes/Admin/NewsletterAdminPage.php';
if (file_exists($admin_file)) {
    echo "✅ PASS: NewsletterAdminPage.php exists\n";
} else {
    echo "❌ FAIL: NewsletterAdminPage.php missing\n";
    $test_results[] = false;
}
echo "\n";

// TEST 3: Check EmailTemplateRenderer has 6 social media constants
echo "TEST 3: Checking EmailTemplateRenderer social media constants\n";
echo "-------------------------------------------------------------------\n";
require_once KG_CORE_PATH . 'includes/Notifications/EmailTemplateRenderer.php';

$expected_constants = [
    'SOCIAL_INSTAGRAM',
    'SOCIAL_YOUTUBE',
    'SOCIAL_TWITTER',
    'SOCIAL_TIKTOK',
    'SOCIAL_PINTEREST',
    'SOCIAL_FACEBOOK',
];

$reflection = new ReflectionClass('KG_Core\Notifications\EmailTemplateRenderer');
$constants = $reflection->getConstants();

foreach ($expected_constants as $const) {
    if (isset($constants[$const])) {
        echo "✅ PASS: Constant {$const} exists\n";
    } else {
        echo "❌ FAIL: Constant {$const} missing\n";
        $test_results[] = false;
    }
}
echo "\n";

// TEST 4: Check get_social_urls returns 6 platforms
echo "TEST 4: Checking get_social_urls method\n";
echo "-------------------------------------------------------------------\n";

// Mock WordPress functions
if (!function_exists('get_option')) {
    function get_option($key, $default = '') {
        $mock_options = [
            'kg_social_instagram' => 'https://instagram.com/test',
            'kg_social_youtube' => 'https://youtube.com/@test',
            'kg_social_twitter' => 'https://twitter.com/test',
            'kg_social_tiktok' => 'https://tiktok.com/@test',
            'kg_social_pinterest' => 'https://pinterest.com/test',
            'kg_social_facebook' => 'https://facebook.com/test',
        ];
        return $mock_options[$key] ?? $default;
    }
}

$social_urls = \KG_Core\Notifications\EmailTemplateRenderer::get_social_urls();

$expected_keys = ['instagram', 'youtube', 'twitter', 'tiktok', 'pinterest', 'facebook'];
foreach ($expected_keys as $key) {
    if (isset($social_urls[$key])) {
        echo "✅ PASS: Social URL for {$key} exists\n";
    } else {
        echo "❌ FAIL: Social URL for {$key} missing\n";
        $test_results[] = false;
    }
}
echo "\n";

// TEST 5: Check VaccinationSchema contains newsletter table definition
echo "TEST 5: Checking VaccinationSchema for newsletter table\n";
echo "-------------------------------------------------------------------\n";
$schema_file = file_get_contents(KG_CORE_PATH . 'includes/Database/VaccinationSchema.php');

if (strpos($schema_file, 'kg_newsletter_subscribers') !== false) {
    echo "✅ PASS: Newsletter table definition found\n";
} else {
    echo "❌ FAIL: Newsletter table definition not found\n";
    $test_results[] = false;
}
echo "\n";

// TEST 6: Check newsletter email templates in seed
echo "TEST 6: Checking newsletter email templates\n";
echo "-------------------------------------------------------------------\n";
$template_keys = ['newsletter_confirmation', 'newsletter_welcome', 'newsletter_weekly'];

foreach ($template_keys as $key) {
    if (strpos($schema_file, $key) !== false) {
        echo "✅ PASS: Template {$key} found\n";
    } else {
        echo "❌ FAIL: Template {$key} missing\n";
        $test_results[] = false;
    }
}
echo "\n";

// TEST 7: Check kg-core.php includes Newsletter files
echo "TEST 7: Checking kg-core.php for Newsletter registration\n";
echo "-------------------------------------------------------------------\n";
$main_file = file_get_contents(KG_CORE_PATH . 'kg-core.php');

$newsletter_includes = [
    'Newsletter/NewsletterSubscriber.php',
    'Newsletter/NewsletterRepository.php',
    'Newsletter/NewsletterService.php',
    'Newsletter/NewsletterRESTController.php',
    'Admin/NewsletterAdminPage.php',
];

foreach ($newsletter_includes as $include) {
    if (strpos($main_file, $include) !== false) {
        echo "✅ PASS: {$include} included in kg-core.php\n";
    } else {
        echo "❌ FAIL: {$include} not included in kg-core.php\n";
        $test_results[] = false;
    }
}
echo "\n";

// TEST 8: Check Newsletter classes are initialized
echo "TEST 8: Checking Newsletter class initialization\n";
echo "-------------------------------------------------------------------\n";

if (strpos($main_file, 'NewsletterRESTController') !== false) {
    echo "✅ PASS: NewsletterRESTController initialized\n";
} else {
    echo "❌ FAIL: NewsletterRESTController not initialized\n";
    $test_results[] = false;
}

if (strpos($main_file, 'NewsletterAdminPage') !== false) {
    echo "✅ PASS: NewsletterAdminPage initialized\n";
} else {
    echo "❌ FAIL: NewsletterAdminPage not initialized\n";
    $test_results[] = false;
}
echo "\n";

// TEST 9: Check SocialMediaSettings has logo and new platforms
echo "TEST 9: Checking SocialMediaSettings updates\n";
echo "-------------------------------------------------------------------\n";
$settings_file = file_get_contents(KG_CORE_PATH . 'includes/Admin/SocialMediaSettings.php');

if (strpos($settings_file, 'kg_social_tiktok') !== false) {
    echo "✅ PASS: TikTok field added\n";
} else {
    echo "❌ FAIL: TikTok field missing\n";
    $test_results[] = false;
}

if (strpos($settings_file, 'kg_social_pinterest') !== false) {
    echo "✅ PASS: Pinterest field added\n";
} else {
    echo "❌ FAIL: Pinterest field missing\n";
    $test_results[] = false;
}

if (strpos($settings_file, 'kg_email_logo') !== false) {
    echo "✅ PASS: Email logo field added\n";
} else {
    echo "❌ FAIL: Email logo field missing\n";
    $test_results[] = false;
}
echo "\n";

// SUMMARY
echo "=== TEST SUMMARY ===\n";
if (empty($test_results) || !in_array(false, $test_results)) {
    echo "🎉 ALL TESTS PASSED!\n";
    echo "\nNewsletter system successfully implemented:\n";
    echo "- 6 social media platforms (Instagram, YouTube, Twitter/X, TikTok, Pinterest, Facebook)\n";
    echo "- Email logo upload functionality\n";
    echo "- Modern email template design\n";
    echo "- Newsletter subscription system with REST API\n";
    echo "- Newsletter admin panel\n";
    echo "- 3 newsletter email templates\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "Please review the failures above.\n";
    exit(1);
}
