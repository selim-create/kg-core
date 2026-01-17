<?php
namespace KG_Core\Health;

/**
 * PrivateVaccineWizard - Handle private vaccine management
 */
class PrivateVaccineWizard {
    
    private $config_file;
    private $configs;
    
    public function __construct() {
        $this->config_file = KG_CORE_PATH . 'data/vaccines/private_vaccine_configs.json';
        $this->load_configs();
    }
    
    /**
     * Load private vaccine configurations from JSON
     */
    private function load_configs() {
        if (!file_exists($this->config_file)) {
            $this->configs = [];
            return;
        }
        
        $json = file_get_contents($this->config_file);
        $this->configs = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Failed to parse private vaccine configs: ' . json_last_error_msg());
            $this->configs = [];
        }
    }
    
    /**
     * Get list of all private vaccine types
     * 
     * @return array List of vaccine types with basic info
     */
    public function get_private_types() {
        $types = [];
        
        foreach ($this->configs as $code => $config) {
            $types[] = [
                'code' => $code,
                'name' => $config['name'],
                'description' => $config['description'],
                'is_annual' => isset($config['is_annual']) ? $config['is_annual'] : false,
                'brand_count' => count($config['brands'])
            ];
        }
        
        return $types;
    }
    
    /**
     * Get configuration for a specific vaccine type
     * 
     * @param string $type Vaccine type code
     * @return array|WP_Error Configuration or error
     */
    public function get_type_config($type) {
        if (!isset($this->configs[$type])) {
            return new \WP_Error('invalid_type', 'Invalid vaccine type');
        }
        
        return $this->configs[$type];
    }
    
    /**
     * Validate private vaccine addition
     * 
     * @param int $user_id User ID
     * @param string $child_id Child ID
     * @param string $type Vaccine type code
     * @param string $brand_code Brand code
     * @param array $options Additional options (schedule_key for multi-schedule vaccines)
     * @return array|WP_Error Validation result or error
     */
    public function validate_addition($user_id, $child_id, $type, $brand_code, $options = []) {
        // Get vaccine configuration
        $config = $this->get_type_config($type);
        if (is_wp_error($config)) {
            return $config;
        }
        
        // Find brand configuration
        $brand = null;
        foreach ($config['brands'] as $b) {
            if ($b['code'] === $brand_code) {
                $brand = $b;
                break;
            }
        }
        
        if (!$brand) {
            return new \WP_Error('invalid_brand', 'Invalid brand code');
        }
        
        // Get child's birth date and calculate age from user meta
        $children = get_user_meta($user_id, '_kg_children', true);
        
        if (!is_array($children)) {
            return new \WP_Error('children_not_found', 'No children found for user');
        }
        
        // Find the matching child
        $child_data = null;
        foreach ($children as $child) {
            if (isset($child['id']) && $child['id'] === $child_id) {
                $child_data = $child;
                break;
            }
        }
        
        if (!$child_data) {
            return new \WP_Error('child_not_found', 'Child not found');
        }
        
        if (!isset($child_data['birth_date'])) {
            return new \WP_Error('birth_date_missing', 'Child birth date not found');
        }
        
        $birth_date = new \DateTime($child_data['birth_date']);
        $today = new \DateTime();
        $age_days = $birth_date->diff($today)->days;
        $age_months = floor($age_days / 30.44);
        $age_weeks = floor($age_days / 7);
        
        // Validate age requirements
        $warnings = [];
        $errors = [];
        
        // Handle multi-schedule vaccines (like Bexsero)
        if (isset($brand['schedules'])) {
            $schedule_key = isset($options['schedule_key']) ? $options['schedule_key'] : null;
            
            if (!$schedule_key) {
                return new \WP_Error('schedule_required', 'Schedule selection required for this vaccine');
            }
            
            if (!isset($brand['schedules'][$schedule_key])) {
                return new \WP_Error('invalid_schedule', 'Invalid schedule key');
            }
            
            $schedule = $brand['schedules'][$schedule_key];
            
            if (isset($schedule['min_age_months']) && $age_months < $schedule['min_age_months']) {
                $errors[] = "Çocuk {$schedule['min_age_months']} aydan küçük";
            }
            
            if (isset($schedule['max_age_months']) && $age_months > $schedule['max_age_months']) {
                $warnings[] = "Önerilen yaş aralığı dışında ({$schedule['min_age_months']}-{$schedule['max_age_months']} ay)";
            }
        } else {
            // Standard vaccine age validation
            if (isset($brand['min_age_weeks']) && $age_weeks < $brand['min_age_weeks']) {
                $errors[] = "Çocuk {$brand['min_age_weeks']} haftalıktan küçük";
            }
            
            if (isset($brand['min_age_months']) && $age_months < $brand['min_age_months']) {
                $errors[] = "Çocuk {$brand['min_age_months']} aylıktan küçük";
            }
            
            // Age window for first dose - show as warning instead of error
            if (isset($brand['max_age_weeks_first_dose']) && $age_weeks > $brand['max_age_weeks_first_dose']) {
                $warnings[] = "İlk doz için önerilen yaş penceresi geçilmiş ({$brand['max_age_weeks_first_dose']} hafta). Doktorunuza danışmanız önerilir.";
            }
        }
        
        // Check for conflicts with existing vaccines
        $conflicts = $this->check_conflicts($child_id, $type, $brand_code);
        if (!empty($conflicts)) {
            $warnings = array_merge($warnings, $conflicts);
        }
        
        if (!empty($errors)) {
            return new \WP_Error('validation_failed', implode(', ', $errors), ['warnings' => $warnings]);
        }
        
        return [
            'valid' => true,
            'warnings' => $warnings,
            'age_days' => $age_days,
            'age_months' => $age_months,
            'age_weeks' => $age_weeks
        ];
    }
    
    /**
     * Add private vaccine to child's schedule
     * 
     * @param int $user_id User ID
     * @param string $child_id Child ID
     * @param string $type Vaccine type code
     * @param string $brand_code Brand code
     * @param array $options Additional options
     * @return array|WP_Error Array of created record IDs or error
     */
    public function add_to_schedule($user_id, $child_id, $type, $brand_code, $options = []) {
        // Validate first
        $validation = $this->validate_addition($user_id, $child_id, $type, $brand_code, $options);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Get configuration
        $config = $this->get_type_config($type);
        $brand = null;
        foreach ($config['brands'] as $b) {
            if ($b['code'] === $brand_code) {
                $brand = $b;
                break;
            }
        }
        
        // Get child's birth date from user meta
        $children = get_user_meta($user_id, '_kg_children', true);
        $child_data = null;
        if (is_array($children)) {
            foreach ($children as $child) {
                if (isset($child['id']) && $child['id'] === $child_id) {
                    $child_data = $child;
                    break;
                }
            }
        }
        
        if (!$child_data || !isset($child_data['birth_date'])) {
            return new \WP_Error('child_not_found', 'Child birth date not found');
        }
        
        $birth_date = new \DateTime($child_data['birth_date']);
        
        $record_ids = [];
        
        // Handle multi-schedule vaccines
        if (isset($brand['schedules'])) {
            $schedule_key = $options['schedule_key'];
            $schedule = $brand['schedules'][$schedule_key];
            
            $doses = $schedule['total_doses'];
            $intervals = $schedule['dose_intervals_months'];
            
            for ($i = 0; $i < $doses; $i++) {
                $scheduled_date = clone $birth_date;
                $scheduled_date->modify("+{$intervals[$i]} months");
                
                $record_id = $this->create_vaccine_record(
                    $user_id,
                    $child_id,
                    "{$type}-{$brand_code}-" . ($i + 1),
                    "{$config['name']} - {$brand['name']} (Doz " . ($i + 1) . ")",
                    $scheduled_date->format('Y-m-d')
                );
                
                if (!is_wp_error($record_id)) {
                    $record_ids[] = $record_id;
                }
            }
        } elseif (isset($brand['dose_intervals_weeks'])) {
            // Week-based intervals (e.g., Rotavirus)
            $doses = $brand['total_doses'];
            $intervals = $brand['dose_intervals_weeks'];
            
            for ($i = 0; $i < $doses; $i++) {
                $scheduled_date = clone $birth_date;
                $total_weeks = array_sum(array_slice($intervals, 0, $i + 1));
                $scheduled_date->modify("+{$total_weeks} weeks");
                
                $record_id = $this->create_vaccine_record(
                    $user_id,
                    $child_id,
                    "{$type}-{$brand_code}-" . ($i + 1),
                    "{$config['name']} - {$brand['name']} (Doz " . ($i + 1) . ")",
                    $scheduled_date->format('Y-m-d')
                );
                
                if (!is_wp_error($record_id)) {
                    $record_ids[] = $record_id;
                }
            }
        } elseif (isset($brand['dose_intervals_months'])) {
            // Month-based intervals
            $doses = $brand['total_doses'];
            $intervals = $brand['dose_intervals_months'];
            
            for ($i = 0; $i < $doses; $i++) {
                $scheduled_date = clone $birth_date;
                $total_months = array_sum(array_slice($intervals, 0, $i + 1));
                $scheduled_date->modify("+{$total_months} months");
                
                $record_id = $this->create_vaccine_record(
                    $user_id,
                    $child_id,
                    "{$type}-{$brand_code}-" . ($i + 1),
                    "{$config['name']} - {$brand['name']} (Doz " . ($i + 1) . ")",
                    $scheduled_date->format('Y-m-d')
                );
                
                if (!is_wp_error($record_id)) {
                    $record_ids[] = $record_id;
                }
            }
        } else {
            // Single dose vaccine
            $scheduled_date = new \DateTime();
            $scheduled_date->modify('+1 week'); // Default to next week
            
            $record_id = $this->create_vaccine_record(
                $user_id,
                $child_id,
                "{$type}-{$brand_code}",
                "{$config['name']} - {$brand['name']}",
                $scheduled_date->format('Y-m-d')
            );
            
            if (!is_wp_error($record_id)) {
                $record_ids[] = $record_id;
            }
        }
        
        return $record_ids;
    }
    
    /**
     * Check for conflicts with existing vaccines
     * 
     * @param string $child_id Child ID
     * @param string $type Vaccine type
     * @param string $brand_code Brand code
     * @return array Array of warning messages
     */
    private function check_conflicts($child_id, $type, $brand_code) {
        global $wpdb;
        
        $warnings = [];
        
        // Check if similar vaccine already exists
        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT vaccine_code, status FROM {$wpdb->prefix}kg_vaccine_records 
            WHERE child_id = %s AND vaccine_code LIKE %s",
            $child_id,
            $wpdb->esc_like($type) . '%'
        ), ARRAY_A);
        
        if (!empty($existing)) {
            $done_count = 0;
            foreach ($existing as $record) {
                if ($record['status'] === 'done') {
                    $done_count++;
                }
            }
            
            if ($done_count > 0) {
                $warnings[] = "Bu aşı türünden {$done_count} doz zaten yapılmış";
            }
        }
        
        return $warnings;
    }
    
    /**
     * Create vaccine record
     * 
     * @param int $user_id User ID
     * @param string $child_id Child ID
     * @param string $vaccine_code Vaccine code
     * @param string $vaccine_name Vaccine name
     * @param string $scheduled_date Scheduled date
     * @return int|WP_Error Record ID or error
     */
    private function create_vaccine_record($user_id, $child_id, $vaccine_code, $vaccine_name, $scheduled_date) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'kg_vaccine_records',
            [
                'child_id' => $child_id,
                'user_id' => $user_id,
                'vaccine_code' => $vaccine_code,
                'status' => 'upcoming',
                'scheduled_date' => $scheduled_date,
                'is_mandatory' => 0, // Private vaccines are always optional
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
        );
        
        if (!$result) {
            return new \WP_Error('insert_failed', $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get timing rule for a private vaccine code
     * 
     * @param string $vaccine_code Vaccine code (e.g., "rotavirus-rotarix-1")
     * @return array|null Timing rule array or null if not found
     */
    public function get_timing_rule_for_vaccine($vaccine_code) {
        // Parse vaccine_code to extract type, brand, and dose number
        // Format: type-brand-dose (e.g., "rotavirus-rotarix-1")
        $parts = explode('-', $vaccine_code);
        
        if (count($parts) < 2) {
            return null;
        }
        
        // Extract dose number if present (last part if it's numeric)
        $dose_number = null;
        if (is_numeric($parts[count($parts) - 1])) {
            $dose_number = (int)array_pop($parts);
        }
        
        // Brand code is everything after the type
        $type = $parts[0];
        $brand_code = implode('-', array_slice($parts, 1));
        
        // Get configuration
        $config = $this->get_type_config($type);
        if (is_wp_error($config) || !isset($config['brands'])) {
            return null;
        }
        
        // Find brand configuration
        $brand = null;
        foreach ($config['brands'] as $b) {
            if ($b['code'] === $brand_code) {
                $brand = $b;
                break;
            }
        }
        
        if (!$brand) {
            return null;
        }
        
        // Generate timing_rule based on brand configuration
        $timing_rule = null;
        
        if (isset($brand['schedules'])) {
            // Multi-schedule vaccines (like Bexsero) - use first schedule as default
            $first_schedule_key = array_key_first($brand['schedules']);
            $schedule = $brand['schedules'][$first_schedule_key];
            
            if (isset($schedule['dose_intervals_months']) && $dose_number && $dose_number <= count($schedule['dose_intervals_months'])) {
                $months = $schedule['dose_intervals_months'][$dose_number - 1];
                $timing_rule = [
                    'type' => 'month',
                    'value' => $months,
                    'tolerance_days_before' => 0,
                    'tolerance_days_after' => 14
                ];
            }
        } elseif (isset($brand['dose_intervals_weeks']) && $dose_number) {
            // Week-based intervals (e.g., Rotavirus)
            // The intervals are cumulative offsets from birth date
            // Example: [0, 4] means dose 1 at 0 weeks, dose 2 at 0+4=4 weeks
            if ($dose_number <= count($brand['dose_intervals_weeks'])) {
                // array_slice($intervals, 0, $dose_number) gives the first $dose_number elements
                // For dose 1: slice([0, 4], 0, 1) = [0], sum = 0 weeks from birth
                // For dose 2: slice([0, 4], 0, 2) = [0, 4], sum = 4 weeks from birth
                $weeks = array_sum(array_slice($brand['dose_intervals_weeks'], 0, $dose_number));
                $timing_rule = [
                    'type' => 'week',
                    'value' => $weeks,
                    'tolerance_days_before' => 0,
                    'tolerance_days_after' => 14
                ];
            }
        } elseif (isset($brand['dose_intervals_months']) && $dose_number) {
            // Month-based intervals
            // The intervals are cumulative offsets from birth date
            // Example: [0, 2, 6] means dose 1 at 0 months, dose 2 at 0+2=2 months, dose 3 at 0+2+6=8 months
            if ($dose_number <= count($brand['dose_intervals_months'])) {
                // array_slice($intervals, 0, $dose_number) gives the first $dose_number elements
                $months = array_sum(array_slice($brand['dose_intervals_months'], 0, $dose_number));
                $timing_rule = [
                    'type' => 'month',
                    'value' => $months,
                    'tolerance_days_before' => 0,
                    'tolerance_days_after' => 14
                ];
            }
        } elseif (isset($brand['min_age_months'])) {
            // Single dose vaccine with minimum age
            $timing_rule = [
                'type' => 'month',
                'value' => $brand['min_age_months'],
                'tolerance_days_before' => 0,
                'tolerance_days_after' => 30
            ];
        } elseif (isset($brand['min_age_weeks'])) {
            // Single dose vaccine with minimum age in weeks
            $timing_rule = [
                'type' => 'week',
                'value' => $brand['min_age_weeks'],
                'tolerance_days_before' => 0,
                'tolerance_days_after' => 14
            ];
        }
        
        return $timing_rule;
    }
    
    /**
     * Get name and description for a private vaccine code
     * 
     * @param string $vaccine_code Vaccine code (e.g., "rotavirus-rotarix-1")
     * @return array|null Array with 'name', 'name_short', 'description' or null if not found
     */
    public function get_vaccine_metadata($vaccine_code) {
        // Parse vaccine_code to extract type, brand, and dose number
        $parts = explode('-', $vaccine_code);
        
        if (count($parts) < 2) {
            return null;
        }
        
        // Extract dose number if present
        $dose_number = null;
        if (is_numeric($parts[count($parts) - 1])) {
            $dose_number = (int)array_pop($parts);
        }
        
        // Brand code is everything after the type
        $type = $parts[0];
        $brand_code = implode('-', array_slice($parts, 1));
        
        // Get configuration
        $config = $this->get_type_config($type);
        if (is_wp_error($config) || !isset($config['brands'])) {
            return null;
        }
        
        // Find brand configuration
        $brand = null;
        foreach ($config['brands'] as $b) {
            if ($b['code'] === $brand_code) {
                $brand = $b;
                break;
            }
        }
        
        if (!$brand) {
            return null;
        }
        
        // Build name with dose number
        $name = $config['name'] . ' - ' . $brand['name'];
        if ($dose_number) {
            $name .= ' (Doz ' . $dose_number . ')';
        }
        
        // Build short name
        $name_short = $config['name'];
        if ($dose_number) {
            $name_short .= '-' . $dose_number;
        }
        
        return [
            'name' => $name,
            'name_short' => $name_short,
            'description' => $config['description']
        ];
    }
    
    /**
     * Remove private vaccine series
     * 
     * @param int $user_id User ID
     * @param int $record_id Record ID to remove
     * @return bool|WP_Error True on success, error on failure
     */
    public function remove_series($user_id, $record_id) {
        global $wpdb;
        
        // Get the record to find related doses
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kg_vaccine_records WHERE id = %d AND user_id = %d",
            $record_id,
            $user_id
        ), ARRAY_A);
        
        if (!$record) {
            return new \WP_Error('record_not_found', 'Vaccine record not found');
        }
        
        // Extract base vaccine code (without dose number)
        $vaccine_code = $record['vaccine_code'];
        $base_code = preg_replace('/-\d+$/', '', $vaccine_code);
        
        // Delete all doses in the series
        $result = $wpdb->delete(
            $wpdb->prefix . 'kg_vaccine_records',
            [
                'child_id' => $record['child_id'],
                'user_id' => $user_id
            ],
            ['%s', '%d']
        );
        
        // More specific: only delete records matching the base code pattern
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}kg_vaccine_records 
            WHERE child_id = %s AND user_id = %d AND vaccine_code LIKE %s",
            $record['child_id'],
            $user_id,
            $wpdb->esc_like($base_code) . '%'
        ));
        
        return true;
    }
}
