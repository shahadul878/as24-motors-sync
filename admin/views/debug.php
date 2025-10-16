<?php
/**
 * Debug Information Page
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get plugin info
$settings = get_option('as24_motors_sync_settings', array());
$credentials = as24_motors_sync()->get_api_credentials();
?>

<div class="wrap">
    <h1><?php _e('AS24 Sync - Debug Information', 'as24-motors-sync'); ?></h1>
    
    <div class="as24-card">
        <h2>🔍 Plugin Status</h2>
        <table class="widefat">
            <tr>
                <th>Plugin Version</th>
                <td><code><?php echo AS24_MOTORS_SYNC_VERSION; ?></code></td>
            </tr>
            <tr>
                <th>Plugin Path</th>
                <td><code><?php echo AS24_MOTORS_SYNC_PATH; ?></code></td>
            </tr>
            <tr>
                <th>Plugin URL</th>
                <td><code><?php echo AS24_MOTORS_SYNC_URL; ?></code></td>
            </tr>
            <tr>
                <th>API Credentials</th>
                <td>
                    <?php if ($credentials): ?>
                        <span style="color: green;">✓ Configured</span>
                    <?php else: ?>
                        <span style="color: red;">✗ Not Configured</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="as24-card">
        <h2>📁 Required Files</h2>
        <table class="widefat">
            <?php
            $required_files = array(
                'includes/class-logger.php',
                'includes/class-queries.php',
                'includes/class-duplicate-handler.php',
                'includes/class-orphan-detector.php',
                'includes/class-importer.php',
                'includes/class-sync-engine.php',
                'includes/class-image-handler.php',
                'includes/class-field-mapper.php',
                'includes/class-cron-manager.php',
                'admin/class-admin.php',
                'admin/class-ajax-handler.php',
                'admin/views/dashboard.php',
                'admin/views/settings.php',
                'assets/css/admin.css',
                'assets/js/admin.js'
            );
            
            foreach ($required_files as $file) {
                $full_path = AS24_MOTORS_SYNC_PATH . $file;
                $exists = file_exists($full_path);
                $readable = $exists && is_readable($full_path);
                ?>
                <tr>
                    <td><code><?php echo $file; ?></code></td>
                    <td>
                        <?php if ($exists && $readable): ?>
                            <span style="color: green;">✓ OK</span>
                        <?php elseif ($exists): ?>
                            <span style="color: orange;">⚠ Not Readable</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Missing</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
    
    <div class="as24-card">
        <h2>🔌 Class Status</h2>
        <table class="widefat">
            <?php
            $required_classes = array(
                'AS24_Logger',
                'AS24_Queries',
                'AS24_Duplicate_Handler',
                'AS24_Orphan_Detector',
                'AS24_Importer',
                'AS24_Sync_Engine',
                'AS24_Image_Handler',
                'AS24_Field_Mapper',
                'AS24_Cron_Manager',
                'AS24_Admin',
                'AS24_Ajax_Handler'
            );
            
            foreach ($required_classes as $class) {
                ?>
                <tr>
                    <td><code><?php echo $class; ?></code></td>
                    <td>
                        <?php if (class_exists($class)): ?>
                            <span style="color: green;">✓ Loaded</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Not Found</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
    
    <div class="as24-card">
        <h2>🎯 AJAX Hooks Registered</h2>
        <table class="widefat">
            <?php
            global $wp_filter;
            
            $ajax_actions = array(
                'wp_ajax_as24_sync_now',
                'wp_ajax_as24_scan_duplicates',
                'wp_ajax_as24_remove_duplicates',
                'wp_ajax_as24_check_orphans',
                'wp_ajax_as24_trash_orphans',
                'wp_ajax_as24_cleanup_trash',
                'wp_ajax_as24_get_stats',
                'wp_ajax_as24_refresh_remote_count',
                'wp_ajax_as24_get_logs',
                'wp_ajax_as24_clear_logs',
                'wp_ajax_as24_test_connection'
            );
            
            foreach ($ajax_actions as $action) {
                $registered = isset($wp_filter[$action]) && !empty($wp_filter[$action]);
                ?>
                <tr>
                    <td><code><?php echo str_replace('wp_ajax_', '', $action); ?></code></td>
                    <td>
                        <?php if ($registered): ?>
                            <span style="color: green;">✓ Registered</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Not Registered</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
    
    <div class="as24-card">
        <h2>⚙️ WordPress Environment</h2>
        <table class="widefat">
            <tr>
                <th>WordPress Version</th>
                <td><code><?php echo get_bloginfo('version'); ?></code></td>
            </tr>
            <tr>
                <th>PHP Version</th>
                <td><code><?php echo PHP_VERSION; ?></code></td>
            </tr>
            <tr>
                <th>Memory Limit</th>
                <td><code><?php echo ini_get('memory_limit'); ?></code></td>
            </tr>
            <tr>
                <th>Max Execution Time</th>
                <td><code><?php echo ini_get('max_execution_time'); ?>s</code></td>
            </tr>
            <tr>
                <th>WP_DEBUG</th>
                <td>
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                        <span style="color: green;">✓ Enabled</span>
                    <?php else: ?>
                        <span style="color: gray;">Disabled</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="as24-card">
        <h2>🧪 Quick Tests</h2>
        
        <h3>Test 1: Test API Connection</h3>
        <button type="button" class="button button-primary" id="test-connection-debug">
            Test Connection
        </button>
        <div id="connection-result" style="margin-top: 10px;"></div>
        
        <h3>Test 2: Test AJAX Endpoint</h3>
        <button type="button" class="button button-primary" id="test-ajax-debug">
            Test AJAX
        </button>
        <div id="ajax-result" style="margin-top: 10px;"></div>
        
        <h3>Test 3: Check JavaScript</h3>
        <button type="button" class="button button-primary" id="test-js-debug">
            Test JavaScript
        </button>
        <div id="js-result" style="margin-top: 10px;"></div>
        
        <h3>Test 4: Detailed Sync Test</h3>
        <button type="button" class="button button-primary" id="test-sync-debug">
            Test Sync with Details
        </button>
        <div id="sync-result" style="margin-top: 10px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test Connection
    $('#test-connection-debug').on('click', function() {
        $('#connection-result').html('<p>Testing...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'as24_test_connection',
                nonce: '<?php echo wp_create_nonce('as24_motors_sync_nonce'); ?>'
            },
            success: function(response) {
                $('#connection-result').html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
            },
            error: function(xhr, status, error) {
                $('#connection-result').html('<p style="color: red;">Error: ' + error + '</p><pre>' + xhr.responseText + '</pre>');
            }
        });
    });
    
    // Test AJAX
    $('#test-ajax-debug').on('click', function() {
        $('#ajax-result').html('<p>Testing AJAX endpoint...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'as24_sync_now',
                nonce: '<?php echo wp_create_nonce('as24_motors_sync_nonce'); ?>'
            },
            success: function(response) {
                $('#ajax-result').html('<p style="color: green;">✓ AJAX working!</p><pre>' + JSON.stringify(response, null, 2) + '</pre>');
            },
            error: function(xhr, status, error) {
                $('#ajax-result').html('<p style="color: red;">✗ AJAX Error: ' + error + '</p><pre>' + xhr.responseText + '</pre>');
            }
        });
    });
    
    // Test JavaScript
    $('#test-js-debug').on('click', function() {
        let results = '';
        
        results += 'typeof AS24_Admin: ' + typeof AS24_Admin + '\n';
        results += 'jQuery loaded: ' + (typeof jQuery !== 'undefined') + '\n';
        results += 'Button exists: ' + $('.as24-sync-now').length + '\n';
        results += 'Button disabled: ' + $('.as24-sync-now').prop('disabled') + '\n';
        
        if (typeof AS24_Admin !== 'undefined') {
            results += '\nAS24_Admin object:\n';
            results += JSON.stringify(AS24_Admin, null, 2);
        }
        
        $('#js-result').html('<pre>' + results + '</pre>');
    });
    
    // Test Sync with Details
    $('#test-sync-debug').on('click', function() {
        $('#sync-result').html('<p>Running detailed sync test...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'as24_sync_now',
                nonce: '<?php echo wp_create_nonce('as24_motors_sync_nonce'); ?>',
                debug: true
            },
            success: function(response) {
                let output = '<h4>Sync Results:</h4>';
                output += '<pre>' + JSON.stringify(response, null, 2) + '</pre>';
                
                if (response.success && response.data) {
                    const data = response.data;
                    output += '<h4>Summary:</h4>';
                    output += '<ul>';
                    output += '<li>Added: ' + data.added + '</li>';
                    output += '<li>Updated: ' + data.updated + '</li>';
                    output += '<li>Removed: ' + data.removed + '</li>';
                    output += '<li>Unchanged: ' + data.unchanged + '</li>';
                    output += '<li>Errors: ' + data.errors + '</li>';
                    output += '<li>Duration: ' + data.duration + 'ms</li>';
                    output += '</ul>';
                    
                    if (data.added === 0 && data.updated === 0) {
                        output += '<p style="color: orange;"><strong>No listings imported. Possible reasons:</strong></p>';
                        output += '<ul>';
                        output += '<li>API credentials not configured</li>';
                        output += '<li>No listings in AutoScout24 account</li>';
                        output += '<li>All listings already exist locally</li>';
                        output += '<li>API connection failed</li>';
                        output += '</ul>';
                    }
                }
                
                $('#sync-result').html(output);
            },
            error: function(xhr, status, error) {
                $('#sync-result').html('<p style="color: red;">Sync Error: ' + error + '</p><pre>' + xhr.responseText + '</pre>');
            }
        });
    });
});
</script>

