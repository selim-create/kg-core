<?php
namespace KG_Core\Admin;

use KG_Core\Migration\StainMigration;

/**
 * StainMigrationPage - Admin UI for stain data migration
 */
class StainMigrationPage {
    
    private $migration;
    
    public function __construct() {
        $this->migration = new StainMigration();
        
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_post_kg_run_stain_migration', [ $this, 'handle_migration' ] );
    }
    
    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'kg-core',
            'Leke Migration',
            'Leke Migration',
            'manage_options',
            'kg-stain-migration',
            [ $this, 'render_page' ]
        );
    }
    
    /**
     * Render admin page
     */
    public function render_page() {
        $has_run = $this->migration->has_run();
        
        // Count existing stains
        $stain_count = wp_count_posts( 'stain' );
        $published_stains = isset( $stain_count->publish ) ? $stain_count->publish : 0;
        
        ?>
        <div class="wrap">
            <h1>🧺 Leke Ansiklopedisi Migration</h1>
            <p class="description">
                Hardcoded leke verilerini WordPress Custom Post Type'a aktarın.
                40+ leke kaydı içerik, meta veriler ve kategorileriyle birlikte import edilecek.
            </p>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h2>Migration Durumu</h2>
                
                <?php if ( $has_run ) : ?>
                    <div class="notice notice-success inline">
                        <p><strong>✅ Migration tamamlanmış!</strong></p>
                        <p>Toplam <?php echo $published_stains; ?> adet leke kaydı mevcut.</p>
                    </div>
                    
                    <p>
                        <a href="<?php echo admin_url( 'edit.php?post_type=stain' ); ?>" class="button button-primary">
                            📋 Leke Listesini Görüntüle
                        </a>
                    </p>
                    
                    <hr>
                    
                    <h3>Yeniden Çalıştırma</h3>
                    <p>
                        Migration'ı yeniden çalıştırmak için önce migration flag'ini silmeniz gerekir:
                    </p>
                    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" 
                          onsubmit="return confirm('Migration flag silinecek. Devam etmek istiyor musunuz?');">
                        <?php wp_nonce_field( 'kg_stain_migration', 'kg_stain_migration_nonce' ); ?>
                        <input type="hidden" name="action" value="kg_run_stain_migration">
                        <input type="hidden" name="reset_flag" value="1">
                        <button type="submit" class="button">🔄 Flag'i Sil ve Yeniden Başlat</button>
                    </form>
                    
                <?php else : ?>
                    <div class="notice notice-warning inline">
                        <p><strong>⚠️ Migration henüz çalıştırılmadı</strong></p>
                        <p>Toplam <?php echo $published_stains; ?> adet leke kaydı mevcut.</p>
                    </div>
                    
                    <h3>Migration İşlemi</h3>
                    <p>
                        Bu işlem hardcoded array'deki 40+ leke kaydını CPT'ye aktaracak:
                    </p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>20 Yemek Lekesi (food)</li>
                        <li>8 Vücut Sıvısı Lekesi (bodily)</li>
                        <li>4 Dış Mekan Lekesi (outdoor)</li>
                        <li>4 Sanat/Oyun Lekesi (craft)</li>
                        <li>4 Ev İçi Leke (household)</li>
                    </ul>
                    
                    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" 
                          onsubmit="return confirm('Migration başlatılacak. Devam etmek istiyor musunuz?');">
                        <?php wp_nonce_field( 'kg_stain_migration', 'kg_stain_migration_nonce' ); ?>
                        <input type="hidden" name="action" value="kg_run_stain_migration">
                        <button type="submit" class="button button-primary button-large">
                            🚀 Migration'ı Başlat
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle migration form submission
     */
    public function handle_migration() {
        // Verify nonce
        if ( ! isset( $_POST['kg_stain_migration_nonce'] ) || 
             ! wp_verify_nonce( $_POST['kg_stain_migration_nonce'], 'kg_stain_migration' ) ) {
            wp_die( 'Security check failed' );
        }
        
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        // Reset flag if requested
        if ( isset( $_POST['reset_flag'] ) && $_POST['reset_flag'] === '1' ) {
            delete_option( 'kg_stain_migration_completed' );
            wp_redirect( add_query_arg( 'page', 'kg-stain-migration', admin_url( 'admin.php' ) ) );
            exit;
        }
        
        // Run migration
        $result = $this->migration->run();
        
        if ( $result['success'] ) {
            $message = sprintf(
                'Migration tamamlandı! %d/%d leke kaydı import edildi.',
                $result['imported'],
                $result['total']
            );
            
            if ( ! empty( $result['errors'] ) ) {
                $message .= ' Hatalar: ' . implode( ', ', $result['errors'] );
            }
            
            wp_redirect( add_query_arg( [
                'page' => 'kg-stain-migration',
                'message' => urlencode( $message ),
            ], admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( [
                'page' => 'kg-stain-migration',
                'error' => urlencode( $result['message'] ),
            ], admin_url( 'admin.php' ) ) );
        }
        exit;
    }
}
