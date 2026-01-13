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
                        this.showResult('success', '✅ ' + response.data.message);
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
