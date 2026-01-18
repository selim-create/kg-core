export default function save({ attributes }) {
    const { contentType, selectedItems } = attributes;
    
    if (!selectedItems || selectedItems.length === 0) {
        return null;
    }
    
    const ids = selectedItems.map(item => item.id).join(',');
    
    // Return shortcode for server-side rendering
    return (
        <div 
            className="kg-embed-placeholder" 
            data-type={contentType} 
            data-ids={ids}
        >
            {`[kg-embed type="${contentType}" ids="${ids}"]`}
        </div>
    );
}
