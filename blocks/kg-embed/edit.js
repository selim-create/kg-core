import { useEffect, useState } from '@wordpress/element';
import { TextControl, Spinner, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function Edit({ attributes, setAttributes }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    
    const { contentType, selectedItems } = attributes;
    
    // Debounced search effect
    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchTerm.length > 0) {
                searchContent();
            } else {
                setSearchResults([]);
            }
        }, 300);
        
        return () => clearTimeout(timer);
    }, [searchTerm, contentType]);
    
    const searchContent = async () => {
        setIsLoading(true);
        setError('');
        
        try {
            const formData = new FormData();
            formData.append('action', 'kg_block_search_content');
            formData.append('nonce', kgBlockEmbed.nonce);
            formData.append('type', contentType);
            formData.append('search', searchTerm);
            
            const response = await fetch(kgBlockEmbed.ajaxUrl, {
                method: 'POST',
                body: formData,
            });
            
            const data = await response.json();
            
            if (data.success) {
                setSearchResults(data.data.items || []);
            } else {
                setError(data.data?.message || __('Search failed', 'kg-core'));
                setSearchResults([]);
            }
        } catch (err) {
            setError(__('Search error occurred', 'kg-core'));
            setSearchResults([]);
        } finally {
            setIsLoading(false);
        }
    };
    
    const selectItem = (item) => {
        const isSelected = selectedItems.some(selected => selected.id === item.id);
        
        if (isSelected) {
            // Remove item
            setAttributes({
                selectedItems: selectedItems.filter(selected => selected.id !== item.id)
            });
        } else {
            // Add item
            setAttributes({
                selectedItems: [...selectedItems, item]
            });
        }
    };
    
    const removeItem = (itemId) => {
        setAttributes({
            selectedItems: selectedItems.filter(item => item.id !== itemId)
        });
    };
    
    const isItemSelected = (itemId) => {
        return selectedItems.some(item => item.id === itemId);
    };
    
    return (
        <div className="kg-embed-block">
            {/* Content Type Tabs */}
            <div className="kg-embed-tabs">
                <button 
                    className={contentType === 'recipe' ? 'active' : ''}
                    onClick={() => {
                        setAttributes({ contentType: 'recipe' });
                        setSearchResults([]);
                        setSearchTerm('');
                    }}
                >
                    🥕 {__('Tarifler', 'kg-core')}
                </button>
                <button 
                    className={contentType === 'ingredient' ? 'active' : ''}
                    onClick={() => {
                        setAttributes({ contentType: 'ingredient' });
                        setSearchResults([]);
                        setSearchTerm('');
                    }}
                >
                    🥬 {__('Malzemeler', 'kg-core')}
                </button>
                <button 
                    className={contentType === 'tool' ? 'active' : ''}
                    onClick={() => {
                        setAttributes({ contentType: 'tool' });
                        setSearchResults([]);
                        setSearchTerm('');
                    }}
                >
                    🔧 {__('Araçlar', 'kg-core')}
                </button>
                <button 
                    className={contentType === 'post' ? 'active' : ''}
                    onClick={() => {
                        setAttributes({ contentType: 'post' });
                        setSearchResults([]);
                        setSearchTerm('');
                    }}
                >
                    📖 {__('Yazılar', 'kg-core')}
                </button>
            </div>
            
            {/* Search Input */}
            <div className="kg-embed-search">
                <TextControl
                    placeholder={__('İçerik ara...', 'kg-core')}
                    value={searchTerm}
                    onChange={setSearchTerm}
                />
            </div>
            
            {/* Loading State */}
            {isLoading && (
                <div className="kg-embed-loading">
                    <Spinner />
                    <span>{__('Yükleniyor...', 'kg-core')}</span>
                </div>
            )}
            
            {/* Error State */}
            {error && (
                <div className="kg-embed-error">
                    {error}
                </div>
            )}
            
            {/* Search Results */}
            {!isLoading && searchResults.length > 0 && (
                <div className="kg-embed-results">
                    <h4>{__('Sonuçlar', 'kg-core')}</h4>
                    {searchResults.map(item => (
                        <div 
                            key={item.id} 
                            className={`kg-embed-result-item ${isItemSelected(item.id) ? 'selected' : ''}`}
                            onClick={() => selectItem(item)}
                        >
                            {item.image && (
                                <img src={item.image} alt={item.title} />
                            )}
                            {!item.image && (
                                <div className="kg-embed-no-image">
                                    <span className={`dashicons ${item.icon || 'dashicons-admin-post'}`}></span>
                                </div>
                            )}
                            <div className="kg-embed-item-info">
                                <div className="kg-embed-item-title">{item.title}</div>
                                {item.meta && (
                                    <div className="kg-embed-item-meta">{item.meta}</div>
                                )}
                            </div>
                            {isItemSelected(item.id) && (
                                <span className="kg-embed-checkmark">✓</span>
                            )}
                        </div>
                    ))}
                </div>
            )}
            
            {/* No Results */}
            {!isLoading && searchTerm && searchResults.length === 0 && !error && (
                <div className="kg-embed-no-results">
                    {__('Sonuç bulunamadı', 'kg-core')}
                </div>
            )}
            
            {/* Selected Items Preview */}
            {selectedItems.length > 0 && (
                <div className="kg-embed-preview">
                    <h4>{__('Seçilen İçerikler', 'kg-core')} ({selectedItems.length})</h4>
                    <div className="kg-embed-preview-list">
                        {selectedItems.map(item => (
                            <div key={item.id} className="kg-embed-preview-item">
                                {item.image && (
                                    <img src={item.image} alt={item.title} />
                                )}
                                {!item.image && (
                                    <div className="kg-embed-no-image-small">
                                        <span className={`dashicons ${item.icon || 'dashicons-admin-post'}`}></span>
                                    </div>
                                )}
                                <div className="kg-embed-preview-info">
                                    <div className="kg-embed-preview-title">{item.title}</div>
                                    {item.meta && (
                                        <div className="kg-embed-preview-meta">{item.meta}</div>
                                    )}
                                </div>
                                <button 
                                    className="kg-embed-remove-btn"
                                    onClick={() => removeItem(item.id)}
                                    aria-label={__('Kaldır', 'kg-core')}
                                >
                                    ×
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
