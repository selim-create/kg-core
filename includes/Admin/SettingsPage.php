<?php
namespace KG_Core\Admin;

class SettingsPage {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_kg_test_image_generation', [$this, 'handle_test_image_generation']);
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=ingredient',
            '⚙️ AI Ayarları',
            '⚙️ AI Ayarları',
            'manage_options',
            'kg-ai-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // AI Provider Settings
        register_setting('kg_ai_settings', 'kg_ai_provider', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'openai'
        ]);
        
        register_setting('kg_ai_settings', 'kg_ai_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('kg_ai_settings', 'kg_ai_model', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-4o-mini'
        ]);
        
        // Image API Settings
        register_setting('kg_ai_settings', 'kg_unsplash_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('kg_ai_settings', 'kg_pexels_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('kg_ai_settings', 'kg_preferred_image_api', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'unsplash'
        ]);
        
        // Image Generation Provider Settings (NEW)
        register_setting('kg_ai_settings', 'kg_image_provider', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'dalle'
        ]);
        
        register_setting('kg_ai_settings', 'kg_stability_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        // Auto-generation Setting
        register_setting('kg_ai_settings', 'kg_auto_generate_on_missing', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ]);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Bu sayfaya erişim yetkiniz yok.');
        }
        
        // Get current settings
        $ai_provider = get_option('kg_ai_provider', 'openai');
        $ai_api_key = get_option('kg_ai_api_key', '');
        $ai_model = get_option('kg_ai_model', 'gpt-4o-mini');
        $unsplash_key = get_option('kg_unsplash_api_key', '');
        $pexels_key = get_option('kg_pexels_api_key', '');
        $preferred_image_api = get_option('kg_preferred_image_api', 'unsplash');
        $image_provider = get_option('kg_image_provider', 'dalle');
        $stability_api_key = get_option('kg_stability_api_key', '');
        $auto_generate = get_option('kg_auto_generate_on_missing', false);
        
        ?>
        <div class="wrap">
            <h1>⚙️ AI Ayarları</h1>
            <p>Malzeme oluşturma için AI ve görsel API ayarlarını yapılandırın.</p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('kg_ai_settings');
                do_settings_sections('kg_ai_settings');
                ?>
                
                <table class="form-table">
                    <!-- AI Provider Settings -->
                    <tr>
                        <th colspan="2">
                            <h2>🤖 AI Sağlayıcı Ayarları</h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="kg_ai_provider">AI Sağlayıcı</label>
                        </th>
                        <td>
                            <select name="kg_ai_provider" id="kg_ai_provider" class="regular-text">
                                <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI (GPT-4)</option>
                                <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>>Anthropic (Claude)</option>
                                <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Google Gemini</option>
                            </select>
                            <p class="description">Kullanılacak AI sağlayıcısını seçin.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="kg_ai_api_key">API Key</label>
                        </th>
                        <td>
                            <input type="password" name="kg_ai_api_key" id="kg_ai_api_key" 
                                   value="<?php echo esc_attr($ai_api_key); ?>" class="regular-text" 
                                   placeholder="sk-...">
                            <p class="description">AI sağlayıcınızın API anahtarını girin.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="kg_ai_model">Model</label>
                        </th>
                        <td>
                            <select name="kg_ai_model" id="kg_ai_model" class="regular-text">
                                <optgroup label="OpenAI">
                                    <option value="gpt-4o" <?php selected($ai_model, 'gpt-4o'); ?>>GPT-4o</option>
                                    <option value="gpt-4o-mini" <?php selected($ai_model, 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                                    <option value="gpt-4-turbo" <?php selected($ai_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                </optgroup>
                                <optgroup label="Anthropic">
                                    <option value="claude-3-5-sonnet-20241022" <?php selected($ai_model, 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
                                    <option value="claude-3-opus-20240229" <?php selected($ai_model, 'claude-3-opus-20240229'); ?>>Claude 3 Opus</option>
                                    <option value="claude-3-sonnet-20240229" <?php selected($ai_model, 'claude-3-sonnet-20240229'); ?>>Claude 3 Sonnet</option>
                                </optgroup>
                                <optgroup label="Google Gemini">
                                    <option value="gemini-1.5-pro" <?php selected($ai_model, 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option>
                                    <option value="gemini-1.5-flash" <?php selected($ai_model, 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash</option>
                                </optgroup>
                            </select>
                            <p class="description">Kullanılacak AI modelini seçin.</p>
                        </td>
                    </tr>
                    
                    <!-- Image Generation Settings -->
                    <tr>
                        <th colspan="2">
                            <h2 style="margin-top: 30px;">🎨 Görsel Oluşturma Ayarları</h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="kg_image_provider">Görsel Sağlayıcı</label>
                        </th>
                        <td>
                            <select name="kg_image_provider" id="kg_image_provider" class="regular-text">
                                <option value="dalle" <?php selected($image_provider, 'dalle'); ?>>DALL-E 3 (OpenAI)</option>
                                <option value="stability" <?php selected($image_provider, 'stability'); ?>>Stable Diffusion (Stability AI)</option>
                            </select>
                            <p class="description">AI görsel oluşturma sağlayıcısını seçin. Her ikisi de ham malzeme görselleri için optimize edilmiştir.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="kg_stability_api_key">Stability AI API Key</label>
                        </th>
                        <td>
                            <input type="password" name="kg_stability_api_key" id="kg_stability_api_key" 
                                   value="<?php echo esc_attr($stability_api_key); ?>" class="regular-text"
                                   placeholder="sk-...">
                            <p class="description">Stability AI API anahtarınızı girin (<a href="https://platform.stability.ai/" target="_blank">Buradan alın</a>). Stable Diffusion sağlayıcısı için gerekli.</p>
                        </td>
                    </tr>
                    
                    <!-- Image API Settings -->
                    <tr>
                        <th colspan="2">
                            <h2 style="margin-top: 30px;">🖼️ Görsel API Ayarları (Yedek)</h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="kg_unsplash_api_key">Unsplash API Key</label>
                        </th>
                        <td>
                            <input type="password" name="kg_unsplash_api_key" id="kg_unsplash_api_key" 
                                   value="<?php echo esc_attr($unsplash_key); ?>" class="regular-text">
                            <p class="description">Unsplash API anahtarınızı girin (<a href="https://unsplash.com/developers" target="_blank">Buradan alın</a>).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="kg_pexels_api_key">Pexels API Key</label>
                        </th>
                        <td>
                            <input type="password" name="kg_pexels_api_key" id="kg_pexels_api_key" 
                                   value="<?php echo esc_attr($pexels_key); ?>" class="regular-text">
                            <p class="description">Pexels API anahtarınızı girin (<a href="https://www.pexels.com/api/" target="_blank">Buradan alın</a>).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="kg_preferred_image_api">Tercih Edilen API</label>
                        </th>
                        <td>
                            <select name="kg_preferred_image_api" id="kg_preferred_image_api" class="regular-text">
                                <option value="dall-e" <?php selected($preferred_image_api, 'dall-e'); ?>>DALL-E 3 (AI Oluşturulmuş)</option>
                                <option value="unsplash" <?php selected($preferred_image_api, 'unsplash'); ?>>Unsplash Öncelikli</option>
                                <option value="pexels" <?php selected($preferred_image_api, 'pexels'); ?>>Pexels Öncelikli</option>
                            </select>
                            <p class="description">Önce hangi API'nin kullanılacağını seçin. DALL-E 3 profesyonel, tutarlı görseller üretir (~$0.04/görsel).</p>
                        </td>
                    </tr>
                    
                    <!-- Auto-generation Settings -->
                    <tr>
                        <th colspan="2">
                            <h2 style="margin-top: 30px;">⚡ Otomatik Oluşturma Ayarları</h2>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="kg_auto_generate_on_missing">Otomatik Oluştur</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="kg_auto_generate_on_missing" id="kg_auto_generate_on_missing" 
                                       value="1" <?php checked($auto_generate, true); ?>>
                                Tarif kaydedilirken eksik malzemeleri otomatik oluştur
                            </label>
                            <p class="description">Aktif edilirse, tarif kaydederken henüz sayfası olmayan malzemeler arka planda AI ile oluşturulur.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Ayarları Kaydet', 'primary', 'kg_ai_settings_submit'); ?>
            </form>
            
            <!-- Image Generation Test Tool -->
            <div style="margin-top: 40px; padding: 20px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3>🧪 Görsel Oluşturma Test Aracı</h3>
                <p>Malzeme adı girerek AI görsel oluşturmayı test edin.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kg_test_ingredient">Malzeme Adı</label>
                        </th>
                        <td>
                            <input type="text" id="kg_test_ingredient" class="regular-text" 
                                   placeholder="Örn: muz, havuç, elma">
                            <button type="button" id="kg_test_generate_btn" class="button button-secondary">
                                🎨 Test Et
                            </button>
                            <span id="kg_test_loading" style="display:none; margin-left: 10px;">
                                ⏳ Görsel oluşturuluyor...
                            </span>
                        </td>
                    </tr>
                </table>
                
                <div id="kg_test_result" style="margin-top: 20px;"></div>
            </div>
            
            <!-- Status Information -->
            <div style="margin-top: 20px; padding: 20px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3>📊 Durum</h3>
                <ul style="list-style: none; padding: 0;">
                    <li>🤖 AI: <?php echo !empty($ai_api_key) ? '<strong style="color: green;">✓ Yapılandırıldı</strong>' : '<strong style="color: red;">✗ Yapılandırılmadı</strong>'; ?></li>
                    <li>🎨 DALL-E 3: <?php echo !empty($ai_api_key) ? '<strong style="color: green;">✓ Kullanılabilir</strong>' : '<strong style="color: red;">✗ API Key Gerekli</strong>'; ?></li>
                    <li>🎨 Stable Diffusion: <?php echo !empty($stability_api_key) ? '<strong style="color: green;">✓ Yapılandırıldı</strong>' : '<strong style="color: red;">✗ Yapılandırılmadı</strong>'; ?></li>
                    <li>🖼️ Unsplash: <?php echo !empty($unsplash_key) ? '<strong style="color: green;">✓ Yapılandırıldı</strong>' : '<strong style="color: red;">✗ Yapılandırılmadı</strong>'; ?></li>
                    <li>🖼️ Pexels: <?php echo !empty($pexels_key) ? '<strong style="color: green;">✓ Yapılandırıldı</strong>' : '<strong style="color: red;">✗ Yapılandırılmadı</strong>'; ?></li>
                </ul>
            </div>
        </div>
        
        <style>
            .form-table th {
                width: 200px;
            }
            .form-table h2 {
                margin: 0;
                padding: 10px 0;
                border-bottom: 2px solid #2271b1;
            }
            #kg_test_result img {
                max-width: 512px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-top: 10px;
            }
            .kg-prompt-display {
                background: #fff;
                padding: 15px;
                border-left: 3px solid #2271b1;
                margin-top: 10px;
                font-family: monospace;
                font-size: 12px;
                white-space: pre-wrap;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#kg_test_generate_btn').on('click', function() {
                var ingredient = $('#kg_test_ingredient').val().trim();
                
                if (!ingredient) {
                    alert('Lütfen bir malzeme adı girin.');
                    return;
                }
                
                $('#kg_test_loading').show();
                $('#kg_test_generate_btn').prop('disabled', true);
                $('#kg_test_result').html('');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'kg_test_image_generation',
                        ingredient: ingredient,
                        nonce: '<?php echo wp_create_nonce('kg_test_image_nonce'); ?>'
                    },
                    success: function(response) {
                        $('#kg_test_loading').hide();
                        $('#kg_test_generate_btn').prop('disabled', false);
                        
                        if (response.success) {
                            var html = '<h4>✅ Görsel Başarıyla Oluşturuldu</h4>';
                            html += '<p><strong>Kaynak:</strong> ' + response.data.source + '</p>';
                            html += '<img src="' + response.data.url + '" alt="' + ingredient + '">';
                            
                            if (response.data.prompt) {
                                html += '<h4 style="margin-top: 20px;">📝 Kullanılan Prompt:</h4>';
                                html += '<div class="kg-prompt-display">' + response.data.prompt + '</div>';
                            }
                            
                            if (response.data.negative_prompt) {
                                html += '<h4 style="margin-top: 20px;">⛔ Negatif Prompt:</h4>';
                                html += '<div class="kg-prompt-display">' + response.data.negative_prompt + '</div>';
                            }
                            
                            $('#kg_test_result').html(html);
                        } else {
                            var errorMsg = response.data && response.data.message ? response.data.message : 'Bilinmeyen hata';
                            
                            // Handle nonce verification failure by suggesting page refresh
                            if (errorMsg.includes('Güvenlik kontrolü')) {
                                errorMsg += ' Lütfen sayfayı yenileyin ve tekrar deneyin.';
                            }
                            
                            $('#kg_test_result').html('<p style="color: red;">❌ Hata: ' + errorMsg + '</p>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#kg_test_loading').hide();
                        $('#kg_test_generate_btn').prop('disabled', false);
                        $('#kg_test_result').html('<p style="color: red;">❌ AJAX Hatası: ' + error + '</p>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX request for testing image generation
     */
    public function handle_test_image_generation() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kg_test_image_nonce')) {
            wp_send_json_error(['message' => 'Güvenlik kontrolü başarısız.']);
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Yetkiniz yok.']);
            return;
        }
        
        // Get ingredient name
        $ingredient = isset($_POST['ingredient']) ? sanitize_text_field($_POST['ingredient']) : '';
        
        if (empty($ingredient)) {
            wp_send_json_error(['message' => 'Malzeme adı boş olamaz.']);
            return;
        }
        
        // Generate image
        $image_service = new \KG_Core\Services\ImageService();
        $result = $image_service->generateImage($ingredient);
        
        if ($result === null) {
            wp_send_json_error(['message' => 'Görsel oluşturulamadı. Lütfen API ayarlarınızı kontrol edin.']);
            return;
        }
        
        wp_send_json_success($result);
    }
}
