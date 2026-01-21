# Phase 3 Testing Checklist - Data Migration System

## Pre-Testing Setup

- [ ] WordPress installation is working
- [ ] KG Core plugin is activated
- [ ] Database tables exist (kg_recipe_meta, kg_ingredient_meta, kg_post_meta)
- [ ] Some test posts exist (recipes, ingredients, blog posts)
- [ ] Test posts have postmeta data with _kg_ prefixed keys

## Admin Interface Testing

### 1. Menu and Page Access
- [ ] Navigate to WordPress Admin
- [ ] Check "KG Core" menu exists in sidebar
- [ ] Click "Data Migration" submenu
- [ ] Verify page loads without errors
- [ ] Check page title: "🔄 Data Migration - PostMeta to Custom Tables"

### 2. Table Status Dashboard
- [ ] Table status section is visible
- [ ] Shows 3 rows: kg_recipe_meta, kg_ingredient_meta, kg_post_meta
- [ ] Each row shows: Status (✓/✗), Record count, Total posts, Completion %
- [ ] "Durumu Yenile" (Refresh Status) button is visible
- [ ] Click refresh button - counts update

### 3. Migration Actions Section
- [ ] 4 migration cards are visible:
  - Tarifleri Migrate Et (Recipes)
  - Malzemeleri Migrate Et (Ingredients)
  - Postları Migrate Et (Posts)
  - Tümünü Migrate Et (All)
- [ ] Each card has description and button
- [ ] Buttons are clickable

### 4. Verification & Rollback Section
- [ ] Verification card is visible
- [ ] Dropdown shows: Tümü, Sadece Tarifler, Sadece Malzemeler, Sadece Postlar
- [ ] "Doğrula" button is visible
- [ ] Rollback card is visible with warning (red border)
- [ ] Rollback dropdown has options
- [ ] "Geri Al" button is initially disabled
- [ ] Selecting rollback type enables button

## Functional Testing

### Test 1: Single Type Migration (Recipes)
- [ ] Click "Tarifleri Migrate Et" button
- [ ] Confirmation dialog appears
- [ ] Click OK to proceed
- [ ] Button shows "İşlem yapılıyor..." during migration
- [ ] Results section appears below
- [ ] Results show: ✓ Migration tamamlandı!
- [ ] Shows counts: Migrate Edilen, Atlanan, Hatalar
- [ ] Table status updates automatically
- [ ] Completion percentage increases

### Test 2: Single Type Migration (Ingredients)
- [ ] Click "Malzemeleri Migrate Et" button
- [ ] Follow same steps as Test 1
- [ ] Verify ingredient meta is migrated

### Test 3: Single Type Migration (Posts)
- [ ] Click "Postları Migrate Et" button
- [ ] Follow same steps as Test 1
- [ ] Verify post meta is migrated

### Test 4: Bulk Migration
- [ ] Click "Tümünü Migrate Et" button
- [ ] Confirmation dialog appears
- [ ] Click OK to proceed
- [ ] Results show all three types:
  - Tarifler (Recipes)
  - Malzemeler (Ingredients)
  - Postlar (Posts)
- [ ] Each type shows migration counts
- [ ] All table statuses update

### Test 5: Re-running Migration (Skip Test)
- [ ] Run same migration again
- [ ] Verify "Atlanan" (Skipped) count equals previous "Migrate Edilen"
- [ ] Verify "Migrate Edilen" is 0 (no duplicates)
- [ ] Confirms skip already migrated works

### Test 6: Verification
- [ ] Select "Tümü" from verification dropdown
- [ ] Click "Doğrula" button
- [ ] Results show for all types
- [ ] If complete: "✓ Tüm X migrate edilmiş!"
- [ ] If missing: "⚠ X eksik:" with Post IDs
- [ ] Try each individual type verification

### Test 7: Rollback (Careful!)
**Note: This will delete data - test on non-production only**
- [ ] Select "Tarifler (kg_recipe_meta)" from rollback dropdown
- [ ] "Geri Al" button becomes enabled
- [ ] Click "Geri Al" button
- [ ] First confirmation dialog appears
- [ ] Click OK
- [ ] Table status shows 0 records for recipes
- [ ] Verify table is empty
- [ ] Re-run migration to restore

### Test 8: Rollback All (Extra Careful!)
**Note: Only test if absolutely necessary**
- [ ] Select "TÜMÜ (Tüm Tablolar)"
- [ ] Click "Geri Al"
- [ ] First confirmation appears
- [ ] Click OK
- [ ] Second "SON UYARI" confirmation appears
- [ ] Click OK
- [ ] All tables show 0 records
- [ ] Re-run "Tümünü Migrate Et" to restore

## Data Integrity Testing

### Verify Recipe Data
- [ ] Check database: SELECT * FROM wp_kg_recipe_meta LIMIT 5
- [ ] Verify prep_time, cook_time mapped correctly
- [ ] Check difficulty: should be lowercase (kolay/orta/zor)
- [ ] Check JSON fields: ingredients, instructions
- [ ] Check boolean fields: freezable, is_featured
- [ ] Check numeric fields: calories, protein
- [ ] Verify expert fields if present

### Verify Ingredient Data
- [ ] Check database: SELECT * FROM wp_kg_ingredient_meta LIMIT 5
- [ ] Verify start_age is integer
- [ ] Check allergy_risk: should be English (low/medium/high)
- [ ] Check JSON fields: season, prep_methods, faq
- [ ] Check boolean fields: is_featured
- [ ] Check float fields: calories_100g, protein_100g

### Verify Post Data
- [ ] Check database: SELECT * FROM wp_kg_post_meta LIMIT 5
- [ ] Verify sponsor fields if present
- [ ] Check boolean fields: is_featured, is_sponsored
- [ ] Check integer fields: sponsor_logo_id

### Verify Original Data Preserved
- [ ] Check wp_postmeta table
- [ ] Verify _kg_* meta keys still exist
- [ ] Original data should NOT be deleted
- [ ] Can compare values to custom tables

## Error Handling Testing

### Test Missing Data
- [ ] Create post with no _kg_ meta
- [ ] Run migration
- [ ] Should skip gracefully
- [ ] No errors in results

### Test Invalid Data
- [ ] Add invalid serialized data to postmeta
- [ ] Run migration
- [ ] Check error is caught and reported
- [ ] Other records continue processing

### Test Large Dataset
- [ ] Create 100+ test posts with meta
- [ ] Run migration
- [ ] Verify batch processing (50 at a time)
- [ ] Check no timeout errors
- [ ] All records processed

## JavaScript/AJAX Testing

### AJAX Functionality
- [ ] Open browser console (F12)
- [ ] Run any migration
- [ ] Check network tab for AJAX calls
- [ ] Verify no JavaScript errors
- [ ] Confirm proper request/response

### UI Updates
- [ ] Results area shows/hides correctly
- [ ] Button states (disabled during operation)
- [ ] Table status refreshes
- [ ] Success/error messages styled correctly

## Browser Compatibility

Test in multiple browsers:
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (if available)

Check:
- [ ] Layout looks correct
- [ ] Buttons work
- [ ] AJAX operations succeed
- [ ] No console errors

## Performance Testing

### Small Dataset (10-50 posts)
- [ ] Migration completes in < 10 seconds
- [ ] No timeout warnings

### Medium Dataset (100-500 posts)
- [ ] Migration completes in < 30 seconds
- [ ] Batch processing visible in logs
- [ ] Memory usage acceptable

### Large Dataset (1000+ posts)
- [ ] Migration completes without timeout
- [ ] Server resources monitored
- [ ] Consider increasing time limit if needed

## Security Testing

### Authentication
- [ ] Logout and try accessing migration page
- [ ] Verify redirect to login
- [ ] Login as subscriber/contributor
- [ ] Verify no access (403/redirect)

### AJAX Security
- [ ] Check nonce in AJAX requests
- [ ] Try forging request without nonce
- [ ] Verify rejection

### SQL Injection Prevention
- [ ] All queries use prepared statements
- [ ] No direct user input in queries

## Final Verification

### Code Quality
- [x] All PHP files pass syntax check
- [x] No PHP errors in error log
- [ ] JavaScript console clean (no errors)
- [ ] CSS renders correctly

### Documentation
- [x] DATA_MIGRATION.md exists and is complete
- [x] PHASE3_SUMMARY.md exists
- [ ] Admin users can understand the page
- [ ] Instructions are clear

### Deployment Readiness
- [ ] All tests pass
- [ ] No critical bugs found
- [ ] Performance is acceptable
- [ ] Security checks pass
- [ ] Documentation is complete
- [ ] Ready for production deployment

## Post-Deployment Monitoring

After deploying to production:
- [ ] Monitor error logs for 24 hours
- [ ] Check migration success rate
- [ ] Monitor server performance
- [ ] Gather user feedback
- [ ] Track any issues reported

## Rollback Plan

If issues occur:
1. Stop all migrations
2. Restore from backup if needed
3. Investigate errors
4. Fix issues
5. Re-test
6. Re-deploy

---

**Testing Date:** _________________
**Tested By:** _________________
**Environment:** Development / Staging / Production
**Result:** Pass / Fail / Partial
**Notes:** _________________
