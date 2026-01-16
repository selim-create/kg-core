<?php
namespace KG_Core\Health;

/**
 * SideEffectTracker - Track vaccine side effects
 */
class SideEffectTracker {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'kg_vaccine_side_effects';
    }
    
    /**
     * Record side effects for a vaccine
     * 
     * @param int $record_id Vaccine record ID
     * @param array $side_effects Array of side effects with details
     * @param string $severity Overall severity (mild, moderate, severe)
     * @return int|WP_Error Side effect record ID on success, WP_Error on failure
     */
    public function record_side_effects($record_id, $side_effects, $severity) {
        global $wpdb;
        
        // Validate inputs
        if (empty($record_id)) {
            return new \WP_Error('invalid_record_id', 'Vaccine record ID is required');
        }
        
        if (empty($side_effects) || !is_array($side_effects)) {
            return new \WP_Error('invalid_side_effects', 'Side effects must be a non-empty array');
        }
        
        if (empty($severity)) {
            return new \WP_Error('invalid_severity', 'Severity is required');
        }
        
        // Validate severity
        $valid_severities = ['mild', 'moderate', 'severe'];
        if (!in_array($severity, $valid_severities)) {
            return new \WP_Error('invalid_severity', 'Severity must be one of: ' . implode(', ', $valid_severities));
        }
        
        // Verify vaccine record exists
        $vaccine_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kg_vaccine_records WHERE id = %d",
            $record_id
        ), ARRAY_A);
        
        if (!$vaccine_record) {
            return new \WP_Error('record_not_found', 'Vaccine record not found');
        }
        
        // Ensure vaccine is marked as done
        if ($vaccine_record['status'] !== 'done') {
            return new \WP_Error('vaccine_not_done', 'Can only record side effects for completed vaccines');
        }
        
        // Insert side effect record
        $result = $wpdb->insert(
            $this->table_name,
            [
                'vaccine_record_id' => $record_id,
                'child_id' => $vaccine_record['child_id'],
                'vaccine_code' => $vaccine_record['vaccine_code'],
                'side_effects' => json_encode($side_effects),
                'severity' => $severity,
                'reported_at' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$result) {
            return new \WP_Error('insert_failed', $wpdb->last_error);
        }
        
        $side_effect_id = $wpdb->insert_id;
        
        error_log("Recorded side effects for vaccine record {$record_id}: severity {$severity}");
        
        return $side_effect_id;
    }
    
    /**
     * Get side effects for a specific vaccine record
     * 
     * @param int $record_id Vaccine record ID
     * @return array|WP_Error Side effect records or WP_Error on failure
     */
    public function get_side_effects($record_id) {
        global $wpdb;
        
        if (empty($record_id)) {
            return new \WP_Error('invalid_record_id', 'Vaccine record ID is required');
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT se.*, v.name as vaccine_name, v.name_short
             FROM {$this->table_name} se
             LEFT JOIN {$wpdb->prefix}kg_vaccines v ON se.vaccine_code = v.code
             WHERE se.vaccine_record_id = %d
             ORDER BY se.reported_at DESC",
            $record_id
        ), ARRAY_A);
        
        if ($wpdb->last_error) {
            return new \WP_Error('database_error', $wpdb->last_error);
        }
        
        // Parse JSON side_effects field
        foreach ($results as &$record) {
            if (isset($record['side_effects']) && !empty($record['side_effects'])) {
                $record['side_effects'] = json_decode($record['side_effects'], true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get complete side effect history for a child
     * 
     * @param int $child_id Child ID
     * @return array|WP_Error Array of side effect records or WP_Error on failure
     */
    public function get_child_side_effect_history($child_id) {
        global $wpdb;
        
        if (empty($child_id)) {
            return new \WP_Error('invalid_child_id', 'Child ID is required');
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT se.*, v.name as vaccine_name, v.name_short, vr.actual_date, vr.scheduled_date
             FROM {$this->table_name} se
             LEFT JOIN {$wpdb->prefix}kg_vaccines v ON se.vaccine_code = v.code
             LEFT JOIN {$wpdb->prefix}kg_vaccine_records vr ON se.vaccine_record_id = vr.id
             WHERE se.child_id = %d
             ORDER BY se.reported_at DESC",
            $child_id
        ), ARRAY_A);
        
        if ($wpdb->last_error) {
            return new \WP_Error('database_error', $wpdb->last_error);
        }
        
        // Parse JSON side_effects field and add computed fields
        foreach ($results as &$record) {
            if (isset($record['side_effects']) && !empty($record['side_effects'])) {
                $record['side_effects'] = json_decode($record['side_effects'], true);
            }
            
            // Calculate days since vaccination if actual_date is available
            if (!empty($record['actual_date'])) {
                $actual_timestamp = strtotime($record['actual_date']);
                $reported_timestamp = strtotime($record['reported_at']);
                $record['days_after_vaccine'] = floor(($reported_timestamp - $actual_timestamp) / 86400);
            }
        }
        
        return $results;
    }
    
    /**
     * Update side effect record
     * 
     * @param int $side_effect_id Side effect record ID
     * @param array $side_effects Updated side effects array
     * @param string $severity Updated severity
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_side_effects($side_effect_id, $side_effects, $severity) {
        global $wpdb;
        
        if (empty($side_effect_id)) {
            return new \WP_Error('invalid_id', 'Side effect record ID is required');
        }
        
        if (empty($side_effects) || !is_array($side_effects)) {
            return new \WP_Error('invalid_side_effects', 'Side effects must be a non-empty array');
        }
        
        // Validate severity
        $valid_severities = ['mild', 'moderate', 'severe'];
        if (!in_array($severity, $valid_severities)) {
            return new \WP_Error('invalid_severity', 'Severity must be one of: ' . implode(', ', $valid_severities));
        }
        
        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $side_effect_id
        ), ARRAY_A);
        
        if (!$existing) {
            return new \WP_Error('record_not_found', 'Side effect record not found');
        }
        
        $result = $wpdb->update(
            $this->table_name,
            [
                'side_effects' => json_encode($side_effects),
                'severity' => $severity,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $side_effect_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return new \WP_Error('update_failed', $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Delete side effect record
     * 
     * @param int $side_effect_id Side effect record ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete_side_effects($side_effect_id) {
        global $wpdb;
        
        if (empty($side_effect_id)) {
            return new \WP_Error('invalid_id', 'Side effect record ID is required');
        }
        
        // Check if record exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE id = %d",
            $side_effect_id
        ));
        
        if (!$existing) {
            return new \WP_Error('record_not_found', 'Side effect record not found');
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $side_effect_id],
            ['%d']
        );
        
        if (!$result) {
            return new \WP_Error('delete_failed', $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Get side effect statistics for a specific vaccine
     * 
     * @param string $vaccine_code Vaccine code
     * @return array|WP_Error Statistics or WP_Error on failure
     */
    public function get_vaccine_side_effect_stats($vaccine_code) {
        global $wpdb;
        
        if (empty($vaccine_code)) {
            return new \WP_Error('invalid_vaccine_code', 'Vaccine code is required');
        }
        
        // Get total count and severity breakdown
        $severity_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT severity, COUNT(*) as count
             FROM {$this->table_name}
             WHERE vaccine_code = %s
             GROUP BY severity",
            $vaccine_code
        ), ARRAY_A);
        
        if ($wpdb->last_error) {
            return new \WP_Error('database_error', $wpdb->last_error);
        }
        
        $stats = [
            'total_reports' => 0,
            'mild' => 0,
            'moderate' => 0,
            'severe' => 0
        ];
        
        foreach ($severity_stats as $row) {
            $stats[$row['severity']] = (int)$row['count'];
            $stats['total_reports'] += (int)$row['count'];
        }
        
        // Get most common side effects
        $all_side_effects = $wpdb->get_col($wpdb->prepare(
            "SELECT side_effects FROM {$this->table_name} WHERE vaccine_code = %s",
            $vaccine_code
        ));
        
        $side_effect_frequency = [];
        
        foreach ($all_side_effects as $json_data) {
            $effects = json_decode($json_data, true);
            if (is_array($effects)) {
                foreach ($effects as $effect) {
                    if (isset($effect['name'])) {
                        $name = $effect['name'];
                        if (!isset($side_effect_frequency[$name])) {
                            $side_effect_frequency[$name] = 0;
                        }
                        $side_effect_frequency[$name]++;
                    }
                }
            }
        }
        
        // Sort by frequency
        arsort($side_effect_frequency);
        
        $stats['common_side_effects'] = array_slice($side_effect_frequency, 0, 10, true);
        
        return $stats;
    }
}
