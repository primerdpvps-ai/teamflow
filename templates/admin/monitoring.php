<?php
/**
 * Live Monitoring Template
 * Path: templates/admin/monitoring.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');

// Get active users
global $wpdb;
$entry_table = $wpdb->prefix . 'teamflow_time_entries';
$active_users = $wpdb->get_results(
    "SELECT DISTINCT user_id FROM $entry_table 
     WHERE status = 'active' AND DATE(start_time) = CURDATE()"
);
?>

<div class="wrap teamflow-wrap" id="tf-monitoring">
    <div class="tf-header">
        <h1><i class="fas fa-desktop"></i> Live Monitoring</h1>
        <div class="tf-header-controls">
            <span class="tf-live-indicator">
                <span class="tf-pulse"></span> Live
            </span>
            <button class="button" id="tf-refresh-monitoring">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Active Users Count -->
    <div class="tf-stats-grid">
        <div class="tf-stat-card tf-card-green">
            <div class="tf-stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Active Now</h3>
                <p class="tf-stat-value" id="tf-active-count"><?php echo count($active_users); ?></p>
                <span class="tf-stat-change">Team members working</span>
            </div>
        </div>

        <div class="tf-stat-card tf-card-blue">
            <div class="tf-stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Total Time Today</h3>
                <p class="tf-stat-value" id="tf-total-time-today">
                    <?php 
                    $total_today = $wpdb->get_var(
                        "SELECT SUM(elapsed_seconds) FROM $entry_table 
                         WHERE DATE(start_time) = CURDATE() AND status IN ('active', 'completed')"
                    );
                    echo round($total_today / 3600, 1);
                    ?>h
                </p>
            </div>
        </div>

        <div class="tf-stat-card tf-card-purple">
            <div class="tf-stat-icon">
                <i class="fas fa-camera"></i>
            </div>
            <div class="tf-stat-content">
                <h3>Screenshots Today</h3>
                <p class="tf-stat-value" id="tf-screenshots-count">
                    <?php 
                    $screenshots_table = $wpdb->prefix . 'teamflow_screenshots';
                    $screenshot_count = $wpdb->get_var(
                        "SELECT COUNT(*) FROM $screenshots_table WHERE DATE(captured_at) = CURDATE()"
                    );
                    echo $screenshot_count;
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="tf-card tf-monitoring-filters">
        <div class="tf-filters-row">
            <div class="tf-form-group">
                <label class="tf-form-label">Filter by User</label>
                <select id="tf-user-filter" class="tf-form-select">
                    <option value="">All Users</option>
                    <?php 
                    $all_users = get_users(array('role__in' => array('team_member', 'team_manager')));
                    foreach ($all_users as $user) : 
                    ?>
                        <option value="<?php echo $user->ID; ?>" <?php selected($user_filter, $user->ID); ?>>
                            <?php echo esc_html($user->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tf-form-group">
                <label class="tf-form-label">View Mode</label>
                <div class="tf-view-toggle">
                    <button class="tf-view-btn active" data-view="grid">
                        <i class="fas fa-th"></i> Grid
                    </button>
                    <button class="tf-view-btn" data-view="list">
                        <i class="fas fa-list"></i> List
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Monitoring Grid -->
    <div id="tf-monitoring-grid" class="tf-monitoring-grid">
        <div class="tf-loading-state">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Loading monitoring data...</p>
        </div>
    </div>

    <!-- Screenshot Modal -->
    <div id="tf-screenshot-modal" class="tf-modal" style="display: none;">
        <div class="tf-modal-content">
            <div class="tf-modal-header">
                <h3>Screenshot Details</h3>
                <button class="tf-modal-close">&times;</button>
            </div>
            <div class="tf-modal-body">
                <img id="tf-screenshot-image" src="" alt="Screenshot" />
                <div class="tf-screenshot-info">
                    <p><strong>User:</strong> <span id="tf-screenshot-user"></span></p>
                    <p><strong>Captured:</strong> <span id="tf-screenshot-time"></span></p>
                    <p><strong>Activity Level:</strong> <span id="tf-screenshot-activity"></span></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tf-header-controls {
    display: flex;
    align-items: center;
    gap: 16px;
}

.tf-live-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #dcfce7;
    color: #166534;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
}

.tf-pulse {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}

.tf-monitoring-filters {
    margin-bottom: 24px;
}

.tf-filters-row {
    display: flex;
    gap: 24px;
    align-items: end;
}

.tf-view-toggle {
    display: flex;
    gap: 8px;
}

.tf-view-btn {
    padding: 10px 20px;
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.tf-view-btn.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.tf-monitoring-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 24px;
}

.tf-monitoring-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tf-monitoring-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}

.tf-user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6, #ec4899);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
}

.tf-user-info h4 {
    margin: 0 0 4px 0;
    font-size: 16px;
    color: #1e293b;
}

.tf-user-task {
    font-size: 13px;
    color: #64748b;
    margin: 0;
}

.tf-monitoring-screenshot {
    width: 100%;
    height: 200px;
    background: #f8fafc;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 16px;
    cursor: pointer;
    position: relative;
}

.tf-monitoring-screenshot img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tf-screenshot-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    color: white;
    padding: 12px;
    font-size: 12px;
}

.tf-monitoring-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.tf-stat-mini {
    text-align: center;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
}

.tf-stat-mini-label {
    display: block;
    font-size: 11px;
    color: #64748b;
    margin-bottom: 4px;
}

.tf-stat-mini-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}

.tf-loading-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px;
    color: #64748b;
}

.tf-loading-state i {
    font-size: 32px;
    margin-bottom: 16px;
}

.tf-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.8);
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.tf-modal-content {
    background: white;
    border-radius: 16px;
    max-width: 90%;
    max-height: 90%;
    overflow: auto;
}

.tf-modal-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tf-modal-close {
    font-size: 24px;
    border: none;
    background: none;
    cursor: pointer;
    color: #64748b;
}

.tf-modal-body {
    padding: 20px;
}

.tf-modal-body img {
    width: 100%;
    border-radius: 8px;
    margin-bottom: 16px;
}

.tf-screenshot-info p {
    margin: 8px 0;
    font-size: 14px;
}
</style>

<script>
jQuery(document).ready(function($) {
    let currentView = 'grid';
    
    function loadMonitoringData() {
        const userId = $('#tf-user-filter').val();
        
        $.ajax({
            url: teamflow.ajax_url,
            type: 'POST',
            data: {
                action: 'teamflow_get_monitoring_data',
                nonce: teamflow.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    renderMonitoringData(response.data.monitoring);
                }
            }
        });
    }
    
    function renderMonitoringData(data) {
        const $grid = $('#tf-monitoring-grid');
        $grid.empty();
        
        if (data.length === 0) {
            $grid.html('<div class="tf-loading-state"><p>No active users at the moment</p></div>');
            return;
        }
        
        data.forEach(item => {
            const initials = item.user_name.split(' ').map(n => n[0]).join('').toUpperCase();
            const screenshotHtml = item.latest_screenshot ? 
                `<div class="tf-monitoring-screenshot" data-user="${item.user_name}">
                    <img src="${item.latest_screenshot}" alt="Latest screenshot" />
                    <div class="tf-screenshot-overlay">${item.last_activity} ago</div>
                </div>` : 
                `<div class="tf-monitoring-screenshot"><p style="text-align:center;padding:80px 20px;color:#64748b;">No screenshot available</p></div>`;
            
            const html = `
                <div class="tf-monitoring-card">
                    <div class="tf-monitoring-header">
                        <div class="tf-user-avatar">${initials}</div>
                        <div class="tf-user-info">
                            <h4>${item.user_name}</h4>
                            <p class="tf-user-task">${item.task}</p>
                        </div>
                    </div>
                    ${screenshotHtml}
                    <div class="tf-monitoring-stats">
                        <div class="tf-stat-mini">
                            <span class="tf-stat-mini-label">Duration</span>
                            <span class="tf-stat-mini-value">${item.duration}h</span>
                        </div>
                        <div class="tf-stat-mini">
                            <span class="tf-stat-mini-label">Activity</span>
                            <span class="tf-stat-mini-value">${item.activity_level}%</span>
                        </div>
                        <div class="tf-stat-mini">
                            <span class="tf-stat-mini-label">Status</span>
                            <span class="tf-stat-mini-value" style="font-size:14px;color:#10b981;">Active</span>
                        </div>
                    </div>
                </div>
            `;
            
            $grid.append(html);
        });
    }
    
    // Auto-refresh every 30 seconds
    setInterval(loadMonitoringData, 30000);
    
    // Manual refresh
    $('#tf-refresh-monitoring').on('click', loadMonitoringData);
    
    // User filter change
    $('#tf-user-filter').on('change', loadMonitoringData);
    
    // View toggle
    $('.tf-view-btn').on('click', function() {
        $('.tf-view-btn').removeClass('active');
        $(this).addClass('active');
        currentView = $(this).data('view');
        
        if (currentView === 'list') {
            $('#tf-monitoring-grid').css('grid-template-columns', '1fr');
        } else {
            $('#tf-monitoring-grid').css('grid-template-columns', 'repeat(auto-fill, minmax(350px, 1fr))');
        }
    });
    
    // Screenshot modal
    $(document).on('click', '.tf-monitoring-screenshot img', function() {
        const src = $(this).attr('src');
        $('#tf-screenshot-image').attr('src', src);
        $('#tf-screenshot-modal').fadeIn();
    });
    
    $('.tf-modal-close, .tf-modal').on('click', function(e) {
        if (e.target === this) {
            $('#tf-screenshot-modal').fadeOut();
        }
    });
    
    // Initial load
    loadMonitoringData();
});
</script>
