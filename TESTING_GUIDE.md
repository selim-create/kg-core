# Testing Guide - Image Generation System

## Prerequisites

Before testing, ensure you have:
1. WordPress environment set up
2. KG Core plugin activated
3. API key for either:
   - OpenAI (for DALL-E 3)
   - Stability AI (for Stable Diffusion)

## Test Scenarios

### Scenario 1: DALL-E 3 Image Generation

**Setup:**
1. Go to Admin → Malzemeler → ⚙️ AI Ayarları
2. Ensure `kg_ai_api_key` is configured with your OpenAI API key
3. Set "Görsel Sağlayıcı" to "DALL-E 3 (OpenAI)"
4. Save settings

**Test Steps:**
1. Scroll to "🧪 Görsel Oluşturma Test Aracı"
2. Enter test ingredient: `muz` (banana)
3. Click "🎨 Test Et"
4. Wait for image generation (~30-60 seconds)

**Expected Results:**
- ✅ Image displays: Whole yellow banana on white background
- ✅ Prompt displays and contains: "whole ripe yellow banana", "raw and uncut", "pure white background"
- ✅ Prompt does NOT contain: "baby food", "puree", "mashed", "cooking"
- ❌ Should NOT show: Banana puree, mashed banana, or banana in a bowl

**Additional Test Cases:**
- `havuç` → Fresh carrots with green tops
- `elma` → Whole red apple
- `avokado` → Whole avocado (not sliced)
- `tavuk göğsü` → Raw chicken breast
- `pirinç` → Raw rice grains

### Scenario 2: Stable Diffusion Image Generation

**Setup:**
1. Go to Admin → Malzemeler → ⚙️ AI Ayarları
2. Enter `kg_stability_api_key` with your Stability AI API key
3. Set "Görsel Sağlayıcı" to "Stable Diffusion (Stability AI)"
4. Save settings

**Test Steps:**
1. Scroll to "🧪 Görsel Oluşturma Test Aracı"
2. Enter test ingredient: `domates` (tomato)
3. Click "🎨 Test Et"
4. Wait for image generation (~30-60 seconds)

**Expected Results:**
- ✅ Image displays: Fresh red tomatoes on white background
- ✅ Prompt displays with "fresh red tomatoes", "raw", "unprocessed"
- ✅ Negative prompt displays with: "cooked", "puree", "baby food", "camera", "kitchen"
- ❌ Should NOT show: Cooked tomatoes, tomato sauce, or kitchen equipment

### Scenario 3: Category Detection

Test different ingredient categories to verify correct prompt templates:

**Fruits (`elma`, `muz`, `portakal`):**
- Template should mention: "single fresh", "whole fruit", "vibrant natural colors"
- Should emphasize: Raw, uncut, whole fruit

**Vegetables (`havuç`, `brokoli`, `ıspanak`):**
- Template should mention: "unprocessed vegetable", "water droplets for freshness"
- Should emphasize: Fresh, raw, grocery store display

**Proteins (`tavuk göğsü`, `somon`, `yumurta`):**
- Template should mention: "uncooked protein ingredient", "butcher shop display"
- Should emphasize: Raw, fresh quality, no seasoning

**Grains (`pirinç`, `yulaf`, `nohut`):**
- Template should mention: "dry uncooked", "individual grain texture"
- Should emphasize: Raw, dry grains, macro photography

**Dairy (`yoğurt`, `süt`, `peynir`):**
- Template should mention: "unprocessed dairy product", "freshness"
- Should emphasize: Fresh, natural presentation

### Scenario 4: Fallback Testing

**Test unknown ingredient:**
1. Enter ingredient name not in dictionary: `bilinmeyen_malzeme`
2. Click "🎨 Test Et"

**Expected Results:**
- ✅ System should use default vegetable template
- ✅ Error log should contain: "Using default category for ingredient: bilinmeyen_malzeme"
- ✅ Image should still generate using the input name as-is

### Scenario 5: Error Handling

**Test 1: No API Key**
1. Remove API keys from settings
2. Try to generate image

**Expected Results:**
- ❌ Error message: "Görsel oluşturulamadı. Lütfen API ayarlarınızı kontrol edin."

**Test 2: Nonce Expiration**
1. Leave page open for 12+ hours
2. Try to generate image

**Expected Results:**
- ❌ Error message includes: "Güvenlik kontrolü başarısız. Lütfen sayfayı yenileyin ve tekrar deneyin."

**Test 3: Invalid Ingredient Name**
1. Enter empty ingredient name
2. Click test button

**Expected Results:**
- ❌ Alert: "Lütfen bir malzeme adı girin."

## Integration Testing

### Test with IngredientGenerator

**Setup:**
1. Ensure image generation is working
2. Go to bulk ingredient seeder or create ingredient manually

**Test Steps:**
1. Create/generate a new ingredient (e.g., "elma")
2. Check if image is automatically generated
3. Verify image is attached as featured image
4. Verify image metadata contains `_kg_ai_generated = true`

**Expected Results:**
- ✅ Featured image is set
- ✅ Image shows raw ingredient (not prepared food)
- ✅ Image is stored in WordPress media library

### Test with AIEnrichButton

**Test Steps:**
1. Edit an existing ingredient without image
2. Click AI enrich button
3. Wait for enrichment to complete

**Expected Results:**
- ✅ Featured image is generated and attached
- ✅ Image matches ingredient category (fruit/vegetable/protein/grain)

## Performance Testing

### Response Times

**DALL-E 3:**
- Expected: 30-60 seconds per image
- Size: 1024x1024 pixels
- Cost: ~$0.04 per image

**Stable Diffusion:**
- Expected: 20-45 seconds per image
- Size: 1024x1024 pixels
- Cost: ~$0.02 per image

### Concurrent Requests

Test multiple ingredients at once:
1. Open multiple tabs with ingredient editor
2. Generate images simultaneously
3. Monitor for API rate limits or errors

## Visual Quality Checklist

For each generated image, verify:

- [ ] Background is pure white (not gray, cream, or colored)
- [ ] Ingredient is centered in frame
- [ ] Ingredient appears raw/unprocessed
- [ ] No kitchen equipment visible
- [ ] No text, watermarks, or logos
- [ ] No hands, people, or body parts
- [ ] Good lighting with subtle shadows
- [ ] Sharp focus and high detail
- [ ] Appropriate size/scale for ingredient
- [ ] Natural, vibrant colors

## Common Issues & Solutions

### Issue: Images show prepared food
**Solution:** Check that prompts don't contain forbidden terms. Verify `buildDallEPrompt()` is being used.

### Issue: Background is not white
**Solution:** Verify prompt contains "pure white seamless background" and negative prompt includes "colored background".

### Issue: Kitchen equipment appears
**Solution:** Verify negative prompt includes "pan, pot, knife, cutting board, kitchen" terms.

### Issue: Translation missing
**Solution:** Add ingredient to `getEnglishName()` dictionary with descriptive terms.

### Issue: Wrong category
**Solution:** Add ingredient to appropriate category array in `getIngredientCategory()`.

## Regression Testing

After any changes, re-run these quick tests:

1. **Banana test:** `muz` → whole banana (not puree)
2. **Carrot test:** `havuç` → fresh carrots with tops (not cooked/pureed)
3. **Chicken test:** `tavuk göğsü` → raw breast (not cooked)
4. **Rice test:** `pirinç` → dry rice grains (not cooked rice)
5. **Negative prompt test:** Verify no kitchen items or prepared food appears

## Documentation

Keep track of:
- Ingredients that generate good results
- Ingredients that need prompt refinement
- Translation additions needed
- Category mapping improvements

## Sign-off Criteria

Before marking as complete:
- [ ] All 5 quick tests pass
- [ ] At least 10 different ingredients tested successfully
- [ ] No prepared food images generated
- [ ] All backgrounds are white
- [ ] Admin UI test tool works
- [ ] Error handling works correctly
- [ ] Performance is acceptable (<60s per image)
- [ ] Both providers (DALL-E & Stable Diffusion) work

## Next Steps

After testing:
1. Document any issues found
2. Add new ingredients to translation dictionary as needed
3. Fine-tune prompt templates based on results
4. Consider caching generated images
5. Monitor API costs and usage
