# TeamFlow - WordPress Installation & Setup Guide

## Overview
TeamFlow is a comprehensive team monitoring, time tracking, and payroll management system for WordPress. This guide will help you install and configure the plugin.

## File Structure

```
teamflow/
├── teamflow.php (Main plugin file)
├── uninstall.php
├── includes/
│   ├── class-teamflow-logger.php
│   ├── class-teamflow-database.php
│   ├── class-teamflow-timer.php
│   ├── class-teamflow-monitoring.php
│   ├── class-teamflow-payroll.php
│   ├── class-teamflow-ajax.php
│   └── class-teamflow-shortcodes.php
├── templates/
│   ├── admin/
│   │   ├── dashboard.php
│   │   ├── team.php
│   │   ├── timesheets.php
│   │   ├── payroll.php
│   │   ├── monitoring.php
│   │   └── settings.php
│   ├── employee/
│   │   ├── my-time.php
│   │   └── my-stats.php
│   └── shortcodes/
│       └── timer-widget.php
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
└── README.md
```

## Installation Steps

### 1. Upload Plugin Files
1. Create a folder named `teamflow` in `/wp-content/plugins/`
2. Upload all plugin files maintaining the directory structure above
3. Ensure proper file permissions (644 for files, 755 for directories)

### 2. Activate Plugin
1. Go to WordPress Admin → Plugins
2. Find "TeamFlow - Team Monitoring System"
3. Click "Activate"
4. The plugin will automatically create database tables and default settings

### 3. Configure User Roles
After activation, the plugin creates two custom roles:
- **Team Manager**: Can view all monitoring, manage team, and process payroll
- **Team Member**: Can track their own time and view personal stats

To assign roles:
1. Go to Users → All Users
2. Edit a user
3. Change their role to "Team Manager" or "Team Member"

### 4. Set User Hourly Rates
1. Go to Users → All Users
2. Edit a user
3. In the user profile, add a custom field:
   - **Meta Key**: `teamflow_hourly_rate`
   - **Meta Value**: e.g., `75.00`
4. Save the user

## Configuration

### Basic Settings
Go to **TeamFlow → Settings** to configure:

#### Screenshot Settings
- **Interval**: How often screenshots are captured (1-60 minutes)
- **Quality**: High, Medium, or Low
- **Blur Sensitive Info**: Enable to automatically blur sensitive content

#### Session Recording
- **FPS**: Ultra-low (0.5), Low (1), Medium (5), or High (15)
- **Session Type**: Daily or Hourly sessions

#### Timer Settings
- **Inactivity Threshold**: Auto-pause timer after X seconds (30-300)
- **Auto-resume**: Automatically resume when activity detected

#### Activity Tracking
- Track keyboard activity
- Track mouse activity
- Track application usage
- Track URL history (optional)

#### Payroll Settings
- **Pay Period**: Weekly, Bi-weekly, Monthly, or Semi-monthly
- **Tax Rate**: Default tax deduction percentage
- **Auto-calculate Overtime**: Enable automatic overtime calculation

#### Notifications
- Low activity alerts
- Idle time notifications
- Daily summary emails
- Payroll reminders

## Using Shortcodes

### Timer Widget
Display a time tracking widget anywhere on your site:
```
[teamflow_timer]
```

With options:
```
[teamflow_timer show_projects="yes" show_stats="yes"]
```

### User Stats
Display user statistics:
```
[teamflow_stats period="week"]
```

Options: `period="today|week|month|year"`

### Timesheet
Display recent time entries:
```
[teamflow_timesheet limit="10"]
```

### Full Dashboard (Frontend)
Create a complete employee dashboard:
```
[teamflow_dashboard]
```

## Admin Pages

### Dashboard
- Overview of team activity
- Real-time statistics
- Activity feed
- Quick actions

### Team
- View all team members
- See current status (active/idle/offline)
- View individual stats and productivity
- Live monitoring access

### Timesheets
- View all time entries
- Filter by employee, project, date range
- Export to CSV
- Activity level tracking

### Payroll
- Generate payroll for any period
- Process payments
- View pending and processed payroll
- Export payroll reports

### Monitoring
- Live view of active employees
- Real-time screenshots
- Activity heatmaps
- Idle time alerts

### Settings
- Configure all system settings
- Manage tracking preferences
- Set up notifications
- Privacy and security settings

## API Endpoints (AJAX)

All AJAX endpoints require authentication and nonce verification:

### Timer Actions
- `teamflow_start_timer` - Start time tracking
- `teamflow_pause_timer` - Pause timer
- `teamflow_stop_timer` - Stop and save entry
- `teamflow_update_activity` - Update activity level

### Data Retrieval
- `teamflow_get_team_data` - Get team statistics
- `teamflow_get_user_stats` - Get user statistics
- `teamflow_get_timesheets` - Get timesheet entries
- `teamflow_get_payroll` - Get payroll records

### Monitoring
- `teamflow_upload_screenshot` - Upload screenshot
- `teamflow_get_screenshots` - Retrieve screenshots
- `teamflow_log_activity` - Log keyboard/mouse activity

### Payroll
- `teamflow_generate_payroll` - Generate payroll for period
- `teamflow_process_payroll` - Mark payroll as processed
- `teamflow_export_payroll` - Export payroll CSV

## Database Tables

The plugin creates 6 custom tables:

1. **teamflow_time_entries**: Time tracking records
2. **teamflow_screenshots**: Screenshot storage
3. **teamflow_activity_logs**: Keyboard/mouse/app activity
4. **teamflow_projects**: Project management
5. **teamflow_payroll**: Payroll records
6. **teamflow_user_settings**: User-specific settings

## Extending the Plugin

### Adding Custom Reports
Create custom report templates in `templates/admin/custom-reports.php`

### Custom Activity Tracking
Hook into activity logging:
```php
add_action('teamflow_log_activity', 'my_custom_activity_handler', 10, 3);
```

### Custom Payroll Calculations
Filter payroll before saving:
```php
add_filter('teamflow_calculate_payroll', 'my_payroll_calculation', 10, 2);
```

### Adding Integrations
Use WordPress hooks to integrate with other plugins:
```php
add_action('teamflow_timer_started', 'integrate_with_other_plugin');
add_action('teamflow_payroll_processed', 'send_to_accounting_system');
```

## Security Considerations

1. **Screenshots**: Stored in WordPress uploads directory with unique filenames
2. **Activity Data**: Encrypted in transit via HTTPS
3. **User Data**: Access controlled by WordPress capabilities
4. **AJAX Requests**: Protected with nonce verification
5. **SQL Queries**: All use prepared statements

## Performance Optimization

1. **Screenshot Cleanup**: Automatically removes screenshots older than 30 days
2. **Database Indexing**: All tables properly indexed for fast queries
3. **Caching**: Use object caching for frequently accessed data
4. **Lazy Loading**: Screenshots loaded on demand

## Troubleshooting

### Timer Not Starting
- Check JavaScript console for errors
- Verify AJAX URL is correct
- Ensure user has `track_time` capability

### Screenshots Not Uploading
- Check file upload permissions
- Verify upload_max_filesize in php.ini
- Check WordPress uploads directory permissions

### Payroll Not Generating
- Ensure user has hourly rate set
- Verify date range has time entries
- Check for completed time entries

### Activity Not Tracking
- Verify tracking settings are enabled
- Check browser console for errors
- Ensure JavaScript is not blocked

## Support & Updates

### Documentation
Full documentation available at plugin documentation site

### Updates
Check for updates regularly in WordPress admin

### Compatibility
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+
- Modern browsers (Chrome, Firefox, Safari, Edge)

## License
GPL v2 or later

## Credits
Developed with WordPress best practices and security standards
