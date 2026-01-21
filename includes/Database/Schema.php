<?php
namespace KG_Core\Database;

/**
 * Schema - Custom Table Schema for KG Core
 * 
 * Creates and manages custom meta tables for recipes, ingredients, and posts
 * to replace wp_postmeta EAV structure for better performance
 */
class Schema {
    
    const DB_VERSION = '1.0.0';
    const DB_VERSION_OPTION = 'kg_core_db_version';
    
    /**
     * Activate - Called on plugin activation
     */
    public static function activate() {
        self::createTables();
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }
    
    /**
     * Create all custom tables
     */
    public static function createTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        // 1. wp_kg_recipe_meta - Recipe metadata table
        $sql_recipe_meta = "CREATE TABLE {$prefix}kg_recipe_meta (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT UNSIGNED NOT NULL UNIQUE,
            prep_time INT UNSIGNED DEFAULT NULL COMMENT 'Hazırlama süresi (dakika)',
            cook_time INT UNSIGNED DEFAULT NULL COMMENT 'Pişirme süresi (dakika)',
            serving_size VARCHAR(100) DEFAULT NULL COMMENT 'Porsiyon sayısı',
            difficulty ENUM('kolay', 'orta', 'zor') DEFAULT NULL COMMENT 'Zorluk seviyesi',
            freezable BOOLEAN DEFAULT FALSE COMMENT 'Dondurulabilir mi?',
            storage_info TEXT DEFAULT NULL COMMENT 'Saklama bilgileri',
            is_featured BOOLEAN DEFAULT FALSE COMMENT 'Öne çıkarılmış mı?',
            video_url VARCHAR(500) DEFAULT NULL COMMENT 'Video URL',
            special_notes TEXT DEFAULT NULL COMMENT 'Özel notlar',
            calories DECIMAL(10,2) DEFAULT NULL COMMENT 'Kalori',
            protein DECIMAL(10,2) DEFAULT NULL COMMENT 'Protein (g)',
            carbs DECIMAL(10,2) DEFAULT NULL COMMENT 'Karbonhidrat (g)',
            fat DECIMAL(10,2) DEFAULT NULL COMMENT 'Yağ (g)',
            fiber DECIMAL(10,2) DEFAULT NULL COMMENT 'Lif (g)',
            sugar DECIMAL(10,2) DEFAULT NULL COMMENT 'Şeker (g)',
            sodium DECIMAL(10,2) DEFAULT NULL COMMENT 'Sodyum (mg)',
            vitamins TEXT DEFAULT NULL COMMENT 'Vitaminler',
            minerals TEXT DEFAULT NULL COMMENT 'Mineraller',
            expert_user_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Uzman kullanıcı ID',
            expert_name VARCHAR(255) DEFAULT NULL COMMENT 'Uzman adı',
            expert_title VARCHAR(255) DEFAULT NULL COMMENT 'Uzman ünvanı',
            expert_note TEXT DEFAULT NULL COMMENT 'Uzman notu',
            expert_approved BOOLEAN DEFAULT FALSE COMMENT 'Uzman onayı',
            ingredients JSON DEFAULT NULL COMMENT 'Malzemeler listesi',
            instructions JSON DEFAULT NULL COMMENT 'Talimatlar',
            substitutes JSON DEFAULT NULL COMMENT 'Alternatif malzemeler',
            cross_sell JSON DEFAULT NULL COMMENT 'Çapraz satış ürünleri',
            rating DECIMAL(3,2) DEFAULT NULL COMMENT 'Genel puan',
            rating_count INT UNSIGNED DEFAULT 0 COMMENT 'Puan sayısı',
            base_rating DECIMAL(3,2) DEFAULT NULL COMMENT 'Temel puan',
            base_rating_count INT UNSIGNED DEFAULT 0 COMMENT 'Temel puan sayısı',
            ratings_data JSON DEFAULT NULL COMMENT 'Detaylı puanlama verileri',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_post_id (post_id),
            INDEX idx_is_featured (is_featured),
            INDEX idx_difficulty (difficulty),
            INDEX idx_expert_approved (expert_approved),
            INDEX idx_rating (rating)
        ) $charset_collate;";
        
        dbDelta( $sql_recipe_meta );
        
        // 2. wp_kg_ingredient_meta - Ingredient metadata table
        $sql_ingredient_meta = "CREATE TABLE {$prefix}kg_ingredient_meta (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT UNSIGNED NOT NULL UNIQUE,
            start_age INT UNSIGNED DEFAULT NULL COMMENT 'Başlangıç yaşı (ay)',
            allergy_risk ENUM('low', 'medium', 'high') DEFAULT NULL COMMENT 'Alerji riski',
            is_featured BOOLEAN DEFAULT FALSE COMMENT 'Öne çıkarılmış mı?',
            season JSON DEFAULT NULL COMMENT 'Mevsim bilgisi',
            calories_100g DECIMAL(10,2) DEFAULT NULL COMMENT '100g başına kalori',
            protein_100g DECIMAL(10,2) DEFAULT NULL COMMENT '100g başına protein (g)',
            carbs_100g DECIMAL(10,2) DEFAULT NULL COMMENT '100g başına karbonhidrat (g)',
            fat_100g DECIMAL(10,2) DEFAULT NULL COMMENT '100g başına yağ (g)',
            fiber_100g DECIMAL(10,2) DEFAULT NULL COMMENT '100g başına lif (g)',
            sugar_100g DECIMAL(10,2) DEFAULT NULL COMMENT '100g başına şeker (g)',
            vitamins TEXT DEFAULT NULL COMMENT 'Vitaminler',
            minerals TEXT DEFAULT NULL COMMENT 'Mineraller',
            cross_contamination TEXT DEFAULT NULL COMMENT 'Çapraz bulaşma riski',
            allergy_symptoms TEXT DEFAULT NULL COMMENT 'Alerji belirtileri',
            alternatives TEXT DEFAULT NULL COMMENT 'Alternatifler',
            benefits TEXT DEFAULT NULL COMMENT 'Faydaları',
            storage_tips TEXT DEFAULT NULL COMMENT 'Saklama ipuçları',
            preparation_tips TEXT DEFAULT NULL COMMENT 'Hazırlama ipuçları',
            selection_tips TEXT DEFAULT NULL COMMENT 'Seçim ipuçları',
            pro_tips TEXT DEFAULT NULL COMMENT 'Profesyonel ipuçları',
            prep_methods JSON DEFAULT NULL COMMENT 'Hazırlama yöntemleri',
            prep_by_age JSON DEFAULT NULL COMMENT 'Yaşa göre hazırlama',
            pairings JSON DEFAULT NULL COMMENT 'Eşleştirmeler',
            faq JSON DEFAULT NULL COMMENT 'Sıkça sorulan sorular',
            expert_user_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Uzman kullanıcı ID',
            expert_name VARCHAR(255) DEFAULT NULL COMMENT 'Uzman adı',
            expert_title VARCHAR(255) DEFAULT NULL COMMENT 'Uzman ünvanı',
            expert_note TEXT DEFAULT NULL COMMENT 'Uzman notu',
            expert_approved BOOLEAN DEFAULT FALSE COMMENT 'Uzman onayı',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_post_id (post_id),
            INDEX idx_is_featured (is_featured),
            INDEX idx_allergy_risk (allergy_risk),
            INDEX idx_start_age (start_age),
            INDEX idx_expert_approved (expert_approved)
        ) $charset_collate;";
        
        dbDelta( $sql_ingredient_meta );
        
        // 3. wp_kg_post_meta - Post metadata table
        $sql_post_meta = "CREATE TABLE {$prefix}kg_post_meta (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT UNSIGNED NOT NULL UNIQUE,
            is_featured BOOLEAN DEFAULT FALSE COMMENT 'Öne çıkarılmış mı?',
            is_sponsored BOOLEAN DEFAULT FALSE COMMENT 'Sponsorlu içerik mi?',
            sponsor_name VARCHAR(255) DEFAULT NULL COMMENT 'Sponsor adı',
            sponsor_url VARCHAR(500) DEFAULT NULL COMMENT 'Sponsor URL',
            sponsor_logo_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Sponsor logo medya ID',
            sponsor_light_logo_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Sponsor açık tema logo ID',
            direct_redirect BOOLEAN DEFAULT FALSE COMMENT 'Doğrudan yönlendirme',
            gam_impression_url VARCHAR(1000) DEFAULT NULL COMMENT 'GAM görüntüleme URL',
            gam_click_url VARCHAR(1000) DEFAULT NULL COMMENT 'GAM tıklama URL',
            has_discount BOOLEAN DEFAULT FALSE COMMENT 'İndirim var mı?',
            discount_text VARCHAR(255) DEFAULT NULL COMMENT 'İndirim metni',
            expert_user_id BIGINT UNSIGNED DEFAULT NULL COMMENT 'Uzman kullanıcı ID',
            expert_name VARCHAR(255) DEFAULT NULL COMMENT 'Uzman adı',
            expert_title VARCHAR(255) DEFAULT NULL COMMENT 'Uzman ünvanı',
            expert_note TEXT DEFAULT NULL COMMENT 'Uzman notu',
            expert_approved BOOLEAN DEFAULT FALSE COMMENT 'Uzman onayı',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_post_id (post_id),
            INDEX idx_is_featured (is_featured),
            INDEX idx_is_sponsored (is_sponsored),
            INDEX idx_expert_approved (expert_approved)
        ) $charset_collate;";
        
        dbDelta( $sql_post_meta );
        
        // 4. wp_kg_migrations - Migration tracking table
        $sql_migrations = "CREATE TABLE {$prefix}kg_migrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE COMMENT 'Migration dosya adı',
            batch INT UNSIGNED NOT NULL COMMENT 'Batch numarası',
            executed_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Çalıştırılma zamanı',
            INDEX idx_batch (batch)
        ) $charset_collate;";
        
        dbDelta( $sql_migrations );
    }
    
    /**
     * Check if all tables exist
     * 
     * @return bool True if all tables exist
     */
    public static function tablesExist() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $tables = [
            "{$prefix}kg_recipe_meta",
            "{$prefix}kg_ingredient_meta",
            "{$prefix}kg_post_meta",
            "{$prefix}kg_migrations"
        ];
        
        foreach ( $tables as $table ) {
            $table_exists = $wpdb->get_var( $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ) );
            
            if ( $table_exists !== $table ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get status of all tables
     * 
     * @return array Array of table status with row counts
     */
    public static function getTableStatus() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $tables = [
            'kg_recipe_meta',
            'kg_ingredient_meta',
            'kg_post_meta',
            'kg_migrations'
        ];
        
        $status = [];
        
        foreach ( $tables as $table ) {
            $full_table_name = $prefix . $table;
            
            // Check if table exists
            $table_exists = $wpdb->get_var( $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $full_table_name
            ) );
            
            if ( $table_exists === $full_table_name ) {
                // Get row count
                $row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$full_table_name}" );
                
                $status[ $table ] = [
                    'exists' => true,
                    'row_count' => (int) $row_count
                ];
            } else {
                $status[ $table ] = [
                    'exists' => false,
                    'row_count' => 0
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Drop all custom tables (for uninstall)
     * WARNING: This will delete all data!
     */
    public static function dropTables() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $tables = [
            "{$prefix}kg_recipe_meta",
            "{$prefix}kg_ingredient_meta",
            "{$prefix}kg_post_meta",
            "{$prefix}kg_migrations"
        ];
        
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
        
        delete_option( self::DB_VERSION_OPTION );
    }
}
