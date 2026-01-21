jQuery(document).ready(function($) {
    'use strict';
    
    // Migrate buttons click handler
    $('.kg-migrate-btn').on('click', function() {
        const $btn = $(this);
        const type = $btn.data('type');
        const originalText = $btn.text();
        
        if (confirm('Bu işlem seçilen post tipinin tüm verilerini migrate edecek. Devam etmek istiyor musunuz?')) {
            $btn.prop('disabled', true).text('İşlem yapılıyor...');
            
            let action = 'kg_migrate_all_types';
            if (type === 'recipes') action = 'kg_migrate_recipes';
            else if (type === 'ingredients') action = 'kg_migrate_ingredients';
            else if (type === 'posts') action = 'kg_migrate_posts';
            
            $.ajax({
                url: kgDataMigration.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: kgDataMigration.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showResults(response.data, type);
                        refreshTableStatus();
                    } else {
                        showError(response.data || 'Bir hata oluştu.');
                    }
                },
                error: function() {
                    showError('AJAX hatası oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        }
    });
    
    // Verify button click handler
    $('#kg-verify-btn').on('click', function() {
        const $btn = $(this);
        const type = $('#kg-verify-type').val();
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Kontrol ediliyor...');
        
        $.ajax({
            url: kgDataMigration.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kg_verify_migration',
                type: type,
                nonce: kgDataMigration.nonce
            },
            success: function(response) {
                if (response.success) {
                    showVerificationResults(response.data);
                } else {
                    showError(response.data || 'Bir hata oluştu.');
                }
            },
            error: function() {
                showError('AJAX hatası oluştu.');
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Rollback type change handler
    $('#kg-rollback-type').on('change', function() {
        const value = $(this).val();
        $('#kg-rollback-btn').prop('disabled', !value);
    });
    
    // Rollback button click handler
    $('#kg-rollback-btn').on('click', function() {
        const $btn = $(this);
        const type = $('#kg-rollback-type').val();
        const originalText = $btn.text();
        
        if (!type) {
            alert('Lütfen bir tablo seçin.');
            return;
        }
        
        const confirmText = type === 'all' 
            ? 'TÜM TABLOLARI SIFIRLAMAK üzeresiniz! Bu işlem geri alınamaz. Emin misiniz?'
            : 'Bu tablo sıfırlanacak. Emin misiniz?';
        
        if (confirm(confirmText)) {
            if (type === 'all' && !confirm('SON UYARI: TÜM VERİLER SİLİNECEK! Devam ediyor musunuz?')) {
                return;
            }
            
            $btn.prop('disabled', true).text('Siliniyor...');
            
            $.ajax({
                url: kgDataMigration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kg_rollback_migration',
                    type: type,
                    nonce: kgDataMigration.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess('Rollback başarılı. Tablo temizlendi.');
                        $('#kg-rollback-type').val('');
                        refreshTableStatus();
                    } else {
                        showError(response.data || 'Bir hata oluştu.');
                    }
                },
                error: function() {
                    showError('AJAX hatası oluştu.');
                },
                complete: function() {
                    $btn.prop('disabled', true).text(originalText);
                }
            });
        }
    });
    
    // Refresh status button click handler
    $('#kg-refresh-status').on('click', function() {
        refreshTableStatus();
    });
    
    // Show results
    function showResults(data, type) {
        let html = '<div class="success-message">✓ Migration tamamlandı!</div>';
        
        if (type === 'all') {
            // All types migrated
            html += '<h4>Tarifler (Recipes):</h4>';
            html += formatResult(data.recipes);
            
            html += '<h4>Malzemeler (Ingredients):</h4>';
            html += formatResult(data.ingredients);
            
            html += '<h4>Postlar (Posts):</h4>';
            html += formatResult(data.posts);
        } else {
            html += formatResult(data);
        }
        
        $('#kg-results-content').html(html);
        $('#kg-migration-results').show();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#kg-migration-results').offset().top - 100
        }, 500);
    }
    
    // Format single result
    function formatResult(result) {
        let html = '<ul>';
        html += '<li>Migrate Edilen: <strong>' + result.migrated + '</strong></li>';
        html += '<li>Atlanan (Zaten Mevcut): <strong>' + result.skipped + '</strong></li>';
        
        if (result.errors && result.errors.length > 0) {
            html += '<li class="error-message">Hatalar: <strong>' + result.errors.length + '</strong></li>';
            html += '<ul>';
            result.errors.forEach(function(error) {
                html += '<li>Post ID ' + error.post_id + ': ' + error.error + '</li>';
            });
            html += '</ul>';
        } else {
            html += '<li class="success-message">Hata yok!</li>';
        }
        
        html += '</ul>';
        return html;
    }
    
    // Show verification results
    function showVerificationResults(data) {
        let html = '<div class="info-message">Doğrulama Sonuçları:</div>';
        
        let hasMissing = false;
        let missingData = {};
        
        if (data.recipe !== undefined) {
            html += '<h4>Tarifler (Recipes):</h4>';
            if (data.recipe.length === 0) {
                html += '<p class="success-message">✓ Tüm tarifler migrate edilmiş!</p>';
            } else {
                hasMissing = true;
                missingData.recipe = data.recipe;
                html += '<p class="error-message">⚠ ' + data.recipe.length + ' tarif eksik:</p>';
                html += '<p>Post IDs: ' + data.recipe.slice(0, 10).join(', ');
                if (data.recipe.length > 10) {
                    html += '... (ve ' + (data.recipe.length - 10) + ' daha)';
                }
                html += '</p>';
                html += '<button class="button button-primary kg-migrate-missing-btn" data-type="recipe" data-count="' + data.recipe.length + '">Bu ' + data.recipe.length + ' Tarifi Migrate Et</button>';
            }
        }
        
        if (data.ingredient !== undefined) {
            html += '<h4>Malzemeler (Ingredients):</h4>';
            if (data.ingredient.length === 0) {
                html += '<p class="success-message">✓ Tüm malzemeler migrate edilmiş!</p>';
            } else {
                hasMissing = true;
                missingData.ingredient = data.ingredient;
                html += '<p class="error-message">⚠ ' + data.ingredient.length + ' malzeme eksik:</p>';
                html += '<p>Post IDs: ' + data.ingredient.slice(0, 10).join(', ');
                if (data.ingredient.length > 10) {
                    html += '... (ve ' + (data.ingredient.length - 10) + ' daha)';
                }
                html += '</p>';
                html += '<button class="button button-primary kg-migrate-missing-btn" data-type="ingredient" data-count="' + data.ingredient.length + '">Bu ' + data.ingredient.length + ' Malzemeyi Migrate Et</button>';
            }
        }
        
        if (data.post !== undefined) {
            html += '<h4>Postlar (Posts):</h4>';
            if (data.post.length === 0) {
                html += '<p class="success-message">✓ Tüm postlar migrate edilmiş!</p>';
            } else {
                hasMissing = true;
                missingData.post = data.post;
                html += '<p class="error-message">⚠ ' + data.post.length + ' post eksik:</p>';
                html += '<p>Post IDs: ' + data.post.slice(0, 10).join(', ');
                if (data.post.length > 10) {
                    html += '... (ve ' + (data.post.length - 10) + ' daha)';
                }
                html += '</p>';
                html += '<button class="button button-primary kg-migrate-missing-btn" data-type="post" data-count="' + data.post.length + '">Bu ' + data.post.length + ' Postu Migrate Et</button>';
            }
        }
        
        $('#kg-results-content').html(html);
        $('#kg-migration-results').show();
        
        // Store missing data for migrate buttons
        $('#kg-migration-results').data('missingData', missingData);
        
        // Attach event handlers to dynamically created buttons
        $('.kg-migrate-missing-btn').on('click', function() {
            const $btn = $(this);
            const type = $btn.data('type');
            const count = $btn.data('count');
            const missingIds = missingData[type] || [];
            
            if (missingIds.length === 0) {
                showError('Migrate edilecek kayıt bulunamadı.');
                return;
            }
            
            if (confirm(count + ' adet ' + type + ' kaydını migrate etmek istediğinize emin misiniz?')) {
                $btn.prop('disabled', true).text('Migrate ediliyor...');
                
                $.ajax({
                    url: kgDataMigration.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'kg_migrate_missing',
                        type: type,
                        post_ids: missingIds,
                        nonce: kgDataMigration.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            const result = response.data;
                            let msg = '<div class="success-message">✓ Migration tamamlandı!</div>';
                            msg += '<ul>';
                            msg += '<li>Başarılı: <strong>' + result.success + '</strong></li>';
                            msg += '<li>Başarısız: <strong>' + result.failed + '</strong></li>';
                            if (result.errors && result.errors.length > 0) {
                                msg += '<li class="error-message">Hatalı Post IDs: ' + result.errors.join(', ') + '</li>';
                            }
                            msg += '</ul>';
                            $('#kg-results-content').html(msg);
                            refreshTableStatus();
                        } else {
                            showError(response.data || 'Bir hata oluştu.');
                        }
                    },
                    error: function() {
                        showError('AJAX hatası oluştu.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('Migration Tamamlandı');
                    }
                });
            }
        });
    }
    
    // Show error
    function showError(message) {
        const html = '<div class="error-message">✗ Hata: ' + message + '</div>';
        $('#kg-results-content').html(html);
        $('#kg-migration-results').show();
    }
    
    // Show success
    function showSuccess(message) {
        const html = '<div class="success-message">✓ ' + message + '</div>';
        $('#kg-results-content').html(html);
        $('#kg-migration-results').show();
    }
    
    // Refresh table status
    function refreshTableStatus() {
        $.ajax({
            url: kgDataMigration.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kg_get_table_status',
                nonce: kgDataMigration.nonce
            },
            success: function(response) {
                if (response.success) {
                    const status = response.data;
                    
                    if (status.kg_recipe_meta) {
                        $('#recipe-count').text(status.kg_recipe_meta.row_count);
                    }
                    if (status.kg_ingredient_meta) {
                        $('#ingredient-count').text(status.kg_ingredient_meta.row_count);
                    }
                    if (status.kg_post_meta) {
                        $('#post-count').text(status.kg_post_meta.row_count);
                    }
                }
            }
        });
    }
});
