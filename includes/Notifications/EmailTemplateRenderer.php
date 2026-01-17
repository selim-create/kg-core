<?php
namespace KG_Core\Notifications;

/**
 * EmailTemplateRenderer - Modern HTML email template wrapper
 * 
 * Wraps email content in a beautiful, responsive HTML template
 * with category-specific colors and professional styling
 */
class EmailTemplateRenderer {
    
    // Social media URLs - update these if URLs change
    const SOCIAL_INSTAGRAM = 'https://instagram.com/kidsgourmet';
    const SOCIAL_FACEBOOK = 'https://facebook.com/kidsgourmet';
    const SOCIAL_TWITTER = 'https://twitter.com/kidsgourmet';
    const SOCIAL_YOUTUBE = 'https://youtube.com/@kidsgourmet';
    
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
        
        // Calculate lighter shade for gradient
        $accent_light = self::adjust_brightness($accent_color, 0.85);
        
        return '
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>KidsGourmet</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f5f5f5; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    <!-- Email Client Preview Text (Hidden but readable by email clients) -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        KidsGourmet - Bebeğiniz için en iyi beslenme ve sağlık rehberi
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                <!-- Main Container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 600px; width: 100%;">
                    
                    <!-- Header with Gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, ' . $accent_color . ' 0%, ' . $accent_light . ' 100%); padding: 40px 30px; text-align: center;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center">
                                        <!-- Logo/Brand -->
                                        <div style="background: rgba(255,255,255,0.95); border-radius: 12px; padding: 15px 25px; display: inline-block; margin-bottom: 10px;">
                                            <h1 style="margin: 0; color: ' . $accent_color . '; font-size: 26px; font-weight: 700; letter-spacing: -0.5px;">🍎 KidsGourmet</h1>
                                        </div>
                                        <p style="margin: 10px 0 0; color: rgba(255,255,255,0.95); font-size: 14px; font-weight: 500;">Bebeğiniz için en iyi beslenme ve sağlık rehberi</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            ' . $content . '
                        </td>
                    </tr>
                    
                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 30px;">
                            <div style="border-top: 2px solid #f0f0f0; margin: 0;"></div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background: linear-gradient(to bottom, #ffffff 0%, #f8f9fa 100%); padding: 30px 30px 20px;">
                            
                            <!-- Social Media Icons -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0 0 15px; color: #666; font-size: 13px; font-weight: 600;">Bizi takip edin</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="display: inline-block;">
                                            <tr>
                                                <td style="padding: 0 8px;">
                                                    <a href="' . self::SOCIAL_INSTAGRAM . '" style="display: inline-block; width: 36px; height: 36px; background: #E1306C; border-radius: 50%; text-align: center; line-height: 36px; color: white; text-decoration: none; font-size: 18px;">📷</a>
                                                </td>
                                                <td style="padding: 0 8px;">
                                                    <a href="' . self::SOCIAL_FACEBOOK . '" style="display: inline-block; width: 36px; height: 36px; background: #1877F2; border-radius: 50%; text-align: center; line-height: 36px; color: white; text-decoration: none; font-size: 18px;">👍</a>
                                                </td>
                                                <td style="padding: 0 8px;">
                                                    <a href="' . self::SOCIAL_TWITTER . '" style="display: inline-block; width: 36px; height: 36px; background: #1DA1F2; border-radius: 50%; text-align: center; line-height: 36px; color: white; text-decoration: none; font-size: 18px;">🐦</a>
                                                </td>
                                                <td style="padding: 0 8px;">
                                                    <a href="' . self::SOCIAL_YOUTUBE . '" style="display: inline-block; width: 36px; height: 36px; background: #FF0000; border-radius: 50%; text-align: center; line-height: 36px; color: white; text-decoration: none; font-size: 18px;">▶️</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Footer Text -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0 0 12px; color: #666; font-size: 13px; line-height: 1.6;">
                                            Bu e-postayı <strong style="color: ' . $accent_color . ';">KidsGourmet</strong> üzerinden aldınız.
                                        </p>
                                        <p style="margin: 0 0 15px; color: #999; font-size: 12px; line-height: 1.8;">
                                            <a href="{{unsubscribe_url}}" style="color: #666; text-decoration: none; font-weight: 500; border-bottom: 1px solid #ddd;">📧 Bildirim Tercihlerim</a> · 
                                            <a href="https://kidsgourmet.com.tr" style="color: #666; text-decoration: none; font-weight: 500; border-bottom: 1px solid #ddd;">🌐 kidsgourmet.com.tr</a>
                                        </p>
                                        <p style="margin: 15px 0 0; padding-top: 15px; border-top: 1px solid #e0e0e0; color: #bbb; font-size: 11px;">
                                            © ' . date('Y') . ' KidsGourmet · Tüm hakları saklıdır · 🇹🇷 Türkiye\'de yapıldı
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * Adjust color brightness for gradient effect
     * 
     * @param string $hex Hex color code
     * @param float $percent Brightness adjustment (0.5 = darker, 1.5 = lighter)
     * @return string Adjusted hex color
     */
    private static function adjust_brightness($hex, $percent) {
        // Remove # if present
        $hex = str_replace('#', '', $hex);
        
        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Adjust brightness with clamping helper
        $r = self::clamp_rgb($r * $percent);
        $g = self::clamp_rgb($g * $percent);
        $b = self::clamp_rgb($b * $percent);
        
        // Convert back to hex
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) 
                  . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) 
                  . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
    
    /**
     * Clamp RGB value to valid range (0-255)
     * 
     * @param float $value RGB value to clamp
     * @return int Clamped integer value
     */
    private static function clamp_rgb($value) {
        return (int)min(255, max(0, round($value)));
    }
}
