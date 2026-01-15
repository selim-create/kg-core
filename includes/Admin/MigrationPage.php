<?php
namespace KG_Core\Admin;

use KG_Core\Migration\AIRecipeMigrator;
use KG_Core\Migration\MigrationLogger;
use KG_Core\Migration\FieldConsolidation;

/**
 * MigrationPage - Admin UI for recipe migration and field consolidation
 */
class MigrationPage {
    
    private $migrator;
    private $logger;
    private $fieldConsolidation;
    
    public function __construct() {
        $this->migrator = new AIRecipeMigrator();
        $this->logger = new MigrationLogger();
        $this->fieldConsolidation = new FieldConsolidation();
        
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_kg_migrate_single', [$this, 'ajaxMigrateSingle']);
        add_action('wp_ajax_kg_migrate_batch', [$this, 'ajaxMigrateBatch']);
        add_action('wp_ajax_kg_migrate_all', [$this, 'ajaxMigrateAll']);
        add_action('wp_ajax_kg_migration_status', [$this, 'ajaxGetStatus']);
        add_action('wp_ajax_kg_clean_test_migrations', [$this, 'ajaxCleanTestMigrations']);
        add_action('wp_ajax_kg_preview_field_consolidation', [$this, 'ajaxPreviewFieldConsolidation']);
        add_action('wp_ajax_kg_run_field_consolidation', [$this, 'ajaxRunFieldConsolidation']);
    }
    
    /**
     * Add admin menu page
     */
    public function addMenuPage() {
        add_menu_page(
            'Tarif Migration',
            'Tarif Migration',
            'manage_options',
            'kg-recipe-migration',
            [$this, 'renderPage'],
            'dashicons-update',
            30
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAssets($hook) {
        if ($hook !== 'toplevel_page_kg-recipe-migration') {
            return;
        }
        
        wp_enqueue_style(
            'kg-migration-css',
            KG_CORE_URL . 'assets/admin/css/migration.css',
            [],
            KG_CORE_VERSION
        );
        
        wp_enqueue_script(
            'kg-migration-js',
            KG_CORE_URL . 'assets/admin/js/migration.js',
            ['jquery'],
            KG_CORE_VERSION,
            true
        );
        
        wp_localize_script('kg-migration-js', 'kgMigration', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kg_migration_nonce')
        ]);
    }
    
    /**
     * Render admin page
     */
    public function renderPage() {
        $status = $this->logger->getStatus();
        $totalCount = $this->logger->getTotalCount();
        $recentMigrations = $this->logger->getRecentMigrations(10);
        $failedMigrations = $this->logger->getFailedMigrations(10);
        
        // Exclude test migrations from success count
        $testMigrationsCount = $this->getTestMigrationsCount();
        $realSuccessCount = $status['success'] - $testMigrationsCount;
        
        $pendingCount = $totalCount - $status['success'] - $status['failed'];
        
        ?>
        <div class="wrap kg-migration-page">
            <h1>📋 Tarif Migration Sistemi - AI-First</h1>
            <p class="description">
                Blog'dan 337 adet tarifi yeni "recipe" post type'ına aktarın. 
                <strong>Sistem OpenAI GPT-4 ile tüm içeriği tek seferde parse eder.</strong>
                Malzemeler, hazırlanış adımları, uzman notu, beslenme değerleri ve daha fazlası otomatik çıkarılır.
            </p>
            
            <!-- Status Overview -->
            <div class="kg-status-cards">
                <div class="kg-status-card kg-total">
                    <h3>Toplam</h3>
                    <div class="number"><?php echo $totalCount; ?></div>
                    <p>Taşınacak Tarif</p>
                </div>
                
                <div class="kg-status-card kg-success">
                    <h3>Başarılı</h3>
                    <div class="number"><?php echo $realSuccessCount; ?></div>
                    <p>Taşınan Tarif</p>
                    <?php if ($testMigrationsCount > 0): ?>
                        <small style="color: #999;">+ <?php echo $testMigrationsCount; ?> test</small>
                    <?php endif; ?>
                </div>
                
                <div class="kg-status-card kg-pending">
                    <h3>Bekleyen</h3>
                    <div class="number"><?php echo $pendingCount; ?></div>
                    <p>Henüz Taşınmadı</p>
                </div>
                
                <div class="kg-status-card kg-failed">
                    <h3>Hatalı</h3>
                    <div class="number"><?php echo $status['failed']; ?></div>
                    <p>Hata Oluştu</p>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="kg-actions">
                <h2>İşlem Seçin</h2>
                
                <div class="kg-action-section">
                    <h3>🧪 Tek Tarif Test</h3>
                    <p>Test için tek bir tarif ID'si girerek işlemi deneyebilirsiniz.</p>
                    <div class="kg-action-form">
                        <input 
                            type="number" 
                            id="kg-single-id" 
                            placeholder="Post ID (örn: 6490)" 
                            class="regular-text"
                        >
                        <button type="button" id="kg-migrate-single" class="button button-primary">
                            Testi Başlat
                        </button>
                    </div>
                </div>
                
                <div class="kg-action-section">
                    <h3>📦 Batch İşlem</h3>
                    <p>10'ar 10'ar migration yapın. Her batch'te 10 tarif işlenir.</p>
                    <button type="button" id="kg-migrate-batch" class="button button-primary button-large">
                        ▶ 10 Tarif İşle
                    </button>
                </div>
                
                <div class="kg-action-section">
                    <h3>🚀 Toplu İşlem</h3>
                    <p class="warning">
                        <strong>⚠️ Uyarı:</strong> Bu işlem tüm tarifleri sırayla işler. 
                        Bu uzun sürebilir (yaklaşık <?php echo ceil($totalCount / 60); ?> saat).
                    </p>
                    <button type="button" id="kg-migrate-all" class="button button-secondary button-large">
                        ▶ Tümünü İşle (<?php echo $pendingCount; ?> tarif)
                    </button>
                </div>
                
                <?php if ($testMigrationsCount > 0): ?>
                <div class="kg-action-section">
                    <h3>🧹 Test Verilerini Temizle</h3>
                    <p>Test modunda oluşturulmuş <?php echo $testMigrationsCount; ?> tarif var.</p>
                    <button type="button" id="kg-clean-test" class="button button-secondary">
                        🗑️ Test Tariflerini Sil
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Ingredient Field Consolidation -->
            <div class="kg-actions" style="margin-top: 40px; border-top: 3px solid #2271b1; padding-top: 30px;">
                <h2>🧬 Malzeme (Ingredient) Alan Birleştirme</h2>
                <p class="description">
                    Ingredient CPT'daki mükerrer alanları birleştir ve eski verileri yeni formata taşı.
                    Bu işlem kategori, besin değerleri ve diğer mükerrer alanları temizler.
                </p>
                
                <div class="kg-action-section">
                    <h3>🔍 Önizleme</h3>
                    <p>Migrasyon etkilerini görüntüleyin (değişiklik yapmaz).</p>
                    <button type="button" id="kg-preview-consolidation" class="button button-secondary">
                        Önizleme Yap
                    </button>
                </div>
                
                <div id="kg-consolidation-preview" style="display:none; margin: 20px 0; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                    <!-- Preview results will be inserted here -->
                </div>
                
                <div class="kg-action-section">
                    <h3>▶️ Migrasyon Çalıştır</h3>
                    <p class="warning">
                        <strong>⚠️ Uyarı:</strong> Bu işlem tüm ingredient kayıtlarını güncelleyecek.
                        Eski alanlar temizlenecek ve veriler yeni alanlara taşınacak.
                    </p>
                    <button type="button" id="kg-run-consolidation" class="button button-primary button-large">
                        🚀 Migrasyon Başlat
                    </button>
                </div>
                
                <div id="kg-consolidation-result" style="display:none; margin: 20px 0; padding: 15px;">
                    <!-- Migration results will be inserted here -->
                </div>
            </div>
            
            <!-- Progress/Result Area -->
            <div id="kg-migration-result" class="kg-result-area" style="display:none;">
                <h3>İşlem Durumu</h3>
                <div id="kg-migration-output"></div>
            </div>
            
            <!-- Recent Migrations Log -->
            <div class="kg-log-section">
                <h2>Son İşlemler</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Blog Post ID</th>
                            <th>Recipe Post ID</th>
                            <th>Durum</th>
                            <th>Başlangıç</th>
                            <th>Bitiş</th>
                            <th>Hata</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentMigrations)): ?>
                            <?php foreach ($recentMigrations as $log): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo get_edit_post_link($log['blog_post_id']); ?>" target="_blank">
                                            <?php echo $log['blog_post_id']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($log['recipe_post_id']): ?>
                                            <a href="<?php echo get_edit_post_link($log['recipe_post_id']); ?>" target="_blank">
                                                <?php echo $log['recipe_post_id']; ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($log['status']); ?>">
                                            <?php echo ucfirst($log['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $log['started_at']; ?></td>
                                    <td><?php echo $log['completed_at'] ?? '—'; ?></td>
                                    <td><?php echo $log['error_message'] ? esc_html(substr($log['error_message'], 0, 100)) : '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">Henüz işlem yapılmadı.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Failed Migrations -->
            <?php if (!empty($failedMigrations)): ?>
                <div class="kg-log-section">
                    <h2>Hatalı İşlemler</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Blog Post ID</th>
                                <th>Hata Mesajı</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failedMigrations as $log): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo get_edit_post_link($log['blog_post_id']); ?>" target="_blank">
                                            <?php echo $log['blog_post_id']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($log['error_message']); ?></td>
                                    <td><?php echo $log['completed_at']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * AJAX: Migrate single post
     */
    public function ajaxMigrateSingle() {
        check_ajax_referer('kg_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$postId) {
            wp_send_json_error('Geçersiz post ID.');
        }
        
        set_time_limit(300); // 5 minutes
        
        $result = $this->migrator->migrate($postId);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Get debug info from logger metadata
        $logEntry = $this->logger->getLogEntry($postId);
        $metadata = !empty($logEntry['metadata']) ? json_decode($logEntry['metadata'], true) : [];
        
        $debugInfo = [
            'ingredients_count' => isset($metadata['ingredients_count']) ? $metadata['ingredients_count'] : 0,
            'instructions_count' => isset($metadata['instructions_count']) ? $metadata['instructions_count'] : 0,
            'has_expert_note' => isset($metadata['has_expert_note']) ? $metadata['has_expert_note'] : false,
            'age_group' => isset($metadata['age_group']) ? $metadata['age_group'] : 'none',
            'ai_enhanced' => isset($metadata['ai_enhanced']) ? $metadata['ai_enhanced'] : false,
        ];
        
        wp_send_json_success([
            'recipe_id' => $result,
            'message' => "Tarif başarıyla oluşturuldu! Recipe ID: {$result}",
            'debug' => $debugInfo
        ]);
    }
    
    /**
     * AJAX: Migrate batch
     */
    public function ajaxMigrateBatch() {
        check_ajax_referer('kg_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        set_time_limit(600); // 10 minutes
        
        $results = $this->migrator->migrateBatch(10);
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Migrate all
     */
    public function ajaxMigrateAll() {
        check_ajax_referer('kg_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        set_time_limit(0); // No limit
        
        $results = $this->migrator->migrateAll();
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Get status
     */
    public function ajaxGetStatus() {
        check_ajax_referer('kg_migration_nonce', 'nonce');
        
        $status = $this->logger->getStatus();
        $totalCount = $this->logger->getTotalCount();
        
        wp_send_json_success([
            'status' => $status,
            'total' => $totalCount
        ]);
    }
    
    /**
     * AJAX: Clean test migrations
     */
    public function ajaxCleanTestMigrations() {
        check_ajax_referer('kg_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        $result = $this->migrator->cleanTestMigrations();
        
        wp_send_json_success($result);
    }
    
    /**
     * Get count of test migrations
     * 
     * @return int Test migrations count
     */
    private function getTestMigrationsCount() {
        $testRecipes = get_posts([
            'post_type' => 'recipe',
            'meta_key' => '_kg_migrated_test',
            'meta_value' => '1',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids'
        ]);
        
        return count($testRecipes);
    }
    
    /**
     * AJAX: Preview field consolidation
     */
    public function ajaxPreviewFieldConsolidation() {
        check_ajax_referer('kg_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        $preview = $this->fieldConsolidation->preview();
        
        wp_send_json_success($preview);
    }
    
    /**
     * AJAX: Run field consolidation
     */
    public function ajaxRunFieldConsolidation() {
        check_ajax_referer('kg_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
        }
        
        set_time_limit(300); // 5 minutes
        
        $results = $this->fieldConsolidation->run();
        
        wp_send_json_success($results);
    }
}
