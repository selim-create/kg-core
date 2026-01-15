<?php
namespace KG_Core\Admin;

class ToolSeeder {
    
    private $tools_data = [];
    
    public function __construct() {
        $this->init_tools_data();
        add_action('admin_menu', [$this, 'add_seeder_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_kg_seed_tool', [$this, 'ajax_seed_tool']);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our seeder page
        if ($hook !== 'tool_page_kg-tool-seeder') {
            return;
        }
        
        // Enqueue jQuery (already included in WordPress)
        wp_enqueue_script('jquery');
        
        // Register and enqueue our custom script handle
        wp_register_script('kg-tool-seeder-js', '', ['jquery'], KG_CORE_VERSION, true);
        wp_enqueue_script('kg-tool-seeder-js');
        
        // Pass data to JavaScript
        wp_localize_script('kg-tool-seeder-js', 'kgToolSeeder', [
            'nonce' => wp_create_nonce('kg_tool_seed'),
            'ajaxurl' => admin_url('admin-ajax.php'),
        ]);
    }
    
    /**
     * Initialize tools data
     */
    private function init_tools_data() {
        $this->tools_data = [
            [
                'title' => 'Banyo Rutini Planlayıcı',
                'slug' => 'bath-planner',
                'description' => 'Bebeğiniz için mevsime göre ideal banyo sıklığını ve rutinini planlayın.',
                'tool_type' => 'bath_planner',
                'icon' => 'fa-bath',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Günlük Hijyen İhtiyacı Hesaplayıcı',
                'slug' => 'hygiene-calculator',
                'description' => 'Bebeğinizin yaşına ve aktivitesine göre günlük mendil ve hijyen ürünü ihtiyacını hesaplayın.',
                'tool_type' => 'hygiene_calculator',
                'icon' => 'fa-hand-sparkles',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Akıllı Bez Hesaplayıcı',
                'slug' => 'diaper-calculator',
                'description' => 'Bebeğinizin yaş ve kilosuna göre günlük bez ihtiyacını hesaplayın ve pişik riskini değerlendirin.',
                'tool_type' => 'diaper_calculator',
                'icon' => 'fa-baby',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Hava Kalitesi Rehberi',
                'slug' => 'air-quality',
                'description' => 'Güncel hava kalitesine göre bebeğiniz için dış mekan aktivitesi önerileri alın.',
                'tool_type' => 'air_quality_guide',
                'icon' => 'fa-wind',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Leke Ansiklopedisi',
                'slug' => 'stain-encyclopedia',
                'description' => 'Bebek kıyafetlerindeki lekeleri nasıl çıkaracağınızı öğrenin.',
                'tool_type' => 'stain_encyclopedia',
                'icon' => 'fa-tshirt',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'BLW Hazırlık Testi',
                'slug' => 'blw-testi',
                'description' => 'Bebeğiniz Baby-Led Weaning\'e hazır mı? Hemen test edin!',
                'tool_type' => 'blw_test',
                'icon' => 'fa-baby',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Persentil Hesaplayıcı',
                'slug' => 'persentil',
                'description' => 'Bebeğinizin boy ve kilo persentilini WHO standartlarına göre hesaplayın.',
                'tool_type' => 'percentile',
                'icon' => 'fa-chart-line',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Su İhtiyacı Hesaplayıcı',
                'slug' => 'su-ihtiyaci',
                'description' => 'Bebeğinizin günlük su ihtiyacını yaş ve beslenme şekline göre hesaplayın.',
                'tool_type' => 'water_calculator',
                'icon' => 'fa-glass-water',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Ek Gıda Rehberi',
                'slug' => 'ek-gida-rehberi',
                'description' => 'Ek gıdaya geçiş sürecinde adım adım rehberlik.',
                'tool_type' => 'food_guide',
                'icon' => 'fa-carrot',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Ek Gıdaya Başlama Kontrolü',
                'slug' => 'ek-gidaya-baslama',
                'description' => 'Bebeğiniz ek gıdaya başlamaya hazır mı? Kontrol edin.',
                'tool_type' => 'solid_food_readiness',
                'icon' => 'fa-utensils',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Bu Gıda Verilir mi?',
                'slug' => 'bu-gida-verilir-mi',
                'description' => 'Bebeğinizin yaşına göre hangi gıdaları verebileceğinizi öğrenin.',
                'tool_type' => 'food_checker',
                'icon' => 'fa-check-circle',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Alerjen Deneme Planlayıcı',
                'slug' => 'alerjen-planlayici',
                'description' => 'Alerjen besinleri güvenli şekilde tanıtmak için plan yapın.',
                'tool_type' => 'allergen_planner',
                'icon' => 'fa-shield-heart',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
            [
                'title' => 'Besin Deneme Takvimi',
                'slug' => 'besin-takvimi',
                'description' => 'Yeni besinleri tanıtırken takip edin ve kayıt tutun.',
                'tool_type' => 'food_trial_calendar',
                'icon' => 'fa-calendar-check',
                'is_active' => true,
                'requires_auth' => false,
                'is_sponsored' => false,
            ],
        ];
    }
    
    /**
     * Add seeder page to admin menu
     */
    public function add_seeder_page() {
        add_submenu_page(
            'edit.php?post_type=tool',
            '🛠️ Araçları Oluştur',
            '🛠️ Araçları Oluştur',
            'manage_options',
            'kg-tool-seeder',
            [$this, 'render_seeder_page']
        );
    }
    
    /**
     * Render seeder page
     */
    public function render_seeder_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Bu sayfaya erişim yetkiniz yok.');
        }
        
        // Get current tool status
        $tool_status = $this->get_tools_status();
        
        ?>
        <div class="wrap">
            <h1>🛠️ Araç Seeder</h1>
            <p>WordPress admin panelinde "Araçlar" (tool) post type'ı için otomatik seed/migration sistemi.</p>
            
            <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin: 20px 0;">
                <strong>ℹ️ Bilgi:</strong>
                <ul>
                    <li>Zaten var olan araçlar atlanır (duplicate kontrolü)</li>
                    <li>Oluşturulan araçlar <strong>yayınlanmış</strong> (published) olarak kaydedilir</li>
                    <li>Her araç için gerekli meta veriler otomatik eklenir</li>
                    <li>Sponsorlu olmayan araçlar <code>sponsor_data: null</code> döner</li>
                </ul>
            </div>
            
            <!-- Tool Status Table -->
            <h2>📊 Araç Durumu</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Araç Adı</th>
                        <th>Slug</th>
                        <th>Tool Type</th>
                        <th>İkon</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->tools_data as $tool_data): ?>
                        <?php 
                        $status = $tool_status[$tool_data['slug']] ?? null;
                        $exists = !empty($status);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($tool_data['title']); ?></strong></td>
                            <td><code><?php echo esc_html($tool_data['slug']); ?></code></td>
                            <td><code><?php echo esc_html($tool_data['tool_type']); ?></code></td>
                            <td><i class="fa <?php echo esc_attr($tool_data['icon']); ?>"></i> <?php echo esc_html($tool_data['icon']); ?></td>
                            <td>
                                <?php if ($exists): ?>
                                    <span style="color: green;">✓ Mevcut (ID: <?php echo $status['id']; ?>)</span>
                                <?php else: ?>
                                    <span style="color: orange;">⚠ Yok</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($exists): ?>
                                    <a href="<?php echo get_edit_post_link($status['id']); ?>" class="button button-small">Düzenle</a>
                                    <button type="button" class="button button-small kg-seed-single-tool" 
                                            data-slug="<?php echo esc_attr($tool_data['slug']); ?>"
                                            data-mode="update">Güncelle</button>
                                <?php else: ?>
                                    <button type="button" class="button button-primary button-small kg-seed-single-tool" 
                                            data-slug="<?php echo esc_attr($tool_data['slug']); ?>"
                                            data-mode="create">Oluştur</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Bulk Actions -->
            <h2>🚀 Toplu İşlemler</h2>
            <div style="background: white; padding: 20px; border: 1px solid #ccc;">
                <p>
                    <button type="button" class="button button-primary button-large" id="kg-seed-all-tools">
                        Tüm Araçları Oluştur (Eksik Olanlar)
                    </button>
                    <button type="button" class="button button-secondary button-large" id="kg-update-all-tools">
                        Tüm Araçları Güncelle (Mevcut Olanlar)
                    </button>
                </p>
            </div>
            
            <!-- Progress Section -->
            <div id="kg-progress-section" style="margin-top: 30px; display: none;">
                <h2>⏳ İlerleme</h2>
                <div style="background: white; padding: 20px; border: 1px solid #ccc;">
                    <div style="margin-bottom: 15px;">
                        <strong>İlerleme: <span id="kg-progress-text">0/0</span></strong>
                        <div style="background: #e0e0e0; height: 30px; border-radius: 5px; overflow: hidden; margin-top: 5px;">
                            <div id="kg-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                        </div>
                    </div>
                    
                    <div id="kg-log" style="max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px;">
                        <!-- Log entries will be added here -->
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let isProcessing = false;
            
            // Single tool seed
            $('.kg-seed-single-tool').on('click', function() {
                if (isProcessing) {
                    alert('Bir işlem zaten devam ediyor!');
                    return;
                }
                
                const slug = $(this).data('slug');
                const mode = $(this).data('mode');
                const $button = $(this);
                
                $button.prop('disabled', true);
                
                seedSingleTool(slug, mode).then(() => {
                    location.reload();
                }).catch((error) => {
                    alert('Hata: ' + error);
                    $button.prop('disabled', false);
                });
            });
            
            // Seed all missing tools
            $('#kg-seed-all-tools').on('click', function() {
                if (isProcessing) {
                    alert('Bir işlem zaten devam ediyor!');
                    return;
                }
                
                const toolsData = <?php echo json_encode($this->tools_data); ?>;
                const toolStatus = <?php echo json_encode($tool_status); ?>;
                
                const missingTools = toolsData.filter(tool => !toolStatus[tool.slug]);
                
                if (missingTools.length === 0) {
                    alert('Tüm araçlar zaten oluşturulmuş!');
                    return;
                }
                
                if (confirm(`${missingTools.length} araç oluşturulacak. Devam etmek istiyor musunuz?`)) {
                    processBulkTools(missingTools, 'create');
                }
            });
            
            // Update all existing tools
            $('#kg-update-all-tools').on('click', function() {
                if (isProcessing) {
                    alert('Bir işlem zaten devam ediyor!');
                    return;
                }
                
                const toolsData = <?php echo json_encode($this->tools_data); ?>;
                const toolStatus = <?php echo json_encode($tool_status); ?>;
                
                const existingTools = toolsData.filter(tool => toolStatus[tool.slug]);
                
                if (existingTools.length === 0) {
                    alert('Güncellenecek araç bulunamadı!');
                    return;
                }
                
                if (confirm(`${existingTools.length} araç güncellenecek. Devam etmek istiyor musunuz?`)) {
                    processBulkTools(existingTools, 'update');
                }
            });
            
            async function processBulkTools(tools, mode) {
                isProcessing = true;
                
                $('#kg-progress-section').show();
                $('#kg-log').html('');
                
                const total = tools.length;
                let completed = 0;
                
                for (let i = 0; i < tools.length; i++) {
                    const tool = tools[i];
                    addLog(`🔄 İşleniyor: ${tool.title} (${tool.slug})...`, 'info');
                    
                    try {
                        await seedSingleTool(tool.slug, mode);
                        addLog(`✅ ${tool.title} - ${mode === 'create' ? 'Oluşturuldu' : 'Güncellendi'}`, 'success');
                    } catch (error) {
                        addLog(`❌ ${tool.title} - Hata: ${error}`, 'error');
                    }
                    
                    completed++;
                    updateProgress(completed, total);
                }
                
                isProcessing = false;
                addLog('🎉 İşlem tamamlandı! Sayfa yenileniyor...', 'success');
                
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
            
            function seedSingleTool(slug, mode) {
                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: kgToolSeeder.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kg_seed_tool',
                            nonce: kgToolSeeder.nonce,
                            slug: slug,
                            mode: mode
                        },
                        success: function(response) {
                            if (response.success) {
                                resolve(response.data);
                            } else {
                                reject(response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            reject(error);
                        }
                    });
                });
            }
            
            function updateProgress(current, total) {
                const percentage = Math.round((current / total) * 100);
                $('#kg-progress-text').text(`${current}/${total}`);
                $('#kg-progress-bar').css('width', percentage + '%');
            }
            
            function addLog(message, type = 'info') {
                const colors = {
                    info: '#0073aa',
                    success: '#46b450',
                    warning: '#ffb900',
                    error: '#dc3232'
                };
                
                const timestamp = new Date().toLocaleTimeString('tr-TR');
                const $entry = $('<div>')
                    .css('color', colors[type])
                    .css('margin-bottom', '5px')
                    .text(`[${timestamp}] ${message}`);
                
                $('#kg-log').append($entry);
                $('#kg-log').scrollTop($('#kg-log')[0].scrollHeight);
            }
        });
        </script>
        <?php
    }
    
    /**
     * Get status of all tools
     */
    private function get_tools_status() {
        $status = [];
        
        foreach ($this->tools_data as $tool_data) {
            $args = [
                'post_type' => 'tool',
                'name' => $tool_data['slug'],
                'post_status' => ['publish', 'draft', 'pending'],
                'posts_per_page' => 1,
            ];
            
            $posts = get_posts($args);
            
            if (!empty($posts)) {
                $status[$tool_data['slug']] = [
                    'id' => $posts[0]->ID,
                    'status' => $posts[0]->post_status,
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * AJAX handler for tool seeding
     */
    public function ajax_seed_tool() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kg_tool_seed')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok');
        }
        
        $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'create';
        
        if (empty($slug)) {
            wp_send_json_error('Slug boş olamaz');
        }
        
        // Find tool data
        $tool_data = null;
        foreach ($this->tools_data as $data) {
            if ($data['slug'] === $slug) {
                $tool_data = $data;
                break;
            }
        }
        
        if (!$tool_data) {
            wp_send_json_error('Araç bulunamadı');
        }
        
        // Check if already exists
        $existing = get_page_by_path($slug, OBJECT, 'tool');
        
        if ($mode === 'create' && $existing) {
            wp_send_json_error('Araç zaten mevcut');
        }
        
        if ($mode === 'update' && !$existing) {
            wp_send_json_error('Güncellenecek araç bulunamadı');
        }
        
        // Create or update tool
        $result = $this->seed_tool($tool_data, $mode === 'update' ? $existing->ID : null);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'post_id' => $result,
                'mode' => $mode
            ]);
        }
    }
    
    /**
     * Seed a single tool
     * 
     * @param array $tool_data Tool data array
     * @param int|null $post_id Existing post ID for update mode
     * @return int|\WP_Error Post ID or error
     */
    private function seed_tool($tool_data, $post_id = null) {
        $post_data = [
            'post_title' => $tool_data['title'],
            'post_name' => $tool_data['slug'],
            'post_content' => $tool_data['description'],
            'post_type' => 'tool',
            'post_status' => 'publish',
        ];
        
        if ($post_id) {
            // Update existing post
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            // Create new post
            $result = wp_insert_post($post_data, true);
        }
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Save meta data
        update_post_meta($result, '_kg_tool_type', $tool_data['tool_type']);
        update_post_meta($result, '_kg_tool_icon', $tool_data['icon']);
        update_post_meta($result, '_kg_is_active', $tool_data['is_active'] ? '1' : '0');
        update_post_meta($result, '_kg_requires_auth', $tool_data['requires_auth'] ? '1' : '0');
        update_post_meta($result, '_kg_tool_is_sponsored', $tool_data['is_sponsored'] ? '1' : '0');
        
        // If ACF is available, also set ACF fields
        if (function_exists('update_field')) {
            update_field('tool_type', $tool_data['tool_type'], $result);
            update_field('tool_icon', $tool_data['icon'], $result);
            update_field('is_active', $tool_data['is_active'], $result);
            update_field('requires_auth', $tool_data['requires_auth'], $result);
        }
        
        return $result;
    }
    
    /**
     * Static method to seed tools on activation
     */
    public static function seed_on_activation() {
        $seeder = new self();
        
        foreach ($seeder->tools_data as $tool_data) {
            // Check if already exists
            $existing = get_page_by_path($tool_data['slug'], OBJECT, 'tool');
            
            if (!$existing) {
                $seeder->seed_tool($tool_data);
            }
        }
    }
}
