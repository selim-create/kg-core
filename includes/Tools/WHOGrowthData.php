<?php
namespace KG_Core\Tools;

class WHOGrowthData {
    
    private $data_dir;
    private $cache = [];

    public function __construct() {
        $this->data_dir = KG_CORE_PATH . 'data/who-growth-tables/';
    }

    /**
     * Calculate weight-for-age percentile
     */
    public function calculate_weight_for_age( $gender, $age_days, $weight ) {
        $file = $gender === 'male' ? 'wfa_boys_0_5.json' : 'wfa_girls_0_5.json';
        $data = $this->load_data( $file );
        
        if ( ! $data ) {
            return null;
        }
        
        $lms = $this->interpolate_lms( $data, $age_days );
        
        if ( ! $lms ) {
            return null;
        }
        
        $z_score = $this->calculate_z_score( $weight, $lms['L'], $lms['M'], $lms['S'] );
        $percentile = $this->z_to_percentile( $z_score );
        
        return [
            'z_score' => $z_score,
            'percentile' => $percentile,
        ];
    }

    /**
     * Calculate height/length-for-age percentile
     */
    public function calculate_height_for_age( $gender, $age_days, $height ) {
        $file = $gender === 'male' ? 'lhfa_boys_0_5.json' : 'lhfa_girls_0_5.json';
        $data = $this->load_data( $file );
        
        if ( ! $data ) {
            return null;
        }
        
        $lms = $this->interpolate_lms( $data, $age_days );
        
        if ( ! $lms ) {
            return null;
        }
        
        $z_score = $this->calculate_z_score( $height, $lms['L'], $lms['M'], $lms['S'] );
        $percentile = $this->z_to_percentile( $z_score );
        
        return [
            'z_score' => $z_score,
            'percentile' => $percentile,
        ];
    }

    /**
     * Calculate head circumference-for-age percentile
     */
    public function calculate_head_for_age( $gender, $age_days, $head ) {
        $file = $gender === 'male' ? 'hcfa_boys_0_5.json' : 'hcfa_girls_0_5.json';
        $data = $this->load_data( $file );
        
        if ( ! $data ) {
            return null;
        }
        
        $lms = $this->interpolate_lms( $data, $age_days );
        
        if ( ! $lms ) {
            return null;
        }
        
        $z_score = $this->calculate_z_score( $head, $lms['L'], $lms['M'], $lms['S'] );
        $percentile = $this->z_to_percentile( $z_score );
        
        return [
            'z_score' => $z_score,
            'percentile' => $percentile,
        ];
    }

    /**
     * Calculate weight-for-height/length percentile
     */
    public function calculate_weight_for_height( $gender, $height, $weight ) {
        $file = $gender === 'male' ? 'wfl_boys.json' : 'wfl_girls.json';
        $data = $this->load_data( $file );
        
        if ( ! $data ) {
            return null;
        }
        
        $lms = $this->interpolate_lms_by_length( $data, $height );
        
        if ( ! $lms ) {
            return null;
        }
        
        $z_score = $this->calculate_z_score( $weight, $lms['L'], $lms['M'], $lms['S'] );
        $percentile = $this->z_to_percentile( $z_score );
        
        return [
            'z_score' => $z_score,
            'percentile' => $percentile,
        ];
    }

    /**
     * Load WHO data from JSON file with caching
     */
    private function load_data( $filename ) {
        // Check cache first
        if ( isset( $this->cache[ $filename ] ) ) {
            return $this->cache[ $filename ];
        }

        $filepath = $this->data_dir . $filename;
        
        if ( ! file_exists( $filepath ) ) {
            error_log( "KG Core: WHO data file not found: $filepath" );
            return null;
        }

        $json = file_get_contents( $filepath );
        $data = json_decode( $json, true );

        if ( ! $data ) {
            error_log( "KG Core: Failed to parse WHO data file: $filepath" );
            return null;
        }

        // Cache the data
        $this->cache[ $filename ] = $data;

        return $data;
    }

    /**
     * Interpolate LMS values for a given age in days
     */
    private function interpolate_lms( $data, $age_days ) {
        $prev = null;
        $next = null;
        
        foreach ( $data as $row ) {
            if ( $row['age'] <= $age_days ) {
                $prev = $row;
            }
            if ( $row['age'] >= $age_days && $next === null ) {
                $next = $row;
                break;
            }
        }
        
        // If age is before first data point or after last data point
        if ( ! $prev && $next ) {
            return [ 'L' => $next['L'], 'M' => $next['M'], 'S' => $next['S'] ];
        }
        
        if ( $prev && ! $next ) {
            return [ 'L' => $prev['L'], 'M' => $prev['M'], 'S' => $prev['S'] ];
        }
        
        if ( ! $prev && ! $next ) {
            return null;
        }
        
        // Exact match
        if ( $prev['age'] === $next['age'] ) {
            return [ 'L' => $prev['L'], 'M' => $prev['M'], 'S' => $prev['S'] ];
        }
        
        // Linear interpolation
        $ratio = ( $age_days - $prev['age'] ) / ( $next['age'] - $prev['age'] );
        
        return [
            'L' => $prev['L'] + ( $next['L'] - $prev['L'] ) * $ratio,
            'M' => $prev['M'] + ( $next['M'] - $prev['M'] ) * $ratio,
            'S' => $prev['S'] + ( $next['S'] - $prev['S'] ) * $ratio,
        ];
    }

    /**
     * Interpolate LMS values for a given length/height
     */
    private function interpolate_lms_by_length( $data, $length ) {
        $prev = null;
        $next = null;
        
        foreach ( $data as $row ) {
            if ( $row['length'] <= $length ) {
                $prev = $row;
            }
            if ( $row['length'] >= $length && $next === null ) {
                $next = $row;
                break;
            }
        }
        
        // If length is before first data point or after last data point
        if ( ! $prev && $next ) {
            return [ 'L' => $next['L'], 'M' => $next['M'], 'S' => $next['S'] ];
        }
        
        if ( $prev && ! $next ) {
            return [ 'L' => $prev['L'], 'M' => $prev['M'], 'S' => $prev['S'] ];
        }
        
        if ( ! $prev && ! $next ) {
            return null;
        }
        
        // Exact match
        if ( $prev['length'] === $next['length'] ) {
            return [ 'L' => $prev['L'], 'M' => $prev['M'], 'S' => $prev['S'] ];
        }
        
        // Linear interpolation
        $ratio = ( $length - $prev['length'] ) / ( $next['length'] - $prev['length'] );
        
        return [
            'L' => $prev['L'] + ( $next['L'] - $prev['L'] ) * $ratio,
            'M' => $prev['M'] + ( $next['M'] - $prev['M'] ) * $ratio,
            'S' => $prev['S'] + ( $next['S'] - $prev['S'] ) * $ratio,
        ];
    }

    /**
     * Calculate z-score using WHO LMS method
     */
    private function calculate_z_score( $value, $L, $M, $S ) {
        // Use epsilon for float comparison to avoid precision issues
        if ( abs( $L ) > PHP_FLOAT_EPSILON ) {
            return ( pow( $value / $M, $L ) - 1 ) / ( $L * $S );
        } else {
            return log( $value / $M ) / $S;
        }
    }

    /**
     * Convert z-score to percentile using standard normal distribution
     */
    private function z_to_percentile( $z ) {
        // Standard normal CDF approximation
        return 100 * 0.5 * ( 1 + $this->erf( $z / sqrt( 2 ) ) );
    }

    /**
     * Error function approximation
     */
    private function erf( $x ) {
        // Abramowitz and Stegun approximation
        $a1 =  0.254829592;
        $a2 = -0.284496736;
        $a3 =  1.421413741;
        $a4 = -1.453152027;
        $a5 =  1.061405429;
        $p  =  0.3275911;

        $sign = $x < 0 ? -1 : 1;
        $x = abs( $x );

        $t = 1.0 / ( 1.0 + $p * $x );
        $y = 1.0 - ( ( ( ( ( $a5 * $t + $a4 ) * $t ) + $a3 ) * $t + $a2 ) * $t + $a1 ) * $t * exp( -$x * $x );

        return $sign * $y;
    }
}
