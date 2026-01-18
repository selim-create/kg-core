# Child Profile Avatar - Quick Reference

## API Endpoints

### Upload Avatar
```bash
curl -X POST https://yourdomain.com/wp-json/kg/v1/child-profiles/{UUID}/avatar \
  -H "Authorization: Bearer {JWT_TOKEN}" \
  -F "avatar=@photo.jpg"
```

### Get Avatar URL
```bash
curl https://yourdomain.com/wp-json/kg/v1/child-profiles/{UUID}/avatar \
  -H "Authorization: Bearer {JWT_TOKEN}"
```

### Delete Avatar
```bash
curl -X DELETE https://yourdomain.com/wp-json/kg/v1/child-profiles/{UUID}/avatar \
  -H "Authorization: Bearer {JWT_TOKEN}"
```

## Migration

### Run Migration (User Meta → Database)
```php
use KG_Core\Migration\ChildProfileMigrator;
$result = ChildProfileMigrator::migrate_all();
print_r($result);
```

### Verify Migration
```php
use KG_Core\Migration\ChildProfileMigrator;
$result = ChildProfileMigrator::verify_migration();
print_r($result);
```

## Security Features

✅ Private storage (not public)
✅ Signed URLs (15 min expiration)
✅ JWT authentication required
✅ Ownership validation
✅ Rate limiting (5 req/min)
✅ File validation (2MB, jpg/png/webp)
✅ MIME type verification

## File Structure

```
wp-content/uploads/private/child-avatars/
  └── {user_id}/
      └── {child_uuid}/
          ├── .htaccess
          └── avatar_{timestamp}.{ext}
```

## Database Table

```sql
CREATE TABLE wp_kg_child_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid VARCHAR(36) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  birth_date DATE NOT NULL,
  gender ENUM('male', 'female', 'unspecified'),
  allergies JSON,
  feeding_style ENUM('blw', 'puree', 'mixed'),
  photo_id BIGINT UNSIGNED,
  avatar_path VARCHAR(500),  -- NEW COLUMN
  kvkk_consent BOOLEAN,
  created_at DATETIME,
  updated_at DATETIME
);
```

## Testing

Run static analysis:
```bash
php tests/static-analysis-child-avatar.php
```

## Documentation

- Full API Docs: `docs/CHILD_AVATAR_API.md`
- Implementation: `CHILD_AVATAR_IMPLEMENTATION.md`
