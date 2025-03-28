#!/bin/bash

# Path to your WordPress installation - UPDATE THIS!
WP_PATH="/var/www/html/wordpress"
# WordPress site URL - UPDATE THIS!
WP_URL="http://localhost/wordpress"

# Log file
LOG_FILE="/var/log/wp-smart-cron.log"
RAPID_MODE_FILE="/tmp/bgm_rapid_mode"

# Ensure log directory exists
touch "$LOG_FILE"

# Function to log with timestamp
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "$LOG_FILE"
}

# Function to trigger WordPress cron with error handling
trigger_wp_cron() {
    local response
    response=$(wget -q -O - "${WP_URL}/wp-cron.php?doing_wp_cron=$(date +%s%N)" 2>&1)
    if [ $? -eq 0 ]; then
        log_message "Successfully triggered WordPress cron"
        return 0
    else
        log_message "ERROR: Failed to trigger WordPress cron: $response"
        return 1
    fi
}

# Check if WordPress is accessible
log_message "Checking WordPress accessibility..."
if ! wget -q -O /dev/null "${WP_URL}/wp-cron.php"; then
    log_message "ERROR: WordPress site is not accessible at ${WP_URL}"
    exit 1
fi

# Function to check if update is in progress or pending
check_update_status() {
    local status_info
    status_info=$(php -r "
        require_once('${WP_PATH}/wp-load.php');
        require_once('${WP_PATH}/wp-includes/cron.php');
        
        \$result = array(
            'update_in_progress' => false,
            'update_pending' => false,
            'reason' => ''
        );
        
        // Check if update is in progress
        \$status = get_transient('bgm_update_in_progress');
        if (\$status) {
            \$result['update_in_progress'] = true;
            \$result['reason'] = 'Update in progress (transient set)';
        }
        
        // Check update progress
        \$update_progress = get_option('bgm_update_progress', array());
        if (!empty(\$update_progress) && isset(\$update_progress['status'])) {
            if (\$update_progress['status'] === 'updating') {
                \$result['update_in_progress'] = true;
                \$result['reason'] = 'Update status is updating';
            }
            
            // Check if there's been recent progress (last 5 minutes)
            if (!empty(\$update_progress['start_time']) && \$update_progress['status'] !== 'completed') {
                \$last_update = strtotime(\$update_progress['start_time']);
                \$now = time();
                if ((\$now - \$last_update) < 300) {
                    \$result['update_in_progress'] = true;
                    \$result['reason'] = 'Recent update activity detected';
                }
            }
        }
        
        // Check if update is scheduled
        \$crons = _get_cron_array();
        foreach(\$crons as \$timestamp => \$cron) {
            if (isset(\$cron['bgm_auto_update_games']) || isset(\$cron['bgm_process_update_batch'])) {
                \$time_to_run = \$timestamp - time();
                if (\$time_to_run <= 60) {
                    \$result['update_pending'] = true;
                    \$result['reason'] = 'Update scheduled within next minute';
                    break;
                }
            }
        }
        
        echo json_encode(\$result);
    ")
    
    echo "$status_info"
}

# Main loop
while true; do
    # Get update status
    status_json=$(check_update_status)
    update_in_progress=$(echo "$status_json" | php -r 'echo json_decode(file_get_contents("php://stdin"))->update_in_progress ? "YES" : "NO";')
    update_pending=$(echo "$status_json" | php -r 'echo json_decode(file_get_contents("php://stdin"))->update_pending ? "YES" : "NO";')
    reason=$(echo "$status_json" | php -r 'echo json_decode(file_get_contents("php://stdin"))->reason;')
    
    log_message "Status check - In Progress: $update_in_progress, Pending: $update_pending ($reason)"
    
    # Determine if we need rapid mode
    if [ "$update_in_progress" = "YES" ] || [ "$update_pending" = "YES" ]; then
        if [ ! -f "$RAPID_MODE_FILE" ]; then
            log_message "Entering rapid mode: $reason"
            touch "$RAPID_MODE_FILE"
        fi
        trigger_wp_cron
    else
        if [ -f "$RAPID_MODE_FILE" ]; then
            log_message "Exiting rapid mode: No active or pending updates"
            rm "$RAPID_MODE_FILE"
        fi
        trigger_wp_cron
        sleep 300  # Sleep for 5 minutes in normal mode
    fi
done