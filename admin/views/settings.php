<?php
/**
 * Settings View - Tabbed Interface
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('as24_motors_sync_settings', array());
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'connection';
?>

<div class="wrap as24-settings">
    <h1>
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('AutoScout24 Sync Settings', 'as24-motors-sync'); ?>
    </h1>
    
    <?php settings_errors('as24_motors_sync'); ?>
    
    <!-- Tabs -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=as24-motors-sync-settings&tab=connection" class="nav-tab <?php echo $active_tab === 'connection' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Connection', 'as24-motors-sync'); ?>
        </a>
        <a href="?page=as24-motors-sync-settings&tab=import" class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Import', 'as24-motors-sync'); ?>
        </a>
        <a href="?page=as24-motors-sync-settings&tab=sync" class="nav-tab <?php echo $active_tab === 'sync' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Sync', 'as24-motors-sync'); ?>
        </a>
        <a href="?page=as24-motors-sync-settings&tab=integrity" class="nav-tab <?php echo $active_tab === 'integrity' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Data Integrity', 'as24-motors-sync'); ?>
        </a>
        <a href="?page=as24-motors-sync-settings&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Logs', 'as24-motors-sync'); ?>
        </a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('as24_settings_save', 'as24_settings_nonce'); ?>
        
        <!-- Connection Tab -->
        <?php if ($active_tab === 'connection'): ?>
            <div class="as24-tab-content">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_username"><?php _e('API Username', 'as24-motors-sync'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="api_username" id="api_username" 
                                   value="<?php echo esc_attr($settings['api_username'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Your AutoScout24 API username (e.g., 2142078191-gma-cars)', 'as24-motors-sync'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_password"><?php _e('API Password', 'as24-motors-sync'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="api_password" id="api_password" 
                                   value="<?php echo esc_attr($settings['api_password'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Your AutoScout24 API password', 'as24-motors-sync'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" class="button button-secondary as24-test-connection">
                        <?php _e('Test Connection', 'as24-motors-sync'); ?>
                    </button>
                    <input type="submit" name="as24_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'as24-motors-sync'); ?>" />
                </p>
                
                <div id="as24-connection-result" style="margin-top: 15px;"></div>
            </div>
        <?php endif; ?>
        
        <!-- Import Tab -->
        <?php if ($active_tab === 'import'): ?>
            <div class="as24-tab-content">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Auto Import', 'as24-motors-sync'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_import" value="1" <?php checked($settings['auto_import'] ?? false, true); ?> />
                                <?php _e('Enable automatic scheduled imports', 'as24-motors-sync'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="import_frequency"><?php _e('Import Frequency', 'as24-motors-sync'); ?></label>
                        </th>
                        <td>
                            <select name="import_frequency" id="import_frequency">
                                <option value="hourly" <?php selected($settings['import_frequency'] ?? '', 'hourly'); ?>><?php _e('Hourly', 'as24-motors-sync'); ?></option>
                                <option value="twicedaily" <?php selected($settings['import_frequency'] ?? '', 'twicedaily'); ?>><?php _e('Twice Daily', 'as24-motors-sync'); ?></option>
                                <option value="daily" <?php selected($settings['import_frequency'] ?? 'daily', 'daily'); ?>><?php _e('Daily', 'as24-motors-sync'); ?></option>
                                <option value="weekly" <?php selected($settings['import_frequency'] ?? '', 'weekly'); ?>><?php _e('Weekly', 'as24-motors-sync'); ?></option>
                            </select>
                            <p class="description"><?php _e('How often to automatically import new listings', 'as24-motors-sync'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="batch_size"><?php _e('Batch Size', 'as24-motors-sync'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="batch_size" id="batch_size" 
                                   value="<?php echo esc_attr($settings['batch_size'] ?? 50); ?>" 
                                   min="10" max="50" />
                            <p class="description"><?php _e('Number of listings to process per batch (recommended: 50)', 'as24-motors-sync'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="as24_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'as24-motors-sync'); ?>" />
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Sync Tab -->
        <?php if ($active_tab === 'sync'): ?>
            <div class="as24-tab-content">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Auto Sync', 'as24-motors-sync'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_sync" value="1" <?php checked($settings['auto_sync'] ?? true, true); ?> />
                                <?php _e('Enable automatic synchronization to detect changes', 'as24-motors-sync'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sync_frequency"><?php _e('Sync Frequency', 'as24-motors-sync'); ?></label>
                        </th>
                        <td>
                            <select name="sync_frequency" id="sync_frequency">
                                <option value="hourly" <?php selected($settings['sync_frequency'] ?? 'hourly', 'hourly'); ?>><?php _e('Hourly', 'as24-motors-sync'); ?></option>
                                <option value="twicedaily" <?php selected($settings['sync_frequency'] ?? '', 'twicedaily'); ?>><?php _e('Twice Daily', 'as24-motors-sync'); ?></option>
                                <option value="daily" <?php selected($settings['sync_frequency'] ?? '', 'daily'); ?>><?php _e('Daily', 'as24-motors-sync'); ?></option>
                            </select>
                            <p class="description"><?php _e('How often to check for changes and update listings', 'as24-motors-sync'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="as24_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'as24-motors-sync'); ?>" />
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Data Integrity Tab -->
        <?php if ($active_tab === 'integrity'): ?>
            <div class="as24-tab-content">
                <h3><?php _e('Duplicate Prevention', 'as24-motors-sync'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Auto Cleanup Duplicates', 'as24-motors-sync'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="duplicate_cleanup" value="1" <?php checked($settings['duplicate_cleanup'] ?? true, true); ?> />
                                <?php _e('Automatically remove duplicate listings during daily cleanup', 'as24-motors-sync'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Orphan Cleanup', 'as24-motors-sync'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Auto Remove Orphans', 'as24-motors-sync'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="orphan_cleanup" value="1" <?php checked($settings['orphan_cleanup'] ?? true, true); ?> />
                                <?php _e('Automatically trash listings removed from AutoScout24', 'as24-motors-sync'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="orphan_retention_days"><?php _e('Trash Retention Period', 'as24-motors-sync'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="orphan_retention_days" id="orphan_retention_days" 
                                   value="<?php echo esc_attr($settings['orphan_retention_days'] ?? 30); ?>" 
                                   min="1" max="90" /> <?php _e('days', 'as24-motors-sync'); ?>
                            <p class="description">
                                <?php _e('Number of days to keep listings in trash before permanent deletion (default: 30)', 'as24-motors-sync'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="as24_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'as24-motors-sync'); ?>" />
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Logs Tab -->
        <?php if ($active_tab === 'logs'): ?>
            <div class="as24-tab-content">
                <h3><?php _e('Log Files', 'as24-motors-sync'); ?></h3>
                
                <div class="as24-log-controls">
                    <label for="as24-log-type"><?php _e('Log Type:', 'as24-motors-sync'); ?></label>
                    <select id="as24-log-type">
                        <option value="general"><?php _e('General', 'as24-motors-sync'); ?></option>
                        <option value="import"><?php _e('Import', 'as24-motors-sync'); ?></option>
                        <option value="sync"><?php _e('Sync', 'as24-motors-sync'); ?></option>
                        <option value="cleanup"><?php _e('Cleanup', 'as24-motors-sync'); ?></option>
                        <option value="duplicate"><?php _e('Duplicate', 'as24-motors-sync'); ?></option>
                        <option value="orphan"><?php _e('Orphan', 'as24-motors-sync'); ?></option>
                        <option value="error"><?php _e('Error', 'as24-motors-sync'); ?></option>
                    </select>
                    
                    <label for="log-level"><?php _e('Level:', 'as24-motors-sync'); ?></label>
                    <select id="as24-log-level">
                        <option value=""><?php _e('All Levels', 'as24-motors-sync'); ?></option>
                        <option value="INFO"><?php _e('INFO', 'as24-motors-sync'); ?></option>
                        <option value="WARNING"><?php _e('WARNING', 'as24-motors-sync'); ?></option>
                        <option value="ERROR"><?php _e('ERROR', 'as24-motors-sync'); ?></option>
                        <option value="DEBUG"><?php _e('DEBUG', 'as24-motors-sync'); ?></option>
                    </select>
                    
                    <button type="button" class="button button-secondary as24-refresh-logs">
                        <span class="dashicons dashicons-update"></span> <?php _e('Refresh', 'as24-motors-sync'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary as24-clear-logs">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Clear Logs', 'as24-motors-sync'); ?>
                    </button>
                </div>
                
                <div class="as24-log-viewer" id="as24-log-viewer">
                    <pre class="as24-log-content" id="as24-log-content"></pre>
                </div>
                
                <p class="description">
                    <?php _e('Logs are stored in:', 'as24-motors-sync'); ?>
                    <code><?php echo wp_upload_dir()['basedir'] . '/as24-motors-sync-logs/'; ?></code>
                </p>
            </div>
        <?php endif; ?>
    </form>
    
    <?php if ($active_tab === 'connection'): ?>
        <div class="as24-card" style="margin-top: 20px;">
            <h3><?php _e('Setup Instructions', 'as24-motors-sync'); ?></h3>
            <ol>
                <li><?php _e('Enter your AutoScout24 API credentials above', 'as24-motors-sync'); ?></li>
                <li><?php _e('Click "Test Connection" to verify credentials', 'as24-motors-sync'); ?></li>
                <li><?php _e('Save your settings', 'as24-motors-sync'); ?></li>
                <li><?php _e('Go to the Dashboard to start importing', 'as24-motors-sync'); ?></li>
            </ol>
            
            <h4><?php _e('Need API Credentials?', 'as24-motors-sync'); ?></h4>
            <p><?php _e('Contact AutoScout24 support to obtain your API username and password.', 'as24-motors-sync'); ?></p>
        </div>
    <?php endif; ?>
</div>

