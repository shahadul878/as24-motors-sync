<?php
/**
 * Cron Manager - Scheduled Tasks
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Cron_Manager {
    
    /**
     * Initialize cron hooks
     */
    public static function init_hooks() {
        add_action('as24_motors_sync_import', array(__CLASS__, 'run_import'));
        add_action('as24_motors_sync_sync', array(__CLASS__, 'run_sync'));
        add_action('as24_motors_sync_cleanup', array(__CLASS__, 'run_cleanup'));
    }
    
    /**
     * Schedule all cron jobs
     */
    public static function schedule_all_jobs() {
        self::schedule_import();
        self::schedule_sync();
        self::schedule_cleanup();
    }
    
    /**
     * Clear all cron jobs
     */
    public static function clear_all_jobs() {
        wp_clear_scheduled_hook('as24_motors_sync_import');
        wp_clear_scheduled_hook('as24_motors_sync_sync');
        wp_clear_scheduled_hook('as24_motors_sync_cleanup');
        
        AS24_Logger::info('All cron jobs cleared', 'general');
    }
    
    /**
     * Schedule import job
     */
    public static function schedule_import() {
        $auto_import = as24_motors_sync()->get_setting('auto_import', false);
        
        wp_clear_scheduled_hook('as24_motors_sync_import');
        
        if ($auto_import) {
            $frequency = as24_motors_sync()->get_setting('import_frequency', 'daily');
            
            if (!wp_next_scheduled('as24_motors_sync_import')) {
                wp_schedule_event(time(), $frequency, 'as24_motors_sync_import');
                AS24_Logger::info(sprintf('Import job scheduled: %s', $frequency), 'general');
            }
        }
    }
    
    /**
     * Schedule sync job
     */
    public static function schedule_sync() {
        $auto_sync = as24_motors_sync()->get_setting('auto_sync', true);
        
        wp_clear_scheduled_hook('as24_motors_sync_sync');
        
        if ($auto_sync) {
            $frequency = as24_motors_sync()->get_setting('sync_frequency', 'hourly');
            
            if (!wp_next_scheduled('as24_motors_sync_sync')) {
                wp_schedule_event(time(), $frequency, 'as24_motors_sync_sync');
                AS24_Logger::info(sprintf('Sync job scheduled: %s', $frequency), 'general');
            }
        }
    }
    
    /**
     * Schedule cleanup job (daily at 3 AM)
     */
    public static function schedule_cleanup() {
        wp_clear_scheduled_hook('as24_motors_sync_cleanup');
        
        if (!wp_next_scheduled('as24_motors_sync_cleanup')) {
            $tomorrow_3am = strtotime('tomorrow 3:00 AM');
            wp_schedule_event($tomorrow_3am, 'daily', 'as24_motors_sync_cleanup');
            AS24_Logger::info('Cleanup job scheduled: daily at 3 AM', 'general');
        }
    }
    
    /**
     * Run import job (uses unified smart sync)
     */
    public static function run_import() {
        AS24_Logger::info('Auto import triggered by cron (using smart sync)', 'import');
        
        $credentials = as24_motors_sync()->get_api_credentials();
        if (!$credentials) {
            AS24_Logger::error('Auto import failed: No credentials configured', 'import');
            return;
        }
        
        // Use smart sync - it handles new imports, updates, and orphan removal
        $result = AS24_Sync_Engine::smart_sync();
        
        if (is_wp_error($result)) {
            AS24_Logger::error('Auto import failed: ' . $result->get_error_message(), 'import');
        } else {
            AS24_Logger::info(sprintf(
                'Auto import completed: %d added, %d updated, %d removed, %d unchanged',
                $result['added'], $result['updated'], $result['removed'], $result['unchanged']
            ), 'import', $result);
        }
    }
    
    /**
     * Run sync job (uses unified smart sync)
     */
    public static function run_sync() {
        AS24_Logger::info('Auto sync triggered by cron', 'sync');
        
        $credentials = as24_motors_sync()->get_api_credentials();
        if (!$credentials) {
            AS24_Logger::error('Auto sync failed: No credentials configured', 'sync');
            return;
        }
        
        // Use smart sync - it handles new imports, updates, and orphan removal
        $result = AS24_Sync_Engine::smart_sync();
        
        if (is_wp_error($result)) {
            AS24_Logger::error('Auto sync failed: ' . $result->get_error_message(), 'sync');
        } else {
            AS24_Logger::info(sprintf(
                'Auto sync completed: %d added, %d updated, %d removed, %d unchanged',
                $result['added'], $result['updated'], $result['removed'], $result['unchanged']
            ), 'sync', $result);
        }
    }
    
    /**
     * Run cleanup job (daily at 3 AM)
     */
    public static function run_cleanup() {
        AS24_Logger::info('=== Auto cleanup triggered by cron ===', 'cleanup');
        
        $start_time = microtime(true);
        $results = array();
        
        try {
            // Priority 1: Duplicate cleanup
            AS24_Logger::info('[Priority 1] Running duplicate cleanup...', 'cleanup');
            $duplicate_result = AS24_Duplicate_Handler::remove_duplicates(false);
            $results['duplicates'] = $duplicate_result;
            
            // Priority 2: Orphan detection
            AS24_Logger::info('[Priority 2] Running orphan detection...', 'cleanup');
            $orphan_result = AS24_Orphan_Detector::trash_orphans(null, false);
            $results['orphans'] = $orphan_result;
            
            // Priority 3: Trash cleanup
            AS24_Logger::info('[Priority 3] Running trash cleanup...', 'cleanup');
            $trash_result = AS24_Orphan_Detector::cleanup_old_trash(false);
            $results['trash_cleanup'] = $trash_result;
            
            $duration = round(microtime(true) - $start_time, 2);
            AS24_Logger::info(sprintf('=== Cleanup complete in %ss ===', $duration), 'cleanup', $results);
            
            update_option('as24_last_cleanup_result', array(
                'timestamp' => current_time('mysql'),
                'results' => $results,
                'status' => 'success'
            ));
            
        } catch (Exception $e) {
            AS24_Logger::error('Cleanup error: ' . $e->getMessage(), 'cleanup');
            
            update_option('as24_last_cleanup_result', array(
                'timestamp' => current_time('mysql'),
                'status' => 'error',
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get next scheduled time for a job
     */
    public static function get_next_scheduled($job) {
        $hook = 'as24_motors_sync_' . $job;
        $timestamp = wp_next_scheduled($hook);
        
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
}

