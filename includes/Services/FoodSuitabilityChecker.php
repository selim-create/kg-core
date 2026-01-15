<?php
namespace KG_Core\Services;

/**
 * Food Suitability Checker Service
 * Checks if a food is suitable for a child's age with hardcoded safety rules
 */
class FoodSuitabilityChecker {

    /**
     * Hardcoded food safety rules (beyond ingredient database)
     */
    private static $hardcoded_rules = [
        'bal' => [
            'min_age_months' => 12,
            'reason' => 'Botulizm riski nedeniyle 1 yaşından önce kesinlikle verilmemelidir.',
            'keywords' => [ 'bal', 'honey', 'مед' ],
        ],
        'tam_findik_ceviz' => [
            'min_age_months' => 48, // 4 years
            'reason' => 'Boğulma riski nedeniyle 4 yaşından önce tam fındık, ceviz gibi sert kuruyemişler verilmemelidir.',
            'keywords' => [ 'fındık', 'ceviz', 'badem', 'fıstık', 'antep fıstığı', 'kaju' ],
            'exceptions' => [ 'fındık ezmesi', 'fıstık ezmesi', 'badem ezmesi', 'fındık tozu', 'çekilmiş' ],
        ],
        'cig_sut_peynir' => [
            'min_age_months' => 12,
            'reason' => 'Pastörize edilmemiş süt ve süt ürünleri enfeksiyon riski taşır.',
            'keywords' => [ 'çiğ süt', 'pastörize edilmemiş', 'unpasteurized' ],
        ],
        'tuz' => [
            'min_age_months' => 12,
            'reason' => '1 yaşından önce yiyeceklere ek tuz eklenmemelidir. Bebeklerin böbrek fonksiyonları gelişmediği için tuz zararlıdır.',
            'keywords' => [ 'tuzlu', 'tuz ekle' ],
        ],
        'seker' => [
            'min_age_months' => 12,
            'reason' => '1 yaşından önce yiyeceklere ek şeker eklenmemelidir. Doğal meyve şekerleri yeterlidir.',
            'keywords' => [ 'şeker', 'şekerli', 'şeker ekle' ],
        ],
        'bogulma_riski' => [
            'min_age_months' => 48, // 4 years
            'reason' => 'Boğulma riski nedeniyle 4 yaşından önce verilmemelidir.',
            'keywords' => [ 'kabak çekirdeği', 'ayçekirdeği', 'popcorn', 'patlamış mısır', 'kuru üzüm', 'cherry domates' ],
        ],
    ];

    /**
     * Check if a food is suitable for a child's age
     * 
     * @param string $query Food name to search
     * @param int $child_age_months Child's age in months
     * @return array Result with verdict
     */
    public static function check( $query, $child_age_months ) {
        // Validate inputs
        if ( empty( $query ) ) {
            return new \WP_Error( 'invalid_query', 'Aranacak gıda adı gereklidir', [ 'status' => 400 ] );
        }

        if ( $child_age_months < 0 ) {
            return new \WP_Error( 'invalid_age', 'Geçerli bir yaş değeri giriniz', [ 'status' => 400 ] );
        }

        $query = sanitize_text_field( $query );
        $query_lower = mb_strtolower( $query, 'UTF-8' );

        // First check hardcoded rules
        $hardcoded_check = self::check_hardcoded_rules( $query_lower, $child_age_months );

        // Search for ingredient in database
        $ingredient = self::search_ingredient( $query );

        // Build response
        $response = [
            'query' => $query,
            'found' => $ingredient ? true : false,
            'ingredient' => $ingredient,
            'verdict' => null,
            'alternatives' => [],
        ];

        // If hardcoded rule applies
        if ( $hardcoded_check ) {
            $response['verdict'] = $hardcoded_check;
            
            // Get alternatives if not suitable
            if ( $hardcoded_check['status'] === 'not_suitable' ) {
                $response['alternatives'] = self::get_alternatives( $query_lower, $child_age_months );
            }
            
            return $response;
        }

        // If ingredient found in database
        if ( $ingredient ) {
            $response['verdict'] = self::get_verdict_from_ingredient( $ingredient, $child_age_months );
            
            // Get alternatives if not suitable
            if ( $response['verdict']['status'] === 'not_suitable' || $response['verdict']['status'] === 'caution' ) {
                $response['alternatives'] = self::get_alternatives( $query_lower, $child_age_months );
            }
            
            return $response;
        }

        // If nothing found
        $response['verdict'] = [
            'status' => 'unknown',
            'status_color' => 'gray',
            'message' => 'Bu gıda hakkında veri bulunamadı',
            'reason' => 'Veritabanımızda bu gıda için bilgi bulunmamaktadır. Çocuk doktorunuza danışmanızı öneririz.',
            'recommended_age' => null,
        ];

        return $response;
    }

    /**
     * Check hardcoded safety rules
     * 
     * @param string $query_lower Lowercase query
     * @param int $child_age_months Child's age in months
     * @return array|null Verdict if rule applies
     */
    private static function check_hardcoded_rules( $query_lower, $child_age_months ) {
        foreach ( self::$hardcoded_rules as $rule_id => $rule ) {
            // Check if query matches keywords
            $matches = false;
            $matched_keyword = '';
            foreach ( $rule['keywords'] as $keyword ) {
                if ( strpos( $query_lower, mb_strtolower( $keyword, 'UTF-8' ) ) !== false ) {
                    // Check for exceptions
                    if ( isset( $rule['exceptions'] ) ) {
                        $is_exception = false;
                        foreach ( $rule['exceptions'] as $exception ) {
                            if ( strpos( $query_lower, mb_strtolower( $exception, 'UTF-8' ) ) !== false ) {
                                $is_exception = true;
                                break;
                            }
                        }
                        if ( $is_exception ) {
                            continue;
                        }
                    }
                    
                    $matches = true;
                    $matched_keyword = $keyword;
                    break;
                }
            }

            if ( ! $matches ) {
                continue;
            }

            // Rule matches, check age
            if ( $child_age_months < $rule['min_age_months'] ) {
                return [
                    'status' => 'not_suitable',
                    'status_color' => 'red',
                    'message' => sprintf( '%s %d aydan önce verilmemelidir', ucfirst( $matched_keyword ), $rule['min_age_months'] ),
                    'reason' => $rule['reason'],
                    'recommended_age' => $rule['min_age_months'] . '+ ay',
                ];
            } else {
                return [
                    'status' => 'suitable',
                    'status_color' => 'green',
                    'message' => 'Yaş açısından uygun',
                    'reason' => 'Çocuğunuzun yaşı bu gıda için uygundur. Alerji riski ve porsiyonlara dikkat edin.',
                    'recommended_age' => $rule['min_age_months'] . '+ ay',
                ];
            }
        }

        return null;
    }

    /**
     * Search for ingredient in database
     * 
     * @param string $query Search query
     * @return array|null Ingredient data
     */
    private static function search_ingredient( $query ) {
        $args = [
            'post_type' => 'ingredient',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => 1,
        ];

        $posts = get_posts( $args );

        if ( empty( $posts ) ) {
            return null;
        }

        $post = $posts[0];

        return [
            'id' => $post->ID,
            'name' => get_the_title( $post->ID ),
            'slug' => $post->post_name,
            'image' => get_the_post_thumbnail_url( $post->ID, 'medium' ),
            'start_age' => get_post_meta( $post->ID, '_kg_start_age', true ),
            'allergy_risk' => get_post_meta( $post->ID, '_kg_allergy_risk', true ),
        ];
    }

    /**
     * Get verdict based on ingredient data
     * 
     * @param array $ingredient Ingredient data
     * @param int $child_age_months Child's age in months
     * @return array Verdict
     */
    private static function get_verdict_from_ingredient( $ingredient, $child_age_months ) {
        $start_age = (int) $ingredient['start_age'];
        $allergy_risk = $ingredient['allergy_risk'];

        if ( $child_age_months < $start_age ) {
            return [
                'status' => 'not_suitable',
                'status_color' => 'red',
                'message' => sprintf( '%s %d aydan önce verilmemelidir', $ingredient['name'], $start_age ),
                'reason' => sprintf( 'Bu gıda en az %d aylık bebekler için uygundur.', $start_age ),
                'recommended_age' => $start_age . '+ ay',
            ];
        }

        // Check allergy risk
        if ( in_array( $allergy_risk, [ 'Yüksek', 'Orta' ] ) ) {
            return [
                'status' => 'caution',
                'status_color' => 'yellow',
                'message' => 'Dikkatli tanıtılmalı - Alerji riski var',
                'reason' => sprintf( '%s alerji riski taşıyan bir besindir. İlk denemede çok az miktarda verin ve 3 gün boyunca başka yeni gıda vermeyin. Alerji belirtilerine dikkat edin.', $ingredient['name'] ),
                'recommended_age' => $start_age . '+ ay',
            ];
        }

        return [
            'status' => 'suitable',
            'status_color' => 'green',
            'message' => 'Çocuğunuza verilebilir',
            'reason' => sprintf( '%s çocuğunuzun yaşı için uygundur. İlk denemede az miktarda verin ve reaksiyonları gözlemleyin.', $ingredient['name'] ),
            'recommended_age' => $start_age . '+ ay',
        ];
    }

    /**
     * Get alternative ingredients suitable for the child's age
     * 
     * @param string $query_lower Lowercase query
     * @param int $child_age_months Child's age in months
     * @return array Alternative ingredients
     */
    private static function get_alternatives( $query_lower, $child_age_months ) {
        // Get 3-5 alternative ingredients that are suitable for the age
        $args = [
            'post_type' => 'ingredient',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'rand',
            'meta_query' => [
                [
                    'key' => '_kg_start_age',
                    'value' => $child_age_months,
                    'compare' => '<=',
                    'type' => 'NUMERIC',
                ],
            ],
        ];

        $posts = get_posts( $args );
        $alternatives = [];

        foreach ( $posts as $post ) {
            $alternatives[] = [
                'id' => $post->ID,
                'name' => get_the_title( $post->ID ),
                'slug' => $post->post_name,
                'image' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ),
                'start_age' => get_post_meta( $post->ID, '_kg_start_age', true ),
            ];
        }

        return $alternatives;
    }
}
