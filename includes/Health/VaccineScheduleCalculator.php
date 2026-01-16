<?php
namespace KG_Core\Health;

/**
 * VaccineScheduleCalculator - Calculate vaccine schedules based on birth date
 */
class VaccineScheduleCalculator {
    
    /**
     * Calculate vaccine schedule for a child
     * 
     * @param string $birth_date Child's birth date (Y-m-d format)
     * @param array $vaccines Array of vaccine definitions
     * @param bool $include_private Include private vaccines
     * @return array Calculated schedule with dates
     */
    public function calculate($birth_date, $vaccines, $include_private = false) {
        if (empty($birth_date) || empty($vaccines)) {
            return [];
        }
        
        $birth_timestamp = strtotime($birth_date);
        if ($birth_timestamp === false) {
            return [];
        }
        
        $schedule = [];
        
        foreach ($vaccines as $vaccine) {
            // Skip private vaccines if not requested
            if (!$include_private && !$vaccine['is_mandatory']) {
                continue;
            }
            
            // Skip inactive vaccines
            if (!$vaccine['is_active']) {
                continue;
            }
            
            $scheduled_date = $this->calculate_vaccine_date($birth_date, $vaccine['timing_rule']);
            
            if ($scheduled_date) {
                $schedule[] = [
                    'vaccine_code' => $vaccine['code'],
                    'vaccine_name' => $vaccine['name'],
                    'vaccine_short_name' => $vaccine['name_short'],
                    'description' => $vaccine['description'],
                    'is_mandatory' => $vaccine['is_mandatory'],
                    'scheduled_date' => $scheduled_date,
                    'min_age_days' => $vaccine['min_age_days'],
                    'max_age_days' => $vaccine['max_age_days'],
                    'depends_on' => $vaccine['depends_on'],
                    'brand_options' => $vaccine['brand_options'],
                    'sort_order' => $vaccine['sort_order']
                ];
            }
        }
        
        // Sort by scheduled date
        usort($schedule, function($a, $b) {
            return strtotime($a['scheduled_date']) - strtotime($b['scheduled_date']);
        });
        
        return $schedule;
    }
    
    /**
     * Calculate specific vaccine date based on timing rule
     * 
     * @param string $birth_date Birth date
     * @param array $timing_rule Timing rule from vaccine definition
     * @return string|null Calculated date in Y-m-d format
     */
    private function calculate_vaccine_date($birth_date, $timing_rule) {
        $birth_timestamp = strtotime($birth_date);
        
        if (!isset($timing_rule['type'])) {
            return null;
        }
        
        $type = $timing_rule['type'];
        $offset_days = isset($timing_rule['offset_days']) ? (int)$timing_rule['offset_days'] : 0;
        
        switch ($type) {
            case 'birth':
                // At birth + offset
                $target_timestamp = $birth_timestamp + ($offset_days * 86400);
                break;
                
            case 'day':
                // Specific day after birth
                $days = isset($timing_rule['value']) ? (int)$timing_rule['value'] : 0;
                $target_timestamp = $birth_timestamp + (($days + $offset_days) * 86400);
                break;
                
            case 'week':
                // Specific week after birth
                $weeks = isset($timing_rule['value']) ? (int)$timing_rule['value'] : 0;
                $days = ($weeks * 7) + $offset_days;
                $target_timestamp = $birth_timestamp + ($days * 86400);
                break;
                
            case 'month':
                // Specific month after birth
                $months = isset($timing_rule['value']) ? (int)$timing_rule['value'] : 0;
                $target_timestamp = strtotime("+{$months} months", $birth_timestamp);
                if ($offset_days != 0) {
                    $target_timestamp = strtotime("+{$offset_days} days", $target_timestamp);
                }
                break;
                
            case 'custom':
                // Custom logic - handle special cases
                $custom_logic = isset($timing_rule['custom_logic']) ? $timing_rule['custom_logic'] : '';
                return $this->handle_custom_logic($birth_date, $custom_logic);
                
            default:
                return null;
        }
        
        return date('Y-m-d', $target_timestamp);
    }
    
    /**
     * Handle custom timing logic
     * 
     * @param string $birth_date Birth date
     * @param string $logic Custom logic identifier
     * @return string|null Calculated date
     */
    private function handle_custom_logic($birth_date, $logic) {
        switch ($logic) {
            case 'annual_flu':
                // Flu vaccine: 6 months after birth, then annually in October
                $birth_timestamp = strtotime($birth_date);
                $six_months = strtotime("+6 months", $birth_timestamp);
                
                // Get the next October after 6 months age
                $current_year = date('Y', $six_months);
                $next_october = strtotime("{$current_year}-10-01");
                
                if ($next_october < $six_months) {
                    $next_october = strtotime(($current_year + 1) . "-10-01");
                }
                
                return date('Y-m-d', $next_october);
                
            default:
                return null;
        }
    }
    
    /**
     * Check if a vaccine is due based on child's age
     * 
     * @param string $birth_date Child's birth date
     * @param int $min_age_days Minimum age in days
     * @param int $max_age_days Maximum age in days (null = no limit)
     * @return bool True if vaccine is due
     */
    public function is_vaccine_due($birth_date, $min_age_days, $max_age_days = null) {
        $birth_timestamp = strtotime($birth_date);
        $now = time();
        
        $age_days = floor(($now - $birth_timestamp) / 86400);
        
        if ($age_days < $min_age_days) {
            return false;
        }
        
        if ($max_age_days !== null && $age_days > $max_age_days) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get child's age in days
     * 
     * @param string $birth_date Birth date
     * @return int Age in days
     */
    public function get_age_in_days($birth_date) {
        $birth_timestamp = strtotime($birth_date);
        $now = time();
        
        return floor(($now - $birth_timestamp) / 86400);
    }
    
    /**
     * Get child's age in months
     * 
     * @param string $birth_date Birth date
     * @return int Age in months
     */
    public function get_age_in_months($birth_date) {
        $birth_date_obj = new \DateTime($birth_date);
        $now = new \DateTime();
        
        $diff = $birth_date_obj->diff($now);
        
        return ($diff->y * 12) + $diff->m;
    }
}
