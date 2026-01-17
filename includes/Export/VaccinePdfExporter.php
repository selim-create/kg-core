<?php
namespace KG_Core\Export;

/**
 * VaccinePdfExporter - Export vaccine schedule and history as PDF
 */
class VaccinePdfExporter {
    
    /**
     * Export vaccine schedule as PDF
     * 
     * @param string $child_id Child ID
     * @param string $type Export type ('schedule' or 'history')
     * @return string|WP_Error PDF content or error
     */
    public function export($child_id, $type = 'schedule') {
        global $wpdb;
        
        // Check if TCPDF is available
        if (!class_exists('\TCPDF')) {
            return new \WP_Error(
                'library_missing',
                'TCPDF library not found. Please run: composer require tecnickcom/tcpdf'
            );
        }
        
        // Get child information
        $child = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE ID = %s",
            $child_id
        ));
        
        if (!$child) {
            return new \WP_Error('child_not_found', 'Child not found');
        }
        
        $child_data = json_decode($child->post_content, true);
        $child_name = $child->post_title;
        $birth_date = isset($child_data['birth_date']) ? $child_data['birth_date'] : 'Bilinmiyor';
        
        // Get vaccine records
        $where = "child_id = %s";
        $params = [$child_id];
        
        if ($type === 'history') {
            $where .= " AND status = 'done'";
        }
        
        $records = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}kg_vaccine_records WHERE {$where} ORDER BY scheduled_date",
            $params
        ), ARRAY_A);
        
        // Create PDF
        try {
            return $this->generate_pdf($child_name, $birth_date, $records, $type);
        } catch (\Exception $e) {
            return new \WP_Error('pdf_generation_failed', $e->getMessage());
        }
    }
    
    /**
     * Get filename for PDF export
     * 
     * @param string $child_name Child's name
     * @param string $type Export type
     * @return string Filename
     */
    public function get_filename($child_name, $type = 'schedule') {
        $type_label = $type === 'history' ? 'gecmis' : 'takvim';
        $safe_name = $this->sanitize_filename($child_name);
        $date = date('Y-m-d');
        
        return "asi-{$type_label}-{$safe_name}-{$date}.pdf";
    }
    
    /**
     * Generate PDF content (simplified version that will work without full TCPDF)
     */
    private function generate_pdf($child_name, $birth_date, $records, $type) {
        // For now, return a simple response indicating PDF would be generated
        // In production, this would use TCPDF to generate actual PDF
        return "PDF generation placeholder for {$child_name}";
    }
    
    /**
     * Sanitize filename
     */
    private function sanitize_filename($filename) {
        $turkish = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
        $replace = ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'];
        $filename = str_replace($turkish, $replace, $filename);
        $filename = preg_replace('/[^a-z0-9-_]/i', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        $filename = trim($filename, '-');
        $filename = strtolower($filename);
        return $filename;
    }
}
