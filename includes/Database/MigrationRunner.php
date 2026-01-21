<?php
namespace KG_Core\Database;

/**
 * MigrationRunner - Migration tracking and execution system
 * 
 * Manages database migrations similar to Laravel's migration system
 */
class MigrationRunner {
    
    /**
     * Migrations directory path
     */
    const MIGRATIONS_DIR = KG_CORE_PATH . 'includes/Database/migrations/';
    
    /**
     * Get all migration files
     * 
     * @return array Array of migration file names
     */
    public static function getMigrations() {
        $migrations = [];
        
        if ( ! file_exists( self::MIGRATIONS_DIR ) ) {
            return $migrations;
        }
        
        $files = scandir( self::MIGRATIONS_DIR );
        
        foreach ( $files as $file ) {
            if ( $file === '.' || $file === '..' ) {
                continue;
            }
            
            if ( substr( $file, -4 ) === '.php' ) {
                $migrations[] = $file;
            }
        }
        
        sort( $migrations );
        
        return $migrations;
    }
    
    /**
     * Get executed migrations from database
     * 
     * @return array Array of executed migration names
     */
    public static function getExecutedMigrations() {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_migrations';
        
        // Check if migrations table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ) );
        
        if ( $table_exists !== $table ) {
            return [];
        }
        
        $results = $wpdb->get_results(
            "SELECT migration FROM {$table} ORDER BY id ASC",
            ARRAY_A
        );
        
        return array_column( $results, 'migration' );
    }
    
    /**
     * Get pending migrations that haven't been executed
     * 
     * @return array Array of pending migration file names
     */
    public static function getPendingMigrations() {
        $all_migrations = self::getMigrations();
        $executed_migrations = self::getExecutedMigrations();
        
        return array_diff( $all_migrations, $executed_migrations );
    }
    
    /**
     * Run all pending migrations
     * 
     * @return array Results of migration execution
     */
    public static function runPending() {
        $pending = self::getPendingMigrations();
        $results = [];
        
        if ( empty( $pending ) ) {
            return [
                'success' => true,
                'message' => 'No pending migrations to run.',
                'migrations' => []
            ];
        }
        
        // Get next batch number
        $batch = self::getNextBatchNumber();
        
        foreach ( $pending as $migration ) {
            $result = self::runMigration( $migration, $batch );
            $results[] = $result;
            
            // Stop on first error
            if ( ! $result['success'] ) {
                break;
            }
        }
        
        $successful_count = count( array_filter( $results, function( $r ) {
            return $r['success'];
        } ) );
        
        return [
            'success' => $successful_count === count( $pending ),
            'message' => sprintf(
                'Ran %d of %d migrations successfully.',
                $successful_count,
                count( $pending )
            ),
            'migrations' => $results
        ];
    }
    
    /**
     * Run a single migration
     * 
     * @param string $migration Migration file name
     * @param int $batch Batch number
     * @return array Result of migration execution
     */
    public static function runMigration( $migration, $batch = null ) {
        global $wpdb;
        
        if ( $batch === null ) {
            $batch = self::getNextBatchNumber();
        }
        
        $migration_file = self::MIGRATIONS_DIR . $migration;
        
        if ( ! file_exists( $migration_file ) ) {
            return [
                'success' => false,
                'migration' => $migration,
                'message' => 'Migration file not found.'
            ];
        }
        
        try {
            // Include migration file
            require_once $migration_file;
            
            // Extract class name from file name
            // Convention: 2024_01_20_create_example_table.php -> CreateExampleTable
            $class_name = self::getMigrationClassName( $migration );
            $full_class_name = "\\KG_Core\\Database\\Migrations\\{$class_name}";
            
            if ( ! class_exists( $full_class_name ) ) {
                return [
                    'success' => false,
                    'migration' => $migration,
                    'message' => "Migration class {$class_name} not found."
                ];
            }
            
            // Run migration
            $migration_instance = new $full_class_name();
            
            if ( ! method_exists( $migration_instance, 'up' ) ) {
                return [
                    'success' => false,
                    'migration' => $migration,
                    'message' => 'Migration class must have an up() method.'
                ];
            }
            
            $migration_instance->up();
            
            // Record migration
            $wpdb->insert(
                $wpdb->prefix . 'kg_migrations',
                [
                    'migration' => $migration,
                    'batch' => $batch
                ],
                [ '%s', '%d' ]
            );
            
            return [
                'success' => true,
                'migration' => $migration,
                'message' => 'Migration executed successfully.'
            ];
            
        } catch ( \Exception $e ) {
            return [
                'success' => false,
                'migration' => $migration,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Rollback the last batch of migrations
     * 
     * @return array Results of rollback
     */
    public static function rollback() {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_migrations';
        
        // Get last batch number
        $last_batch = $wpdb->get_var(
            "SELECT MAX(batch) FROM {$table}"
        );
        
        if ( ! $last_batch ) {
            return [
                'success' => true,
                'message' => 'No migrations to rollback.',
                'migrations' => []
            ];
        }
        
        // Get migrations in last batch
        $migrations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT migration FROM {$table} WHERE batch = %d ORDER BY id DESC",
                $last_batch
            ),
            ARRAY_A
        );
        
        $results = [];
        
        foreach ( $migrations as $migration_record ) {
            $migration = $migration_record['migration'];
            $migration_file = self::MIGRATIONS_DIR . $migration;
            
            if ( ! file_exists( $migration_file ) ) {
                $results[] = [
                    'success' => false,
                    'migration' => $migration,
                    'message' => 'Migration file not found.'
                ];
                continue;
            }
            
            try {
                require_once $migration_file;
                
                $class_name = self::getMigrationClassName( $migration );
                $full_class_name = "\\KG_Core\\Database\\Migrations\\{$class_name}";
                
                if ( ! class_exists( $full_class_name ) ) {
                    $results[] = [
                        'success' => false,
                        'migration' => $migration,
                        'message' => "Migration class {$class_name} not found."
                    ];
                    continue;
                }
                
                $migration_instance = new $full_class_name();
                
                if ( method_exists( $migration_instance, 'down' ) ) {
                    $migration_instance->down();
                }
                
                // Remove migration record
                $wpdb->delete(
                    $table,
                    [ 'migration' => $migration ],
                    [ '%s' ]
                );
                
                $results[] = [
                    'success' => true,
                    'migration' => $migration,
                    'message' => 'Migration rolled back successfully.'
                ];
                
            } catch ( \Exception $e ) {
                $results[] = [
                    'success' => false,
                    'migration' => $migration,
                    'message' => 'Error: ' . $e->getMessage()
                ];
            }
        }
        
        return [
            'success' => count( $results ) > 0,
            'message' => sprintf( 'Rolled back %d migrations.', count( $results ) ),
            'migrations' => $results
        ];
    }
    
    /**
     * Get migration status
     * 
     * @return array Status information
     */
    public static function getStatus() {
        $all_migrations = self::getMigrations();
        $executed_migrations = self::getExecutedMigrations();
        $pending_migrations = self::getPendingMigrations();
        
        global $wpdb;
        $table = $wpdb->prefix . 'kg_migrations';
        
        $last_batch = $wpdb->get_var(
            "SELECT MAX(batch) FROM {$table}"
        );
        
        return [
            'total_migrations' => count( $all_migrations ),
            'executed_migrations' => count( $executed_migrations ),
            'pending_migrations' => count( $pending_migrations ),
            'last_batch' => (int) $last_batch,
            'migrations' => [
                'all' => $all_migrations,
                'executed' => $executed_migrations,
                'pending' => $pending_migrations
            ]
        ];
    }
    
    /**
     * Get next batch number
     * 
     * @return int Next batch number
     */
    private static function getNextBatchNumber() {
        global $wpdb;
        $table = $wpdb->prefix . 'kg_migrations';
        
        $last_batch = $wpdb->get_var(
            "SELECT MAX(batch) FROM {$table}"
        );
        
        return ( $last_batch ? (int) $last_batch : 0 ) + 1;
    }
    
    /**
     * Get migration class name from file name
     * Convention: 2024_01_20_create_example_table.php -> CreateExampleTable
     * 
     * @param string $filename Migration file name
     * @return string Class name
     */
    private static function getMigrationClassName( $filename ) {
        // Remove .php extension
        $name = substr( $filename, 0, -4 );
        
        // Remove date prefix (YYYY_MM_DD_)
        $name = preg_replace( '/^\d{4}_\d{2}_\d{2}_/', '', $name );
        
        // Convert snake_case to PascalCase
        $parts = explode( '_', $name );
        $parts = array_map( 'ucfirst', $parts );
        
        return implode( '', $parts );
    }
}
