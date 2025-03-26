<?php
/**
 * Manages game updates from BoardGameGeek
 */
class BGM_Update_Manager {
    /**
     * Constructor - register hooks
     */
    public function __construct() {
        // Register AJAX endpoints
        add_action('wp_ajax_bgm_start_games_update', array($this, 'ajax_start_games_update'));
        add_action('wp_ajax_bgm_pause_games_update', array($this, 'ajax_pause_games_update'));
        add_action('wp_ajax_bgm_resume_games_update', array($this, 'ajax_resume_games_update'));
        add_action('wp_ajax_bgm_stop_games_update', array($this, 'ajax_stop_games_update'));
        add_action('wp_ajax_bgm_get_update_progress', array($this, 'ajax_get_update_progress'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Register cron schedules
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // Register cron hooks
        add_action('bgm_auto_update_games', array($this, 'run_auto_update'));
        
        // Setup or remove cron job based on settings
        add_action('update_option_bgm_update_frequency', array($this, 'setup_cron_schedule'), 10, 2);
        
        // Setup cron on plugin activation
        register_activation_hook(BGM_PLUGIN_FILE, array($this, 'activate_cron'));
        
        // Remove cron on plugin deactivation
        register_deactivation_hook(BGM_PLUGIN_FILE, array($this, 'deactivate_cron'));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('bgm_update_settings', 'bgm_update_frequency');
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display' => __('Once Monthly')
        );
        
        return $schedules;
    }
    
    /**
     * Setup cron schedule when settings are updated
     */
    public function setup_cron_schedule($old_value, $new_value) {
        // Clear existing scheduled event
        wp_clear_scheduled_hook('bgm_auto_update_games');
        
        // If not disabled, schedule new event
        if ($new_value !== 'disabled') {
            wp_schedule_event(time(), $new_value, 'bgm_auto_update_games');
        }
    }
    
    /**
     * Activate cron schedule on plugin activation
     */
    public function activate_cron() {
        // Only schedule if not already scheduled
        if (!wp_next_scheduled('bgm_auto_update_games')) {
            $frequency = get_option('bgm_update_frequency', 'weekly');
            
            if ($frequency !== 'disabled') {
                wp_schedule_event(time(), $frequency, 'bgm_auto_update_games');
            }
        }
    }
    
    /**
     * Deactivate cron schedule on plugin deactivation
     */
    public function deactivate_cron() {
        wp_clear_scheduled_hook('bgm_auto_update_games');
    }
    
    /**
     * Run automatic update process
     */
    public function run_auto_update() {
        // Only proceed if no update is already in progress
        if (!get_transient('bgm_update_in_progress')) {
            // Log the start of auto-update
            $this->log_update('Starting automatic update process for ALL games');
            
            // Update the last auto-update timestamp
            update_option('bgm_last_auto_update', current_time('mysql'));
            
            // Start the update process for ALL games (not just ones older than a month)
            // false, false = don't filter by missing or old games
            // null = no time filter
            $this->start_update(false, false, null);
            
            // The update will run in the background via the WP-Cron
        }
    }
    
    /**
     * AJAX handler for starting a game update
     */
    public function ajax_start_games_update() {
        check_ajax_referer('bgm_update_games_nonce', 'security');
        
        // Check if an update is already in progress
        if (get_transient('bgm_update_in_progress')) {
            wp_send_json_error('An update is already in progress.');
            return;
        }
        
        // Get parameters
        $update_old = isset($_POST['update_old']) ? filter_var($_POST['update_old'], FILTER_VALIDATE_BOOLEAN) : false;
        $timeframe = isset($_POST['timeframe']) ? sanitize_text_field($_POST['timeframe']) : '1week';
        
        // Start the update
        $result = $this->start_update(false, $update_old, $timeframe);
        
        if ($result) {
            wp_send_json_success('Update started');
        } else {
            wp_send_json_error('Failed to start update');
        }
    }
    
    /**
     * AJAX handler for pausing a game update
     */
    public function ajax_pause_games_update() {
        check_ajax_referer('bgm_update_games_nonce', 'security');
        
        // Check if an update is in progress
        if (!get_transient('bgm_update_in_progress')) {
            wp_send_json_error('No update is currently in progress.');
            return;
        }
        
        // Update progress status
        $progress = get_option('bgm_update_progress', []);
        $progress['status'] = 'paused';
        update_option('bgm_update_progress', $progress);
        
        // Log the pause
        $this->log_update('Update paused by user');
        
        wp_send_json_success('Update paused');
    }
    
    /**
     * AJAX handler for resuming a game update
     */
    public function ajax_resume_games_update() {
        check_ajax_referer('bgm_update_games_nonce', 'security');
        
        // Check if an update is in progress
        if (!get_transient('bgm_update_in_progress')) {
            wp_send_json_error('No update is currently in progress.');
            return;
        }
        
        // Update progress status
        $progress = get_option('bgm_update_progress', []);
        
        // Only resume if it was paused
        if ($progress['status'] !== 'paused') {
            wp_send_json_error('Update is not paused.');
            return;
        }
        
        // Set status back to updating
        $progress['status'] = 'updating';
        update_option('bgm_update_progress', $progress);
        
        // Log the resume
        $this->log_update('Update resumed by user');
        
        // Schedule the next batch
        $this->schedule_next_batch($progress['current_offset']);
        
        wp_send_json_success('Update resumed');
    }
    
    /**
     * AJAX handler for stopping a game update
     */
    public function ajax_stop_games_update() {
        check_ajax_referer('bgm_update_games_nonce', 'security');
        
        // Check if an update is in progress
        if (!get_transient('bgm_update_in_progress')) {
            wp_send_json_error('No update is currently in progress.');
            return;
        }
        
        // Update progress status
        $progress = get_option('bgm_update_progress', []);
        $progress['status'] = 'stopped';
        $progress['end_time'] = current_time('mysql');
        update_option('bgm_update_progress', $progress);
        
        // Log the stop
        $this->log_update('Update stopped by user');
        
        // Remove the in-progress flag
        delete_transient('bgm_update_in_progress');
        
        wp_send_json_success('Update stopped');
    }
    
    /**
     * AJAX handler for getting update progress
     */
    public function ajax_get_update_progress() {
        check_ajax_referer('bgm_update_games_nonce', 'security');
        
        // Get the current progress
        $progress = get_option('bgm_update_progress', []);
        
        // Get log messages since last check
        $log_messages = get_option('bgm_update_log', []);
        
        // Find the last log message ID that was sent to the client
        $last_sent_log_id = isset($_GET['last_log_id']) ? intval($_GET['last_log_id']) : -1;
        
        // Only send new log messages
        $new_logs = [];
        foreach ($log_messages as $index => $log) {
            if ($index > $last_sent_log_id) {
                $new_logs[] = $log;
            }
        }
        
        // Add log messages to the response
        $progress['log'] = $new_logs;
        $progress['last_log_id'] = count($log_messages) - 1;
        
        // Don't clear the log
        
        wp_send_json_success($progress);
    }
    
    /**
     * Start the update process
     */
    private function start_update($update_missing, $update_old, $timeframe) {
        global $wpdb;
        
        // Set update in progress flag (expires in 2 hours as a safety measure)
        set_transient('bgm_update_in_progress', true, 2 * HOUR_IN_SECONDS);
        
        // Build query to get games that need updating
        $table_name = $wpdb->prefix . 'bgm_games';
        $where_clauses = [];
        
        if ($update_missing) {
            $where_clauses[] = "(last_updated IS NULL)";
        }
        
        if ($update_old && $timeframe !== null) {
            $interval = '7 DAY'; // Default to 1 week
            
            switch ($timeframe) {
                case '1day':
                    $interval = '1 DAY';
                    break;
                case '1week':
                    $interval = '7 DAY';
                    break;
                case '1month':
                    $interval = '30 DAY';
                    break;
                case '3months':
                    $interval = '90 DAY';
                    break;
            }
            
            $where_clauses[] = "(last_updated < DATE_SUB(NOW(), INTERVAL $interval))";
        }
        
        // If no criteria were selected, update all games
        $where = !empty($where_clauses) ? "WHERE " . implode(' OR ', $where_clauses) : "";
        
        // Count total games to update
        $total_query = "SELECT COUNT(*) FROM $table_name $where";
        $total_games = $wpdb->get_var($total_query);
        
        if ($total_games <= 0) {
            $this->log_update('No games found that need updating.');
            delete_transient('bgm_update_in_progress');
            return false;
        }
        
        // Store the query conditions for future use
        $query_criteria = [
            'update_missing' => $update_missing,
            'update_old' => $update_old,
            'timeframe' => $timeframe,
            'where' => $where
        ];
        
        // Initialize progress data
        $progress = [
            'total' => (int)$total_games,
            'completed' => 0,
            'current_offset' => 0,
            'status' => 'updating',
            'last_updated_game' => '',
            'start_time' => current_time('mysql'),
            'end_time' => '',
            'query_criteria' => $query_criteria, // Store query criteria
            'processed_ids' => [] // Initialize empty array to track processed games
        ];
        
        update_option('bgm_update_progress', $progress);
        
        // Clear any existing log
        update_option('bgm_update_log', []);
        
        // Log the start
        $this->log_update("Starting automatic update for $total_games games");
        
        // Schedule the first batch
        $this->schedule_next_batch(0);
        
        return true;
    }
    
    /**
     * Schedule the next batch of games to update
     */
    private function schedule_next_batch($offset) {
        // Schedule a single event to run as soon as possible
        wp_schedule_single_event(time(), 'bgm_process_update_batch', [$offset]);
        
        // Make sure the event gets run soon
        $this->maybe_trigger_cron();
    }
    
    /**
     * Process a batch of games
     */
    public function process_update_batch($offset) {
        // Check if the update is still in progress
        if (!get_transient('bgm_update_in_progress')) {
            return;
        }
        
        // Get progress data
        $progress = get_option('bgm_update_progress', []);
        
        // Check if the update has been paused or stopped
        if ($progress['status'] === 'paused' || $progress['status'] === 'stopped') {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        
        // Build the query to get a batch of games
        $batch_size = 10; // Process 10 games at a time
        
        // Get the where clause from the stored query criteria
        $where = '';
        if (isset($progress['query_criteria']) && isset($progress['query_criteria']['where'])) {
            $where = $progress['query_criteria']['where'];
        }
        
        // Add a check to exclude already processed games
        $processed_ids = isset($progress['processed_ids']) ? $progress['processed_ids'] : [];
        $exclude_clause = '';
        
        if (!empty($processed_ids)) {
            // Prepare the IDs properly for the query
            $exclude_ids = implode(',', array_map('intval', $processed_ids));
            // Add the NOT IN clause to the WHERE clause correctly
            if (!empty($where)) {
                $where .= " AND bgg_id NOT IN ($exclude_ids)";
            } else {
                $where = "WHERE bgg_id NOT IN ($exclude_ids)";
            }
        }
        
        $query = "SELECT id, bgg_id, name FROM $table_name $where ORDER BY last_updated ASC LIMIT $batch_size";
        
        $games = $wpdb->get_results($query);
        
        if (empty($games)) {
            // No more games to update, mark as complete
            $progress['status'] = 'completed';
            $progress['end_time'] = current_time('mysql');
            // Make sure completed doesn't exceed total
            $progress['completed'] = min($progress['completed'], $progress['total']);
            update_option('bgm_update_progress', $progress);
            
            // Log completion
            $this->log_update("Update completed. {$progress['completed']} games updated successfully.");
            
            // Remove the in-progress flag
            delete_transient('bgm_update_in_progress');
            
            return;
        }
        
        // Process each game in the batch
        foreach ($games as $game) {
            // Skip if game doesn't have a bgg_id (shouldn't happen, but just in case)
            if (empty($game->bgg_id)) {
                continue;
            }
            
            // Check again if the update has been paused or stopped
            $current_progress = get_option('bgm_update_progress', []);
            if ($current_progress['status'] === 'paused' || $current_progress['status'] === 'stopped') {
                return;
            }
            
            // Use the BGG API class to update the game
            $result = $this->update_single_game($game->bgg_id);
            
            if ($result['success']) {
                // Add this game to processed list
                if (!isset($progress['processed_ids'])) {
                    $progress['processed_ids'] = [];
                }
                $progress['processed_ids'][] = $game->bgg_id;
                
                // Increment completed count but don't exceed total
                $progress['completed'] = min($progress['completed'] + 1, $progress['total']);
                $progress['last_updated_game'] = $game->name;
                
                // Log success
                $this->log_update("Updated: {$game->name}", 'success');
            } else {
                // Log error
                $this->log_update("Error updating {$game->name}: {$result['message']}", 'error');
                
                // Still add to processed list to avoid retrying failed items
                if (!isset($progress['processed_ids'])) {
                    $progress['processed_ids'] = [];
                }
                $progress['processed_ids'][] = $game->bgg_id;
            }
            
            // Update progress after each game
            update_option('bgm_update_progress', $progress);
            
            // Small delay to avoid overwhelming the BGG API
            sleep(2);
        }
        
        // Schedule the next batch - don't use offsets anymore, we're using the processed_ids list instead
        $this->schedule_next_batch(0);
    }
    
    /**
     * Update a single game from the BGG API
     */
    private function update_single_game($bgg_id, $retry_count = 0) {
        $max_retries = 3;
        
        try {
            // Attempt to request data from BGG API
            $url = 'https://boardgamegeek.com/xmlapi2/thing?stats=1&id=' . $bgg_id;
            $response = wp_remote_get($url);
            
            if (is_wp_error($response)) {
                if ($retry_count < $max_retries) {
                    // Progressive backoff
                    sleep(($retry_count + 1) * 2);
                    return $this->update_single_game($bgg_id, $retry_count + 1);
                }
                return [
                    'success' => false,
                    'message' => 'Failed to connect to BGG API after multiple attempts'
                ];
            }
            
            $body = wp_remote_retrieve_body($response);
            
            // Process XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            
            if ($xml === false) {
                if ($retry_count < $max_retries) {
                    sleep(($retry_count + 1) * 2);
                    return $this->update_single_game($bgg_id, $retry_count + 1);
                }
                return [
                    'success' => false,
                    'message' => 'Failed to parse BGG API response'
                ];
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'bgm_games';
            
            // Extract basic game data
            $id = (string)$xml->item->attributes()->id;
            $name_actual = (string)$xml->item->name[0]->attributes()->value;
            $name = str_replace("'", "", $name_actual);
            $thumbnail = (string)$xml->item->thumbnail;
            $minplayers = (string)$xml->item->minplayers['value'];
            $maxplayers = (string)$xml->item->maxplayers['value'];
            $minplaytime = (string)$xml->item->minplaytime['value'];
            $maxplaytime = (string)$xml->item->maxplaytime['value'];
            $complexity_actual = (string)$xml->item->statistics->ratings->averageweight['value'];
            $complexity = round((float)$complexity_actual, 2);
            $rating_actual = (string)$xml->item->statistics->ratings->average['value'];
            $rating = round((float)$rating_actual, 2);
            $bgglink = 'https://boardgamegeek.com/boardgame/' . $id;
            $description = (string)$xml->item->description;
            $year_published = isset($xml->item->yearpublished['value']) ? (string)$xml->item->yearpublished['value'] : null;
            
            // Extract categories and mechanics
            $catNames = "";
            $mechNames = "";
            $publisher = "";
            $designer = "";
            
            // Limit number of publishers/designers to prevent very long strings
            $publisherCount = 0;
            $designerCount = 0;
            $maxPublishers = 3;
            $maxDesigners = 3;
            
            foreach ($xml->item->link as $link) {
                if ((string)$link->attributes()->type == "boardgamecategory") {
                    $catNames .= (string)$link->attributes()->value . ", ";
                } elseif ((string)$link->attributes()->type == "boardgamemechanic") {
                    $mechNames .= (string)$link->attributes()->value . ", ";
                } elseif ((string)$link->attributes()->type == "boardgamepublisher" && $publisherCount < $maxPublishers) {
                    $publisher .= (string)$link->attributes()->value . ", ";
                    $publisherCount++;
                } elseif ((string)$link->attributes()->type == "boardgamedesigner" && $designerCount < $maxDesigners) {
                    $designer .= (string)$link->attributes()->value . ", ";
                    $designerCount++;
                }
            }
            
            // Remove trailing commas
            $catNames = rtrim($catNames, ", ");
            $mechNames = rtrim($mechNames, ", ");
            $publisher = rtrim($publisher, ", ");
            $designer = rtrim($designer, ", ");
            
            // Add indication if lists were truncated
            if ($publisherCount >= $maxPublishers) {
                $publisher .= " (and more)";
            }
            if ($designerCount >= $maxDesigners) {
                $designer .= " (and more)";
            }
            
            // Get current timestamp in the WordPress format
            $timestamp = current_time('mysql');
            
            // Update the database with prepared statement to prevent SQL injection
            $result = $wpdb->update(
                $table_name,
                [
                    'name' => $name,
                    'thumb' => $thumbnail,
                    'minplayers' => $minplayers,
                    'maxplayers' => $maxplayers,
                    'minplaytime' => $minplaytime,
                    'maxplaytime' => $maxplaytime,
                    'complexity' => $complexity,
                    'rating' => $rating,
                    'bgglink' => $bgglink,
                    'gamecats' => $catNames,
                    'gamemechs' => $mechNames,
                    'description' => $description,
                    'year_published' => $year_published,
                    'publisher' => $publisher,
                    'designer' => $designer,
                    'last_updated' => $timestamp
                ],
                ['bgg_id' => $bgg_id],
                [
                    '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
                    '%s', '%s', '%s', '%s', '%s', '%s'
                ],
                ['%s']
            );
            
            if ($result !== false) {
                return [
                    'success' => true,
                    'message' => 'Game updated successfully',
                    'name' => $name
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Database update failed: ' . $wpdb->last_error
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Log update progress message with duplicate detection
     */
    private function log_update($message, $type = '') {
        // Get current log
        $log = get_option('bgm_update_log', []);
        
        // If this is a game update message, handle it specially
        if (strpos($message, 'Updated: ') === 0) {
            // Extract the game name from the message
            $game_name = substr($message, 9); // Everything after "Updated: "
            
            // Check if we already logged this game name
            foreach ($log as $entry) {
                if (isset($entry['message']) && strpos($entry['message'], 'Updated: ' . $game_name) === 0) {
                    // Skip adding this duplicate log entry
                    return;
                }
            }
        }
        
        // Add the new message to the log
        $log[] = [
            'message' => $message,
            'type' => $type,
            'time' => current_time('mysql')
        ];
        
        // Keep only the last 100 messages to prevent excessive DB size
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }
        
        update_option('bgm_update_log', $log);
    }
    
    /**
     * Helper to ensure WordPress cron runs immediately if possible
     */
    private function maybe_trigger_cron() {
        if (defined('DOING_CRON') || wp_doing_cron()) {
            return;
        }

        // Try to trigger WP-Cron with a loopback request
        $cron_url = site_url('wp-cron.php?doing_wp_cron=1');
        $args = [
            'blocking' => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'timeout' => 0.01
        ];
        
        wp_remote_post($cron_url, $args);
    }
}