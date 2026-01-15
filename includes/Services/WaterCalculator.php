<?php
namespace KG_Core\Services;

/**
 * Water Calculator Service
 * Calculates daily fluid needs for babies using Holliday-Segar formula
 */
class WaterCalculator {

    /**
     * Calculate daily fluid needs
     * 
     * @param float $weight_kg Baby weight in kg
     * @param int $age_months Baby age in months
     * @param string $weather Weather condition (hot, normal, cold)
     * @param bool $is_breastfed Is baby exclusively breastfed
     * @return array Calculation results
     */
    public static function calculate( $weight_kg, $age_months, $weather = 'normal', $is_breastfed = false ) {
        // Validate inputs
        if ( $weight_kg <= 0 ) {
            return new \WP_Error( 'invalid_weight', 'Geçerli bir kilo değeri giriniz', [ 'status' => 400 ] );
        }

        if ( $age_months < 0 ) {
            return new \WP_Error( 'invalid_age', 'Geçerli bir yaş değeri giriniz', [ 'status' => 400 ] );
        }

        // Calculate base fluid need using Holliday-Segar formula
        $base_need = self::holliday_segar_formula( $weight_kg );

        // Weather adjustment
        $weather_adjustment = 1.0;
        if ( $weather === 'hot' ) {
            $weather_adjustment = 1.15; // 15% increase in hot weather
        } elseif ( $weather === 'cold' ) {
            $weather_adjustment = 0.95; // 5% decrease in cold weather
        }

        $adjusted_need = $base_need * $weather_adjustment;

        // Calculate breakdown
        $breakdown = self::calculate_breakdown( $adjusted_need, $age_months, $is_breastfed );

        // Generate notes and warnings
        $notes = self::generate_notes( $age_months, $weather, $is_breastfed );
        $warning = self::get_warning( $age_months );

        return [
            'daily_fluid_need_ml' => round( $adjusted_need ),
            'breakdown' => $breakdown,
            'notes' => $notes,
            'formula' => self::get_formula_description( $weight_kg ),
            'warning' => $warning,
        ];
    }

    /**
     * Holliday-Segar formula calculation
     * - First 10 kg: 100 ml/kg/day
     * - 10-20 kg: 1000 ml + 50 ml/kg (for kg above 10)
     * - 20+ kg: 1500 ml + 20 ml/kg (for kg above 20)
     * 
     * @param float $weight_kg Weight in kg
     * @return float Daily fluid need in ml
     */
    private static function holliday_segar_formula( $weight_kg ) {
        if ( $weight_kg <= 10 ) {
            return $weight_kg * 100;
        } elseif ( $weight_kg <= 20 ) {
            return 1000 + ( ( $weight_kg - 10 ) * 50 );
        } else {
            return 1500 + ( ( $weight_kg - 20 ) * 20 );
        }
    }

    /**
     * Calculate fluid intake breakdown by source
     * 
     * @param float $total_need Total fluid need in ml
     * @param int $age_months Baby age in months
     * @param bool $is_breastfed Is baby exclusively breastfed
     * @return array Breakdown by source
     */
    private static function calculate_breakdown( $total_need, $age_months, $is_breastfed ) {
        $breakdown = [
            'from_breast_milk_formula' => 0,
            'from_food' => 0,
            'from_water' => 0,
        ];

        if ( $age_months < 6 ) {
            // Under 6 months: All from breast milk/formula
            $breakdown['from_breast_milk_formula'] = round( $total_need );
            $breakdown['from_food'] = 0;
            $breakdown['from_water'] = 0;
        } elseif ( $age_months < 12 ) {
            // 6-12 months: Mostly from breast milk/formula
            if ( $is_breastfed ) {
                $breakdown['from_breast_milk_formula'] = round( $total_need * 0.75 );
                $breakdown['from_food'] = round( $total_need * 0.20 );
                $breakdown['from_water'] = round( $total_need * 0.05 );
            } else {
                $breakdown['from_breast_milk_formula'] = round( $total_need * 0.70 );
                $breakdown['from_food'] = round( $total_need * 0.20 );
                $breakdown['from_water'] = round( $total_need * 0.10 );
            }
        } else {
            // 12+ months: More balanced distribution
            if ( $is_breastfed ) {
                $breakdown['from_breast_milk_formula'] = round( $total_need * 0.50 );
                $breakdown['from_food'] = round( $total_need * 0.30 );
                $breakdown['from_water'] = round( $total_need * 0.20 );
            } else {
                $breakdown['from_breast_milk_formula'] = round( $total_need * 0.40 );
                $breakdown['from_food'] = round( $total_need * 0.35 );
                $breakdown['from_water'] = round( $total_need * 0.25 );
            }
        }

        return $breakdown;
    }

    /**
     * Generate informative notes based on age and conditions
     * 
     * @param int $age_months Baby age in months
     * @param string $weather Weather condition
     * @param bool $is_breastfed Is baby exclusively breastfed
     * @return array Notes
     */
    private static function generate_notes( $age_months, $weather, $is_breastfed ) {
        $notes = [];

        if ( $age_months < 6 ) {
            $notes[] = '6 aydan küçük bebekler için tüm sıvı ihtiyacı anne sütü veya mama ile karşılanmalıdır';
            if ( $is_breastfed ) {
                $notes[] = 'Anne sütü alan bebekler genellikle ek su ihtiyacı duymaz';
            }
        } elseif ( $age_months < 12 ) {
            $notes[] = '6-12 ay arası bebekler için su ihtiyacının çoğu anne sütü/mamadan karşılanır';
            $notes[] = 'Ek gıda ile birlikte az miktarda su verilebilir';
            if ( $weather === 'hot' ) {
                $notes[] = 'Sıcak havalarda %15 artış uygulanmıştır';
            }
        } else {
            $notes[] = '12 ay ve üzeri çocuklar için dengeli sıvı alımı önemlidir';
            $notes[] = 'Su, süt ve gıdalardan dengeli şekilde sıvı alınmalıdır';
            if ( $weather === 'hot' ) {
                $notes[] = 'Sıcak havalarda ve fiziksel aktivite sırasında daha fazla sıvı gerekebilir';
            }
        }

        return $notes;
    }

    /**
     * Get formula description based on weight
     * 
     * @param float $weight_kg Weight in kg
     * @return string Formula description
     */
    private static function get_formula_description( $weight_kg ) {
        if ( $weight_kg <= 10 ) {
            return 'Holliday-Segar: İlk 10 kg için 100 ml/kg/gün';
        } elseif ( $weight_kg <= 20 ) {
            return 'Holliday-Segar: 1000 ml + 50 ml/kg (10 kg üzeri için)';
        } else {
            return 'Holliday-Segar: 1500 ml + 20 ml/kg (20 kg üzeri için)';
        }
    }

    /**
     * Get age-specific warnings
     * 
     * @param int $age_months Baby age in months
     * @return string|null Warning message
     */
    private static function get_warning( $age_months ) {
        if ( $age_months < 6 ) {
            return '6 aydan küçük bebeklere su verilmemelidir. Tüm sıvı ihtiyacı anne sütü veya mama ile karşılanmalıdır.';
        }

        return null;
    }
}
