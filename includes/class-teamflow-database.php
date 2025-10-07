<?php
/**
 * TeamFlow Database Management - HARDENED VERSION
 * Path: includes/class-teamflow-database.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class TeamFlow_Database {
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Time entries table with unique constraint
        $table_time_entries = $wpdb->prefix . 'teamflow_time_entries';
        $sql_time_entries = "CREATE TABLE $table_time_entries (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            project_id bigint(20),
            task_name varchar(255) NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime,
            elapsed_seconds int(11) DEFAULT 0,
            idle_seconds int(11) DEFAULT 0,
            activity_level int(3) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            is_manual tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY project_id (project_id),
            KEY start_time (start_time),
            KEY status (status),
            KEY user_status (user_id, status),
            KEY user_date (user_id, start_time)
        ) $charset_collate;";
        dbDelta($sql_time_entries);
        
        // Screenshots table
        $table_screenshots = $wpdb->prefix . 'teamflow_screenshots';
        $sql_screenshots = "CREATE TABLE $table_screenshots (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            time_entry_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            file_path varchar(500) NOT NULL,
            thumbnail_path varchar(500),
            activity_level int(3) DEFAULT 0,
            captured_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY time_entry_id (time_entry_id),
            KEY user_id (user_id),
            KEY captured_at (captured_at)
        ) $charset_collate;";
        dbDelta($sql_screenshots);
        
        // Activity logs table
        $table_activity_logs = $wpdb->prefix . 'teamflow_activity_logs';
        $sql_activity_logs = "CREATE TABLE $table_activity_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            time_entry_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            activity_type varchar(50) NOT NULL,
            activity_data text,
            recorded_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY time_entry_id (time_entry_id),
            KEY user_id (user_id),
            KEY activity_type (activity_type),
            KEY recorded_at (recorded_at)
        ) $charset_collate;";
        dbDelta($sql_activity_logs);
        
        // Projects table
        $table_projects = $wpdb->prefix . 'teamflow_projects';
        $sql_projects = "CREATE TABLE $table_projects (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            client_name varchar(255),
            hourly_rate decimal(10,2),
            status varchar(20) DEFAULT 'active',
            created_by bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by)
        ) $charset_collate;";
        dbDelta($sql_projects);
        
        // Payroll records table
        $table_payroll = $wpdb->prefix . 'teamflow_payroll';
        $sql_payroll = "CREATE TABLE $table_payroll (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            period_start date NOT NULL,
            period_end date NOT NULL,
            hours_worked decimal(10,2) DEFAULT 0,
            hourly_rate decimal(10,2) DEFAULT 0,
            gross_pay decimal(10,2) DEFAULT 0,
            deductions decimal(10,2) DEFAULT 0,
            net_pay decimal(10,2) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            processed_at datetime,
            processed_by bigint(20),
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY period_start (period_start),
            KEY status (status),
            UNIQUE KEY user_period (user_id, period_start, period_end)
        ) $charset_collate;";
        dbDelta($sql_payroll);
        
        // User settings table
        $table_settings = $wpdb->prefix . 'teamflow_user_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_setting (user_id, setting_key),
            KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_settings);
        
        // Insert default projects
        self::insert_default_projects();
    }
    
    private static function insert_default_projects() {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_projects';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count == 0) {
            $projects = array(
                array('name' => 'Project Alpha', 'description' => 'Main development project', 'status' => 'active'),
                array('name' => 'Project Beta', 'description' => 'Secondary development project', 'status' => 'active'),
                array('name' => 'Internal Development', 'description' => 'Internal tools and systems', 'status' => 'active'),
                array('name' => 'Client Work', 'description' => 'General client projects', 'status' => 'active'),
                array('name' => 'Research', 'description' => 'Research and development', 'status' => 'active'),
            );
            
            foreach ($projects as $project) {
                $wpdb->insert($table, $project);
            }
        }
    }
    
    public static function get_time_entries($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        $defaults = array(
            'user_id' => null,
            'project_id' => null,
            'status' => null,
            'start_date' => null,
            'end_date' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'start_time',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clauses with prepared statements
        $where = array('1=1');
        $prepare_args = array();
        
        if ($args['user_id']) {
            $where[] = 'user_id = %d';
            $prepare_args[] = absint($args['user_id']);
        }
        
        if ($args['project_id']) {
            $where[] = 'project_id = %d';
            $prepare_args[] = absint($args['project_id']);
        }
        
        if ($args['status']) {
            $where[] = 'status = %s';
            $prepare_args[] = sanitize_text_field($args['status']);
        }
        
        if ($args['start_date']) {
            $where[] = 'start_time >= %s';
            $prepare_args[] = sanitize_text_field($args['start_date']);
        }
        
        if ($args['end_date']) {
            $where[] = 'start_time <= %s';
            $prepare_args[] = sanitize_text_field($args['end_date']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Whitelist orderby to prevent SQL injection
        $allowed_orderby = array('start_time', 'elapsed_seconds', 'activity_level', 'id', 'end_time');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'start_time';
        
        // Whitelist order direction
        $allowed_order = array('ASC', 'DESC');
        $order = in_array(strtoupper($args['order']), $allowed_order) ? strtoupper($args['order']) : 'DESC';
        
        // Add limit and offset to prepare args
        $prepare_args[] = absint($args['limit']);
        $prepare_args[] = absint($args['offset']);
        
        $sql = "SELECT * FROM $table 
                WHERE $where_clause 
                ORDER BY $orderby $order 
                LIMIT %d OFFSET %d";
        
        if (empty($prepare_args)) {
            return $wpdb->get_results($sql);
        }
        
        return $wpdb->get_results($wpdb->prepare($sql, $prepare_args));
    }
    
    public static function insert_time_entry($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        $defaults = array(
            'user_id' => get_current_user_id(),
            'task_name' => '',
            'start_time' => current_time('mysql'),
            'status' => 'active',
            'elapsed_seconds' => 0,
            'idle_seconds' => 0,
            'activity_level' => 0,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitize data
        $insert_data = array(
            'user_id' => absint($data['user_id']),
            'project_id' => isset($data['project_id']) ? absint($data['project_id']) : null,
            'task_name' => sanitize_text_field($data['task_name']),
            'start_time' => sanitize_text_field($data['start_time']),
            'status' => sanitize_text_field($data['status']),
            'elapsed_seconds' => absint($data['elapsed_seconds']),
            'idle_seconds' => absint($data['idle_seconds']),
            'activity_level' => absint($data['activity_level']),
        );
        
        $result = $wpdb->insert($table, $insert_data);
        
        if ($result === false) {
            TeamFlow_Logger::error("Failed to insert time entry: " . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    public static function update_time_entry($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        // Sanitize update data
        $update_data = array();
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        if (isset($data['end_time'])) {
            $update_data['end_time'] = sanitize_text_field($data['end_time']);
        }
        
        if (isset($data['elapsed_seconds'])) {
            $update_data['elapsed_seconds'] = absint($data['elapsed_seconds']);
        }
        
        if (isset($data['idle_seconds'])) {
            $update_data['idle_seconds'] = absint($data['idle_seconds']);
        }
        
        if (isset($data['activity_level'])) {
            $update_data['activity_level'] = min(100, max(0, absint($data['activity_level'])));
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update($table, $update_data, array('id' => absint($id)));
        
        if ($result === false) {
            TeamFlow_Logger::error("Failed to update time entry $id: " . $wpdb->last_error);
        }
        
        return $result;
    }
    
    public static function get_user_stats($user_id, $period = 'today') {
        // Check cache first
        $cache_key = "teamflow_stats_{$user_id}_{$period}";
        $cached = wp_cache_get($cache_key, 'teamflow');
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        $date_conditions = self::get_date_conditions($period);
        
        $sql = "SELECT 
                    COUNT(*) as total_entries,
                    COALESCE(SUM(elapsed_seconds), 0) as total_seconds,
                    COALESCE(SUM(idle_seconds), 0) as total_idle,
                    COALESCE(AVG(activity_level), 0) as avg_activity
                FROM $table 
                WHERE user_id = %d 
                AND status IN ('completed', 'paused')
                AND {$date_conditions}";
        
        $result = $wpdb->get_row($wpdb->prepare($sql, absint($user_id)));
        
        // Cache for 5 minutes
        wp_cache_set($cache_key, $result, 'teamflow', 300);
        
        return $result;
    }
    
    public static function get_team_stats($period = 'today') {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        $date_conditions = self::get_date_conditions($period);
        
        $sql = "SELECT 
                    user_id,
                    COUNT(*) as total_entries,
                    COALESCE(SUM(elapsed_seconds), 0) as total_seconds,
                    COALESCE(AVG(activity_level), 0) as avg_activity
                FROM $table 
                WHERE status IN ('completed', 'paused', 'active')
                AND {$date_conditions}
                GROUP BY user_id";
        
        return $wpdb->get_results($sql);
    }
    
    private static function get_date_conditions($period) {
        $today = current_time('Y-m-d');
        
        switch ($period) {
            case 'today':
                return $GLOBALS['wpdb']->prepare("DATE(start_time) = %s", $today);
            case 'week':
                return $GLOBALS['wpdb']->prepare("YEARWEEK(start_time, 1) = YEARWEEK(%s, 1)", $today);
            case 'month':
                return $GLOBALS['wpdb']->prepare("YEAR(start_time) = YEAR(%s) AND MONTH(start_time) = MONTH(%s)", $today, $today);
            case 'year':
                return $GLOBALS['wpdb']->prepare("YEAR(start_time) = YEAR(%s)", $today);
            default:
                return $GLOBALS['wpdb']->prepare("DATE(start_time) = %s", $today);
        }
    }
    
    public static function get_projects($status = 'active') {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_projects';
        
        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s ORDER BY name ASC",
                sanitize_text_field($status)
            ));
        }
        
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }
    
    /**
     * Delete old time entries (for cleanup)
     */
    public static function cleanup_old_entries($days = 365) {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_time_entries';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE start_time < DATE_SUB(NOW(), INTERVAL %d DAY)",
            absint($days)
        ));
        
        if ($deleted) {
            TeamFlow_Logger::info("Cleaned up $deleted old time entries");
        }
        
        return $deleted;
    }
}
