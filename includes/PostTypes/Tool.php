<?php
namespace KG_Core\PostTypes;

class Tool {
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'init', [ $this, 'register_taxonomy' ] );
        add_action( 'acf/init', [ $this, 'register_acf_fields' ] );
    }

    public function register_post_type() {
        register_post_type( 'tool', [
            'labels' => [
                'name' => 'Araçlar',
                'singular_name' => 'Araç'
            ],
            'public' => true,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-calculator'
        ]);
    }

    public function register_taxonomy() {
        register_taxonomy( 'tool_type', 'tool', [
            'labels' => [
                'name' => 'Araç Tipleri',
                'singular_name' => 'Araç Tipi'
            ],
            'public' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'rewrite' => [ 'slug' => 'tool-type' ],
        ]);
    }

    public function register_acf_fields() {
        // Check if ACF is available
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        // Basic Tool Configuration
        acf_add_local_field_group([
            'key' => 'group_tool_basic',
            'title' => 'Araç Ayarları',
            'fields' => [
                [
                    'key' => 'field_tool_type',
                    'label' => 'Araç Tipi',
                    'name' => 'tool_type',
                    'type' => 'select',
                    'required' => 1,
                    'choices' => [
                        'blw_test' => 'BLW Hazırlık Testi',
                        'percentile' => 'Persentil Hesaplayıcı',
                        'water_calculator' => 'Su İhtiyacı Hesaplayıcı',
                        'meal_planner' => 'Yemek Planlayıcı',
                        'bath_planner' => 'Banyo Rutini Planlayıcı',
                        'hygiene_calculator' => 'Günlük Hijyen İhtiyacı Hesaplayıcı',
                        'diaper_calculator' => 'Akıllı Bez Hesaplayıcı',
                        'air_quality_guide' => 'Hava Kalitesi Rehberi',
                        'stain_encyclopedia' => 'Leke Ansiklopedisi',
                    ],
                    'default_value' => 'blw_test',
                ],
                [
                    'key' => 'field_tool_icon',
                    'label' => 'Araç İkonu',
                    'name' => 'tool_icon',
                    'type' => 'text',
                    'instructions' => 'FontAwesome class (örn: fa-utensils)',
                    'default_value' => 'fa-calculator',
                ],
                [
                    'key' => 'field_is_active',
                    'label' => 'Aktif mi?',
                    'name' => 'is_active',
                    'type' => 'true_false',
                    'default_value' => 1,
                    'ui' => 1,
                ],
                [
                    'key' => 'field_requires_auth',
                    'label' => 'Giriş Gerekli mi?',
                    'name' => 'requires_auth',
                    'type' => 'true_false',
                    'default_value' => 0,
                    'ui' => 1,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'tool',
                    ],
                ],
            ],
        ]);

        // BLW Test Configuration
        acf_add_local_field_group([
            'key' => 'group_blw_test',
            'title' => 'BLW Test Ayarları',
            'fields' => [
                [
                    'key' => 'field_blw_questions',
                    'label' => 'Sorular',
                    'name' => 'blw_questions',
                    'type' => 'repeater',
                    'layout' => 'block',
                    'button_label' => 'Soru Ekle',
                    'sub_fields' => [
                        [
                            'key' => 'field_question_id',
                            'label' => 'Soru ID',
                            'name' => 'id',
                            'type' => 'text',
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_question_category',
                            'label' => 'Kategori',
                            'name' => 'category',
                            'type' => 'select',
                            'choices' => [
                                'physical_readiness' => 'Fiziksel Hazırlık',
                                'safety' => 'Güvenlik',
                                'environment' => 'Çevre',
                            ],
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_question_text',
                            'label' => 'Soru Metni',
                            'name' => 'question',
                            'type' => 'text',
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_question_description',
                            'label' => 'Açıklama',
                            'name' => 'description',
                            'type' => 'textarea',
                            'rows' => 2,
                        ],
                        [
                            'key' => 'field_question_icon',
                            'label' => 'İkon',
                            'name' => 'icon',
                            'type' => 'text',
                            'default_value' => 'fa-question-circle',
                        ],
                        [
                            'key' => 'field_question_weight',
                            'label' => 'Ağırlık (0-100)',
                            'name' => 'weight',
                            'type' => 'number',
                            'default_value' => 50,
                            'min' => 0,
                            'max' => 100,
                        ],
                        [
                            'key' => 'field_question_options',
                            'label' => 'Seçenekler',
                            'name' => 'options',
                            'type' => 'repeater',
                            'layout' => 'table',
                            'button_label' => 'Seçenek Ekle',
                            'sub_fields' => [
                                [
                                    'key' => 'field_option_id',
                                    'label' => 'ID',
                                    'name' => 'id',
                                    'type' => 'text',
                                    'required' => 1,
                                ],
                                [
                                    'key' => 'field_option_text',
                                    'label' => 'Metin',
                                    'name' => 'text',
                                    'type' => 'text',
                                    'required' => 1,
                                ],
                                [
                                    'key' => 'field_option_value',
                                    'label' => 'Değer (0-100)',
                                    'name' => 'value',
                                    'type' => 'number',
                                    'required' => 1,
                                    'min' => 0,
                                    'max' => 100,
                                ],
                                [
                                    'key' => 'field_option_is_red_flag',
                                    'label' => 'Kırmızı Bayrak',
                                    'name' => 'is_red_flag',
                                    'type' => 'true_false',
                                    'ui' => 1,
                                ],
                                [
                                    'key' => 'field_option_red_flag_message',
                                    'label' => 'Kırmızı Bayrak Mesajı',
                                    'name' => 'red_flag_message',
                                    'type' => 'textarea',
                                    'rows' => 2,
                                    'conditional_logic' => [
                                        [
                                            [
                                                'field' => 'field_option_is_red_flag',
                                                'operator' => '==',
                                                'value' => '1',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'field_result_buckets',
                    'label' => 'Sonuç Kategorileri',
                    'name' => 'result_buckets',
                    'type' => 'repeater',
                    'layout' => 'block',
                    'button_label' => 'Kategori Ekle',
                    'sub_fields' => [
                        [
                            'key' => 'field_bucket_id',
                            'label' => 'ID',
                            'name' => 'id',
                            'type' => 'text',
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_bucket_min_score',
                            'label' => 'Min Puan',
                            'name' => 'min_score',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 0,
                            'max' => 100,
                        ],
                        [
                            'key' => 'field_bucket_max_score',
                            'label' => 'Max Puan',
                            'name' => 'max_score',
                            'type' => 'number',
                            'required' => 1,
                            'min' => 0,
                            'max' => 100,
                        ],
                        [
                            'key' => 'field_bucket_title',
                            'label' => 'Başlık',
                            'name' => 'title',
                            'type' => 'text',
                            'required' => 1,
                        ],
                        [
                            'key' => 'field_bucket_subtitle',
                            'label' => 'Alt Başlık',
                            'name' => 'subtitle',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'field_bucket_color',
                            'label' => 'Renk',
                            'name' => 'color',
                            'type' => 'select',
                            'choices' => [
                                'green' => 'Yeşil',
                                'yellow' => 'Sarı',
                                'red' => 'Kırmızı',
                            ],
                        ],
                        [
                            'key' => 'field_bucket_icon',
                            'label' => 'İkon',
                            'name' => 'icon',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'field_bucket_description',
                            'label' => 'Açıklama',
                            'name' => 'description',
                            'type' => 'textarea',
                        ],
                        [
                            'key' => 'field_bucket_action_items',
                            'label' => 'Aksiyon Maddeleri',
                            'name' => 'action_items',
                            'type' => 'textarea',
                            'instructions' => 'Her satır bir madde olarak işlenir',
                        ],
                        [
                            'key' => 'field_bucket_next_steps',
                            'label' => 'Sonraki Adımlar',
                            'name' => 'next_steps',
                            'type' => 'textarea',
                            'instructions' => 'Her satır bir adım olarak işlenir',
                        ],
                    ],
                ],
                [
                    'key' => 'field_disclaimer_text',
                    'label' => 'Sorumluluk Reddi',
                    'name' => 'disclaimer_text',
                    'type' => 'wysiwyg',
                    'toolbar' => 'basic',
                    'media_upload' => 0,
                ],
                [
                    'key' => 'field_emergency_text',
                    'label' => 'Acil Durum Metni',
                    'name' => 'emergency_text',
                    'type' => 'wysiwyg',
                    'toolbar' => 'basic',
                    'media_upload' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'tool',
                    ],
                ],
            ],
        ]);
    }
}