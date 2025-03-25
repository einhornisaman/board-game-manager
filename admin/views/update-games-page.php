<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Render the update games page
 */
function bgm_render_update_games_page() {
    // Get update settings
    $update_frequency = get_option('bgm_update_frequency', 'weekly');
    $last_auto_update = get_option('bgm_last_auto_update', 'Never');
    
    // Check if an update is in progress
    $update_in_progress = get_transient('bgm_update_in_progress');
    $update_progress = get_option('bgm_update_progress', [
        'total' => 0,
        'completed' => 0,
        'current_offset' => 0,
        'status' => 'idle',
        'last_updated_game' => '',
        'start_time' => '',
        'end_time' => ''
    ]);
    
    // Format timestamps if they exist
    $start_time_formatted = !empty($update_progress['start_time']) ? 
        date_i18n('F j, Y g:i a', strtotime($update_progress['start_time'])) : 'N/A';
    $end_time_formatted = !empty($update_progress['end_time']) ? 
        date_i18n('F j, Y g:i a', strtotime($update_progress['end_time'])) : 'N/A';
    
    // Calculate progress percentage
    $progress_percentage = 0;
    if ($update_progress['total'] > 0) {
        $progress_percentage = round(($update_progress['completed'] / $update_progress['total']) * 100);
    }

    // Security nonce for AJAX operations
    $nonce = wp_create_nonce('bgm_update_games_nonce');
    
    ?>
    <div class="wrap">
        <h1>Update Games</h1>
        
        <div class="card">
            <h2>Manual Update</h2>
            <p>Update all games in your collection with the latest information from BoardGameGeek.</p>
            
            <?php if ($update_in_progress): ?>
                <div class="update-status-container">
                    <h3>Update in Progress</h3>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo esc_attr(min($progress_percentage, 100)); ?>%">
                            <span><?php echo esc_html(min($progress_percentage, 100)); ?>%</span>
                        </div>
                    </div>
                    <p>Status: <?php echo esc_html(ucfirst($update_progress['status'])); ?></p>
                    <p>Started: <?php echo esc_html($start_time_formatted); ?></p>
                    <p>Games updated: <?php echo esc_html(min($update_progress['completed'], $update_progress['total'])); ?> of <?php echo esc_html($update_progress['total']); ?></p>
                    <?php if (!empty($update_progress['last_updated_game'])): ?>
                        <p>Last game updated: <?php echo esc_html($update_progress['last_updated_game']); ?></p>
                    <?php endif; ?>
                    
                    <button id="pause-update" class="button button-secondary" <?php echo $update_progress['status'] === 'paused' ? 'disabled' : ''; ?>>Pause Update</button>
                    <button id="resume-update" class="button button-primary" <?php echo $update_progress['status'] !== 'paused' ? 'disabled' : ''; ?>>Resume Update</button>
                    <button id="stop-update" class="button button-link-delete">Stop Update</button>
                </div>
            <?php else: ?>
                <div class="update-controls">
                    <button id="start-update" class="button button-primary">Start Full Update</button>
                    
                    <div class="update-options" style="margin-top: 15px;">
                        <label>
                            <input type="checkbox" id="update-option-old">
                            Update games older than:
                            <select id="update-option-timeframe">
                                <option value="1day">1 day</option>
                                <option value="1week" selected>1 week</option>
                                <option value="1month">1 month</option>
                                <option value="3months">3 months</option>
                            </select>
                        </label>
                    </div>
                </div>
                
                <?php if (!empty($update_progress['end_time'])): ?>
                <div class="previous-update-info">
                    <h3>Previous Update</h3>
                    <p>Last completed: <?php echo esc_html($end_time_formatted); ?></p>
                    <p>Games updated: <?php echo esc_html(min($update_progress['completed'], $update_progress['total'])); ?> of <?php echo esc_html($update_progress['total']); ?></p>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Update Log</h2>
            <div id="update-log" class="update-log-container" style="max-height: 300px; overflow-y: auto; background: #f8f8f8; padding: 10px; border: 1px solid #ddd;">
                <div class="log-message">Waiting for update to start...</div>
            </div>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Automatic Updates</h2>
            <p>Configure BoardGameGeek data to be automatically refreshed on a schedule.</p>
            
            <form method="post" action="options.php" id="auto-update-settings">
                <?php settings_fields('bgm_update_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Update Frequency</th>
                        <td>
                            <select name="bgm_update_frequency">
                                <option value="hourly" <?php selected($update_frequency, 'hourly'); ?>>Hourly</option>
                                <option value="twicedaily" <?php selected($update_frequency, 'twicedaily'); ?>>Twice Daily</option>
                                <option value="daily" <?php selected($update_frequency, 'daily'); ?>>Daily</option>
                                <option value="weekly" <?php selected($update_frequency, 'weekly'); ?>>Weekly</option>
                                <option value="monthly" <?php selected($update_frequency, 'monthly'); ?>>Monthly</option>
                                <option value="disabled" <?php selected($update_frequency, 'disabled'); ?>>Disabled</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Last Automatic Update</th>
                        <td><?php echo esc_html($last_auto_update); ?></td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
    </div>

    <style>
        .card {
            background: white;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        .progress-bar-container {
            width: 100%;
            background-color: #f0f0f0;
            border-radius: 4px;
            margin: 15px 0;
            overflow: hidden;
        }
        .progress-bar {
            height: 30px;
            background-color: #2271b1;
            text-align: center;
            line-height: 30px;
            color: white;
            transition: width 0.3s ease;
        }
        .update-options label {
            display: block;
            margin-bottom: 10px;
        }
        .log-message {
            margin-bottom: 5px;
            font-family: monospace;
        }
        .log-message.error {
            color: #d63638;
        }
        .log-message.success {
            color: #00a32a;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        const updateLogContainer = $('#update-log');
        let updateCheckInterval;
        
        // Function to log messages
        function logMessage(message, type = '') {
            const now = new Date();
            const timestamp = now.toLocaleTimeString();
            const logEntry = $('<div class="log-message ' + type + '">[' + timestamp + '] ' + message + '</div>');
            updateLogContainer.append(logEntry);
            updateLogContainer.scrollTop(updateLogContainer[0].scrollHeight);
        }
        
        // Function to update the progress display
        
        function updateProgressDisplay() {
            // Track last log ID we've received
            var lastLogId = -1;
            
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'bgm_get_update_progress',
                    security: '<?php echo $nonce; ?>',
                    last_log_id: lastLogId
                },
                success: function(response) {
                    if (response.success) {
                        const progress = response.data;
                        
                        // Update the progress bar
                        const percentage = progress.total > 0 ? Math.round((progress.completed / progress.total) * 100) : 0;
                        $('.progress-bar').css('width', percentage + '%').find('span').text(percentage + '%');
                        
                        // Update status text
                        $('.update-status-container p:nth-child(3)').text('Status: ' + progress.status.charAt(0).toUpperCase() + progress.status.slice(1));
                        $('.update-status-container p:nth-child(5)').text('Games updated: ' + progress.completed + ' of ' + progress.total);
                        
                        if (progress.last_updated_game) {
                            $('.update-status-container p:nth-child(6)').text('Last game updated: ' + progress.last_updated_game);
                        }
                        
                        // Log any new messages
                        if (progress.log && progress.log.length > 0) {
                            progress.log.forEach(logEntry => {
                                logMessage(logEntry.message, logEntry.type);
                            });
                        }
                        
                        // Update the last log ID we've received
                        if (progress.last_log_id !== undefined) {
                            lastLogId = progress.last_log_id;
                        }
                        
                        // Enable/disable pause and resume buttons based on status
                        if (progress.status === 'paused') {
                            $('#pause-update').prop('disabled', true);
                            $('#resume-update').prop('disabled', false);
                        } else if (progress.status === 'updating') {
                            $('#pause-update').prop('disabled', false);
                            $('#resume-update').prop('disabled', true);
                        }
                        
                        // If the update is finished, refresh the page
                        if (progress.status === 'completed' || progress.status === 'stopped') {
                            clearInterval(updateCheckInterval);
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        }
                    }
                }
            });
        }
        
        // Start update button click handler
        $('#start-update').on('click', function() {
            const updateOld = $('#update-option-old').is(':checked');
            const timeframe = $('#update-option-timeframe').val();
            
            // Confirm before proceeding
            if (!confirm('Are you sure you want to start updating games from BoardGameGeek? This process may take some time.')) {
                return;
            }
            
            $(this).prop('disabled', true).text('Starting update...');
            
            // Clear the log
            updateLogContainer.empty();
            logMessage('Starting update process...');
            
            // Start the update process
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bgm_start_games_update',
                    update_old: updateOld,
                    timeframe: timeframe,
                    security: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        logMessage('Update started successfully.', 'success');
                        
                        // Start checking for updates
                        updateCheckInterval = setInterval(updateProgressDisplay, 3000);
                        
                        // Refresh the page to show progress UI
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        logMessage('Error: ' + response.data, 'error');
                        $('#start-update').prop('disabled', false).text('Start Full Update');
                    }
                },
                error: function() {
                    logMessage('Connection error while starting update.', 'error');
                    $('#start-update').prop('disabled', false).text('Start Full Update');
                }
            });
        });
        
        // Pause update button click handler
        $('#pause-update').on('click', function() {
            $(this).prop('disabled', true);
            logMessage('Pausing update...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bgm_pause_games_update',
                    security: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        logMessage('Update paused.', 'success');
                        $('#resume-update').prop('disabled', false);
                    } else {
                        logMessage('Error: ' + response.data, 'error');
                        $('#pause-update').prop('disabled', false);
                    }
                }
            });
        });
        
        // Resume update button click handler
        $('#resume-update').on('click', function() {
            $(this).prop('disabled', true);
            logMessage('Resuming update...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bgm_resume_games_update',
                    security: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        logMessage('Update resumed.', 'success');
                        $('#pause-update').prop('disabled', false);
                    } else {
                        logMessage('Error: ' + response.data, 'error');
                        $('#resume-update').prop('disabled', false);
                    }
                }
            });
        });
        
        // Stop update button click handler
        $('#stop-update').on('click', function() {
            if (!confirm('Are you sure you want to stop the update process? Progress will be lost.')) {
                return;
            }
            
            $(this).prop('disabled', true);
            logMessage('Stopping update...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bgm_stop_games_update',
                    security: '<?php echo $nonce; ?>'
                },
                success: function(response) {
                    if (response.success) {
                        logMessage('Update stopped.', 'success');
                        // Refresh the page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        logMessage('Error: ' + response.data, 'error');
                        $('#stop-update').prop('disabled', false);
                    }
                }
            });
        });
        
        // Start the progress check if an update is in progress
        <?php if ($update_in_progress): ?>
        updateCheckInterval = setInterval(updateProgressDisplay, 3000);
        updateProgressDisplay(); // Initial update
        <?php endif; ?>
    });
    </script>
    <?php
}