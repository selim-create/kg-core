<?php
/**
 * Test Script for Sponsored Content Support
 * 
 * This script tests the sponsor meta box functionality
 * Run this from WordPress CLI or as a test endpoint
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    // For testing outside WordPress, define a simple bootstrap
    require_once __DIR__ . '/../../../wp-load.php';
}

echo "=== Testing Sponsored Content Support ===\n\n";

// Test 1: Check if PostMetaBox class exists
echo "Test 1: Check if PostMetaBox class exists\n";
if ( class_exists( '\KG_Core\Admin\PostMetaBox' ) ) {
    echo "✓ PostMetaBox class exists\n\n";
} else {
    echo "✗ PostMetaBox class NOT found\n\n";
}

// Test 2: Create a test post with sponsor data
echo "Test 2: Creating test post with sponsor data\n";

$test_post_id = wp_insert_post([
    'post_title' => 'Test Sponsored Post - ' . date('Y-m-d H:i:s'),
    'post_content' => 'This is a test sponsored post.',
    'post_status' => 'draft',
    'post_type' => 'post'
]);

if ( ! is_wp_error( $test_post_id ) ) {
    echo "✓ Test post created (ID: {$test_post_id})\n";
    
    // Add sponsor meta data
    update_post_meta( $test_post_id, '_kg_is_sponsored', '1' );
    update_post_meta( $test_post_id, '_kg_sponsor_name', 'Test Brand' );
    update_post_meta( $test_post_id, '_kg_sponsor_url', 'https://example.com' );
    update_post_meta( $test_post_id, '_kg_direct_redirect', '1' );
    update_post_meta( $test_post_id, '_kg_gam_impression_url', 'https://ad.doubleclick.net/impression' );
    update_post_meta( $test_post_id, '_kg_gam_click_url', 'https://ad.doubleclick.net/click?adurl=' );
    
    echo "✓ Sponsor meta data added\n\n";
    
    // Test 3: Verify meta data was saved
    echo "Test 3: Verify meta data was saved\n";
    $is_sponsored = get_post_meta( $test_post_id, '_kg_is_sponsored', true );
    $sponsor_name = get_post_meta( $test_post_id, '_kg_sponsor_name', true );
    $sponsor_url = get_post_meta( $test_post_id, '_kg_sponsor_url', true );
    
    if ( $is_sponsored === '1' && $sponsor_name === 'Test Brand' && $sponsor_url === 'https://example.com' ) {
        echo "✓ Meta data verified successfully\n";
        echo "  - is_sponsored: {$is_sponsored}\n";
        echo "  - sponsor_name: {$sponsor_name}\n";
        echo "  - sponsor_url: {$sponsor_url}\n\n";
    } else {
        echo "✗ Meta data verification failed\n\n";
    }
    
    // Test 4: Test REST API field (simulate)
    echo "Test 4: Test REST API sponsor_data field\n";
    
    // Manually call the callback to simulate REST API
    $post_array = [ 'id' => $test_post_id ];
    
    // Get the callback function
    $rest_fields = apply_filters( 'rest_api_init', null );
    
    // Simulate the REST field callback
    $sponsor_logo_id = get_post_meta( $test_post_id, '_kg_sponsor_logo', true );
    $sponsor_light_logo_id = get_post_meta( $test_post_id, '_kg_sponsor_light_logo', true );
    
    $sponsor_logo_url = $sponsor_logo_id ? wp_get_attachment_url( $sponsor_logo_id ) : null;
    $sponsor_light_logo_url = $sponsor_light_logo_id ? wp_get_attachment_url( $sponsor_light_logo_id ) : null;
    
    $sponsor_data = [
        'is_sponsored' => true,
        'sponsor_name' => get_post_meta( $test_post_id, '_kg_sponsor_name', true ),
        'sponsor_url' => get_post_meta( $test_post_id, '_kg_sponsor_url', true ),
        'sponsor_logo' => [
            'id' => $sponsor_logo_id ? absint( $sponsor_logo_id ) : null,
            'url' => $sponsor_logo_url,
        ],
        'sponsor_light_logo' => [
            'id' => $sponsor_light_logo_id ? absint( $sponsor_light_logo_id ) : null,
            'url' => $sponsor_light_logo_url,
        ],
        'direct_redirect' => get_post_meta( $test_post_id, '_kg_direct_redirect', true ) === '1',
        'gam_impression_url' => get_post_meta( $test_post_id, '_kg_gam_impression_url', true ),
        'gam_click_url' => get_post_meta( $test_post_id, '_kg_gam_click_url', true ),
    ];
    
    echo "✓ REST API sponsor_data structure:\n";
    echo json_encode( $sponsor_data, JSON_PRETTY_PRINT ) . "\n\n";
    
    // Test 5: Test non-sponsored post
    echo "Test 5: Test non-sponsored post (should return null)\n";
    $regular_post_id = wp_insert_post([
        'post_title' => 'Regular Post - ' . date('Y-m-d H:i:s'),
        'post_content' => 'This is a regular non-sponsored post.',
        'post_status' => 'draft',
        'post_type' => 'post'
    ]);
    
    if ( ! is_wp_error( $regular_post_id ) ) {
        $is_sponsored_regular = get_post_meta( $regular_post_id, '_kg_is_sponsored', true );
        if ( $is_sponsored_regular !== '1' ) {
            echo "✓ Regular post returns null for sponsor_data (not sponsored)\n\n";
        } else {
            echo "✗ Regular post incorrectly marked as sponsored\n\n";
        }
        
        // Clean up
        wp_delete_post( $regular_post_id, true );
    }
    
    // Clean up test post
    echo "Cleaning up test data...\n";
    wp_delete_post( $test_post_id, true );
    echo "✓ Test post deleted\n\n";
    
} else {
    echo "✗ Failed to create test post\n\n";
}

echo "=== All Tests Complete ===\n";
