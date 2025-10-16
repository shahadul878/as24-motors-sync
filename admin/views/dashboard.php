<?php
/**
 * Dashboard View - Modern Card-Based Interface
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = AS24_Admin::get_dashboard_stats();
$last_sync_result = get_option('as24_last_sync_result', array());
$last_cleanup_result = get_option('as24_last_cleanup_result', array());
?>

<div class="wrap as24-dashboard">
    <h1 class="as24-page-title">
        <span class="dashicons dashicons-update"></span>
        <?php _e('AutoScout24 Motors Sync', 'as24-motors-sync'); ?>
        <span class="as24-version">v<?php echo AS24_MOTORS_SYNC_VERSION; ?></span>
    </h1>
    
    <?php if (!$stats['credentials_configured']): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('API credentials not configured!', 'as24-motors-sync'); ?></strong>
                <a href="<?php echo admin_url('admin.php?page=as24-motors-sync-settings'); ?>" class="button button-small">
                    <?php _e('Configure Now', 'as24-motors-sync'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Alert Banners -->
    <?php if ($stats['duplicates'] > 0): ?>
        <div class="as24-alert as24-alert-warning">
            <span class="dashicons dashicons-warning"></span>
            <strong><?php printf(__('%d duplicate listings detected', 'as24-motors-sync'), $stats['duplicates']); ?></strong>
            <div class="as24-alert-actions">
                <button class="button button-small as24-remove-duplicates">
                    <?php _e('Auto-Clean', 'as24-motors-sync'); ?>
                </button>
                <button class="button button-small as24-dismiss-alert">
                    <?php _e('Dismiss', 'as24-motors-sync'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($stats['orphaned'] > 0): ?>
        <div class="as24-alert as24-alert-info">
            <span class="dashicons dashicons-info"></span>
            <strong><?php printf(__('%d listings removed from AutoScout24', 'as24-motors-sync'), $stats['orphaned']); ?></strong>
            <div class="as24-alert-actions">
                <button class="button button-small as24-trash-orphans">
                    <?php _e('Auto-Delete', 'as24-motors-sync'); ?>
                </button>
                <button class="button button-small">
                    <?php _e('Review', 'as24-motors-sync'); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Stats Grid -->
    <div class="as24-stats-grid">
        <!-- Remote Listings -->
        <div class="as24-stat-card">
            <div class="as24-stat-icon" style="background: #0073aa;">
                <span class="dashicons dashicons-cloud"></span>
            </div>
            <div class="as24-stat-content">
                <div class="as24-stat-value" id="remote-total"><?php echo number_format($stats['remote_total']); ?></div>
                <div class="as24-stat-label"><?php _e('Remote Listings', 'as24-motors-sync'); ?></div>
                <div class="as24-stat-sublabel"><?php _e('In AutoScout24', 'as24-motors-sync'); ?></div>
            </div>
        </div>
        
        <!-- Local Listings -->
        <div class="as24-stat-card">
            <div class="as24-stat-icon" style="background: #46b450;">
                <span class="dashicons dashicons-admin-home"></span>
            </div>
            <div class="as24-stat-content">
                <div class="as24-stat-value" id="local-total"><?php echo number_format($stats['local_total']); ?></div>
                <div class="as24-stat-label"><?php _e('Local Listings', 'as24-motors-sync'); ?></div>
                <div class="as24-stat-sublabel"><?php _e('Imported to site', 'as24-motors-sync'); ?></div>
            </div>
        </div>
        
        <!-- Last Sync -->
        <div class="as24-stat-card">
            <div class="as24-stat-icon" style="background: #826eb4;">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="as24-stat-content">
                <div class="as24-stat-value">
                    <?php 
                    if ($stats['last_sync']) {
                        echo human_time_diff($stats['last_sync'], current_time('timestamp')) . ' ' . __('ago', 'as24-motors-sync');
                    } else {
                        _e('Never', 'as24-motors-sync');
                    }
                    ?>
                </div>
                <div class="as24-stat-label"><?php _e('Last Sync', 'as24-motors-sync'); ?></div>
                <div class="as24-stat-sublabel as24-stat-status-<?php echo isset($last_sync_result['status']) ? $last_sync_result['status'] : 'unknown'; ?>">
                    <?php 
                    if (isset($last_sync_result['status']) && $last_sync_result['status'] === 'success') {
                        echo '<span class="dashicons dashicons-yes-alt"></span> ' . __('Success', 'as24-motors-sync');
                    } else {
                        echo __('Unknown', 'as24-motors-sync');
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Duplicates -->
        <div class="as24-stat-card <?php echo $stats['duplicates'] > 0 ? 'as24-stat-card-warning' : ''; ?>">
            <div class="as24-stat-icon" style="background: <?php echo $stats['duplicates'] > 0 ? '#f0ad4e' : '#5cb85c'; ?>;">
                <span class="dashicons dashicons-admin-page"></span>
            </div>
            <div class="as24-stat-content">
                <div class="as24-stat-value" id="duplicate-count"><?php echo number_format($stats['duplicates']); ?></div>
                <div class="as24-stat-label"><?php _e('Duplicates', 'as24-motors-sync'); ?></div>
                <div class="as24-stat-sublabel">
                    <?php echo $stats['duplicates'] > 0 ? __('Need cleanup', 'as24-motors-sync') : '<span class="dashicons dashicons-yes-alt"></span> ' . __('Clean', 'as24-motors-sync'); ?>
                </div>
            </div>
        </div>
        
        <!-- Orphaned -->
        <div class="as24-stat-card <?php echo $stats['orphaned'] > 0 ? 'as24-stat-card-warning' : ''; ?>">
            <div class="as24-stat-icon" style="background: <?php echo $stats['orphaned'] > 0 ? '#f0ad4e' : '#5cb85c'; ?>;">
                <span class="dashicons dashicons-dismiss"></span>
            </div>
            <div class="as24-stat-content">
                <div class="as24-stat-value" id="orphan-count"><?php echo number_format($stats['orphaned']); ?></div>
                <div class="as24-stat-label"><?php _e('Orphaned', 'as24-motors-sync'); ?></div>
                <div class="as24-stat-sublabel">
                    <?php echo $stats['orphaned'] > 0 ? __('Removed from AS24', 'as24-motors-sync') : '<span class="dashicons dashicons-yes-alt"></span> ' . __('Clean', 'as24-motors-sync'); ?>
                </div>
            </div>
        </div>
        
        <!-- In Trash -->
        <div class="as24-stat-card">
            <div class="as24-stat-icon" style="background: #dc3232;">
                <span class="dashicons dashicons-trash"></span>
            </div>
            <div class="as24-stat-content">
                <div class="as24-stat-value" id="trash-count"><?php echo number_format($stats['in_trash']); ?></div>
                <div class="as24-stat-label"><?php _e('In Trash', 'as24-motors-sync'); ?></div>
                <div class="as24-stat-sublabel">
                    <?php if ($stats['in_trash'] > 0): ?>
                        <button class="button-link as24-empty-trash"><?php _e('Empty Trash', 'as24-motors-sync'); ?></button>
                    <?php else: ?>
                        <?php _e('Empty', 'as24-motors-sync'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="as24-card">
        <h2><?php _e('Quick Actions', 'as24-motors-sync'); ?></h2>
        <div class="as24-actions-grid">
            <button class="as24-action-btn as24-action-primary as24-sync-now" <?php echo !$stats['credentials_configured'] ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-update-alt"></span>
                <span><?php _e('Sync & Import', 'as24-motors-sync'); ?></span>
            </button>
            
            <button class="as24-action-btn as24-action-secondary as24-scan-duplicates">
                <span class="dashicons dashicons-admin-page"></span>
                <span><?php _e('Scan Duplicates', 'as24-motors-sync'); ?></span>
            </button>
            
            <button class="as24-action-btn as24-action-secondary as24-check-orphans" <?php echo !$stats['credentials_configured'] ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-search"></span>
                <span><?php _e('Check Orphans', 'as24-motors-sync'); ?></span>
            </button>
            
            <button class="as24-action-btn as24-action-secondary as24-refresh-stats">
                <span class="dashicons dashicons-chart-bar"></span>
                <span><?php _e('Refresh Stats', 'as24-motors-sync'); ?></span>
            </button>

            <button class="as24-action-btn as24-action-warning as24-cleanup-non-as24">
                <span class="dashicons dashicons-trash"></span>
                <span><?php _e('Remove Non-AS24', 'as24-motors-sync'); ?></span>
            </button>
            
            <a href="<?php echo admin_url('admin.php?page=as24-motors-sync-settings'); ?>" class="as24-action-btn as24-action-secondary">
                <span class="dashicons dashicons-admin-settings"></span>
                <span><?php _e('Settings', 'as24-motors-sync'); ?></span>
            </a>
        </div>
        
        <p class="description" style="margin-top: 15px; padding: 10px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px;">
            <strong><?php _e('ℹ️ About Sync & Import:', 'as24-motors-sync'); ?></strong><br>
            <?php _e('This intelligent operation automatically detects new listings, updates changed ones, and removes orphaned listings. Works for both initial import and ongoing synchronization.', 'as24-motors-sync'); ?>
        </p>
    </div>
    
    <!-- Progress Section -->
    <div class="as24-card as24-progress-section" style="display: none;">
        <h2><?php _e('Operation Progress', 'as24-motors-sync'); ?></h2>
        <div class="as24-progress-bar-container">
            <div class="as24-progress-bar">
                <div class="as24-progress-fill" style="width: 0%;"></div>
            </div>
            <div class="as24-progress-text">
                <span class="as24-progress-percentage">0%</span>
                <span class="as24-progress-status"></span>
            </div>
        </div>
        <div class="as24-progress-details"></div>
    </div>
    
    <!-- Recent Activity -->
    <div class="as24-card">
        <h2><?php _e('Recent Activity', 'as24-motors-sync'); ?></h2>
        <div class="as24-activity-list" id="as24-activity-list">
            <?php
            $recent_logs = AS24_Logger::get_recent_logs('sync', 10, 'INFO');
            
            if (empty($recent_logs)): ?>
                <p class="as24-no-activity"><?php _e('No recent activity', 'as24-motors-sync'); ?></p>
            <?php else: ?>
                <ul>
                    <?php foreach ($recent_logs as $log): ?>
                        <li><?php echo esc_html($log); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Cron Status -->
    <div class="as24-card">
        <h2><?php _e('Scheduled Tasks', 'as24-motors-sync'); ?></h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Task', 'as24-motors-sync'); ?></th>
                    <th><?php _e('Status', 'as24-motors-sync'); ?></th>
                    <th><?php _e('Next Run', 'as24-motors-sync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('Auto Import', 'as24-motors-sync'); ?></td>
                    <td>
                        <?php if (as24_motors_sync()->get_setting('auto_import')): ?>
                            <span class="as24-status-active"><?php _e('Enabled', 'as24-motors-sync'); ?></span>
                        <?php else: ?>
                            <span class="as24-status-inactive"><?php _e('Disabled', 'as24-motors-sync'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo AS24_Cron_Manager::get_next_scheduled('import') ?? __('Not scheduled', 'as24-motors-sync'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Auto Sync', 'as24-motors-sync'); ?></td>
                    <td>
                        <?php if (as24_motors_sync()->get_setting('auto_sync')): ?>
                            <span class="as24-status-active"><?php _e('Enabled', 'as24-motors-sync'); ?></span>
                        <?php else: ?>
                            <span class="as24-status-inactive"><?php _e('Disabled', 'as24-motors-sync'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo AS24_Cron_Manager::get_next_scheduled('sync') ?? __('Not scheduled', 'as24-motors-sync'); ?></td>
                </tr>
                <tr>
                    <td><?php _e('Daily Cleanup', 'as24-motors-sync'); ?></td>
                    <td><span class="as24-status-active"><?php _e('Enabled', 'as24-motors-sync'); ?></span></td>
                    <td><?php echo AS24_Cron_Manager::get_next_scheduled('cleanup') ?? __('Not scheduled', 'as24-motors-sync'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Last Sync Results -->
    <?php if (!empty($last_sync_result)): ?>
        <div class="as24-card">
            <h2><?php _e('Last Sync Results', 'as24-motors-sync'); ?></h2>
            <div class="as24-sync-results">
                <p>
                    <strong><?php _e('Time:', 'as24-motors-sync'); ?></strong> 
                    <?php echo $last_sync_result['timestamp'] ?? __('Unknown', 'as24-motors-sync'); ?>
                </p>
                <?php if (isset($last_sync_result['results'])): ?>
                    <div class="as24-results-grid">
                        <div class="as24-result-item as24-result-added">
                            <span class="as24-result-value"><?php echo isset($last_sync_result['results']['added']) ? esc_html($last_sync_result['results']['added']) : '0'; ?></span>
                            <span class="as24-result-label"><?php _e('Added', 'as24-motors-sync'); ?></span>
                        </div>
                        <div class="as24-result-item as24-result-updated">
                            <span class="as24-result-value"><?php echo isset($last_sync_result['results']['updated']) ? esc_html($last_sync_result['results']['updated']) : '0'; ?></span>
                            <span class="as24-result-label"><?php _e('Updated', 'as24-motors-sync'); ?></span>
                        </div>
                        <div class="as24-result-item as24-result-removed">
                            <span class="as24-result-value"><?php echo isset($last_sync_result['results']['removed']) ? esc_html($last_sync_result['results']['removed']) : '0'; ?></span>
                            <span class="as24-result-label"><?php _e('Removed', 'as24-motors-sync'); ?></span>
                        </div>
                        <div class="as24-result-item as24-result-unchanged">
                            <span class="as24-result-value"><?php echo isset($last_sync_result['results']['unchanged']) ? esc_html($last_sync_result['results']['unchanged']) : '0'; ?></span>
                            <span class="as24-result-label"><?php _e('Unchanged', 'as24-motors-sync'); ?></span>
                        </div>
                    </div>
                    <p>
                        <strong><?php _e('Duration:', 'as24-motors-sync'); ?></strong> 
                        <?php echo isset($last_sync_result['results']['duration']) ? esc_html($last_sync_result['results']['duration']) : '0'; ?> <?php _e('seconds', 'as24-motors-sync'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Toast Container -->
<div class="as24-toast-container" id="as24-toast-container"></div>

