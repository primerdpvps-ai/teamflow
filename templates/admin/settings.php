<?php
/**
 * Settings Page Template
 * Path: templates/admin/settings.php
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['teamflow_save_settings']) && check_admin_referer('teamflow_settings_save')) {
    $settings = array(
        'screenshot_interval' => intval($_POST['screenshot_interval']),
        'screenshot_quality' => sanitize_text_field($_POST['screenshot_quality']),
        'recording_fps' => sanitize_text_field($_POST['recording_fps']),
        'inactivity_threshold' => intval($_POST['inactivity_threshold']),
        'session_type' => sanitize_text_field($_POST['session_type']),
        'blur_sensitive' => isset($_POST['blur_sensitive']) ? 1 : 0,
        'auto_resume' => isset($_POST['auto_resume']) ? 1 : 0,
        'track_keyboard' => isset($_POST['track_keyboard']) ? 1 : 0,
        'track_mouse' => isset($_POST['track_mouse']) ? 1 : 0,
        'track_apps' => isset($_POST['track_apps']) ? 1 : 0,
        'track_urls' => isset($_POST['track_urls']) ? 1 : 0,
        'pay_period' => sanitize_text_field($_POST['pay_period']),
        'tax_rate' => floatval($_POST['tax_rate']),
        'auto_overtime' => isset($_POST['auto_overtime']) ? 1 : 0,
        'low_activity_alerts' => isset($_POST['low_activity_alerts']) ? 1 : 0,
        'idle_notifications' => isset($_POST['idle_notifications']) ? 1 : 0,
        'daily_summary' => isset($_POST['daily_summary']) ? 1 : 0,
        'payroll_reminders' => isset($_POST['payroll_reminders']) ? 1 : 0,
    );
    
    foreach ($settings as $key => $value) {
        update_option('teamflow_' . $key, $value);
    }
    
    echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
}

// Get current settings
$settings = array(
    'screenshot_interval' => get_option('teamflow_screenshot_interval', 5),
    'screenshot_quality' => get_option('teamflow_screenshot_quality', 'high'),
    'recording_fps' => get_option('teamflow_recording_fps', 'ultra-low'),
    'inactivity_threshold' => get_option('teamflow_inactivity_threshold', 60),
    'session_type' => get_option('teamflow_session_type', 'daily'),
    'blur_sensitive' => get_option('teamflow_blur_sensitive', 1),
    'auto_resume' => get_option('teamflow_auto_resume', 0),
    'track_keyboard' => get_option('teamflow_track_keyboard', 1),
    'track_mouse' => get_option('teamflow_track_mouse', 1),
    'track_apps' => get_option('teamflow_track_apps', 1),
    'track_urls' => get_option('teamflow_track_urls', 0),
    'pay_period' => get_option('teamflow_pay_period', 'monthly'),
    'tax_rate' => get_option('teamflow_tax_rate', 20),
    'auto_overtime' => get_option('teamflow_auto_overtime', 1),
    'low_activity_alerts' => get_option('teamflow_low_activity_alerts', 1),
    'idle_notifications' => get_option('teamflow_idle_notifications', 1),
    'daily_summary' => get_option('teamflow_daily_summary', 1),
    'payroll_reminders' => get_option('teamflow_payroll_reminders', 1),
);
?>

<div class="wrap teamflow-wrap">
    <div class="tf-header">
        <h1><i class="fas fa-cog"></i> TeamFlow Settings</h1>
        <p class="tf-subtitle">Configure your team monitoring and time tracking system</p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('teamflow_settings_save'); ?>
        
        <div class="tf-settings-grid">
            <!-- Screenshot Settings -->
            <div class="tf-card">
                <div class="tf-card-header">
                    <h2><i class="fas fa-camera"></i> Screenshot Settings</h2>
                </div>
                <div class="tf-card-body">
                    <div class="tf-form-group">
                        <label class="tf-form-label">Default Tax Rate (%)</label>
                        <input type="number" name="tax_rate" class="tf-form-input" 
                               value="<?php echo esc_attr($settings['tax_rate']); ?>" 
                               min="0" max="50" step="0.5" />
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="auto_overtime" value="1" 
                                   <?php checked($settings['auto_overtime'], 1); ?> />
                            <span>Auto-calculate overtime pay</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="tf-card">
                <div class="tf-card-header">
                    <h2><i class="fas fa-bell"></i> Notifications</h2>
                </div>
                <div class="tf-card-body">
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="low_activity_alerts" value="1" 
                                   <?php checked($settings['low_activity_alerts'], 1); ?> />
                            <span>Send low activity alerts</span>
                        </label>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="idle_notifications" value="1" 
                                   <?php checked($settings['idle_notifications'], 1); ?> />
                            <span>Send idle time notifications</span>
                        </label>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="daily_summary" value="1" 
                                   <?php checked($settings['daily_summary'], 1); ?> />
                            <span>Send daily summary emails</span>
                        </label>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="payroll_reminders" value="1" 
                                   <?php checked($settings['payroll_reminders'], 1); ?> />
                            <span>Send payroll processing reminders</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Privacy Notice -->
        <div class="tf-card tf-privacy-card">
            <div class="tf-card-header">
                <h2><i class="fas fa-shield-alt"></i> Privacy & Security</h2>
            </div>
            <div class="tf-card-body">
                <div class="tf-privacy-notice">
                    <p><strong>Data Protection:</strong> All employee data is encrypted and stored securely. Screenshots and recordings are accessible only to authorized administrators.</p>
                    <p><strong>Employee Rights:</strong> Employees are notified when monitoring is active and can request access to their own data at any time.</p>
                    <p><strong>Compliance:</strong> This system is designed to comply with workplace monitoring laws. Ensure you have proper consent and policies in place.</p>
                    <p><strong>Data Retention:</strong> Screenshots older than 30 days are automatically deleted. Time tracking data is retained indefinitely unless manually deleted.</p>
                </div>
                
                <div class="tf-action-buttons">
                    <a href="<?php echo admin_url('privacy.php'); ?>" class="tf-btn tf-btn-secondary">
                        <i class="fas fa-file-alt"></i> View Privacy Policy
                    </a>
                    <a href="#" class="tf-btn tf-btn-secondary" id="tf-data-retention">
                        <i class="fas fa-database"></i> Data Retention Settings
                    </a>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="tf-form-actions">
            <button type="button" class="tf-btn tf-btn-secondary" onclick="location.reload();">
                <i class="fas fa-undo"></i> Reset to Defaults
            </button>
            <button type="submit" name="teamflow_save_settings" class="tf-btn tf-btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<style>
.tf-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.tf-checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 12px;
    border-radius: 8px;
    transition: background 0.2s;
}

.tf-checkbox-label:hover {
    background: #f8fafc;
}

.tf-checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.tf-radio-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.tf-radio-group label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 12px;
    border-radius: 8px;
    transition: background 0.2s;
}

.tf-radio-group label:hover {
    background: #f8fafc;
}

.tf-privacy-card {
    background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
    border: 2px solid #3b82f6;
}

.tf-privacy-notice {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.tf-privacy-notice p {
    margin-bottom: 12px;
    line-height: 1.6;
}

.tf-action-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.tf-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 24px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.tf-btn-secondary {
    background: #f1f5f9;
    color: #475569;
}

.tf-btn-secondary:hover {
    background: #e2e8f0;
}

@media (max-width: 768px) {
    .tf-settings-grid {
        grid-template-columns: 1fr;
    }
    
    .tf-form-actions {
        flex-direction: column;
    }
    
    .tf-form-actions button {
        width: 100%;
    }
}
</style>
group">
                        <label class="tf-form-label">Screenshot Interval (minutes)</label>
                        <input type="number" name="screenshot_interval" class="tf-form-input" 
                               value="<?php echo esc_attr($settings['screenshot_interval']); ?>" 
                               min="1" max="60" />
                        <p class="description">How often screenshots are captured</p>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-form-label">Screenshot Quality</label>
                        <select name="screenshot_quality" class="tf-form-select">
                            <option value="high" <?php selected($settings['screenshot_quality'], 'high'); ?>>High Quality</option>
                            <option value="medium" <?php selected($settings['screenshot_quality'], 'medium'); ?>>Medium Quality</option>
                            <option value="low" <?php selected($settings['screenshot_quality'], 'low'); ?>>Low Quality (Bandwidth Saver)</option>
                        </select>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="blur_sensitive" value="1" 
                                   <?php checked($settings['blur_sensitive'], 1); ?> />
                            <span>Blur sensitive information in screenshots</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Session Recording Settings -->
            <div class="tf-card">
                <div class="tf-card-header">
                    <h2><i class="fas fa-video"></i> Session Recording Settings</h2>
                </div>
                <div class="tf-card-body">
                    <div class="tf-form-group">
                        <label class="tf-form-label">Recording FPS</label>
                        <select name="recording_fps" class="tf-form-select">
                            <option value="ultra-low" <?php selected($settings['recording_fps'], 'ultra-low'); ?>>Ultra Low (0.5 FPS)</option>
                            <option value="low" <?php selected($settings['recording_fps'], 'low'); ?>>Low (1 FPS)</option>
                            <option value="medium" <?php selected($settings['recording_fps'], 'medium'); ?>>Medium (5 FPS)</option>
                            <option value="high" <?php selected($settings['recording_fps'], 'high'); ?>>High (15 FPS)</option>
                        </select>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-form-label">Session Type</label>
                        <div class="tf-radio-group">
                            <label>
                                <input type="radio" name="session_type" value="daily" 
                                       <?php checked($settings['session_type'], 'daily'); ?> />
                                Daily Sessions
                            </label>
                            <label>
                                <input type="radio" name="session_type" value="hourly" 
                                       <?php checked($settings['session_type'], 'hourly'); ?> />
                                Hourly Sessions
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timer Settings -->
            <div class="tf-card">
                <div class="tf-card-header">
                    <h2><i class="fas fa-clock"></i> Timer Settings</h2>
                </div>
                <div class="tf-card-body">
                    <div class="tf-form-group">
                        <label class="tf-form-label">Inactivity Threshold (seconds)</label>
                        <input type="number" name="inactivity_threshold" class="tf-form-input" 
                               value="<?php echo esc_attr($settings['inactivity_threshold']); ?>" 
                               min="30" max="300" step="30" />
                        <p class="description">Timer auto-pauses after this many seconds of inactivity</p>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="auto_resume" value="1" 
                                   <?php checked($settings['auto_resume'], 1); ?> />
                            <span>Auto-resume timer when activity detected</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Activity Tracking -->
            <div class="tf-card">
                <div class="tf-card-header">
                    <h2><i class="fas fa-chart-line"></i> Activity Tracking</h2>
                </div>
                <div class="tf-card-body">
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="track_keyboard" value="1" 
                                   <?php checked($settings['track_keyboard'], 1); ?> />
                            <span>Track keyboard activity</span>
                        </label>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="track_mouse" value="1" 
                                   <?php checked($settings['track_mouse'], 1); ?> />
                            <span>Track mouse activity</span>
                        </label>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="track_apps" value="1" 
                                   <?php checked($settings['track_apps'], 1); ?> />
                            <span>Track application usage</span>
                        </label>
                    </div>
                    
                    <div class="tf-form-group">
                        <label class="tf-checkbox-label">
                            <input type="checkbox" name="track_urls" value="1" 
                                   <?php checked($settings['track_urls'], 1); ?> />
                            <span>Track URL history</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Payroll Settings -->
            <div class="tf-card">
                <div class="tf-card-header">
                    <h2><i class="fas fa-dollar-sign"></i> Payroll Settings</h2>
                </div>
                <div class="tf-card-body">
                    <div class="tf-form-group">
                        <label class="tf-form-label">Pay Period</label>
                        <select name="pay_period" class="tf-form-select">
                            <option value="weekly" <?php selected($settings['pay_period'], 'weekly'); ?>>Weekly</option>
                            <option value="biweekly" <?php selected($settings['pay_period'], 'biweekly'); ?>>Bi-weekly</option>
                            <option value="monthly" <?php selected($settings['pay_period'], 'monthly'); ?>>Monthly</option>
                            <option value="semimonthly" <?php selected($settings['pay_period'], 'semimonthly'); ?>>Semi-monthly</option>
                        </select>
                    </div>
                    
                    <div class="tf-form-