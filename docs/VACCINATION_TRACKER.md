# Vaccination Tracker Module

## Overview

The Vaccination Tracker module is a comprehensive health management system integrated into the KidsGourmet platform. It provides automated vaccine scheduling, reminder notifications, side effect tracking, and complete vaccine record management based on the Turkish Ministry of Health vaccination calendar (TR_2026_v1).

## Features

### ✅ Core Features
- **Automated Vaccine Scheduling**: Calculate personalized vaccine schedules based on child's birth date
- **Mandatory & Private Vaccines**: Support for both government-mandated and optional vaccines
- **Reminder System**: Email notifications 3 days and 1 day before vaccine date
- **Overdue Tracking**: Automatic reminders for missed vaccines
- **Side Effect Monitoring**: Track and record vaccine side effects
- **Multi-Channel Notifications**: Email and Push notification support (Phase 2)
- **Admin Management**: Full CRUD interface for vaccines and email templates
- **Queue System**: Reliable notification delivery with retry mechanism

## Database Schema

The module creates 6 new tables:

1. **`kg_vaccine_master`** - Vaccine definitions (admin-managed)
2. **`kg_vaccine_records`** - User vaccine records
3. **`kg_email_templates`** - Email template management
4. **`kg_email_logs`** - Email delivery logs
5. **`kg_notification_queue`** - Notification queue
6. **`kg_push_subscriptions`** - Push notification subscriptions

## File Structure

```
kg-core/
├── includes/
│   ├── Database/
│   │   └── VaccinationSchema.php          # Database schema & table creation
│   ├── Health/
│   │   ├── VaccineManager.php             # Main vaccine business logic
│   │   ├── VaccineScheduleCalculator.php  # Schedule calculation engine
│   │   ├── VaccineRecordManager.php       # Vaccine record CRUD
│   │   └── SideEffectTracker.php          # Side effect tracking
│   ├── Notifications/
│   │   ├── NotificationManager.php        # Central notification handler
│   │   ├── EmailService.php               # SMTP email service
│   │   ├── TemplateEngine.php             # Email template rendering
│   │   └── NotificationQueue.php          # Queue management
│   ├── API/
│   │   ├── VaccineController.php          # Public vaccine endpoints
│   │   ├── NotificationController.php     # Notification preferences
│   │   └── AdminVaccineController.php     # Admin-only endpoints
│   ├── Admin/
│   │   ├── VaccineAdminPage.php           # Vaccine master data UI
│   │   ├── EmailTemplateAdminPage.php     # Email template UI
│   │   └── NotificationLogAdminPage.php   # Logs and queue viewer
│   └── Cron/
│       └── VaccineReminderCron.php        # Daily reminder cron job
└── data/
    └── vaccines/
        ├── tr_2026_v1.json                # Mandatory vaccines (18)
        └── private_vaccines.json          # Optional vaccines (12)
```

## API Endpoints

### Public Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/kg/v1/health/vaccines/master` | ❌ | Get all vaccine definitions |
| GET | `/kg/v1/health/vaccines/schedule-versions` | ❌ | Get available schedule versions |

### Authenticated Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/kg/v1/health/vaccines?child_id={id}` | ✅ | Get child's vaccine schedule |
| POST | `/kg/v1/health/vaccines/mark-done` | ✅ | Mark vaccine as done |
| POST | `/kg/v1/health/vaccines/update-status` | ✅ | Update vaccine status |
| POST | `/kg/v1/health/vaccines/add-private` | ✅ | Add optional vaccine |
| POST | `/kg/v1/health/vaccines/side-effects` | ✅ | Report side effects |
| GET | `/kg/v1/health/vaccines/upcoming?child_id={id}` | ✅ | Get upcoming vaccines |
| GET | `/kg/v1/health/vaccines/history?child_id={id}` | ✅ | Get vaccine history |
| GET | `/kg/v1/notifications/preferences` | ✅ | Get notification preferences |
| PUT | `/kg/v1/notifications/preferences` | ✅ | Update preferences |
| POST | `/kg/v1/notifications/push/subscribe` | ✅ | Subscribe to push |
| DELETE | `/kg/v1/notifications/push/unsubscribe` | ✅ | Unsubscribe from push |

### Admin Endpoints (requires `manage_options` capability)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/kg/v1/admin/vaccines` | List all vaccines |
| POST | `/kg/v1/admin/vaccines` | Create vaccine |
| PUT | `/kg/v1/admin/vaccines/{id}` | Update vaccine |
| DELETE | `/kg/v1/admin/vaccines/{id}` | Delete vaccine |
| GET | `/kg/v1/admin/email-templates` | List templates |
| POST | `/kg/v1/admin/email-templates` | Create template |
| PUT | `/kg/v1/admin/email-templates/{id}` | Update template |
| DELETE | `/kg/v1/admin/email-templates/{id}` | Delete template |
| POST | `/kg/v1/admin/email-templates/{id}/test` | Send test email |
| GET | `/kg/v1/admin/email-logs` | View email logs |
| GET | `/kg/v1/admin/notification-queue` | View notification queue |

## Usage Examples

### 1. Create Vaccine Schedule for a Child

```php
use KG_Core\Health\VaccineRecordManager;

$record_manager = new VaccineRecordManager();

// Create schedule when child is added
$result = $record_manager->create_schedule_for_child(
    $user_id,
    $child_id,
    '2024-10-16', // birth_date
    false         // include_private (false = mandatory only)
);

if (!is_wp_error($result)) {
    // $result = number of vaccine records created
    echo "Created {$result} vaccine records";
}
```

### 2. Get Child's Vaccines

```php
$vaccines = $record_manager->get_child_vaccines($child_id, 'upcoming');

foreach ($vaccines as $vaccine) {
    echo "{$vaccine['vaccine_name']}: {$vaccine['scheduled_date']}\n";
}
```

### 3. Mark Vaccine as Done

```php
$result = $record_manager->mark_as_done(
    $record_id,
    '2025-01-15',  // actual_date
    'No issues'    // notes
);
```

### 4. Send Notification

```php
use KG_Core\Notifications\NotificationManager;

$manager = new NotificationManager();

$placeholders = [
    'parent_name' => 'Ayşe Yılmaz',
    'child_name' => 'Ali',
    'vaccine_name' => 'Hepatit B (2. Doz)',
    'scheduled_date' => '15 Ocak 2026'
];

$manager->send_immediate_notification(
    $user_id,
    'vaccine_reminder_3day',
    $placeholders
);
```

## Email Templates

The module includes 4 pre-configured email templates:

1. **vaccine_reminder_3day** - Reminder 3 days before vaccine
2. **vaccine_reminder_1day** - Reminder 1 day before vaccine
3. **vaccine_overdue** - Overdue vaccine notification
4. **vaccine_side_effect_followup** - Post-vaccine follow-up

### Available Placeholders

- `{{parent_name}}` - Parent's name
- `{{child_name}}` - Child's name
- `{{vaccine_name}}` - Vaccine name
- `{{vaccine_code}}` - Vaccine code
- `{{scheduled_date}}` - Scheduled date
- `{{actual_date}}` - Actual vaccination date
- `{{days_remaining}}` - Days until vaccine
- `{{app_url}}` - Application URL
- `{{unsubscribe_url}}` - Unsubscribe link

## Cron Job

The `VaccineReminderCron` runs daily at 2:00 AM and:

1. Sends 3-day advance reminders
2. Sends 1-day advance reminders
3. Sends overdue notifications (3 days past due)
4. Sends side effect follow-up (1 day after vaccination)

## Admin Interface

Access via WordPress Admin:
- **KG Health > Aşı Yönetimi** - Manage vaccine definitions
- **KG Health > E-posta Şablonları** - Manage email templates
- **KG Health > Bildirim Logları** - View logs and queue

## Vaccine Data

### Mandatory Vaccines (18)
Based on Turkish Ministry of Health calendar:
- Hepatit B (3 doses)
- BCG
- 5'li Karma/DaBT-İPA-Hib (3 doses + booster)
- Konjuge Pnömokok (3 doses)
- Oral Polio (2 doses)
- KKK (2 doses)
- Hepatit A (2 doses)
- 4'lü Karma (booster)

### Private Vaccines (12)
Optional vaccines parents can add:
- Rotavirus (Rotarix 2 doses / RotaTeq 3 doses)
- Meningokok ACWY
- Meningokok B (3 doses)
- Varicella (2 doses)
- Influenza (annual)

## Security

- All endpoints use JWT authentication
- Admin endpoints require `manage_options` capability
- CSRF protection with WordPress nonces
- SQL injection prevention via `$wpdb->prepare()`
- Input sanitization and validation
- Output escaping in admin UI

## Installation

The module auto-installs on plugin activation:

1. Creates database tables
2. Seeds email templates
3. Loads vaccine master data from JSON
4. Registers cron schedule

## Testing

Run syntax checks:
```bash
cd /home/runner/work/kg-core/kg-core
php -l includes/Database/VaccinationSchema.php
php -l includes/Health/*.php
php -l includes/Notifications/*.php
php -l includes/API/VaccineController.php
```

## Future Enhancements (Phase 2)

- [ ] Push notification implementation
- [ ] Advanced side effect analytics
- [ ] PDF export of vaccination records
- [ ] Multi-language support
- [ ] Integration with health providers
- [ ] Vaccine inventory tracking
- [ ] SMS notifications

## Support

For issues or questions:
- Check logs in WordPress Debug Log
- Review admin notification logs
- Contact: [Your Support Email]

---

**Version**: 1.0.0  
**Last Updated**: January 2026  
**License**: Proprietary
