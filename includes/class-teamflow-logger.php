<?php
/**
 * TeamFlow Logger
 * Path: includes/class-teamflow-logger.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class TeamFlow_Logger {
    
    const LOG_FILE = 'teamflow-debug.log';
    
    /**
     * Log a message
     */
    public static function log($message, $level = 'INFO') {
        if (!defined('TEAMFLOW_DEBUG') || !TEAMFLOW_DEBUG) {
            return;
        }
        
        $log_file = WP_CONTENT_DIR . '/' . self::LOG_FILE;
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf("[%s] [%s] %s\n", $timestamp, $level, $message);
        
        // Ensure log directory is writable
        if (!is_writable(WP_CONTENT_DIR)) {
            error_log('TeamFlow: Content directory not writable');
            return;
        }
        
        error_log($log_entry, 3, $log_file);
        
        // Rotate log if too large (> 5MB)
        if (file_exists($log_file) && filesize($log_file) > 5 * 1024 * 1024) {
            self::rotate_log($log_file);
        }
    }
    
    /**
     * Log error
     */
    public static function error($message) {
        self::log($message, 'ERROR');
    }
    
    /**
     * Log warning
     */
    public static function warning($message) {
        self::log($message, 'WARNING');
    }
    
    /**
     * Log info
     */
    public static function info($message) {
        self::log($message, 'INFO');
    }
    
    /**
     * Log debug
     */
    public static function debug($message) {
        self::log($message, 'DEBUG');
    }
    
    /**
     * Rotate log file
     */
    private static function rotate_log($log_file) {
        $backup = $log_file . '.' . time() . '.bak';
        
        if (rename($log_file, $backup)) {
            // Keep only last 5 backups
            $backups = glob($log_file . '.*.bak');
            if (count($backups) > 5) {
                usort($backups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                // Delete oldest
                $to_delete = array_slice($backups, 0, count($backups) - 5);
                foreach ($to_delete as $old_backup) {
                    @unlink($old_backup);
                }
            }
        }
    }
    
    /**
     * Get log contents
     */
    public static function get_log($lines = 100) {
        $log_file = WP_CONTENT_DIR . '/' . self::LOG_FILE;
        
        if (!file_exists($log_file)) {
            return 'No log file found.';
        }
        
        $file = new SplFileObject($log_file);
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key() + 1;
        
        $start_line = max(0, $total_lines - $lines);
        
        $log_lines = array();
        $file->seek($start_line);
        
        while (!$file->eof()) {
            $line = $file->fgets();
            if (!empty(trim($line))) {
                $log_lines[] = $line;
            }
        }
        
        return implode('', $log_lines);
    }
    
    /**
     * Clear log
     */
    public static function clear_log() {
        $log_file = WP_CONTENT_DIR . '/' . self::LOG_FILE;
        
        if (file_exists($log_file)) {
            return @unlink($log_file);
        }
        
        return true;
    }
}
