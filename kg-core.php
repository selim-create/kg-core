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

if ( file_exists( KG_CORE_PATH . 'includes/Utils/PrivacyHelper.php' ) ) {
    require_once KG_CORE_PATH . 'includes/Utils/PrivacyHelper.php';
}

// 2.5. AUTH SINIFLARI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Auth/JWTHandler.php' ) ) require_once KG_CORE_PATH . 'includes/Auth/JWTHandler.php';
if ( file_exists( KG_CORE_PATH . 'includes/Auth/GoogleAuth.php' ) ) require_once KG_CORE_PATH . 'includes/Auth/GoogleAuth.php';

// 2.5.1. ROL YÖNETİMİ SINIFLARI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Roles/RoleManager.php' ) ) require_once KG_CORE_PATH . 'includes/Roles/RoleManager.php';

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
if ( file_exists( KG_CORE_PATH . 'includes/Services/MealPlanGenerator.php' ) ) require_once KG_CORE_PATH . 'includes/Services/MealPlanGenerator.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/ShoppingListAggregator.php' ) ) require_once KG_CORE_PATH . 'includes/Services/ShoppingListAggregator.php';

// 2.9. SMART ASSISTANT TOOL SERVICES (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/Services/WaterCalculator.php' ) ) require_once KG_CORE_PATH . 'includes/Services/WaterCalculator.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/AllergenPlanner.php' ) ) require_once KG_CORE_PATH . 'includes/Services/AllergenPlanner.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/FoodSuitabilityChecker.php' ) ) require_once KG_CORE_PATH . 'includes/Services/FoodSuitabilityChecker.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/SolidFoodReadinessChecker.php' ) ) require_once KG_CORE_PATH . 'includes/Services/SolidFoodReadinessChecker.php';

// 2.9.1. RECOMMENDATION AND SAFETY SERVICES (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/Services/RecommendationService.php' ) ) require_once KG_CORE_PATH . 'includes/Services/RecommendationService.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/SafetyCheckService.php' ) ) require_once KG_CORE_PATH . 'includes/Services/SafetyCheckService.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/NutritionTrackerService.php' ) ) require_once KG_CORE_PATH . 'includes/Services/NutritionTrackerService.php';
if ( file_exists( KG_CORE_PATH . 'includes/Services/FoodIntroductionService.php' ) ) require_once KG_CORE_PATH . 'includes/Services/FoodIntroductionService.php';

// 2.10. TOOLS DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Tools/WHOGrowthData.php' ) ) require_once KG_CORE_PATH . 'includes/Tools/WHOGrowthData.php';

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
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/SpecialCondition.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/SpecialCondition.php';
// Community Circle (Çemberler) Taxonomy
if ( file_exists( KG_CORE_PATH . 'includes/Taxonomies/CommunityCircle.php' ) ) require_once KG_CORE_PATH . 'includes/Taxonomies/CommunityCircle.php';

// 5. ADMIN PANELİ ÖZEL ALANLARI (ACF Alternatifi)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/RecipeMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/RecipeMetaBox.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/IngredientMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/IngredientMetaBox.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/PostMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/PostMetaBox.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/DiscussionMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/DiscussionMetaBox.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/ToolSponsorMetaBox.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/ToolSponsorMetaBox.php';

// 5.5. AI ADMIN SAYFALARI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Admin/SettingsPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/SettingsPage.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/BulkIngredientSeeder.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/BulkIngredientSeeder.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/AIEnrichButton.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/AIEnrichButton.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/IngredientEnricher.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/IngredientEnricher.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/MigrationPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/MigrationPage.php';
// Tool Seeder (Araç Oluşturma Sayfası)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/ToolSeeder.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/ToolSeeder.php';
// Discussion Admin (Moderasyon Sayfası)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/DiscussionAdmin.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/DiscussionAdmin.php';
// User Profile Fields (Kullanıcı Profil Alanları)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/UserProfileFields.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/UserProfileFields.php';
// Embed Selector (İçerik Embed Seçici)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/EmbedSelector.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/EmbedSelector.php';

// 5.5.1. HEALTH ADMIN PAGES (Vaccination Tracker)
if ( file_exists( KG_CORE_PATH . 'includes/Health/VaccineManager.php' ) ) require_once KG_CORE_PATH . 'includes/Health/VaccineManager.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/KGCoreAdminMenu.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/KGCoreAdminMenu.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/VaccineAdminPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/VaccineAdminPage.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/EmailTemplateAdminPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/EmailTemplateAdminPage.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/NotificationLogAdminPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/NotificationLogAdminPage.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/SocialMediaSettings.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/SocialMediaSettings.php';

// 5.6. MIGRATION SINIFLARINI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Migration/FieldConsolidation.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/FieldConsolidation.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/ContentParser.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/ContentParser.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/IngredientParser.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/IngredientParser.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/AgeGroupMapper.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/AgeGroupMapper.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/AIEnhancer.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/AIEnhancer.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/SEOHandler.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/SEOHandler.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/MigrationLogger.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/MigrationLogger.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/RecipeMigrator.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/RecipeMigrator.php';
if ( file_exists( KG_CORE_PATH . 'includes/Migration/AIRecipeMigrator.php' ) ) require_once KG_CORE_PATH . 'includes/Migration/AIRecipeMigrator.php';

// 5.7. SHORTCODE SINIFLARINI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Shortcodes/ContentEmbed.php' ) ) require_once KG_CORE_PATH . 'includes/Shortcodes/ContentEmbed.php';

// 5.8. BLOCK SINIFLARINI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/Blocks/EmbedBlock.php' ) ) require_once KG_CORE_PATH . 'includes/Blocks/EmbedBlock.php';

// 6. API KONTROL CİHAZLARINI DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/API/RecipeController.php' ) ) require_once KG_CORE_PATH . 'includes/API/RecipeController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/IngredientController.php' ) ) require_once KG_CORE_PATH . 'includes/API/IngredientController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/UserController.php' ) ) require_once KG_CORE_PATH . 'includes/API/UserController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/SearchController.php' ) ) require_once KG_CORE_PATH . 'includes/API/SearchController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/CrossSellController.php' ) ) require_once KG_CORE_PATH . 'includes/API/CrossSellController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/FeaturedController.php' ) ) require_once KG_CORE_PATH . 'includes/API/FeaturedController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/CollectionController.php' ) ) require_once KG_CORE_PATH . 'includes/API/CollectionController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/ToolController.php' ) ) require_once KG_CORE_PATH . 'includes/API/ToolController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/MediaController.php' ) ) require_once KG_CORE_PATH . 'includes/API/MediaController.php';

// 6.5. AI API CONTROLLER DAHİL ET
if ( file_exists( KG_CORE_PATH . 'includes/API/AIController.php' ) ) require_once KG_CORE_PATH . 'includes/API/AIController.php';
// Discussion API Controller
if ( file_exists( KG_CORE_PATH . 'includes/API/DiscussionController.php' ) ) require_once KG_CORE_PATH . 'includes/API/DiscussionController.php';
// Expert API Controller
if ( file_exists( KG_CORE_PATH . 'includes/API/ExpertController.php' ) ) require_once KG_CORE_PATH . 'includes/API/ExpertController.php';
// Meal Plan API Controller
if ( file_exists( KG_CORE_PATH . 'includes/API/MealPlanController.php' ) ) require_once KG_CORE_PATH . 'includes/API/MealPlanController.php';
// Percentile API Controller
if ( file_exists( KG_CORE_PATH . 'includes/API/PercentileController.php' ) ) require_once KG_CORE_PATH . 'includes/API/PercentileController.php';
// Food Trial API Controller (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/API/FoodTrialController.php' ) ) require_once KG_CORE_PATH . 'includes/API/FoodTrialController.php';
// Sponsored Tool API Controller
if ( file_exists( KG_CORE_PATH . 'includes/API/SponsoredToolController.php' ) ) require_once KG_CORE_PATH . 'includes/API/SponsoredToolController.php';
// Lookup Controller (Slug Lookup Endpoint)
if ( file_exists( KG_CORE_PATH . 'includes/API/LookupController.php' ) ) require_once KG_CORE_PATH . 'includes/API/LookupController.php';
// Recommendation Controller (Personalization & Safety)
if ( file_exists( KG_CORE_PATH . 'includes/API/RecommendationController.php' ) ) require_once KG_CORE_PATH . 'includes/API/RecommendationController.php';
// Comment Controller (Generic Comments for Recipes and Posts)
if ( file_exists( KG_CORE_PATH . 'includes/API/CommentController.php' ) ) require_once KG_CORE_PATH . 'includes/API/CommentController.php';
// REST API Filters (WordPress REST API Avatar URL Override)
if ( file_exists( KG_CORE_PATH . 'includes/API/RestApiFilters.php' ) ) require_once KG_CORE_PATH . 'includes/API/RestApiFilters.php';

// 6.5. VACCINATION TRACKER API CONTROLLERS (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/API/VaccineController.php' ) ) require_once KG_CORE_PATH . 'includes/API/VaccineController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/NotificationController.php' ) ) require_once KG_CORE_PATH . 'includes/API/NotificationController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/AdminVaccineController.php' ) ) require_once KG_CORE_PATH . 'includes/API/AdminVaccineController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/VaccinePrivateController.php' ) ) require_once KG_CORE_PATH . 'includes/API/VaccinePrivateController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/VaccineExportController.php' ) ) require_once KG_CORE_PATH . 'includes/API/VaccineExportController.php';
if ( file_exists( KG_CORE_PATH . 'includes/API/PushNotificationController.php' ) ) require_once KG_CORE_PATH . 'includes/API/PushNotificationController.php';

// 6.6. ADMIN SINIFLARI DAHİL ET (Frontend View Links)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/FrontendViewLinks.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/FrontendViewLinks.php';

// 6.7. VACCINATION TRACKER ADMIN PAGES (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/Admin/VaccineAdminPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/VaccineAdminPage.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/EmailTemplateAdminPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/EmailTemplateAdminPage.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/NotificationLogAdminPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/NotificationLogAdminPage.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/PushNotificationAdminPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/PushNotificationAdminPage.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/VaccineStatsAdminPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/VaccineStatsAdminPage.php';

// 6.8. VACCINATION TRACKER HEALTH SERVICES (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/Health/VaccineManager.php' ) ) require_once KG_CORE_PATH . 'includes/Health/VaccineManager.php';
if ( file_exists( KG_CORE_PATH . 'includes/Health/VaccineScheduleCalculator.php' ) ) require_once KG_CORE_PATH . 'includes/Health/VaccineScheduleCalculator.php';
if ( file_exists( KG_CORE_PATH . 'includes/Health/VaccineRecordManager.php' ) ) require_once KG_CORE_PATH . 'includes/Health/VaccineRecordManager.php';
// SideEffectTracker is deprecated - use SideEffectManager instead
// if ( file_exists( KG_CORE_PATH . 'includes/Health/SideEffectTracker.php' ) ) require_once KG_CORE_PATH . 'includes/Health/SideEffectTracker.php';
if ( file_exists( KG_CORE_PATH . 'includes/Health/SideEffectManager.php' ) ) require_once KG_CORE_PATH . 'includes/Health/SideEffectManager.php';
if ( file_exists( KG_CORE_PATH . 'includes/Health/PrivateVaccineWizard.php' ) ) require_once KG_CORE_PATH . 'includes/Health/PrivateVaccineWizard.php';
if ( file_exists( KG_CORE_PATH . 'includes/Health/VaccineStatsCalculator.php' ) ) require_once KG_CORE_PATH . 'includes/Health/VaccineStatsCalculator.php';

// 6.9. VACCINATION TRACKER NOTIFICATION SERVICES (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/Notifications/NotificationManager.php' ) ) require_once KG_CORE_PATH . 'includes/Notifications/NotificationManager.php';
if ( file_exists( KG_CORE_PATH . 'includes/Notifications/EmailService.php' ) ) require_once KG_CORE_PATH . 'includes/Notifications/EmailService.php';
if ( file_exists( KG_CORE_PATH . 'includes/Notifications/EmailConfig.php' ) ) require_once KG_CORE_PATH . 'includes/Notifications/EmailConfig.php';
if ( file_exists( KG_CORE_PATH . 'includes/Notifications/EmailTemplateRenderer.php' ) ) require_once KG_CORE_PATH . 'includes/Notifications/EmailTemplateRenderer.php';
if ( file_exists( KG_CORE_PATH . 'includes/Notifications/TemplateEngine.php' ) ) require_once KG_CORE_PATH . 'includes/Notifications/TemplateEngine.php';
if ( file_exists( KG_CORE_PATH . 'includes/Notifications/NotificationQueue.php' ) ) require_once KG_CORE_PATH . 'includes/Notifications/NotificationQueue.php';
if ( file_exists( KG_CORE_PATH . 'includes/Notifications/VapidKeyManager.php' ) ) require_once KG_CORE_PATH . 'includes/Notifications/VapidKeyManager.php';
if ( file_exists( KG_CORE_PATH . 'includes/Notifications/PushSubscriptionManager.php' ) ) require_once KG_CORE_PATH . 'includes/Notifications/PushSubscriptionManager.php';
if ( file_exists( KG_CORE_PATH . 'includes/Notifications/PushNotificationService.php' ) ) require_once KG_CORE_PATH . 'includes/Notifications/PushNotificationService.php';

// 6.9.1. VACCINATION TRACKER EXPORT SERVICES (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/Export/VaccinePdfExporter.php' ) ) require_once KG_CORE_PATH . 'includes/Export/VaccinePdfExporter.php';

// 6.10. VACCINATION TRACKER CRON JOBS (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/Cron/VaccineReminderCron.php' ) ) require_once KG_CORE_PATH . 'includes/Cron/VaccineReminderCron.php';

// 6.11. VACCINATION TRACKER DATABASE SCHEMA (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/Database/VaccinationSchema.php' ) ) require_once KG_CORE_PATH . 'includes/Database/VaccinationSchema.php';

// 6.12. NEWSLETTER MODULE (NEW)
if ( file_exists( KG_CORE_PATH . 'includes/Newsletter/NewsletterSubscriber.php' ) ) require_once KG_CORE_PATH . 'includes/Newsletter/NewsletterSubscriber.php';
if ( file_exists( KG_CORE_PATH . 'includes/Newsletter/NewsletterRepository.php' ) ) require_once KG_CORE_PATH . 'includes/Newsletter/NewsletterRepository.php';
if ( file_exists( KG_CORE_PATH . 'includes/Newsletter/NewsletterService.php' ) ) require_once KG_CORE_PATH . 'includes/Newsletter/NewsletterService.php';
if ( file_exists( KG_CORE_PATH . 'includes/Newsletter/NewsletterRESTController.php' ) ) require_once KG_CORE_PATH . 'includes/Newsletter/NewsletterRESTController.php';
if ( file_exists( KG_CORE_PATH . 'includes/Admin/NewsletterAdminPage.php' ) ) require_once KG_CORE_PATH . 'includes/Admin/NewsletterAdminPage.php';

// 6.13. REDIRECT SINIFLARI DAHİL ET (Frontend Redirect)
if ( file_exists( KG_CORE_PATH . 'includes/Redirect/FrontendRedirect.php' ) ) require_once KG_CORE_PATH . 'includes/Redirect/FrontendRedirect.php';

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
    if ( class_exists( '\KG_Core\Taxonomies\SpecialCondition' ) ) new \KG_Core\Taxonomies\SpecialCondition();
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
    if ( is_admin() && class_exists( '\KG_Core\Admin\DiscussionMetaBox' ) ) {
        new \KG_Core\Admin\DiscussionMetaBox();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\ToolSponsorMetaBox' ) ) {
        new \KG_Core\Admin\ToolSponsorMetaBox();
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
    if ( is_admin() && class_exists( '\KG_Core\Admin\IngredientEnricher' ) ) {
        new \KG_Core\Admin\IngredientEnricher();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\MigrationPage' ) ) {
        new \KG_Core\Admin\MigrationPage();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\ToolSeeder' ) ) {
        new \KG_Core\Admin\ToolSeeder();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\DiscussionAdmin' ) ) {
        new \KG_Core\Admin\DiscussionAdmin();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\UserProfileFields' ) ) {
        new \KG_Core\Admin\UserProfileFields();
    }
    
    // Embed Selector (Admin only)
    if ( is_admin() && class_exists( '\KG_Core\Admin\EmbedSelector' ) ) {
        new \KG_Core\Admin\EmbedSelector();
    }
    
    // Health Admin Pages (Vaccination Tracker)
    if ( is_admin() && class_exists( '\KG_Core\Admin\KGCoreAdminMenu' ) ) {
        new \KG_Core\Admin\KGCoreAdminMenu();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\VaccineAdminPage' ) ) {
        new \KG_Core\Admin\VaccineAdminPage();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\EmailTemplateAdminPage' ) ) {
        new \KG_Core\Admin\EmailTemplateAdminPage();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\NotificationLogAdminPage' ) ) {
        new \KG_Core\Admin\NotificationLogAdminPage();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\SocialMediaSettings' ) ) {
        new \KG_Core\Admin\SocialMediaSettings();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\PushNotificationAdminPage' ) ) {
        new \KG_Core\Admin\PushNotificationAdminPage();
    }
    if ( is_admin() && class_exists( '\KG_Core\Admin\VaccineStatsAdminPage' ) ) {
        new \KG_Core\Admin\VaccineStatsAdminPage();
    }
    
    // Newsletter Admin Page
    if ( is_admin() && class_exists( '\KG_Core\Admin\NewsletterAdminPage' ) ) {
        new \KG_Core\Admin\NewsletterAdminPage();
    }
    
    // Email Configuration
    if ( class_exists( '\KG_Core\Notifications\EmailConfig' ) ) {
        new \KG_Core\Notifications\EmailConfig();
    }

    // Role Manager (RBAC)
    if ( class_exists( '\KG_Core\Roles\RoleManager' ) ) {
        new \KG_Core\Roles\RoleManager();
    }
    
    // Shortcodes
    if ( class_exists( '\KG_Core\Shortcodes\ContentEmbed' ) ) {
        new \KG_Core\Shortcodes\ContentEmbed();
    }
    
    // Blocks
    if ( class_exists( '\KG_Core\Blocks\EmbedBlock' ) ) {
        new \KG_Core\Blocks\EmbedBlock();
    }

    // API Controllers
    if ( class_exists( '\KG_Core\API\RecipeController' ) ) new \KG_Core\API\RecipeController();
    if ( class_exists( '\KG_Core\API\IngredientController' ) ) new \KG_Core\API\IngredientController();
    if ( class_exists( '\KG_Core\API\UserController' ) ) new \KG_Core\API\UserController();
    if ( class_exists( '\KG_Core\API\SearchController' ) ) new \KG_Core\API\SearchController();
    if ( class_exists( '\KG_Core\API\CrossSellController' ) ) new \KG_Core\API\CrossSellController();
    if ( class_exists( '\KG_Core\API\FeaturedController' ) ) new \KG_Core\API\FeaturedController();
    if ( class_exists( '\KG_Core\API\CollectionController' ) ) new \KG_Core\API\CollectionController();
    if ( class_exists( '\KG_Core\API\ToolController' ) ) new \KG_Core\API\ToolController();
    if ( class_exists( '\KG_Core\API\MediaController' ) ) new \KG_Core\API\MediaController();
    if ( class_exists( '\KG_Core\API\AIController' ) ) new \KG_Core\API\AIController();
    if ( class_exists( '\KG_Core\API\DiscussionController' ) ) new \KG_Core\API\DiscussionController();
    if ( class_exists( '\KG_Core\API\ExpertController' ) ) new \KG_Core\API\ExpertController();
    if ( class_exists( '\KG_Core\API\MealPlanController' ) ) new \KG_Core\API\MealPlanController();
    if ( class_exists( '\KG_Core\API\PercentileController' ) ) new \KG_Core\API\PercentileController();
    if ( class_exists( '\KG_Core\API\FoodTrialController' ) ) new \KG_Core\API\FoodTrialController();
    if ( class_exists( '\KG_Core\API\SponsoredToolController' ) ) new \KG_Core\API\SponsoredToolController();
    if ( class_exists( '\KG_Core\API\LookupController' ) ) new \KG_Core\API\LookupController();
    if ( class_exists( '\KG_Core\API\RecommendationController' ) ) new \KG_Core\API\RecommendationController();
    if ( class_exists( '\KG_Core\API\CommentController' ) ) new \KG_Core\API\CommentController();
    
    // REST API Filters (WordPress REST API Avatar URL Override)
    if ( class_exists( '\KG_Core\API\RestApiFilters' ) ) new \KG_Core\API\RestApiFilters();
    
    // Vaccination Tracker API Controllers
    if ( class_exists( '\KG_Core\API\VaccineController' ) ) new \KG_Core\API\VaccineController();
    if ( class_exists( '\KG_Core\API\NotificationController' ) ) new \KG_Core\API\NotificationController();
    if ( class_exists( '\KG_Core\API\AdminVaccineController' ) ) new \KG_Core\API\AdminVaccineController();
    if ( class_exists( '\KG_Core\API\VaccinePrivateController' ) ) new \KG_Core\API\VaccinePrivateController();
    if ( class_exists( '\KG_Core\API\VaccineExportController' ) ) new \KG_Core\API\VaccineExportController();
    if ( class_exists( '\KG_Core\API\PushNotificationController' ) ) new \KG_Core\API\PushNotificationController();
    
    // Newsletter REST Controller
    if ( class_exists( '\KG_Core\Newsletter\NewsletterRESTController' ) ) {
        new \KG_Core\Newsletter\NewsletterRESTController();
    }
    
    // Frontend View Links (Admin only)
    if ( is_admin() && class_exists( '\KG_Core\Admin\FrontendViewLinks' ) ) {
        new \KG_Core\Admin\FrontendViewLinks();
    }
    
    // Frontend Redirect (sadece frontend isteklerinde)
    if ( !is_admin() && !wp_doing_ajax() && !(defined('REST_REQUEST') && REST_REQUEST) && class_exists( '\KG_Core\Redirect\FrontendRedirect' ) ) {
        new \KG_Core\Redirect\FrontendRedirect();
    }
    
    // Vaccination Tracker Cron Job
    if ( class_exists( '\KG_Core\Cron\VaccineReminderCron' ) ) {
        new \KG_Core\Cron\VaccineReminderCron();
    }
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
    
    // Load on post edit screens for tool (Tool Post Type)
    if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && $post_type === 'tool' ) {
        // Enqueue WordPress Media Uploader
        wp_enqueue_media();
        
        // Enqueue sponsor media JS (reuse the same script for tool sponsors)
        wp_enqueue_script( 
            'kg-tool-sponsor-media-js', 
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
    
    // Register sponsor_data field for tool post type
    register_rest_field( 'tool', 'sponsor_data', [
        'get_callback' => function( $tool ) {
            $is_sponsored = get_post_meta( $tool['id'], '_kg_tool_is_sponsored', true );
            
            // Return null if not sponsored
            if ( $is_sponsored !== '1' ) {
                return null;
            }
            
            // Get all sponsor meta data
            $sponsor_logo_id = get_post_meta( $tool['id'], '_kg_tool_sponsor_logo', true );
            $sponsor_light_logo_id = get_post_meta( $tool['id'], '_kg_tool_sponsor_light_logo', true );
            
            // Convert attachment IDs to URLs
            $sponsor_logo_url = $sponsor_logo_id ? wp_get_attachment_url( $sponsor_logo_id ) : null;
            $sponsor_light_logo_url = $sponsor_light_logo_id ? wp_get_attachment_url( $sponsor_light_logo_id ) : null;
            
            return [
                'is_sponsored' => true,
                'sponsor_name' => get_post_meta( $tool['id'], '_kg_tool_sponsor_name', true ),
                'sponsor_url' => get_post_meta( $tool['id'], '_kg_tool_sponsor_url', true ),
                'sponsor_logo' => [
                    'id' => $sponsor_logo_id ? absint( $sponsor_logo_id ) : null,
                    'url' => $sponsor_logo_url,
                ],
                'sponsor_light_logo' => [
                    'id' => $sponsor_light_logo_id ? absint( $sponsor_light_logo_id ) : null,
                    'url' => $sponsor_light_logo_url,
                ],
                'sponsor_tagline' => get_post_meta( $tool['id'], '_kg_tool_sponsor_tagline', true ),
                'sponsor_cta' => [
                    'text' => get_post_meta( $tool['id'], '_kg_tool_sponsor_cta_text', true ),
                    'url' => get_post_meta( $tool['id'], '_kg_tool_sponsor_cta_url', true ),
                ],
                'gam_impression_url' => get_post_meta( $tool['id'], '_kg_tool_gam_impression_url', true ),
                'gam_click_url' => get_post_meta( $tool['id'], '_kg_tool_gam_click_url', true ),
            ];
        },
        'schema' => [
            'description' => __( 'Sponsor data for the tool', 'kg-core' ),
            'type' => [ 'object', 'null' ],
        ],
    ]);
});

// 8.6. Enhance WordPress posts REST API with additional fields
add_filter( 'rest_prepare_post', function( $response, $post, $request ) {
    $data = $response->get_data();
    
    // Add author data
    $author = get_userdata( $post->post_author );
    $data['author_data'] = [
        'name' => $author ? $author->display_name : 'KidsGourmet Editörü',
        'avatar' => get_avatar_url( $post->post_author, [ 'size' => 96 ] )
    ];
    
    // Add category data
    $categories = get_the_category( $post->ID );
    $data['category_data'] = !empty( $categories ) ? [
        'id' => $categories[0]->term_id,
        'name' => $categories[0]->name,
        'slug' => $categories[0]->slug
    ] : null;
    
    // Calculate read time
    $content = strip_tags( $post->post_content );
    $word_count = str_word_count( $content );
    $data['read_time'] = ceil( $word_count / 200 ) . ' dk';
    
    // Decode HTML entities in title
    if ( isset( $data['title']['rendered'] ) ) {
        $data['title']['rendered'] = \KG_Core\Utils\Helper::decode_html_entities( $data['title']['rendered'] );
    }
    
    $response->set_data( $data );
    return $response;
}, 10, 3 );

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

// 9.3. Helper function for taxonomy HTML entity decoding
function kg_decode_taxonomy_response( $response, $term ) {
    $data = $response->get_data();
    if ( isset( $data['name'] ) ) {
        $data['name'] = html_entity_decode( $data['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }
    if ( isset( $data['description'] ) ) {
        $data['description'] = html_entity_decode( $data['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }
    $response->set_data( $data );
    return $response;
}

// 9.4. Add HTML entity decoding filters for taxonomies
add_filter( 'rest_prepare_age-group', 'kg_decode_taxonomy_response', 10, 2 );
add_filter( 'rest_prepare_meal-type', 'kg_decode_taxonomy_response', 10, 2 );
add_filter( 'rest_prepare_diet-type', 'kg_decode_taxonomy_response', 10, 2 );
add_filter( 'rest_prepare_special-condition', 'kg_decode_taxonomy_response', 10, 2 );
add_filter( 'rest_prepare_category', 'kg_decode_taxonomy_response', 10, 2 );

// 10. ACTIVATION HOOK - Seed tools on plugin activation
register_activation_hook( __FILE__, function() {
    // Start output buffering - guarantee no output is produced
    ob_start();
    
    try {
        // Register roles first
        if ( class_exists( '\KG_Core\Roles\RoleManager' ) ) {
            $role_manager = new \KG_Core\Roles\RoleManager();
            $role_manager->register_custom_roles();
            
            // Update existing expert users
            \KG_Core\Roles\RoleManager::update_expert_capabilities();
        }
        
        // Seed tools on plugin activation
        if ( class_exists( '\KG_Core\Admin\ToolSeeder' ) ) {
            \KG_Core\Admin\ToolSeeder::seed_on_activation();
        }
        
        // Create vaccination tracker database tables
        if ( class_exists( '\KG_Core\Database\VaccinationSchema' ) ) {
            \KG_Core\Database\VaccinationSchema::create_tables();
        }
        
        // Load vaccine master data from JSON
        if ( class_exists( '\KG_Core\Health\VaccineManager' ) ) {
            $vaccine_manager = new \KG_Core\Health\VaccineManager();
            $vaccine_manager->load_vaccine_master_data();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
    } catch ( \Exception $e ) {
        error_log( 'KG Core Activation Error: ' . $e->getMessage() );
    } catch ( \Error $e ) {
        error_log( 'KG Core Activation Fatal Error: ' . $e->getMessage() );
    }
    
    // Clean all output
    ob_end_clean();
} );
