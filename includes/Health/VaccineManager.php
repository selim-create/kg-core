<?php
namespace KG_Core\Health;

/**
 * VaccineManager - Main business logic for vaccine operations
 */
class VaccineManager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'kg_vaccine_master';
    }
    
    /**
     * Load vaccines from JSON and seed to database
     * 
     * @param string $schedule_version Schedule version (e.g., 'TR_2026_v1')
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function load_vaccine_master_data($schedule_version = 'TR_2026_v1') {
        global $wpdb;
        
        try {
            // Determine JSON file path based on schedule version
            $json_file = $this->get_json_file_path($schedule_version);
            
            if (!file_exists($json_file)) {
                $error_msg = "Vaccine data file not found: {$json_file}";
                error_log('VaccineManager::load_vaccine_master_data Error: ' . $error_msg);
                return new \WP_Error('file_not_found', $error_msg);
            }
            
            // Read and parse JSON - suppress warnings
            $json_data = @file_get_contents($json_file);
            if ($json_data === false) {
                $error_msg = "Failed to read vaccine data file: {$json_file}";
                error_log('VaccineManager::load_vaccine_master_data Error: ' . $error_msg);
                return new \WP_Error('file_read_error', $error_msg);
            }
            
            $vaccines = json_decode($json_data, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_msg = 'Failed to parse vaccine data: ' . json_last_error_msg();
                error_log('VaccineManager::load_vaccine_master_data Error: ' . $error_msg);
                return new \WP_Error('json_parse_error', $error_msg);
            }
            
            if (empty($vaccines) || !is_array($vaccines)) {
                $error_msg = 'Invalid vaccine data structure';
                error_log('VaccineManager::load_vaccine_master_data Error: ' . $error_msg);
                return new \WP_Error('invalid_data', $error_msg);
            }
            
            // Begin transaction for data integrity
            $wpdb->query('START TRANSACTION');
        
        $inserted = 0;
        $updated = 0;
        
        foreach ($vaccines as $vaccine) {
            // Validate required fields
            if (!isset($vaccine['code']) || !isset($vaccine['name'])) {
                $wpdb->query('ROLLBACK');
                $error_msg = 'Vaccine missing required fields (code, name)';
                error_log('VaccineManager::load_vaccine_master_data Error: ' . $error_msg);
                return new \WP_Error('invalid_vaccine_data', $error_msg);
            }
            
            // Check if vaccine already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE code = %s AND schedule_version = %s",
                $vaccine['code'],
                $schedule_version
            ));
            
            $data = [
                'code' => $vaccine['code'],
                'name' => $vaccine['name'],
                'name_short' => isset($vaccine['name_short']) ? $vaccine['name_short'] : $vaccine['name'],
                'description' => isset($vaccine['description']) ? $vaccine['description'] : '',
                'timing_rule' => isset($vaccine['timing_rule']) ? json_encode($vaccine['timing_rule']) : null,
                'min_age_days' => isset($vaccine['min_age_days']) ? (int)$vaccine['min_age_days'] : 0,
                'max_age_days' => isset($vaccine['max_age_days']) ? (int)$vaccine['max_age_days'] : null,
                'is_mandatory' => isset($vaccine['is_mandatory']) ? (bool)$vaccine['is_mandatory'] : true,
                'depends_on' => isset($vaccine['depends_on']) ? $vaccine['depends_on'] : null,
                'brand_options' => isset($vaccine['brand_options']) ? json_encode($vaccine['brand_options']) : null,
                'schedule_version' => $schedule_version,
                'source_url' => isset($vaccine['source_url']) ? $vaccine['source_url'] : null,
                'sort_order' => isset($vaccine['sort_order']) ? (int)$vaccine['sort_order'] : 999,
                'is_active' => isset($vaccine['is_active']) ? (bool)$vaccine['is_active'] : true,
                'updated_at' => current_time('mysql')
            ];
            
            $format = [
                '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', 
                '%s', '%s', '%d', '%d', '%s'
            ];
            
            if ($existing) {
                // Update existing vaccine
                $result = $wpdb->update(
                    $this->table_name,
                    $data,
                    ['id' => $existing],
                    $format,
                    ['%d']
                );
                
                if ($result !== false) {
                    $updated++;
                }
            } else {
                // Insert new vaccine
                $data['created_at'] = current_time('mysql');
                $format[] = '%s';
                
                $result = $wpdb->insert(
                    $this->table_name,
                    $data,
                    $format
                );
                
                if ($result) {
                    $inserted++;
                }
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        error_log("Vaccine data loaded: {$inserted} inserted, {$updated} updated");
        
        return true;
        
        } catch ( \Exception $e ) {
            if (isset($wpdb)) {
                $wpdb->query('ROLLBACK');
            }
            error_log( 'VaccineManager::load_vaccine_master_data Exception: ' . $e->getMessage() );
            return new \WP_Error('exception', $e->getMessage());
        } catch ( \Error $e ) {
            if (isset($wpdb)) {
                $wpdb->query('ROLLBACK');
            }
            error_log( 'VaccineManager::load_vaccine_master_data Fatal Error: ' . $e->getMessage() );
            return new \WP_Error('fatal_error', $e->getMessage());
        }
    }
    
    /**
     * Get JSON file path for a schedule version
     * 
     * @param string $schedule_version Schedule version
     * @return string File path
     */
    private function get_json_file_path($schedule_version) {
        $vaccines_dir = defined('KG_CORE_PATH') ? KG_CORE_PATH . 'data/vaccines/' : plugin_dir_path(__FILE__) . '../../data/vaccines/';
        
        // Map schedule version to file
        $version_map = [
            'TR_2026_v1' => 'tr_2026_v1.json',
            'private' => 'private_vaccines.json'
        ];
        
        $filename = isset($version_map[$schedule_version]) ? $version_map[$schedule_version] : strtolower($schedule_version) . '.json';
        
        return $vaccines_dir . $filename;
    }
    
    /**
     * Get all vaccine definitions
     * 
     * @param bool $mandatory_only If true, only return mandatory vaccines
     * @return array|WP_Error Array of vaccines or WP_Error on failure
     */
    public function get_all_vaccines($mandatory_only = false) {
        global $wpdb;
        
        $where = 'WHERE is_active = 1';
        
        if ($mandatory_only) {
            $where .= ' AND is_mandatory = 1';
        }
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} {$where} ORDER BY sort_order ASC, code ASC",
            ARRAY_A
        );
        
        if ($wpdb->last_error) {
            return new \WP_Error('database_error', $wpdb->last_error);
        }
        
        // Parse JSON fields
        foreach ($results as &$vaccine) {
            if (isset($vaccine['timing_rule']) && !empty($vaccine['timing_rule'])) {
                $vaccine['timing_rule'] = json_decode($vaccine['timing_rule'], true);
            }
            if (isset($vaccine['brand_options']) && !empty($vaccine['brand_options'])) {
                $vaccine['brand_options'] = json_decode($vaccine['brand_options'], true);
            }
            
            // Convert boolean fields
            $vaccine['is_mandatory'] = (bool)$vaccine['is_mandatory'];
            $vaccine['is_active'] = (bool)$vaccine['is_active'];
        }
        
        return $results;
    }
    
    /**
     * Get single vaccine by code
     * 
     * @param string $code Vaccine code
     * @return array|WP_Error Vaccine data or WP_Error if not found
     */
    public function get_vaccine_by_code($code) {
        global $wpdb;
        
        $vaccine = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE code = %s AND is_active = 1 LIMIT 1",
            $code
        ), ARRAY_A);
        
        if ($wpdb->last_error) {
            return new \WP_Error('database_error', $wpdb->last_error);
        }
        
        if (!$vaccine) {
            return new \WP_Error('vaccine_not_found', "Vaccine not found: {$code}");
        }
        
        // Parse JSON fields
        if (isset($vaccine['timing_rule']) && !empty($vaccine['timing_rule'])) {
            $vaccine['timing_rule'] = json_decode($vaccine['timing_rule'], true);
        }
        if (isset($vaccine['brand_options']) && !empty($vaccine['brand_options'])) {
            $vaccine['brand_options'] = json_decode($vaccine['brand_options'], true);
        }
        
        // Convert boolean fields
        $vaccine['is_mandatory'] = (bool)$vaccine['is_mandatory'];
        $vaccine['is_active'] = (bool)$vaccine['is_active'];
        
        return $vaccine;
    }
    
    /**
     * Get available schedule versions
     * 
     * @return array|WP_Error Array of schedule versions or WP_Error on failure
     */
    public function get_schedule_versions() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT DISTINCT schedule_version FROM {$this->table_name} WHERE is_active = 1 ORDER BY schedule_version DESC",
            ARRAY_A
        );
        
        if ($wpdb->last_error) {
            return new \WP_Error('database_error', $wpdb->last_error);
        }
        
        $versions = [];
        foreach ($results as $row) {
            $versions[] = $row['schedule_version'];
        }
        
        return $versions;
    }
}
