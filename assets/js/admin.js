/**
 * Admin JavaScript - Real-time Updates and Interactions
 * 
 * @package AS24_Motors_Sync
 * @version 2.0.0
 */

(function($) {
    'use strict';
    
    const AS24 = {
        // Track image processing polling
        imageProcessingInterval: null,
        
        checkImageProcessing: function(postId) {
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_check_image_processing',
                    nonce: AS24_Admin.nonce,
                    post_id: postId
                },
                success: (response) => {
                    if (response.success) {
                        const status = response.data;
                        const percent = Math.round((status.processed / status.total) * 100);
                        
                        // Update progress bar if exists
                        if ($('.as24-image-progress').length === 0) {
                            $('.postbox-header').after(`
                                <div class="as24-image-progress" style="margin: 10px; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                                    <div class="as24-image-progress-text">
                                        Processing images: ${status.processed} of ${status.total} (${percent}%)
                                        ${status.failed > 0 ? `, Failed: ${status.failed}` : ''}
                                    </div>
                                    <div class="as24-image-progress-bar" style="margin-top: 5px; height: 20px; background: #e0e0e0; border-radius: 10px; overflow: hidden;">
                                        <div class="as24-image-progress-fill" style="width: ${percent}%; height: 100%; background: #2271b1; transition: width 0.3s ease;"></div>
                                    </div>
                                </div>
                            `);
                        } else {
                            $('.as24-image-progress-text').text(
                                `Processing images: ${status.processed} of ${status.total} (${percent}%)` +
                                (status.failed > 0 ? `, Failed: ${status.failed}` : '')
                            );
                            $('.as24-image-progress-fill').css('width', percent + '%');
                        }
                        
                        // Continue polling
                        setTimeout(() => this.checkImageProcessing(postId), 2000);
                    } else {
                        // Processing complete or no active processing
                        if (this.imageProcessingInterval) {
                            clearInterval(this.imageProcessingInterval);
                            this.imageProcessingInterval = null;
                        }
                        $('.as24-image-progress').fadeOut(() => {
                            $('.as24-image-progress').remove();
                        });
                    }
                }
            });
        },
        
        init: function() {
            console.log('AS24: Initializing plugin JavaScript');
            
            // Check for active image processing on listing edit page
            const postId = $('#post_ID').val();
            if (postId) {
                this.checkImageProcessing(postId);
            }
            
            // Check if AS24_Admin is defined
            if (typeof AS24_Admin === 'undefined') {
                console.error('AS24: AS24_Admin object not found! Script may not be localized.');
                return;
            }
            
            console.log('AS24: ajaxurl =', AS24_Admin.ajaxurl);
            console.log('AS24: nonce =', AS24_Admin.nonce ? 'Set' : 'Missing');
            
            this.bindEvents();
            this.initLogViewer();
            
            // Auto-refresh stats every 30 seconds
            setInterval(() => this.refreshStats(), 30000);
            
            console.log('AS24: Initialization complete');
        },
        
        bindEvents: function() {
            // Quick Actions - Unified Sync & Import
            $('.as24-sync-now').on('click', () => this.syncAndImport());
            $('.as24-scan-duplicates').on('click', () => this.scanDuplicates());
            $('.as24-check-orphans').on('click', () => this.checkOrphans());
            $('.as24-refresh-stats').on('click', () => this.refreshStats());
            $('.as24-remove-duplicates').on('click', () => this.removeDuplicates());
            $('.as24-trash-orphans').on('click', () => this.trashOrphans());
            $('.as24-empty-trash').on('click', () => this.emptyTrash());
            $('.as24-cleanup-non-as24').on('click', () => this.cleanupNonAS24());
            
            // Connection test
            $('.as24-test-connection').on('click', () => this.testConnection());
            
            // Log viewer
            $('.as24-refresh-logs').on('click', () => this.loadLogs());
            $('.as24-clear-logs').on('click', () => this.clearLogs());
            $('#as24-log-type, #as24-log-level').on('change', () => this.loadLogs());
            
            // Alert dismissal
            $('.as24-dismiss-alert').on('click', function() {
                $(this).closest('.as24-alert').fadeOut();
            });
        },
        
        syncAndImport: function() {
            console.log('AS24: Sync & Import button clicked');
            
            const $btn = $('.as24-sync-now');
            
            if ($btn.prop('disabled')) {
                console.log('AS24: Button is disabled');
                this.showToast('Please configure API credentials first', 'error');
                return;
            }
            
            this.setButtonLoading($btn, true);
            this.showProgress('Starting synchronization...');
            
            console.log('AS24: Starting background sync');
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_sync_now',
                    nonce: AS24_Admin.nonce
                },
                success: (response) => {
                    console.log('AS24: Start sync response:', response);
                    
                    if (response.success) {
                        // Start polling for status
                        this.pollSyncStatus();
                    } else {
                        const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                        console.error('AS24: Failed to start sync:', errorMsg);
                        this.showToast('Failed to start sync: ' + errorMsg, 'error');
                        this.hideProgress();
                        this.setButtonLoading($btn, false);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AS24: AJAX error:', {xhr, status, error});
                    this.showToast('Failed to start sync: ' + error, 'error');
                    this.hideProgress();
                    this.setButtonLoading($btn, false);
                }
            });
        },
        
        pollSyncStatus: function() {
            console.log('AS24: Polling sync status');
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_sync_now',
                    nonce: AS24_Admin.nonce,
                    check_status: true
                },
                success: (response) => {
                    if (!response.success) {
                        console.error('AS24: Status check failed:', response);
                        this.showToast('Failed to check sync status', 'error');
                        this.hideProgress();
                        this.setButtonLoading($('.as24-sync-now'), false);
                        return;
                    }
                    
                    const status = response.data;
                    console.log('AS24: Status update:', status);
                    
                    if (status.is_running) {
                        // Update progress
                        this.updateProgress(status.progress, status.message);
                        
                        // Poll again in 2 seconds
                        setTimeout(() => this.pollSyncStatus(), 2000);
                    } else {
                        // Sync complete
                        if (status.stats) {
                            const stats = status.stats;
                            let message = 'Sync complete! ';
                            if (stats.added > 0) message += `Added: ${stats.added} `;
                            if (stats.updated > 0) message += `Updated: ${stats.updated} `;
                            if (stats.errors > 0) message += `Errors: ${stats.errors} `;
                            
                            this.showToast(message, 'success');
                            this.updateProgressComplete({
                                added: stats.added,
                                updated: stats.updated,
                                errors: stats.errors
                            });
                        } else {
                            this.showToast(status.message, 'success');
                            this.hideProgress();
                        }
                        
                        this.setButtonLoading($('.as24-sync-now'), false);
                        this.refreshStats();
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AS24: Status check error:', error);
                    this.showToast('Failed to check sync status: ' + error, 'error');
                    this.hideProgress();
                    this.setButtonLoading($('.as24-sync-now'), false);
                }
            });
        },
        
        scanDuplicates: function() {
            const $btn = $('.as24-scan-duplicates');
            this.setButtonLoading($btn, true);
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_scan_duplicates',
                    nonce: AS24_Admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const count = response.data.total_duplicate_listings;
                        if (count > 0) {
                            this.showToast(`Found ${count} duplicate listings`, 'info');
                            $('#duplicate-count').text(count);
                        } else {
                            this.showToast('No duplicates found!', 'success');
                            $('#duplicate-count').text('0');
                        }
                    }
                },
                complete: () => {
                    this.setButtonLoading($btn, false);
                }
            });
        },
        
        removeDuplicates: function() {
            if (!confirm(AS24_Admin.strings.confirm_cleanup)) {
                return;
            }
            
            const $btn = $(event.target).closest('button');
            this.setButtonLoading($btn, true);
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_remove_duplicates',
                    nonce: AS24_Admin.nonce,
                    dry_run: 'false'
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(`Removed ${response.data.total_removed} duplicates`, 'success');
                        this.refreshStats();
                    }
                },
                complete: () => {
                    this.setButtonLoading($btn, false);
                }
            });
        },
        
        checkOrphans: function() {
            const $btn = $('.as24-check-orphans');
            this.setButtonLoading($btn, true);
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_check_orphans',
                    nonce: AS24_Admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const count = response.data.orphans_detected;
                        if (count > 0) {
                            this.showToast(`Found ${count} orphaned listings`, 'info');
                            $('#orphan-count').text(count);
                        } else {
                            this.showToast('No orphaned listings found!', 'success');
                            $('#orphan-count').text('0');
                        }
                    }
                },
                complete: () => {
                    this.setButtonLoading($btn, false);
                }
            });
        },
        
        trashOrphans: function() {
            if (!confirm(AS24_Admin.strings.confirm_orphan)) {
                return;
            }
            
            const $btn = $(event.target).closest('button');
            this.setButtonLoading($btn, true);
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_trash_orphans',
                    nonce: AS24_Admin.nonce,
                    dry_run: 'false'
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(`Moved ${response.data.trashed} orphaned listings to trash`, 'success');
                        this.refreshStats();
                    }
                },
                complete: () => {
                    this.setButtonLoading($btn, false);
                }
            });
        },
        
        cleanupNonAS24: function() {
            if (!confirm('Remove all listings that are not from AutoScout24? They will be moved to trash.')) {
                return;
            }
            
            const $btn = $('.as24-cleanup-non-as24');
            this.setButtonLoading($btn, true);
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_cleanup_non_as24',
                    nonce: AS24_Admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(`Moved ${response.data.removed} non-AS24 listings to trash`, 'success');
                        this.refreshStats();
                    }
                },
                complete: () => {
                    this.setButtonLoading($btn, false);
                }
            });
        },
        
        emptyTrash: function() {
            if (!confirm('Permanently delete all listings in trash? This cannot be undone!')) {
                return;
            }
            
            const $btn = $('.as24-empty-trash');
            this.setButtonLoading($btn, true);
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_cleanup_trash',
                    nonce: AS24_Admin.nonce,
                    dry_run: 'false'
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(`Permanently deleted ${response.data.deleted} listings`, 'success');
                        this.refreshStats();
                    }
                },
                complete: () => {
                    this.setButtonLoading($btn, false);
                }
            });
        },
        
        refreshStats: function() {
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_get_stats',
                    nonce: AS24_Admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const stats = response.data;
                        $('#remote-total').text(stats.remote_total.toLocaleString());
                        $('#local-total').text(stats.local_total.toLocaleString());
                        $('#duplicate-count').text(stats.duplicates.toLocaleString());
                        $('#orphan-count').text(stats.orphaned.toLocaleString());
                        $('#trash-count').text(stats.in_trash.toLocaleString());
                    }
                }
            });
            
            // Also refresh remote count
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_refresh_remote_count',
                    nonce: AS24_Admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#remote-total').text(response.data.total.toLocaleString());
                    }
                }
            });
        },
        
        testConnection: function() {
            const $btn = $('.as24-test-connection');
            const $result = $('#as24-connection-result');
            
            this.setButtonLoading($btn, true);
            $result.html('');
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_test_connection',
                    nonce: AS24_Admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $result.html(`
                            <div class="notice notice-success">
                                <p><strong>✓ ${response.data.message}</strong></p>
                                <p>Total listings available: ${response.data.total_listings}</p>
                            </div>
                        `);
                    } else {
                        $result.html(`
                            <div class="notice notice-error">
                                <p><strong>✗ ${response.data.message}</strong></p>
                            </div>
                        `);
                    }
                },
                complete: () => {
                    this.setButtonLoading($btn, false);
                }
            });
        },
        
        stopSync: function() {
            if (!confirm('Are you sure you want to stop the sync process?')) {
                return;
            }
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_stop_sync',
                    nonce: AS24_Admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast(response.data.message, 'success');
                        this.hideProgress();
                        this.setButtonLoading($('.as24-sync-now'), false);
                        this.refreshStats();
                    } else {
                        this.showToast(response.data.message, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showToast('Failed to stop sync: ' + error, 'error');
                }
            });
        },
        
        showProgress: function(message) {
            const $section = $('.as24-progress-section');
            $section.addClass('active').show();
            $('.as24-progress-status').text(message);
            $('.as24-progress-fill').css('width', '0%');
            $('.as24-progress-percentage').text('0%');
            
            // Add stop button if not exists
            if (!$('.as24-stop-sync').length) {
                const $stopBtn = $(`
                    <button type="button" class="button button-secondary as24-stop-sync">
                        <span class="dashicons dashicons-no-alt"></span> Stop Sync
                    </button>
                `);
                $stopBtn.on('click', () => this.stopSync());
                $section.append($stopBtn);
            }
        },
        
        updateProgress: function(percentage, message) {
            $('.as24-progress-fill').css('width', percentage + '%');
            $('.as24-progress-percentage').text(percentage + '%');
            if (message) {
                $('.as24-progress-status').text(message);
            }
        },
        
        updateProgressComplete: function(data) {
            $('.as24-progress-fill').addClass('success').css('width', '100%');
            $('.as24-progress-percentage').text('100%');
            $('.as24-progress-status').text('Complete!');
            
            if (data) {
                let details = `
                    Added: ${data.added || data.imported || 0}, 
                    Updated: ${data.updated || 0}, 
                    Removed: ${data.removed || 0}
                `;
                if (data.duration) {
                    details += `, Duration: ${data.duration}s`;
                }
                $('.as24-progress-details').text(details);
            }
            
            setTimeout(() => this.hideProgress(), 5000);
        },
        
        hideProgress: function() {
            $('.as24-progress-section').fadeOut();
            $('.as24-stop-sync').remove();
        },
        
        showToast: function(message, type = 'info') {
            const icons = {
                success: 'yes-alt',
                error: 'dismiss',
                info: 'info'
            };
            
            const $toast = $(`
                <div class="as24-toast ${type}">
                    <span class="dashicons dashicons-${icons[type]}"></span>
                    <div class="as24-toast-message">${message}</div>
                    <button class="as24-toast-close">&times;</button>
                </div>
            `);
            
            $('#as24-toast-container').append($toast);
            
            $toast.find('.as24-toast-close').on('click', function() {
                $toast.fadeOut(() => $toast.remove());
            });
            
            setTimeout(() => {
                $toast.fadeOut(() => $toast.remove());
            }, 5000);
        },
        
        setButtonLoading: function($btn, loading) {
            if (loading) {
                $btn.addClass('loading').prop('disabled', true);
                $btn.data('original-text', $btn.html());
                $btn.find('span:last').text('Loading...');
            } else {
                $btn.removeClass('loading').prop('disabled', false);
                const originalText = $btn.data('original-text');
                if (originalText) {
                    $btn.html(originalText);
                }
            }
        },
        
        initLogViewer: function() {
            if ($('#as24-log-viewer').length) {
                this.loadLogs();
            }
        },
        
        loadLogs: function() {
            const type = $('#as24-log-type').val() || 'general';
            const level = $('#as24-log-level').val() || null;
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_get_logs',
                    nonce: AS24_Admin.nonce,
                    type: type,
                    lines: 100,
                    level: level
                },
                success: (response) => {
                    if (response.success) {
                        const logs = response.data.logs.join('\n');
                        $('#as24-log-content').text(logs || 'No logs found');
                    }
                }
            });
        },
        
        clearLogs: function() {
            if (!confirm('Clear all logs? This cannot be undone!')) {
                return;
            }
            
            const type = $('#as24-log-type').val() || 'all';
            
            $.ajax({
                url: AS24_Admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'as24_clear_logs',
                    nonce: AS24_Admin.nonce,
                    type: type
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast('Logs cleared', 'success');
                        this.loadLogs();
                    }
                }
            });
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(() => AS24.init());
    
})(jQuery);

