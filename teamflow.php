<?php
/**
 * Plugin Name: TeamFlow - Team Monitoring System
 * Plugin URI: https://yoursite.com/teamflow
 * Description: Comprehensive team time tracking, monitoring, and payroll management system
 * Version: 1.0.1
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * Text Domain: teamflow
 * Requires PHP: 7.4
 * Requires at least: 5.0
 */

// Security: Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TEAMFLOW_VERSION', '1.0.1');
define('TEAMFLOW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TEAMFLOW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TEAMFLOW_DEBUG', defined('WP_DEBUG') && WP_DEBUG);

// Include required files
require_once TEAMFLOW_PLUGIN_DIR . 'includes/class-teamflow-logger.php';
require_once TEAMFLOW_PLUGIN_DIR . 'includes/class-teamflow-database.php';
require_once TEAMFLOW_PLUGIN_DIR . 'includes/class-teamflow-timer.php';
require_once TEAMFLOW_PLUGIN_DIR . 'includes/class-teamflow-monitoring.php';
require_once TEAMFLOW_PLUGIN_DIR . 'includes/class-teamflow-payroll.php';
require_once TEAMFLOW_PLUGIN_DIR . 'includes/class-teamflow-ajax.php';
require_once TEAMFLOW_PLUGIN_DIR . 'includes/class-teamflow-shortcodes.php';

class TeamFlow {
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Add cleanup cron job
        if (!wp_next_scheduled('teamflow_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'teamflow_daily_cleanup');
        }
        add_action('teamflow_daily_cleanup', array($this, 'daily_cleanup'));
        
        // Initialize components
        new TeamFlow_Database();
        new TeamFlow_Timer();
        new TeamFlow_Monitoring();
        new TeamFlow_Payroll();
        new TeamFlow_AJAX();
        new TeamFlow_Shortcodes();
        
        TeamFlow_Logger::info('TeamFlow initialized successfully');
    }
    
    public function init() {
        load_plugin_textdomain('teamflow', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Add custom user roles
        $this->add_custom_roles();
    }
    
    public function activate() {
        TeamFlow_Logger::info('Activating TeamFlow plugin');
        
        // Create database tables
        TeamFlow_Database::create_tables();
        
        // Add custom roles
        $this->add_custom_roles();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        TeamFlow_Logger::info('TeamFlow activated successfully');
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('teamflow_daily_cleanup');
        wp_clear_scheduled_hook('teamflow_cleanup_screenshots');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        TeamFlow_Logger::info('TeamFlow deactivated');
    }
    
    /**
     * Daily cleanup task
     */
    public function daily_cleanup() {
        TeamFlow_Logger::info('Running daily cleanup');
        
        // Clean up old entries (optional - only if enabled)
        if (get_option('teamflow_auto_cleanup_entries', false)) {
            $days = get_option('teamflow_cleanup_days', 365);
            TeamFlow_Database::cleanup_old_entries($days);
        }
        
        // Clear expired caches
        wp_cache_flush();
    }
    
    private function add_custom_roles() {
        // Remove old roles first to update capabilities
        remove_role('team_manager');
        remove_role('team_member');
        
        // Add Team Manager role
        add_role('team_manager', __('Team Manager', 'teamflow'), array(
            'read' => true,
            'manage_team' => true,
            'view_timesheets' => true,
            'manage_payroll' => true,
            'view_monitoring' => true,
            'track_time' => true,
            'view_own_stats' => true,
        ));
        
        // Add Team Member role
        add_role('team_member', __('Team Member', 'teamflow'), array(
            'read' => true,
            'track_time' => true,
            'view_own_stats' => true,
        ));
        
        // Add capabilities to admin
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('manage_team');
            $admin->add_cap('view_timesheets');
            $admin->add_cap('manage_payroll');
            $admin->add_cap('view_monitoring');
            $admin->add_cap('track_time');
            $admin->add_cap('view_own_stats');
        }
    }
    
    private function set_default_options() {
        $defaults = array(
            'teamflow_screenshot_interval' => 5,
            'teamflow_screenshot_quality' => 'high',
            'teamflow_recording_fps' => 'ultra-low',
            'teamflow_inactivity_threshold' => 60,
            'teamflow_session_type' => 'daily',
            'teamflow_blur_sensitive' => true,
            'teamflow_auto_resume' => false,
            'teamflow_track_keyboard' => true,
            'teamflow_track_mouse' => true,
            'teamflow_track_apps' => true,
            'teamflow_track_urls' => false,
            'teamflow_pay_period' => 'monthly',
            'teamflow_tax_rate' => 20,
            'teamflow_auto_overtime' => true,
            'teamflow_low_activity_alerts' => true,
            'teamflow_idle_notifications' => true,
            'teamflow_daily_summary' => true,
            'teamflow_payroll_reminders' => true,
            'teamflow_screenshot_retention_days' => 30,
            'teamflow_auto_cleanup_entries' => false,
            'teamflow_cleanup_days' => 365,
            'teamflow_log_activity_updates' => false,
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('TeamFlow', 'teamflow'),
            __('TeamFlow', 'teamflow'),
            'manage_team',
            'teamflow',
            array($this, 'render_dashboard'),
            'dashicons-chart-line',
            30
        );
        
        // Submenu pages
        add_submenu_page('teamflow', __('Dashboard', 'teamflow'), __('Dashboard', 'teamflow'), 'manage_team', 'teamflow', array($this, 'render_dashboard'));
        add_submenu_page('teamflow', __('Team Members', 'teamflow'), __('Team', 'teamflow'), 'manage_team', 'teamflow-team', array($this, 'render_team'));
        add_submenu_page('teamflow', __('Timesheets', 'teamflow'), __('Timesheets', 'teamflow'), 'view_timesheets', 'teamflow-timesheets', array($this, 'render_timesheets'));
        add_submenu_page('teamflow', __('Payroll', 'teamflow'), __('Payroll', 'teamflow'), 'manage_payroll', 'teamflow-payroll', array($this, 'render_payroll'));
        add_submenu_page('teamflow', __('Monitoring', 'teamflow'), __('Monitoring', 'teamflow'), 'view_monitoring', 'teamflow-monitoring', array($this, 'render_monitoring'));
        add_submenu_page('teamflow', __('Settings', 'teamflow'), __('Settings', 'teamflow'), 'manage_options', 'teamflow-settings', array($this, 'render_settings'));
        
        // Employee pages (hidden from menu)
        add_submenu_page(null, __('My Time', 'teamflow'), __('My Time', 'teamflow'), 'track_time', 'teamflow-my-time', array($this, 'render_my_time'));
        add_submenu_page(null, __('My Stats', 'teamflow'), __('My Stats', 'teamflow'), 'view_own_stats', 'teamflow-my-stats', array($this, 'render_my_stats'));
        
        // Debug log viewer (admin only)
        if (TEAMFLOW_DEBUG && current_user_can('manage_options')) {
            add_submenu_page('teamflow', __('Debug Log', 'teamflow'), __('Debug Log', 'teamflow'), 'manage_options', 'teamflow-debug', array($this, 'render_debug_log'));
        }
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'teamflow') === false) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style('teamflow-admin', TEAMFLOW_PLUGIN_URL . 'assets/css/admin.css', array(), TEAMFLOW_VERSION);
        wp_enqueue_style('teamflow-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
        
        // Enqueue scripts
        wp_enqueue_script('teamflow-admin', TEAMFLOW_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-api'), TEAMFLOW_VERSION, true);
        
        // Only load Chart.js on pages that need it
        if (in_array($hook, array('toplevel_page_teamflow', 'teamflow_page_teamflow-my-stats'))) {
            wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js', array(), '4.4.0', true);
        }
        
        // Localize script
        wp_localize_script('teamflow-admin', 'teamflow', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('teamflow_nonce'),
            'current_user_id' => get_current_user_id(),
            'is_admin' => current_user_can('manage_team'),
        ));
    }
    
    public function enqueue_frontend_assets() {
        // Only enqueue on pages with shortcodes
        global $post;
        
        if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'teamflow_timer') || 
            has_shortcode($post->post_content, 'teamflow_dashboard') ||
            has_shortcode($post->post_content, 'teamflow_stats') ||
            has_shortcode($post->post_content, 'teamflow_timesheet'))) {
            
            wp_enqueue_style('teamflow-frontend', TEAMFLOW_PLUGIN_URL . 'assets/css/frontend.css', array(), TEAMFLOW_VERSION);
            wp_enqueue_style('teamflow-icons', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
            wp_enqueue_script('teamflow-frontend', TEAMFLOW_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), TEAMFLOW_VERSION, true);
            
            // Load Chart.js for stats
            if (has_shortcode($post->post_content, 'teamflow_stats') || has_shortcode($post->post_content, 'teamflow_dashboard')) {
                wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js', array(), '4.4.0', true);
            }
            
            wp_localize_script('teamflow-frontend', 'teamflow', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('teamflow_nonce'),
                'current_user_id' => get_current_user_id(),
            ));
        }
    }
    
    // Render admin pages
    public function render_dashboard() {
        include TEAMFLOW_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
    
    public function render_team() {
        include TEAMFLOW_PLUGIN_DIR . 'templates/admin/team.php';
    }
    
    public function render_timesheets() {
        include TEAMFLOW_PLUGIN_DIR . 'templates/admin/timesheets.php';
    }
    
    public function render_payroll() {
        include TEAMFLOW_PLUGIN_DIR . 'templates/admin/payroll.php';
    }
    
    public function render_monitoring() {
        include TEAMFLOW_PLUGIN_DIR . 'templates/admin/monitoring.php';
    }
    
    public function render_settings() {
        include TEAMFLOW_PLUGIN_DIR . 'templates/admin/settings.php';
    }
    
    public function render_my_time() {
        include TEAMFLOW_PLUGIN_DIR . 'templates/employee/my-time.php';
    }
    
    public function render_my_stats() {
        include TEAMFLOW_PLUGIN_DIR . 'templates/employee/my-stats.php';
    }
    
    /**
     * Debug log viewer
     */
    public function render_debug_log() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'teamflow'));
        }
        
        // Handle log clear
        if (isset($_POST['clear_log']) && check_admin_referer('teamflow_clear_log')) {
            TeamFlow_Logger::clear_log();
            echo '<div class="notice notice-success"><p>Log cleared successfully</p></div>';
        }
        
        $log_content = TeamFlow_Logger::get_log(500);
        ?>
        <div class="wrap">
            <h1><?php _e('TeamFlow Debug Log', 'teamflow'); ?></h1>
            
            <form method="post" style="margin: 20px 0;">
                <?php wp_nonce_field('teamflow_clear_log'); ?>
                <button type="submit" name="clear_log" class="button button-secondary">
                    <i class="fas fa-trash"></i> Clear Log
                </button>
                <button type="button" onclick="location.reload();" class="button">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </form>
            
            <div style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px; max-height: 600px; overflow-y: auto;">
                <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($log_content); ?></pre>
            </div>
            
            <p style="margin-top: 20px; color: #666;">
                <strong>Note:</strong> Debug logging is enabled. To disable, set <code>WP_DEBUG</code> to <code>false</code> in <code>wp-config.php</code>.
            </p>
        </div>
        <?php
    }
}

// Initialize plugin
function teamflow_init() {
    return TeamFlow::get_instance();
}
add_action('plugins_loaded', 'teamflow_init');