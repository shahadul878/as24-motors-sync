<?php
/**
 * Duplicate Detection and Prevention
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Duplicate_Handler {
    
    public static function listing_exists($as24_id) {
        $existing = get_posts(array(
            'post_type' => 'listings',
            'meta_query' => array(
                array(
                    'key' => 'autoscout24-id',
                    'value' => $as24_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids'
        ));
        
        return !empty($existing) ? $existing[0] : false;
    }
    
    public static function find_duplicates() {
        global $wpdb;
        
        $query = "
            SELECT 
                pm.meta_value as autoscout24_id,
                COUNT(*) as count,
                GROUP_CONCAT(p.ID ORDER BY p.ID ASC) as post_ids,
                GROUP_CONCAT(p.post_title ORDER BY p.ID ASC SEPARATOR '|||') as post_titles
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'listings'
            AND p.post_status != 'trash'
            AND pm.meta_key = 'autoscout24-id'
            AND pm.meta_value != ''
            GROUP BY pm.meta_value
            HAVING COUNT(*) > 1
            ORDER BY COUNT(*) DESC
        ";
        
        $results = $wpdb->get_results($query);
        
        $duplicates = array();
        foreach ($results as $row) {
            $post_ids = explode(',', $row->post_ids);
            $post_titles = explode('|||', $row->post_titles);
            
            $duplicates[] = array(
                'autoscout24_id' => $row->autoscout24_id,
                'count' => (int)$row->count,
                'post_ids' => array_map('intval', $post_ids),
                'post_titles' => $post_titles,
                'keep_id' => (int)$post_ids[0],
                'remove_ids' => array_map('intval', array_slice($post_ids, 1))
            );
        }
        
        AS24_Logger::info(sprintf('Found %d duplicate groups', count($duplicates)), 'duplicate');
        
        return $duplicates;
    }
    
    public static function remove_duplicates($dry_run = false) {
        $duplicates = self::find_duplicates();
        
        $results = array(
            'total_groups' => count($duplicates),
            'total_removed' => 0,
            'errors' => 0,
            'dry_run' => $dry_run,
            'removed_listings' => array()
        );
        
        if (empty($duplicates)) {
            AS24_Logger::info('No duplicates found to remove', 'duplicate');
            return $results;
        }
        
        foreach ($duplicates as $duplicate) {
            $keep_id = $duplicate['keep_id'];
            $remove_ids = $duplicate['remove_ids'];
            
            foreach ($remove_ids as $duplicate_id) {
                if ($dry_run) {
                    $results['total_removed']++;
                    $results['removed_listings'][] = $duplicate_id;
                } else {
                    // Merge images
                    self::merge_listing_data($keep_id, $duplicate_id);
                    
                    // Delete duplicate
                    $deleted = wp_delete_post($duplicate_id, true);
                    
                    if ($deleted) {
                        $results['total_removed']++;
                        $results['removed_listings'][] = $duplicate_id;
                        AS24_Logger::info(sprintf('Removed duplicate listing %d (AS24 ID: %s)', $duplicate_id, $duplicate['autoscout24_id']), 'duplicate');
                    } else {
                        $results['errors']++;
                        AS24_Logger::error(sprintf('Failed to remove duplicate %d', $duplicate_id), 'duplicate');
                    }
                }
            }
        }
        
        AS24_Logger::info(sprintf('Duplicate cleanup: %d removed, %d errors', $results['total_removed'], $results['errors']), 'duplicate');
        
        return $results;
    }
    
    private static function merge_listing_data($keep_id, $duplicate_id) {
        $keep_images = get_attached_media('image', $keep_id);
        $duplicate_images = get_attached_media('image', $duplicate_id);
        
        foreach ($duplicate_images as $image) {
            $image_exists = false;
            foreach ($keep_images as $keep_image) {
                if ($keep_image->guid === $image->guid) {
                    $image_exists = true;
                    break;
                }
            }
            
            if (!$image_exists) {
                wp_update_post(array(
                    'ID' => $image->ID,
                    'post_parent' => $keep_id
                ));
            }
        }
    }
    
    public static function add_unique_index() {
        global $wpdb;
        
        $index_exists = $wpdb->get_var("
            SELECT COUNT(1) 
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE table_schema = DATABASE()
            AND table_name = '{$wpdb->postmeta}'
            AND index_name = 'idx_as24_id_lookup'
        ");
        
        if ($index_exists) {
            AS24_Logger::info('Database index already exists', 'general');
            return true;
        }
        
        $result = $wpdb->query("
            CREATE INDEX idx_as24_id_lookup 
            ON {$wpdb->postmeta} (meta_key(191), meta_value(191))
        ");
        
        if ($result !== false) {
            AS24_Logger::info('Successfully created database index', 'general');
            return true;
        }
        
        AS24_Logger::error('Failed to create index: ' . $wpdb->last_error, 'general');
        return false;
    }
    
    public static function get_duplicate_stats() {
        $duplicates = self::find_duplicates();
        
        $total_duplicates = 0;
        foreach ($duplicates as $duplicate) {
            $total_duplicates += ($duplicate['count'] - 1);
        }
        
        return array(
            'duplicate_groups' => count($duplicates),
            'total_duplicate_listings' => $total_duplicates,
            'duplicates' => $duplicates
        );
    }
}

