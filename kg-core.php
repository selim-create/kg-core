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

// 2.6. CORS DESTEĞI
if ( file_exists( KG_CORE_PATH . 'includes/CORS/CORSHandler.php' ) ) {
    require_once KG_CORE_PATH . 'includes/CORS/CORSHandler.php';
    new \KG_Core\CORS\CORSHandler();
}

// 2.7. SERVİS SINIFLARI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Services/TariftenService.php' ) ) require_once KG_CORE_PATH . 'includes/Services/TariftenService.php';

// 3. POST TYPE SINIFLARINI DAHİL ET (CPT)
// Dosyalar mevcutsa dahil et
if ( file_exists( KG_CORE_PATH . 'includes/PostTypes/Recipe.php' ) ) require_once KG_CORE_PATH . 'includes/PostTypes/Recipe.php';
if ( file_exists( KG_CORE_PATH . 'includes/PostTypes/Ingredient.php' ) ) require_once KG_CORE_PATH . 'includes/PostTypes/Ingredient.php';
if ( file_exists( KG_CORE_PATH . 'includes/PostTypes/Tool.php' ) ) require_once KG_CORE_PATH . 'includes/PostTypes/Tool.php';

// 4. TAXONOMY SINIFLARINI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/AgeGroup.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/AgeGroup.php';
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/Allergen.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/Allergen.php';
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/DietType.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/DietType.php';

// 5. ADMIN PANELİ ÖZEL ALANLARI (ACF Alternatifi)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/RecipeMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/RecipeMetaBox.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/IngredientMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/IngredientMetaBox.php';

// 6. API KONTROL CİHAZLARINI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/API/RecipeController.php' ) ) require_once KG_CORE_PATH . 'includes/API/RecipeController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/IngredientController.php' ) ) require_once KG_CORE_PATH . 'includes/API/IngredientController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/UserController.php' ) ) require_once KG_CORE_PATH . 'includes/API/UserController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/SearchController.php' ) ) require_once KG_CORE_PATH . 'includes/API/SearchController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/CrossSellController.php' ) ) require_once KG_CORE_PATH . 'includes/API/CrossSellController.php';

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

    // Taxonomies
    if ( class_exists( '\KG_Core\Taxonomies\AgeGroup' ) ) new \KG_Core\Taxonomies\AgeGroup();
    if ( class_exists( '\KG_Core\Taxonomies\Allergen' ) ) new \KG_Core\Taxonomies\Allergen();
    if ( class_exists( '\KG_Core\Taxonomies\DietType' ) ) new \KG_Core\Taxonomies\DietType();

    // Admin Meta Boxes (Sadece Admin panelinde çalışsın)
    if ( is_admin() && class_exists( '\KG_Core\Admin\RecipeMetaBox' ) ) {
        new \KG_Core\Admin\RecipeMetaBox();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\IngredientMetaBox' ) ) {
        new \KG_Core\Admin\IngredientMetaBox();
    }

    // API Controllers
    if ( class_exists( '\KG_Core\API\RecipeController' ) ) new \KG_Core\API\RecipeController();
    if ( class_exists( '\KG_Core\API\IngredientController' ) ) new \KG_Core\API\IngredientController();
    if ( class_exists( '\KG_Core\API\UserController' ) ) new \KG_Core\API\UserController();
    if ( class_exists( '\KG_Core\API\SearchController' ) ) new \KG_Core\API\SearchController();
    if ( class_exists( '\KG_Core\API\CrossSellController' ) ) new \KG_Core\API\CrossSellController();
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
}
add_action( 'admin_enqueue_scripts', 'kg_core_enqueue_admin_assets' );

// Opsiyonel: ACF JSON Kayıt Yeri (Eğer ACF kurarsanız diye)
add_filter('acf/settings/save_json', function( $path ) {
    return KG_CORE_PATH . 'includes/Fields/acf-json';
});
add_filter('acf/settings/load_json', function( $paths ) {
    unset($paths[0]);
    $paths[] = KG_CORE_PATH . 'includes/Fields/acf-json';
    return $paths;
});
