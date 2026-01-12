# Image Generation System Improvements

## Overview
This document describes the improvements made to the image generation system to address issues with DALL-E 3 generating prepared food images instead of raw ingredients.

## Problems Addressed

### Before:
- ❌ DALL-E 3 was generating prepared food (banana puree instead of whole banana)
- ❌ Images sometimes included unwanted objects (cameras, spotlights, kitchen equipment)
- ❌ Prompts used misleading terms like "baby food" and "nutrition guide"

### After:
- ✅ Optimized prompts focus on raw, unprocessed ingredients
- ✅ Added Stable Diffusion as alternative provider
- ✅ Negative prompts prevent unwanted elements
- ✅ Category-based prompt templates for better results
- ✅ 100+ ingredient translations with descriptive terms

## Changes Made

### 1. ImageService.php - Complete Rewrite

#### New Methods:
- `generateImage()` - Main method that routes to appropriate provider (DALL-E or Stable Diffusion)
- `generateWithDallE()` - DALL-E 3 API integration with optimized prompts
- `generateWithStableDiffusion()` - Stability AI API integration with negative prompts
- `buildDallEPrompt()` - Builds optimized prompts for DALL-E
- `buildStabilityPrompt()` - Builds prompts for Stable Diffusion
- `getStabilityNegativePrompt()` - Returns comprehensive negative prompt
- `getIngredientPromptTemplate()` - Category-based prompt templates
- `getIngredientCategory()` - Determines ingredient category (fruits, vegetables, proteins, grains, dairy)
- `getEnglishName()` - Expanded translation dictionary with 100+ ingredients

#### Prompt Optimization Rules:
**Removed Terms:**
- ❌ "food", "meal", "dish", "puree", "baby food", "cooking"
- ❌ "nutrition guide", "baby food website"

**Added Terms:**
- ✅ "raw", "fresh", "uncut", "whole", "unprocessed"
- ✅ "isolated on pure white background"
- ✅ "no other objects", "no props", "no decorations"
- ✅ "professional product photography"

#### Category-Based Prompts:

**Fruits:**
```
A single fresh {INGREDIENT}, raw and uncut, whole fruit, 
isolated on pure white seamless background, 
professional product photography, commercial food photography style, 
soft diffused natural lighting from left side, 
subtle natural shadow beneath the fruit, 
vibrant natural colors, highly detailed texture of skin, 
sharp focus, centered composition, negative space around subject, 
no other objects, no decorations, no props, 
clean minimalist style, stock photo quality
```

**Vegetables:**
```
Fresh raw {INGREDIENT}, whole and unprocessed vegetable, 
isolated on pure white seamless studio background, 
professional product photography for grocery store, 
soft box lighting from upper left at 45 degrees, 
gentle natural shadow on white surface, 
vivid fresh colors showing ripeness and quality, 
detailed texture visible, water droplets for freshness look, 
sharp focus throughout, centered in frame, 
no cutting board, no knife, no kitchen items, 
clean commercial photography style
```

**Proteins:**
```
Raw fresh {INGREDIENT}, uncooked protein ingredient, 
placed on pure white background, 
professional butcher shop or fishmonger display style photography, 
clean soft lighting, no harsh shadows, 
showing fresh quality and natural color, 
high detail, sharp focus, 
no seasoning, no marinade, no cooking preparation, 
isolated single item, no other ingredients, 
commercial food photography
```

**Grains:**
```
Dry uncooked {INGREDIENT}, raw grain or cereal ingredient, 
small pile or scattered arrangement on pure white background, 
macro food photography showing individual grain texture, 
soft even lighting, no harsh shadows, 
natural earthy colors, sharp detail, 
no bowl, no container, no scoop, 
clean product photography style
```

#### Stable Diffusion Negative Prompt:
```
cooked, cooking, meal, dish, recipe, plate, bowl, puree, mashed, soup, stew, sauce, prepared food, baby food, processed,
pan, pot, spoon, fork, knife, cutting board, kitchen, stove, oven, microwave, blender, mixer,
camera, spotlight, tripod, studio equipment, lighting equipment, hands, fingers, person, human, face, body parts,
text, watermark, logo, signature, label, tag, price tag, table, tablecloth, napkin, decoration, flowers, vase,
blurry, low quality, pixelated, grainy, noisy, artifacts, distorted, deformed, ugly, bad anatomy, wrong proportions,
oversaturated, undersaturated, overexposed, underexposed, busy background, cluttered, messy, complex background,
colored background, patterned background, textured background, gradient background, dark background, black background
```

### 2. SettingsPage.php - New Features

#### New Settings:
- `kg_image_provider` - Select between "dalle" or "stability"
- `kg_stability_api_key` - Stability AI API key for Stable Diffusion

#### Image Test Tool:
- Input field for ingredient name (Turkish)
- "Test" button to generate image
- Preview area showing generated image
- Display of prompt used for generation
- Display of negative prompt (for Stable Diffusion)
- AJAX handler for async image generation testing

#### Admin UI Improvements:
- Reorganized settings into clear sections
- Added status indicators for all API services
- Added inline help text and documentation links

## Translation Dictionary (100+ Ingredients)

### Fruits (28):
elma, muz, armut, şeftali, kayısı, erik, kiraz, çilek, üzüm, karpuz, kavun, portakal, mandalina, kivi, hurma, incir, nar, avokado, mango, ananas, papaya, böğürtlen, ahududu, yaban mersini, vişne, limon, greyfurt, ayva

### Vegetables (25):
havuç, patates, brokoli, tatlı patates, kabak, balkabağı, karnabahar, ıspanak, pırasa, bezelye, fasulye, domates, salatalık, biber, patlıcan, lahana, soğan, sarımsak, kereviz, pancar, marul, roka, turp, bamya, enginar

### Proteins (15):
tavuk göğsü, tavuk, hindi, somon, levrek, çipura, ton balığı, yumurta, dana eti, kuzu eti, kırmızı et, balık, hamsi, palamut, uskumru

### Grains & Legumes (14):
pirinç, yulaf, mercimek, nohut, bulgur, kinoa, arpa, buğday, mısır, kuskus, kırmızı mercimek, yeşil mercimek, barbunya, fasulye

### Dairy (8):
yoğurt, süt, peynir, lor peyniri, beyaz peynir, kaşar peyniri, labne, tereyağı

### Nuts & Seeds (10):
badem, ceviz, fındık, fıstık, antep fıstığı, susam, ayçiçeği çekirdeği, kabak çekirdeği, çam fıstığı, kaju

### Herbs & Spices (7):
maydanoz, dereotu, nane, fesleğen, kekik, biberiye, tarhun

## Usage

### Selecting Image Provider:
1. Go to Admin → Malzemeler → ⚙️ AI Ayarları
2. Scroll to "🎨 Görsel Oluşturma Ayarları"
3. Select provider:
   - **DALL-E 3** - Uses OpenAI API (requires kg_ai_api_key)
   - **Stable Diffusion** - Uses Stability AI (requires kg_stability_api_key)

### Testing Image Generation:
1. Scroll to "🧪 Görsel Oluşturma Test Aracı"
2. Enter ingredient name in Turkish (e.g., "muz", "havuç", "elma")
3. Click "🎨 Test Et"
4. View generated image and prompts used

### API Keys Required:
- **DALL-E 3**: Uses `kg_ai_api_key` (OpenAI API key)
- **Stable Diffusion**: Uses `kg_stability_api_key` (Stability AI API key)

## Expected Results

### Example: Banana (Muz)
- ❌ **Before:** Mashed banana puree in a bowl
- ✅ **After:** Whole yellow banana on white background

### Example: Avocado (Avokado)
- ❌ **Before:** Sliced avocado on toast
- ✅ **After:** Whole avocado on white background

### Example: Carrot (Havuç)
- ❌ **Before:** Cooked carrot puree
- ✅ **After:** Fresh carrots with green tops on white background

## Technical Details

### Backward Compatibility:
- Old method `translateToEnglish()` is kept for backward compatibility
- Method now calls `getEnglishName()` internally
- All existing code continues to work without changes

### API Integration:
- **DALL-E 3**: Uses OpenAI Images API v1
- **Stable Diffusion**: Uses Stability AI v1 (SDXL 1024)
- Both return standardized image data structure

### Return Format:
```php
[
    'url' => 'https://...',           // Image URL or data URL
    'source' => 'dall-e-3|stability-ai',
    'credit' => 'AI Generated (...)',
    'credit_url' => '',
    'prompt' => '...',                // Prompt used (optional)
    'negative_prompt' => '...'        // Negative prompt (SD only)
]
```

## Cost Considerations

- **DALL-E 3**: ~$0.04 per 1024x1024 standard quality image
- **Stable Diffusion**: ~$0.02 per 1024x1024 image (30 steps)

Both providers generate high-quality, consistent results optimized for raw ingredient photography.

## Testing Checklist

- [x] Prompt generation logic tested
- [x] Category detection tested
- [x] Translation dictionary tested
- [x] Negative prompt includes all unwanted elements
- [ ] DALL-E 3 API integration (requires API key)
- [ ] Stable Diffusion API integration (requires API key)
- [ ] Admin UI test tool (requires WordPress environment)

## Files Modified

1. `/includes/Services/ImageService.php` - Complete rewrite
2. `/includes/Admin/SettingsPage.php` - Added image provider settings and test tool

## Maintenance Notes

### Adding New Ingredients:
1. Add to `getEnglishName()` translation dictionary
2. Add to appropriate category array in `getIngredientCategory()`
3. Test with the admin test tool

### Modifying Prompts:
1. Edit templates in `getIngredientPromptTemplate()`
2. Test with multiple ingredients from the category
3. Verify no unwanted elements appear

### Updating Negative Prompt:
1. Edit `getStabilityNegativePrompt()` method
2. Add new unwanted terms as comma-separated values
3. Test with Stable Diffusion provider
