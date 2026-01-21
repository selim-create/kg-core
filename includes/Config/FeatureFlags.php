<?php
namespace KG_Core\Config;

/**
 * FeatureFlags - Feature flag system for gradual rollout
 * 
 * Manages feature flags stored in WordPress options table.
 * Supports dual-write migration strategy for custom tables.
 */
class FeatureFlags {
    
    /**
     * Option name in WordPress options table
     */
    private static $option_name = 'kg_feature_flags';
    
    /**
     * Default flag values
     */
    private static $defaults = [
        'dual_write' => true,
        'read_from_custom_table' => true,
        'custom_table_only' => false,
    ];
    
    /**
     * Check if a feature flag is enabled
     * 
     * @param string $flag Flag name
     * @return bool True if enabled, false otherwise
     */
    public static function isEnabled( $flag ) {
        $flags = self::getAll();
        
        if ( ! isset( $flags[ $flag ] ) ) {
            // Return default if exists, otherwise false
            return isset( self::$defaults[ $flag ] ) ? self::$defaults[ $flag ] : false;
        }
        
        return (bool) $flags[ $flag ];
    }
    
    /**
     * Set a feature flag value
     * 
     * @param string $flag Flag name
     * @param bool $value Flag value
     * @return bool True on success, false on failure
     */
    public static function set( $flag, $value ) {
        $flags = self::getAll();
        $flags[ $flag ] = (bool) $value;
        
        return update_option( self::$option_name, $flags );
    }
    
    /**
     * Enable a feature flag
     * 
     * @param string $flag Flag name
     * @return bool True on success, false on failure
     */
    public static function enable( $flag ) {
        return self::set( $flag, true );
    }
    
    /**
     * Disable a feature flag
     * 
     * @param string $flag Flag name
     * @return bool True on success, false on failure
     */
    public static function disable( $flag ) {
        return self::set( $flag, false );
    }
    
    /**
     * Get all feature flags
     * 
     * @return array All flags with values
     */
    public static function getAll() {
        $flags = get_option( self::$option_name, [] );
        
        // Merge with defaults
        return wp_parse_args( $flags, self::$defaults );
    }
    
    /**
     * Reset all flags to defaults
     * 
     * @return bool True on success, false on failure
     */
    public static function reset() {
        return update_option( self::$option_name, self::$defaults );
    }
    
    /**
     * Check if custom tables should be used for reading
     * 
     * Convenience method for dual-write migration
     * 
     * @return bool True if custom tables should be used
     */
    public static function useCustomTables() {
        return self::isEnabled( 'read_from_custom_table' );
    }
    
    /**
     * Check if dual-write is enabled
     * 
     * Convenience method - writes to both wp_postmeta and custom tables
     * 
     * @return bool True if dual-write is enabled
     */
    public static function useDualWrite() {
        return self::isEnabled( 'dual_write' );
    }
    
    /**
     * Check if custom table only mode is enabled
     * 
     * Convenience method - only use custom tables, no wp_postmeta
     * 
     * @return bool True if custom table only mode is enabled
     */
    public static function customTableOnly() {
        return self::isEnabled( 'custom_table_only' );
    }
}
