<?php
namespace KG_Core\Migration;

class ExpertMigrator {
    
    // Bilinen uzman eşleştirmeleri (name pattern => user_id)
    private $knownExperts = [
        'Enver Mahir' => 13,        // Doç. Dr. Enver Mahir Gülcan
        'Gülcan' => 13,
        'Çiğdem Ünver' => 10,       // Fzt. Çiğdem Ünver
        'Cigdem Unver' => 10,
        'Deniz Özkılıç' => 14,      // Dr. Psikolog Deniz Özkılıç Kabul
        'Deniz Ozkilic' => 14,
        'Bengi Semerci' => 15,      // Prof. Dr. Bengi Semerci
        'Ayse Nil' => 19,           // Şef Ayse Nil Dinler
        'Ayşe Nil' => 19,
        'Figen Fişekçi' => 20,      // Dyt. Figen Fişekçi Üvez
        'Figen Fisekci' => 20,
        'Serdar Kula' => 21,        // Prof. Dr. Serdar Kula
    ];
    
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'addAdminMenu' ] );
        add_action( 'wp_ajax_kg_migrate_experts', [ $this, 'ajaxMigrate' ] );
        add_action( 'wp_ajax_kg_preview_expert_migration', [ $this, 'ajaxPreview' ] );
    }
    
    public function addAdminMenu() {
        add_submenu_page(
            'kg-core-settings',
            'Uzman Migration',
            'Uzman Migration',
            'manage_options',
            'kg-expert-migration',
            [ $this, 'renderPage' ]
        );
    }
    
    public function renderPage() {
        ?>
        <div class="wrap">
            <h1>Uzman Migration</h1>
            <p>Bu araç, tariflerdeki manuel girilen uzman adlarını kayıtlı WordPress kullanıcılarıyla eşleştirir.</p>
            
            <h2>Önizleme</h2>
            <button id="kg-preview-migration" class="button">Eşleşmeleri Önizle</button>
            <div id="kg-preview-results" style="margin-top: 20px;"></div>
            
            <h2>Migration</h2>
            <button id="kg-run-migration" class="button button-primary" disabled>Migration Çalıştır</button>
            <div id="kg-migration-results" style="margin-top: 20px;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#kg-preview-migration').click(function() {
                $(this).prop('disabled', true).text('Yükleniyor...');
                $.post(ajaxurl, { action: 'kg_preview_expert_migration' }, function(response) {
                    $('#kg-preview-results').html(response.data.html);
                    $('#kg-run-migration').prop('disabled', false);
                    $('#kg-preview-migration').prop('disabled', false).text('Eşleşmeleri Önizle');
                });
            });
            
            $('#kg-run-migration').click(function() {
                if (!confirm('Migration çalıştırılsın mı?')) return;
                $(this).prop('disabled', true).text('Çalışıyor...');
                $.post(ajaxurl, { action: 'kg_migrate_experts' }, function(response) {
                    $('#kg-migration-results').html(response.data.html);
                    $('#kg-run-migration').text('Tamamlandı');
                });
            });
        });
        </script>
        <?php
    }
    
    public function ajaxPreview() {
        $results = $this->analyze();
        
        $html = '<table class="widefat"><thead><tr>';
        $html .= '<th>Tarif ID</th><th>Tarif</th><th>Mevcut Uzman Adı</th><th>Eşleşen User</th><th>User ID</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ( $results['matched'] as $item ) {
            $html .= sprintf(
                '<tr style="background:#d4edda;"><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%d</td></tr>',
                $item['recipe_id'],
                esc_html( $item['recipe_title'] ),
                esc_html( $item['expert_name'] ),
                esc_html( $item['matched_user_name'] ),
                $item['matched_user_id']
            );
        }
        
        foreach ( $results['unmatched'] as $item ) {
            $html .= sprintf(
                '<tr style="background:#f8d7da;"><td>%d</td><td>%s</td><td>%s</td><td>Eşleşme yok</td><td>-</td></tr>',
                $item['recipe_id'],
                esc_html( $item['recipe_title'] ),
                esc_html( $item['expert_name'] )
            );
        }
        
        $html .= '</tbody></table>';
        $html .= sprintf( '<p><strong>Eşleşen:</strong> %d | <strong>Eşleşmeyen:</strong> %d</p>', 
            count( $results['matched'] ), count( $results['unmatched'] ) );
        
        wp_send_json_success( [ 'html' => $html ] );
    }
    
    public function ajaxMigrate() {
        $results = $this->migrate();
        
        $html = sprintf(
            '<div class="notice notice-success"><p>Migration tamamlandı! <strong>%d</strong> tarif güncellendi.</p></div>',
            $results['updated']
        );
        
        wp_send_json_success( [ 'html' => $html ] );
    }
    
    public function analyze() {
        $recipes = get_posts( [
            'post_type' => 'recipe',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_kg_expert_name',
                    'value' => '',
                    'compare' => '!='
                ]
            ]
        ] );
        
        $matched = [];
        $unmatched = [];
        
        foreach ( $recipes as $recipe ) {
            $expert_name = get_post_meta( $recipe->ID, '_kg_expert_name', true );
            $existing_user_id = get_post_meta( $recipe->ID, '_kg_expert_user_id', true );
            
            // Zaten eşleşmiş mi?
            if ( ! empty( $existing_user_id ) ) {
                continue;
            }
            
            $user_id = $this->findUserByName( $expert_name );
            
            if ( $user_id ) {
                $user = get_user_by( 'ID', $user_id );
                $matched[] = [
                    'recipe_id' => $recipe->ID,
                    'recipe_title' => $recipe->post_title,
                    'expert_name' => $expert_name,
                    'matched_user_id' => $user_id,
                    'matched_user_name' => $user->display_name,
                ];
            } else {
                $unmatched[] = [
                    'recipe_id' => $recipe->ID,
                    'recipe_title' => $recipe->post_title,
                    'expert_name' => $expert_name,
                ];
            }
        }
        
        return [ 'matched' => $matched, 'unmatched' => $unmatched ];
    }
    
    public function migrate() {
        $analysis = $this->analyze();
        $updated = 0;
        
        foreach ( $analysis['matched'] as $item ) {
            update_post_meta( $item['recipe_id'], '_kg_expert_user_id', $item['matched_user_id'] );
            $updated++;
        }
        
        return [ 'updated' => $updated ];
    }
    
    private function findUserByName( $name ) {
        // 1. Bilinen eşleştirmelerden ara
        foreach ( $this->knownExperts as $pattern => $user_id ) {
            if ( stripos( $name, $pattern ) !== false ) {
                return $user_id;
            }
        }
        
        // 2. Display name ile tam eşleşme
        $users = get_users( [ 'role' => 'kg_expert' ] );
        foreach ( $users as $user ) {
            if ( strcasecmp( $user->display_name, $name ) === 0 ) {
                return $user->ID;
            }
        }
        
        // 3. Benzerlik skoru ile ara (70%+ eşleşme)
        foreach ( $users as $user ) {
            similar_text( strtolower( $name ), strtolower( $user->display_name ), $percent );
            if ( $percent >= 70 ) {
                return $user->ID;
            }
        }
        
        return null;
    }
}
