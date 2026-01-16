<?php
/**
 * Bath Planner API Integration Examples
 * 
 * This file demonstrates how to use the Bath Planner API endpoints
 * from the frontend (kidsgourmet-web) perspective.
 */

echo "=== Bath Planner API Integration Examples ===\n\n";

// Example 1: GET /tools/bath-planner/config
echo "1. GET /tools/bath-planner/config\n";
echo "   This endpoint returns configuration data for the Bath Planner tool.\n\n";
echo "   Expected Response Structure:\n";
echo "   {\n";
echo "     tool_info: { id, title, description, icon },\n";
echo "     skin_types: [\n";
echo "       { id: 'normal', label: 'Normal Cilt' },\n";
echo "       { id: 'dry', label: 'Kuru Cilt' },\n";
echo "       { id: 'sensitive', label: 'Hassas Cilt' },\n";
echo "       { id: 'oily', label: 'Yağlı Cilt' }\n";
echo "     ],\n";
echo "     seasons: [\n";
echo "       { id: 'spring', label: 'İlkbahar' },\n";
echo "       { id: 'summer', label: 'Yaz' },\n";
echo "       { id: 'autumn', label: 'Sonbahar' },\n";
echo "       { id: 'winter', label: 'Kış' }\n";
echo "     ],\n";
echo "     frequency_options: [\n";
echo "       { id: '2-3', label: 'Haftada 2-3 kez', description: 'Yenidoğanlar için önerilen' },\n";
echo "       { id: '3-4', label: 'Haftada 3-4 kez', description: '3-6 aylık bebekler için' },\n";
echo "       { id: '4-5', label: 'Haftada 4-5 kez', description: '6-12 aylık bebekler için' },\n";
echo "       { id: 'daily', label: 'Her gün', description: '12 ay üzeri için' }\n";
echo "     ],\n";
echo "     sponsor: {\n";
echo "       is_sponsored: boolean,\n";
echo "       sponsor_name: string,\n";
echo "       sponsor_logo: string | null,        // Direct URL\n";
echo "       sponsor_light_logo: string | null,  // Direct URL\n";
echo "       sponsor_tagline: string,\n";
echo "       sponsor_cta_text: string,\n";
echo "       sponsor_cta_url: string,\n";
echo "       gam_impression_url: string | null,\n";
echo "       gam_click_url: string | null\n";
echo "     }\n";
echo "   }\n\n";

// Example 2: POST /tools/bath-planner/generate
echo "2. POST /tools/bath-planner/generate\n";
echo "   This endpoint generates a personalized bath routine plan.\n\n";
echo "   Request Body:\n";
echo "   {\n";
echo "     baby_age_months: 6,           // Baby's age in months\n";
echo "     skin_type: 'sensitive',       // One of: normal, dry, sensitive, oily\n";
echo "     season: 'winter',             // One of: spring, summer, autumn, winter\n";
echo "     has_eczema: false             // Boolean\n";
echo "   }\n\n";
echo "   Expected Response Structure:\n";
echo "   {\n";
echo "     recommended_frequency: '3-4 kez/hafta',\n";
echo "     weekly_schedule: [\n";
echo "       { day: 'Pazartesi', bath: true, note: 'Ilık su ve kısa süreli banyo' },\n";
echo "       { day: 'Salı', bath: false, note: null },\n";
echo "       // ... rest of week\n";
echo "     ],\n";
echo "     tips: [\n";
echo "       'Bebeği banyoda asla yalnız bırakmayın',\n";
echo "       'Su sıcaklığını her zaman dirsekle test edin',\n";
echo "       // ...\n";
echo "     ],\n";
echo "     warnings: [\n";
echo "       'Kış aylarında banyo sıklığını azaltmayı düşünün',\n";
echo "       'Banyodan sonra cildi iyi nemlendirin',\n";
echo "       // ...\n";
echo "     ],\n";
echo "     product_recommendations: [\n";
echo "       'Parfümsüz, hipoalerjenik bebek şampuanı',\n";
echo "       'Oat (yulaf) bazlı banyo yağı',\n";
echo "       // ...\n";
echo "     ],\n";
echo "     products: [...],  // Legacy format (still included for backwards compatibility)\n";
echo "     routine: [...],   // Legacy format (still included for backwards compatibility)\n";
echo "     sponsor: {...}    // Same structure as in config\n";
echo "   }\n\n";

echo "=== Key Changes from Previous Version ===\n\n";
echo "1. Config endpoint now includes:\n";
echo "   - skin_types array (was missing)\n";
echo "   - seasons array (was missing)\n";
echo "   - frequency_options array (was missing)\n\n";

echo "2. Generate endpoint now accepts:\n";
echo "   - baby_age_months (in addition to child_age_months for backwards compatibility)\n";
echo "   - season parameter (in addition to activity_level for backwards compatibility)\n";
echo "   - has_eczema parameter (new)\n\n";

echo "3. Generate endpoint now returns:\n";
echo "   - weekly_schedule array (new)\n";
echo "   - warnings array (new)\n";
echo "   - product_recommendations string array (new)\n\n";

echo "4. Sponsor data format changed:\n";
echo "   - sponsor_logo: Direct URL string instead of { id, url } object\n";
echo "   - sponsor_light_logo: Direct URL string instead of { id, url } object\n";
echo "   - sponsor_cta_text and sponsor_cta_url: Flattened from nested object\n";
echo "   - gam_impression_url and gam_click_url: Now have null fallback\n";
echo "   - Response key changed from 'sponsor_data' to 'sponsor'\n\n";

echo "=== Frontend Implementation Notes ===\n\n";
echo "1. The frontend should use the new 'sponsor' key instead of 'sponsor_data'\n";
echo "2. Skin type and season dropdowns will now be populated from the config endpoint\n";
echo "3. The weekly_schedule array can be used to display a calendar view\n";
echo "4. Warnings should be displayed prominently, especially for eczema cases\n";
echo "5. Product recommendations are now a simple string array for easy display\n\n";

echo "✅ API is now fully compatible with frontend expectations!\n";
