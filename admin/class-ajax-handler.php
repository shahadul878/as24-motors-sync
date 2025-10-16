<?php
/**
 * AJAX Handler - All Admin Operations
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Ajax_Handler {
    
    /**
     * Initialize AJAX hooks
     */
    public static function init_hooks() {
        // Unified Sync & Import operation
        add_action('wp_ajax_as24_sync_now', array(__CLASS__, 'ajax_sync_now'));
        
        // Duplicate operations
        add_action('wp_ajax_as24_scan_duplicates', array(__CLASS__, 'ajax_scan_duplicates'));
        add_action('wp_ajax_as24_remove_duplicates', array(__CLASS__, 'ajax_remove_duplicates'));
        
        // Orphan operations
        add_action('wp_ajax_as24_check_orphans', array(__CLASS__, 'ajax_check_orphans'));
        add_action('wp_ajax_as24_trash_orphans', array(__CLASS__, 'ajax_trash_orphans'));
        add_action('wp_ajax_as24_cleanup_trash', array(__CLASS__, 'ajax_cleanup_trash'));
        
        // Stats and monitoring
        add_action('wp_ajax_as24_get_stats', array(__CLASS__, 'ajax_get_stats'));
        add_action('wp_ajax_as24_refresh_remote_count', array(__CLASS__, 'ajax_refresh_remote_count'));
        
        // Logs
        add_action('wp_ajax_as24_get_logs', array(__CLASS__, 'ajax_get_logs'));
        add_action('wp_ajax_as24_clear_logs', array(__CLASS__, 'ajax_clear_logs'));
        
        // Connection test
        add_action('wp_ajax_as24_test_connection', array(__CLASS__, 'ajax_test_connection'));
        
        // Cleanup non-AS24 listings
        add_action('wp_ajax_as24_cleanup_non_as24', array(__CLASS__, 'ajax_cleanup_non_as24'));
    }
    
    /**
     * Verify nonce for AJAX requests
     */
    private static function verify_nonce() {
        if (!check_ajax_referer('as24_motors_sync_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Invalid security token'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
    }
    
    /**
     * Unified Sync & Import operation
     * Uses smart sync which handles both new imports and updates
     */
    public static function ajax_sync_now() {
        self::verify_nonce();
        
        // Check if status check requested
        if (isset($_POST['check_status']) && $_POST['check_status'] === 'true') {
            $status = AS24_Background_Sync::get_status();
            wp_send_json_success($status);
            return;
        }
        
        // Check if sync already running
        $status = AS24_Background_Sync::get_status();
        if ($status['is_running']) {
            wp_send_json_error(array(
                'message' => 'Sync already in progress',
                'status' => $status
            ));
            return;
        }
        
        // Start background sync
        $result = AS24_Background_Sync::start_sync();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Background sync started',
            'status' => AS24_Background_Sync::get_status()
        ));
    }
    
    /**
     * Debug sync with detailed information
     */
    private static function debug_sync() {
        $start_time = microtime(true);
        
        $debug_info = array(
            'added' => 0,
            'updated' => 0,
            'removed' => 0,
            'unchanged' => 0,
            'errors' => 0,
            'duration' => 0,
            'debug' => array()
        );
        
        // Step 1: Test API connection
        $debug_info['debug']['step1'] = 'Testing API connection...';
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            $debug_info['debug']['step1_error'] = $data->get_error_message();
            return $debug_info;
        }
        
        $total_listings = $data['data']['search']['listings']['metadata']['totalItems'];
        $debug_info['debug']['step1_success'] = "Found {$total_listings} total listings in API";
        
        // Step 2: Fetch first page of listings
        $debug_info['debug']['step2'] = 'Fetching first page of listings...';
        $query = AS24_Queries::get_essential_listings_query(1, 5); // Just 5 for testing
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            $debug_info['debug']['step2_error'] = $data->get_error_message();
            return $debug_info;
        }
        
        $listings = $data['data']['search']['listings']['listings'];
        $debug_info['debug']['step2_success'] = "Fetched " . count($listings) . " listings from first page";
        
        if (empty($listings)) {
            $debug_info['debug']['step2_warning'] = "No listings returned from API";
            return $debug_info;
        }
        
        // Step 3: Check local listings
        $debug_info['debug']['step3'] = 'Checking local listings...';
        $local_count = wp_count_posts('listings');
        $debug_info['debug']['step3_success'] = "Found {$local_count->publish} local listings";
        
        // Step 4: Try to import first listing
        $debug_info['debug']['step4'] = 'Attempting to import first listing...';
        $first_listing = $listings[0];
        $debug_info['debug']['step4_listing_id'] = $first_listing['id'];
        
        $import_result = AS24_Importer::import_single_listing($first_listing);
        
        if (is_wp_error($import_result)) {
            $debug_info['debug']['step4_error'] = $import_result->get_error_message();
        } else {
            $debug_info['debug']['step4_success'] = "Import result: " . $import_result['action'];
            if ($import_result['action'] === 'imported') {
                $debug_info['added'] = 1;
            } elseif ($import_result['action'] === 'updated') {
                $debug_info['updated'] = 1;
            } else {
                $debug_info['unchanged'] = 1;
            }
        }
        
        $duration = round(microtime(true) - $start_time, 2);
        $debug_info['duration'] = $duration;
        
        return $debug_info;
    }
    
    /**
     * Scan for duplicates
     */
    public static function ajax_scan_duplicates() {
        self::verify_nonce();
        
        $stats = AS24_Duplicate_Handler::get_duplicate_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Remove duplicates
     */
    public static function ajax_remove_duplicates() {
        self::verify_nonce();
        
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';
        
        $result = AS24_Duplicate_Handler::remove_duplicates($dry_run);
        
        wp_send_json_success($result);
    }
    
    /**
     * Check for orphans
     */
    public static function ajax_check_orphans() {
        self::verify_nonce();
        
        $stats = AS24_Orphan_Detector::get_orphan_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Trash orphaned listings
     */
    public static function ajax_trash_orphans() {
        self::verify_nonce();
        
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';
        
        $result = AS24_Orphan_Detector::trash_orphans(null, $dry_run);
        
        wp_send_json_success($result);
    }
    
    /**
     * Cleanup old trash
     */
    public static function ajax_cleanup_trash() {
        self::verify_nonce();
        
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === 'true';
        
        $result = AS24_Orphan_Detector::cleanup_old_trash($dry_run);
        
        wp_send_json_success($result);
    }
    
    /**
     * Get dashboard statistics
     */
    public static function ajax_get_stats() {
        self::verify_nonce();
        
        $stats = AS24_Admin::get_dashboard_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Refresh remote listing count
     */
    public static function ajax_refresh_remote_count() {
        self::verify_nonce();
        
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            wp_send_json_error(array('message' => $data->get_error_message()));
        }
        
        $total = $data['data']['search']['listings']['metadata']['totalItems'];
        update_option('as24_cached_remote_total', $total);
        
        wp_send_json_success(array('total' => $total));
    }
    
    /**
     * Get logs
     */
    public static function ajax_get_logs() {
        self::verify_nonce();
        
        $type = sanitize_text_field($_POST['type'] ?? 'general');
        $lines = intval($_POST['lines'] ?? 100);
        $level = sanitize_text_field($_POST['level'] ?? null);
        
        $logs = AS24_Logger::get_recent_logs($type, $lines, $level);
        
        wp_send_json_success(array('logs' => $logs));
    }
    
    /**
     * Clear logs
     */
    public static function ajax_clear_logs() {
        self::verify_nonce();
        
        $type = sanitize_text_field($_POST['type'] ?? 'all');
        
        $result = AS24_Logger::clear_logs($type);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Logs cleared successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to clear logs'));
        }
    }
    
    /**
     * Test API connection
     */
    /**
     * Remove non-AutoScout24 listings
     */
    public static function ajax_cleanup_non_as24() {
        self::verify_nonce();
        
        $result = AS24_Cleanup::remove_non_as24_listings();
        
        wp_send_json_success($result);
    }
    
    /**
     * Test API connection
     */
    public static function ajax_test_connection() {
        self::verify_nonce();
        
        $query = AS24_Queries::get_total_count_query();
        $data = AS24_Queries::make_request($query);
        
        if (is_wp_error($data)) {
            wp_send_json_error(array(
                'message' => 'Connection failed: ' . $data->get_error_message()
            ));
        }
        
        $total = $data['data']['search']['listings']['metadata']['totalItems'];
        
        wp_send_json_success(array(
            'message' => 'Connection successful!',
            'total_listings' => $total
        ));
    }
}

