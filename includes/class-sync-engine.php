<?php
/**
 * Sync Engine - Smart Synchronization with Orphan Detection
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Sync_Engine {
    
    /**
     * Smart sync - fetch IDs first, then only changed listings
     */
    public static function smart_sync() {
        $start_time = microtime(true);
        
        AS24_Logger::info('=== Starting smart sync ===', 'sync');
        
        $results = array(
            'added' => 0,
            'updated' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'duration' => 0
        );
        
        // Step 1: Get total count
        AS24_Logger::info('[Step 1/3] Getting total count...', 'sync');
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            AS24_Logger::error('Failed to get total count: ' . $data->get_error_message(), 'sync');
            return $data;
        }
        
        $total = $data['data']['search']['listings']['metadata']['totalItems'];
        AS24_Logger::info(sprintf('[Step 1/3] Found %d total listings', $total), 'sync');
        
        // Step 2: Get local listings
        AS24_Logger::info('[Step 2/3] Getting local listings...', 'sync');
        $local_listings = self::get_local_listings_with_timestamps();
        AS24_Logger::info(sprintf('[Step 2/3] Found %d local listings', count($local_listings)), 'sync');
        
        // Step 3: Process each listing one by one
        AS24_Logger::info('[Step 3/3] Processing listings one by one...', 'sync');
        
        $page = 1;
        $per_page = 1; // One at a time
        $total_pages = ceil($total / $per_page);
        
        while ($page <= $total_pages) {
            AS24_Logger::info(sprintf('Processing listing %d of %d...', $page, $total), 'sync');
            
            // Get batch of listings
            $query = AS24_Queries::get_essential_listings_query($page, $per_page);
            $data = AS24_Queries::make_request($query);
            
            if (is_wp_error($data)) {
                AS24_Logger::error('Failed to fetch listings: ' . $data->get_error_message(), 'sync');
                $results['errors']++;
                $page++;
                continue;
            }
            
            if (empty($data['data']['search']['listings']['listings'])) {
                AS24_Logger::error('No listings data returned', 'sync');
                $results['errors']++;
                $page++;
                continue;
            }
            
            $listings = $data['data']['search']['listings']['listings'];
            AS24_Logger::info(sprintf('Processing batch of %d listings...', count($listings)), 'sync');
            
            foreach ($listings as $listing) {
                $as24_id = $listing['id'];
                
                // Check if exists
                $existing_id = AS24_Duplicate_Handler::listing_exists($as24_id);
                
                if ($existing_id) {
                    // Update existing
                    $result = AS24_Importer::import_single_listing($listing);
                    if (!is_wp_error($result)) {
                        if ($result['action'] === 'updated') {
                            $results['updated']++;
                            AS24_Logger::info(sprintf('Updated listing %s', $as24_id), 'sync');
                        } else {
                            $results['unchanged']++;
                            AS24_Logger::info(sprintf('Listing %s unchanged', $as24_id), 'sync');
                        }
                    } else {
                        $results['errors']++;
                        AS24_Logger::error('Update failed: ' . $result->get_error_message(), 'sync');
                    }
                } else {
                    // Create new
                    $result = AS24_Importer::import_single_listing($listing);
                    if (!is_wp_error($result) && $result['action'] === 'imported') {
                        $results['added']++;
                        AS24_Logger::info(sprintf('Imported new listing %s', $as24_id), 'sync');
                    } else {
                        $results['errors']++;
                        AS24_Logger::error('Import failed: ' . $result->get_error_message(), 'sync');
                    }
                }
            }
            
            $page++;
        }
        
        $duration = round(microtime(true) - $start_time, 2);
        $results['duration'] = $duration;
        
        AS24_Logger::info(sprintf('=== Sync complete: %d added, %d updated, %d removed, %d unchanged in %ss ===', 
            $results['added'], $results['updated'], $results['removed'], $results['unchanged'], $duration), 'sync', $results);
        
        // Update last sync time
        as24_motors_sync()->update_setting('last_sync', time());
        
        // Store sync result for dashboard
        update_option('as24_last_sync_result', array(
            'timestamp' => current_time('mysql'),
            'results' => $results,
            'status' => 'success'
        ));
        
        return $results;
    }
    
    /**
     * Fetch all remote listings (IDs and timestamps only)
     */
    private static function fetch_all_remote_listings() {
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        $total = $data['data']['search']['listings']['metadata']['totalItems'];
        $per_page = 50;
        $total_pages = ceil($total / $per_page);
        
        $all_listings = array();
        
        for ($page = 1; $page <= $total_pages; $page++) {
            $query = AS24_Queries::get_ids_only_query($page, $per_page);
            $data = AS24_Queries::make_request($query);
            
            if (is_wp_error($data)) {
                return $data;
            }
            
            $listings = $data['data']['search']['listings']['listings'];
            foreach ($listings as $listing) {
                $all_listings[$listing['id']] = array(
                    'id' => $listing['id'],
                    'timestamp' => $listing['details']['publication']['changedTimestamp'] ?? null
                );
            }
        }
        
        return $all_listings;
    }
    
    /**
     * Get local listings with timestamps
     */
    private static function get_local_listings_with_timestamps() {
        global $wpdb;
        
        $query = "
            SELECT 
                p.ID as post_id,
                MAX(CASE WHEN pm.meta_key = 'autoscout24-id' THEN pm.meta_value END) as as24_id,
                MAX(CASE WHEN pm.meta_key = 'as24-updated-at' THEN pm.meta_value END) as timestamp
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'listings'
            AND p.post_status != 'trash'
            AND pm.meta_key IN ('autoscout24-id', 'as24-updated-at')
            GROUP BY p.ID
            HAVING as24_id IS NOT NULL AND as24_id != ''
        ";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        $local_listings = array();
        foreach ($results as $row) {
            $local_listings[$row['as24_id']] = $row;
        }
        
        return $local_listings;
    }
    
    /**
     * Compare remote and local listings
     */
    private static function compare_listings($remote_listings, $local_listings) {
        $remote_ids = array_keys($remote_listings);
        $local_ids = array_keys($local_listings);
        
        $comparison = array(
            'new' => array(),
            'changed' => array(),
            'unchanged' => array(),
            'orphaned' => array()
        );
        
        // Find new and changed listings
        foreach ($remote_listings as $as24_id => $remote_data) {
            if (!in_array($as24_id, $local_ids)) {
                // New listing
                $comparison['new'][] = $as24_id;
            } else {
                // Exists locally - check if changed
                $local_data = $local_listings[$as24_id];
                
                if ($remote_data['timestamp'] && $local_data['timestamp']) {
                    if ($remote_data['timestamp'] !== $local_data['timestamp']) {
                        // Changed
                        $comparison['changed'][] = $as24_id;
                    } else {
                        // Unchanged
                        $comparison['unchanged'][] = $as24_id;
                    }
                } else {
                    // No timestamp, update to be safe
                    $comparison['changed'][] = $as24_id;
                }
            }
        }
        
        // Find orphaned listings (exist locally but not in remote)
        foreach ($local_ids as $local_id) {
            if (!in_array($local_id, $remote_ids)) {
                $comparison['orphaned'][] = $local_id;
            }
        }
        
        return $comparison;
    }
    
    /**
     * Fetch single listing by ID
     * For now, fetch from batch and find the specific listing
     * TODO: Use single listing query when GUID is available
     */
    private static function fetch_single_listing($as24_id) {
        // For efficiency, we'll fetch the first page and look for the listing
        // If not found, we'll need to search through pages
        $page = 1;
        $max_pages = 10; // Safety limit
        
        while ($page <= $max_pages) {
            $query = AS24_Queries::get_essential_listings_query($page, 50);
            $data = AS24_Queries::make_request($query);
            
            if (is_wp_error($data)) {
                AS24_Logger::error('Error fetching page ' . $page . ': ' . $data->get_error_message(), 'sync');
                return $data;
            }
            
            if (!isset($data['data']['search']['listings']['listings'])) {
                return new WP_Error('no_data', 'No listings in API response');
            }
            
            $listings = $data['data']['search']['listings']['listings'];
            
            // Search for our listing
            foreach ($listings as $listing) {
                if ($listing['id'] === $as24_id) {
                    AS24_Logger::debug('Found listing ' . $as24_id . ' on page ' . $page, 'sync');
                    return $listing;
                }
            }
            
            // Check if there are more pages
            $metadata = $data['data']['search']['listings']['metadata'];
            if ($page >= $metadata['totalPages']) {
                break;
            }
            
            $page++;
        }
        
        AS24_Logger::warning('Listing ' . $as24_id . ' not found in API', 'sync');
        return new WP_Error('not_found', 'Listing not found in API');
    }
}

