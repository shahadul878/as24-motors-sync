<?php
/**
 * Admin Interface
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Admin {
    
    /**
     * Add dedicated admin menu
     */
    public static function add_menu_pages() {
        // Add top-level menu with custom icon
        add_menu_page(
            __('AutoScout24 Sync', 'as24-motors-sync'),
            __('AS24 Sync', 'as24-motors-sync'),
            'manage_options',
            'as24-motors-sync',
            array(__CLASS__, 'render_dashboard'),
            'dashicons-update-alt', // Rotating arrows icon
            58 // Position after Settings (25), Tools (75), below typical plugin menus
        );
        
        // Add Dashboard as first submenu (replaces duplicate menu item)
        add_submenu_page(
            'as24-motors-sync',
            __('Dashboard', 'as24-motors-sync'),
            __('Dashboard', 'as24-motors-sync'),
            'manage_options',
            'as24-motors-sync',
            array(__CLASS__, 'render_dashboard')
        );
        
        // Add Settings as submenu
        add_submenu_page(
            'as24-motors-sync',
            __('Settings', 'as24-motors-sync'),
            __('Settings', 'as24-motors-sync'),
            'manage_options',
            'as24-motors-sync-settings',
            array(__CLASS__, 'render_settings')
        );
        
        // Add Debug page as submenu
        add_submenu_page(
            'as24-motors-sync',
            __('Debug Info', 'as24-motors-sync'),
            __('Debug Info', 'as24-motors-sync'),
            'manage_options',
            'as24-motors-sync-debug',
            array(__CLASS__, 'render_debug')
        );
    }
    
    /**
     * Render debug page
     */
    public static function render_debug() {
        include AS24_MOTORS_SYNC_PATH . 'admin/views/debug.php';
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        // Load on all admin pages for our plugin (dashboard and settings)
        $our_pages = array(
            'toplevel_page_as24-motors-sync',        // Dashboard
            'as24-sync_page_as24-motors-sync-settings', // Settings
            'settings_page_as24-motors-sync',        // If under Settings menu
            'settings_page_as24-motors-sync-settings' // Settings under Settings menu
        );
        
        // Check if we're on one of our pages
        $is_our_page = in_array($hook, $our_pages) || strpos($hook, 'as24-motors-sync') !== false;
        
        if (!$is_our_page) {
            return;
        }
        
        // Enqueue WordPress Dashicons
        wp_enqueue_style('dashicons');
        
        // Enqueue styles
        wp_enqueue_style(
            'as24-motors-sync-admin',
            AS24_MOTORS_SYNC_URL . 'assets/css/admin.css',
            array('dashicons'),
            AS24_MOTORS_SYNC_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'as24-motors-sync-admin',
            AS24_MOTORS_SYNC_URL . 'assets/js/admin.js',
            array('jquery'),
            AS24_MOTORS_SYNC_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('as24-motors-sync-admin', 'AS24_Admin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('as24_motors_sync_nonce'),
            'plugin_url' => AS24_MOTORS_SYNC_URL,
            'strings' => array(
                'importing' => __('Importing...', 'as24-motors-sync'),
                'syncing' => __('Syncing...', 'as24-motors-sync'),
                'complete' => __('Complete!', 'as24-motors-sync'),
                'error' => __('Error occurred', 'as24-motors-sync'),
                'confirm_cleanup' => __('Are you sure you want to remove all duplicates?', 'as24-motors-sync'),
                'confirm_orphan' => __('Are you sure you want to trash orphaned listings?', 'as24-motors-sync'),
            )
        ));
    }
    
    /**
     * Render dashboard page
     */
    public static function render_dashboard() {
        include AS24_MOTORS_SYNC_PATH . 'admin/views/dashboard.php';
    }
    
    /**
     * Render settings page
     */
    public static function render_settings() {
        // Handle form submission
        if (isset($_POST['as24_save_settings']) && check_admin_referer('as24_settings_save', 'as24_settings_nonce')) {
            self::save_settings();
        }
        
        include AS24_MOTORS_SYNC_PATH . 'admin/views/settings.php';
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        $settings = array();
        
        // API Credentials
        $settings['api_username'] = sanitize_text_field($_POST['api_username'] ?? '');
        $settings['api_password'] = sanitize_text_field($_POST['api_password'] ?? '');
        
        // Import settings
        $settings['auto_import'] = isset($_POST['auto_import']);
        $settings['import_frequency'] = sanitize_text_field($_POST['import_frequency'] ?? 'daily');
        
        // Sync settings
        $settings['auto_sync'] = isset($_POST['auto_sync']);
        $settings['sync_frequency'] = sanitize_text_field($_POST['sync_frequency'] ?? 'hourly');
        
        // Data integrity
        $settings['duplicate_cleanup'] = isset($_POST['duplicate_cleanup']);
        $settings['orphan_cleanup'] = isset($_POST['orphan_cleanup']);
        $settings['orphan_retention_days'] = intval($_POST['orphan_retention_days'] ?? 30);
        
        // Batch size
        $settings['batch_size'] = intval($_POST['batch_size'] ?? 50);
        
        // Update settings
        $current_settings = get_option('as24_motors_sync_settings', array());
        $updated_settings = array_merge($current_settings, $settings);
        update_option('as24_motors_sync_settings', $updated_settings);
        
        // Reschedule cron jobs
        AS24_Cron_Manager::schedule_all_jobs();
        
        AS24_Logger::info('Settings updated', 'general', $settings);
        
        add_settings_error('as24_motors_sync', 'settings_saved', __('Settings saved successfully!', 'as24-motors-sync'), 'success');
    }
    
    /**
     * Get dashboard statistics
     */
    public static function get_dashboard_stats() {
        $credentials = as24_motors_sync()->get_api_credentials();
        
        $stats = array(
            'remote_total' => 0,
            'local_total' => 0,
            'duplicates' => 0,
            'orphaned' => 0,
            'in_trash' => 0,
            'last_sync' => null,
            'last_import' => null,
            'credentials_configured' => !empty($credentials)
        );
        
        // Get local count
        $count = wp_count_posts('listings');
        $stats['local_total'] = $count->publish + $count->draft + $count->private;
        
        // Get duplicate stats
        $duplicate_stats = AS24_Duplicate_Handler::get_duplicate_stats();
        $stats['duplicates'] = $duplicate_stats['total_duplicate_listings'];
        
        // Get orphan stats
        $orphan_stats = AS24_Orphan_Detector::get_orphan_stats();
        $stats['orphaned'] = $orphan_stats['orphans_detected'];
        $stats['in_trash'] = $orphan_stats['in_trash'];
        
        // Get remote total (cached)
        $stats['remote_total'] = get_option('as24_cached_remote_total', 0);
        
        // Get last sync/import times
        $stats['last_sync'] = as24_motors_sync()->get_setting('last_sync');
        $stats['last_import'] = as24_motors_sync()->get_setting('last_import');
        
        return $stats;
    }
}

