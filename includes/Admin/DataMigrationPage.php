<?php
namespace KG_Core\Admin;

use KG_Core\Database\DataMigration;
use KG_Core\Database\Schema;

/**
 * DataMigrationPage - Admin UI for migrating postmeta to custom tables
 */
class DataMigrationPage {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_kg_migrate_recipes', [$this, 'ajaxMigrateRecipes']);
        add_action('wp_ajax_kg_migrate_ingredients', [$this, 'ajaxMigrateIngredients']);
        add_action('wp_ajax_kg_migrate_posts', [$this, 'ajaxMigratePosts']);
        add_action('wp_ajax_kg_migrate_all_types', [$this, 'ajaxMigrateAll']);
        add_action('wp_ajax_kg_verify_migration', [$this, 'ajaxVerify']);
        add_action('wp_ajax_kg_rollback_migration', [$this, 'ajaxRollback']);
        add_action('wp_ajax_kg_get_table_status', [$this, 'ajaxGetTableStatus']);
        add_action('wp_ajax_kg_migrate_missing', [$this, 'ajaxMigrateMissing']);
        add_action('wp_ajax_kg_force_migrate_single', [$this, 'ajaxForceMigrateSingle']);
        add_action('wp_ajax_kg_force_migrate_missing', [$this, 'ajaxForceMigrateMissing']);
    }
    
    /**
     * Add admin menu page
     */
    public function addMenuPage() {
        add_submenu_page(
            'kg-core',
            'Data Migration',
            'Data Migration',
            'manage_options',
            'kg-data-migration',
            [$this, 'renderPage']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAssets($hook) {
        if ($hook !== 'kg-core_page_kg-data-migration') {
            return;
        }
        
        wp_enqueue_style(
            'kg-data-migration-css',
            KG_CORE_URL . 'assets/admin/css/data-migration.css',
            [],
            KG_CORE_VERSION
        );
        
        wp_enqueue_script(
            'kg-data-migration-js',
            KG_CORE_URL . 'assets/admin/js/data-migration.js',
            ['jquery'],
            KG_CORE_VERSION,
            true
        );
        
        wp_localize_script('kg-data-migration-js', 'kgDataMigration', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kg_data_migration_nonce')
        ]);
    }
    
    /**
     * Render admin page
     */
    public function renderPage() {
        $tableStatus = Schema::getTableStatus();
        
        // Get post counts efficiently
        $recipeCounts = wp_count_posts('recipe');
        $ingredientCounts = wp_count_posts('ingredient');
        $postCounts = wp_count_posts('post');
        
        $recipesCount = ($recipeCounts->publish ?? 0) + ($recipeCounts->draft ?? 0);
        $ingredientsCount = ($ingredientCounts->publish ?? 0) + ($ingredientCounts->draft ?? 0);
        $postsCount = ($postCounts->publish ?? 0) + ($postCounts->draft ?? 0);
        
        ?>
        <div class="wrap kg-data-migration-page">
            <h1>🔄 Data Migration - PostMeta to Custom Tables</h1>
            <p class="description">
                Mevcut wp_postmeta verilerini custom tablolara (kg_recipe_meta, kg_ingredient_meta, kg_post_meta) taşıyın.
                Bu işlem mevcut verileri korur ve yeni tablolara kopyalar.
            </p>
            
            <!-- Table Status Overview -->
            <div class="kg-status-section">
                <h2>Tablo Durumu</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Tablo</th>
                            <th>Durum</th>
                            <th>Kayıt Sayısı</th>
                            <th>Toplam Post</th>
                            <th>Tamamlanma</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>kg_recipe_meta</strong></td>
                            <td>
                                <?php if ($tableStatus['kg_recipe_meta']['exists']): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span> Mevcut
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss" style="color: red;"></span> Yok
                                <?php endif; ?>
                            </td>
                            <td id="recipe-count"><?php echo $tableStatus['kg_recipe_meta']['row_count']; ?></td>
                            <td><?php echo $recipesCount; ?></td>
                            <td>
                                <?php 
                                $percentage = $recipesCount > 0 ? round(($tableStatus['kg_recipe_meta']['row_count'] / $recipesCount) * 100) : 0;
                                echo $percentage . '%';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>kg_ingredient_meta</strong></td>
                            <td>
                                <?php if ($tableStatus['kg_ingredient_meta']['exists']): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span> Mevcut
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss" style="color: red;"></span> Yok
                                <?php endif; ?>
                            </td>
                            <td id="ingredient-count"><?php echo $tableStatus['kg_ingredient_meta']['row_count']; ?></td>
                            <td><?php echo $ingredientsCount; ?></td>
                            <td>
                                <?php 
                                $percentage = $ingredientsCount > 0 ? round(($tableStatus['kg_ingredient_meta']['row_count'] / $ingredientsCount) * 100) : 0;
                                echo $percentage . '%';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>kg_post_meta</strong></td>
                            <td>
                                <?php if ($tableStatus['kg_post_meta']['exists']): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span> Mevcut
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss" style="color: red;"></span> Yok
                                <?php endif; ?>
                            </td>
                            <td id="post-count"><?php echo $tableStatus['kg_post_meta']['row_count']; ?></td>
                            <td><?php echo $postsCount; ?></td>
                            <td>
                                <?php 
                                $percentage = $postsCount > 0 ? round(($tableStatus['kg_post_meta']['row_count'] / $postsCount) * 100) : 0;
                                echo $percentage . '%';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <button type="button" id="kg-refresh-status" class="button" style="margin-top: 10px;">
                    <span class="dashicons dashicons-update"></span> Durumu Yenile
                </button>
            </div>
            
            <!-- Migration Actions -->
            <div class="kg-actions-section">
                <h2>Migration İşlemleri</h2>
                
                <div class="kg-action-grid">
                    <div class="kg-action-card">
                        <h3>📖 Tarifleri Migrate Et</h3>
                        <p>Recipe post type'ına ait tüm postmeta verilerini kg_recipe_meta tablosuna taşır.</p>
                        <button type="button" class="button button-primary kg-migrate-btn" data-type="recipes">
                            Tarifleri Migrate Et
                        </button>
                        <button type="button" class="button button-secondary kg-force-migrate-btn" data-type="recipe">
                            🔧 Eksikleri Zorla Migrate Et
                        </button>
                    </div>
                    
                    <div class="kg-action-card">
                        <h3>🥕 Malzemeleri Migrate Et</h3>
                        <p>Ingredient post type'ına ait tüm postmeta verilerini kg_ingredient_meta tablosuna taşır.</p>
                        <button type="button" class="button button-primary kg-migrate-btn" data-type="ingredients">
                            Malzemeleri Migrate Et
                        </button>
                        <button type="button" class="button button-secondary kg-force-migrate-btn" data-type="ingredient">
                            🔧 Eksikleri Zorla Migrate Et
                        </button>
                    </div>
                    
                    <div class="kg-action-card">
                        <h3>📝 Blog Postlarını Migrate Et</h3>
                        <p>Post type'ına ait tüm postmeta verilerini kg_post_meta tablosuna taşır.</p>
                        <button type="button" class="button button-primary kg-migrate-btn" data-type="posts">
                            Postları Migrate Et
                        </button>
                        <button type="button" class="button button-secondary kg-force-migrate-btn" data-type="post">
                            🔧 Eksikleri Zorla Migrate Et
                        </button>
                    </div>
                    
                    <div class="kg-action-card">
                        <h3>🚀 Tümünü Migrate Et</h3>
                        <p>Tüm post tiplerini (recipe, ingredient, post) tek seferde migrate eder.</p>
                        <button type="button" class="button button-primary button-large kg-migrate-btn" data-type="all">
                            Tümünü Migrate Et
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Verification & Rollback -->
            <div class="kg-verify-section">
                <h2>Doğrulama ve Geri Alma</h2>
                
                <div class="kg-action-grid">
                    <div class="kg-action-card">
                        <h3>🔍 Migration Doğrula</h3>
                        <p>Eksik kayıtları kontrol eder ve listeler.</p>
                        <select id="kg-verify-type" class="regular-text">
                            <option value="all">Tümü</option>
                            <option value="recipe">Sadece Tarifler</option>
                            <option value="ingredient">Sadece Malzemeler</option>
                            <option value="post">Sadece Postlar</option>
                        </select>
                        <button type="button" id="kg-verify-btn" class="button">
                            Doğrula
                        </button>
                    </div>
                    
                    <div class="kg-action-card kg-danger">
                        <h3>⚠️ Migration Geri Al (Rollback)</h3>
                        <p class="warning">
                            <strong>DİKKAT:</strong> Bu işlem seçilen tablodaki TÜM verileri siler! Geri alınamaz!
                        </p>
                        <select id="kg-rollback-type" class="regular-text">
                            <option value="">Tablo Seçin</option>
                            <option value="recipe">Tarifler (kg_recipe_meta)</option>
                            <option value="ingredient">Malzemeler (kg_ingredient_meta)</option>
                            <option value="post">Postlar (kg_post_meta)</option>
                            <option value="all">TÜMÜ (Tüm Tablolar)</option>
                        </select>
                        <button type="button" id="kg-rollback-btn" class="button button-secondary" disabled>
                            Geri Al
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Results Area -->
            <div id="kg-migration-results" class="kg-results-area" style="display:none;">
                <h3>İşlem Sonuçları</h3>
                <div id="kg-results-content"></div>
            </div>
            
            <!-- Log Area -->
            <div id="kg-migration-log" class="kg-log-area" style="display:none;">
                <h3>İşlem Logu</h3>
                <div id="kg-log-content"></div>
            </div>
        </div>
        
        <style>
            .kg-data-migration-page {
                max-width: 1200px;
            }
            
            .kg-status-section,
            .kg-actions-section,
            .kg-verify-section {
                background: #fff;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            
            .kg-action-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            
            .kg-action-card {
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #f9f9f9;
            }
            
            .kg-action-card.kg-danger {
                border-color: #dc3232;
                background: #fff8f8;
            }
            
            .kg-action-card h3 {
                margin-top: 0;
                font-size: 16px;
            }
            
            .kg-action-card p {
                font-size: 14px;
                color: #666;
            }
            
            .kg-action-card .warning {
                color: #dc3232;
            }
            
            .kg-action-card button {
                margin-top: 10px;
            }
            
            .kg-action-card .kg-force-migrate-btn {
                display: block;
            }
            
            .kg-results-area,
            .kg-log-area {
                background: #fff;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #ccd0d4;
            }
            
            #kg-results-content,
            #kg-log-content {
                font-family: monospace;
                background: #f5f5f5;
                padding: 15px;
                border-radius: 4px;
                max-height: 400px;
                overflow-y: auto;
            }
            
            .success-message {
                color: #46b450;
                font-weight: bold;
            }
            
            .error-message {
                color: #dc3232;
                font-weight: bold;
            }
            
            .info-message {
                color: #0073aa;
            }
        </style>
        <?php
    }
    
    /**
     * AJAX: Migrate recipes
     */
    public function ajaxMigrateRecipes() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        set_time_limit(300);
        
        $result = DataMigration::migrateRecipes();
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Migrate ingredients
     */
    public function ajaxMigrateIngredients() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        set_time_limit(300);
        
        $result = DataMigration::migrateIngredients();
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Migrate posts
     */
    public function ajaxMigratePosts() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        set_time_limit(300);
        
        $result = DataMigration::migratePosts();
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Migrate all types
     */
    public function ajaxMigrateAll() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        set_time_limit(600);
        
        $result = DataMigration::migrateAll();
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Verify migration
     */
    public function ajaxVerify() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        
        $result = DataMigration::verify($type);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Rollback migration
     */
    public function ajaxRollback() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        
        if (empty($type)) {
            wp_send_json_error('Geçersiz tablo tipi.');
        }
        
        DataMigration::rollback($type);
        
        wp_send_json_success([
            'message' => 'Rollback başarılı.'
        ]);
    }
    
    /**
     * AJAX: Get table status
     */
    public function ajaxGetTableStatus() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        $status = Schema::getTableStatus();
        
        wp_send_json_success($status);
    }
    
    /**
     * AJAX: Migrate missing records
     */
    public function ajaxMigrateMissing() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
        
        if (empty($type) || empty($post_ids)) {
            wp_send_json_error('Geçersiz parametreler.');
        }
        
        set_time_limit(300);
        
        $result = DataMigration::forceMigrateMultiple($post_ids, $type);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Force migrate single post
     */
    public function ajaxForceMigrateSingle() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (empty($type) || empty($post_id)) {
            wp_send_json_error('Geçersiz parametreler.');
        }
        
        $result = DataMigration::forceMigrate($post_id, $type);
        
        if ($result) {
            wp_send_json_success(['message' => 'Migration başarılı.']);
        } else {
            wp_send_json_error('Migration başarısız.');
        }
    }
    
    /**
     * AJAX: Force migrate all missing records
     */
    public function ajaxForceMigrateMissing() {
        check_ajax_referer('kg_data_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        
        if (!in_array($type, ['recipe', 'ingredient', 'post'])) {
            wp_send_json_error('Invalid type');
        }
        
        set_time_limit(300);
        
        $result = DataMigration::forceMigrateMissing($type);
        
        wp_send_json_success($result);
    }
}
