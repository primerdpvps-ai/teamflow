<?php
/**
 * Payroll Management Template
 * Path: templates/admin/payroll.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get payroll summary
$summary = TeamFlow_Payroll::get_payroll_summary();
$pending_amount = $summary ? $summary->pending_amount : 0;
$pending_count = $summary ? $summary->pending_count : 0;

// Get recent payroll records
global $wpdb;
$table = $wpdb->prefix . 'teamflow_payroll';
$payroll_records = $wpdb->get_results(
    "SELECT * FROM $table ORDER BY period_end DESC LIMIT 50"
);

// Get team members for filter
$team_members = get_users(array('role__in' => array('team_member', 'team_manager')));
?>

<div class="wrap teamflow-wrap">
    <div class="tf-header">
        <h1><i class="fas fa-dollar-sign"></i> Payroll Management</h1>
    </div>

    <!-- Summary Cards -->
    <div class="tf-stats-grid">
        <div class="tf-stat-card tf-card-orange">
            <div class="tf-stat-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Pending Payroll</h3>
                <p class="tf-stat-value"><?php echo $pending_count; ?></p>
                <span class="tf-stat-change">Records awaiting processing</span>
            </div>
        </div>

        <div class="tf-stat-card tf-card-blue">
            <div class="tf-stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Pending Amount</h3>
                <p class="tf-stat-value">$<?php echo number_format($pending_amount, 2); ?></p>
                <span class="tf-stat-change">Total to be paid</span>
            </div>
        </div>

        <div class="tf-stat-card tf-card-green">
            <div class="tf-stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Processed This Month</h3>
                <p class="tf-stat-value"><?php 
                    $processed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'processed' AND MONTH(processed_at) = MONTH(NOW())");
                    echo $processed;
                ?></p>
            </div>
        </div>
    </div>

    <!-- Generate Payroll Section -->
    <div class="tf-card">
        <div class="tf-card-header">
            <h2><i class="fas fa-calculator"></i> Generate Payroll</h2>
        </div>
        <div class="tf-card-body">
            <form id="tf-generate-payroll-form">
                <div class="tf-form-grid">
                    <div class="tf-form-group">
                        <label class="tf-form-label">Employee (Optional)</label>
                        <select name="user_id" class="tf-form-select">
                            <option value="">All Employees</option>
                            <?php foreach ($team_members as $member) : ?>
                                <option value="<?php echo $member->ID; ?>">
                                    <?php echo esc_html($member->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tf-form-group">
                        <label class="tf-form-label">Period Start</label>
                        <input type="date" name="start_date" class="tf-form-input" required 
                               value="<?php echo date('Y-m-01'); ?>" />
                    </div>

                    <div class="tf-form-group">
                        <label class="tf-form-label">Period End</label>
                        <input type="date" name="end_date" class="tf-form-input" required 
                               value="<?php echo date('Y-m-d'); ?>" />
                    </div>

                    <div class="tf-form-group">
                        <label class="tf-form-label">&nbsp;</label>
                        <button type="submit" class="tf-btn tf-btn-primary">
                            <i class="fas fa-cogs"></i> Generate Payroll
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Payroll Records Table -->
    <div class="tf-card">
        <div class="tf-card-header">
            <h2>Payroll Records</h2>
            <div>
                <a href="<?php echo admin_url('admin.php?page=teamflow-payroll&action=export'); ?>" class="button">
                    <i class="fas fa-download"></i> Export CSV
                </a>
            </div>
        </div>
        <div class="tf-card-body">
            <?php if (empty($payroll_records)) : ?>
                <p class="tf-no-data">No payroll records found. Generate payroll above to get started.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Period</th>
                        <th>Hours</th>
                        <th>Rate</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payroll_records as $record) : 
                        $user = get_userdata($record->user_id);
                        $period = date('M d', strtotime($record->period_start)) . ' - ' . date('M d, Y', strtotime($record->period_end));
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo $user ? esc_html($user->display_name) : 'Unknown'; ?></strong>
                        </td>
                        <td><?php echo $period; ?></td>
                        <td><?php echo number_format($record->hours_worked, 2); ?>h</td>
                        <td>$<?php echo number_format($record->hourly_rate, 2); ?></td>
                        <td><strong>$<?php echo number_format($record->gross_pay, 2); ?></strong></td>
                        <td>$<?php echo number_format($record->deductions, 2); ?></td>
                        <td><strong class="tf-net-pay">$<?php echo number_format($record->net_pay, 2); ?></strong></td>
                        <td>
                            <span class="tf-status-badge tf-status-<?php echo $record->status; ?>">
                                <?php echo ucfirst($record->status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($record->status === 'pending') : ?>
                                <button class="button button-primary button-small tf-process-payroll" 
                                        data-payroll-id="<?php echo $record->id; ?>">
                                    <i class="fas fa-check"></i> Process
                                </button>
                            <?php else : ?>
                                <span class="tf-processed-date">
                                    <?php echo date('M d, Y', strtotime($record->processed_at)); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.tf-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    align-items: end;
}

.tf-no-data {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

.tf-net-pay {
    color: #10b981;
    font-size: 16px;
}

.tf-processed-date {
    font-size: 12px;
    color: #64748b;
}

.tf-status-pending {
    background: #fef3c7;
    color: #92400e;
}

.tf-status-processed {
    background: #dcfce7;
    color: #166534;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Generate payroll
    $('#tf-generate-payroll-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: teamflow.ajax_url,
            type: 'POST',
            data: {
                action: 'teamflow_generate_payroll',
                nonce: teamflow.nonce,
                ...Object.fromEntries(new URLSearchParams(formData))
            },
            beforeSend: function() {
                $('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating...');
            },
            success: function(response) {
                if (response.success) {
                    TeamFlow.notifications.show('Payroll generated successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    TeamFlow.notifications.show(response.data.message, 'error');
                }
            },
            complete: function() {
                $('button[type="submit"]').prop('disabled', false).html('<i class="fas fa-cogs"></i> Generate Payroll');
            }
        });
    });
    
    // Process payroll
    $('.tf-process-payroll').on('click', function() {
        if (!confirm('Process this payroll record? This action cannot be undone.')) {
            return;
        }
        
        const payrollId = $(this).data('payroll-id');
        const $btn = $(this);
        
        $.ajax({
            url: teamflow.ajax_url,
            type: 'POST',
            data: {
                action: 'teamflow_process_payroll',
                nonce: teamflow.nonce,
                payroll_id: payrollId
            },
            beforeSend: function() {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            },
            success: function(response) {
                if (response.success) {
                    TeamFlow.notifications.show('Payroll processed successfully', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    TeamFlow.notifications.show(response.data.message, 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-check"></i> Process');
                }
            }
        });
    });
});
</script>
