<?php
/**
 * Plugin Name: AutoScout24 Motors Sync
 * Plugin URI: https://github.com/shahadul878/as24-motors-sync
 * Description: High-performance AutoScout24 to Motors theme synchronization with automatic duplicate prevention, orphan cleanup, and optimized API queries. 70% faster imports, zero duplicates guaranteed.
 * Version: 2.0.0
 * Author: H M Shahadul Islam
 * Author URI: https://github.com/shahadul878
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: as24-motors-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AS24_MOTORS_SYNC_VERSION', '2.0.0');
define('AS24_MOTORS_SYNC_FILE', __FILE__);
define('AS24_MOTORS_SYNC_PATH', plugin_dir_path(__FILE__));
define('AS24_MOTORS_SYNC_URL', plugin_dir_url(__FILE__));
define('AS24_MOTORS_SYNC_BASENAME', plugin_basename(__FILE__));

// Require composer autoloader if available
if (file_exists(AS24_MOTORS_SYNC_PATH . 'vendor/autoload.php')) {
    require_once AS24_MOTORS_SYNC_PATH . 'vendor/autoload.php';
}

// Core includes
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-logger.php';
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-queries.php';
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-duplicate-handler.php';
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-orphan-detector.php';
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-importer.php';
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-sync-engine.php';
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-image-handler.php';
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-field-mapper.php';
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-background-sync.php';
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-cleanup.php';

// Admin includes
if (is_admin()) {
    require_once AS24_MOTORS_SYNC_PATH . 'admin/class-admin.php';
    require_once AS24_MOTORS_SYNC_PATH . 'admin/class-ajax-handler.php';
}

// Cron includes
require_once AS24_MOTORS_SYNC_PATH . 'includes/class-cron-manager.php';

/**
 * Main Plugin Class
 */
class AS24_Motors_Sync {
    
    /**
     * Instance of this class
     * @var AS24_Motors_Sync
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     * @var array
     */
    private $settings = array();
    
    /**
     * Get singleton instance
     * 
     * @return AS24_Motors_Sync
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_settings();
        $this->init_hooks();
        
        // Initialize logger
        AS24_Logger::init();
        
        // Log plugin loaded
        AS24_Logger::info('Plugin loaded - version ' . AS24_MOTORS_SYNC_VERSION, 'general');
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(AS24_MOTORS_SYNC_FILE, array($this, 'activate'));
        register_deactivation_hook(AS24_MOTORS_SYNC_FILE, array($this, 'deactivate'));
        
        // Init hook
        add_action('init', array($this, 'init'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array('AS24_Admin', 'add_menu_pages'));
            add_action('admin_enqueue_scripts', array('AS24_Admin', 'enqueue_assets'));
            
            // Add settings link on plugins page
            add_filter('plugin_action_links_' . AS24_MOTORS_SYNC_BASENAME, array($this, 'add_plugin_action_links'));
            
            // AJAX hooks
            AS24_Ajax_Handler::init_hooks();
        }
        
        // Background sync hook
        add_action('as24_process_sync_batch', array('AS24_Background_Sync', 'process_batch'));
        
        // Cron hooks
        AS24_Cron_Manager::init_hooks();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('as24-motors-sync', false, dirname(AS24_MOTORS_SYNC_BASENAME) . '/languages');
        
        // Check if Motors plugin is active
        if (!$this->is_motors_active()) {
            add_action('admin_notices', array($this, 'motors_inactive_notice'));
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        AS24_Logger::info('=== Plugin Activation Started ===', 'general');
        
        // Set default settings
        $default_settings = array(
            'api_username' => '',
            'api_password' => '',
            'auto_import' => false,
            'auto_sync' => true,
            'import_frequency' => 'daily',
            'sync_frequency' => 'hourly',
            'batch_size' => 50,
            'orphan_cleanup' => true,
            'orphan_retention_days' => 30,
            'duplicate_cleanup' => true,
            'version' => AS24_MOTORS_SYNC_VERSION,
            'installed_at' => current_time('mysql')
        );
        
        add_option('as24_motors_sync_settings', $default_settings);
        
        // Create database index for duplicate prevention
        AS24_Duplicate_Handler::add_unique_index();
        AS24_Logger::info('Database indexes created', 'general');
        
        // Schedule cron jobs
        AS24_Cron_Manager::schedule_all_jobs();
        AS24_Logger::info('Cron jobs scheduled', 'general');
        
        // Create necessary database tables if needed
        $this->create_tables();
        
        AS24_Logger::info('=== Plugin Activation Completed ===', 'general');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        AS24_Logger::info('=== Plugin Deactivation Started ===', 'general');
        
        // Clear all scheduled cron jobs
        AS24_Cron_Manager::clear_all_jobs();
        AS24_Logger::info('Cron jobs cleared', 'general');
        
        AS24_Logger::info('=== Plugin Deactivation Completed ===', 'general');
    }
    
    /**
     * Create necessary database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sync history table for tracking operations
        $table_name = $wpdb->prefix . 'as24_sync_history';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            operation_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            total_processed int(11) DEFAULT 0,
            total_imported int(11) DEFAULT 0,
            total_updated int(11) DEFAULT 0,
            total_removed int(11) DEFAULT 0,
            total_errors int(11) DEFAULT 0,
            duration float DEFAULT 0,
            message text,
            details longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY operation_type (operation_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        AS24_Logger::info('Database tables created/verified', 'general');
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $this->settings = get_option('as24_motors_sync_settings', array());
    }
    
    /**
     * Get plugin setting
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Update plugin setting
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public function update_setting($key, $value) {
        $this->settings[$key] = $value;
        return update_option('as24_motors_sync_settings', $this->settings);
    }
    
    /**
     * Check if Motors plugin is active
     * 
     * @return bool
     */
    private function is_motors_active() {
        // Check if Motors plugin is active
        return defined('STM_LISTINGS_PATH') || 
               class_exists('STM_Listings') || 
               post_type_exists('stm_listings');
    }
    
    /**
     * Display admin notice if Motors is not active
     */
    public function motors_inactive_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>AutoScout24 Motors Sync</strong> requires the 
                <strong>Motors - Car Dealer, Classifieds & Listing</strong> plugin to be installed and activated.
            </p>
        </div>
        <?php
    }
    
    /**
     * Add action links on plugins page
     * 
     * @param array $links Existing plugin action links
     * @return array Modified links
     */
    public function add_plugin_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=as24-motors-sync') . '">' . 
                '<strong style="color: #0073aa;">' . __('Dashboard', 'as24-motors-sync') . '</strong>' .
            '</a>',
            '<a href="' . admin_url('admin.php?page=as24-motors-sync-settings') . '">' . 
                __('Settings', 'as24-motors-sync') . 
            '</a>'
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Get API credentials
     * 
     * @return array|false Array with username and password or false if not set
     */
    public function get_api_credentials() {
        $username = $this->get_setting('api_username');
        $password = $this->get_setting('api_password');
        
        if (empty($username) || empty($password)) {
            return false;
        }
        
        return array(
            'username' => $username,
            'password' => $password
        );
    }
}

/**
 * Get main plugin instance
 * 
 * @return AS24_Motors_Sync
 */
function as24_motors_sync() {
    return AS24_Motors_Sync::instance();
}

// Initialize plugin
as24_motors_sync();

