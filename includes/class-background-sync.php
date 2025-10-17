<?php
/**
 * Background Sync Process
 * Handles importing listings in the background
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Background_Sync {
    
    /**
     * Stop background sync process
     */
    public static function stop_sync() {
        $status = get_option('as24_sync_status');
        
        if (!$status || $status['status'] !== 'running') {
            return array(
                'success' => false,
                'message' => 'No active sync process to stop'
            );
        }
        
        // Clear scheduled event
        wp_clear_scheduled_hook('as24_process_sync_batch');
        
        // Update status
        $status['status'] = 'stopped';
        $status['duration'] = time() - $status['start_time'];
        update_option('as24_sync_status', $status);
        
        AS24_Logger::info(sprintf('Sync stopped manually at %d of %d listings', 
            $status['current'], $status['total']), 'sync');
        
        return array(
            'success' => true,
            'message' => 'Sync process stopped'
        );
    }
    
    /**
     * Start background sync process
     */
    public static function start_sync() {
        // Get total count first
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            AS24_Logger::error('Failed to get total count: ' . $data->get_error_message(), 'sync');
            return $data;
        }
        
        $total = $data['data']['search']['listings']['metadata']['totalItems'];
        AS24_Logger::info(sprintf('Starting background sync for %d listings', $total), 'sync');
        
        // Store sync info
        update_option('as24_sync_status', array(
            'total' => $total,
            'current' => 0,
            'added' => 0,
            'updated' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'start_time' => time(),
            'last_update' => time(),
            'status' => 'running'
        ));
        
        // Schedule first batch
        wp_schedule_single_event(time(), 'as24_process_sync_batch');
        
        return array(
            'success' => true,
            'message' => sprintf('Background sync started for %d listings', $total)
        );
    }
    
    /**
     * Process a batch of listings
     */
    public static function process_batch() {
        $status = get_option('as24_sync_status');
        
        if (!$status || $status['status'] !== 'running') {
            AS24_Logger::info('No active sync process', 'sync');
            return;
        }
        
        // Process 5 listings per batch
        $batch_size = 5;
        $start = $status['current'] + 1;
        $end = min($start + $batch_size - 1, $status['total']);
        
        AS24_Logger::info(sprintf('Processing batch %d to %d of %d', $start, $end, $status['total']), 'sync');
        
        for ($page = $start; $page <= $end; $page++) {
            // Get single listing
            $query = AS24_Queries::get_essential_listings_query($page, 1);
            $data = AS24_Queries::make_request($query);
            
            if (is_wp_error($data)) {
                AS24_Logger::error('Failed to fetch listing: ' . $data->get_error_message(), 'sync');
                $status['errors']++;
                continue;
            }
            
            if (empty($data['data']['search']['listings']['listings'])) {
                AS24_Logger::error('No listing data returned', 'sync');
                $status['errors']++;
                continue;
            }
            
            $listing = $data['data']['search']['listings']['listings'][0];
            $as24_id = $listing['id'];
            
            // Check if exists
            $existing_id = AS24_Duplicate_Handler::listing_exists($as24_id);
            
            if ($existing_id) {
                // Update existing
                $result = AS24_Importer::import_single_listing($listing);
                if (!is_wp_error($result)) {
                    if ($result['action'] === 'updated') {
                        $status['updated']++;
                        AS24_Logger::info(sprintf('Updated listing %s', $as24_id), 'sync');
                    } else {
                        $status['unchanged']++;
                        AS24_Logger::info(sprintf('Listing %s unchanged', $as24_id), 'sync');
                    }
                } else {
                    $status['errors']++;
                    AS24_Logger::error('Update failed: ' . $result->get_error_message(), 'sync');
                }
            } else {
                // Create new
                $result = AS24_Importer::import_single_listing($listing);
                if (!is_wp_error($result) && $result['action'] === 'imported') {
                    $status['added']++;
                    AS24_Logger::info(sprintf('Imported new listing %s', $as24_id), 'sync');
                } else {
                    $status['errors']++;
                    AS24_Logger::error('Import failed: ' . $result->get_error_message(), 'sync');
                }
            }
            
            $status['current'] = $page;
            $status['last_update'] = time();
            update_option('as24_sync_status', $status);
        }
        
        // Schedule next batch if not done
        if ($status['current'] < $status['total']) {
            wp_schedule_single_event(time(), 'as24_process_sync_batch');
        } else {
            // All done!
            $duration = time() - $status['start_time'];
            AS24_Logger::info(sprintf('Background sync complete: %d added, %d updated, %d errors in %d seconds', 
                $status['added'], $status['updated'], $status['errors'], $duration), 'sync');
            
            $status['status'] = 'complete';
            $status['duration'] = $duration;
            update_option('as24_sync_status', $status);
            
            // Store final result
            update_option('as24_last_sync_result', array(
                'timestamp' => current_time('mysql'),
                'results' => array(
                    'added' => $status['added'],
                    'updated' => $status['updated'],
                    'removed' => $status['removed'],
                    'unchanged' => $status['unchanged'],
                    'errors' => $status['errors'],
                    'duration' => $duration
                ),
                'status' => 'success'
            ));
            
            // Update last sync time
            as24_motors_sync()->update_setting('last_sync', time());
        }
    }
    
    /**
     * Get current sync status
     */
    public static function get_status() {
        $status = get_option('as24_sync_status');
        
        if (!$status) {
            return array(
                'is_running' => false,
                'message' => 'No sync in progress'
            );
        }
        
        if ($status['status'] === 'complete') {
            return array(
                'is_running' => false,
                'message' => sprintf('Sync complete: %d added, %d updated, %d errors', 
                    $status['added'], $status['updated'], $status['errors']),
                'progress' => 100
            );
        }
        
        $progress = round(($status['current'] / $status['total']) * 100);
        $elapsed = time() - $status['start_time'];
        $rate = $status['current'] / max(1, $elapsed);
        $remaining = round(($status['total'] - $status['current']) / max(0.1, $rate));
        
        return array(
            'is_running' => true,
            'message' => sprintf('Processing %d of %d listings (%d%%, %d minutes remaining)', 
                $status['current'], $status['total'], $progress, ceil($remaining / 60)),
            'progress' => $progress,
            'stats' => array(
                'added' => $status['added'],
                'updated' => $status['updated'],
                'errors' => $status['errors'],
                'elapsed' => $elapsed,
                'remaining' => $remaining
            )
        );
    }
}
