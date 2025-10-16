<?php
/**
 * File-Based Logging System
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Logger {
    
    private static $log_dir;
    private static $max_file_size = 10485760; // 10MB
    private static $retention_days = 7;
    
    public static function init() {
        $upload_dir = wp_upload_dir();
        self::$log_dir = $upload_dir['basedir'] . '/as24-motors-sync-logs';
        
        if (!file_exists(self::$log_dir)) {
            wp_mkdir_p(self::$log_dir);
            self::protect_logs();
        }
        
        self::cleanup_old_logs();
    }
    
    private static function protect_logs() {
        file_put_contents(self::$log_dir . '/.htaccess', "Deny from all\n");
        file_put_contents(self::$log_dir . '/index.php', "<?php\n// Silence is golden.\n");
    }
    
    public static function info($message, $type = 'general', $context = array()) {
        self::log('INFO', $message, $type, $context);
    }
    
    public static function warning($message, $type = 'general', $context = array()) {
        self::log('WARNING', $message, $type, $context);
    }
    
    public static function error($message, $type = 'general', $context = array()) {
        self::log('ERROR', $message, $type, $context);
    }
    
    public static function debug($message, $type = 'general', $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log('DEBUG', $message, $type, $context);
        }
    }
    
    private static function log($level, $message, $type, $context = array()) {
        self::init();
        
        $log_file = self::get_log_file($type);
        
        if (file_exists($log_file) && filesize($log_file) > self::$max_file_size) {
            self::rotate_log($log_file);
        }
        
        $timestamp = current_time('mysql');
        $context_str = !empty($context) ? ' ' . json_encode($context) : '';
        $log_entry = sprintf("[%s] [%s] %s%s\n", $timestamp, $level, $message, $context_str);
        
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        if ($level === 'ERROR') {
            error_log("AS24 Motors Sync [{$type}]: {$message}");
        }
    }
    
    private static function get_log_file($type) {
        return self::$log_dir . '/as24-' . $type . '.log';
    }
    
    private static function rotate_log($log_file) {
        $rotated_file = $log_file . '.' . date('Y-m-d-His') . '.old';
        rename($log_file, $rotated_file);
    }
    
    private static function cleanup_old_logs() {
        if (!file_exists(self::$log_dir)) {
            return;
        }
        
        $cutoff_time = time() - (self::$retention_days * DAY_IN_SECONDS);
        $files = glob(self::$log_dir . '/*.log*');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
    
    public static function get_recent_logs($type = 'general', $lines = 100, $level = null) {
        self::init();
        
        $log_file = self::get_log_file($type);
        
        if (!file_exists($log_file)) {
            return array();
        }
        
        $file = new SplFileObject($log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $last_line = $file->key();
        $start_line = max(0, $last_line - $lines);
        
        $log_entries = array();
        $file->seek($start_line);
        
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                if ($level === null || strpos($line, "[{$level}]") !== false) {
                    $log_entries[] = $line;
                }
            }
            $file->next();
        }
        
        return array_reverse($log_entries);
    }
    
    public static function clear_logs($type = 'all') {
        self::init();
        
        if ($type === 'all') {
            $files = glob(self::$log_dir . '/*.log*');
            foreach ($files as $file) {
                if (basename($file) !== 'index.php' && basename($file) !== '.htaccess') {
                    unlink($file);
                }
            }
            return true;
        } else {
            $log_file = self::get_log_file($type);
            if (file_exists($log_file)) {
                unlink($log_file);
                return true;
            }
        }
        
        return false;
    }
    
    public static function get_log_stats() {
        self::init();
        
        $stats = array();
        $files = glob(self::$log_dir . '/*.log');
        
        foreach ($files as $file) {
            $type = str_replace(array('as24-', '.log'), '', basename($file));
            $stats[$type] = array(
                'size' => filesize($file),
                'size_formatted' => size_format(filesize($file)),
                'modified' => filemtime($file),
                'modified_formatted' => date('Y-m-d H:i:s', filemtime($file))
            );
        }
        
        return $stats;
    }
}

