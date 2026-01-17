<?php
namespace KG_Core\Health;

/**
 * VaccineRecordManager - Manage user vaccine records
 */
class VaccineRecordManager {
    
    private $table_name;
    private $vaccine_manager;
    private $schedule_calculator;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'kg_vaccine_records';
        $this->vaccine_manager = new VaccineManager();
        $this->schedule_calculator = new VaccineScheduleCalculator();
    }
    
    /**
     * Create initial vaccine schedule for a child
     * 
     * @param int $user_id Parent user ID
     * @param int $child_id Child ID
     * @param string $birth_date Birth date in Y-m-d format
     * @param bool $include_private Include private/optional vaccines
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function create_schedule_for_child($user_id, $child_id, $birth_date, $include_private = false) {
        global $wpdb;
        
        // Validate inputs
        if (empty($user_id) || empty($child_id) || empty($birth_date)) {
            return new \WP_Error('invalid_parameters', 'User ID, Child ID, and Birth Date are required');
        }
        
        // Validate birth date
        if (strtotime($birth_date) === false) {
            return new \WP_Error('invalid_birth_date', 'Invalid birth date format. Use Y-m-d format');
        }
        
        // Check if schedule already exists for this child
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE child_id = %s",
            $child_id
        ));
        
        if ($existing > 0) {
            return new \WP_Error('schedule_exists', 'Vaccine schedule already exists for this child');
        }
        
        // Get all vaccines
        $vaccines = $this->vaccine_manager->get_all_vaccines(!$include_private);
        
        if (is_wp_error($vaccines)) {
            return $vaccines;
        }
        
        if (empty($vaccines)) {
            return new \WP_Error('no_vaccines', 'No vaccines found to create schedule');
        }
        
        // Calculate schedule
        $schedule = $this->schedule_calculator->calculate($birth_date, $vaccines, $include_private);
        
        if (empty($schedule)) {
            return new \WP_Error('schedule_calculation_failed', 'Failed to calculate vaccine schedule');
        }
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        $created = 0;
        
        foreach ($schedule as $vaccine) {
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'user_id' => $user_id,
                    'child_id' => $child_id,
                    'vaccine_code' => $vaccine['vaccine_code'],
                    'scheduled_date' => $vaccine['scheduled_date'],
                    'status' => 'scheduled',
                    'is_mandatory' => $vaccine['is_mandatory'] ? 1 : 0,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );
            
            if ($result) {
                $created++;
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        error_log("Created vaccine schedule for child {$child_id}: {$created} vaccines");
        
        return true;
    }
    
    /**
     * Get vaccines for a specific child
     * 
     * @param int $child_id Child ID
     * @param string|null $status Filter by status (scheduled, done, skipped, overdue)
     * @return array|WP_Error Array of vaccine records or WP_Error on failure
     */
    public function get_child_vaccines($child_id, $status = null) {
        global $wpdb;
        
        if (empty($child_id)) {
            return new \WP_Error('invalid_child_id', 'Child ID is required');
        }
        
        $where = $wpdb->prepare("WHERE child_id = %s", $child_id);
        
        if (!empty($status)) {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }
        
        $results = $wpdb->get_results(
            "SELECT vr.*, v.name, v.name_short, v.description, v.brand_options
             FROM {$this->table_name} vr
             LEFT JOIN {$wpdb->prefix}kg_vaccine_master v ON vr.vaccine_code = v.code
             {$where}
             ORDER BY vr.scheduled_date ASC",
            ARRAY_A
        );
        
        if ($wpdb->last_error) {
            return new \WP_Error('database_error', $wpdb->last_error);
        }
        
        // Parse JSON fields and convert booleans
        foreach ($results as &$record) {
            if (isset($record['brand_options']) && !empty($record['brand_options'])) {
                $record['brand_options'] = json_decode($record['brand_options'], true);
            }
            $record['is_mandatory'] = (bool)$record['is_mandatory'];
        }
        
        return $results;
    }
    
    /**
     * Mark vaccine as done
     * 
     * @param int $record_id Vaccine record ID
     * @param string $actual_date Actual vaccination date in Y-m-d format
     * @param string $notes Optional notes
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function mark_as_done($record_id, $actual_date, $notes = '') {
        global $wpdb;
        
        if (empty($record_id) || empty($actual_date)) {
            return new \WP_Error('invalid_parameters', 'Record ID and actual date are required');
        }
        
        // Validate date
        if (strtotime($actual_date) === false) {
            return new \WP_Error('invalid_date', 'Invalid date format. Use Y-m-d format');
        }
        
        // Check if record exists
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $record_id
        ), ARRAY_A);
        
        if (!$record) {
            return new \WP_Error('record_not_found', 'Vaccine record not found');
        }
        
        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => 'done',
                'actual_date' => $actual_date,
                'notes' => $notes,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $record_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return new \WP_Error('update_failed', $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Update vaccine status
     * 
     * @param int $record_id Vaccine record ID
     * @param string $status New status (scheduled, done, skipped, overdue)
     * @param string $notes Optional notes
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update_status($record_id, $status, $notes = '') {
        global $wpdb;
        
        if (empty($record_id) || empty($status)) {
            return new \WP_Error('invalid_parameters', 'Record ID and status are required');
        }
        
        // Validate status
        $valid_statuses = ['scheduled', 'done', 'skipped', 'overdue'];
        if (!in_array($status, $valid_statuses)) {
            return new \WP_Error('invalid_status', 'Invalid status. Must be one of: ' . implode(', ', $valid_statuses));
        }
        
        // Check if record exists
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $record_id
        ), ARRAY_A);
        
        if (!$record) {
            return new \WP_Error('record_not_found', 'Vaccine record not found');
        }
        
        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => $status,
                'notes' => $notes,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $record_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return new \WP_Error('update_failed', $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Add private/optional vaccine to child's schedule
     * 
     * @param int $user_id Parent user ID
     * @param int $child_id Child ID
     * @param string $vaccine_code Vaccine code to add
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function add_private_vaccine($user_id, $child_id, $vaccine_code) {
        global $wpdb;
        
        if (empty($user_id) || empty($child_id) || empty($vaccine_code)) {
            return new \WP_Error('invalid_parameters', 'User ID, Child ID, and Vaccine Code are required');
        }
        
        // Check if vaccine exists and is valid
        $vaccine = $this->vaccine_manager->get_vaccine_by_code($vaccine_code);
        
        if (is_wp_error($vaccine)) {
            return $vaccine;
        }
        
        // Check if already added
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE child_id = %s AND vaccine_code = %s",
            $child_id,
            $vaccine_code
        ));
        
        if ($existing) {
            return new \WP_Error('vaccine_exists', 'This vaccine is already in the child\'s schedule');
        }
        
        // Get child's birth date to calculate scheduled date
        $child_birth_date = $wpdb->get_var($wpdb->prepare(
            "SELECT birth_date FROM {$wpdb->prefix}kg_children WHERE id = %d",
            $child_id
        ));
        
        if (!$child_birth_date) {
            return new \WP_Error('child_not_found', 'Child not found or birth date missing');
        }
        
        // Calculate scheduled date using the calculator
        $schedule = $this->schedule_calculator->calculate($child_birth_date, [$vaccine], false);
        
        if (!empty($schedule) && isset($schedule[0]['scheduled_date'])) {
            $scheduled_date = $schedule[0]['scheduled_date'];
        } else {
            $scheduled_date = current_time('Y-m-d');
        }
        
        // Insert the vaccine record
        $result = $wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'child_id' => $child_id,
                'vaccine_code' => $vaccine_code,
                'scheduled_date' => $scheduled_date,
                'status' => 'scheduled',
                'is_mandatory' => $vaccine['is_mandatory'] ? 1 : 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );
        
        if (!$result) {
            return new \WP_Error('insert_failed', $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Get upcoming vaccines for a child
     * 
     * @param int $child_id Child ID
     * @param int $days_ahead Number of days to look ahead (default: 30)
     * @return array|WP_Error Array of upcoming vaccines or WP_Error on failure
     */
    public function get_upcoming_vaccines($child_id, $days_ahead = 30) {
        global $wpdb;
        
        if (empty($child_id)) {
            return new \WP_Error('invalid_child_id', 'Child ID is required');
        }
        
        $today = current_time('Y-m-d');
        $future_date = date('Y-m-d', strtotime("+{$days_ahead} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT vr.*, v.name, v.name_short, v.description
             FROM {$this->table_name} vr
             LEFT JOIN {$wpdb->prefix}kg_vaccine_master v ON vr.vaccine_code = v.code
             WHERE vr.child_id = %s
             AND vr.status = 'scheduled'
             AND vr.scheduled_date BETWEEN %s AND %s
             ORDER BY vr.scheduled_date ASC",
            $child_id,
            $today,
            $future_date
        ), ARRAY_A);
        
        if ($wpdb->last_error) {
            return new \WP_Error('database_error', $wpdb->last_error);
        }
        
        // Convert booleans
        foreach ($results as &$record) {
            $record['is_mandatory'] = (bool)$record['is_mandatory'];
        }
        
        return $results;
    }
    
    /**
     * Get overdue vaccines for a child
     * 
     * @param int $child_id Child ID
     * @return array|WP_Error Array of overdue vaccines or WP_Error on failure
     */
    public function get_overdue_vaccines($child_id) {
        global $wpdb;
        
        if (empty($child_id)) {
            return new \WP_Error('invalid_child_id', 'Child ID is required');
        }
        
        $today = current_time('Y-m-d');
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT vr.*, v.name, v.name_short, v.description, v.max_age_days
             FROM {$this->table_name} vr
             LEFT JOIN {$wpdb->prefix}kg_vaccine_master v ON vr.vaccine_code = v.code
             WHERE vr.child_id = %s
             AND vr.status = 'scheduled'
             AND vr.scheduled_date < %s
             ORDER BY vr.scheduled_date ASC",
            $child_id,
            $today
        ), ARRAY_A);
        
        if ($wpdb->last_error) {
            return new \WP_Error('database_error', $wpdb->last_error);
        }
        
        // Convert booleans and calculate days overdue
        foreach ($results as &$record) {
            $record['is_mandatory'] = (bool)$record['is_mandatory'];
            $scheduled_timestamp = strtotime($record['scheduled_date']);
            $today_timestamp = strtotime($today);
            $record['days_overdue'] = floor(($today_timestamp - $scheduled_timestamp) / 86400);
        }
        
        return $results;
    }
}
