/**
 * KG Migration Page JavaScript
 */
(function($) {
    'use strict';
    
    const KGMigration = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('#kg-migrate-single').on('click', this.migrateSingle.bind(this));
            $('#kg-migrate-batch').on('click', this.migrateBatch.bind(this));
            $('#kg-migrate-all').on('click', this.migrateAll.bind(this));
            $('#kg-clean-test').on('click', this.cleanTestMigrations.bind(this));
            $('#kg-preview-consolidation').on('click', this.previewConsolidation.bind(this));
            $('#kg-run-consolidation').on('click', this.runConsolidation.bind(this));
        },
        
        /**
         * Migrate single post
         */
        migrateSingle: function(e) {
            e.preventDefault();
            
            const postId = $('#kg-single-id').val();
            
            if (!postId || postId <= 0) {
                alert('Lütfen geçerli bir Post ID girin.');
                return;
            }
            
            if (!confirm(`Post ID ${postId} için migration başlatılsın mı?`)) {
                return;
            }
            
            const $btn = $(e.currentTarget);
            this.setLoading($btn, true);
            this.showResult('info', `Post ID ${postId} için migration başlatılıyor...`);
            
            $.ajax({
                url: kgMigration.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'kg_migrate_single',
                    nonce: kgMigration.nonce,
                    post_id: postId
                },
                success: (response) => {
                    this.setLoading($btn, false);
                    
                    if (response.success) {
                        let message = '✅ ' + response.data.message;
                        
                        // Add debug information if available
                        if (response.data.debug) {
                            const debug = response.data.debug;
                            message += '\n\n📊 Debug Bilgisi:';
                            message += `\n- Malzemeler: ${debug.ingredients_count}`;
                            message += `\n- Adımlar: ${debug.instructions_count}`;
                            message += `\n- Uzman Notu: ${debug.has_expert_note ? 'Var' : 'Yok'}`;
                            message += `\n- Yaş Grubu: ${debug.age_group || 'Bulunamadı'}`;
                            message += `\n- AI Zenginleştirme: ${debug.ai_enhanced ? 'Evet' : 'Hayır'}`;
                        }
                        
                        this.showResult('success', message);
                        this.updateStatus();
                    } else {
                        this.showResult('error', '❌ Hata: ' + response.data);
                    }
                },
                error: (xhr) => {
                    this.setLoading($btn, false);
                    this.showResult('error', '❌ AJAX hatası: ' + xhr.statusText);
                }
            });
        },
        
        /**
         * Migrate batch
         */
        migrateBatch: function(e) {
            e.preventDefault();
            
            if (!confirm('10 tarif için migration başlatılsın mı? Bu işlem birkaç dakika sürebilir.')) {
                return;
            }
            
            const $btn = $(e.currentTarget);
            this.setLoading($btn, true);
            this.showResult('info', '📦 10 tarif için batch migration başlatılıyor...');
            
            $.ajax({
                url: kgMigration.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'kg_migrate_batch',
                    nonce: kgMigration.nonce
                },
                success: (response) => {
                    this.setLoading($btn, false);
                    
                    if (response.success) {
                        const data = response.data;
                        let message = `✅ Batch tamamlandı:\n`;
                        message += `- Başarılı: ${data.success}\n`;
                        message += `- Başarısız: ${data.failed}\n`;
                        message += `- Atlandı: ${data.skipped}\n`;
                        
                        if (data.errors && data.errors.length > 0) {
                            message += `\nHatalar:\n`;
                            data.errors.forEach(err => {
                                message += `- Post ${err.post_id}: ${err.error}\n`;
                            });
                        }
                        
                        this.showResult(data.failed > 0 ? 'error' : 'success', message);
                        this.updateStatus();
                    } else {
                        this.showResult('error', '❌ Hata: ' + response.data);
                    }
                },
                error: (xhr) => {
                    this.setLoading($btn, false);
                    this.showResult('error', '❌ AJAX hatası: ' + xhr.statusText);
                }
            });
        },
        
        /**
         * Migrate all
         */
        migrateAll: function(e) {
            e.preventDefault();
            
            const confirmMsg = 'TÜM tarifleri migration etmek istediğinize emin misiniz?\n\n' +
                             'Bu işlem çok uzun sürebilir (saatler).\n\n' +
                             'Devam etmek istediğinize emin misiniz?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            const $btn = $(e.currentTarget);
            this.setLoading($btn, true);
            this.showResult('info', '🚀 Tüm tariflerin migration işlemi başlatılıyor...\nBu işlem uzun sürebilir, lütfen bekleyin.');
            
            $.ajax({
                url: kgMigration.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'kg_migrate_all',
                    nonce: kgMigration.nonce
                },
                timeout: 0, // No timeout
                success: (response) => {
                    this.setLoading($btn, false);
                    
                    if (response.success) {
                        const data = response.data;
                        let message = `✅ Toplu migration tamamlandı:\n`;
                        message += `- Başarılı: ${data.success}\n`;
                        message += `- Başarısız: ${data.failed}\n`;
                        message += `- Atlandı: ${data.skipped}\n`;
                        
                        if (data.errors && data.errors.length > 0) {
                            message += `\nHatalar (ilk 10):\n`;
                            data.errors.slice(0, 10).forEach(err => {
                                message += `- Post ${err.post_id}: ${err.error}\n`;
                            });
                        }
                        
                        this.showResult(data.failed > 0 ? 'error' : 'success', message);
                        this.updateStatus();
                        
                        // Reload page after 3 seconds
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        this.showResult('error', '❌ Hata: ' + response.data);
                    }
                },
                error: (xhr) => {
                    this.setLoading($btn, false);
                    this.showResult('error', '❌ AJAX hatası: ' + xhr.statusText);
                }
            });
        },
        
        /**
         * Clean test migrations
         */
        cleanTestMigrations: function(e) {
            e.preventDefault();
            
            if (!confirm('Test modunda oluşturulmuş tüm tarifleri silmek istediğinize emin misiniz?\n\nBu işlem geri alınamaz!')) {
                return;
            }
            
            const $btn = $(e.currentTarget);
            this.setLoading($btn, true);
            this.showResult('info', '🧹 Test tarifleri temizleniyor...');
            
            $.ajax({
                url: kgMigration.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'kg_clean_test_migrations',
                    nonce: kgMigration.nonce
                },
                success: (response) => {
                    this.setLoading($btn, false);
                    
                    if (response.success) {
                        this.showResult('success', '✅ ' + response.data.message);
                        
                        // Reload page after 2 seconds
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        this.showResult('error', '❌ Hata: ' + response.data);
                    }
                },
                error: (xhr) => {
                    this.setLoading($btn, false);
                    this.showResult('error', '❌ AJAX hatası: ' + xhr.statusText);
                }
            });
        },
        
        /**
         * Preview field consolidation
         */
        previewConsolidation: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const $preview = $('#kg-consolidation-preview');
            
            this.setLoading($btn, true);
            $preview.html('<p>Önizleme yükleniyor...</p>').show();
            
            $.ajax({
                url: kgMigration.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'kg_preview_field_consolidation',
                    nonce: kgMigration.nonce
                },
                success: (response) => {
                    this.setLoading($btn, false);
                    
                    if (response.success) {
                        const data = response.data;
                        let html = '<h4>📊 Migrasyon Önizlemesi</h4>';
                        html += '<ul style="list-style: disc; margin-left: 20px;">';
                        html += `<li><strong>Toplam Ingredient:</strong> ${data.total_ingredients}</li>`;
                        html += `<li><strong>Kategori Migrate Edilecek:</strong> ${data.will_migrate_category}</li>`;
                        html += `<li><strong>Besin Değerleri Migrate Edilecek:</strong> ${data.will_migrate_nutrition}</li>`;
                        html += `<li><strong>Temizlenecek Alan Bulunduran:</strong> ${data.has_deprecated_fields}</li>`;
                        html += '</ul>';
                        
                        $preview.html(html);
                    } else {
                        $preview.html('<p style="color: red;">❌ Hata: ' + response.data + '</p>');
                    }
                },
                error: (xhr) => {
                    this.setLoading($btn, false);
                    $preview.html('<p style="color: red;">❌ AJAX hatası: ' + xhr.statusText + '</p>');
                }
            });
        },
        
        /**
         * Run field consolidation
         */
        runConsolidation: function(e) {
            e.preventDefault();
            
            const confirmMsg = 'Ingredient field consolidation işlemini başlatmak istediğinize emin misiniz?\n\n' +
                             'Bu işlem:\n' +
                             '- Kategori meta field\'larını taxonomy\'ye taşıyacak\n' +
                             '- Eski besin değerlerini 100g formatına migrate edecek\n' +
                             '- Mükerrer alanları temizleyecek\n\n' +
                             'Devam etmek istiyor musunuz?';
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            const $btn = $(e.currentTarget);
            const $result = $('#kg-consolidation-result');
            
            this.setLoading($btn, true);
            $result.html('<p style="color: #2271b1;">⏳ Migrasyon işlemi başlatılıyor...</p>').show();
            
            $.ajax({
                url: kgMigration.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'kg_run_field_consolidation',
                    nonce: kgMigration.nonce
                },
                timeout: 300000, // 5 minutes
                success: (response) => {
                    this.setLoading($btn, false);
                    
                    if (response.success) {
                        const data = response.data;
                        let html = '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px;">';
                        html += '<h4 style="color: #155724; margin-top: 0;">✅ Migrasyon Başarıyla Tamamlandı</h4>';
                        html += '<ul style="list-style: disc; margin-left: 20px; color: #155724;">';
                        html += `<li><strong>İşlenen Ingredient:</strong> ${data.processed}</li>`;
                        html += `<li><strong>Kategori Migrate Edildi:</strong> ${data.category_migrated}</li>`;
                        html += `<li><strong>Besin Değerleri Migrate Edildi:</strong> ${data.nutrition_migrated}</li>`;
                        
                        if (data.errors && data.errors.length > 0) {
                            html += '</ul>';
                            html += '<h5 style="color: #721c24; margin-top: 10px;">⚠️ Hatalar:</h5>';
                            html += '<ul style="list-style: disc; margin-left: 20px; color: #721c24;">';
                            data.errors.forEach(err => {
                                html += `<li>${this.escapeHtml(err)}</li>`;
                            });
                        }
                        
                        html += '</ul></div>';
                        
                        $result.html(html);
                        
                        // Reload page after 3 seconds
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        $result.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; color: #721c24;">❌ Hata: ' + response.data + '</div>');
                    }
                },
                error: (xhr) => {
                    this.setLoading($btn, false);
                    $result.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; color: #721c24;">❌ AJAX hatası: ' + xhr.statusText + '</div>');
                }
            });
        },
        
        /**
         * Show result message
         */
        showResult: function(type, message) {
            const $resultArea = $('#kg-migration-result');
            const $output = $('#kg-migration-output');
            
            $resultArea.show();
            
            const timestamp = new Date().toLocaleTimeString();
            const html = `<div class="${type}">[${timestamp}] ${this.escapeHtml(message)}</div>`;
            
            $output.append(html);
            
            // Scroll to bottom
            $output.scrollTop($output[0].scrollHeight);
        },
        
        /**
         * Update status display
         */
        updateStatus: function() {
            $.ajax({
                url: kgMigration.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'kg_migration_status',
                    nonce: kgMigration.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Optionally update status cards
                        // This would require dynamically updating the numbers
                        console.log('Status updated:', response.data);
                    }
                }
            });
        },
        
        /**
         * Set button loading state
         */
        setLoading: function($btn, isLoading) {
            if (isLoading) {
                $btn.addClass('is-loading').prop('disabled', true);
                
                if (!$btn.find('.kg-loading').length) {
                    $btn.append('<span class="kg-loading"></span>');
                }
            } else {
                $btn.removeClass('is-loading').prop('disabled', false);
                $btn.find('.kg-loading').remove();
            }
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        KGMigration.init();
    });
    
})(jQuery);
