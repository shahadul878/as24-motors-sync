<?php
/**
 * Image Handler - Efficient Image Import and Deduplication
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Image_Handler {
    
    /**
     * Import images for a listing
     */
    public static function import_images($post_id, $images) {
        if (empty($images) || !is_array($images)) {
            return;
        }
        
        // Store images for background processing
        $image_queue = array();
        foreach ($images as $index => $image) {
            $image_url = self::get_best_image_url($image);
            if (!empty($image_url)) {
                $image_queue[] = array(
                    'url' => $image_url,
                    'index' => $index
                );
            }
        }
        
        if (empty($image_queue)) {
            return;
        }
        
        // Store image queue for background processing
        update_post_meta($post_id, '_as24_image_queue', $image_queue);
        update_post_meta($post_id, '_as24_image_queue_status', array(
            'total' => count($image_queue),
            'processed' => 0,
            'failed' => 0,
            'image_ids' => array()
        ));
        
        // Schedule background processing
        if (!wp_next_scheduled('as24_process_image_queue', array($post_id))) {
            wp_schedule_single_event(time(), 'as24_process_image_queue', array($post_id));
        }
        
        return array(
            'status' => 'queued',
            'total_images' => count($image_queue)
        );
    }
    
    /**
     * Process image queue in background
     */
    public static function process_image_queue($post_id) {
        $image_queue = get_post_meta($post_id, '_as24_image_queue', true);
        $queue_status = get_post_meta($post_id, '_as24_image_queue_status', true);
        
        if (empty($image_queue) || empty($queue_status)) {
            return;
        }
        
        // Process next image in queue
        $image = array_shift($image_queue);
        
        // Check if image already exists
        $attachment_id = self::get_existing_attachment($image['url']);
        
        if (!$attachment_id) {
            // Import new image
            $attachment_id = self::import_single_image($image['url'], $post_id);
        } else {
            // Update parent if needed
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_parent' => $post_id
            ));
        }
        
        if ($attachment_id && !is_wp_error($attachment_id)) {
            $queue_status['image_ids'][] = $attachment_id;
            
            // Set first image as featured
            if ($image['index'] === 0) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        } else {
            $queue_status['failed']++;
        }
        
        $queue_status['processed']++;
        
        // Update queue and status
        if (!empty($image_queue)) {
            update_post_meta($post_id, '_as24_image_queue', $image_queue);
            update_post_meta($post_id, '_as24_image_queue_status', $queue_status);
            
            // Schedule next image processing
            wp_schedule_single_event(time() + 1, 'as24_process_image_queue', array($post_id));
        } else {
            // Queue completed, update gallery
            if (!empty($queue_status['image_ids'])) {
                // Store as comma-separated string (legacy format)
                update_post_meta($post_id, '_gallery_images', implode(',', $queue_status['image_ids']));
                
                // Store as serialized array (WordPress native format)
                update_post_meta($post_id, 'gallery', $queue_status['image_ids']);
                
                // Store in ACF format if ACF is active
                if (function_exists('update_field')) {
                    update_field('gallery_images', $queue_status['image_ids'], $post_id);
                }
            }
            
            // Cleanup
            delete_post_meta($post_id, '_as24_image_queue');
            delete_post_meta($post_id, '_as24_image_queue_status');
        }
        
        // Store all image IDs as gallery in multiple formats for maximum compatibility
        if (!empty($image_ids)) {
            // Store as comma-separated string (legacy format)
            update_post_meta($post_id, '_gallery_images', implode(',', $image_ids));
            
            // Store as serialized array (WordPress native format)
            update_post_meta($post_id, 'gallery', $image_ids);
            
            // Store in ACF format if ACF is active
            if (function_exists('update_field')) {
                update_field('gallery_images', $image_ids, $post_id);
            }
            
            // Update post content to include gallery shortcode
           
        }
        
        AS24_Logger::debug(sprintf('Imported %d images for listing %d', count($image_ids), $post_id), 'import');
        
        return $image_ids;
    }
    
    /**
     * Get best quality image URL
     */
    private static function get_best_image_url($image) {
        // Prefer WebP format at 1280x960
        if (!empty($image['formats']['webp']['size1280x960'])) {
            return $image['formats']['webp']['size1280x960'];
        }
        
        // Fallback to JPG at 1280x960
        if (!empty($image['formats']['jpg']['size1280x960'])) {
            return $image['formats']['jpg']['size1280x960'];
        }
        
        // Fallback to 800x600 WebP
        if (!empty($image['formats']['webp']['size800x600'])) {
            return $image['formats']['webp']['size800x600'];
        }
        
        // Fallback to 800x600 JPG
        if (!empty($image['formats']['jpg']['size800x600'])) {
            return $image['formats']['jpg']['size800x600'];
        }
        
        return null;
    }
    
    /**
     * Check if image already exists in media library
     */
    private static function get_existing_attachment($image_url) {
        global $wpdb;
        
        $attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1",
            $image_url
        ));
        
        return $attachment ? (int)$attachment : false;
    }
    
    /**
     * Import single image
     */
    private static function import_single_image($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            AS24_Logger::error('Failed to download image: ' . $tmp->get_error_message(), 'import');
            return $tmp;
        }
        
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );
        
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            AS24_Logger::error('Failed to sideload image: ' . $attachment_id->get_error_message(), 'import');
            return $attachment_id;
        }
        
        @unlink($tmp);
        
        return $attachment_id;
    }
}

