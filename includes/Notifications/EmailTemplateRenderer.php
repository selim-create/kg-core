<?php
namespace KG_Core\Notifications;

/**
 * EmailTemplateRenderer - Modern HTML email template wrapper
 * 
 * Wraps email content in a beautiful, responsive HTML template
 * with category-specific colors and professional styling
 */
class EmailTemplateRenderer {
    
    /**
     * Wrap content in HTML email template
     * 
     * @param string $content Email content (HTML)
     * @param string $category Email category (vaccination, growth, nutrition, system, marketing)
     * @return string Complete HTML email
     */
    public static function wrap_content($content, $category = 'system') {
        $category_colors = [
            'vaccination' => '#4CAF50', // Green
            'growth' => '#2196F3',      // Blue
            'nutrition' => '#FF9800',   // Orange
            'system' => '#607D8B',      // Gray
            'marketing' => '#E91E63'    // Pink
        ];
        
        $accent_color = $category_colors[$category] ?? '#FF6B35';
        
        return '
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KidsGourmet</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 600px;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, ' . $accent_color . ' 0%, ' . $accent_color . 'dd 100%); padding: 30px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;">KidsGourmet</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            ' . $content . '
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px 40px; text-align: center; border-top: 1px solid #eee;">
                            <p style="margin: 0 0 10px; color: #666; font-size: 14px;">
                                Bu e-postayı <strong>KidsGourmet</strong> üzerinden aldınız.
                            </p>
                            <p style="margin: 0; color: #999; font-size: 12px;">
                                <a href="{{unsubscribe_url}}" style="color: #999; text-decoration: none;">Bildirim tercihlerini yönet</a> · 
                                <a href="https://kidsgourmet.com.tr" style="color: #999; text-decoration: none;">kidsgourmet.com.tr</a>
                            </p>
                            <p style="margin: 15px 0 0; color: #ccc; font-size: 11px;">
                                © ' . date('Y') . ' KidsGourmet. Tüm hakları saklıdır.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}
