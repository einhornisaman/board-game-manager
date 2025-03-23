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
    }
    
    /**
     * Register admin menu items
     */
    public function register_menu() {
        add_menu_page(
            'Board Game Manager',
            'Board Games',
            'manage_options',
            'board-game-manager',
            array($this, 'admin_page'),
            'dashicons-games',
            30
        );
        
        add_submenu_page(
            'board-game-manager',
            'Add/Remove Games',
            'Add/Remove Games',
            'manage_options',
            'bgm-add-remove',
            array($this, 'add_remove_page')
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
     * Display add/remove games page
     */
    public function add_remove_page() {
        // Process BGG search if form submitted
        $search_results = array();
        $result = array();
        $delete_result = array();
        
        if (isset($_POST['bgg_search_term']) && !empty($_POST['bgg_search_term'])) {
            $search_term = sanitize_text_field($_POST['bgg_search_term']);
            $search_results = $this->search_bgg_games($search_term);
        }
        
        // Process game addition
        if (isset($_POST['add_game_id']) && !empty($_POST['add_game_id'])) {
            $bgg_id = intval($_POST['add_game_id']);
            $result = BGM_BGG_API::import_game($bgg_id);
        }
        
        // Process game deletion
        if (isset($_POST['delete_game_id']) && !empty($_POST['delete_game_id'])) {
            $game_id = intval($_POST['delete_game_id']);
            $delete_result = $this->delete_game($game_id);
        }
        
        // Include the view file
        require_once BGM_PLUGIN_DIR . 'admin/views/add-remove-page.php';
        
        // Call the render function (you'll need to create this in add-remove-page.php)
        bgm_render_add_remove_page($search_results, $result, $delete_result);
    }
    
    /**
     * Delete game by BGG ID
     */
    private function delete_game($bgg_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        
        // Get the game name first for confirmation message
        $game = $wpdb->get_row($wpdb->prepare("SELECT id, name FROM $table_name WHERE bgg_id = %d", $bgg_id));
        
        if ($game) {
            // Delete the game
            $result = $wpdb->delete(
                $table_name,
                array('bgg_id' => $bgg_id),
                array('%d')
            );
            
            if ($result) {
                return array(
                    'success' => true,
                    'message' => sprintf('Game "%s" has been deleted successfully.', $game->name)
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Error deleting game: ' . $wpdb->last_error
                );
            }
        } else {
            return array(
                'success' => false,
                'message' => sprintf('Game with ID %d not found.', $bgg_id)
            );
        }
    }
    
    /**
     * Search BGG for games
     */
    private function search_bgg_games($search_term) {
        $search_term = urlencode($search_term);
        $search_url = "https://boardgamegeek.com/xmlapi2/search?type=boardgame,boardgameexpansion&query=$search_term";
        
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
                'message' => 'No games found matching your search term.'
            );
        }
        
        $game_ids = array();
        $temp_results = array();
        
        // First, collect all game IDs and basic info
        foreach ($xml->item as $item) {
            $game_id = (string)$item['id'];
            $name = "";
            
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
                    'thumbnail' => '' // Will be populated in the next step
                );
                
                $game_ids[] = $game_id;
            }
        }
        
        // Now, if we have games, fetch their details to get thumbnails
        if (!empty($game_ids)) {
            $batch_size = 10;
            $batches = array_chunk($game_ids, $batch_size);
            
            foreach ($batches as $batch) {
                $ids_string = implode(',', $batch);
                $thing_url = "https://boardgamegeek.com/xmlapi2/thing?id=$ids_string";
                
                $thing_response = wp_remote_get($thing_url);
                
                if (!is_wp_error($thing_response)) {
                    $thing_body = wp_remote_retrieve_body($thing_response);
                    $thing_xml = simplexml_load_string($thing_body);
                    
                    if ($thing_xml !== false) {
                        foreach ($thing_xml->item as $item) {
                            $id = (string)$item['id'];
                            $thumbnail = isset($item->thumbnail) ? (string)$item->thumbnail : '';
                            
                            if (isset($temp_results[$id])) {
                                $temp_results[$id]['thumbnail'] = $thumbnail;
                            }
                        }
                    }
                }
            }
        }
        
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
        
        if (empty($search)) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Search term is empty.'
            ));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        
        // Get games matching the search term
        $games = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, thumb, year_published, minplayers, maxplayers FROM $table_name 
             WHERE name LIKE %s 
             ORDER BY name 
             LIMIT 12",
            '%' . $wpdb->esc_like($search) . '%'
        ));
        
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
                'year' => $game->year_published,
                'players' => $game->minplayers . '-' . $game->maxplayers
            );
        }
        
        wp_send_json(array(
            'success' => true,
            'count' => count($results),
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
}

// Initialize the admin
new BGM_Admin();