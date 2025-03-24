<?php
/**
 * Admin functionality for Board Game Manager
 */
class BGM_Admin {
    /**
     * Constructor - register admin hooks
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_bgm_search_games_ajax', array($this, 'ajax_search_games_ajax'));
        add_action('wp_ajax_bgm_get_game_ajax', array($this, 'ajax_get_game'));
        add_action('wp_ajax_bgm_update_game_ajax', array($this, 'ajax_update_game'));
        add_action('wp_ajax_bgm_delete_game_ajax', array($this, 'ajax_delete_game'));
        add_action('wp_ajax_bgm_fetch_game_by_id', array($this, 'ajax_fetch_game_by_id'));
        add_action('wp_ajax_bgm_start_games_update', array($this, 'ajax_start_games_update'));
        add_action('wp_ajax_bgm_pause_games_update', array($this, 'ajax_pause_games_update'));
        add_action('wp_ajax_bgm_resume_games_update', array($this, 'ajax_resume_games_update'));
        add_action('wp_ajax_bgm_stop_games_update', array($this, 'ajax_stop_games_update'));
        add_action('wp_ajax_bgm_get_update_progress', array($this, 'ajax_get_update_progress'));
    }
    
        /**
     * Register admin menu items
     */
    public function register_menu() {
        add_menu_page(
            'Game Lists',             // Changed page title
            'Game Lists',             // Changed menu title (the blue section)
            'manage_options',
            'board-game-manager',     // Keep this slug the same
            array($this, 'admin_page'),
            'dashicons-games',
            30
        );
        
        // Add the Master Game List as first submenu to replace the default
        add_submenu_page(
            'board-game-manager',
            'Master Game List',       // Changed page title
            'Master Game List',       // Changed submenu title (right under the blue section)
            'manage_options',
            'board-game-manager',     // Same as parent slug
            array($this, 'admin_page')
        );
        
        // Add Games submenu (keep this as is)
        add_submenu_page(
            'board-game-manager',
            'Add Games',
            'Add Games',
            'manage_options',
            'bgm-add-games',          // If you've changed this from bgm-add-remove
            array($this, 'add_game_page')  // If you've changed this from add_remove_page
        );

        // Add new Update Games submenu
        add_submenu_page(
            'board-game-manager',
            'Update Games',
            'Update Games',
            'manage_options',
            'bgm-update-games',
            array($this, 'update_games_page')
        );
    }
    
    /**
     * Enqueue admin CSS and JavaScript
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'board-game-manager') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'bgm-admin-css',
            BGM_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            BGM_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'bgm-admin-js',
            BGM_PLUGIN_URL . 'admin/assets/js/admin.js',
            array('jquery'),
            BGM_VERSION,
            true
        );
    }
    
    /**
     * Display main admin page
     */
    public function admin_page() {
        // Include the admin page file
        require_once BGM_PLUGIN_DIR . 'admin/views/admin-page.php';
        
        // Call the render function
        bgm_render_admin_page();
    }
    
        /**
     * Display add games page
     */
    public function add_game_page() {
        // Process BGG search if form submitted
        $search_results = array();
        $result = array();
        
        if (isset($_POST['bgg_search_term']) && !empty($_POST['bgg_search_term'])) {
            $search_term = sanitize_text_field($_POST['bgg_search_term']);
            $search_results = $this->search_bgg_games($search_term);
        }
        
        // Process game addition
        if (isset($_POST['add_game_id']) && !empty($_POST['add_game_id'])) {
            $bgg_id = intval($_POST['add_game_id']);
            $result = BGM_BGG_API::import_game($bgg_id);
        }
        
        // Include the view file
        require_once BGM_PLUGIN_DIR . 'admin/views/add-game-page.php';
        
        // Call the render function
        bgm_render_add_game_page($search_results, $result);
    }

    /**
     * Display the update games page
     */
    public function update_games_page() {
        // Include the view file
        require_once BGM_PLUGIN_DIR . 'admin/views/update-games-page.php';
        
        // Call the render function
        bgm_render_update_games_page();
    }
    
    /**
     * Search BGG for games with rank sorting
     */
    private function search_bgg_games($search_term) {
        $search_term = urlencode($search_term);
        
        // Get selected thing types or default to boardgame
        $thing_types = isset($_POST['thing_types']) && !empty($_POST['thing_types']) 
            ? $_POST['thing_types'] 
            : array('boardgame');

        // Sanitize thing types array
        $thing_types = array_map('sanitize_text_field', $thing_types);

        // Build type parameter
        $type_param = implode(',', $thing_types);

        // Build the search URL with just the type parameter
        $search_url = "https://boardgamegeek.com/xmlapi2/search?query=$search_term&type=$type_param";

        // Debug log the search URL
        error_log('BGG Search URL: ' . $search_url);
        
        $response = wp_remote_get($search_url);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Failed to connect to BoardGameGeek API: ' . $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Process XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if ($xml === false) {
            return array(
                'success' => false,
                'message' => 'Failed to parse BoardGameGeek API response.'
            );
        }
        
        $total = (int)$xml['total'];
        
        if ($total === 0) {
            return array(
                'success' => false,
                'message' => 'No games found matching your search criteria.'
            );
        }
        
        // First stage: Collect basic info from search results
        $game_ids = array();
        $temp_results = array();

        foreach ($xml->item as $item) {
            $game_id = (string)$item['id'];
            $name = "";
            $thing_type = (string)$item['type'];
            
            // Find primary name
            foreach ($item->name as $name_node) {
                if ((string)$name_node['type'] === 'primary') {
                    $name = (string)$name_node['value'];
                    break;
                }
            }
            
            // If no primary name found, use the first name
            if (empty($name) && isset($item->name[0])) {
                $name = (string)$item->name[0]['value'];
            }
            
            // Year published
            $year = (isset($item->yearpublished)) ? (string)$item->yearpublished['value'] : 'N/A';
            
            // Add to temp results if we have a name
            if (!empty($name)) {
                $temp_results[$game_id] = array(
                    'id' => $game_id,
                    'name' => $name,
                    'year' => $year,
                    'type' => $thing_type,
                    'thumbnail' => '', // Will be populated in the next step
                    'rank' => 999999 // Default high rank for sorting purposes
                );
                
                $game_ids[] = $game_id;
            }
        }
        
        // Second stage: Get detailed information with ranks and apply accurate filtering
        if (!empty($game_ids)) {
            $batch_size = 10;
            $batches = array_chunk($game_ids, $batch_size);
            $filtered_game_ids = array();
            
            foreach ($batches as $batch) {
                $ids_string = implode(',', $batch);
                $thing_url = "https://boardgamegeek.com/xmlapi2/thing?id=$ids_string&stats=1"; // Added stats=1 to get ranking info
                
                $thing_response = wp_remote_get($thing_url);
                
                if (!is_wp_error($thing_response)) {
                    $thing_body = wp_remote_retrieve_body($thing_response);
                    $thing_xml = simplexml_load_string($thing_body);
                    
                    if ($thing_xml !== false) {
                        foreach ($thing_xml->item as $item) {
                            $id = (string)$item['id'];
                            $type = (string)$item['type'];
                            $name = isset($temp_results[$id]['name']) ? $temp_results[$id]['name'] : '';
                            
                            error_log("Full item details: '$name' (ID: $id) - Detailed Type: $type");
                            
                            // Apply strict filtering based on the selected types
                            // If this is an expansion and expansions weren't selected, skip it
                            if ($type === 'boardgameexpansion' && !in_array('boardgameexpansion', $thing_types)) {
                                error_log("FILTERING OUT: '$name' - It's an expansion");
                                unset($temp_results[$id]);
                                continue;
                            }
                            
                            // If it's an accessory and accessories weren't selected, skip it
                            if ($type === 'boardgameaccessory' && !in_array('boardgameaccessory', $thing_types)) {
                                error_log("FILTERING OUT: '$name' - It's an accessory");
                                unset($temp_results[$id]);
                                continue;
                            }
                            
                            // If it's an RPG item and RPG items weren't selected, skip it
                            if ($type === 'rpgitem' && !in_array('rpgitem', $thing_types)) {
                                error_log("FILTERING OUT: '$name' - It's an RPG item");
                                unset($temp_results[$id]);
                                continue;
                            }
                            
                            // If it's a video game and video games weren't selected, skip it
                            if ($type === 'videogame' && !in_array('videogame', $thing_types)) {
                                error_log("FILTERING OUT: '$name' - It's a video game");
                                unset($temp_results[$id]);
                                continue;
                            }
                            
                            // Get BGG rank from statistics if available
                            $rank = 999999; // Default high rank for unranked games
                            if (isset($item->statistics->ratings->ranks->rank)) {
                                foreach ($item->statistics->ratings->ranks->rank as $rank_item) {
                                    if ((string)$rank_item['type'] === 'subtype' && (string)$rank_item['name'] === 'boardgame') {
                                        if ((string)$rank_item['value'] !== 'Not Ranked') {
                                            $rank = (int)$rank_item['value'];
                                        }
                                        break;
                                    }
                                }
                            }
                            
                            // If we get here, keep this item and update its info
                            $thumbnail = isset($item->thumbnail) ? (string)$item->thumbnail : '';
                            if (isset($temp_results[$id])) {
                                $temp_results[$id]['thumbnail'] = $thumbnail;
                                $temp_results[$id]['type'] = $type; // Update type with the more accurate one
                                $temp_results[$id]['rank'] = $rank; // Store the rank
                                $filtered_game_ids[] = $id;
                                
                                error_log("KEEPING: '$name' - Type: $type, Rank: $rank");
                            }
                        }
                    }
                }
            }
            
            // Only keep games that passed the filtering
            foreach ($temp_results as $id => $game) {
                if (!in_array($id, $filtered_game_ids)) {
                    unset($temp_results[$id]);
                }
            }
        }
        
        // Sort games by rank (ascending - lowest rank first)
        uasort($temp_results, function($a, $b) {
            return $a['rank'] - $b['rank'];
        });
        
        // Format the final results
        $results = array();
        foreach ($temp_results as $result) {
            $results[] = $result;
        }
        
        return array(
            'success' => true,
            'games' => $results
        );
    }
    
        /**
     * AJAX handler for real-time game search
     */
    public function ajax_search_games_ajax() {
        // Verify nonce
        check_ajax_referer('bgm_edit_game_nonce', 'security');
        
        $search = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        // Get per_page setting from request, default to 20 if not provided
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        
        // Sanitize per_page to only allow specific values
        $allowed_per_page = array(10, 20, 50, 100);
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 20; // Default if invalid
        }
        
        // Get current page, default to 1
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Calculate offset
        $offset = ($current_page - 1) * $per_page;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        
        // Get the total number of games in the database
        $total_games = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Set up the WHERE clause based on search term
        $where_clause = empty($search) ? '' : $wpdb->prepare("WHERE name LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        
        // Get total matching results count
        $matching_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");
        
        // Get paginated results
        $games = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, thumb, bgglink, year_published, minplayers, maxplayers, 
                        complexity, rating, bgg_id
                FROM $table_name $where_clause
                ORDER BY name 
                LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        
        if ($wpdb->last_error) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Database error: ' . $wpdb->last_error
            ));
            return;
        }
        
        $results = array();
        foreach ($games as $game) {
            $results[] = array(
                'id' => $game->id,
                'name' => $game->name,
                'thumb' => $game->thumb,
                'bgglink' => $game->bgglink,
                'year' => $game->year_published,
                'minplayers' => $game->minplayers,
                'maxplayers' => $game->maxplayers,
                'complexity' => $game->complexity,
                'rating' => $game->rating,
                'bgg_id' => $game->bgg_id
            );
        }
        
        // Calculate total pages
        $total_pages = ceil($matching_count / $per_page);
        
        wp_send_json(array(
            'success' => true,
            'count' => count($results),
            'matching_count' => $matching_count,
            'total_games' => $total_games,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
            'data' => $results
        ));
    }
    
    /**
     * AJAX handler for getting game data for edit form
     */
    public function ajax_get_game() {
        // Verify nonce
        check_ajax_referer('bgm_edit_game_nonce', 'security');
        
        // Get game ID
        $game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;
        
        if ($game_id <= 0) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Invalid game ID.'
            ));
            return;
        }
        
        // Get game data
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        
        $game = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $game_id),
            ARRAY_A
        );
        
        if (!$game) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Game not found.'
            ));
            return;
        }
        
        wp_send_json(array(
            'success' => true,
            'game' => $game
        ));
    }
    
    /**
     * AJAX handler for updating game data
     */
    public function ajax_update_game() {
        // Parse the form data
        parse_str($_POST['formData'], $data);
        
        // Verify nonce
        if (!isset($data['bgm_edit_nonce']) || !wp_verify_nonce($data['bgm_edit_nonce'], 'bgm_update_game')) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Security check failed.'
            ));
            return;
        }
        
        // Get game ID
        $game_id = isset($data['game_id']) ? intval($data['game_id']) : 0;
        
        if ($game_id <= 0) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Invalid game ID.'
            ));
            return;
        }
        
        // Update game data
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        
        // Sanitize and prepare data
        $update_data = array(
            'name' => sanitize_text_field($data['name']),
            'thumb' => esc_url_raw($data['thumb']),
            'minplayers' => intval($data['minplayers']),
            'maxplayers' => intval($data['maxplayers']),
            'minplaytime' => intval($data['minplaytime']),
            'maxplaytime' => intval($data['maxplaytime']),
            'complexity' => floatval($data['complexity']),
            'gamecats' => sanitize_text_field($data['gamecats']),
            'gamemechs' => sanitize_text_field($data['gamemechs']),
            'rating' => floatval($data['rating']),
            'qty' => intval($data['qty']),
            'qtyrented' => intval($data['qtyrented']),
            'description' => wp_kses_post($data['description']),
            'year_published' => intval($data['year_published']),
            'publisher' => sanitize_text_field($data['publisher']),
            'designer' => sanitize_text_field($data['designer']),
            'last_updated' => current_time('mysql')
        );
        
        // Update the database
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $game_id),
            array(
                '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%f', 
                '%d', '%d', '%s', '%d', '%s', '%s', '%s'
            ),
            array('%d')
        );
        
        if ($result !== false) {
            // Get the updated game data to return
            $updated_game = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $game_id),
                ARRAY_A
            );
            
            wp_send_json(array(
                'success' => true,
                'message' => 'Game updated successfully.',
                'game' => $updated_game
            ));
        } else {
            wp_send_json(array(
                'success' => false,
                'message' => $wpdb->last_error ? 'Error: ' . $wpdb->last_error : 'No changes were made.'
            ));
        }
    }

        /**
     * AJAX handler for deleting a game
     */
    public function ajax_delete_game() {
        // Parse the form data
        parse_str($_POST['formData'], $data);
        
        // Verify nonce
        if (!isset($data['bgm_delete_nonce']) || !wp_verify_nonce($data['bgm_delete_nonce'], 'bgm_delete_game')) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Security check failed.'
            ));
            return;
        }
        
        // Get game ID
        $game_id = isset($data['game_id']) ? intval($data['game_id']) : 0;
        
        if ($game_id <= 0) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Invalid game ID.'
            ));
            return;
        }
        
        // Delete the game
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        
        // Get the game name first for confirmation message
        $game = $wpdb->get_row($wpdb->prepare("SELECT id, name, bgg_id FROM $table_name WHERE bgg_id = %d", $game_id));
        
        if (!$game) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Game not found.'
            ));
            return;
        }
        
        // Delete the game
        $result = $wpdb->delete(
            $table_name,
            array('bgg_id' => $game_id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json(array(
                'success' => true,
                'message' => sprintf('Game "%s" has been deleted successfully.', $game->name),
                'game_id' => $game->id
            ));
        } else {
            wp_send_json(array(
                'success' => false,
                'message' => 'Error deleting game: ' . $wpdb->last_error
            ));
        }
    }

        /**
     * AJAX handler for fetching game data by BGG ID
     */
    public function ajax_fetch_game_by_id() {
        // Verify nonce
        check_ajax_referer('bgm_edit_game_nonce', 'security');
        
        // Get game ID from request
        $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
        
        if ($game_id <= 0) {
            wp_send_json_error('Invalid game ID provided.');
            return;
        }
        
        // Fetch game data from BGG API
        $api_url = "https://boardgamegeek.com/xmlapi2/thing?id={$game_id}&stats=1";
        
        $response = wp_remote_get($api_url);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to connect to BoardGameGeek API: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Process XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if ($xml === false) {
            wp_send_json_error('Failed to parse BoardGameGeek API response.');
            return;
        }
        
        // Check if we got a valid game
        if (!isset($xml->item) || count($xml->item) === 0) {
            wp_send_json_error('Game not found with the provided ID.');
            return;
        }
        
        $item = $xml->item;
        
        // Extract game data
        try {
            $game_data = array(
                'id' => (string)$item['id'],
                'name' => (string)$item->name[0]['value'],
                'thumbnail' => isset($item->thumbnail) ? (string)$item->thumbnail : '',
                'minplayers' => (string)$item->minplayers['value'],
                'maxplayers' => (string)$item->maxplayers['value'],
                'minplaytime' => (string)$item->minplaytime['value'],
                'maxplaytime' => (string)$item->maxplaytime['value'],
                'year_published' => isset($item->yearpublished) ? (string)$item->yearpublished['value'] : '',
                'complexity' => (string)$item->statistics->ratings->averageweight['value'],
                'rating' => (string)$item->statistics->ratings->average['value']
            );
            
            // Get BGG rank if available
            $game_data['rank'] = 999999; // Default high rank for unranked games
            if (isset($item->statistics->ratings->ranks->rank)) {
                foreach ($item->statistics->ratings->ranks->rank as $rank_item) {
                    if ((string)$rank_item['type'] === 'subtype' && (string)$rank_item['name'] === 'boardgame') {
                        if ((string)$rank_item['value'] !== 'Not Ranked') {
                            $game_data['rank'] = (int)$rank_item['value'];
                        }
                        break;
                    }
                }
            }
            
            // Success response
            wp_send_json_success($game_data);
            
        } catch (Exception $e) {
            wp_send_json_error('Error processing game data: ' . $e->getMessage());
        }
    }

        /**
     * AJAX handler for starting a game update
     */
    public function ajax_start_games_update() {
        if (!class_exists('BGM_Update_Manager')) {
            wp_send_json_error('Update manager not available.');
            return;
        }
        
        // Call the update manager's method
        $update_manager = new BGM_Update_Manager();
        $update_manager->ajax_start_games_update();
    }

    /**
     * AJAX handler for pausing a game update
     */
    public function ajax_pause_games_update() {
        if (!class_exists('BGM_Update_Manager')) {
            wp_send_json_error('Update manager not available.');
            return;
        }
        
        // Call the update manager's method
        $update_manager = new BGM_Update_Manager();
        $update_manager->ajax_pause_games_update();
    }

    /**
     * AJAX handler for resuming a game update
     */
    public function ajax_resume_games_update() {
        if (!class_exists('BGM_Update_Manager')) {
            wp_send_json_error('Update manager not available.');
            return;
        }
        
        // Call the update manager's method
        $update_manager = new BGM_Update_Manager();
        $update_manager->ajax_resume_games_update();
    }

    /**
     * AJAX handler for stopping a game update
     */
    public function ajax_stop_games_update() {
        if (!class_exists('BGM_Update_Manager')) {
            wp_send_json_error('Update manager not available.');
            return;
        }
        
        // Call the update manager's method
        $update_manager = new BGM_Update_Manager();
        $update_manager->ajax_stop_games_update();
    }

    /**
     * AJAX handler for getting update progress
     */
    public function ajax_get_update_progress() {
        if (!class_exists('BGM_Update_Manager')) {
            wp_send_json_error('Update manager not available.');
            return;
        }
        
        // Call the update manager's method
        $update_manager = new BGM_Update_Manager();
        $update_manager->ajax_get_update_progress();
    }

}

// Initialize the admin
new BGM_Admin();