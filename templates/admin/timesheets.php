<?php
/**
 * Timesheets Template
 * Path: templates/admin/timesheets.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

// Get time entries
$entries = TeamFlow_Database::get_time_entries(array(
    'user_id' => $user_filter,
    'start_date' => $date_from,
    'end_date' => $date_to . ' 23:59:59',
    'limit' => 100,
));

// Calculate totals
$total_hours = 0;
$total_entries = count($entries);
foreach ($entries as $entry) {
    $total_hours += $entry->elapsed_seconds / 3600;
}

// Get all team members for filter
$team_members = get_users(array('role__in' => array('team_member', 'team_manager')));
?>

<div class="wrap teamflow-wrap">
    <div class="tf-header">
        <h1><i class="fas fa-calendar-alt"></i> Timesheets</h1>
    </div>

    <!-- Filters -->
    <div class="tf-card tf-filters-card">
        <form method="get" action="">
            <input type="hidden" name="page" value="teamflow-timesheets" />
            <div class="tf-filters-grid">
                <div class="tf-form-group">
                    <label class="tf-form-label">Employee</label>
                    <select name="user_id" class="tf-form-select">
                        <option value="">All Employees</option>
                        <?php foreach ($team_members as $member) : ?>
                            <option value="<?php echo $member->ID; ?>" <?php selected($user_filter, $member->ID); ?>>
                                <?php echo esc_html($member->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="tf-form-group">
                    <label class="tf-form-label">From Date</label>
                    <input type="date" name="date_from" class="tf-form-input" value="<?php echo esc_attr($date_from); ?>" />
                </div>
                
                <div class="tf-form-group">
                    <label class="tf-form-label">To Date</label>
                    <input type="date" name="date_to" class="tf-form-input" value="<?php echo esc_attr($date_to); ?>" />
                </div>
                
                <div class="tf-form-group">
                    <label class="tf-form-label">&nbsp;</label>
                    <button type="submit" class="button button-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="tf-summary-grid">
        <div class="tf-summary-card tf-card-blue">
            <i class="fas fa-clock"></i>
            <div>
                <span class="tf-summary-label">Total Hours</span>
                <span class="tf-summary-value"><?php echo round($total_hours, 1); ?>h</span>
            </div>
        </div>
        <div class="tf-summary-card tf-card-green">
            <i class="fas fa-list"></i>
            <div>
                <span class="tf-summary-label">Total Entries</span>
                <span class="tf-summary-value"><?php echo $total_entries; ?></span>
            </div>
        </div>
        <div class="tf-summary-card tf-card-purple">
            <i class="fas fa-chart-line"></i>
            <div>
                <span class="tf-summary-label">Avg Per Entry</span>
                <span class="tf-summary-value"><?php echo $total_entries > 0 ? round($total_hours / $total_entries, 1) : 0; ?>h</span>
            </div>
        </div>
    </div>

    <!-- Timesheets Table -->
    <div class="tf-card">
        <div class="tf-card-header">
            <h2>Time Entries</h2>
            <a href="<?php echo admin_url('admin.php?page=teamflow-timesheets&export=csv&' . http_build_query($_GET)); ?>" class="button">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>
        <div class="tf-card-body tf-table-container">
            <?php if (empty($entries)) : ?>
                <p class="tf-no-data">No time entries found for the selected filters.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Project</th>
                        <th>Task</th>
                        <th>Start Time</th>
                        <th>Duration</th>
                        <th>Activity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry) : 
                        $user = get_userdata($entry->user_id);
                        $project_name = $entry->project_id ? get_project_name($entry->project_id) : 'No Project';
                        $hours = round($entry->elapsed_seconds / 3600, 2);
                        $date = date('M d, Y', strtotime($entry->start_time));
                        $time = date('h:i A', strtotime($entry->start_time));
                    ?>
                    <tr>
                        <td><?php echo esc_html($date); ?></td>
                        <td>
                            <strong><?php echo esc_html($user ? $user->display_name : 'Unknown'); ?></strong>
                        </td>
                        <td>
                            <span class="tf-project-badge"><?php echo esc_html($project_name); ?></span>
                        </td>
                        <td><?php echo esc_html($entry->task_name); ?></td>
                        <td><?php echo esc_html($time); ?></td>
                        <td><strong><?php echo $hours; ?>h</strong></td>
                        <td>
                            <div class="tf-activity-display">
                                <div class="tf-activity-bar-mini">
                                    <div class="tf-activity-fill-mini" style="width: <?php echo $entry->activity_level; ?>%"></div>
                                </div>
                                <span><?php echo $entry->activity_level; ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="tf-status-badge tf-status-<?php echo $entry->status; ?>">
                                <?php echo ucfirst($entry->status); ?>
                            </span>
                        </td>
                        <td>
                            <a href="#" class="button button-small" onclick="viewEntryDetails(<?php echo $entry->id; ?>); return false;">
                                <i class="fas fa-eye"></i> Details
                            </a>
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
.tf-filters-card {
    margin-bottom: 24px;
}

.tf-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    align-items: end;
}

.tf-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.tf-summary-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tf-summary-card i {
    font-size: 32px;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.tf-card-blue i {
    background: #dbeafe;
    color: #3b82f6;
}

.tf-card-green i {
    background: #dcfce7;
    color: #10b981;
}

.tf-card-purple i {
    background: #f3e8ff;
    color: #8b5cf6;
}

.tf-summary-label {
    display: block;
    font-size: 13px;
    color: #64748b;
    margin-bottom: 4px;
}

.tf-summary-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
}

.tf-table-container {
    overflow-x: auto;
}

.tf-no-data {
    text-align: center;
    padding: 40px;
    color: #64748b;
    font-size: 16px;
}

.tf-project-badge {
    background: #dbeafe;
    color: #1e40af;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.tf-activity-display {
    display: flex;
    align-items: center;
    gap: 8px;
}

.tf-activity-bar-mini {
    width: 60px;
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
}

.tf-activity-fill-mini {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
}

.tf-status-completed {
    background: #dcfce7;
    color: #166534;
}

.tf-status-active {
    background: #dbeafe;
    color: #1e40af;
}

.tf-status-paused {
    background: #fef3c7;
    color: #92400e;
}
</style>

<script>
function viewEntryDetails(entryId) {
    // Implement details modal or redirect
    alert('View details for entry #' + entryId);
}
</script>
