#!/bin/bash

echo "=== Air Quality API Manual Test Guide ==="
echo ""
echo "These curl commands can be used to test the API in a WordPress environment:"
echo ""
echo "----------------------------------------------------------------------"
echo "TEST 1: Frontend Current Request (Scenario from problem statement)"
echo "----------------------------------------------------------------------"
cat << 'EOF'
curl -X POST http://your-wordpress-site.com/wp-json/kg/v1/tools/air-quality/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "home_type": "apartment",
    "has_pets": true,
    "has_smoker": false,
    "heating_type": "natural_gas",
    "season": "winter"
  }'
EOF

echo ""
echo "Expected Response Structure:"
cat << 'EOF'
{
  "risk_level": "medium",
  "risk_score": 45-55,
  "risk_factors": [
    {
      "factor": "Apartman Dairesi",
      "impact": "Havalandırma sınırlı olabilir...",
      "severity": "low",
      "category": "environment"
    },
    ...
  ],
  "recommendations": ["...", "..."],
  "seasonal_alerts": ["...", "..."],
  "indoor_tips": ["...", "..."],
  "sponsor": {...}
}
EOF

echo ""
echo "----------------------------------------------------------------------"
echo "TEST 2: High Risk Scenario with Baby (Scenario from problem statement)"
echo "----------------------------------------------------------------------"
cat << 'EOF'
curl -X POST http://your-wordpress-site.com/wp-json/kg/v1/tools/air-quality/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "child_age_months": 4,
    "home_type": "ground_floor",
    "has_pets": false,
    "has_smoker": true,
    "heating_type": "stove",
    "season": "winter",
    "respiratory_issues": true
  }'
EOF

echo ""
echo "Expected Response Structure:"
cat << 'EOF'
{
  "risk_level": "high",
  "risk_score": 95-100,
  "risk_factors": [
    {
      "factor": "Sigara Dumanı",
      "impact": "Çocukların solunum sistemine ciddi zarar verir...",
      "severity": "high",
      "category": "lifestyle"
    },
    {
      "factor": "Soba Isıtma",
      "impact": "Karbonmonoksit ve partikül madde salınımı riski...",
      "severity": "high",
      "category": "heating"
    },
    ...
  ],
  "recommendations": [
    "Evde sigara içilmemesi çocuğunuzun sağlığı için kritik öneme sahiptir",
    "Hava temizleyici cihaz kullanmayı düşünün (HEPA filtreli)",
    "Yenidoğan ve küçük bebekler hava kirliliğine çok hassastır",
    ...
  ],
  "seasonal_alerts": [
    "Kış aylarında kapalı ortamda geçirilen süre arttığından hava kalitesine dikkat edin",
    "Isıtma sisteminizden kaynaklı karbonmonoksit riski için dedektör kullanın",
    ...
  ],
  ...
}
EOF

echo ""
echo "----------------------------------------------------------------------"
echo "TEST 3: Backward Compatibility with Old AQI Parameter"
echo "----------------------------------------------------------------------"
cat << 'EOF'
curl -X POST http://your-wordpress-site.com/wp-json/kg/v1/tools/air-quality/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "aqi": 120,
    "has_newborn": true,
    "respiratory_issues": false
  }'
EOF

echo ""
echo "Expected Response Structure:"
cat << 'EOF'
{
  "risk_level": "low/medium/high",
  "risk_score": XX,
  "risk_factors": [...],
  "recommendations": [...],
  "seasonal_alerts": [...],
  "indoor_tips": [...],
  "sponsor": {...},
  "external_aqi": {
    "aqi": 120,
    "quality_level": {
      "level": "Hassas Gruplar İçin Sağlıksız",
      "color": "orange",
      "description": "Hassas gruplar etkilenebilir"
    },
    "is_safe_for_outdoor": false
  }
}
EOF

echo ""
echo "----------------------------------------------------------------------"
echo "TEST 4: Minimal Request (All Defaults)"
echo "----------------------------------------------------------------------"
cat << 'EOF'
curl -X POST http://your-wordpress-site.com/wp-json/kg/v1/tools/air-quality/analyze \
  -H "Content-Type: application/json" \
  -d '{}'
EOF

echo ""
echo "Expected: Will use default values (apartment, central, daily, medium, current season)"
echo ""
echo "----------------------------------------------------------------------"
echo "TEST 5: Summer Season with Pets"
echo "----------------------------------------------------------------------"
cat << 'EOF'
curl -X POST http://your-wordpress-site.com/wp-json/kg/v1/tools/air-quality/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "child_age_months": 18,
    "home_type": "house",
    "has_pets": true,
    "has_smoker": false,
    "heating_type": "air_conditioner",
    "season": "summer",
    "ventilation_frequency": "multiple_daily",
    "cooking_frequency": "low"
  }'
EOF

echo ""
echo "Expected: Lower risk score, summer-specific alerts about AC filters and ozone"
echo ""
echo "======================================================================"
echo "Note: Replace 'http://your-wordpress-site.com' with your actual WordPress URL"
echo "======================================================================"
