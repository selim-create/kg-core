<?php
namespace KG_Core\Services;

/**
 * Solid Food Readiness Checker Service
 * Assesses if a baby is ready for solid foods based on WHO/AAP standards
 */
class SolidFoodReadinessChecker {

    /**
     * Get solid food readiness test configuration
     * 
     * @return array Test configuration
     */
    public static function get_config() {
        return [
            'questions' => [
                [
                    'id' => 'q1_sitting',
                    'question' => 'Bebek destekli oturabilyor mu?',
                    'description' => 'Koltukta otururken başını dik tutabiliyor mu?',
                    'weight' => 25,
                    'options' => [
                        [
                            'id' => 'sitting_yes',
                            'text' => 'Evet, destekli olarak oturabiliyor',
                            'value' => 100,
                        ],
                        [
                            'id' => 'sitting_support',
                            'text' => 'Biraz destekle oturuyor',
                            'value' => 60,
                        ],
                        [
                            'id' => 'sitting_no',
                            'text' => 'Hayır, henüz oturamıyor',
                            'value' => 0,
                        ],
                    ],
                ],
                [
                    'id' => 'q2_tongue_reflex',
                    'question' => 'Dil itme refleksi azaldı mı?',
                    'description' => 'Ağzına bir şey konduğunda diliyle dışarı itmiyor mu?',
                    'weight' => 20,
                    'options' => [
                        [
                            'id' => 'reflex_gone',
                            'text' => 'Evet, kayboldu',
                            'value' => 100,
                        ],
                        [
                            'id' => 'reflex_reducing',
                            'text' => 'Azalmış görünüyor',
                            'value' => 70,
                        ],
                        [
                            'id' => 'reflex_present',
                            'text' => 'Hayır, hala var',
                            'value' => 20,
                        ],
                    ],
                ],
                [
                    'id' => 'q3_interest',
                    'question' => 'Yiyeceklere ilgi gösteriyor mu?',
                    'description' => 'Sizin yediğinizi izliyor, elini uzatıyor mu?',
                    'weight' => 15,
                    'options' => [
                        [
                            'id' => 'interest_high',
                            'text' => 'Evet, çok ilgili',
                            'value' => 100,
                        ],
                        [
                            'id' => 'interest_some',
                            'text' => 'Bazen ilgi gösteriyor',
                            'value' => 60,
                        ],
                        [
                            'id' => 'interest_no',
                            'text' => 'Hayır, ilgisiz',
                            'value' => 20,
                        ],
                    ],
                ],
                [
                    'id' => 'q4_hand_mouth',
                    'question' => 'El-ağız koordinasyonu var mı?',
                    'description' => 'Nesneleri ağzına götürebiliyor mu?',
                    'weight' => 15,
                    'options' => [
                        [
                            'id' => 'coordination_yes',
                            'text' => 'Evet, yapabiliyor',
                            'value' => 100,
                        ],
                        [
                            'id' => 'coordination_trying',
                            'text' => 'Denemeye başladı',
                            'value' => 70,
                        ],
                        [
                            'id' => 'coordination_no',
                            'text' => 'Hayır, yapamıyor',
                            'value' => 30,
                        ],
                    ],
                ],
                [
                    'id' => 'q5_age',
                    'question' => 'Bebek en az 4 ay (tercihen 6 ay) mı?',
                    'description' => 'WHO, ek gıdaya 6. aydan sonra başlanmasını öneriyor',
                    'weight' => 20,
                    'options' => [
                        [
                            'id' => 'age_6plus',
                            'text' => '6 ay ve üzeri',
                            'value' => 100,
                        ],
                        [
                            'id' => 'age_5to6',
                            'text' => '5-6 ay arası',
                            'value' => 60,
                        ],
                        [
                            'id' => 'age_4to5',
                            'text' => '4-5 ay arası',
                            'value' => 30,
                        ],
                        [
                            'id' => 'age_below4',
                            'text' => '4 aydan küçük',
                            'value' => 0,
                        ],
                    ],
                ],
                [
                    'id' => 'q6_weight',
                    'question' => 'Doğum kilosunu ikiye katladı mı?',
                    'description' => 'Genel bir gelişim göstergesi',
                    'weight' => 5,
                    'options' => [
                        [
                            'id' => 'weight_doubled',
                            'text' => 'Evet, ikiye katladı',
                            'value' => 100,
                        ],
                        [
                            'id' => 'weight_almost',
                            'text' => 'Neredeyse',
                            'value' => 70,
                        ],
                        [
                            'id' => 'weight_not_yet',
                            'text' => 'Henüz değil',
                            'value' => 40,
                        ],
                        [
                            'id' => 'weight_unknown',
                            'text' => 'Bilmiyorum',
                            'value' => 50,
                        ],
                    ],
                ],
            ],
            'result_buckets' => [
                [
                    'id' => 'ready',
                    'min_score' => 80,
                    'max_score' => 100,
                    'title' => 'Ek Gıdaya Hazır Görünüyor',
                    'description' => 'Bebeğiniz tüm önemli gelişim göstergelerini karşılıyor. Ek gıdaya başlayabilirsiniz.',
                    'color' => 'green',
                    'icon' => '✓',
                    'recommendations' => [
                        'Yumuşak, kolay sindirilebilir gıdalarla başlayın',
                        'İlk hafta tek bir gıda deneyin (3 gün kuralı)',
                        'Öğünleri sabah veya öğle saatlerinde verin',
                        'Bebeğinizin kendi hızında yemesine izin verin',
                        'Yeni bir gıdayı 3 gün tekrarlayarak alerji takibi yapın',
                    ],
                ],
                [
                    'id' => 'almost_ready',
                    'min_score' => 60,
                    'max_score' => 79,
                    'title' => 'Neredeyse Hazır',
                    'description' => 'Bebeğiniz bazı göstergeleri karşılıyor ancak biraz daha zamana ihtiyacı var.',
                    'color' => 'yellow',
                    'icon' => '⌛',
                    'recommendations' => [
                        '1-2 hafta daha bekleyin',
                        'Oturma becerilerini destekleyin',
                        'Aile yemeklerinde masaya dahil edin',
                        'Pediatristinize danışın',
                        '2-3 hafta sonra testi tekrarlayın',
                    ],
                ],
                [
                    'id' => 'not_yet',
                    'min_score' => 0,
                    'max_score' => 59,
                    'title' => 'Biraz Daha Zamana İhtiyaç Var',
                    'description' => 'Bebeğiniz henüz ek gıdaya hazır görünmüyor. Acele etmeyin.',
                    'color' => 'red',
                    'icon' => '⏸',
                    'recommendations' => [
                        'Anne sütü/mama ile beslenmeye devam edin',
                        'Baş kontrolü ve oturma becerilerini destekleyin',
                        'Pediatristinize gelişim hakkında danışın',
                        '4-6 hafta sonra testi tekrarlayın',
                        'Her bebek kendi hızında gelişir, endişelenmeyin',
                    ],
                ],
            ],
            'disclaimer' => 'Bu test genel bilgilendirme amaçlıdır ve tıbbi tavsiye yerine geçmez. Ek gıdaya başlamadan önce mutlaka çocuk doktorunuzla görüşün.',
        ];
    }

    /**
     * Submit and evaluate readiness test
     * 
     * @param array $answers User's answers
     * @return array Test results
     */
    public static function submit( $answers ) {
        if ( empty( $answers ) || ! is_array( $answers ) ) {
            return new \WP_Error( 'invalid_answers', 'Cevaplar gereklidir', [ 'status' => 400 ] );
        }

        $config = self::get_config();
        $questions = $config['questions'];

        // Calculate score
        $total_weight = 0;
        $weighted_sum = 0;

        foreach ( $questions as $question ) {
            $question_id = $question['id'];
            
            if ( ! isset( $answers[ $question_id ] ) ) {
                continue;
            }

            $selected_option_id = $answers[ $question_id ];
            $weight = $question['weight'];

            // Find selected option
            $selected_option = null;
            foreach ( $question['options'] as $option ) {
                if ( $option['id'] === $selected_option_id ) {
                    $selected_option = $option;
                    break;
                }
            }

            if ( ! $selected_option ) {
                continue;
            }

            $weighted_sum += $selected_option['value'] * $weight;
            $total_weight += $weight;
        }

        if ( $total_weight === 0 ) {
            return new \WP_Error( 'no_answers', 'Hiçbir soru cevaplanmadı', [ 'status' => 400 ] );
        }

        $score = $weighted_sum / $total_weight;

        // Find result bucket
        $result_bucket = null;
        foreach ( $config['result_buckets'] as $bucket ) {
            if ( $score >= $bucket['min_score'] && $score <= $bucket['max_score'] ) {
                $result_bucket = $bucket;
                break;
            }
        }

        if ( ! $result_bucket ) {
            return new \WP_Error( 'no_bucket', 'Sonuç kategorisi bulunamadı', [ 'status' => 500 ] );
        }

        return [
            'score' => round( $score, 2 ),
            'result' => $result_bucket,
            'timestamp' => current_time( 'c' ),
        ];
    }
}
