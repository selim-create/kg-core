<?php
namespace KG_Core\Admin;

use KG_Core\Services\IngredientGenerator;

class BulkIngredientSeeder {
    
    private $ingredient_lists = [];
    
    public function __construct() {
        $this->initIngredientLists();
        add_action('admin_menu', [$this, 'add_seeder_page']);
        add_action('wp_ajax_kg_bulk_seed_ingredient', [$this, 'ajax_seed_ingredient']);
    }
    
    /**
     * Initialize predefined ingredient lists
     */
    private function initIngredientLists() {
        $this->ingredient_lists = [
            'meyveler' => [
                'name' => '🍎 Meyveler (25 adet)',
                'items' => [
                    'Elma', 'Muz', 'Armut', 'Şeftali', 'Kayısı', 'Erik', 'Kiraz', 'Çilek', 
                    'Üzüm', 'Karpuz', 'Kavun', 'Portakal', 'Mandalina', 'Avokado', 'Kivi', 
                    'Hurma', 'İncir', 'Nar', 'Böğürtlen', 'Ahududu', 'Yaban Mersini', 
                    'Mango', 'Ananas', 'Papaya', 'Dut'
                ]
            ],
            'sebzeler' => [
                'name' => '🥦 Sebzeler (30 adet)',
                'items' => [
                    'Havuç', 'Patates', 'Tatlı Patates', 'Kabak', 'Balkabağı', 'Brokoli', 
                    'Karnabahar', 'Ispanak', 'Pırasa', 'Bezelye', 'Fasulye', 'Mercimek', 
                    'Domates', 'Salatalık', 'Biber', 'Patlıcan', 'Enginar', 'Kereviz', 
                    'Pancar', 'Turp', 'Lahana', 'Brüksel Lahanası', 'Kuşkonmaz', 'Bamya', 
                    'Pazı', 'Semizotu', 'Maydanoz', 'Dereotu', 'Nane', 'Sarımsak'
                ]
            ],
            'proteinler' => [
                'name' => '🍗 Proteinler (20 adet)',
                'items' => [
                    'Tavuk Göğsü', 'Tavuk But', 'Dana Kıyma', 'Dana Bonfile', 'Kuzu Eti', 
                    'Hindi Göğsü', 'Somon', 'Levrek', 'Çipura', 'Mezgit', 'Hamsi', 
                    'Yumurta', 'Yumurta Sarısı', 'Kırmızı Mercimek', 'Yeşil Mercimek', 
                    'Nohut', 'Kuru Fasulye', 'Barbunya', 'Karabuğday', 'Kinoa'
                ]
            ],
            'tahillar' => [
                'name' => '🌾 Tahıllar (15 adet)',
                'items' => [
                    'Pirinç', 'Bulgur', 'Yulaf', 'Yulaf Ezmesi', 'Buğday', 'Arpa', 'Mısır', 
                    'Mısır Unu', 'Pirinç Unu', 'Tam Buğday Unu', 'İrmik', 'Makarna', 
                    'Şehriye', 'Kuskus', 'Ekmek Kırıntısı'
                ]
            ],
            'sut_urunleri' => [
                'name' => '🥛 Süt Ürünleri (10 adet)',
                'items' => [
                    'Tam Yağlı Süt', 'Anne Sütü', 'Yoğurt', 'Süzme Yoğurt', 'Kefir', 
                    'Lor Peyniri', 'Taze Kaşar', 'Beyaz Peynir', 'Labne', 'Tereyağı'
                ]
            ]
        ];
    }
    
    /**
     * Get all ingredients from all categories
     */
    private function getAllIngredients() {
        $all = [];
        foreach ($this->ingredient_lists as $category) {
            $all = array_merge($all, $category['items']);
        }
        return $all;
    }
    
    /**
     * Add seeder page to admin menu
     */
    public function add_seeder_page() {
        add_submenu_page(
            'kg-core',
            '🤖 Toplu AI Oluştur',
            '🤖 Toplu AI Oluştur',
            'manage_options',
            'kg-bulk-seeder',
            [$this, 'render_seeder_page']
        );
    }
    
    /**
     * Render bulk seeder page
     */
    public function render_seeder_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Bu sayfaya erişim yetkiniz yok.');
        }
        
        ?>
        <div class="wrap">
            <h1>🤖 Toplu Malzeme Oluşturma (AI)</h1>
            <p>Bebek tariflerinde en çok kullanılan malzemeleri AI ile otomatik oluşturun.</p>
            
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;">
                <strong>⚠️ Önemli Notlar:</strong>
                <ul>
                    <li>Her malzeme arasında 2 saniye bekleme yapılır (API limitlerini korumak için)</li>
                    <li>Oluşturulan malzemeler <strong>taslak</strong> olarak kaydedilir (manuel inceleme için)</li>
                    <li>Zaten var olan malzemeler atlanır</li>
                    <li>AI ve görsel API ayarlarının yapılandırılmış olması gerekir</li>
                </ul>
            </div>
            
            <!-- Predefined Packages -->
            <h2>📦 Hazır Paketler</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-bottom: 30px;">
                
                <!-- All Ingredients -->
                <div class="kg-package-card">
                    <h3>🥕 En Popüler 100 Malzeme</h3>
                    <p>Tüm kategorilerden toplam <?php echo count($this->getAllIngredients()); ?> malzeme</p>
                    <button type="button" class="button button-primary kg-seed-package" 
                            data-package="all">Tümünü Oluştur</button>
                </div>
                
                <!-- Individual Categories -->
                <?php foreach ($this->ingredient_lists as $key => $category): ?>
                <div class="kg-package-card">
                    <h3><?php echo esc_html($category['name']); ?></h3>
                    <p><?php echo count($category['items']); ?> malzeme</p>
                    <button type="button" class="button button-primary kg-seed-package" 
                            data-package="<?php echo esc_attr($key); ?>">Oluştur</button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Custom List -->
            <h2>✍️ Özel Liste</h2>
            <div style="background: white; padding: 20px; border: 1px solid #ccc;">
                <p>Her satıra bir malzeme adı yazın:</p>
                <textarea id="kg-custom-ingredients" rows="10" style="width: 100%; font-family: monospace;" 
                          placeholder="Havuç&#10;Patates&#10;Elma&#10;..."></textarea>
                <button type="button" class="button button-primary" id="kg-seed-custom">Listeyi Oluştur</button>
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
                    
                    <button type="button" class="button" id="kg-stop-process" style="margin-top: 10px;">Durdur</button>
                </div>
            </div>
        </div>
        
        <style>
            .kg-package-card {
                background: white;
                padding: 20px;
                border: 1px solid #ccc;
                border-radius: 5px;
                text-align: center;
            }
            .kg-package-card h3 {
                margin-top: 0;
                font-size: 18px;
            }
            .kg-package-card p {
                color: #666;
                margin: 10px 0;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let isProcessing = false;
            let shouldStop = false;
            
            // Package buttons
            $('.kg-seed-package').on('click', function() {
                if (isProcessing) {
                    alert('Bir işlem zaten devam ediyor!');
                    return;
                }
                
                const packageName = $(this).data('package');
                const ingredients = getPackageIngredients(packageName);
                
                if (confirm(`${ingredients.length} malzeme oluşturulacak. Devam etmek istiyor musunuz?`)) {
                    processIngredients(ingredients);
                }
            });
            
            // Custom list button
            $('#kg-seed-custom').on('click', function() {
                if (isProcessing) {
                    alert('Bir işlem zaten devam ediyor!');
                    return;
                }
                
                const text = $('#kg-custom-ingredients').val().trim();
                if (!text) {
                    alert('Lütfen en az bir malzeme adı girin!');
                    return;
                }
                
                const ingredients = text.split('\n').map(i => i.trim()).filter(i => i.length > 0);
                
                if (confirm(`${ingredients.length} malzeme oluşturulacak. Devam etmek istiyor musunuz?`)) {
                    processIngredients(ingredients);
                }
            });
            
            // Stop button
            $('#kg-stop-process').on('click', function() {
                shouldStop = true;
                addLog('⏸️ Durdurma talebi alındı. Mevcut işlem tamamlanıyor...', 'info');
            });
            
            function getPackageIngredients(packageName) {
                const packages = <?php echo json_encode($this->ingredient_lists); ?>;
                
                if (packageName === 'all') {
                    let all = [];
                    Object.keys(packages).forEach(key => {
                        all = all.concat(packages[key].items);
                    });
                    return all;
                }
                
                return packages[packageName] ? packages[packageName].items : [];
            }
            
            async function processIngredients(ingredients) {
                isProcessing = true;
                shouldStop = false;
                
                $('#kg-progress-section').show();
                $('#kg-log').html('');
                
                const total = ingredients.length;
                let completed = 0;
                
                for (let i = 0; i < ingredients.length; i++) {
                    if (shouldStop) {
                        addLog('⏹️ İşlem kullanıcı tarafından durduruldu.', 'warning');
                        break;
                    }
                    
                    const ingredient = ingredients[i];
                    addLog(`🔄 İşleniyor: ${ingredient}...`, 'info');
                    
                    try {
                        const result = await seedIngredient(ingredient);
                        
                        if (result.success) {
                            addLog(`✅ ${ingredient} - Oluşturuldu (ID: ${result.post_id})`, 'success');
                        } else {
                            addLog(`⚠️ ${ingredient} - ${result.message}`, 'warning');
                        }
                    } catch (error) {
                        addLog(`❌ ${ingredient} - Hata: ${error}`, 'error');
                    }
                    
                    completed++;
                    updateProgress(completed, total);
                    
                    // Wait 2 seconds before next (rate limiting)
                    if (i < ingredients.length - 1 && !shouldStop) {
                        await sleep(2000);
                    }
                }
                
                isProcessing = false;
                addLog('🎉 İşlem tamamlandı!', 'success');
            }
            
            function seedIngredient(name) {
                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kg_bulk_seed_ingredient',
                            nonce: '<?php echo wp_create_nonce("kg_bulk_seed"); ?>',
                            ingredient_name: name
                        },
                        success: function(response) {
                            if (response.success) {
                                resolve(response.data);
                            } else {
                                resolve({success: false, message: response.data});
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
            
            function sleep(ms) {
                return new Promise(resolve => setTimeout(resolve, ms));
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for single ingredient seeding
     */
    public function ajax_seed_ingredient() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kg_bulk_seed')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok');
        }
        
        $ingredient_name = isset($_POST['ingredient_name']) ? sanitize_text_field($_POST['ingredient_name']) : '';
        
        if (empty($ingredient_name)) {
            wp_send_json_error('Malzeme adı boş olamaz');
        }
        
        // Check if already exists
        $existing = get_page_by_title($ingredient_name, OBJECT, 'ingredient');
        if ($existing) {
            wp_send_json_success([
                'success' => false,
                'message' => 'Zaten mevcut'
            ]);
            return;
        }
        
        // Create ingredient
        $generator = new IngredientGenerator();
        $result = $generator->create($ingredient_name);
        
        if (is_wp_error($result)) {
            wp_send_json_success([
                'success' => false,
                'message' => $result->get_error_message()
            ]);
        } else {
            wp_send_json_success([
                'success' => true,
                'post_id' => $result
            ]);
        }
    }
}
