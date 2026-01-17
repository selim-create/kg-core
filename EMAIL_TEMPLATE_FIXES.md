# Email Template System Fixes

## Overview
This document describes the fixes applied to the KidsGourmet email template system to resolve critical issues with template seeding, preview/test functionality, and social media URL configuration.

## Problems Fixed

### 1. Missing Email Templates (UPSERT Logic)
**Problem:** Only 4 vaccine templates appeared in admin panel because the seeding logic would exit early if any templates existed.

**Solution:** Updated `VaccinationSchema::seed_email_templates()` to use UPSERT logic:
- Check each template individually by `template_key`
- Only insert if template doesn't exist
- All 23 templates across 5 categories are now properly seeded

**Files Changed:**
- `includes/Database/VaccinationSchema.php`

### 2. Test Emails Without HTML Wrapper
**Problem:** Test emails were sent as plain HTML without the modern email template wrapper (header, footer, social media links).

**Solution:** Updated `EmailTemplateAdminPage::handle_test_email()` to:
- Wrap email body with `EmailTemplateRenderer::wrap_content()`
- Apply category-specific styling
- Include unsubscribe URL replacement

**Files Changed:**
- `includes/Admin/EmailTemplateAdminPage.php`

### 3. Preview Without HTML Wrapper
**Problem:** Email preview showed raw HTML content without the full template wrapper.

**Solution:** Updated `EmailTemplateAdminPage::render_preview()` to:
- Wrap preview content with `EmailTemplateRenderer::wrap_content()`
- Display in iframe for safe and accurate rendering
- Show full email design with header, footer, and social links

**Files Changed:**
- `includes/Admin/EmailTemplateAdminPage.php`

### 4. Hardcoded Social Media URLs
**Problem:** Social media URLs were hardcoded constants, making them difficult to update.

**Solution:** Made social URLs configurable via WordPress options:
- Added `EmailTemplateRenderer::get_social_urls()` method
- Retrieves URLs from WordPress options with fallback to defaults
- Created new admin page `SocialMediaSettings` for easy configuration

**Files Changed:**
- `includes/Notifications/EmailTemplateRenderer.php`
- `includes/Admin/SocialMediaSettings.php` (new)
- `kg-core.php` (registration)

## Email Template Categories

The system now properly seeds all 23 email templates across 5 categories:

### Vaccination (4 templates)
- `vaccine_reminder_3day` - 3 days before vaccine
- `vaccine_reminder_1day` - 1 day before vaccine
- `vaccine_overdue` - Vaccine is overdue
- `vaccine_side_effect_followup` - Post-vaccine follow-up

### Growth (4 templates)
- `growth_measurement_reminder` - Monthly measurement reminder
- `growth_percentile_alert` - Percentile change alert
- `growth_milestone_reached` - Milestone achievement
- `growth_weekly_summary` - Weekly growth summary

### Nutrition (5 templates)
- `nutrition_new_food_suggestion` - New food suggestions
- `nutrition_allergy_reminder` - 3-day rule reminder
- `nutrition_weekly_menu` - Weekly menu
- `nutrition_milestone_unlocked` - New food group unlocked
- `nutrition_daily_tip` - Daily nutrition tip

### System (6 templates)
- `system_welcome` - Welcome email
- `system_password_reset` - Password reset
- `system_email_verification` - Email verification
- `system_account_deletion` - Account deletion confirmation
- `system_subscription_expiring` - Subscription expiring
- `system_data_export_ready` - Data export ready

### Marketing (4 templates)
- `marketing_newsletter` - Weekly newsletter
- `marketing_new_feature` - New feature announcement
- `marketing_birthday_greeting` - Child birthday greeting
- `marketing_anniversary` - KidsGourmet anniversary

## Admin Interface

### Social Media Settings Page
Access via: **KG Settings → Sosyal Medya**

Configure social media URLs:
- 📷 Instagram
- 👍 Facebook
- 🐦 Twitter/X
- ▶️ YouTube

URLs are automatically applied to all email templates. Empty fields fallback to defaults.

### Email Templates Page
Access via: **KG Settings → E-posta Şablonları**

Features:
- View all 23+ email templates
- Filter by category
- Preview with full HTML wrapper (in iframe)
- Send test emails with wrapper applied
- Edit template content and placeholders

## Testing

Run the test suite:
```bash
php tests/test-email-template-fixes.php
```

Expected output: 5/5 tests passed ✅

## Technical Details

### UPSERT Logic
```php
foreach ($templates as $template) {
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE template_key = %s",
        $template['template_key']
    ));
    
    if (!$exists) {
        $wpdb->insert($table, $template);
    }
}
```

### HTML Wrapper Integration
```php
// Test email
$wrapped_body = EmailTemplateRenderer::wrap_content($body, $template['category']);
wp_mail($email, $subject, $wrapped_body, $headers);

// Preview
$full_preview = EmailTemplateRenderer::wrap_content($preview_html, $template['category']);
echo '<iframe srcdoc="' . esc_attr($full_preview) . '"></iframe>';
```

### Social URLs Configuration
```php
public static function get_social_urls() {
    return [
        'instagram' => get_option('kg_social_instagram', self::SOCIAL_INSTAGRAM),
        'facebook' => get_option('kg_social_facebook', self::SOCIAL_FACEBOOK),
        'twitter' => get_option('kg_social_twitter', self::SOCIAL_TWITTER),
        'youtube' => get_option('kg_social_youtube', self::SOCIAL_YOUTUBE),
    ];
}
```

## Migration Notes

### Existing Installations
When plugin is activated or updated:
1. `VaccinationSchema::seed_email_templates()` runs automatically
2. Missing templates are added (UPSERT logic)
3. Existing templates are preserved
4. No data loss occurs

### Database Changes
No schema changes required. Uses existing `kg_email_templates` table.

### WordPress Options Added
- `kg_social_instagram`
- `kg_social_facebook`
- `kg_social_twitter`
- `kg_social_youtube`

## Backwards Compatibility

All changes are backwards compatible:
- Existing templates remain unchanged
- Default social URLs maintained as constants
- Admin interface enhanced, not replaced
- API endpoints unaffected

## Future Improvements

Potential enhancements:
- Bulk template export/import
- Template versioning
- A/B testing for email templates
- Email preview in multiple clients
- Template variables autocomplete
- Schedule template updates
