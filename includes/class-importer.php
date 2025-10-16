<?php
/**
 * Import Service - Optimized with Pagination
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Importer {
    
    /**
     * Import all listings with pagination (50 per batch)
     */
    public static function import_all_listings() {
        $start_time = microtime(true);
        
        AS24_Logger::info('=== Starting full import ===', 'import');
        
        // Get total count
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            AS24_Logger::error('Failed to get total count: ' . $data->get_error_message(), 'import');
            return $data;
        }
        
        $total = $data['data']['search']['listings']['metadata']['totalItems'];
        $per_page = 10; // Reduced batch size
        $total_pages = ceil($total / $per_page);
        
        AS24_Logger::info(sprintf('Total listings to import: %d (in %d pages)', $total, $total_pages), 'import');
        
        // Store import state
        $import_state = array(
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => 1,
            'per_page' => $per_page,
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'start_time' => $start_time,
            'status' => 'running'
        );
        update_option('as24_import_state', $import_state);
        
        // Schedule background processing
        if (!wp_next_scheduled('as24_process_import_batch')) {
            wp_schedule_single_event(time(), 'as24_process_import_batch');
        }
        
        return array(
            'status' => 'started',
            'total_items' => $total,
            'total_pages' => $total_pages,
            'message' => 'Import started in background'
        );
    }
    
    /**
     * Process import batch in background
     */
    public static function process_import_batch() {
        $import_state = get_option('as24_import_state');
        
        if (!$import_state || $import_state['status'] !== 'running') {
            return;
        }
        
        $page = $import_state['current_page'];
        $per_page = $import_state['per_page'];
        
        // Process current batch
        $batch_result = self::import_batch($page, $per_page);
        
        if (is_wp_error($batch_result)) {
            $import_state['errors']++;
            AS24_Logger::error(sprintf('Page %d failed: %s', $page, $batch_result->get_error_message()), 'import');
        } else {
            $import_state['imported'] += $batch_result['imported'];
            $import_state['updated'] += $batch_result['updated'];
            $import_state['skipped'] += $batch_result['skipped'];
            $import_state['errors'] += $batch_result['errors'];
        }
        
        // Update progress
        $import_state['current_page']++;
        $progress = round(($page / $import_state['total_pages']) * 100, 1);
        
        AS24_Logger::info(sprintf('Progress: %s%% (Page %d/%d)', $progress, $page, $import_state['total_pages']), 'import');
        
        if ($import_state['current_page'] <= $import_state['total_pages']) {
            // Schedule next batch
            wp_schedule_single_event(time() + 1, 'as24_process_import_batch');
            $import_state['status'] = 'running';
        } else {
            // Import complete
            $duration = round(microtime(true) - $import_state['start_time'], 2);
            $import_state['duration'] = $duration;
            $import_state['status'] = 'completed';
            
            AS24_Logger::info(sprintf('=== Import complete: %d imported, %d updated, %d skipped, %d errors in %ss ===', 
                $import_state['imported'], $import_state['updated'], $import_state['skipped'], $import_state['errors'], $duration), 'import');
            
            // Update last import time
            as24_motors_sync()->update_setting('last_import', time());
        }
        
        update_option('as24_import_state', $import_state);
        
        $duration = round(microtime(true) - $start_time, 2);
        $results['duration'] = $duration;
        
        AS24_Logger::info(sprintf('=== Import complete: %d imported, %d updated, %d skipped, %d errors in %ss ===', 
            $results['imported'], $results['updated'], $results['skipped'], $results['errors'], $duration), 'import', $results);
        
        // Update last import time
        as24_motors_sync()->update_setting('last_import', time());
        
        return $results;
    }
    
    /**
     * Import a single batch (page) of listings
     */
    public static function import_batch($page = 1, $size = 50) {
        $query = AS24_Queries::get_essential_listings_query($page, $size);
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        if (!isset($data['data']['search']['listings']['listings'])) {
            return new WP_Error('no_listings', 'No listings found in response');
        }
        
        $listings = $data['data']['search']['listings']['listings'];
        
        $results = array(
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        );
        
        foreach ($listings as $listing) {
            $result = self::import_single_listing($listing);
            
            if (is_wp_error($result)) {
                $results['errors']++;
            } elseif ($result['action'] === 'imported') {
                $results['imported']++;
            } elseif ($result['action'] === 'updated') {
                $results['updated']++;
            } else {
                $results['skipped']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Import a single listing
     */
    public static function import_single_listing($listing) {
        $as24_id = $listing['id'];
        $timestamp = $listing['details']['publication']['changedTimestamp'] ?? null;
        
        // Check for duplicates
        $existing_id = AS24_Duplicate_Handler::listing_exists($as24_id);
        
        if ($existing_id) {
            // Check if update needed
            $current_timestamp = get_post_meta($existing_id, 'as24-updated-at', true);
            
            if ($timestamp && $current_timestamp === $timestamp) {
                // No changes, skip
                return array('action' => 'skipped', 'post_id' => $existing_id);
            }
            
            // Update existing listing
            $post_id = self::update_listing($existing_id, $listing);
            
            if (is_wp_error($post_id)) {
                AS24_Logger::error(sprintf('Failed to update listing %s: %s', $as24_id, $post_id->get_error_message()), 'import');
                return $post_id;
            }
            
            AS24_Logger::debug(sprintf('Updated listing %d (AS24 ID: %s)', $post_id, $as24_id), 'import');
            return array('action' => 'updated', 'post_id' => $post_id);
        }
        
        // Create new listing
        $post_id = self::create_listing($listing);
        
        if (is_wp_error($post_id)) {
            AS24_Logger::error(sprintf('Failed to create listing %s: %s', $as24_id, $post_id->get_error_message()), 'import');
            return $post_id;
        }
        
        AS24_Logger::debug(sprintf('Created listing %d (AS24 ID: %s)', $post_id, $as24_id), 'import');
        return array('action' => 'imported', 'post_id' => $post_id);
    }
    
    /**
     * Create new listing
     */
    private static function create_listing($listing) {
        AS24_Logger::debug('Creating new listing...', 'import');
        $post_data = AS24_Field_Mapper::map_to_post_data($listing);
        AS24_Logger::debug('Post data: ' . print_r($post_data, true), 'import');
        
        $post_id = wp_insert_post($post_data);
        AS24_Logger::debug('wp_insert_post result: ' . print_r($post_id, true), 'import');
        
        if (is_wp_error($post_id)) {
            AS24_Logger::error('Failed to insert post: ' . $post_id->get_error_message(), 'import');
            return $post_id;
        }
        
        // Add metadata
        AS24_Logger::debug('Adding metadata...', 'import');
        self::add_listing_meta($post_id, $listing);
        
        // Import images
        if (!empty($listing['details']['media']['images'])) {
            AS24_Logger::debug('Importing images...', 'import');
            AS24_Image_Handler::import_images($post_id, $listing['details']['media']['images']);
        }
        
        return $post_id;
    }
    
    /**
     * Update existing listing
     */
    private static function update_listing($post_id, $listing) {
        AS24_Logger::debug('Updating listing ' . $post_id . '...', 'import');
        $post_data = AS24_Field_Mapper::map_to_post_data($listing);
        $post_data['ID'] = $post_id;
        AS24_Logger::debug('Post data: ' . print_r($post_data, true), 'import');
        
        $result = wp_update_post($post_data);
        AS24_Logger::debug('wp_update_post result: ' . print_r($result, true), 'import');
        
        if (is_wp_error($result)) {
            AS24_Logger::error('Failed to update post: ' . $result->get_error_message(), 'import');
            return $result;
        }
        
        // Update metadata
        AS24_Logger::debug('Updating metadata...', 'import');
        self::add_listing_meta($post_id, $listing);
        
        // Update images
        if (!empty($listing['details']['media']['images'])) {
            AS24_Logger::debug('Updating images...', 'import');
            AS24_Image_Handler::import_images($post_id, $listing['details']['media']['images']);
        }
        
        return $post_id;
    }
    
    /**
     * Add listing metadata
     */
    private static function add_listing_meta($post_id, $listing) {
        AS24_Logger::debug('Mapping metadata...', 'import');
        $meta_data = AS24_Field_Mapper::map_to_meta_data($listing);
        AS24_Logger::debug('Meta data: ' . print_r($meta_data, true), 'import');
        
        foreach ($meta_data as $meta_key => $meta_value) {
            AS24_Logger::debug('Setting meta ' . $meta_key . '...', 'import');
            $result = update_post_meta($post_id, $meta_key, $meta_value);
            AS24_Logger::debug('Meta result: ' . ($result ? 'success' : 'failed'), 'import');
        }
        
        // Add taxonomies
        AS24_Logger::debug('Adding taxonomies...', 'import');
        AS24_Field_Mapper::add_taxonomies($post_id, $listing);
    }
}

