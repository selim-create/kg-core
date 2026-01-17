<?php
namespace KG_Core\Notifications;

/**
 * EmailTemplateRenderer - Modern HTML email template wrapper
 * 
 * Wraps email content in a beautiful, responsive HTML template
 * with category-specific colors and professional styling
 */
class EmailTemplateRenderer {
    
    // Default social media URLs (used as fallback)
    const SOCIAL_INSTAGRAM = 'https://instagram.com/kidsgourmet';
    const SOCIAL_YOUTUBE = 'https://youtube.com/@kidsgourmet';
    const SOCIAL_TWITTER = 'https://twitter.com/kidsgourmet';
    const SOCIAL_TIKTOK = 'https://tiktok.com/@kidsgourmet';
    const SOCIAL_PINTEREST = 'https://pinterest.com/kidsgourmet';
    const SOCIAL_FACEBOOK = 'https://facebook.com/kidsgourmet';
    
    /**
     * Get social media URLs from WordPress options with fallback to defaults
     * 
     * @return array Social media URLs
     */
    public static function get_social_urls() {
        return [
            'instagram' => get_option('kg_social_instagram', self::SOCIAL_INSTAGRAM),
            'youtube' => get_option('kg_social_youtube', self::SOCIAL_YOUTUBE),
            'twitter' => get_option('kg_social_twitter', self::SOCIAL_TWITTER),
            'tiktok' => get_option('kg_social_tiktok', self::SOCIAL_TIKTOK),
            'pinterest' => get_option('kg_social_pinterest', self::SOCIAL_PINTEREST),
            'facebook' => get_option('kg_social_facebook', self::SOCIAL_FACEBOOK),
        ];
    }
    
    /**
     * Wrap content in HTML email template
     * 
     * @param string $content Email content (HTML)
     * @param string $category Email category (vaccination, growth, nutrition, system, marketing)
     * @return string Complete HTML email
     */
    public static function wrap_content($content, $category = 'system') {
        // Get social media URLs from options
        $social_urls = self::get_social_urls();
        
        // Get logo URL from options
        $logo_url = get_option('kg_email_logo', '');
        
        // Primary brand color
        $primary_color = '#FF6B35';
        
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
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f8fafc; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    <!-- Email Client Preview Text (Hidden but readable by email clients) -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        KidsGourmet - Bebeğiniz için en iyi beslenme ve sağlık rehberi
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f8fafc;">
        <tr>
            <td align="center" style="padding: 40px 10px;">
                <!-- Main Container -->
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 600px; width: 100%;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px 30px 30px; text-align: center; border-bottom: 3px solid ' . $primary_color . ';">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center">
                                        <!-- Logo -->
                                        ' . (
                                            $logo_url 
                                            ? '<img src="' . esc_url($logo_url) . '" alt="KidsGourmet" style="max-height: 60px; display: block; margin: 0 auto;">'
                                            : '<h1 style="margin: 0; color: ' . $primary_color . '; font-size: 32px; font-weight: 700; letter-spacing: -0.5px;">🍎 KidsGourmet</h1>'
                                        ) . '
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px; color: #1e293b; font-size: 16px; line-height: 1.6;">
                            ' . $content . '
                        </td>
                    </tr>
                    
                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 30px;">
                            <div style="border-top: 1px solid #e2e8f0; margin: 0;"></div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px 30px 20px;">
                            
                            <!-- Social Media Icons -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0 0 15px; color: #64748b; font-size: 13px; font-weight: 600;">Bizi takip edin</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="display: inline-block;">
                                            <tr>
                                                <td style="padding: 0 6px;">
                                                    <a href="' . esc_url($social_urls['instagram']) . '" style="display: inline-block; width: 40px; height: 40px; background: #E1306C; border-radius: 50%; text-align: center; line-height: 40px; color: white; text-decoration: none; font-size: 16px; font-weight: 700; font-family: Arial, sans-serif;">IG</a>
                                                </td>
                                                <td style="padding: 0 6px;">
                                                    <a href="' . esc_url($social_urls['youtube']) . '" style="display: inline-block; width: 40px; height: 40px; background: #FF0000; border-radius: 50%; text-align: center; line-height: 40px; color: white; text-decoration: none; font-size: 16px; font-weight: 700; font-family: Arial, sans-serif;">YT</a>
                                                </td>
                                                <td style="padding: 0 6px;">
                                                    <a href="' . esc_url($social_urls['twitter']) . '" style="display: inline-block; width: 40px; height: 40px; background: #000000; border-radius: 50%; text-align: center; line-height: 40px; color: white; text-decoration: none; font-size: 16px; font-weight: 700; font-family: Arial, sans-serif;">X</a>
                                                </td>
                                                <td style="padding: 0 6px;">
                                                    <a href="' . esc_url($social_urls['tiktok']) . '" style="display: inline-block; width: 40px; height: 40px; background: #000000; border-radius: 50%; text-align: center; line-height: 40px; color: white; text-decoration: none; font-size: 16px; font-weight: 700; font-family: Arial, sans-serif;">TT</a>
                                                </td>
                                                <td style="padding: 0 6px;">
                                                    <a href="' . esc_url($social_urls['pinterest']) . '" style="display: inline-block; width: 40px; height: 40px; background: #E60023; border-radius: 50%; text-align: center; line-height: 40px; color: white; text-decoration: none; font-size: 18px; font-weight: 700; font-family: Arial, sans-serif;">P</a>
                                                </td>
                                                <td style="padding: 0 6px;">
                                                    <a href="' . esc_url($social_urls['facebook']) . '" style="display: inline-block; width: 40px; height: 40px; background: #1877F2; border-radius: 50%; text-align: center; line-height: 40px; color: white; text-decoration: none; font-size: 18px; font-weight: 700; font-family: Arial, sans-serif;">f</a>
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
                                        <p style="margin: 0 0 12px; color: #64748b; font-size: 13px; line-height: 1.6;">
                                            Bu e-postayı <strong style="color: ' . $primary_color . ';">KidsGourmet</strong> üzerinden aldınız.
                                        </p>
                                        <p style="margin: 0 0 15px; color: #64748b; font-size: 12px; line-height: 1.8;">
                                            <a href="{{unsubscribe_url}}" style="color: ' . $primary_color . '; text-decoration: none; font-weight: 500;">📧 Bildirim Tercihlerim</a> · 
                                            <a href="https://kidsgourmet.com.tr" style="color: ' . $primary_color . '; text-decoration: none; font-weight: 500;">🌐 kidsgourmet.com.tr</a>
                                        </p>
                                        <p style="margin: 15px 0 0; padding-top: 15px; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 11px;">
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
