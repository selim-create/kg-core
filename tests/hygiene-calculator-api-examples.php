<?php
/**
 * Example API Request/Response for Hygiene Calculator
 * 
 * This demonstrates the updated API endpoint usage
 */

echo "=== Hygiene Calculator API Examples ===\n\n";

// Example 1: Newborn (0-3 months)
echo "Example 1: Newborn Baby (2 months old)\n";
echo "Request:\n";
$request1 = [
    'baby_age_months' => 2,
    'daily_diaper_changes' => 10,
    'outdoor_hours' => 1,
    'meal_count' => 0
];
echo json_encode($request1, JSON_PRETTY_PRINT) . "\n\n";
echo "Expected Response:\n";
$response1 = [
    'daily_wipes_needed' => 41,
    'weekly_wipes_needed' => 287,
    'monthly_wipes_needed' => 1230,
    'recommendations' => [
        'Islak mendilleri serin ve kuru bir yerde saklayın',
        'Hassas ciltler için parfümsüz mendil tercih edin',
        'Yenidoğan cildi çok hassastır, %99 su içerikli mendiller tercih edin',
        'Her bez değişiminde nazikçe temizleyin, ovalamayın'
    ],
    'carry_bag_essentials' => [
        'Islak mendil paketi (mini seyahat boy)',
        'Yedek bez (en az 2-3 adet)',
        'Bez değiştirme altlığı',
        'Pişik kremi',
        'Nemlendirici krem',
        'Güneş koruyucu (6 ay üzeri için)'
    ],
    'sponsor' => null
];
echo json_encode($response1, JSON_PRETTY_PRINT) . "\n\n";

// Example 2: 6-month-old starting solid foods
echo "Example 2: 6-Month-Old Baby (Starting Solid Foods)\n";
echo "Request:\n";
$request2 = [
    'baby_age_months' => 6,
    'daily_diaper_changes' => 6,
    'outdoor_hours' => 2,
    'meal_count' => 3
];
echo json_encode($request2, JSON_PRETTY_PRINT) . "\n\n";
echo "Expected Response:\n";
$response2 = [
    'daily_wipes_needed' => 27,
    'weekly_wipes_needed' => 189,
    'monthly_wipes_needed' => 810,
    'recommendations' => [
        'Islak mendilleri serin ve kuru bir yerde saklayın',
        'Hassas ciltler için parfümsüz mendil tercih edin',
        'Pişik önleyici bariyer krem kullanmayı unutmayın',
        'Yemek sonrası yüz ve elleri ıslak mendille temizleyin',
        'Mama önlüğü kullanarak kıyafet kirliliğini azaltın'
    ],
    'carry_bag_essentials' => [
        'Islak mendil paketi (mini seyahat boy)',
        'Yedek bez (en az 2-3 adet)',
        'Bez değiştirme altlığı',
        'Pişik kremi',
        'Yedek önlük',
        'Atıştırmalık kabı',
        'Ekstra mendil paketi',
        'Küçük çöp poşetleri',
        'Nemlendirici krem',
        'Güneş koruyucu (6 ay üzeri için)'
    ],
    'sponsor' => null
];
echo json_encode($response2, JSON_PRETTY_PRINT) . "\n\n";

// Example 3: Active toddler
echo "Example 3: Active 12-Month-Old Toddler\n";
echo "Request:\n";
$request3 = [
    'baby_age_months' => 12,
    'daily_diaper_changes' => 5,
    'outdoor_hours' => 4,
    'meal_count' => 4
];
echo json_encode($request3, JSON_PRETTY_PRINT) . "\n\n";
echo "Expected Response:\n";
$response3 = [
    'daily_wipes_needed' => 34,
    'weekly_wipes_needed' => 238,
    'monthly_wipes_needed' => 1020,
    'recommendations' => [
        'Islak mendilleri serin ve kuru bir yerde saklayın',
        'Hassas ciltler için parfümsüz mendil tercih edin',
        'Yemek sonrası yüz ve elleri ıslak mendille temizleyin',
        'Mama önlüğü kullanarak kıyafet kirliliğini azaltın',
        'Dışarıda geçirilen süre fazla, çantada yedek mendil paketi bulundurun',
        'Güneş koruyucu uyguladıktan sonra eller için ayrı mendil kullanın'
    ],
    'carry_bag_essentials' => [
        'Islak mendil paketi (mini seyahat boy)',
        'Yedek bez (en az 2-3 adet)',
        'Bez değiştirme altlığı',
        'Pişik kremi',
        'Yedek önlük',
        'Atıştırmalık kabı',
        'El temizleme jeli (alkol içermeyen)',
        'Ekstra mendil paketi',
        'Küçük çöp poşetleri',
        'Yedek kıyafet seti',
        'İkinci bez paketi',
        'Nemlendirici krem',
        'Güneş koruyucu (6 ay üzeri için)'
    ],
    'sponsor' => null
];
echo json_encode($response3, JSON_PRETTY_PRINT) . "\n\n";

// Example 4: Backwards compatibility
echo "Example 4: Backwards Compatibility (Old Parameter Name)\n";
echo "Request:\n";
$request4 = [
    'child_age_months' => 6,  // Old parameter name
    'lifestyle' => 'moderate'  // Old parameter (ignored but not causing error)
];
echo json_encode($request4, JSON_PRETTY_PRINT) . "\n\n";
echo "Note: Uses default values for daily_diaper_changes (6), outdoor_hours (2), meal_count (3)\n";
echo "Expected Response: Same structure with calculated values based on defaults\n\n";

// cURL Examples
echo "=== cURL Command Examples ===\n\n";

echo "# Test 1: Newborn (0-3 months)\n";
echo "curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/tools/hygiene-calculator/calculate \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"baby_age_months\": 2, \"daily_diaper_changes\": 10, \"outdoor_hours\": 1, \"meal_count\": 0}'\n\n";

echo "# Test 2: 6-Month-Old Baby\n";
echo "curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/tools/hygiene-calculator/calculate \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"baby_age_months\": 6, \"daily_diaper_changes\": 6, \"outdoor_hours\": 2, \"meal_count\": 3}'\n\n";

echo "# Test 3: 12-Month-Old Active Baby\n";
echo "curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/tools/hygiene-calculator/calculate \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"baby_age_months\": 12, \"daily_diaper_changes\": 5, \"outdoor_hours\": 4, \"meal_count\": 4}'\n\n";

echo "# Test 4: Backwards Compatibility\n";
echo "curl -X POST https://api.kidsgourmet.com.tr/wp-json/kg/v1/tools/hygiene-calculator/calculate \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"child_age_months\": 6, \"lifestyle\": \"moderate\"}'\n\n";

echo "=== Response Format ===\n\n";
echo "TypeScript Interface (from kidsgourmet-web/types.ts):\n";
echo "interface HygieneCalculatorResult {\n";
echo "  daily_wipes_needed: number;\n";
echo "  weekly_wipes_needed: number;\n";
echo "  monthly_wipes_needed: number;\n";
echo "  recommendations: string[];\n";
echo "  carry_bag_essentials: string[];\n";
echo "  sponsor?: ToolSponsorData;\n";
echo "}\n\n";

echo "=== Key Features ===\n\n";
echo "✓ Accepts new parameters: baby_age_months, daily_diaper_changes, outdoor_hours, meal_count\n";
echo "✓ Backwards compatible: still accepts child_age_months parameter\n";
echo "✓ Smart defaults: diaper_changes=6, outdoor_hours=2, meal_count=3\n";
echo "✓ Age-based calculations: Different wipe counts for different age groups\n";
echo "✓ Contextual recommendations: Based on age, diaper changes, outdoor time, meals\n";
echo "✓ Dynamic carry bag essentials: Based on age and outdoor hours\n";
echo "✓ Supports values of 0 for parameters (e.g., meal_count=0 for newborns)\n";
echo "✓ Age validation: 0-36 months\n";
echo "✓ Response format matches frontend expectations\n\n";

echo "=== Calculation Logic ===\n\n";
echo "Wipes per Diaper Change (by age):\n";
echo "  0-3 months: 4 wipes\n";
echo "  3-12 months: 3 wipes\n";
echo "  12+ months: 2 wipes\n\n";

echo "Wipes per Meal (by age):\n";
echo "  0-6 months: 1 wipe\n";
echo "  6-9 months: 2 wipes\n";
echo "  9-12 months: 3 wipes\n";
echo "  12+ months: 4 wipes\n\n";

echo "Wipes per Outdoor Hour (by age):\n";
echo "  0-6 months: 1 wipe\n";
echo "  6-12 months: 1.5 wipes\n";
echo "  12+ months: 2 wipes\n\n";

echo "Formula:\n";
echo "daily_wipes = (diaper_changes × wipes_per_diaper) + \n";
echo "              (meal_count × wipes_per_meal) + \n";
echo "              (outdoor_hours × wipes_per_outdoor_hour)\n";
