<?php
/**
 * Memory Manager - Optimize Memory Usage During Import
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Memory_Manager {
    
    /**
     * Initialize memory management
     */
    public static function init() {
        // Increase memory limit if possible
        $current_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $desired_limit = wp_convert_hr_to_bytes('256M');
        
        if ($current_limit < $desired_limit) {
            @ini_set('memory_limit', '256M');
        }
        
        // Increase max execution time
        @set_time_limit(300);
        
        // Disable unnecessary WordPress features during import
        wp_defer_term_counting(true);
        wp_defer_comment_counting(true);
        
        // Add cleanup hooks
        add_action('as24_import_complete', array(__CLASS__, 'cleanup'));
        add_action('as24_import_failed', array(__CLASS__, 'cleanup'));
    }
    
    /**
     * Clean up after import
     */
    public static function cleanup() {
        // Re-enable term counting
        wp_defer_term_counting(false);
        wp_defer_comment_counting(false);
        
        // Clear any temporary data
        delete_option('as24_temp_import_data');
        
        // Clean up post meta
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_as24_temp_%'");
        
        // Clear object cache
        wp_cache_flush();
        
        // Run garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Check available memory
     */
    public static function check_memory() {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        $available_memory = $memory_limit - $memory_usage;
        
        // If less than 10MB available, trigger cleanup
        if ($available_memory < 10 * 1024 * 1024) {
            self::cleanup();
            return false;
        }
        
        return true;
    }
    
    /**
     * Convert memory value to bytes
     */
    private static function wp_convert_hr_to_bytes($value) {
        $value = strtolower(trim($value));
        $bytes = (int) $value;
        
        if (strpos($value, 'g') !== false) {
            $bytes *= 1024 * 1024 * 1024;
        } elseif (strpos($value, 'm') !== false) {
            $bytes *= 1024 * 1024;
        } elseif (strpos($value, 'k') !== false) {
            $bytes *= 1024;
        }
        
        return $bytes;
    }
}

// Initialize memory manager
add_action('as24_before_import', array('AS24_Memory_Manager', 'init'));
