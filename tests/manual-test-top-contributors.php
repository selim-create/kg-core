<?php
/**
 * Manual Test Script for Top Contributors Endpoint
 * This script demonstrates how the endpoint would work with sample data
 */

echo "=== Top Contributors Endpoint Manual Test ===\n\n";

// Simulate the response structure
$sample_contributors = [
    [
        'id' => 123,
        'name' => 'Ayşe Yılmaz',
        'avatar' => 'https://example.com/avatar1.jpg',
        'contribution_count' => 25,
        'discussion_count' => 10,
        'comment_count' => 15,
        'rank' => 1,
    ],
    [
        'id' => 456,
        'name' => 'Fatma Demir',
        'avatar' => 'https://example.com/avatar2.jpg',
        'contribution_count' => 18,
        'discussion_count' => 8,
        'comment_count' => 10,
        'rank' => 2,
    ],
    [
        'id' => 789,
        'name' => 'Zeynep Kaya',
        'avatar' => null,
        'contribution_count' => 12,
        'discussion_count' => 5,
        'comment_count' => 7,
        'rank' => 3,
    ],
];

echo "Sample Response Structure:\n";
echo "==========================\n\n";
echo json_encode($sample_contributors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Endpoint Usage Examples:\n";
echo "========================\n\n";

$examples = [
    [
        'url' => '/wp-json/kg/v1/community/top-contributors',
        'description' => 'Default: Top 5 contributors from the last week',
        'params' => ['limit' => 5, 'period' => 'week'],
    ],
    [
        'url' => '/wp-json/kg/v1/community/top-contributors?limit=10',
        'description' => 'Top 10 contributors from the last week',
        'params' => ['limit' => 10, 'period' => 'week'],
    ],
    [
        'url' => '/wp-json/kg/v1/community/top-contributors?period=month',
        'description' => 'Top 5 contributors from the last month',
        'params' => ['limit' => 5, 'period' => 'month'],
    ],
    [
        'url' => '/wp-json/kg/v1/community/top-contributors?limit=10&period=all',
        'description' => 'Top 10 contributors of all time',
        'params' => ['limit' => 10, 'period' => 'all'],
    ],
];

foreach ($examples as $i => $example) {
    echo ($i + 1) . ". " . $example['description'] . "\n";
    echo "   URL: " . $example['url'] . "\n";
    echo "   Parameters: " . json_encode($example['params']) . "\n\n";
}

echo "Response Field Descriptions:\n";
echo "============================\n\n";

$fields = [
    'id' => 'User ID (integer)',
    'name' => 'User display name (string)',
    'avatar' => 'User avatar URL (string|null) - Priority: _kg_avatar_id > google_avatar > Gravatar',
    'contribution_count' => 'Total contributions (discussion_count + comment_count)',
    'discussion_count' => 'Number of published discussions (integer)',
    'comment_count' => 'Number of approved comments (integer)',
    'rank' => 'User rank based on contribution count (integer)',
];

foreach ($fields as $field => $description) {
    echo "  • $field: $description\n";
}

echo "\n";

echo "User Filtering:\n";
echo "===============\n";
echo "✅ Includes: Regular users (mothers/parents)\n";
echo "❌ Excludes: Administrators, Editors, kg_expert role users\n\n";

echo "Period Filtering:\n";
echo "=================\n";
echo "• week: Last 7 days (default)\n";
echo "• month: Last 30 days\n";
echo "• all: All time\n\n";

echo "Validation Rules:\n";
echo "=================\n";
echo "• limit: Must be numeric, > 0, <= 20 (default: 5)\n";
echo "• period: Must be one of 'week', 'month', 'all' (default: 'week')\n\n";

echo "✅ Implementation complete and ready for testing!\n";
