<?php
/**
 * TeamFlow Payroll Management
 * Path: includes/class-teamflow-payroll.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class TeamFlow_Payroll {
    
    public function __construct() {
        // AJAX actions for payroll
        add_action('wp_ajax_teamflow_generate_payroll', array($this, 'generate_payroll'));
        add_action('wp_ajax_teamflow_process_payroll', array($this, 'process_payroll'));
        add_action('wp_ajax_teamflow_export_payroll', array($this, 'export_payroll'));
        add_action('wp_ajax_teamflow_update_user_rate', array($this, 'update_user_rate'));
    }
    
    /**
     * Generate payroll for a period
     */
    public function generate_payroll() {
        check_ajax_referer('teamflow_nonce', 'nonce');
        
        if (!current_user_can('manage_payroll')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$start_date || !$end_date) {
            wp_send_json_error(array('message' => 'Missing date range'));
        }
        
        // Get users to generate payroll for
        $users = array();
        if ($user_id) {
            $users = array(get_userdata($user_id));
        } else {
            $users = get_users(array('role__in' => array('team_member', 'team_manager')));
        }
        
        $generated = array();
        
        foreach ($users as $user) {
            $payroll_data = $this->calculate_payroll($user->ID, $start_date, $end_date);
            
            if ($payroll_data) {
                $payroll_id = $this->save_payroll_record($payroll_data);
                $generated[] = array(
                    'user_id' => $user->ID,
                    'user_name' => $user->display_name,
                    'payroll_id' => $payroll_id,
                    'data' => $payroll_data,
                );
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Payroll generated successfully',
            'generated' => $generated,
        ));
    }
    
    /**
     * Calculate payroll for a user
     */
    private function calculate_payroll($user_id, $start_date, $end_date) {
        // Get time entries for period
        $entries = TeamFlow_Database::get_time_entries(array(
            'user_id' => $user_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'completed',
            'limit' => 9999,
        ));
        
        if (empty($entries)) {
            return null;
        }
        
        $total_seconds = 0;
        foreach ($entries as $entry) {
            $total_seconds += $entry->elapsed_seconds;
        }
        
        $hours_worked = $total_seconds / 3600;
        
        // Get user hourly rate
        $hourly_rate = floatval(get_user_meta($user_id, 'teamflow_hourly_rate', true));
        if (!$hourly_rate) {
            $hourly_rate = 0;
        }
        
        // Calculate gross pay
        $gross_pay = $hours_worked * $hourly_rate;
        
        // Calculate overtime if enabled
        $auto_overtime = get_option('teamflow_auto_overtime', true);
        $overtime_pay = 0;
        
        if ($auto_overtime) {
            $regular_hours_limit = 160; // 40 hours/week * 4 weeks
            if ($hours_worked > $regular_hours_limit) {
                $overtime_hours = $hours_worked - $regular_hours_limit;
                $overtime_rate = $hourly_rate * 1.5;
                $overtime_pay = $overtime_hours * ($overtime_rate - $hourly_rate);
                $gross_pay += $overtime_pay;
            }
        }
        
        // Calculate deductions
        $tax_rate = floatval(get_option('teamflow_tax_rate', 20)) / 100;
        $deductions = $gross_pay * $tax_rate;
        
        // Calculate net pay
        $net_pay = $gross_pay - $deductions;
        
        return array(
            'user_id' => $user_id,
            'period_start' => $start_date,
            'period_end' => $end_date,
            'hours_worked' => round($hours_worked, 2),
            'hourly_rate' => $hourly_rate,
            'gross_pay' => round($gross_pay, 2),
            'deductions' => round($deductions, 2),
            'net_pay' => round($net_pay, 2),
            'overtime_pay' => round($overtime_pay, 2),
            'status' => 'pending',
        );
    }
    
    /**
     * Save payroll record to database
     */
    private function save_payroll_record($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_payroll';
        
        // Check if record already exists for this period
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table 
             WHERE user_id = %d 
             AND period_start = %s 
             AND period_end = %s",
            $data['user_id'],
            $data['period_start'],
            $data['period_end']
        ));
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $table,
                $data,
                array('id' => $existing->id)
            );
            return $existing->id;
        } else {
            // Insert new record
            $wpdb->insert($table, $data);
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Process payroll (mark as processed)
     */
    public function process_payroll() {
        check_ajax_referer('teamflow_nonce', 'nonce');
        
        if (!current_user_can('manage_payroll')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $payroll_id = isset($_POST['payroll_id']) ? intval($_POST['payroll_id']) : 0;
        
        if (!$payroll_id) {
            wp_send_json_error(array('message' => 'Invalid payroll ID'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_payroll';
        
        $updated = $wpdb->update(
            $table,
            array(
                'status' => 'processed',
                'processed_at' => current_time('mysql'),
                'processed_by' => get_current_user_id(),
            ),
            array('id' => $payroll_id)
        );
        
        if ($updated) {
            // Send notification to employee
            $payroll = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $payroll_id));
            $this->send_payroll_notification($payroll);
            
            wp_send_json_success(array('message' => 'Payroll processed successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to process payroll'));
        }
    }
    
    /**
     * Export payroll data to CSV
     */
    public function export_payroll() {
        check_ajax_referer('teamflow_nonce', 'nonce');
        
        if (!current_user_can('manage_payroll')) {
            wp_die('Permission denied');
        }
        
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_payroll';
        
        $where = '1=1';
        if ($start_date) {
            $where .= $wpdb->prepare(' AND period_start >= %s', $start_date);
        }
        if ($end_date) {
            $where .= $wpdb->prepare(' AND period_end <= %s', $end_date);
        }
        
        $payroll_records = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY period_end DESC");
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=payroll_export_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'Employee Name',
            'Email',
            'Period Start',
            'Period End',
            'Hours Worked',
            'Hourly Rate',
            'Gross Pay',
            'Deductions',
            'Net Pay',
            'Status',
            'Processed Date'
        ));
        
        // CSV data
        foreach ($payroll_records as $record) {
            $user = get_userdata($record->user_id);
            fputcsv($output, array(
                $user ? $user->display_name : 'Unknown',
                $user ? $user->user_email : '',
                $record->period_start,
                $record->period_end,
                $record->hours_worked,
                '$' . $record->hourly_rate,
                '$' . $record->gross_pay,
                '$' . $record->deductions,
                '$' . $record->net_pay,
                ucfirst($record->status),
                $record->processed_at ? $record->processed_at : 'Not processed'
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Update user hourly rate
     */
    public function update_user_rate() {
        check_ajax_referer('teamflow_nonce', 'nonce');
        
        if (!current_user_can('manage_team')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $hourly_rate = isset($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : 0;
        
        if (!$user_id || $hourly_rate < 0) {
            wp_send_json_error(array('message' => 'Invalid data'));
        }
        
        update_user_meta($user_id, 'teamflow_hourly_rate', $hourly_rate);
        
        wp_send_json_success(array(
            'message' => 'Hourly rate updated',
            'user_id' => $user_id,
            'hourly_rate' => $hourly_rate,
        ));
    }
    
    /**
     * Send payroll notification to employee
     */
    private function send_payroll_notification($payroll) {
        $user = get_userdata($payroll->user_id);
        if (!$user) return;
        
        $subject = 'Payroll Processed - ' . $payroll->period_start . ' to ' . $payroll->period_end;
        
        $message = "Hello {$user->display_name},\n\n";
        $message .= "Your payroll for the period {$payroll->period_start} to {$payroll->period_end} has been processed.\n\n";
        $message .= "Payment Details:\n";
        $message .= "Hours Worked: {$payroll->hours_worked}h\n";
        $message .= "Hourly Rate: $" . number_format($payroll->hourly_rate, 2) . "\n";
        $message .= "Gross Pay: $" . number_format($payroll->gross_pay, 2) . "\n";
        $message .= "Deductions: $" . number_format($payroll->deductions, 2) . "\n";
        $message .= "Net Pay: $" . number_format($payroll->net_pay, 2) . "\n\n";
        $message .= "If you have any questions, please contact your manager.\n\n";
        $message .= "Best regards,\n";
        $message .= get_bloginfo('name');
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Get payroll summary for a period
     */
    public static function get_payroll_summary($start_date = null, $end_date = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'teamflow_payroll';
        
        $where = '1=1';
        if ($start_date) {
            $where .= $wpdb->prepare(' AND period_start >= %s', $start_date);
        }
        if ($end_date) {
            $where .= $wpdb->prepare(' AND period_end <= %s', $end_date);
        }
        
        $summary = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_records,
                SUM(hours_worked) as total_hours,
                SUM(gross_pay) as total_gross,
                SUM(deductions) as total_deductions,
                SUM(net_pay) as total_net,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'pending' THEN net_pay ELSE 0 END) as pending_amount
            FROM $table 
            WHERE $where"
        );
        
        return $summary;
    }
}
