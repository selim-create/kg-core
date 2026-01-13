<?php
/**
 * Plugin Name: KG Core (KidsGourmet)
 * Plugin URI: https://kidsgourmet.com
 * Description: KidsGourmet Headless CMS Core Logic. Handles CPTs, Taxonomies, and Custom API Endpoints.
 * Version: 1.0.0
 * Author: Hip Medya
 * Text Domain: kg-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// 1. SABİTLERİ TANIMLA
define( 'KG_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'KG_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'KG_CORE_VERSION', '1.0.0' );

// 2. YARDIMCI SINIFLARI DAHİL ET (Utils)
// Dosya var mı kontrolü eklenerek hata önleniyor.
if ( file_exists( KG_CORE_PATH . 'includes/Utils/Helper.php' ) ) {
    require_once KG_CORE_PATH . 'includes/Utils/Helper.php';
} else {
    // Geliştirme aşamasında hata ayıklamak için log atılabilir veya admin uyarısı gösterilebilir.
    error_log( 'KG Core: Helper.php dosyası bulunamadı.' );
}

// 2.5. AUTH SINIFLARI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Auth/JWTHandler.php' ) ) require_once KG_CORE_PATH . 'includes/Auth/JWTHandler.php';
if ( file_exists( KG_CORE_PATH . 'includes/Auth/GoogleAuth.php' ) ) require_once KG_CORE_PATH . 'includes/Auth/GoogleAuth.php';

// 2.6. CORS DESTEĞI
if ( file_exists( KG_CORE_PATH . 'includes/CORS/CORSHandler.php' ) ) {
    require_once KG_CORE_PATH . 'includes/CORS/CORSHandler.php';
    new \KG_Core\CORS\CORSHandler();
}

// 2.7. SERVİS SINIFLARI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Services/TariftenService.php' ) ) require_once KG_CORE_PATH . 'includes/Services/TariftenService.php';

// 2.8. AI SERVİSLERİ DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Services/AIService.php' ) ) require_once KG_CORE_PATH . 'includes/Services/AIService.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/ImageService.php' ) ) require_once KG_CORE_PATH . 'includes/Services/ImageService.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/IngredientGenerator.php' ) ) require_once KG_CORE_PATH . 'includes/Services/IngredientGenerator.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/RecipeSEOGenerator.php' ) ) require_once KG_CORE_PATH . 'includes/Services/RecipeSEOGenerator.php';

// 3. POST TYPE SINIFLARINI DAHİL ET (CPT)
// Dosyalar mevcutsa dahil et
if ( file_exists( KG_CORE_PATH . 'includes/PostTypes/Recipe.php' ) ) require_once KG_CORE_PATH . 'includes/PostTypes/Recipe.php';
if ( file_exists( KG_CORE_PATH . 'includes/PostTypes/Ingredient.php' ) ) require_once KG_CORE_PATH . 'includes/PostTypes/Ingredient.php';
if ( file_exists( KG_CORE_PATH . 'includes/PostTypes/Tool.php' ) ) require_once KG_CORE_PATH . 'includes/PostTypes/Tool.php';
// Discussion (Topluluk Soruları) Post Type
if ( file_exists( KG_CORE_PATH . 'includes/PostTypes/Discussion.php' ) ) require_once KG_CORE_PATH . 'includes/PostTypes/Discussion.php';

// 4. TAXONOMY SINIFLARINI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/AgeGroup.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/AgeGroup.php';
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/Allergen.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/Allergen.php';
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/DietType.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/DietType.php';
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/MealType.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/MealType.php';
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/IngredientCategory.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/IngredientCategory.php';
// Community Circle (Çemberler) Taxonomy
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/CommunityCircle.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/CommunityCircle.php';

// 5. ADMIN PANELİ ÖZEL ALANLARI (ACF Alternatifi)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/RecipeMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/RecipeMetaBox.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/IngredientMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/IngredientMetaBox.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/PostMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/PostMetaBox.php';

// 5.5. AI ADMIN SAYFALARI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Admin/SettingsPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/SettingsPage.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/BulkIngredientSeeder.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/BulkIngredientSeeder.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/AIEnrichButton.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/AIEnrichButton.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/MigrationPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/MigrationPage.php';
// Discussion Admin (Moderasyon Sayfası)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/DiscussionAdmin.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/DiscussionAdmin.php';

// 5.6. MIGRATION SINIFLARINI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Migration/ContentParser.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/ContentParser.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/IngredientParser.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/IngredientParser.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/AgeGroupMapper.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/AgeGroupMapper.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/AIEnhancer.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/AIEnhancer.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/SEOHandler.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/SEOHandler.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/MigrationLogger.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/MigrationLogger.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/RecipeMigrator.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/RecipeMigrator.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/AIRecipeMigrator.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/AIRecipeMigrator.php';

// 6. API KONTROL CİHAZLARINI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/API/RecipeController.php' ) ) require_once KG_CORE_PATH . 'includes/API/RecipeController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/IngredientController.php' ) ) require_once KG_CORE_PATH . 'includes/API/IngredientController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/UserController.php' ) ) require_once KG_CORE_PATH . 'includes/API/UserController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/SearchController.php' ) ) require_once KG_CORE_PATH . 'includes/API/SearchController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/CrossSellController.php' ) ) require_once KG_CORE_PATH . 'includes/API/CrossSellController.php';

// 6.5. AI API CONTROLLER DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/API/AIController.php' ) ) require_once KG_CORE_PATH . 'includes/API/AIController.php';
// Discussion API Controller
if ( file_exists( KG_CORE_PATH . 'includes/API/DiscussionController.php' ) ) require_once KG_CORE_PATH . 'includes/API/DiscussionController.php';

// 7. SINIFLARI BAŞLAT (INIT HOOK)
function kg_core_init() {
    // Helper sınıfı static metodlar içerdiği için başlatılmasına gerek yoktur.
    // Ancak sınıfın yüklendiğinden emin olmak gerekir.
    if ( class_exists( '\KG_Core\Utils\Helper' ) ) {
        // \KG_Core\Utils\Helper::log('KG Core Başlatıldı.');
    }

    // Post Types
    if ( class_exists( '\KG_Core\PostTypes\Recipe' ) ) new \KG_Core\PostTypes\Recipe();
    if ( class_exists( '\KG_Core\PostTypes\Ingredient' ) ) new \KG_Core\PostTypes\Ingredient();
    if ( class_exists( '\KG_Core\PostTypes\Tool' ) ) new \KG_Core\PostTypes\Tool();
    if ( class_exists( '\KG_Core\PostTypes\Discussion' ) ) new \KG_Core\PostTypes\Discussion();

    // Taxonomies
    if ( class_exists( '\KG_Core\Taxonomies\AgeGroup' ) ) new \KG_Core\Taxonomies\AgeGroup();
    if ( class_exists( '\KG_Core\Taxonomies\Allergen' ) ) new \KG_Core\Taxonomies\Allergen();
    if ( class_exists( '\KG_Core\Taxonomies\DietType' ) ) new \KG_Core\Taxonomies\DietType();
    if ( class_exists( '\KG_Core\Taxonomies\MealType' ) ) new \KG_Core\Taxonomies\MealType();
    if ( class_exists( '\KG_Core\Taxonomies\IngredientCategory' ) ) new \KG_Core\Taxonomies\IngredientCategory();
    if ( class_exists( '\KG_Core\Taxonomies\CommunityCircle' ) ) new \KG_Core\Taxonomies\CommunityCircle();

    // Admin Meta Boxes (Sadece Admin panelinde çalışsın)
    if ( is_admin() && class_exists( '\KG_Core\Admin\RecipeMetaBox' ) ) {
        new \KG_Core\Admin\RecipeMetaBox();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\IngredientMetaBox' ) ) {
        new \KG_Core\Admin\IngredientMetaBox();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\PostMetaBox' ) ) {
        new \KG_Core\Admin\PostMetaBox();
    }
    
    // AI Admin Pages (Sadece Admin panelinde çalışsın)
    if ( is_admin() && class_exists( '\KG_Core\Admin\SettingsPage' ) ) {
        new \KG_Core\Admin\SettingsPage();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\BulkIngredientSeeder' ) ) {
        new \KG_Core\Admin\BulkIngredientSeeder();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\AIEnrichButton' ) ) {
        new \KG_Core\Admin\AIEnrichButton();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\MigrationPage' ) ) {
        new \KG_Core\Admin\MigrationPage();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\DiscussionAdmin' ) ) {
        new \KG_Core\Admin\DiscussionAdmin();
    }

    // API Controllers
    if ( class_exists( '\KG_Core\API\RecipeController' ) ) new \KG_Core\API\RecipeController();
    if ( class_exists( '\KG_Core\API\IngredientController' ) ) new \KG_Core\API\IngredientController();
    if ( class_exists( '\KG_Core\API\UserController' ) ) new \KG_Core\API\UserController();
    if ( class_exists( '\KG_Core\API\SearchController' ) ) new \KG_Core\API\SearchController();
    if ( class_exists( '\KG_Core\API\CrossSellController' ) ) new \KG_Core\API\CrossSellController();
    if ( class_exists( '\KG_Core\API\AIController' ) ) new \KG_Core\API\AIController();
    if ( class_exists( '\KG_Core\API\DiscussionController' ) ) new \KG_Core\API\DiscussionController();
}
add_action( 'plugins_loaded', 'kg_core_init' );

// 8. ADMIN ASSETS ENQUEUE
function kg_core_enqueue_admin_assets( $hook ) {
    // Only load on post edit screens for recipe post type
    global $post_type;
    
    if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && $post_type === 'recipe' ) {
        // Enqueue jQuery UI Sortable (already included in WordPress)
        wp_enqueue_script( 'jquery-ui-sortable' );
        
        // Enqueue custom admin CSS
        wp_enqueue_style( 
            'kg-metabox-css', 
            KG_CORE_URL . 'assets/admin/css/metabox.css', 
            [], 
            KG_CORE_VERSION 
        );
        
        // Enqueue custom admin JS
        wp_enqueue_script( 
            'kg-metabox-js', 
            KG_CORE_URL . 'assets/admin/js/metabox.js', 
            [ 'jquery', 'jquery-ui-sortable' ], 
            KG_CORE_VERSION, 
            true 
        );
        
        // Enqueue cross-sell JS
        wp_enqueue_script( 
            'kg-cross-sell-js', 
            KG_CORE_URL . 'assets/admin/js/cross-sell.js', 
            [ 'jquery' ], 
            KG_CORE_VERSION, 
            true 
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script( 'kg-metabox-js', 'kgMetaBox', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'kg_metabox_nonce' ),
        ]);
        
        // Localize cross-sell script with nonce
        wp_localize_script( 'kg-cross-sell-js', 'kg_cross_sell', [
            'nonce' => wp_create_nonce( 'kg_cross_sell_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ]);
    }
    
    // Load on post edit screens for post (WordPress Posts)
    if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && $post_type === 'post' ) {
        // Enqueue WordPress Media Uploader
        wp_enqueue_media();
        
        // Enqueue sponsor media JS
        wp_enqueue_script( 
            'kg-sponsor-media-js', 
            KG_CORE_URL . 'assets/admin/js/sponsor-media.js', 
            [ 'jquery' ], 
            KG_CORE_VERSION, 
            true 
        );
    }
}
add_action( 'admin_enqueue_scripts', 'kg_core_enqueue_admin_assets' );

// 8.5. REST API - Register sponsor_data field for posts
add_action( 'rest_api_init', function() {
    register_rest_field( 'post', 'sponsor_data', [
        'get_callback' => function( $post ) {
            $is_sponsored = get_post_meta( $post['id'], '_kg_is_sponsored', true );
            
            // Return null if not sponsored
            if ( $is_sponsored !== '1' ) {
                return null;
            }
            
            // Get all sponsor meta data
            $sponsor_logo_id = get_post_meta( $post['id'], '_kg_sponsor_logo', true );
            $sponsor_light_logo_id = get_post_meta( $post['id'], '_kg_sponsor_light_logo', true );
            
            // Convert attachment IDs to URLs
            $sponsor_logo_url = $sponsor_logo_id ? wp_get_attachment_url( $sponsor_logo_id ) : null;
            $sponsor_light_logo_url = $sponsor_light_logo_id ? wp_get_attachment_url( $sponsor_light_logo_id ) : null;
            
            return [
                'is_sponsored' => true,
                'sponsor_name' => get_post_meta( $post['id'], '_kg_sponsor_name', true ),
                'sponsor_url' => get_post_meta( $post['id'], '_kg_sponsor_url', true ),
                'sponsor_logo' => [
                    'id' => $sponsor_logo_id ? absint( $sponsor_logo_id ) : null,
                    'url' => $sponsor_logo_url,
                ],
                'sponsor_light_logo' => [
                    'id' => $sponsor_light_logo_id ? absint( $sponsor_light_logo_id ) : null,
                    'url' => $sponsor_light_logo_url,
                ],
                'direct_redirect' => get_post_meta( $post['id'], '_kg_direct_redirect', true ) === '1',
                'gam_impression_url' => get_post_meta( $post['id'], '_kg_gam_impression_url', true ),
                'gam_click_url' => get_post_meta( $post['id'], '_kg_gam_click_url', true ),
            ];
        },
        'schema' => [
            'description' => __( 'Sponsor data for the post', 'kg-core' ),
            'type' => [ 'object', 'null' ],
        ],
    ]);
});

// Opsiyonel: ACF JSON Kayıt Yeri (Eğer ACF kurarsanız diye)
add_filter('acf/settings/save_json', function( $path ) {
    return KG_CORE_PATH . 'includes/Fields/acf-json';
});
add_filter('acf/settings/load_json', function( $paths ) {
    unset($paths[0]);
    $paths[] = KG_CORE_PATH . 'includes/Fields/acf-json';
    return $paths;
});

// 9. CRON HOOKS - AI ile malzeme ve SEO oluşturma

// 9.1. Malzeme oluşturma CRON
add_action( 'kg_generate_ingredient', function( $ingredient_name ) {
    if ( class_exists( '\KG_Core\Services\IngredientGenerator' ) ) {
        $generator = new \KG_Core\Services\IngredientGenerator();
        $result = $generator->create( $ingredient_name );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'KG Core: Ingredient generation failed for ' . $ingredient_name . ': ' . $result->get_error_message() );
            
            // Fallback: Create basic ingredient post if AI fails
            $fallback_id = wp_insert_post([
                'post_title' => $ingredient_name,
                'post_type' => 'ingredient',
                'post_status' => 'draft',
                'post_content' => __('Bu malzeme otomatik oluşturuldu ve AI ile zenginleştirilmesi bekleniyor.', 'kg-core')
            ]);
            
            if ( ! is_wp_error( $fallback_id ) ) {
                update_post_meta( $fallback_id, '_kg_needs_ai_enrichment', '1' );
                error_log( 'KG Core: Fallback ingredient created (ID: ' . $fallback_id . ') for ' . $ingredient_name );
            }
        } else {
            error_log( 'KG Core: Ingredient generated successfully - ' . $ingredient_name . ' (ID: ' . $result . ')' );
        }
    }
} );

// 9.2. Recipe SEO oluşturma CRON
add_action( 'kg_generate_recipe_seo', function( $recipe_id ) {
    if ( class_exists( '\KG_Core\Services\RecipeSEOGenerator' ) ) {
        $generator = new \KG_Core\Services\RecipeSEOGenerator();
        $seo_data = $generator->generateSEO( $recipe_id );
        
        if ( is_wp_error( $seo_data ) ) {
            error_log( 'KG Core: Recipe SEO generation failed for ID ' . $recipe_id . ': ' . $seo_data->get_error_message() );
        } else {
            $generator->saveSEO( $recipe_id, $seo_data );
            error_log( 'KG Core: Recipe SEO generated successfully for ID ' . $recipe_id );
        }
    }
} );
