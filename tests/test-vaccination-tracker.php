<?php
/**
 * Test Script for Vaccination Tracker Module
 * 
 * Tests:
 * 1. Database tables creation
 * 2. Vaccine master data loading
 * 3. Vaccine schedule calculation
 * 4. Vaccine record management
 * 5. Email template rendering
 * 6. API endpoints
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Use statements
use KG_Core\Health\VaccineScheduleCalculator;
use KG_Core\Health\VaccineManager;
use KG_Core\Health\VaccineRecordManager;
use KG_Core\Notifications\TemplateEngine;
use KG_Core\Notifications\NotificationQueue;

echo "=== VACCINATION TRACKER TEST ===\n\n";

// ===== TEST 1: Check Database Tables =====
echo "TEST 1: Checking database tables...\n";
global $wpdb;
$prefix = $wpdb->prefix;

$tables = [
    'kg_vaccine_master',
    'kg_vaccine_records',
    'kg_email_templates',
    'kg_email_logs',
    'kg_notification_queue',
    'kg_push_subscriptions'
];

foreach ($tables as $table) {
    $table_name = $prefix . $table;
    $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
    $result = $wpdb->get_var($query);
    
    if ($result === $table_name) {
        echo "✅ Table {$table} exists\n";
    } else {
        echo "❌ Table {$table} does NOT exist\n";
    }
}
echo "\n";

// ===== TEST 2: Check Vaccine Master Data =====
echo "TEST 2: Checking vaccine master data...\n";
$vaccine_count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}kg_vaccine_master");
echo "Vaccines in database: {$vaccine_count}\n";

if ($vaccine_count > 0) {
    echo "✅ Vaccine master data loaded\n";
    
    // Show some vaccines
    $vaccines = $wpdb->get_results("SELECT code, name, is_mandatory FROM {$prefix}kg_vaccine_master ORDER BY sort_order LIMIT 5", ARRAY_A);
    echo "Sample vaccines:\n";
    foreach ($vaccines as $vaccine) {
        $type = $vaccine['is_mandatory'] ? 'Mandatory' : 'Private';
        echo "  - {$vaccine['code']}: {$vaccine['name']} ({$type})\n";
    }
} else {
    echo "❌ No vaccine master data found\n";
}
echo "\n";

// ===== TEST 3: Check Email Templates =====
echo "TEST 3: Checking email templates...\n";
$template_count = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}kg_email_templates");
echo "Email templates in database: {$template_count}\n";

if ($template_count > 0) {
    echo "✅ Email templates seeded\n";
    
    $templates = $wpdb->get_results("SELECT template_key, name, category FROM {$prefix}kg_email_templates", ARRAY_A);
    foreach ($templates as $template) {
        echo "  - {$template['template_key']}: {$template['name']} ({$template['category']})\n";
    }
} else {
    echo "❌ No email templates found\n";
}
echo "\n";

// ===== TEST 4: Test VaccineScheduleCalculator =====
echo "TEST 4: Testing VaccineScheduleCalculator...\n";

$calculator = new VaccineScheduleCalculator();
$manager = new VaccineManager();

// Test with a 3-month-old baby born on 2024-10-16
$birth_date = '2024-10-16';
$vaccines = $manager->get_all_vaccines(true); // Get mandatory vaccines only

if (!is_wp_error($vaccines)) {
    $schedule = $calculator->calculate($birth_date, $vaccines, false);
    
    echo "✅ Schedule calculated for baby born on {$birth_date}\n";
    echo "Total vaccines in schedule: " . count($schedule) . "\n";
    
    // Show first 5 vaccines
    echo "First 5 vaccines:\n";
    $shown = 0;
    foreach ($schedule as $vaccine) {
        if ($shown >= 5) break;
        echo "  - {$vaccine['scheduled_date']}: {$vaccine['vaccine_name']} ({$vaccine['vaccine_code']})\n";
        $shown++;
    }
} else {
    echo "❌ Failed to calculate schedule: " . $vaccines->get_error_message() . "\n";
}
echo "\n";

// ===== TEST 5: Test Template Engine =====
echo "TEST 5: Testing TemplateEngine...\n";

$template_engine = new TemplateEngine();

$placeholders = [
    'parent_name' => 'Ayşe Yılmaz',
    'child_name' => 'Ali',
    'vaccine_name' => 'Hepatit B (2. Doz)',
    'scheduled_date' => '15 Ocak 2026',
    'days_remaining' => '3',
    'app_url' => home_url(),
    'unsubscribe_url' => home_url('/hesap/bildirim-tercihleri')
];

$rendered = $template_engine->render('vaccine_reminder_3day', $placeholders);

if (!is_wp_error($rendered)) {
    echo "✅ Template rendered successfully\n";
    echo "Subject: {$rendered['subject']}\n";
    echo "Body preview: " . substr(strip_tags($rendered['body_html']), 0, 100) . "...\n";
} else {
    echo "❌ Template rendering failed: " . $rendered->get_error_message() . "\n";
}
echo "\n";

// ===== TEST 6: Create Test User and Child =====
echo "TEST 6: Creating test user and child profile...\n";
$test_email = 'vaccine_test_' . time() . '@example.com';
$test_username = 'vaccine_test_' . time();
$test_password = 'TestPass123!';

$user_id = wp_create_user($test_username, $test_password, $test_email);

if (is_wp_error($user_id)) {
    echo "❌ Failed to create user: " . $user_id->get_error_message() . "\n";
} else {
    $user = get_user_by('id', $user_id);
    $user->set_role('kg_parent');
    
    echo "✅ Test user created: ID={$user_id}, Email={$test_email}\n";
    
    // Create child profile
    $child_id = wp_generate_uuid4();
    $children = [
        [
            'id' => $child_id,
            'name' => 'Test Baby',
            'birth_date' => '2024-10-16',
            'gender' => 'male',
            'allergens' => []
        ]
    ];
    
    update_user_meta($user_id, 'kg_children', $children);
    echo "✅ Child profile created: ID={$child_id}, Name=Test Baby, Birth Date=2024-10-16\n";
    
    // Test VaccineRecordManager
    echo "\nTEST 7: Testing VaccineRecordManager...\n";
    
    $record_manager = new VaccineRecordManager();
    $result = $record_manager->create_schedule_for_child($user_id, $child_id, '2024-10-16', false);
    
    if (!is_wp_error($result)) {
        echo "✅ Vaccine schedule created for child\n";
        echo "Total records created: {$result}\n";
        
        // Get child's vaccines
        $child_vaccines = $record_manager->get_child_vaccines($child_id);
        
        if (!is_wp_error($child_vaccines)) {
            echo "✅ Retrieved child vaccines: " . count($child_vaccines) . " vaccines\n";
            
            // Show first 3
            echo "First 3 vaccines:\n";
            for ($i = 0; $i < min(3, count($child_vaccines)); $i++) {
                $v = $child_vaccines[$i];
                echo "  - {$v['scheduled_date']}: {$v['vaccine_name']} - Status: {$v['status']}\n";
            }
        } else {
            echo "❌ Failed to get child vaccines: " . $child_vaccines->get_error_message() . "\n";
        }
    } else {
        echo "❌ Failed to create schedule: " . $result->get_error_message() . "\n";
    }
}
echo "\n";

// ===== TEST 8: Test REST API Endpoints =====
echo "TEST 8: Testing REST API endpoints...\n";

// Test GET /kg/v1/health/vaccines/master
$request = new WP_REST_Request('GET', '/kg/v1/health/vaccines/master');
$controller = new \KG_Core\API\VaccineController();
$response = $controller->get_vaccine_master($request);

if (is_wp_error($response)) {
    echo "❌ GET /kg/v1/health/vaccines/master failed: " . $response->get_error_message() . "\n";
} else {
    $data = $response->get_data();
    echo "✅ GET /kg/v1/health/vaccines/master: " . count($data) . " vaccines\n";
}

// Test GET /kg/v1/health/vaccines/schedule-versions
$request = new WP_REST_Request('GET', '/kg/v1/health/vaccines/schedule-versions');
$response = $controller->get_schedule_versions($request);

if (is_wp_error($response)) {
    echo "❌ GET /kg/v1/health/vaccines/schedule-versions failed\n";
} else {
    $data = $response->get_data();
    echo "✅ GET /kg/v1/health/vaccines/schedule-versions: " . count($data['versions']) . " versions\n";
}
echo "\n";

// ===== TEST 9: Test Notification Queue =====
echo "TEST 9: Testing NotificationQueue...\n";

$queue = new NotificationQueue();

$payload = [
    'child_id' => $child_id,
    'vaccine_code' => 'hep-b-2',
    'scheduled_date' => '2024-11-16'
];

$queue_id = $queue->add(
    $user_id,
    'email',
    'vaccine_reminder_3day',
    $payload,
    date('Y-m-d H:i:s', strtotime('+1 hour'))
);

if (!is_wp_error($queue_id)) {
    echo "✅ Notification added to queue: ID={$queue_id}\n";
    
    // Get pending notifications
    $pending = $queue->get_pending(10);
    echo "✅ Retrieved pending notifications: " . count($pending) . " items\n";
} else {
    echo "❌ Failed to add to queue: " . $queue_id->get_error_message() . "\n";
}
echo "\n";

// ===== CLEANUP =====
echo "TEST 10: Cleanup test data...\n";
if (isset($user_id) && !is_wp_error($user_id)) {
    // Delete vaccine records
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$prefix}kg_vaccine_records WHERE user_id = %d",
        $user_id
    ));
    
    // Delete queue items
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$prefix}kg_notification_queue WHERE user_id = %d",
        $user_id
    ));
    
    // Delete user
    wp_delete_user($user_id);
    
    echo "✅ Test data cleaned up\n";
}

echo "\n=== ALL TESTS COMPLETED ===\n";
