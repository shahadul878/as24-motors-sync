<?php
/**
 * Cleanup Handler
 * Handles removal of non-AutoScout24 listings
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Cleanup {
    
    /**
     * Remove all listings that don't have AutoScout24 ID
     */
    public static function remove_non_as24_listings() {
        global $wpdb;
        
        AS24_Logger::info('Starting cleanup of non-AutoScout24 listings', 'cleanup');
        
        // Get all listings without AutoScout24 ID
        $query = $wpdb->prepare(
            "SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p 
            WHERE p.post_type = %s 
            AND p.post_status NOT IN ('trash', 'auto-draft')
            AND NOT EXISTS (
                SELECT 1 
                FROM {$wpdb->postmeta} pm 
                WHERE pm.post_id = p.ID 
                AND pm.meta_key = '_as24_id'
            )",
            'listings'
        );
        
        // Log the query for debugging
        AS24_Logger::info('Running query: ' . $query, 'cleanup');
        
        $non_as24_listings = $wpdb->get_col($query);
        
        // Log the results
        AS24_Logger::info(sprintf('Found %d listings without _as24_id', count($non_as24_listings)), 'cleanup');
        
        if (!empty($non_as24_listings)) {
            // Log the first few IDs for verification
            $sample_ids = array_slice($non_as24_listings, 0, 5);
            AS24_Logger::info('Sample listing IDs: ' . implode(', ', $sample_ids), 'cleanup');
            
            // Double check these IDs really don't have _as24_id
            foreach ($sample_ids as $post_id) {
                $has_as24_id = get_post_meta($post_id, '_as24_id', true);
                AS24_Logger::info(sprintf('Listing #%d _as24_id = %s', $post_id, $has_as24_id ? $has_as24_id : 'NULL'), 'cleanup');
            }
        }
        
        if (empty($non_as24_listings)) {
            AS24_Logger::info('No non-AutoScout24 listings found', 'cleanup');
            return array(
                'removed' => 0,
                'message' => 'No non-AutoScout24 listings found'
            );
        }
        
        $count = count($non_as24_listings);
        AS24_Logger::info(sprintf('Found %d non-AutoScout24 listings to remove', $count), 'cleanup');
        
        // Move them to trash
        foreach ($non_as24_listings as $post_id) {
            wp_trash_post($post_id);
            AS24_Logger::info(sprintf('Moved listing #%d to trash', $post_id), 'cleanup');
        }
        
        return array(
            'removed' => $count,
            'message' => sprintf('%d non-AutoScout24 listings moved to trash', $count)
        );
    }
    
    /**
     * Permanently delete old listings from trash
     */
    public static function empty_trash() {
        global $wpdb;
        
        AS24_Logger::info('Starting permanent deletion of trashed listings', 'cleanup');
        
        // Get all trashed listings older than 30 days
        $query = $wpdb->prepare(
            "SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = %s 
            AND post_status = 'trash'
            AND post_modified < %s",
            'listings',
            date('Y-m-d H:i:s', strtotime('-30 days'))
        );
        
        $old_trash = $wpdb->get_col($query);
        
        if (empty($old_trash)) {
            AS24_Logger::info('No old trashed listings found', 'cleanup');
            return array(
                'removed' => 0,
                'message' => 'No old trashed listings found'
            );
        }
        
        $count = count($old_trash);
        AS24_Logger::info(sprintf('Found %d old trashed listings to delete', $count), 'cleanup');
        
        // Permanently delete them
        foreach ($old_trash as $post_id) {
            wp_delete_post($post_id, true);
            AS24_Logger::info(sprintf('Permanently deleted listing #%d', $post_id), 'cleanup');
        }
        
        return array(
            'removed' => $count,
            'message' => sprintf('%d old trashed listings permanently deleted', $count)
        );
    }
}
