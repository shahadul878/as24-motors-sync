<?php
/**
 * Progress Tracker - Import Progress UI
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Progress_Tracker {
    
    /**
     * Get current import progress
     */
    public static function get_progress() {
        $import_state = get_option('as24_import_state');
        
        if (!$import_state) {
            return array(
                'status' => 'idle',
                'progress' => 0,
                'message' => 'No import in progress'
            );
        }
        
        $progress = 0;
        $message = '';
        
        if ($import_state['status'] === 'running') {
            $progress = round(($import_state['current_page'] / $import_state['total_pages']) * 100, 1);
            $message = sprintf(
                'Processing page %d of %d (%s%%)<br>Imported: %d, Updated: %d, Skipped: %d, Errors: %d',
                $import_state['current_page'],
                $import_state['total_pages'],
                $progress,
                $import_state['imported'],
                $import_state['updated'],
                $import_state['skipped'],
                $import_state['errors']
            );
        } elseif ($import_state['status'] === 'completed') {
            $progress = 100;
            $message = sprintf(
                'Import completed in %ss<br>Imported: %d, Updated: %d, Skipped: %d, Errors: %d',
                $import_state['duration'],
                $import_state['imported'],
                $import_state['updated'],
                $import_state['skipped'],
                $import_state['errors']
            );
        }
        
        return array(
            'status' => $import_state['status'],
            'progress' => $progress,
            'message' => $message
        );
    }
    
    /**
     * Output progress HTML
     */
    public static function render_progress() {
        $progress = self::get_progress();
        ?>
        <div class="as24-progress-wrapper">
            <div class="as24-progress-bar">
                <div class="as24-progress-fill" style="width: <?php echo esc_attr($progress['progress']); ?>%"></div>
            </div>
            <div class="as24-progress-status">
                <?php echo wp_kses_post($progress['message']); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add AJAX endpoint for progress updates
     */
    public static function init() {
        add_action('wp_ajax_as24_get_import_progress', array(__CLASS__, 'ajax_get_progress'));
    }
    
    /**
     * AJAX handler for progress updates
     */
    public static function ajax_get_progress() {
        wp_send_json(self::get_progress());
    }
}

// Initialize progress tracker
add_action('init', array('AS24_Progress_Tracker', 'init'));
