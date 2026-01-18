/**
 * KG Embed Selector
 * Handles the embed content modal and selection
 */
(function($) {
    'use strict';
    
    var KGEmbedSelector = {
        currentType: 'recipe',
        selectedItems: [],
        searchTimeout: null,
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Open modal button
            $(document).on('click', '#kg-embed-button', function(e) {
                e.preventDefault();
                self.openModal();
            });
            
            // Close modal
            $(document).on('click', '.kg-embed-modal-close, #kg-embed-cancel', function(e) {
                e.preventDefault();
                self.closeModal();
            });
            
            // Close on outside click
            $(document).on('click', '.kg-embed-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            // Tab switching
            $(document).on('click', '.kg-embed-tab', function(e) {
                e.preventDefault();
                var type = $(this).data('type');
                self.switchTab(type);
            });
            
            // Search
            $(document).on('keyup', '#kg-embed-search-input', function() {
                clearTimeout(self.searchTimeout);
                self.searchTimeout = setTimeout(function() {
                    self.searchContent();
                }, 300);
            });
            
            // Item selection
            $(document).on('click', '.kg-embed-item', function(e) {
                if (!$(e.target).is('input[type="checkbox"]')) {
                    var checkbox = $(this).find('.kg-embed-item-checkbox');
                    checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
                }
            });
            
            $(document).on('change', '.kg-embed-item-checkbox', function() {
                var item = $(this).closest('.kg-embed-item');
                var itemId = parseInt($(this).val());
                
                if ($(this).is(':checked')) {
                    item.addClass('selected');
                    self.selectedItems.push(itemId);
                } else {
                    item.removeClass('selected');
                    self.selectedItems = self.selectedItems.filter(function(id) {
                        return id !== itemId;
                    });
                }
                
                self.updateSelectedCount();
            });
            
            // Insert embed
            $(document).on('click', '#kg-embed-insert', function(e) {
                e.preventDefault();
                self.insertEmbed();
            });
            
            // ESC key to close
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape' && $('.kg-embed-modal').is(':visible')) {
                    self.closeModal();
                }
            });
        },
        
        openModal: function() {
            this.selectedItems = [];
            this.currentType = 'recipe';
            $('.kg-embed-modal').fadeIn(200);
            this.loadContent();
        },
        
        closeModal: function() {
            $('.kg-embed-modal').fadeOut(200);
            this.selectedItems = [];
            $('#kg-embed-search-input').val('');
            this.updateSelectedCount();
        },
        
        switchTab: function(type) {
            this.currentType = type;
            this.selectedItems = [];
            
            $('.kg-embed-tab').removeClass('active');
            $('.kg-embed-tab[data-type="' + type + '"]').addClass('active');
            
            $('#kg-embed-search-input').val('');
            this.updateSelectedCount();
            this.loadContent();
        },
        
        searchContent: function() {
            this.loadContent($('#kg-embed-search-input').val());
        },
        
        loadContent: function(search) {
            var self = this;
            search = search || '';
            
            var $results = $('#kg-embed-results');
            $results.html('<div class="kg-embed-results loading">' + kgEmbedSelector.labels.loading + '</div>');
            
            $.ajax({
                url: kgEmbedSelector.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kg_search_embeddable_content',
                    nonce: kgEmbedSelector.nonce,
                    type: self.currentType,
                    search: search
                },
                success: function(response) {
                    if (response.success && response.data.items) {
                        self.renderResults(response.data.items);
                    } else {
                        $results.html('<div class="kg-embed-results empty">' + kgEmbedSelector.labels.noResults + '</div>');
                    }
                },
                error: function() {
                    $results.html('<div class="kg-embed-results empty">Bir hata oluştu. Lütfen tekrar deneyin.</div>');
                }
            });
        },
        
        renderResults: function(items) {
            var self = this;
            var $results = $('#kg-embed-results');
            
            if (!items || items.length === 0) {
                $results.html('<div class="kg-embed-results empty">' + kgEmbedSelector.labels.noResults + '</div>');
                return;
            }
            
            var html = '';
            
            items.forEach(function(item) {
                var isSelected = self.selectedItems.indexOf(item.id) !== -1;
                var selectedClass = isSelected ? 'selected' : '';
                var checkedAttr = isSelected ? 'checked' : '';
                
                html += '<div class="kg-embed-item ' + selectedClass + '">';
                html += '<input type="checkbox" class="kg-embed-item-checkbox" value="' + item.id + '" ' + checkedAttr + '>';
                
                if (item.image) {
                    html += '<img src="' + item.image + '" class="kg-embed-item-image" alt="' + self.escapeHtml(item.title) + '">';
                } else {
                    html += '<div class="kg-embed-item-image placeholder">';
                    html += '<span class="dashicons ' + item.icon + '"></span>';
                    html += '</div>';
                }
                
                html += '<div class="kg-embed-item-content">';
                html += '<div class="kg-embed-item-title">' + self.escapeHtml(item.title) + '</div>';
                
                if (item.meta) {
                    html += '<div class="kg-embed-item-meta">' + self.escapeHtml(item.meta) + '</div>';
                }
                
                html += '</div>';
                html += '</div>';
            });
            
            $results.html(html);
        },
        
        updateSelectedCount: function() {
            var count = this.selectedItems.length;
            $('#kg-embed-selected-count').text(count);
            
            if (count > 0) {
                $('#kg-embed-insert').prop('disabled', false);
            } else {
                $('#kg-embed-insert').prop('disabled', true);
            }
        },
        
        insertEmbed: function() {
            if (this.selectedItems.length === 0) {
                return;
            }
            
            var shortcode;
            
            if (this.selectedItems.length === 1) {
                shortcode = '[kg-embed type="' + this.currentType + '" id="' + this.selectedItems[0] + '"]';
            } else {
                shortcode = '[kg-embed type="' + this.currentType + '" ids="' + this.selectedItems.join(',') + '"]';
            }
            
            // Insert into editor
            if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                wp.media.editor.insert(shortcode);
            } else {
                // Fallback for classic editor
                var editor = document.getElementById('content');
                if (editor) {
                    editor.value += '\n\n' + shortcode + '\n\n';
                }
            }
            
            this.closeModal();
        },
        
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        KGEmbedSelector.init();
    });
    
})(jQuery);
