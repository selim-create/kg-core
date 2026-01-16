<?php
namespace KG_Core\Health;

/**
 * VaccineStatsCalculator - Calculate vaccine statistics
 */
class VaccineStatsCalculator {
    
    /**
     * Get detailed vaccine statistics for a child
     * 
     * @param string $child_id Child ID
     * @return array|WP_Error Statistics or error
     */
    public function get_child_stats($child_id) {
        global $wpdb;
        
        // Get all vaccine records for child
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kg_vaccine_records WHERE child_id = %s ORDER BY scheduled_date",
            $child_id
        ), ARRAY_A);
        
        if (empty($records)) {
            return [
                'total' => 0,
                'done' => 0,
                'upcoming' => 0,
                'overdue' => 0,
                'skipped' => 0,
                'completion_rate' => 0,
                'next_vaccine' => null,
                'recent_vaccines' => []
            ];
        }
        
        $stats = [
            'total' => count($records),
            'done' => 0,
            'upcoming' => 0,
            'overdue' => 0,
            'skipped' => 0,
            'delayed' => 0,
            'by_month' => [],
            'by_status' => [],
            'timeline' => []
        ];
        
        $today = new \DateTime();
        $next_vaccine = null;
        $recent_vaccines = [];
        
        foreach ($records as $record) {
            // Count by status
            $status = $record['status'];
            $stats[$status]++;
            
            // Track by month
            $month = date('Y-m', strtotime($record['scheduled_date']));
            if (!isset($stats['by_month'][$month])) {
                $stats['by_month'][$month] = ['total' => 0, 'done' => 0];
            }
            $stats['by_month'][$month]['total']++;
            if ($status === 'done') {
                $stats['by_month'][$month]['done']++;
            }
            
            // Check for overdue
            $scheduled_date = new \DateTime($record['scheduled_date']);
            if ($status === 'upcoming' && $scheduled_date < $today) {
                $stats['overdue']++;
            }
            
            // Find next upcoming vaccine
            if ($status === 'upcoming' && $scheduled_date >= $today) {
                if (!$next_vaccine || $scheduled_date < new \DateTime($next_vaccine['scheduled_date'])) {
                    $next_vaccine = $record;
                }
            }
            
            // Track recent vaccines (done in last 30 days)
            if ($status === 'done' && !empty($record['actual_date'])) {
                $actual_date = new \DateTime($record['actual_date']);
                $days_ago = $today->diff($actual_date)->days;
                if ($days_ago <= 30) {
                    $recent_vaccines[] = $record;
                }
            }
            
            // Build timeline
            $stats['timeline'][] = [
                'vaccine_code' => $record['vaccine_code'],
                'scheduled_date' => $record['scheduled_date'],
                'actual_date' => $record['actual_date'],
                'status' => $status,
                'has_side_effects' => !empty($record['side_effects']) && $record['side_effects'] !== 'null'
            ];
        }
        
        // Calculate completion rate
        $stats['completion_rate'] = $stats['total'] > 0 
            ? round(($stats['done'] / $stats['total']) * 100, 1) 
            : 0;
        
        // Add next vaccine info
        if ($next_vaccine) {
            $scheduled = new \DateTime($next_vaccine['scheduled_date']);
            $days_until = $today->diff($scheduled)->days;
            
            // Get vaccine name
            $vaccine = $wpdb->get_row($wpdb->prepare(
                "SELECT name, name_short FROM {$wpdb->prefix}kg_vaccine_master WHERE code = %s",
                $next_vaccine['vaccine_code']
            ));
            
            $stats['next_vaccine'] = [
                'vaccine_code' => $next_vaccine['vaccine_code'],
                'vaccine_name' => $vaccine ? $vaccine->name : $next_vaccine['vaccine_code'],
                'scheduled_date' => $next_vaccine['scheduled_date'],
                'days_until' => $days_until
            ];
        }
        
        // Add recent vaccines info
        $stats['recent_vaccines'] = array_map(function($record) use ($wpdb) {
            $vaccine = $wpdb->get_row($wpdb->prepare(
                "SELECT name, name_short FROM {$wpdb->prefix}kg_vaccine_master WHERE code = %s",
                $record['vaccine_code']
            ));
            
            return [
                'vaccine_code' => $record['vaccine_code'],
                'vaccine_name' => $vaccine ? $vaccine->name : $record['vaccine_code'],
                'actual_date' => $record['actual_date'],
                'has_side_effects' => !empty($record['side_effects']) && $record['side_effects'] !== 'null'
            ];
        }, $recent_vaccines);
        
        // Add age-based progress
        $stats['age_progress'] = $this->calculate_age_progress($child_id);
        
        return $stats;
    }
    
    /**
     * Calculate age-based vaccination progress
     * 
     * @param string $child_id Child ID
     * @return array Age progress data
     */
    private function calculate_age_progress($child_id) {
        global $wpdb;
        
        // Get child's birth date
        $child = $wpdb->get_row($wpdb->prepare(
            "SELECT post_content FROM {$wpdb->posts} WHERE ID = %s",
            $child_id
        ));
        
        if (!$child) {
            return [];
        }
        
        $child_data = json_decode($child->post_content, true);
        if (!isset($child_data['birth_date'])) {
            return [];
        }
        
        $birth_date = new \DateTime($child_data['birth_date']);
        $today = new \DateTime();
        $age_months = $birth_date->diff($today)->m + ($birth_date->diff($today)->y * 12);
        
        // Define age milestones
        $milestones = [
            ['age' => 2, 'label' => '0-2 ay'],
            ['age' => 4, 'label' => '2-4 ay'],
            ['age' => 6, 'label' => '4-6 ay'],
            ['age' => 12, 'label' => '6-12 ay'],
            ['age' => 18, 'label' => '12-18 ay'],
            ['age' => 24, 'label' => '18-24 ay'],
            ['age' => 48, 'label' => '2-4 yaş']
        ];
        
        $progress = [];
        
        foreach ($milestones as $milestone) {
            if ($age_months >= $milestone['age']) {
                // Calculate vaccines for this age range
                $vaccines = $wpdb->get_results($wpdb->prepare(
                    "SELECT vr.status 
                    FROM {$wpdb->prefix}kg_vaccine_records vr
                    JOIN {$wpdb->prefix}kg_vaccine_master vm ON vr.vaccine_code = vm.code
                    WHERE vr.child_id = %s 
                    AND vm.min_age_days <= %d",
                    $child_id,
                    $milestone['age'] * 30
                ), ARRAY_A);
                
                $total = count($vaccines);
                $done = 0;
                foreach ($vaccines as $v) {
                    if ($v['status'] === 'done') {
                        $done++;
                    }
                }
                
                $progress[] = [
                    'age_label' => $milestone['label'],
                    'total' => $total,
                    'done' => $done,
                    'completion_rate' => $total > 0 ? round(($done / $total) * 100) : 0
                ];
            }
        }
        
        return $progress;
    }
    
    /**
     * Get global vaccine statistics (admin only)
     * 
     * @return array Global statistics
     */
    public function get_global_stats() {
        global $wpdb;
        
        $stats = [
            'total_users' => 0,
            'total_children' => 0,
            'total_vaccines' => 0,
            'completion_rate' => 0,
            'most_common_vaccines' => [],
            'side_effect_summary' => []
        ];
        
        // Total vaccine records
        $stats['total_vaccines'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}kg_vaccine_records"
        );
        
        // Done vaccines
        $done_vaccines = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}kg_vaccine_records WHERE status = 'done'"
        );
        
        $stats['completion_rate'] = $stats['total_vaccines'] > 0 
            ? round(($done_vaccines / $stats['total_vaccines']) * 100, 1) 
            : 0;
        
        // Unique children
        $stats['total_children'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT child_id) FROM {$wpdb->prefix}kg_vaccine_records"
        );
        
        // Unique users
        $stats['total_users'] = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}kg_vaccine_records"
        );
        
        // Most common vaccines
        $common_vaccines = $wpdb->get_results(
            "SELECT vaccine_code, COUNT(*) as count 
            FROM {$wpdb->prefix}kg_vaccine_records 
            WHERE status = 'done'
            GROUP BY vaccine_code 
            ORDER BY count DESC 
            LIMIT 10",
            ARRAY_A
        );
        
        foreach ($common_vaccines as $vaccine) {
            $vaccine_info = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}kg_vaccine_master WHERE code = %s",
                $vaccine['vaccine_code']
            ));
            
            $stats['most_common_vaccines'][] = [
                'vaccine_code' => $vaccine['vaccine_code'],
                'vaccine_name' => $vaccine_info ? $vaccine_info->name : $vaccine['vaccine_code'],
                'count' => (int) $vaccine['count']
            ];
        }
        
        return $stats;
    }
}
