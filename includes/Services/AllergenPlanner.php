<?php
namespace KG_Core\Services;

/**
 * Allergen Planner Service
 * Generates allergen introduction plans based on WHO/AAP guidelines
 */
class AllergenPlanner {

    /**
     * Allergen introduction templates based on WHO/AAP standards
     */
    private static $allergen_templates = [
        'yumurta' => [
            'name' => 'Yumurta',
            'risk_level' => 'Yüksek',
            'total_days' => 5,
            'plan' => [
                [
                    'day' => 1,
                    'amount' => '1/4 çay kaşığı',
                    'form' => 'Tam pişmiş yumurta sarısı',
                    'time' => 'Sabah (reaksiyon takibi için)',
                    'notes' => 'Diğer yeni gıda vermekten kaçının. Reaksiyonları not edin.',
                ],
                [
                    'day' => 2,
                    'amount' => '1/2 çay kaşığı',
                    'form' => 'Tam pişmiş yumurta sarısı',
                    'time' => 'Sabah',
                    'notes' => 'Önceki gün reaksiyon yoksa miktarı artırın.',
                ],
                [
                    'day' => 3,
                    'amount' => '1 çay kaşığı',
                    'form' => 'Tam pişmiş karışık yumurta',
                    'time' => 'Sabah',
                    'notes' => 'Sarı ve beyazı karıştırarak verin.',
                ],
                [
                    'day' => 4,
                    'amount' => '1/4 yumurta',
                    'form' => 'Tam pişmiş haşlanmış yumurta',
                    'time' => 'Sabah',
                    'notes' => 'Haşlanmış yumurtadan çeyrek parça verin.',
                ],
                [
                    'day' => 5,
                    'amount' => '1/2 yumurta',
                    'form' => 'Tam pişmiş haşlanmış yumurta',
                    'time' => 'Sabah',
                    'notes' => 'Reaksiyon yoksa normal porsiyon verebilirsiniz.',
                ],
            ],
            'cross_allergens' => [ 'tavuk' ],
        ],
        'sut' => [
            'name' => 'İnek Sütü',
            'risk_level' => 'Yüksek',
            'total_days' => 5,
            'plan' => [
                [
                    'day' => 1,
                    'amount' => '1 çay kaşığı',
                    'form' => 'Pişmiş süt (yoğurt veya peynir)',
                    'time' => 'Sabah',
                    'notes' => 'Yoğurt veya pastörize peynir ile başlayın.',
                ],
                [
                    'day' => 2,
                    'amount' => '1 yemek kaşığı',
                    'form' => 'Yoğurt',
                    'time' => 'Sabah',
                    'notes' => 'Sade yoğurt tercih edin.',
                ],
                [
                    'day' => 3,
                    'amount' => '2 yemek kaşığı',
                    'form' => 'Yoğurt veya peynir',
                    'time' => 'Sabah',
                    'notes' => 'Miktarı kademeli artırın.',
                ],
                [
                    'day' => 4,
                    'amount' => '50 ml',
                    'form' => 'Tam yağlı süt (pişmiş)',
                    'time' => 'Sabah',
                    'notes' => 'Sütü yemeğe katarak verin.',
                ],
                [
                    'day' => 5,
                    'amount' => '100 ml',
                    'form' => 'Tam yağlı süt',
                    'time' => 'Sabah',
                    'notes' => 'Reaksiyon yoksa normal tüketim yapabilirsiniz.',
                ],
            ],
            'cross_allergens' => [ 'keçi sütü', 'koyun sütü' ],
        ],
        'fistik' => [
            'name' => 'Yer Fıstığı',
            'risk_level' => 'Yüksek',
            'total_days' => 7,
            'plan' => [
                [
                    'day' => 1,
                    'amount' => '1/4 çay kaşığı',
                    'form' => 'İnce yer fıstığı ezmesi (sulandırılmış)',
                    'time' => 'Sabah',
                    'notes' => 'Su veya anne sütü ile sulandırın. TAM fıstık VERMEYİN!',
                ],
                [
                    'day' => 2,
                    'amount' => '1/2 çay kaşığı',
                    'form' => 'Yer fıstığı ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Yoğurt veya püreye karıştırın.',
                ],
                [
                    'day' => 3,
                    'amount' => '1 çay kaşığı',
                    'form' => 'Yer fıstığı ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Miktarı kademeli artırın.',
                ],
                [
                    'day' => 4,
                    'amount' => '1,5 çay kaşığı',
                    'form' => 'Yer fıstığı ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Normal kıvamda verilebilir.',
                ],
                [
                    'day' => 5,
                    'amount' => '2 çay kaşığı',
                    'form' => 'Yer fıstığı ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Reaksiyonları kontrol edin.',
                ],
                [
                    'day' => 6,
                    'amount' => '1 yemek kaşığı',
                    'form' => 'Yer fıstığı ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Tam porsiyon verilebilir.',
                ],
                [
                    'day' => 7,
                    'amount' => '1 yemek kaşığı',
                    'form' => 'Yer fıstığı ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Reaksiyon yoksa güvenle tüketebilir.',
                ],
            ],
            'cross_allergens' => [ 'bakliyat', 'soya' ],
        ],
        'balik' => [
            'name' => 'Balık',
            'risk_level' => 'Orta',
            'total_days' => 5,
            'plan' => [
                [
                    'day' => 1,
                    'amount' => '1 çay kaşığı',
                    'form' => 'İyi pişmiş beyaz balık (levrek, çipura)',
                    'time' => 'Sabah',
                    'notes' => 'Kılçıksız, iyice ezilmiş balık verin.',
                ],
                [
                    'day' => 2,
                    'amount' => '2 çay kaşığı',
                    'form' => 'İyi pişmiş beyaz balık',
                    'time' => 'Sabah',
                    'notes' => 'Kılçık kontrolü yapın.',
                ],
                [
                    'day' => 3,
                    'amount' => '1 yemek kaşığı',
                    'form' => 'İyi pişmiş beyaz balık',
                    'time' => 'Sabah',
                    'notes' => 'Püre veya yoğurt ile karıştırabilirsiniz.',
                ],
                [
                    'day' => 4,
                    'amount' => '2 yemek kaşığı',
                    'form' => 'İyi pişmiş balık',
                    'time' => 'Sabah',
                    'notes' => 'Miktarı artırın.',
                ],
                [
                    'day' => 5,
                    'amount' => 'Normal porsiyon',
                    'form' => 'İyi pişmiş balık',
                    'time' => 'Sabah veya öğle',
                    'notes' => 'Reaksiyon yoksa haftada 2-3 kez tüketebilir.',
                ],
            ],
            'cross_allergens' => [ 'kabuklu deniz ürünleri' ],
        ],
        'buday' => [
            'name' => 'Buğday (Gluten)',
            'risk_level' => 'Orta',
            'total_days' => 5,
            'plan' => [
                [
                    'day' => 1,
                    'amount' => '1 çay kaşığı',
                    'form' => 'İrmik lapası',
                    'time' => 'Sabah',
                    'notes' => 'Sade irmik lapası ile başlayın.',
                ],
                [
                    'day' => 2,
                    'amount' => '2 çay kaşığı',
                    'form' => 'İrmik lapası',
                    'time' => 'Sabah',
                    'notes' => 'Miktarı artırın.',
                ],
                [
                    'day' => 3,
                    'amount' => '1/4 dilim',
                    'form' => 'Beyaz ekmek içi',
                    'time' => 'Sabah',
                    'notes' => 'Ekmek içini sulandırarak verin.',
                ],
                [
                    'day' => 4,
                    'amount' => '1/2 dilim',
                    'form' => 'Ekmek',
                    'time' => 'Sabah',
                    'notes' => 'Normal kıvamda verilebilir.',
                ],
                [
                    'day' => 5,
                    'amount' => 'Normal porsiyon',
                    'form' => 'Buğday ürünleri',
                    'time' => 'Herhangi bir öğün',
                    'notes' => 'Reaksiyon yoksa makarna, ekmek verilebilir.',
                ],
            ],
            'cross_allergens' => [ 'arpa', 'çavdar' ],
        ],
        'soya' => [
            'name' => 'Soya',
            'risk_level' => 'Orta',
            'total_days' => 5,
            'plan' => [
                [
                    'day' => 1,
                    'amount' => '1 çay kaşığı',
                    'form' => 'Tofu (haşlanmış ve ezilmiş)',
                    'time' => 'Sabah',
                    'notes' => 'İyi pişmiş, yumuşak tofu verin.',
                ],
                [
                    'day' => 2,
                    'amount' => '2 çay kaşığı',
                    'form' => 'Tofu',
                    'time' => 'Sabah',
                    'notes' => 'Püre ile karıştırabilirsiniz.',
                ],
                [
                    'day' => 3,
                    'amount' => '1 yemek kaşığı',
                    'form' => 'Tofu',
                    'time' => 'Sabah',
                    'notes' => 'Miktarı artırın.',
                ],
                [
                    'day' => 4,
                    'amount' => '2 yemek kaşığı',
                    'form' => 'Soya ürünleri',
                    'time' => 'Sabah',
                    'notes' => 'Soya sütü veya tofu verilebilir.',
                ],
                [
                    'day' => 5,
                    'amount' => 'Normal porsiyon',
                    'form' => 'Soya ürünleri',
                    'time' => 'Herhangi bir öğün',
                    'notes' => 'Reaksiyon yoksa düzenli tüketilebilir.',
                ],
            ],
            'cross_allergens' => [ 'yer fıstığı', 'bakliyat' ],
        ],
        'findik' => [
            'name' => 'Fındık',
            'risk_level' => 'Yüksek',
            'total_days' => 7,
            'plan' => [
                [
                    'day' => 1,
                    'amount' => '1/4 çay kaşığı',
                    'form' => 'Fındık tozu (ince öğütülmüş)',
                    'time' => 'Sabah',
                    'notes' => 'Su veya anne sütü ile karıştırın. TAM fındık VERMEYİN!',
                ],
                [
                    'day' => 2,
                    'amount' => '1/2 çay kaşığı',
                    'form' => 'Fındık tozu',
                    'time' => 'Sabah',
                    'notes' => 'Yoğurt veya püreye ekleyin.',
                ],
                [
                    'day' => 3,
                    'amount' => '1 çay kaşığı',
                    'form' => 'Fındık ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'İnce fındık ezmesi kullanın.',
                ],
                [
                    'day' => 4,
                    'amount' => '1,5 çay kaşığı',
                    'form' => 'Fındık ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Reaksiyonları gözlemleyin.',
                ],
                [
                    'day' => 5,
                    'amount' => '2 çay kaşığı',
                    'form' => 'Fındık ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Kademeli artış devam edin.',
                ],
                [
                    'day' => 6,
                    'amount' => '1 yemek kaşığı',
                    'form' => 'Fındık ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Normal porsiyon verilebilir.',
                ],
                [
                    'day' => 7,
                    'amount' => '1 yemek kaşığı',
                    'form' => 'Fındık ezmesi',
                    'time' => 'Sabah',
                    'notes' => 'Reaksiyon yoksa güvenle tüketebilir. 4 yaşından önce tam fındık VERMEYİN!',
                ],
            ],
            'cross_allergens' => [ 'ceviz', 'badem', 'kaju' ],
        ],
        'susam' => [
            'name' => 'Susam',
            'risk_level' => 'Orta',
            'total_days' => 5,
            'plan' => [
                [
                    'day' => 1,
                    'amount' => '1/4 çay kaşığı',
                    'form' => 'Tahin (ince)',
                    'time' => 'Sabah',
                    'notes' => 'Su veya anne sütü ile sulandırın.',
                ],
                [
                    'day' => 2,
                    'amount' => '1/2 çay kaşığı',
                    'form' => 'Tahin',
                    'time' => 'Sabah',
                    'notes' => 'Yoğurt veya püreye ekleyin.',
                ],
                [
                    'day' => 3,
                    'amount' => '1 çay kaşığı',
                    'form' => 'Tahin',
                    'time' => 'Sabah',
                    'notes' => 'Normal kıvamda verilebilir.',
                ],
                [
                    'day' => 4,
                    'amount' => '1,5 çay kaşığı',
                    'form' => 'Tahin',
                    'time' => 'Sabah',
                    'notes' => 'Miktarı artırın.',
                ],
                [
                    'day' => 5,
                    'amount' => '2 çay kaşığı',
                    'form' => 'Tahin veya susam tohumu',
                    'time' => 'Sabah',
                    'notes' => 'Reaksiyon yoksa düzenli tüketilebilir.',
                ],
            ],
            'cross_allergens' => [ 'kabuklu kuruyemiş' ],
        ],
    ];

    /**
     * Warning signs for allergic reactions
     */
    private static $warning_signs = [
        'Cilt döküntüsü, kızarıklık, kaşıntı',
        'Şişlik (özellikle yüz, dudaklar, dil)',
        'Kusma veya ishal',
        'Karın ağrısı, gaz',
        'Hırıltılı solunum',
        'Öksürük veya hapşırma',
        'Burun akıntısı',
        'Göz sulanması, kaşıntı',
    ];

    /**
     * Emergency warning signs
     */
    private static $emergency_signs = [
        'Nefes almada ciddi zorluk',
        'Yüz veya dudaklarda şişme',
        'Bayılma, sersemlik',
        'Nabızta hızlanma',
        'Vücutta yaygın kızarıklık',
        '**ACİL DURUM: 112 ARAYIN**',
    ];

    /**
     * Get allergen planner configuration
     * 
     * @return array List of available allergens
     */
    public static function get_config() {
        $allergens = [];

        foreach ( self::$allergen_templates as $slug => $template ) {
            $allergens[] = [
                'id' => $slug,
                'name' => $template['name'],
                'risk_level' => $template['risk_level'],
                'total_days' => $template['total_days'],
            ];
        }

        return [
            'allergens' => $allergens,
            'warning_signs' => self::$warning_signs,
            'emergency_signs' => self::$emergency_signs,
        ];
    }

    /**
     * Generate allergen introduction plan
     * 
     * @param string $allergen_id Allergen ID
     * @param array $params Additional parameters
     * @return array|WP_Error Introduction plan
     */
    public static function generate_plan( $allergen_id, $params = [] ) {
        // Validate allergen ID
        if ( ! isset( self::$allergen_templates[ $allergen_id ] ) ) {
            return new \WP_Error( 'invalid_allergen', 'Geçersiz alerjen ID', [ 'status' => 400 ] );
        }

        $template = self::$allergen_templates[ $allergen_id ];

        // Get related ingredients with cross-allergen risk
        $related_ingredients = self::get_related_ingredients( $template['cross_allergens'] );

        $result = [
            'allergen' => [
                'id' => $allergen_id,
                'name' => $template['name'],
                'risk_level' => $template['risk_level'],
            ],
            'introduction_plan' => [
                'total_days' => $template['total_days'],
                'days' => $template['plan'],
            ],
            'warning_signs' => self::$warning_signs,
            'emergency_signs' => self::$emergency_signs,
            'when_to_stop' => [
                'Herhangi bir alerji belirtisi gözlemlerseniz hemen durdurun',
                'Reaksiyon gözlenmesi durumunda en az 3 ay bekleyin',
                'Ciddi reaksiyon durumunda doktora danışmadan tekrar denemeyin',
                'Şüphe durumunda pediatristinize danışın',
            ],
            'success_criteria' => sprintf( '%d gün boyunca hiçbir reaksiyon gözlenmezse, bu alerjeni güvenle tüketebilir', $template['total_days'] ),
            'related_ingredients' => $related_ingredients,
        ];

        return $result;
    }

    /**
     * Get ingredients with cross-allergen risk
     * 
     * @param array $allergen_names Allergen names
     * @return array Related ingredients
     */
    private static function get_related_ingredients( $allergen_names ) {
        if ( empty( $allergen_names ) ) {
            return [];
        }

        $related = [];
        foreach ( $allergen_names as $name ) {
            $related[] = [
                'name' => $name,
                'warning' => 'Çapraz alerji riski bulunmaktadır',
            ];
        }

        return $related;
    }
}
