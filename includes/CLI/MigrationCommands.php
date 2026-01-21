<?php
namespace KG_Core\CLI;

use KG_Core\Database\DataMigration;
use KG_Core\Database\Schema;
use KG_Core\Config\FeatureFlags;

/**
 * WP-CLI Commands for Custom Table Migration
 * 
 * Usage examples:
 *   wp kg migrate all --batch-size=50
 *   wp kg migrate recipes --dry-run
 *   wp kg verify all
 *   wp kg rollback recipes --yes
 *   wp kg status
 *   wp kg schema create
 */
class MigrationCommands {
    
    /**
     * Register WP-CLI commands
     */
    public static function register() {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }
        
        \WP_CLI::add_command('kg migrate', [__CLASS__, 'migrate']);
        \WP_CLI::add_command('kg verify', [__CLASS__, 'verify']);
        \WP_CLI::add_command('kg rollback', [__CLASS__, 'rollback']);
        \WP_CLI::add_command('kg status', [__CLASS__, 'status']);
        \WP_CLI::add_command('kg schema', [__CLASS__, 'schema']);
        \WP_CLI::add_command('kg index', [__CLASS__, 'index']);
        \WP_CLI::add_command('kg cache', [__CLASS__, 'cache']);
    }
    
    /**
     * Migrate data from wp_postmeta to custom tables
     * 
     * ## OPTIONS
     * 
     * <type>
     * : Type to migrate: all, recipes, ingredients, posts
     * 
     * [--batch-size=<size>]
     * : Number of records to process per batch
     * ---
     * default: 50
     * ---
     * 
     * [--dry-run]
     * : Show what would be migrated without actually migrating
     * 
     * ## EXAMPLES
     * 
     *     wp kg migrate all
     *     wp kg migrate recipes --batch-size=100
     *     wp kg migrate ingredients --dry-run
     * 
     * @when after_wp_load
     */
    public static function migrate($args, $assoc_args) {
        list($type) = $args;
        
        $batch_size = isset($assoc_args['batch-size']) ? intval($assoc_args['batch-size']) : 50;
        $dry_run = isset($assoc_args['dry-run']);
        
        // Validate type
        $valid_types = ['all', 'recipes', 'ingredients', 'posts'];
        if (!in_array($type, $valid_types)) {
            \WP_CLI::error("Invalid type '{$type}'. Must be one of: " . implode(', ', $valid_types));
        }
        
        // Check if tables exist
        if (!Schema::tablesExist()) {
            \WP_CLI::error('Custom tables do not exist. Run: wp kg schema create');
        }
        
        if ($dry_run) {
            \WP_CLI::line(\WP_CLI::colorize('%YDRY RUN MODE - No data will be migrated%n'));
            \WP_CLI::line('');
        }
        
        \WP_CLI::line(\WP_CLI::colorize('%GMigration started%n'));
        \WP_CLI::line('Batch size: ' . $batch_size);
        \WP_CLI::line('');
        
        $results = [];
        
        if ($type === 'all' || $type === 'recipes') {
            \WP_CLI::line(\WP_CLI::colorize('%BProcessing Recipes...%n'));
            if (!$dry_run) {
                $results['recipes'] = DataMigration::migrateRecipes($batch_size);
                self::displayResults('Recipes', $results['recipes']);
            } else {
                self::showDryRunInfo('recipe');
            }
            \WP_CLI::line('');
        }
        
        if ($type === 'all' || $type === 'ingredients') {
            \WP_CLI::line(\WP_CLI::colorize('%BProcessing Ingredients...%n'));
            if (!$dry_run) {
                $results['ingredients'] = DataMigration::migrateIngredients($batch_size);
                self::displayResults('Ingredients', $results['ingredients']);
            } else {
                self::showDryRunInfo('ingredient');
            }
            \WP_CLI::line('');
        }
        
        if ($type === 'all' || $type === 'posts') {
            \WP_CLI::line(\WP_CLI::colorize('%BProcessing Posts...%n'));
            if (!$dry_run) {
                $results['posts'] = DataMigration::migratePosts($batch_size);
                self::displayResults('Posts', $results['posts']);
            } else {
                self::showDryRunInfo('post');
            }
            \WP_CLI::line('');
        }
        
        if (!$dry_run) {
            $total_migrated = 0;
            $total_errors = 0;
            
            foreach ($results as $result) {
                $total_migrated += $result['migrated'];
                $total_errors += count($result['errors']);
            }
            
            \WP_CLI::line(str_repeat('=', 50));
            \WP_CLI::line(\WP_CLI::colorize('%G✓ Migration complete!%n'));
            \WP_CLI::line("Total migrated: {$total_migrated}");
            
            if ($total_errors > 0) {
                \WP_CLI::warning("Total errors: {$total_errors}");
            }
        } else {
            \WP_CLI::success('Dry run complete. Use without --dry-run to perform actual migration.');
        }
    }
    
    /**
     * Verify migration coverage
     * 
     * ## OPTIONS
     * 
     * <type>
     * : Type to verify: all, recipes, ingredients, posts
     * 
     * ## EXAMPLES
     * 
     *     wp kg verify all
     *     wp kg verify recipes
     * 
     * @when after_wp_load
     */
    public static function verify($args, $assoc_args) {
        list($type) = $args;
        
        // Validate type
        $valid_types = ['all', 'recipes', 'ingredients', 'posts'];
        if (!in_array($type, $valid_types)) {
            \WP_CLI::error("Invalid type '{$type}'. Must be one of: " . implode(', ', $valid_types));
        }
        
        // Check if tables exist
        if (!Schema::tablesExist()) {
            \WP_CLI::error('Custom tables do not exist. Run: wp kg schema create');
        }
        
        \WP_CLI::line(\WP_CLI::colorize('%GVerifying Migration Coverage%n'));
        \WP_CLI::line('');
        
        $verification_map = [
            'recipes' => 'recipe',
            'ingredients' => 'ingredient',
            'posts' => 'post',
        ];
        
        $missing = DataMigration::verify($type === 'all' ? 'all' : $verification_map[$type]);
        
        $has_missing = false;
        
        foreach ($missing as $post_type => $missing_ids) {
            $label = ucfirst($post_type);
            
            if (empty($missing_ids)) {
                \WP_CLI::line(\WP_CLI::colorize("%G✓ {$label}:%n All migrated"));
            } else {
                $has_missing = true;
                $count = count($missing_ids);
                \WP_CLI::line(\WP_CLI::colorize("%Y⚠ {$label}:%n {$count} records not migrated"));
                
                // Show first 5 missing IDs
                if ($count > 0) {
                    $sample = array_slice($missing_ids, 0, 5);
                    \WP_CLI::line('  Missing IDs: ' . implode(', ', $sample));
                    if ($count > 5) {
                        \WP_CLI::line('  ... and ' . ($count - 5) . ' more');
                    }
                }
            }
            \WP_CLI::line('');
        }
        
        if ($has_missing) {
            \WP_CLI::warning('Some records are not migrated. Run migration again to complete.');
        } else {
            \WP_CLI::success('All records are migrated!');
        }
    }
    
    /**
     * Rollback migration (truncate tables)
     * 
     * ## OPTIONS
     * 
     * <type>
     * : Type to rollback: recipes, ingredients, posts, all
     * 
     * [--yes]
     * : Skip confirmation prompt
     * 
     * ## EXAMPLES
     * 
     *     wp kg rollback recipes
     *     wp kg rollback all --yes
     * 
     * @when after_wp_load
     */
    public static function rollback($args, $assoc_args) {
        list($type) = $args;
        
        // Validate type
        $valid_types = ['recipes', 'ingredients', 'posts', 'all'];
        if (!in_array($type, $valid_types)) {
            \WP_CLI::error("Invalid type '{$type}'. Must be one of: " . implode(', ', $valid_types));
        }
        
        // Check if tables exist
        if (!Schema::tablesExist()) {
            \WP_CLI::error('Custom tables do not exist.');
        }
        
        $skip_confirm = isset($assoc_args['yes']);
        
        // Confirmation prompt
        if (!$skip_confirm) {
            \WP_CLI::warning("This will DELETE ALL DATA from {$type} custom table(s)!");
            \WP_CLI::confirm('Are you sure you want to continue?');
        }
        
        \WP_CLI::line(\WP_CLI::colorize('%YRolling back migration...%n'));
        
        $type_map = [
            'recipes' => 'recipe',
            'ingredients' => 'ingredient',
            'posts' => 'post',
            'all' => 'all',
        ];
        
        $result = DataMigration::rollback($type_map[$type]);
        
        if ($result) {
            \WP_CLI::success("Rollback complete for: {$type}");
        } else {
            \WP_CLI::error("Rollback failed for: {$type}");
        }
    }
    
    /**
     * Show migration status
     * 
     * ## EXAMPLES
     * 
     *     wp kg status
     * 
     * @when after_wp_load
     */
    public static function status($args, $assoc_args) {
        \WP_CLI::line(\WP_CLI::colorize('%G=== KG Core Migration Status ===%n'));
        \WP_CLI::line('');
        
        // Schema status
        \WP_CLI::line(\WP_CLI::colorize('%BDatabase Schema:%n'));
        
        if (Schema::tablesExist()) {
            \WP_CLI::line(\WP_CLI::colorize('%G✓%n All custom tables exist'));
            
            $table_status = Schema::getTableStatus();
            foreach ($table_status as $table => $status) {
                if ($status['exists']) {
                    $count = number_format($status['row_count']);
                    \WP_CLI::line("  • {$table}: {$count} rows");
                }
            }
        } else {
            \WP_CLI::line(\WP_CLI::colorize('%R✗%n Custom tables do not exist'));
            \WP_CLI::line('  Run: wp kg schema create');
        }
        
        \WP_CLI::line('');
        
        // Feature flags
        \WP_CLI::line(\WP_CLI::colorize('%BFeature Flags:%n'));
        $flags = FeatureFlags::getAll();
        
        foreach ($flags as $flag => $value) {
            $status = $value ? \WP_CLI::colorize('%G✓ enabled%n') : \WP_CLI::colorize('%R✗ disabled%n');
            \WP_CLI::line("  • {$flag}: {$status}");
        }
        
        \WP_CLI::line('');
        
        // Migration coverage (if tables exist)
        if (Schema::tablesExist()) {
            \WP_CLI::line(\WP_CLI::colorize('%BMigration Coverage:%n'));
            
            $coverage = self::calculateCoverage();
            
            foreach ($coverage as $type => $data) {
                $label = ucfirst($type);
                $percent = $data['percent'];
                $bar = self::createProgressBar($percent);
                
                \WP_CLI::line("  {$label}:");
                \WP_CLI::line("    {$bar} {$percent}%");
                \WP_CLI::line("    {$data['migrated']} / {$data['total']} records");
            }
        }
        
        \WP_CLI::line('');
        \WP_CLI::success('Status check complete');
    }
    
    /**
     * Manage database schema
     * 
     * ## OPTIONS
     * 
     * <action>
     * : Action to perform: create, drop, status
     * 
     * [--yes]
     * : Skip confirmation prompt (for drop action)
     * 
     * ## EXAMPLES
     * 
     *     wp kg schema create
     *     wp kg schema status
     *     wp kg schema drop --yes
     * 
     * @when after_wp_load
     */
    public static function schema($args, $assoc_args) {
        list($action) = $args;
        
        // Validate action
        $valid_actions = ['create', 'drop', 'status'];
        if (!in_array($action, $valid_actions)) {
            \WP_CLI::error("Invalid action '{$action}'. Must be one of: " . implode(', ', $valid_actions));
        }
        
        switch ($action) {
            case 'create':
                \WP_CLI::line(\WP_CLI::colorize('%GCreating custom tables...%n'));
                
                if (Schema::tablesExist()) {
                    \WP_CLI::warning('Custom tables already exist');
                } else {
                    Schema::createTables();
                    
                    if (Schema::tablesExist()) {
                        \WP_CLI::success('Custom tables created successfully!');
                    } else {
                        \WP_CLI::error('Failed to create custom tables');
                    }
                }
                break;
                
            case 'drop':
                $skip_confirm = isset($assoc_args['yes']);
                
                if (!$skip_confirm) {
                    \WP_CLI::warning('This will DELETE ALL custom tables and data!');
                    \WP_CLI::confirm('Are you sure you want to continue?');
                }
                
                \WP_CLI::line(\WP_CLI::colorize('%YDropping custom tables...%n'));
                Schema::dropTables();
                
                if (!Schema::tablesExist()) {
                    \WP_CLI::success('Custom tables dropped successfully');
                } else {
                    \WP_CLI::error('Failed to drop custom tables');
                }
                break;
                
            case 'status':
                \WP_CLI::line(\WP_CLI::colorize('%GDatabase Schema Status%n'));
                \WP_CLI::line('');
                
                $table_status = Schema::getTableStatus();
                
                foreach ($table_status as $table => $status) {
                    if ($status['exists']) {
                        $count = number_format($status['row_count']);
                        \WP_CLI::line(\WP_CLI::colorize("%G✓%n {$table}: {$count} rows"));
                    } else {
                        \WP_CLI::line(\WP_CLI::colorize("%R✗%n {$table}: Does not exist"));
                    }
                }
                
                \WP_CLI::line('');
                
                if (Schema::tablesExist()) {
                    \WP_CLI::success('All tables exist');
                } else {
                    \WP_CLI::warning('Some tables are missing. Run: wp kg schema create');
                }
                break;
        }
    }
    
    /**
     * Display migration results
     */
    private static function displayResults($label, $results) {
        \WP_CLI::line("  Migrated: {$results['migrated']}");
        \WP_CLI::line("  Skipped:  {$results['skipped']}");
        
        if (!empty($results['errors'])) {
            $error_count = count($results['errors']);
            \WP_CLI::warning("  Errors:   {$error_count}");
            
            // Show first 3 errors
            $sample_errors = array_slice($results['errors'], 0, 3);
            foreach ($sample_errors as $error) {
                \WP_CLI::line("    - Post {$error['post_id']}: {$error['error']}");
            }
            
            if ($error_count > 3) {
                \WP_CLI::line("    ... and " . ($error_count - 3) . " more errors");
            }
        }
    }
    
    /**
     * Show dry run information
     */
    private static function showDryRunInfo($post_type) {
        global $wpdb;
        
        // Count total posts
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $post_type
        ));
        
        // Count already migrated
        $table_map = [
            'recipe' => 'kg_recipe_meta',
            'ingredient' => 'kg_ingredient_meta',
            'post' => 'kg_post_meta',
        ];
        
        $table = $wpdb->prefix . $table_map[$post_type];
        $migrated = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        $to_migrate = max(0, $total - $migrated);
        
        \WP_CLI::line("  Total posts: {$total}");
        \WP_CLI::line("  Already migrated: {$migrated}");
        \WP_CLI::line(\WP_CLI::colorize("  %YWould migrate: {$to_migrate}%n"));
    }
    
    /**
     * Calculate migration coverage
     */
    private static function calculateCoverage() {
        global $wpdb;
        
        $coverage = [];
        
        $types = [
            'recipe' => 'kg_recipe_meta',
            'ingredient' => 'kg_ingredient_meta',
            'post' => 'kg_post_meta',
        ];
        
        foreach ($types as $post_type => $table_name) {
            $table = $wpdb->prefix . $table_name;
            
            // Count total published posts with KG meta
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s 
                AND p.post_status = 'publish'
                AND pm.meta_key LIKE '_kg_%%'",
                $post_type
            ));
            
            // Count migrated
            $migrated = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            
            $percent = $total > 0 ? round(($migrated / $total) * 100, 1) : 100;
            
            $coverage[$post_type] = [
                'total' => (int) $total,
                'migrated' => (int) $migrated,
                'percent' => $percent,
            ];
        }
        
        return $coverage;
    }
    
    /**
     * Create ASCII progress bar
     */
    private static function createProgressBar($percent, $width = 30) {
        $filled = round(($percent / 100) * $width);
        $empty = $width - $filled;
        
        $bar = '[' . str_repeat('=', $filled) . str_repeat(' ', $empty) . ']';
        
        if ($percent >= 100) {
            return \WP_CLI::colorize("%G{$bar}%n");
        } elseif ($percent >= 50) {
            return \WP_CLI::colorize("%Y{$bar}%n");
        } else {
            return \WP_CLI::colorize("%R{$bar}%n");
        }
    }
    
    /**
     * Manage postmeta indexes
     * 
     * ## OPTIONS
     * 
     * <action>
     * : Action to perform: add, remove, status
     * 
     * ## EXAMPLES
     * 
     *     wp kg index add
     *     wp kg index remove
     *     wp kg index status
     * 
     * @when after_wp_load
     */
    public static function index($args, $assoc_args) {
        list($action) = $args;
        
        // Validate action
        $valid_actions = ['add', 'remove', 'status'];
        if (!in_array($action, $valid_actions)) {
            \WP_CLI::error("Invalid action '{$action}'. Must be one of: " . implode(', ', $valid_actions));
        }
        
        global $wpdb;
        
        switch ($action) {
            case 'add':
                \WP_CLI::line(\WP_CLI::colorize('%GAdding postmeta indexes...%n'));
                
                // Get existing indexes
                $existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta}");
                $index_names = array_column($existing_indexes, 'Key_name');
                
                $added = 0;
                
                // Add meta_key + meta_value composite index
                if (!in_array('idx_kg_meta_key_value', $index_names)) {
                    $result = $wpdb->query("CREATE INDEX idx_kg_meta_key_value ON {$wpdb->postmeta} (meta_key, meta_value(191))");
                    if ($result !== false) {
                        \WP_CLI::line(\WP_CLI::colorize('%G✓%n Added index: idx_kg_meta_key_value'));
                        $added++;
                    } else {
                        \WP_CLI::warning('Failed to add index: idx_kg_meta_key_value');
                    }
                } else {
                    \WP_CLI::line('Index already exists: idx_kg_meta_key_value');
                }
                
                // Add meta_key + post_id composite index
                if (!in_array('idx_kg_meta_key_post', $index_names)) {
                    $result = $wpdb->query("CREATE INDEX idx_kg_meta_key_post ON {$wpdb->postmeta} (meta_key, post_id)");
                    if ($result !== false) {
                        \WP_CLI::line(\WP_CLI::colorize('%G✓%n Added index: idx_kg_meta_key_post'));
                        $added++;
                    } else {
                        \WP_CLI::warning('Failed to add index: idx_kg_meta_key_post');
                    }
                } else {
                    \WP_CLI::line('Index already exists: idx_kg_meta_key_post');
                }
                
                if ($added > 0) {
                    \WP_CLI::success("Added {$added} index(es)");
                } else {
                    \WP_CLI::success('All indexes already exist');
                }
                break;
                
            case 'remove':
                \WP_CLI::line(\WP_CLI::colorize('%YRemoving postmeta indexes...%n'));
                
                $removed = 0;
                
                $result = $wpdb->query("DROP INDEX IF EXISTS idx_kg_meta_key_value ON {$wpdb->postmeta}");
                if ($result !== false) {
                    \WP_CLI::line(\WP_CLI::colorize('%G✓%n Removed index: idx_kg_meta_key_value'));
                    $removed++;
                }
                
                $result = $wpdb->query("DROP INDEX IF EXISTS idx_kg_meta_key_post ON {$wpdb->postmeta}");
                if ($result !== false) {
                    \WP_CLI::line(\WP_CLI::colorize('%G✓%n Removed index: idx_kg_meta_key_post'));
                    $removed++;
                }
                
                \WP_CLI::success("Removed {$removed} index(es)");
                break;
                
            case 'status':
                \WP_CLI::line(\WP_CLI::colorize('%GPostmeta Index Status%n'));
                \WP_CLI::line('');
                
                // Get existing indexes
                $existing_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->postmeta}");
                $index_names = array_column($existing_indexes, 'Key_name');
                
                $indexes = ['idx_kg_meta_key_value', 'idx_kg_meta_key_post'];
                
                foreach ($indexes as $index) {
                    if (in_array($index, $index_names)) {
                        \WP_CLI::line(\WP_CLI::colorize("%G✓%n {$index}: Exists"));
                    } else {
                        \WP_CLI::line(\WP_CLI::colorize("%R✗%n {$index}: Does not exist"));
                    }
                }
                
                \WP_CLI::line('');
                \WP_CLI::success('Index status check complete');
                break;
        }
    }
    
    /**
     * Manage cache warming
     * 
     * ## OPTIONS
     * 
     * <action>
     * : Action to perform: warm, status
     * 
     * ## EXAMPLES
     * 
     *     wp kg cache warm
     *     wp kg cache status
     * 
     * @when after_wp_load
     */
    public static function cache($args, $assoc_args) {
        list($action) = $args;
        
        // Validate action
        $valid_actions = ['warm', 'status'];
        if (!in_array($action, $valid_actions)) {
            \WP_CLI::error("Invalid action '{$action}'. Must be one of: " . implode(', ', $valid_actions));
        }
        
        if (!class_exists('\KG_Core\Services\CacheWarmer')) {
            \WP_CLI::error('CacheWarmer service not available');
        }
        
        switch ($action) {
            case 'warm':
                \WP_CLI::line(\WP_CLI::colorize('%GWarming caches...%n'));
                
                \KG_Core\Services\CacheWarmer::trigger();
                
                \WP_CLI::success('Cache warming complete');
                break;
                
            case 'status':
                \WP_CLI::line(\WP_CLI::colorize('%GCache Warming Status%n'));
                \WP_CLI::line('');
                
                $next_run = wp_next_scheduled('kg_cache_warm');
                
                if ($next_run) {
                    $time_until = human_time_diff(time(), $next_run);
                    \WP_CLI::line(\WP_CLI::colorize("%G✓%n Cache warming is scheduled"));
                    \WP_CLI::line("  Next run: in {$time_until}");
                } else {
                    \WP_CLI::line(\WP_CLI::colorize("%R✗%n Cache warming is not scheduled"));
                }
                
                \WP_CLI::line('');
                \WP_CLI::success('Cache status check complete');
                break;
        }
    }
}
