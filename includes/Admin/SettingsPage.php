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
            'kg-core',
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
        // === İÇERİK ÜRETİMİ AYARLARI ===
        register_setting('kg_ai_settings', 'kg_ai_provider', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'openai'
        ]);
        
        register_setting('kg_ai_settings', 'kg_openai_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('kg_ai_settings', 'kg_ai_model', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-4o-mini'
        ]);
        
        // === GÖRSEL KAYNAĞI AYARLARI (TEK SEÇENEK) ===
        register_setting('kg_ai_settings', 'kg_image_source', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'dalle'
        ]);
        
        // === API KEYS (Her servis için ayrı) ===
        register_setting('kg_ai_settings', 'kg_dalle_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('kg_ai_settings', 'kg_stability_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
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
        
        // === GOOGLE OAUTH AYARLARI ===
        register_setting('kg_ai_settings', 'kg_google_client_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);

        register_setting('kg_ai_settings', 'kg_google_client_secret', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);

        register_setting('kg_ai_settings', 'kg_google_auth_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ]);
        
        // === OTOMASYON ===
        register_setting('kg_ai_settings', 'kg_auto_generate_on_missing', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ]);
        
        // Backward compatibility - keep old settings registered
        register_setting('kg_ai_settings', 'kg_ai_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('kg_ai_settings', 'kg_preferred_image_api', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'unsplash'
        ]);
        
        register_setting('kg_ai_settings', 'kg_image_provider', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'dalle'
        ]);
        
        // Migrate old settings to new settings
        $this->migrate_old_settings();
    }
    
    /**
     * Migrate old settings to new unified structure
     */
    private function migrate_old_settings() {
        // Eski kg_ai_api_key varsa ve yeni yoksa, migrate et
        $old_api_key = get_option('kg_ai_api_key', '');
        $new_openai_key = get_option('kg_openai_api_key', '');
        $new_dalle_key = get_option('kg_dalle_api_key', '');
        
        if (!empty($old_api_key) && empty($new_openai_key)) {
            update_option('kg_openai_api_key', $old_api_key);
        }
        
        if (!empty($old_api_key) && empty($new_dalle_key)) {
            update_option('kg_dalle_api_key', $old_api_key);
        }
        
        // Eski kg_preferred_image_api'yi kg_image_source'a migrate et
        $old_preferred = get_option('kg_preferred_image_api', '');
        $new_source = get_option('kg_image_source', '');
        
        if (!empty($old_preferred) && empty($new_source)) {
            // Map old values to new values
            $mapping = [
                'dall-e' => 'dalle',
                'unsplash' => 'unsplash',
                'pexels' => 'pexels'
            ];
            $mapped_value = isset($mapping[$old_preferred]) ? $mapping[$old_preferred] : $old_preferred;
            update_option('kg_image_source', $mapped_value);
        }
        
        // Eski kg_image_provider'ı kontrol et (stability için)
        $old_provider = get_option('kg_image_provider', '');
        if ($old_provider === 'stability' && empty($new_source)) {
            update_option('kg_image_source', 'stability');
        }
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
        $openai_api_key = get_option('kg_openai_api_key', '') ?: get_option('kg_ai_api_key', '');
        $ai_model = get_option('kg_ai_model', 'gpt-4o-mini');
        
        $image_source = get_option('kg_image_source', 'dalle');
        $dalle_api_key = get_option('kg_dalle_api_key', '') ?: get_option('kg_ai_api_key', '');
        $stability_api_key = get_option('kg_stability_api_key', '');
        $unsplash_api_key = get_option('kg_unsplash_api_key', '');
        $pexels_api_key = get_option('kg_pexels_api_key', '');
        
        $auto_generate = get_option('kg_auto_generate_on_missing', false);
        
        $google_auth_enabled = get_option('kg_google_auth_enabled', false);
        $google_client_id = get_option('kg_google_client_id', '');
        $google_client_secret = get_option('kg_google_client_secret', '');
        
        ?>
        <div class="wrap">
            <h1>🤖 KidsGourmet AI Ayarları</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('kg_ai_settings'); ?>
                
                <!-- İÇERİK ÜRETİMİ -->
                <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 2px solid #4CAF50; padding-bottom: 10px;">
                        📝 İçerik Üretimi (AI Text)
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">AI Sağlayıcı</th>
                            <td>
                                <select name="kg_ai_provider" id="kg_ai_provider">
                                    <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI (GPT-4)</option>
                                    <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>>Anthropic (Claude)</option>
                                    <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Google (Gemini)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">OpenAI API Key</th>
                            <td>
                                <input type="password" name="kg_openai_api_key" value="<?php echo esc_attr($openai_api_key); ?>" class="regular-text" placeholder="sk-...">
                                <p class="description">İçerik üretimi için OpenAI API anahtarı. <a href="https://platform.openai.com/api-keys" target="_blank">API Key Al</a></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Model</th>
                            <td>
                                <select name="kg_ai_model">
                                    <option value="gpt-4o" <?php selected($ai_model, 'gpt-4o'); ?>>GPT-4o (En İyi Kalite)</option>
                                    <option value="gpt-4o-mini" <?php selected($ai_model, 'gpt-4o-mini'); ?>>GPT-4o Mini (Hızlı & Ekonomik)</option>
                                    <option value="gpt-4-turbo" <?php selected($ai_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- GÖRSEL KAYNAĞI -->
                <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 2px solid #2196F3; padding-bottom: 10px;">
                        🖼️ Görsel Kaynağı
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Tercih Edilen Kaynak</th>
                            <td>
                                <fieldset>
                                    <label style="display: block; margin-bottom: 12px; padding: 12px; background: <?php echo $image_source === 'dalle' ? '#E8F5E9' : '#f5f5f5'; ?>; border-radius: 8px; cursor: pointer;">
                                        <input type="radio" name="kg_image_source" value="dalle" <?php checked($image_source, 'dalle'); ?>>
                                        <strong>🎨 DALL-E 3</strong> (OpenAI) 
                                        <span style="color: #666; font-size: 12px;">~$0.04/görsel - En iyi kalite, tutarlı stil</span>
                                    </label>
                                    
                                    <label style="display: block; margin-bottom: 12px; padding: 12px; background: <?php echo $image_source === 'stability' ? '#E3F2FD' : '#f5f5f5'; ?>; border-radius: 8px; cursor: pointer;">
                                        <input type="radio" name="kg_image_source" value="stability" <?php checked($image_source, 'stability'); ?>>
                                        <strong>🌀 Stable Diffusion XL</strong> (Stability AI) 
                                        <span style="color: #666; font-size: 12px;">~$0.01/görsel - Ekonomik, negatif prompt desteği</span>
                                    </label>
                                    
                                    <label style="display: block; margin-bottom: 12px; padding: 12px; background: <?php echo $image_source === 'unsplash' ? '#FFF3E0' : '#f5f5f5'; ?>; border-radius: 8px; cursor: pointer;">
                                        <input type="radio" name="kg_image_source" value="unsplash" <?php checked($image_source, 'unsplash'); ?>>
                                        <strong>📷 Unsplash</strong> 
                                        <span style="color: #666; font-size: 12px;">Ücretsiz stok fotoğraflar</span>
                                    </label>
                                    
                                    <label style="display: block; padding: 12px; background: <?php echo $image_source === 'pexels' ? '#FCE4EC' : '#f5f5f5'; ?>; border-radius: 8px; cursor: pointer;">
                                        <input type="radio" name="kg_image_source" value="pexels" <?php checked($image_source, 'pexels'); ?>>
                                        <strong>📸 Pexels</strong> 
                                        <span style="color: #666; font-size: 12px;">Ücretsiz stok fotoğraflar</span>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">
                    
                    <h3>🔑 API Anahtarları</h3>
                    <p class="description" style="margin-bottom: 15px;">Sadece yukarıda seçtiğiniz kaynağın API anahtarını girmeniz yeterli.</p>
                    
                    <table class="form-table">
                        <tr id="dalle-key-row" style="<?php echo $image_source !== 'dalle' ? 'opacity: 0.5;' : ''; ?>">
                            <th scope="row">🎨 DALL-E 3 API Key</th>
                            <td>
                                <input type="password" name="kg_dalle_api_key" value="<?php echo esc_attr($dalle_api_key); ?>" class="regular-text" placeholder="sk-...">
                                <p class="description">OpenAI API Key (İçerik üretimi ile aynı key kullanılabilir)</p>
                            </td>
                        </tr>
                        <tr id="stability-key-row" style="<?php echo $image_source !== 'stability' ? 'opacity: 0.5;' : ''; ?>">
                            <th scope="row">🌀 Stability AI API Key</th>
                            <td>
                                <input type="password" name="kg_stability_api_key" value="<?php echo esc_attr($stability_api_key); ?>" class="regular-text" placeholder="sk-...">
                                <p class="description"><a href="https://platform.stability.ai/account/keys" target="_blank">Stability AI'dan API Key Al</a></p>
                            </td>
                        </tr>
                        <tr id="unsplash-key-row" style="<?php echo $image_source !== 'unsplash' ? 'opacity: 0.5;' : ''; ?>">
                            <th scope="row">📷 Unsplash API Key</th>
                            <td>
                                <input type="password" name="kg_unsplash_api_key" value="<?php echo esc_attr($unsplash_api_key); ?>" class="regular-text">
                                <p class="description"><a href="https://unsplash.com/developers" target="_blank">Unsplash Developer</a></p>
                            </td>
                        </tr>
                        <tr id="pexels-key-row" style="<?php echo $image_source !== 'pexels' ? 'opacity: 0.5;' : ''; ?>">
                            <th scope="row">📸 Pexels API Key</th>
                            <td>
                                <input type="password" name="kg_pexels_api_key" value="<?php echo esc_attr($pexels_api_key); ?>" class="regular-text">
                                <p class="description"><a href="https://www.pexels.com/api/" target="_blank">Pexels API</a></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- GOOGLE OAUTH -->
                <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 2px solid #4285F4; padding-bottom: 10px;">
                        🔐 Google OAuth Ayarları
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Google ile Giriş</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="kg_google_auth_enabled" value="1" <?php checked($google_auth_enabled, true); ?>>
                                    Google ile giriş özelliğini aktif et
                                </label>
                                <p class="description">Kullanıcıların Google hesapları ile giriş yapmasına izin verir.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Google Client ID</th>
                            <td>
                                <input type="text" name="kg_google_client_id" value="<?php echo esc_attr($google_client_id); ?>" class="regular-text" placeholder="xxxx.apps.googleusercontent.com">
                                <p class="description">Google Cloud Console'dan alınan Client ID</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Google Client Secret</th>
                            <td>
                                <input type="password" name="kg_google_client_secret" value="<?php echo esc_attr($google_client_secret); ?>" class="regular-text" placeholder="GOCSPX-...">
                                <p class="description">Google Cloud Console'dan alınan Client Secret. <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></p>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="background: #E8F0FE; padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #1967D2;">📝 Google OAuth Kurulum Adımları:</h4>
                        <ol style="margin: 0; padding-left: 20px; color: #5F6368;">
                            <li>Google Cloud Console'a gidin ve yeni bir proje oluşturun</li>
                            <li>APIs & Services > Credentials bölümüne gidin</li>
                            <li>"Create Credentials" > "OAuth client ID" seçin</li>
                            <li>Application type olarak "Web application" seçin</li>
                            <li>Authorized JavaScript origins: <code><?php echo home_url(); ?></code></li>
                            <li>Authorized redirect URIs: <code><?php echo home_url('/wp-json/kg/v1/auth/google/callback'); ?></code></li>
                            <li>Client ID ve Client Secret'ı yukarıya yapıştırın</li>
                        </ol>
                    </div>
                </div>
                
                <!-- OTOMASYON -->
                <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0; border-bottom: 2px solid #FF9800; padding-bottom: 10px;">
                        ⚡ Otomasyon
                    </h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Eksik Malzeme Oluşturma</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="kg_auto_generate_on_missing" value="1" <?php checked($auto_generate, true); ?>>
                                    Tarif eklenirken eksik malzemeyi otomatik oluştur
                                </label>
                                <p class="description">Bu seçenek aktifken, tarif malzeme listesine yazılan bir malzeme sistemde yoksa AI ile otomatik oluşturulur.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button('💾 Ayarları Kaydet'); ?>
            </form>
            
            <!-- GÖRSEL TEST -->
            <div style="background: white; padding: 20px; border-radius: 12px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h2 style="margin-top: 0; border-bottom: 2px solid #9C27B0; padding-bottom: 10px;">
                    🧪 Görsel Üretim Testi
                </h2>
                
                <div style="display: flex; gap: 10px; align-items: flex-end; margin-bottom: 20px;">
                    <div>
                        <label><strong>Malzeme Adı:</strong></label><br>
                        <input type="text" id="kg_test_ingredient" placeholder="Örn: Havuç" style="width: 200px; padding: 8px;">
                    </div>
                    <button type="button" class="button button-primary" onclick="kgTestImage()">🖼️ Test Et</button>
                </div>
                
                <div id="kg_test_result" style="display: none; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                    <div id="kg_test_loading" style="text-align: center; display: none;">
                        <span class="spinner is-active" style="float: none;"></span>
                        <p>Görsel üretiliyor... (30-60 saniye sürebilir)</p>
                    </div>
                    <div id="kg_test_output"></div>
                </div>
            </div>
        </div>
        
        <script>
        document.querySelectorAll('input[name="kg_image_source"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                // Tüm satırları soluklaştır
                document.getElementById('dalle-key-row').style.opacity = '0.5';
                document.getElementById('stability-key-row').style.opacity = '0.5';
                document.getElementById('unsplash-key-row').style.opacity = '0.5';
                document.getElementById('pexels-key-row').style.opacity = '0.5';
                
                // Seçileni vurgula
                document.getElementById(this.value + '-key-row').style.opacity = '1';
            });
        });
        
        function kgTestImage() {
            var ingredient = document.getElementById('kg_test_ingredient').value;
            if (!ingredient) {
                alert('Lütfen bir malzeme adı girin');
                return;
            }
            
            document.getElementById('kg_test_result').style.display = 'block';
            document.getElementById('kg_test_loading').style.display = 'block';
            document.getElementById('kg_test_output').innerHTML = '';
            
            jQuery.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'kg_test_image_generation',
                    ingredient: ingredient,
                    nonce: '<?php echo wp_create_nonce('kg_test_image'); ?>'
                },
                success: function(response) {
                    document.getElementById('kg_test_loading').style.display = 'none';
                    if (response.success) {
                        var html = '<div style="text-align: center;">';
                        html += '<img src="' + response.data.url + '" style="max-width: 400px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">';
                        html += '<p style="margin-top: 15px;"><strong>Kaynak:</strong> ' + response.data.source + '</p>';
                        if (response.data.prompt) {
                            html += '<details style="text-align: left; margin-top: 10px;"><summary>Kullanılan Prompt</summary>';
                            html += '<pre style="white-space: pre-wrap; font-size: 11px; background: #fff; padding: 10px; border-radius: 4px; max-height: 200px; overflow: auto;">' + response.data.prompt + '</pre></details>';
                        }
                        html += '</div>';
                        document.getElementById('kg_test_output').innerHTML = html;
                    } else {
                        document.getElementById('kg_test_output').innerHTML = '<p style="color: red;">❌ Hata: ' + response.data + '</p>';
                    }
                },
                error: function() {
                    document.getElementById('kg_test_loading').style.display = 'none';
                    document.getElementById('kg_test_output').innerHTML = '<p style="color: red;">❌ Bağlantı hatası</p>';
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Handle AJAX request for testing image generation
     */
    public function handle_test_image_generation() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kg_test_image')) {
            wp_send_json_error('Güvenlik kontrolü başarısız.');
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetkiniz yok.');
            return;
        }
        
        // Get ingredient name
        $ingredient = isset($_POST['ingredient']) ? sanitize_text_field($_POST['ingredient']) : '';
        
        if (empty($ingredient)) {
            wp_send_json_error('Malzeme adı boş olamaz.');
            return;
        }
        
        try {
            $image_service = new \KG_Core\Services\ImageService();
            $result = $image_service->generateImage($ingredient);
            
            if ($result && !empty($result['url'])) {
                wp_send_json_success([
                    'url' => $result['url'],
                    'source' => $result['source'] ?? 'unknown',
                    'prompt' => $result['prompt'] ?? ''
                ]);
            } else {
                wp_send_json_error('Görsel oluşturulamadı. API ayarlarını kontrol edin.');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
