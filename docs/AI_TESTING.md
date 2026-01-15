# AI-Powered Ingredient Management System - Testing Guide

## Prerequisites
- WordPress installed and running
- KG Core plugin activated
- Admin user with `manage_options` capability

## 1. Configure AI Settings

### Access Settings Page
1. Log in to WordPress admin
2. Navigate to **Malzeme Rehberi** > **⚙️ AI Ayarları**
3. Verify the settings page loads correctly

### Configure API Keys

#### Option A: OpenAI (Recommended for Turkish)
1. Select **AI Sağlayıcı**: OpenAI (GPT-4)
2. Enter **API Key**: Your OpenAI API key (starts with `sk-`)
3. Select **Model**: `GPT-4o Mini` (cost-effective)
4. Save settings

#### Option B: Anthropic Claude
1. Select **AI Sağlayıcı**: Anthropic (Claude)
2. Enter **API Key**: Your Anthropic API key
3. Select **Model**: `Claude 3.5 Sonnet`
4. Save settings

#### Option C: Google Gemini
1. Select **AI Sağlayıcı**: Google Gemini
2. Enter **API Key**: Your Google AI API key
3. Select **Model**: `Gemini 1.5 Flash`
4. Save settings

### Configure Image APIs
1. **Unsplash API Key**: Enter your Unsplash Access Key
   - Get it from: https://unsplash.com/developers
2. **Pexels API Key**: Enter your Pexels API key
   - Get it from: https://www.pexels.com/api/
3. **Tercih Edilen API**: Select `Unsplash Öncelikli`
4. Save settings

### Enable Auto-Generation (Optional)
1. Check **"Tarif kaydedilirken eksik malzemeleri otomatik oluştur"**
2. Save settings

### Verify Status
- Check the **Durum** section at bottom of page:
  - 🤖 AI: ✓ Yapılandırıldı
  - 🖼️ Unsplash: ✓ Yapılandırıldı
  - 🖼️ Pexels: ✓ Yapılandırıldı

---

## 2. Test Single Ingredient Generation via REST API

### Using cURL
```bash
# Replace with your WordPress admin credentials
TOKEN=$(curl -X POST "http://localhost/wp-json/kg/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"your-password"}' \
  | jq -r '.token')

# Generate a single ingredient
curl -X POST "http://localhost/wp-json/kg/v1/ai/generate-ingredient" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Havuç"}'
```

### Expected Response
```json
{
  "success": true,
  "post_id": 123,
  "message": "Malzeme başarıyla oluşturuldu (taslak olarak)"
}
```

### Verify in WordPress Admin
1. Go to **Malzeme Rehberi** > **Tüm Malzemeler**
2. Look for "Havuç" with status **Taslak**
3. Open the ingredient and verify:
   - ✓ Title populated
   - ✓ Content with 3-4 paragraphs
   - ✓ Excerpt (short description)
   - ✓ Featured image attached
   - ✓ Meta fields: Başlangıç Yaşı, Alerji Riski, etc.
   - ✓ Nutrition values: Kalori, Protein, etc.
   - ✓ Allergens assigned (if any)

---

## 3. Test Bulk Ingredient Seeder

### Access Bulk Seeder
1. Navigate to **Malzeme Rehberi** > **🤖 Toplu AI Oluştur**
2. Verify the page loads with predefined packages

### Test Predefined Package
1. Click **"Oluştur"** on **🍎 Meyveler (25 adet)** package
2. Confirm the alert: "25 malzeme oluşturulacak"
3. Observe:
   - Progress bar updates in real-time
   - Log shows each ingredient being processed
   - ✅ Success messages for new ingredients
   - ⚠️ "Zaten mevcut" for existing ones
   - 2-second delay between items (rate limiting)

### Test Custom List
1. In **"✍️ Özel Liste"** section, enter:
   ```
   Domates
   Salatalık
   Biber
   ```
2. Click **"Listeyi Oluştur"**
3. Confirm and observe progress

### Verify Results
1. Go to **Malzeme Rehberi** > **Tüm Malzemeler**
2. Filter by status: **Taslak**
3. Verify all generated ingredients are present
4. Open a few random ones to verify quality:
   - Content in Turkish
   - Proper nutrition values
   - Images downloaded
   - Allergens assigned

---

## 4. Test Auto-Generation on Recipe Save

### Prerequisites
- Auto-generation must be enabled in AI Settings
- Have a recipe post type ready

### Test Workflow
1. Go to **Tarifler** > **Yeni Tarif Ekle**
2. Add recipe details
3. In **Malzemeler** section, add an ingredient that doesn't exist:
   - Miktar: 2
   - Birim: adet
   - Malzeme Adı: "Yeni Malzeme" (make up a name)
4. Save the recipe
5. Check WordPress cron:
   ```bash
   wp cron event list --path=/path/to/wordpress
   ```
6. Look for `kg_generate_ingredient` event
7. Trigger cron manually (for testing):
   ```bash
   wp cron event run kg_generate_ingredient --path=/path/to/wordpress
   ```
8. Verify the new ingredient was created as a draft

---

## 5. Test AI Status Endpoint

```bash
curl -X GET "http://localhost/wp-json/kg/v1/ai/status" \
  -H "Authorization: Bearer $TOKEN"
```

### Expected Response
```json
{
  "provider": "openai",
  "configured": true,
  "image_apis": {
    "unsplash": true,
    "pexels": true
  },
  "auto_generate_enabled": true
}
```

---

## 6. Test Error Handling

### Test Missing API Key
1. Go to AI Settings
2. Clear the AI API Key field
3. Save settings
4. Try to generate an ingredient
5. Expected: Error message "AI API anahtarı ayarlanmamış"

### Test Invalid API Key
1. Enter a fake API key: `sk-fake123`
2. Try to generate "Test Malzeme"
3. Expected: Error from AI provider (401 or API error)

### Test Duplicate Ingredient
1. Generate "Elma"
2. Try to generate "Elma" again
3. Expected: "Malzeme zaten mevcut: Elma"

### Test Rate Limiting
1. Start bulk seeding with 10 items
2. Observe 2-second delay between each request
3. Click "Durdur" button
4. Expected: Process stops after current item completes

---

## 7. Verify Security

### Test Permission Checks
1. Log out of admin
2. Try to access AI Settings page
3. Expected: "Bu sayfaya erişim yetkiniz yok"

### Test REST API Authorization
```bash
# Without token - should fail
curl -X POST "http://localhost/wp-json/kg/v1/ai/generate-ingredient" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test"}'
```
Expected: 401 Unauthorized or permission error

### Test AJAX Nonce
1. Open browser console
2. Try to call AJAX without proper nonce
3. Expected: "Invalid nonce" error

---

## 8. Check WordPress Logs

```bash
# View error logs
tail -f /path/to/wordpress/wp-content/debug.log
```

Look for:
- `KG Core: Ingredient generated successfully - [name] (ID: [id])`
- `KG Core AI Error: [error message]`
- `KG Core Unsplash Error: [error]`
- `KG Core Pexels Error: [error]`

---

## 9. Performance Testing

### Single Ingredient Generation Time
- Expected: 5-15 seconds (depends on AI provider)
  - AI response: 2-8 seconds
  - Image download: 2-5 seconds
  - WordPress save: 1-2 seconds

### Bulk Generation
- 10 ingredients: ~2-3 minutes (with 2s rate limit)
- 25 ingredients: ~5-8 minutes
- 100 ingredients: ~20-30 minutes

---

## 10. Quality Checks

### AI Content Quality
- [ ] Content is in Turkish
- [ ] No markdown formatting in final output
- [ ] Age recommendations appropriate (4-36 months)
- [ ] Nutrition values are realistic
- [ ] Benefits are baby-food specific
- [ ] FAQ questions are relevant

### Image Quality
- [ ] Images are landscape orientation
- [ ] Images are food-related and appropriate
- [ ] Image credits are saved in post meta
- [ ] Featured image is set correctly

### Metadata Completeness
- [ ] Start age is numeric (4, 6, 8, 12, etc.)
- [ ] Allergy risk is one of: Düşük, Orta, Yüksek
- [ ] Season is appropriate: İlkbahar, Yaz, Sonbahar, Kış, Tüm Yıl
- [ ] Storage tips are practical
- [ ] Preparation tips are for babies

---

## Common Issues & Solutions

### Issue: "No image found"
- **Cause**: Image API returned no results
- **Solution**: Check image search query is in English, try different API

### Issue: "JSON parse error"
- **Cause**: AI returned invalid JSON
- **Solution**: Check AI prompt, try different model, check AI provider status

### Issue: "Cron not running"
- **Cause**: WordPress cron disabled or not triggered
- **Solution**: 
  ```bash
  wp cron event run kg_generate_ingredient --path=/path/to/wordpress
  ```
  Or configure real cron job

### Issue: Rate limit exceeded
- **Cause**: Too many API requests
- **Solution**: Wait 60 seconds, increase delay in bulk seeder

---

## Success Criteria

✅ All 8 tests pass
✅ No PHP errors in debug.log
✅ AI-generated content is high quality and in Turkish
✅ Images download successfully
✅ All meta fields populate correctly
✅ Allergen taxonomy assignments work
✅ Bulk seeder completes without errors
✅ Auto-generation triggers on recipe save
✅ Security checks prevent unauthorized access
✅ Performance is acceptable (< 15s per ingredient)

---

## Next Steps After Testing

1. **Review Generated Content**: Manually review a few AI-generated ingredients for quality
2. **Adjust Prompts**: If content quality is poor, modify prompt in `AIService.php`
3. **Production Deployment**: Once satisfied, use in production with real API keys
4. **Monitor Usage**: Track API usage and costs
5. **Batch Generation**: Use bulk seeder to populate ingredient database

---

## Support

For issues or questions:
1. Check WordPress debug.log
2. Verify API keys are valid
3. Test API endpoints manually
4. Check API provider status pages
