<?php
namespace KG_Core\Health;

/**
 * SideEffectManager - Enhanced side effect tracking with detailed schema
 */
class SideEffectManager {
    
    /**
     * Get side effect schema definition
     * 
     * @return array Side effect schema
     */
    public function get_schema() {
        return [
            'fever' => [
                'label' => 'Ateş',
                'type' => 'boolean',
                'severity_tracking' => true,
                'details' => [
                    'max_temperature' => ['type' => 'float', 'label' => 'En yüksek ateş (°C)'],
                    'duration_hours' => ['type' => 'int', 'label' => 'Süre (saat)']
                ]
            ],
            'irritability' => [
                'label' => 'Huzursuzluk / Ağlama',
                'type' => 'boolean',
                'severity_tracking' => true,
                'details' => [
                    'duration_hours' => ['type' => 'int', 'label' => 'Süre (saat)']
                ]
            ],
            'injection_site_swelling' => [
                'label' => 'Enjeksiyon Yerinde Şişlik',
                'type' => 'boolean',
                'severity_tracking' => true,
                'details' => [
                    'size_cm' => ['type' => 'float', 'label' => 'Boyut (cm)'],
                    'redness' => ['type' => 'boolean', 'label' => 'Kızarıklık']
                ]
            ],
            'rash' => [
                'label' => 'Döküntü',
                'type' => 'boolean',
                'severity_tracking' => true,
                'details' => [
                    'location' => ['type' => 'string', 'label' => 'Konum'],
                    'duration_days' => ['type' => 'int', 'label' => 'Süre (gün)']
                ]
            ],
            'loss_of_appetite' => [
                'label' => 'İştahsızlık',
                'type' => 'boolean',
                'severity_tracking' => true,
                'details' => [
                    'duration_days' => ['type' => 'int', 'label' => 'Süre (gün)']
                ]
            ],
            'drowsiness' => [
                'label' => 'Uyuşukluk / Uyku Hali',
                'type' => 'boolean',
                'severity_tracking' => true
            ],
            'vomiting' => [
                'label' => 'Kusma',
                'type' => 'boolean',
                'severity_tracking' => true,
                'details' => [
                    'frequency' => ['type' => 'int', 'label' => 'Kaç kez']
                ]
            ],
            'diarrhea' => [
                'label' => 'İshal',
                'type' => 'boolean',
                'severity_tracking' => true
            ],
            'other' => [
                'label' => 'Diğer',
                'type' => 'text',
                'max_length' => 500
            ]
        ];
    }
    
    /**
     * Report side effects for a vaccine
     * 
     * @param int $record_id Vaccine record ID
     * @param array $side_effects Side effects data
     * @param string $severity Overall severity
     * @return int|WP_Error Side effect record ID or error
     */
    public function report($record_id, $side_effects, $severity) {
        global $wpdb;
        
        // Validate inputs
        if (empty($record_id)) {
            return new \WP_Error('invalid_record_id', 'Vaccine record ID is required');
        }
        
        if (empty($side_effects) || !is_array($side_effects)) {
            return new \WP_Error('invalid_side_effects', 'Side effects must be a non-empty array');
        }
        
        // Validate severity
        $valid_severities = ['none', 'mild', 'moderate', 'severe'];
        if (!in_array($severity, $valid_severities)) {
            return new \WP_Error('invalid_severity', 'Invalid severity value');
        }
        
        // Verify vaccine record exists
        $vaccine_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kg_vaccine_records WHERE id = %d",
            $record_id
        ), ARRAY_A);
        
        if (!$vaccine_record) {
            return new \WP_Error('record_not_found', 'Vaccine record not found');
        }
        
        // Validate side effects against schema
        $schema = $this->get_schema();
        $validated_effects = [];
        
        foreach ($side_effects as $key => $value) {
            if (!isset($schema[$key])) {
                continue; // Skip unknown side effects
            }
            
            $validated_effects[$key] = $value;
        }
        
        // Update vaccine record with side effects
        $wpdb->update(
            $wpdb->prefix . 'kg_vaccine_records',
            [
                'side_effects' => json_encode($validated_effects),
                'side_effect_severity' => $severity,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $record_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        return $record_id;
    }
    
    /**
     * Get side effects for a vaccine record
     * 
     * @param int $record_id Vaccine record ID
     * @return array|WP_Error Side effects data or error
     */
    public function get($record_id) {
        global $wpdb;
        
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT side_effects, side_effect_severity FROM {$wpdb->prefix}kg_vaccine_records WHERE id = %d",
            $record_id
        ), ARRAY_A);
        
        if (!$record) {
            return new \WP_Error('record_not_found', 'Vaccine record not found');
        }
        
        $side_effects = !empty($record['side_effects']) ? json_decode($record['side_effects'], true) : [];
        
        return [
            'side_effects' => $side_effects,
            'severity' => $record['side_effect_severity'],
            'schema' => $this->get_schema()
        ];
    }
    
    /**
     * Update side effects for a vaccine record
     * 
     * @param int $record_id Vaccine record ID
     * @param array $side_effects Updated side effects
     * @param string $severity Updated severity
     * @return bool|WP_Error True on success, error on failure
     */
    public function update($record_id, $side_effects, $severity) {
        return $this->report($record_id, $side_effects, $severity);
    }
    
    /**
     * Get anonymous side effect statistics
     * 
     * @param string|null $vaccine_code Optional vaccine code to filter by
     * @return array|WP_Error Statistics or error
     */
    public function get_statistics($vaccine_code = null) {
        global $wpdb;
        
        $where = "WHERE side_effects IS NOT NULL AND side_effects != 'null' AND side_effects != '{}'";
        $params = [];
        
        if ($vaccine_code) {
            $where .= " AND vaccine_code = %s";
            $params[] = $vaccine_code;
        }
        
        $query = "SELECT vaccine_code, side_effects, side_effect_severity 
                  FROM {$wpdb->prefix}kg_vaccine_records 
                  {$where}";
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        $records = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($records)) {
            return [
                'total_reports' => 0,
                'message' => 'Henüz yan etki raporu yok'
            ];
        }
        
        // Apply k-anonymity: require minimum 10 reports
        if (count($records) < 10) {
            return [
                'total_reports' => count($records),
                'message' => 'İstatistik için yeterli veri yok (minimum 10 rapor gerekli)',
                'disclaimer' => 'Gizlilik nedeniyle 10\'dan az rapor olduğunda istatistik gösterilmez'
            ];
        }
        
        // Calculate statistics
        $stats = [
            'total_reports' => count($records),
            'side_effect_rates' => [],
            'severity_distribution' => [
                'none' => 0,
                'mild' => 0,
                'moderate' => 0,
                'severe' => 0
            ]
        ];
        
        $side_effect_counts = [];
        $side_effect_severities = [];
        
        foreach ($records as $record) {
            // Count severity
            $severity = $record['side_effect_severity'] ?: 'none';
            $stats['severity_distribution'][$severity]++;
            
            // Parse side effects
            $effects = json_decode($record['side_effects'], true);
            if (!is_array($effects)) {
                continue;
            }
            
            foreach ($effects as $effect => $value) {
                if (!isset($side_effect_counts[$effect])) {
                    $side_effect_counts[$effect] = 0;
                    $side_effect_severities[$effect] = [
                        'mild' => 0,
                        'moderate' => 0,
                        'severe' => 0
                    ];
                }
                
                // Check if effect is present
                $is_present = false;
                if (is_bool($value)) {
                    $is_present = $value;
                } elseif (is_array($value) && isset($value['present'])) {
                    $is_present = $value['present'];
                } elseif (is_string($value) && !empty($value)) {
                    $is_present = true;
                }
                
                if ($is_present) {
                    $side_effect_counts[$effect]++;
                    if ($severity !== 'none') {
                        $side_effect_severities[$effect][$severity]++;
                    }
                }
            }
        }
        
        // Calculate percentages
        $schema = $this->get_schema();
        foreach ($side_effect_counts as $effect => $count) {
            $percentage = ($count / count($records)) * 100;
            
            $effect_data = [
                'percentage' => round($percentage, 1),
                'count' => $count,
                'label' => isset($schema[$effect]) ? $schema[$effect]['label'] : $effect
            ];
            
            if (isset($schema[$effect]['severity_tracking']) && $schema[$effect]['severity_tracking']) {
                $total_severity = array_sum($side_effect_severities[$effect]);
                if ($total_severity > 0) {
                    $effect_data['severity_distribution'] = [
                        'mild' => round(($side_effect_severities[$effect]['mild'] / $total_severity) * 100),
                        'moderate' => round(($side_effect_severities[$effect]['moderate'] / $total_severity) * 100),
                        'severe' => round(($side_effect_severities[$effect]['severe'] / $total_severity) * 100)
                    ];
                }
            }
            
            $stats['side_effect_rates'][$effect] = $effect_data;
        }
        
        // Add disclaimer
        $stats['disclaimer'] = 'Bu istatistikler kullanıcı raporlarına dayanmaktadır ve tıbbi tavsiye niteliği taşımaz.';
        
        if ($vaccine_code) {
            // Get vaccine name
            $vaccine = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}kg_vaccine_master WHERE code = %s",
                $vaccine_code
            ));
            
            if ($vaccine) {
                $stats['vaccine_name'] = $vaccine->name;
            }
            $stats['vaccine_code'] = $vaccine_code;
        }
        
        return $stats;
    }
    
    /**
     * Get complete side effect history for a child
     * 
     * @param string $child_id Child ID
     * @return array Side effect history
     */
    public function get_child_history($child_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT vr.*, v.name as vaccine_name, v.name_short
             FROM {$wpdb->prefix}kg_vaccine_records vr
             LEFT JOIN {$wpdb->prefix}kg_vaccine_master v ON vr.vaccine_code = v.code
             WHERE vr.child_id = %s 
             AND vr.side_effects IS NOT NULL 
             AND vr.side_effects != 'null'
             AND vr.side_effects != '{}'
             ORDER BY vr.actual_date DESC",
            $child_id
        ), ARRAY_A);
        
        foreach ($results as &$record) {
            if (isset($record['side_effects']) && !empty($record['side_effects'])) {
                $record['side_effects'] = json_decode($record['side_effects'], true);
            }
        }
        
        return $results;
    }
}
