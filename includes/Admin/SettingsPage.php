<?php
namespace KG_Core\Admin;

class SettingsPage {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
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
        $auto_generate = get_option('kg_auto_generate_on_missing', false);
        
        // Check if form was submitted
        if (isset($_POST['kg_ai_settings_submit'])) {
            check_admin_referer('kg_ai_settings_action', 'kg_ai_settings_nonce');
            echo '<div class="notice notice-success is-dismissible"><p>Ayarlar kaydedildi!</p></div>';
        }
        
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
                    
                    <!-- Image API Settings -->
                    <tr>
                        <th colspan="2">
                            <h2 style="margin-top: 30px;">🖼️ Görsel API Ayarları</h2>
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
                                <option value="unsplash" <?php selected($preferred_image_api, 'unsplash'); ?>>Unsplash Öncelikli</option>
                                <option value="pexels" <?php selected($preferred_image_api, 'pexels'); ?>>Pexels Öncelikli</option>
                            </select>
                            <p class="description">Önce hangi API'nin deneneceğini seçin. Bulunamazsa diğeri kullanılır.</p>
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
            
            <!-- Status Information -->
            <div style="margin-top: 40px; padding: 20px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3>📊 Durum</h3>
                <ul style="list-style: none; padding: 0;">
                    <li>🤖 AI: <?php echo !empty($ai_api_key) ? '<strong style="color: green;">✓ Yapılandırıldı</strong>' : '<strong style="color: red;">✗ Yapılandırılmadı</strong>'; ?></li>
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
        </style>
        <?php
    }
}
