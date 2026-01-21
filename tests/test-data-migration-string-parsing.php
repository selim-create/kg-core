<?php
/**
 * Test DataMigration String Parsing
 * 
 * Tests the string parsing functionality for numeric fields
 */

class Test_DataMigration_String_Parsing extends WP_UnitTestCase {
    
    /**
     * Test parseTimeValue method using reflection
     */
    public function test_parse_time_value() {
        $reflection = new ReflectionClass('KG_Core\Database\DataMigration');
        $method = $reflection->getMethod('parseTimeValue');
        $method->setAccessible(true);
        
        // Test "30 dakika" -> 30
        $result = $method->invokeArgs(null, ["30 dakika"]);
        $this->assertEquals(30, $result, '"30 dakika" should be parsed as 30');
        
        // Test "1 saat" -> 60
        $result = $method->invokeArgs(null, ["1 saat"]);
        $this->assertEquals(60, $result, '"1 saat" should be parsed as 60 minutes');
        
        // Test "1.5 saat" -> 90
        $result = $method->invokeArgs(null, ["1.5 saat"]);
        $this->assertEquals(90, $result, '"1.5 saat" should be parsed as 90 minutes');
        
        // Test "45 dk" -> 45
        $result = $method->invokeArgs(null, ["45 dk"]);
        $this->assertEquals(45, $result, '"45 dk" should be parsed as 45');
        
        // Test numeric value
        $result = $method->invokeArgs(null, [30]);
        $this->assertEquals(30, $result, 'Numeric 30 should stay as 30');
        
        // Test null value
        $result = $method->invokeArgs(null, [null]);
        $this->assertNull($result, 'null should return null');
        
        // Test empty string
        $result = $method->invokeArgs(null, [""]);
        $this->assertNull($result, 'Empty string should return null');
    }
    
    /**
     * Test parseNutritionValue method using reflection
     */
    public function test_parse_nutrition_value() {
        $reflection = new ReflectionClass('KG_Core\Database\DataMigration');
        $method = $reflection->getMethod('parseNutritionValue');
        $method->setAccessible(true);
        
        // Test "180 kcal" -> 180.0
        $result = $method->invokeArgs(null, ["180 kcal"]);
        $this->assertEquals(180.0, $result, '"180 kcal" should be parsed as 180.0');
        
        // Test "6 g" -> 6.0
        $result = $method->invokeArgs(null, ["6 g"]);
        $this->assertEquals(6.0, $result, '"6 g" should be parsed as 6.0');
        
        // Test "200 mg" -> 200.0
        $result = $method->invokeArgs(null, ["200 mg"]);
        $this->assertEquals(200.0, $result, '"200 mg" should be parsed as 200.0');
        
        // Test "3,5 g" (Turkish decimal) -> 3.5
        $result = $method->invokeArgs(null, ["3,5 g"]);
        $this->assertEquals(3.5, $result, '"3,5 g" should be parsed as 3.5');
        
        // Test "12.5" -> 12.5
        $result = $method->invokeArgs(null, ["12.5"]);
        $this->assertEquals(12.5, $result, '"12.5" should be parsed as 12.5');
        
        // Test numeric value
        $result = $method->invokeArgs(null, [180]);
        $this->assertEquals(180.0, $result, 'Numeric 180 should become 180.0');
        
        // Test null value
        $result = $method->invokeArgs(null, [null]);
        $this->assertNull($result, 'null should return null');
    }
    
    /**
     * Test parseNumericValue method using reflection
     */
    public function test_parse_numeric_value() {
        $reflection = new ReflectionClass('KG_Core\Database\DataMigration');
        $method = $reflection->getMethod('parseNumericValue');
        $method->setAccessible(true);
        
        // Test "6 ay" -> 6
        $result = $method->invokeArgs(null, ["6 ay"]);
        $this->assertEquals(6, $result, '"6 ay" should be parsed as 6');
        
        // Test "6+ ay" -> 6
        $result = $method->invokeArgs(null, ["6+ ay"]);
        $this->assertEquals(6, $result, '"6+ ay" should be parsed as 6');
        
        // Test "6 aydan itibaren" -> 6
        $result = $method->invokeArgs(null, ["6 aydan itibaren"]);
        $this->assertEquals(6, $result, '"6 aydan itibaren" should be parsed as 6');
        
        // Test numeric value
        $result = $method->invokeArgs(null, [12]);
        $this->assertEquals(12, $result, 'Numeric 12 should stay as 12');
        
        // Test null value
        $result = $method->invokeArgs(null, [null]);
        $this->assertNull($result, 'null should return null');
    }
    
    /**
     * Test convertType method with string values
     */
    public function test_convert_type_with_strings() {
        $reflection = new ReflectionClass('KG_Core\Database\DataMigration');
        $method = $reflection->getMethod('convertType');
        $method->setAccessible(true);
        
        // Test prep_time with string
        $result = $method->invokeArgs(null, ['prep_time', '30 dakika', 'recipe']);
        $this->assertEquals(30, $result, 'prep_time "30 dakika" should be parsed as 30');
        
        // Test cook_time with string
        $result = $method->invokeArgs(null, ['cook_time', '1 saat', 'recipe']);
        $this->assertEquals(60, $result, 'cook_time "1 saat" should be parsed as 60');
        
        // Test calories with string
        $result = $method->invokeArgs(null, ['calories', '180 kcal', 'recipe']);
        $this->assertEquals(180.0, $result, 'calories "180 kcal" should be parsed as 180.0');
        
        // Test protein with string
        $result = $method->invokeArgs(null, ['protein', '6 g', 'recipe']);
        $this->assertEquals(6.0, $result, 'protein "6 g" should be parsed as 6.0');
        
        // Test start_age with string
        $result = $method->invokeArgs(null, ['start_age', '6 ay', 'ingredient']);
        $this->assertEquals(6, $result, 'start_age "6 ay" should be parsed as 6');
        
        // Test calories_100g with string
        $result = $method->invokeArgs(null, ['calories_100g', '100 kcal', 'ingredient']);
        $this->assertEquals(100.0, $result, 'calories_100g "100 kcal" should be parsed as 100.0');
    }
    
    /**
     * Test migrateSinglePost method exists and is public
     */
    public function test_migrate_single_post_is_public() {
        $this->assertTrue(
            method_exists('KG_Core\Database\DataMigration', 'migrateSinglePost'),
            'migrateSinglePost method should exist'
        );
        
        $reflection = new ReflectionClass('KG_Core\Database\DataMigration');
        $method = $reflection->getMethod('migrateSinglePost');
        
        $this->assertTrue(
            $method->isPublic(),
            'migrateSinglePost method should be public'
        );
    }
    
    /**
     * Test forceMigrate method exists and is public
     */
    public function test_force_migrate_is_public() {
        $this->assertTrue(
            method_exists('KG_Core\Database\DataMigration', 'forceMigrate'),
            'forceMigrate method should exist'
        );
        
        $reflection = new ReflectionClass('KG_Core\Database\DataMigration');
        $method = $reflection->getMethod('forceMigrate');
        
        $this->assertTrue(
            $method->isPublic(),
            'forceMigrate method should be public'
        );
    }
    
    /**
     * Test forceMigrateMultiple method exists and is public
     */
    public function test_force_migrate_multiple_is_public() {
        $this->assertTrue(
            method_exists('KG_Core\Database\DataMigration', 'forceMigrateMultiple'),
            'forceMigrateMultiple method should exist'
        );
        
        $reflection = new ReflectionClass('KG_Core\Database\DataMigration');
        $method = $reflection->getMethod('forceMigrateMultiple');
        
        $this->assertTrue(
            $method->isPublic(),
            'forceMigrateMultiple method should be public'
        );
    }
}
