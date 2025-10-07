<?php
/**
 * Team Management Template
 * Path: templates/admin/team.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get all team members
$team_members = get_users(array(
    'role__in' => array('team_member', 'team_manager', 'administrator'),
    'orderby' => 'display_name',
    'order' => 'ASC'
));
?>

<div class="wrap teamflow-wrap">
    <div class="tf-header">
        <h1><i class="fas fa-users"></i> Team Members</h1>
        <a href="<?php echo admin_url('user-new.php'); ?>" class="button button-primary">
            <i class="fas fa-user-plus"></i> Add New Member
        </a>
    </div>

    <div class="tf-team-grid">
        <?php foreach ($team_members as $member) : 
            $stats = TeamFlow_Database::get_user_stats($member->ID, 'today');
            $stats_week = TeamFlow_Database::get_user_stats($member->ID, 'week');
            $hourly_rate = get_user_meta($member->ID, 'teamflow_hourly_rate', true);
            
            $hours_today = $stats ? round($stats->total_seconds / 3600, 1) : 0;
            $hours_week = $stats_week ? round($stats_week->total_seconds / 3600, 1) : 0;
            $activity = $stats ? round($stats->avg_activity) : 0;
            
            // Check if user is currently active
            $active_timer = TeamFlow_Timer::get_active_timer($member->ID);
            $status = $active_timer && $active_timer->status === 'active' ? 'active' : 
                     ($active_timer && $active_timer->status === 'paused' ? 'idle' : 'offline');
            
            $initials = '';
            $name_parts = explode(' ', $member->display_name);
            foreach ($name_parts as $part) {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        ?>
        
        <div class="tf-card tf-team-member-card">
            <div class="tf-member-header">
                <div class="tf-member-avatar-large"><?php echo esc_html($initials); ?></div>
                <div class="tf-member-info">
                    <h3><?php echo esc_html($member->display_name); ?></h3>
                    <p class="tf-member-email"><?php echo esc_html($member->user_email); ?></p>
                    <span class="tf-status-badge tf-status-<?php echo $status; ?>">
                        <?php echo ucfirst($status); ?>
                    </span>
                </div>
            </div>

            <div class="tf-member-stats-grid">
                <div class="tf-stat-item">
                    <span class="tf-stat-label">Hours Today</span>
                    <span class="tf-stat-value"><?php echo $hours_today; ?>h</span>
                </div>
                <div class="tf-stat-item">
                    <span class="tf-stat-label">Hours This Week</span>
                    <span class="tf-stat-value"><?php echo $hours_week; ?>h</span>
                </div>
                <div class="tf-stat-item">
                    <span class="tf-stat-label">Activity Level</span>
                    <span class="tf-stat-value"><?php echo $activity; ?>%</span>
                </div>
                <div class="tf-stat-item">
                    <span class="tf-stat-label">Hourly Rate</span>
                    <span class="tf-stat-value">$<?php echo $hourly_rate ? $hourly_rate : '0'; ?></span>
                </div>
            </div>

            <?php if ($active_timer) : ?>
            <div class="tf-current-task">
                <strong>Current Task:</strong> <?php echo esc_html($active_timer->task_name); ?>
            </div>
            <?php endif; ?>

            <div class="tf-member-actions">
                <a href="<?php echo admin_url('user-edit.php?user_id=' . $member->ID); ?>" class="tf-btn tf-btn-small">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
                <a href="<?php echo admin_url('admin.php?page=teamflow-monitoring&user_id=' . $member->ID); ?>" class="tf-btn tf-btn-small tf-btn-primary">
                    <i class="fas fa-desktop"></i> View Activity
                </a>
            </div>
        </div>
        
        <?php endforeach; ?>
    </div>
</div>

<style>
.tf-team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 24px;
}

.tf-team-member-card {
    padding: 24px;
}

.tf-member-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.tf-member-avatar-large {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 20px;
    flex-shrink: 0;
}

.tf-member-info h3 {
    margin: 0 0 4px 0;
    font-size: 18px;
    color: #1e293b;
}

.tf-member-email {
    font-size: 13px;
    color: #64748b;
    margin: 0 0 8px 0;
}

.tf-status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.tf-status-active {
    background: #dcfce7;
    color: #166534;
}

.tf-status-idle {
    background: #fef3c7;
    color: #92400e;
}

.tf-status-offline {
    background: #f1f5f9;
    color: #475569;
}

.tf-member-stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 16px;
}

.tf-stat-item {
    background: #f8fafc;
    padding: 12px;
    border-radius: 8px;
}

.tf-stat-label {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 4px;
}

.tf-stat-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}

.tf-current-task {
    background: #dbeafe;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 14px;
    color: #1e40af;
}

.tf-member-actions {
    display: flex;
    gap: 8px;
}

.tf-btn-small {
    padding: 8px 16px;
    font-size: 13px;
    flex: 1;
    text-align: center;
    text-decoration: none;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: white;
    color: #475569;
    transition: all 0.2s;
}

.tf-btn-small:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.tf-btn-small.tf-btn-primary {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.tf-btn-small.tf-btn-primary:hover {
    background: #2563eb;
}
</style>
