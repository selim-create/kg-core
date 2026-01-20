<?php
namespace KG_Core\Migration;

/**
 * StainMigration - Migrates hardcoded stain data to CPT
 */
class StainMigration {

    private $migration_flag_key = 'kg_stain_migration_completed';

    /**
     * Check if migration has already run
     */
    public function has_run() {
        return get_option( $this->migration_flag_key, false );
    }

    /**
     * Run the migration
     */
    public function run() {
        // Check if already run
        if ( $this->has_run() ) {
            return [
                'success' => false,
                'message' => 'Migration already completed. Delete the option "kg_stain_migration_completed" to run again.',
            ];
        }

        $stains_data = $this->get_hardcoded_stain_data();
        $imported = 0;
        $errors = [];

        foreach ( $stains_data as $stain ) {
            $result = $this->import_stain( $stain );
            if ( is_wp_error( $result ) ) {
                $errors[] = $stain['slug'] . ': ' . $result->get_error_message();
            } else {
                $imported++;
            }
        }

        // Mark migration as complete
        update_option( $this->migration_flag_key, true );

        return [
            'success' => true,
            'imported' => $imported,
            'total' => count( $stains_data ),
            'errors' => $errors,
        ];
    }

    /**
     * Import a single stain
     */
    private function import_stain( $stain ) {
        // Check if stain already exists
        $existing = get_page_by_path( $stain['slug'], OBJECT, 'stain' );
        if ( $existing ) {
            return new \WP_Error( 'stain_exists', 'Stain already exists' );
        }

        // Create post
        $post_data = [
            'post_title' => $stain['name'],
            'post_name' => $stain['slug'],
            'post_type' => 'stain',
            'post_status' => 'publish',
            'post_content' => '', // We can leave content empty as details are in meta
        ];

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Set category
        if ( ! empty( $stain['category'] ) ) {
            $term = get_term_by( 'slug', $stain['category'], 'stain_category' );
            if ( $term ) {
                wp_set_post_terms( $post_id, [ $term->term_id ], 'stain_category' );
            }
        }

        // Save meta fields
        update_post_meta( $post_id, '_kg_stain_emoji', $stain['emoji'] ?? '' );
        update_post_meta( $post_id, '_kg_stain_difficulty', $stain['difficulty'] ?? 'easy' );
        update_post_meta( $post_id, '_kg_stain_steps', wp_json_encode( $stain['steps'] ?? [] ) );
        update_post_meta( $post_id, '_kg_stain_warnings', wp_json_encode( $stain['warnings'] ?? [] ) );
        update_post_meta( $post_id, '_kg_stain_related_ingredients', wp_json_encode( $stain['related_ingredients'] ?? [] ) );

        return $post_id;
    }

    /**
     * Get the hardcoded stain data from SponsoredToolController
     * This is a copy of the data to preserve it
     */
    private function get_hardcoded_stain_data() {
        // This data is copied from SponsoredToolController::get_stain_database()
        // to preserve the original data during migration
        return $this->get_stain_database();
    }

    /**
     * Original hardcoded stain database
     * Copied from SponsoredToolController for migration purposes
     */
    private function get_stain_database() {
        // Comprehensive stain database with 40+ stains
        return [
            // FOOD STAINS (20 stains)
            [
                'id' => 1,
                'slug' => 'domates-lekesi',
                'name' => 'Domates Lekesi',
                'emoji' => '🍅',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla domatesi hemen kazıyarak temizleyin.',
                        'tip' => 'Lekeyi ovuşturmayın, daha fazla yayılmasına neden olur.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Lekeyi ters taraftan soğuk su ile durulayın.',
                        'tip' => 'Sıcak su lekeyi sabitler, mutlaka soğuk su kullanın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bulaşık deterjanı veya sıvı çamaşır deterjanı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15-30 dakika bekletin, sonra normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sıcak su veya kurutucu kullanmayın, leke kalıcı hale gelir.',
                    'Beyaz kumaşlarda limon suyu dikkatli kullanılmalıdır.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Beyaz sirke',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 2,
                'slug' => 'cikolata-lekesi',
                'name' => 'Çikolata Lekesi',
                'emoji' => '🍫',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla çikolatayı plastik bir kaşıkla kazıyın.',
                        'tip' => 'Metal kullanmayın, kumaşa zarar verebilir.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla lekeyi durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Yağ çözücü (bulaşık deterjanı) veya leke çıkarıcı sprey uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => '40°C\'de yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sıcak su kullanmayın, çikolata lekesini sabitler.',
                    'Kurutucuda kurutmayın.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Leke çıkarıcı sprey',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 3,
                'slug' => 'muz-lekesi',
                'name' => 'Muz Lekesi',
                'emoji' => '🍌',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla muzu kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Lekeyi ters taraftan soğuk su ile durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı çamaşır deterjanı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '10-15 dakika bekletin, sonra normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Kurumadan temizleyin, eski muz lekeleri zordur.',
                ],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                    'Beyaz sirke',
                ],
            ],
            [
                'id' => 4,
                'slug' => 'havuc-lekesi',
                'name' => 'Havuç Lekesi',
                'emoji' => '🥕',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla havucu kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Lekeyi ters taraftan soğuk su ile durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı çamaşır deterjanı ve biraz sirke karışımı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Havuç beta-karoten içerir, hızlı müdahale önemlidir.',
                ],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                    'Beyaz sirke',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 5,
                'slug' => 'mama-lekesi',
                'name' => 'Mama Lekesi',
                'emoji' => '🥣',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla mamayı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bebek deterjanı veya hassas çamaşır deterjanı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin, sonra yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Bebek cildine uygun deterjan kullanın.',
                ],
                'related_ingredients' => [
                    'Bebek deterjanı',
                    'Hassas çamaşır deterjanı',
                ],
            ],
            [
                'id' => 6,
                'slug' => 'sut-lekesi',
                'name' => 'Süt Lekesi',
                'emoji' => '🥛',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Lekeyi hemen soğuk su ile durulayın.',
                        'tip' => 'Sıcak su protein lekelerini sabitler.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Enzimli deterjan uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Asla sıcak su kullanmayın, lekeyi kalıcı hale getirir.',
                ],
                'related_ingredients' => [
                    'Enzimli deterjan',
                    'Soğuk su',
                ],
            ],
            [
                'id' => 7,
                'slug' => 'yumurta-lekesi',
                'name' => 'Yumurta Lekesi',
                'emoji' => '🥚',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla yumurtayı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                        'tip' => 'Sıcak su yumurta lekesini pişirir ve sabitler.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Enzimli deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Soğuk veya ılık suda yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sıcak su kesinlikle kullanmayın.',
                ],
                'related_ingredients' => [
                    'Enzimli deterjan',
                    'Soğuk su',
                ],
            ],
            [
                'id' => 8,
                'slug' => 'bal-lekesi',
                'name' => 'Bal Lekesi',
                'emoji' => '🍯',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla balı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin, sonra yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                ],
            ],
            [
                'id' => 9,
                'slug' => 'yogurt-lekesi',
                'name' => 'Yoğurt Lekesi',
                'emoji' => '🥄',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla yoğurdu kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Enzimli deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin, sonra yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sıcak su kullanmayın.',
                ],
                'related_ingredients' => [
                    'Enzimli deterjan',
                ],
            ],
            [
                'id' => 10,
                'slug' => 'kirmizi-meyve-lekesi',
                'name' => 'Kırmızı Meyve Lekesi',
                'emoji' => '🍓',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla meyveyi kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Beyaz sirke veya limon suyu uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Deterjanla yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Çilek, ahududu gibi meyveler inatçı leke yapar.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Limon suyu',
                    'Sıvı çamaşır deterjanı',
                ],
            ],
            [
                'id' => 11,
                'slug' => 'uzum-suyu-lekesi',
                'name' => 'Üzüm Suyu Lekesi',
                'emoji' => '🍇',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Hemen soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Beyaz sirke uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Oksijenli leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Mor üzüm suyu eski lekelerde kalıcı olabilir.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 12,
                'slug' => 'ispanak-lekesi',
                'name' => 'Ispanak Lekesi',
                'emoji' => '🥬',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla ıspanağı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan ve sirke karışımı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Ispanak klorofil içerir, inatçı leke yapar.',
                ],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                    'Beyaz sirke',
                ],
            ],
            [
                'id' => 13,
                'slug' => 'bezelye-lekesi',
                'name' => 'Bezelye Lekesi',
                'emoji' => '🫛',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla bezelyeyi kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin, sonra yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                ],
            ],
            [
                'id' => 14,
                'slug' => 'kabak-lekesi',
                'name' => 'Kabak Lekesi',
                'emoji' => '🎃',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla kabağı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin, sonra yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                ],
            ],
            [
                'id' => 15,
                'slug' => 'patates-lekesi',
                'name' => 'Patates Lekesi',
                'emoji' => '🥔',
                'category' => 'food',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla patates püresini kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin, sonra yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                ],
            ],
            [
                'id' => 16,
                'slug' => 'yag-lekesi',
                'name' => 'Yağ Lekesi',
                'emoji' => '🛢️',
                'category' => 'food',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Lekeye kuru toz (mısır nişastası veya bebek pudrası) döküp yağı emdirin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => '15 dakika bekleyin ve tozu fırçalayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bulaşık deterjanı uygulayın (yağ çözücü).',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Mümkünse sıcak suda yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Su kullanmadan önce mutlaka yağı emdirin.',
                ],
                'related_ingredients' => [
                    'Mısır nişastası',
                    'Bebek pudrası',
                    'Bulaşık deterjanı',
                ],
            ],
            [
                'id' => 17,
                'slug' => 'ketcap-lekesi',
                'name' => 'Ketçap Lekesi',
                'emoji' => '🍅',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla ketçapı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla ters taraftan durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bulaşık deterjanı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Beyaz sirke ile tekrar uygulayın.',
                    ],
                    [
                        'step' => 6,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sıcak su kullanmayın.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Beyaz sirke',
                ],
            ],
            [
                'id' => 18,
                'slug' => 'zerdecal-lekesi',
                'name' => 'Zerdeçal Lekesi',
                'emoji' => '🧡',
                'category' => 'food',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla zerdeçalı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Lekeye limon suyu veya beyaz sirke uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Güneş ışığında bekletin (doğal ağartıcı).',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Oksijenli leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Zerdeçal en inatçı lekelerden biridir.',
                    'Renkli kumaşlarda limon suyu renk açabilir.',
                ],
                'related_ingredients' => [
                    'Limon suyu',
                    'Beyaz sirke',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
            [
                'id' => 19,
                'slug' => 'nar-lekesi',
                'name' => 'Nar Lekesi',
                'emoji' => '🥭',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Hemen soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Beyaz sirke uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Sıvı deterjan ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Nar suyu hızla kumaşa işler, hemen müdahale edin.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Sıvı çamaşır deterjanı',
                ],
            ],
            [
                'id' => 20,
                'slug' => 'avokado-lekesi',
                'name' => 'Avokado Lekesi',
                'emoji' => '🥑',
                'category' => 'food',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla avokadoyu kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bulaşık deterjanı uygulayın (yağ çözücü).',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Avokado yağlı olduğu için hemen müdahale edin.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Sıvı çamaşır deterjanı',
                ],
            ],

            // BODILY STAINS (8 stains)
            [
                'id' => 21,
                'slug' => 'kaka-lekesi',
                'name' => 'Kaka Lekesi',
                'emoji' => '💩',
                'category' => 'bodily',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla katı maddeyi tuvalet kağıdı ile kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla ters taraftan durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Enzimli deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Ek ön yıkama yapın, sonra normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sıcak su kullanmayın, lekeyi sabitler.',
                    'Bebek deterjanı kullanın.',
                ],
                'related_ingredients' => [
                    'Enzimli bebek deterjanı',
                    'Soğuk su',
                ],
            ],
            [
                'id' => 22,
                'slug' => 'kusmuk-lekesi',
                'name' => 'Kusmuk Lekesi',
                'emoji' => '🤮',
                'category' => 'bodily',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla kusmuk maddeyi kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Beyaz sirke ve su karışımı (1:1) uygulayın (koku giderici).',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Enzimli deterjan ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Asidik içerik lekeye ve kumaşa zarar verebilir, hızlı müdahale edin.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Enzimli deterjan',
                    'Karbonat (koku giderici)',
                ],
            ],
            [
                'id' => 23,
                'slug' => 'anne-sutu-lekesi',
                'name' => 'Anne Sütü Lekesi',
                'emoji' => '🍼',
                'category' => 'bodily',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Hemen soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Enzimli bebek deterjanı uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sıcak su kullanmayın, protein lekelerini sabitler.',
                ],
                'related_ingredients' => [
                    'Enzimli bebek deterjanı',
                    'Soğuk su',
                ],
            ],
            [
                'id' => 24,
                'slug' => 'tukuruk-lekesi',
                'name' => 'Tükürük Lekesi',
                'emoji' => '💧',
                'category' => 'bodily',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Bebek deterjanı uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Bebek deterjanı',
                ],
            ],
            [
                'id' => 25,
                'slug' => 'idrar-lekesi',
                'name' => 'İdrar Lekesi',
                'emoji' => '💛',
                'category' => 'bodily',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Hemen soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Beyaz sirke ve su karışımı (1:1) uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Enzimli deterjan ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Koku kalıcı olabilir, sirke kullanın.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Enzimli deterjan',
                    'Karbonat',
                ],
            ],
            [
                'id' => 26,
                'slug' => 'kan-lekesi',
                'name' => 'Kan Lekesi',
                'emoji' => '🩸',
                'category' => 'bodily',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Hemen soğuk suyla durulayın.',
                        'tip' => 'Sıcak su kan lekesini pıhtılaştırır ve kalıcı hale getirir.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Tuz ve soğuk su karışımı yapın ve lekeye uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Oksijenli leke çıkarıcı veya hidrojen peroksit uygulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Enzimli deterjan ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Asla sıcak su kullanmayın.',
                    'Renkli kumaşlarda hidrojen peroksit renk açabilir.',
                ],
                'related_ingredients' => [
                    'Tuz',
                    'Soğuk su',
                    'Hidrojen peroksit',
                    'Enzimli deterjan',
                ],
            ],
            [
                'id' => 27,
                'slug' => 'ter-lekesi',
                'name' => 'Ter Lekesi',
                'emoji' => '💦',
                'category' => 'bodily',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Beyaz sirke ve su karışımı (1:1) uygulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Karbonat ve su karışımı ile ovalayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Sararmış ter lekeleri çok eski ise tamamen çıkmayabilir.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'Karbonat',
                    'Limon suyu',
                ],
            ],
            [
                'id' => 28,
                'slug' => 'goz-yasi-lekesi',
                'name' => 'Göz Yaşı Lekesi',
                'emoji' => '😢',
                'category' => 'bodily',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Hassas çamaşır deterjanı',
                ],
            ],

            // OUTDOOR STAINS (4 stains)
            [
                'id' => 29,
                'slug' => 'cim-lekesi',
                'name' => 'Çim Lekesi',
                'emoji' => '🌱',
                'category' => 'outdoor',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla toprağı fırçalayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Beyaz sirke veya alkol (isopropil) uygulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Enzimli deterjan ile ovalayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Çim klorofil içerir, inatçı leke yapar.',
                ],
                'related_ingredients' => [
                    'Beyaz sirke',
                    'İzopropil alkol',
                    'Enzimli deterjan',
                ],
            ],
            [
                'id' => 30,
                'slug' => 'toprak-lekesi',
                'name' => 'Toprak Lekesi',
                'emoji' => '🌍',
                'category' => 'outdoor',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Toprağın tamamen kurumasını bekleyin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Kuru toprağı fırçalayarak çıkarın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Islak toprak lekesini ovmayın, daha derinlere işler.',
                ],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                    'Leke çıkarıcı sprey',
                ],
            ],
            [
                'id' => 31,
                'slug' => 'kum-lekesi',
                'name' => 'Kum Lekesi',
                'emoji' => '🏖️',
                'category' => 'outdoor',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Kuru kumu silkeleme ile çıkarın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Kalan kumları fırçalayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Islak kumu ovalamayın.',
                ],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                ],
            ],
            [
                'id' => 32,
                'slug' => 'cicek-poleni-lekesi',
                'name' => 'Çiçek Poleni Lekesi',
                'emoji' => '🌼',
                'category' => 'outdoor',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Poleni ASLA elinizle dokunmayın veya su ile durulayamayın.',
                        'tip' => 'Yayılmasına neden olur.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Polenin üzerine yapışkan bant yapıştırarak kaldırın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Kalan lekeye izopropil alkol uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Oksijenli leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Polen en zor çıkan lekelerden biridir.',
                    'Su kullanmadan önce mutlaka polenin tamamını çıkarın.',
                ],
                'related_ingredients' => [
                    'Yapışkan bant',
                    'İzopropil alkol',
                    'Oksijenli leke çıkarıcı',
                ],
            ],

            // CRAFT STAINS (4 stains)
            [
                'id' => 33,
                'slug' => 'boya-lekesi',
                'name' => 'Boya Lekesi',
                'emoji' => '🎨',
                'category' => 'craft',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Boya türünü belirleyin (su bazlı / yağlı).',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Su bazlı ise: Hemen bol soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Yağlı ise: Terpantin veya alkol kullanın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Deterjan uygulayın ve 30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Kurumadan müdahale edin.',
                    'Yağlı boya çok zordur.',
                ],
                'related_ingredients' => [
                    'Soğuk su (su bazlı)',
                    'Terpantin (yağlı)',
                    'İzopropil alkol',
                    'Sıvı deterjan',
                ],
            ],
            [
                'id' => 34,
                'slug' => 'keceli-kalem-lekesi',
                'name' => 'Keçeli Kalem Lekesi',
                'emoji' => '🖍️',
                'category' => 'craft',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Lekeye izopropil alkol uygulayın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Temiz bir bezle lekeyi emdirin (ovmayın).',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Alkol uygulayıp emdirme işlemini tekrarlayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Bazı keçeli kalemler kalıcıdır ve tamamen çıkmaz.',
                ],
                'related_ingredients' => [
                    'İzopropil alkol',
                    'Leke çıkarıcı sprey',
                    'Sıvı deterjan',
                ],
            ],
            [
                'id' => 35,
                'slug' => 'pastel-boya-lekesi',
                'name' => 'Pastel Boya Lekesi',
                'emoji' => '🖌️',
                'category' => 'craft',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla boyayı kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Bulaşık deterjanı uygulayın (yağ çözücü).',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Ön yıkama yapın.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Pastel yağ bazlıdır, yağ çözücü kullanın.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Leke çıkarıcı sprey',
                ],
            ],
            [
                'id' => 36,
                'slug' => 'oyun-hamuru-lekesi',
                'name' => 'Oyun Hamuru Lekesi',
                'emoji' => '🧸',
                'category' => 'craft',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Oyun hamurunun kurumasını bekleyin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Kuru hamuru fırçalayarak çıkarın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Kalan lekeye sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '15 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Islak hamuru ovmayın.',
                ],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                ],
            ],

            // HOUSEHOLD STAINS (4 stains)
            [
                'id' => 37,
                'slug' => 'krem-lekesi',
                'name' => 'Krem Lekesi',
                'emoji' => '🧴',
                'category' => 'household',
                'difficulty' => 'medium',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla kremi kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Bulaşık deterjanı uygulayın (yağ çözücü).',
                    ],
                    [
                        'step' => 3,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Krem yağlıdır, mutlaka yağ çözücü kullanın.',
                ],
                'related_ingredients' => [
                    'Bulaşık deterjanı',
                    'Sıvı çamaşır deterjanı',
                ],
            ],
            [
                'id' => 38,
                'slug' => 'dis-macunu-lekesi',
                'name' => 'Diş Macunu Lekesi',
                'emoji' => '🪥',
                'category' => 'household',
                'difficulty' => 'easy',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla macunu kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Soğuk suyla durulayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Sıvı deterjan uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => 'Normal yıkayın.',
                    ],
                ],
                'warnings' => [],
                'related_ingredients' => [
                    'Sıvı çamaşır deterjanı',
                ],
            ],
            [
                'id' => 39,
                'slug' => 'bebek-yagi-lekesi',
                'name' => 'Bebek Yağı Lekesi',
                'emoji' => '🛢️',
                'category' => 'household',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Lekeye mısır nişastası veya bebek pudrası döküp yağı emdirin.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => '15 dakika bekleyin ve tozu fırçalayın.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bulaşık deterjanı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Sıcak suda yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Yağ lekeleri su ile yayılır, önce emdirin.',
                ],
                'related_ingredients' => [
                    'Mısır nişastası',
                    'Bebek pudrası',
                    'Bulaşık deterjanı',
                ],
            ],
            [
                'id' => 40,
                'slug' => 'pisik-kremi-lekesi',
                'name' => 'Pişik Kremi Lekesi',
                'emoji' => '🩹',
                'category' => 'household',
                'difficulty' => 'hard',
                'steps' => [
                    [
                        'step' => 1,
                        'instruction' => 'Fazla kremi kazıyın.',
                    ],
                    [
                        'step' => 2,
                        'instruction' => 'Mısır nişastası ile yağı emdirin.',
                    ],
                    [
                        'step' => 3,
                        'instruction' => 'Bulaşık deterjanı uygulayın.',
                    ],
                    [
                        'step' => 4,
                        'instruction' => '30-60 dakika bekletin.',
                    ],
                    [
                        'step' => 5,
                        'instruction' => 'Ön yıkama yapın.',
                    ],
                    [
                        'step' => 6,
                        'instruction' => 'Oksijenli leke çıkarıcı ile yıkayın.',
                    ],
                ],
                'warnings' => [
                    'Pişik kremi çinko oksit içerir, en zor lekelerden biridir.',
                    'Birkaç kez yıkama gerekebilir.',
                ],
                'related_ingredients' => [
                    'Mısır nişastası',
                    'Bulaşık deterjanı',
                    'Oksijenli leke çıkarıcı',
                ],
            ],
        ];
    }
}
