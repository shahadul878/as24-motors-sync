<?php
/**
 * Orphan Detection and Auto-Delete Handler
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Orphan_Detector {
    
    private static $trash_retention_days = 30;
    
    public static function detect_orphans($remote_ids = null) {
        if ($remote_ids === null) {
            $remote_ids = self::fetch_remote_listing_ids();
            if (is_wp_error($remote_ids)) {
                AS24_Logger::error('Failed to fetch remote IDs: ' . $remote_ids->get_error_message(), 'orphan');
                return array('error' => $remote_ids->get_error_message(), 'orphans' => array());
            }
        }
        
        $local_listings = self::get_local_listings();
        
        $orphans = array();
        foreach ($local_listings as $local) {
            if (!in_array($local['as24_id'], $remote_ids)) {
                $orphans[] = $local;
            }
        }
        
        AS24_Logger::info(sprintf('Detected %d orphaned listings', count($orphans)), 'orphan', array(
            'local_count' => count($local_listings),
            'remote_count' => count($remote_ids),
            'orphan_count' => count($orphans)
        ));
        
        return array(
            'local_count' => count($local_listings),
            'remote_count' => count($remote_ids),
            'orphan_count' => count($orphans),
            'orphans' => $orphans
        );
    }
    
    public static function trash_orphans($orphan_ids = null, $dry_run = false) {
        if ($orphan_ids === null) {
            $detection = self::detect_orphans();
            if (isset($detection['error'])) {
                return $detection;
            }
            $orphans = $detection['orphans'];
            $orphan_ids = array_column($orphans, 'post_id');
        }
        
        $results = array(
            'trashed' => 0,
            'errors' => 0,
            'dry_run' => $dry_run,
            'trashed_listings' => array()
        );
        
        if (empty($orphan_ids)) {
            AS24_Logger::info('No orphaned listings to trash', 'orphan');
            return $results;
        }
        
        foreach ($orphan_ids as $post_id) {
            $as24_id = get_post_meta($post_id, 'autoscout24-id', true);
            $post_title = get_the_title($post_id);
            
            if ($dry_run) {
                $results['trashed']++;
                $results['trashed_listings'][] = array('post_id' => $post_id, 'title' => $post_title, 'as24_id' => $as24_id);
            } else {
                $trashed = wp_trash_post($post_id);
                
                if ($trashed) {
                    $results['trashed']++;
                    $results['trashed_listings'][] = array('post_id' => $post_id, 'title' => $post_title, 'as24_id' => $as24_id);
                    
                    add_post_meta($post_id, '_as24_orphan_reason', 'No longer exists in AutoScout24', true);
                    add_post_meta($post_id, '_as24_orphan_date', current_time('mysql'), true);
                    
                    AS24_Logger::info(sprintf('Trashed orphaned listing: %d - "%s" (AS24 ID: %s)', $post_id, $post_title, $as24_id), 'orphan');
                } else {
                    $results['errors']++;
                    AS24_Logger::error(sprintf('Failed to trash orphaned listing: %d', $post_id), 'orphan');
                }
            }
        }
        
        AS24_Logger::info(sprintf('Orphan trash operation: %d trashed, %d errors', $results['trashed'], $results['errors']), 'orphan');
        
        return $results;
    }
    
    public static function cleanup_old_trash($dry_run = false) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . self::$trash_retention_days . ' days'));
        
        $trashed_posts = get_posts(array(
            'post_type' => 'listings',
            'post_status' => 'trash',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'column' => 'post_modified',
                    'before' => $cutoff_date,
                    'inclusive' => true
                )
            )
        ));
        
        $results = array(
            'deleted' => 0,
            'errors' => 0,
            'dry_run' => $dry_run,
            'deleted_listings' => array()
        );
        
        if (empty($trashed_posts)) {
            AS24_Logger::info('No old trashed listings to permanently delete', 'orphan');
            return $results;
        }
        
        foreach ($trashed_posts as $post) {
            $as24_id = get_post_meta($post->ID, 'autoscout24-id', true);
            
            if ($dry_run) {
                $results['deleted']++;
            } else {
                $deleted = wp_delete_post($post->ID, true);
                
                if ($deleted) {
                    $results['deleted']++;
                    $results['deleted_listings'][] = array('post_id' => $post->ID, 'title' => $post->post_title, 'as24_id' => $as24_id);
                    AS24_Logger::info(sprintf('Permanently deleted: %d - "%s"', $post->ID, $post->post_title), 'orphan');
                } else {
                    $results['errors']++;
                    AS24_Logger::error(sprintf('Failed to delete: %d', $post->ID), 'orphan');
                }
            }
        }
        
        AS24_Logger::info(sprintf('Trash cleanup: %d deleted, %d errors', $results['deleted'], $results['errors']), 'orphan');
        
        return $results;
    }
    
    private static function get_local_listings() {
        global $wpdb;
        
        $query = "
            SELECT p.ID as post_id, p.post_title, pm.meta_value as as24_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'listings'
            AND p.post_status != 'trash'
            AND pm.meta_key = 'autoscout24-id'
            AND pm.meta_value != ''
        ";
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    private static function fetch_remote_listing_ids() {
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        $total = $data['data']['search']['listings']['metadata']['totalItems'];
        
        $all_ids = array();
        $page = 1;
        $per_page = 50;
        $total_pages = ceil($total / $per_page);
        
        while ($page <= $total_pages) {
            $query = AS24_Queries::get_ids_only_query($page, $per_page);
            $data = AS24_Queries::make_request($query);
            
            if (is_wp_error($data)) {
                return $data;
            }
            
            $listings = $data['data']['search']['listings']['listings'];
            foreach ($listings as $listing) {
                $all_ids[] = $listing['id'];
            }
            
            $page++;
        }
        
        AS24_Logger::debug(sprintf('Fetched %d remote listing IDs', count($all_ids)), 'orphan');
        
        return $all_ids;
    }
    
    public static function get_orphan_stats() {
        $detection = self::detect_orphans();
        
        $trashed = get_posts(array(
            'post_type' => 'listings',
            'post_status' => 'trash',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . self::$trash_retention_days . ' days'));
        $old_trashed = get_posts(array(
            'post_type' => 'listings',
            'post_status' => 'trash',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'date_query' => array(
                array(
                    'column' => 'post_modified',
                    'before' => $cutoff_date,
                    'inclusive' => true
                )
            )
        ));
        
        return array(
            'orphans_detected' => $detection['orphan_count'] ?? 0,
            'in_trash' => count($trashed),
            'ready_for_deletion' => count($old_trashed),
            'trash_retention_days' => self::$trash_retention_days
        );
    }
}

