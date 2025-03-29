<?php
/**
 * AJAX handlers for Board Game Manager
 */
class BGM_Ajax_Handlers {
    private $user_manager;
    private $bgg_api;
    
    public function __construct() {
        $this->user_manager = new BGM_User_Manager();
        $this->bgg_api = new BGM_BGG_API();
        
        // Register AJAX actions
        add_action('wp_ajax_bgm_create_list', array($this, 'handle_create_list'));
        add_action('wp_ajax_bgm_delete_list', array($this, 'handle_delete_list'));
        add_action('wp_ajax_bgm_add_game', array($this, 'handle_add_game'));
        add_action('wp_ajax_bgm_remove_game', array($this, 'handle_remove_game'));
        add_action('wp_ajax_bgm_edit_game', array($this, 'handle_edit_game'));
        add_action('wp_ajax_bgm_search_bgg', array($this, 'handle_search_bgg'));
        add_action('wp_ajax_bgm_search_local', array($this, 'handle_search_local'));
        add_action('wp_ajax_bgm_get_game_details', array($this, 'handle_get_game_details'));
        add_action('wp_ajax_bgm_edit_list', array($this, 'handle_edit_list'));
        add_action('wp_ajax_bgm_add_game_to_list', array($this, 'handle_add_game_to_list'));
        add_action('wp_ajax_bgm_update_list_order', array($this, 'handle_update_list_order'));
    }
    
    /**
     * Handle creating a new list
     */
    public function handle_create_list() {
        // Verify nonce
        check_ajax_referer('bgm_ajax_nonce', 'security');

        // Parse form data
        wp_parse_str($_POST['formData'], $data);

        // Get user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }

        // Check if user can create more lists
        if (!$this->user_manager->can_create_list($user_id)) {
            wp_send_json_error('You have reached your list limit. Please upgrade your subscription to create more lists.');
            return;
        }

        // Validate input
        $name = isset($data['list_name']) ? sanitize_text_field($data['list_name']) : '';
        $description = isset($data['list_description']) ? sanitize_textarea_field($data['list_description']) : '';

        if (empty($name)) {
            wp_send_json_error('List name is required');
            return;
        }

        // Check if a list with this name already exists for this user
        global $wpdb;
        $existing_list = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}bgm_user_lists WHERE user_id = %d AND name = %s",
            $user_id,
            $name
        ));

        if ($existing_list > 0) {
            wp_send_json_error('A list with this name already exists');
            return;
        }

        // Create list
        $result = $this->user_manager->create_list($user_id, $name, $description);

        if ($result) {
            wp_send_json_success('List created successfully');
        } else {
            wp_send_json_error('Error creating list');
        }

        // Make sure we exit after sending the response
        wp_die();
    }
    
    /**
     * Handle deleting a list
     */
    public function handle_delete_list() {
        // Verify nonce
        check_ajax_referer('bgm_ajax_nonce', 'security');

        // Get user ID and list ID
        $user_id = get_current_user_id();
        $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;

        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }

        if (!$list_id) {
            wp_send_json_error('Invalid list ID');
            return;
        }

        // Delete list
        $result = $this->user_manager->delete_list($list_id, $user_id);

        if ($result) {
            wp_send_json_success('List deleted successfully');
        } else {
            wp_send_json_error('Error deleting list');
        }
    }
    
    /**
     * Handle adding a game to a list
     */
    public function handle_add_game() {
        // Verify nonce
        check_ajax_referer('bgm_ajax_nonce', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        $user_id = get_current_user_id();
        $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
        $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
        
        if (!$list_id) {
            wp_send_json_error('Invalid list ID');
            return;
        }
        
        if (!$game_id) {
            wp_send_json_error('Invalid game ID');
            return;
        }
        
        $custom_fields = isset($_POST['custom_fields']) ? json_decode(stripslashes($_POST['custom_fields']), true) : array();
        
        // First, check if the game exists in our database by BGG ID
        global $wpdb;
        $games_table = $wpdb->prefix . 'bgm_games';
        $game = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $games_table WHERE bgg_id = %d",
            $game_id
        ));
        
        if (!$game) {
            // If game doesn't exist, get it from BGG and add it
            $bgg_game = $this->bgg_api->get_game($game_id);
            if (!$bgg_game) {
                wp_send_json_error('Error fetching game from BoardGameGeek');
                return;
            }
            
            // Add game to database
            $result = BGM_BGG_API::import_game($game_id);
            if (!$result['success']) {
                wp_send_json_error($result['message'] || 'Error adding game to database');
                return;
            }
            
            // Get the internal game ID from the result
            $game = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $games_table WHERE bgg_id = %d",
                $game_id
            ));
        }

        // Check if the game is already in the user's list
        $items_table = $wpdb->prefix . 'bgm_user_list_items';
        $existing_game = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $items_table WHERE list_id = %d AND game_id = %d",
            $list_id,
            $game->id
        ));

        if ($existing_game) {
            wp_send_json_error('This game is already in your list');
            return;
        }
        
        // Add game to user's list
        $result = $this->user_manager->add_game_to_list($list_id, $game->id, $user_id);
        
        if ($result) {
            // Add custom fields if any
            foreach ($custom_fields as $field_name => $field_value) {
                $this->user_manager->update_custom_field($user_id, $game->id, $field_name, $field_value);
            }
            
            wp_send_json_success('Game added successfully');
        } else {
            wp_send_json_error('Error adding game to list');
        }
    }
    
    /**
     * Handle adding a game to a list
     */
    public function handle_add_game_to_list() {
        // Verify nonce
        check_ajax_referer('bgm_ajax_nonce', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        $user_id = get_current_user_id();
        $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
        $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
        
        if (!$list_id) {
            wp_send_json_error('Invalid list ID');
            return;
        }
        
        if (!$game_id) {
            wp_send_json_error('Invalid game ID');
            return;
        }
        
        // First, check if the game exists in our database by BGG ID
        global $wpdb;
        $games_table = $wpdb->prefix . 'bgm_games';
        $game = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $games_table WHERE bgg_id = %d",
            $game_id
        ));
        
        if (!$game) {
            // If game doesn't exist, get it from BGG and add it
            $bgg_game = $this->bgg_api->get_game($game_id);
            if (!$bgg_game) {
                wp_send_json_error('Error fetching game from BoardGameGeek');
                return;
            }
            
            // Add game to database
            $result = BGM_BGG_API::import_game($game_id);
            if (!$result['success']) {
                wp_send_json_error($result['message'] || 'Error adding game to database');
                return;
            }
            
            // Get the internal game ID from the result
            $game = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $games_table WHERE bgg_id = %d",
                $game_id
            ));
        }

        // Check if the game is already in the user's list
        $items_table = $wpdb->prefix . 'bgm_user_list_items';
        $existing_game = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $items_table WHERE list_id = %d AND game_id = %d",
            $list_id,
            $game->id
        ));

        if ($existing_game) {
            wp_send_json_error('This game is already in your list');
            return;
        }
        
        // Add game to user's list
        $result = $this->user_manager->add_game_to_list($list_id, $game->id, $user_id);
        
        if ($result) {
            wp_send_json_success('Game added successfully');
        } else {
            wp_send_json_error('Error adding game to list');
        }
    }
    
    /**
     * Handle removing a game from a list
     */
    public function handle_remove_game() {
        // Verify nonce
        check_ajax_referer('bgm_ajax_nonce', 'security');

        // Get user ID and list item ID
        $user_id = get_current_user_id();
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        if (!$user_id) {
            wp_send_json_error('User not logged in');
            return;
        }

        if (!$item_id) {
            wp_send_json_error('Invalid item ID');
            return;
        }

        // Remove game
        $result = $this->user_manager->remove_game_from_list($item_id, $user_id);

        if ($result) {
            wp_send_json_success('Game removed successfully');
        } else {
            wp_send_json_error('Error removing game');
        }
    }
    
    /**
     * Handle editing a game's custom data
     */
    public function handle_edit_game() {
        check_ajax_referer('bgm_edit_game', 'bgm_nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $user_id = get_current_user_id();
        $game_id = intval($_POST['game_id']);
        $custom_fields = isset($_POST['custom_fields']) ? json_decode(stripslashes($_POST['custom_fields']), true) : array();
        
        // Update custom fields
        foreach ($custom_fields as $field_name => $field_value) {
            $this->user_manager->update_custom_field($user_id, $game_id, $field_name, $field_value);
        }
        
        wp_send_json_success('Game updated successfully');
    }
    
    /**
     * Handle editing a list
     */
    public function handle_edit_list() {
        // Verify nonce
        check_ajax_referer('bgm_ajax_nonce', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        $user_id = get_current_user_id();
        $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
        
        if (!$list_id) {
            wp_send_json_error('Invalid list ID');
            return;
        }
        
        parse_str($_POST['formData'], $form_data);
        
        $list_name = isset($form_data['list_name']) ? sanitize_text_field($form_data['list_name']) : '';
        $list_description = isset($form_data['list_description']) ? sanitize_textarea_field($form_data['list_description']) : '';
        
        if (empty($list_name)) {
            wp_send_json_error('List name is required');
            return;
        }
        
        $result = $this->user_manager->update_list($list_id, $user_id, $list_name, $list_description);
        
        if ($result) {
            wp_send_json_success('List updated successfully');
        } else {
            wp_send_json_error('Error updating list');
        }
    }
    
    /**
     * Handle searching BoardGameGeek
     */
    public function handle_search_bgg() {
        try {
            // Verify nonce
            check_ajax_referer('bgm_ajax_nonce', 'security');
            
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            if (empty($search)) {
                wp_send_json_error('Search term is required');
                return;
            }
            
            // Get and sanitize thing types
            $thing_types = isset($_POST['thing_types']) ? (array)$_POST['thing_types'] : ['boardgame'];
            $thing_types = array_map('sanitize_text_field', $thing_types);
            
            // Validate thing types
            $valid_types = ['boardgame', 'boardgameexpansion', 'boardgameaccessory', 'rpgitem', 'videogame'];
            $thing_types = array_intersect($thing_types, $valid_types);
            
            if (empty($thing_types)) {
                wp_send_json_error('At least one valid game type must be selected');
                return;
            }
            
            $bgg_api = new BGM_BGG_API();
            $results = $bgg_api->search_games($search, true, $thing_types);
            
            if (is_wp_error($results)) {
                wp_send_json_error($results->get_error_message());
                return;
            }
            
            wp_send_json_success($results);
            
        } catch (Exception $e) {
            error_log('BGG Search Error: ' . $e->getMessage());
            wp_send_json_error('An unexpected error occurred while searching BoardGameGeek. Please try again.');
        }
    }
    
    /**
     * Handle getting game details from BoardGameGeek
     */
    public function handle_get_game_details() {
        check_ajax_referer('bgm_get_game_details', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in to view game details.');
            return;
        }
        
        $game_id = intval($_POST['game_id']);
        
        if (!$game_id) {
            wp_send_json_error('Invalid game ID provided.');
            return;
        }
        
        // First check if game exists in our database
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        $game = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE bgg_id = %d",
            $game_id
        ));
        
        if ($game) {
            // Return game from database
            wp_send_json_success(array(
                'id' => $game->bgg_id,
                'name' => $game->name,
                'year' => $game->year_published,
                'thumbnail' => $game->thumb,
                'description' => $game->description,
                'minplayers' => $game->minplayers,
                'maxplayers' => $game->maxplayers,
                'minplaytime' => $game->minplaytime,
                'maxplaytime' => $game->maxplaytime,
                'complexity' => $game->complexity,
                'rating' => $game->rating,
                'categories' => $game->gamecats,
                'mechanics' => $game->gamemechs
            ));
            return;
        }
        
        // If not in database, fetch from BGG API
        $game = $this->bgg_api->get_game($game_id);
        
        if ($game) {
            wp_send_json_success($game);
        } else {
            wp_send_json_error('Unable to fetch game details from BoardGameGeek. Please try again later.');
        }
    }

    /**
     * Handle searching local database
     */
    public function handle_search_local() {
        // Verify nonce
        check_ajax_referer('bgm_ajax_nonce', 'security');
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        if (empty($search)) {
            wp_send_json_error('Search term is required');
            return;
        }
        
        // Get and sanitize thing types
        $thing_types = isset($_POST['thing_types']) ? (array)$_POST['thing_types'] : ['boardgame'];
        $thing_types = array_map('sanitize_text_field', $thing_types);
        
        // Validate thing types
        $valid_types = ['boardgame', 'boardgameexpansion', 'boardgameaccessory', 'rpgitem', 'videogame'];
        $thing_types = array_intersect($thing_types, $valid_types);
        
        if (empty($thing_types)) {
            wp_send_json_error('At least one valid game type must be selected');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        
        // Build the type condition
        $type_conditions = array();
        foreach ($thing_types as $type) {
            $type_conditions[] = $wpdb->prepare('(primary_type = %s OR FIND_IN_SET(%s, subtypes) > 0)', $type, $type);
        }
        $type_condition = '(' . implode(' OR ', $type_conditions) . ')';
        
        // Search query with type filtering
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, bgg_id, name, thumb as thumbnail, year_published as year, primary_type as type, subtypes
                FROM $table_name
                WHERE name LIKE %s AND $type_condition
                ORDER BY name ASC
                LIMIT 50",
                '%' . $wpdb->esc_like($search) . '%'
            )
        );
        
        if ($wpdb->last_error) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }
        
        // Format results and add source identifier
        $formatted_results = array();
        foreach ($results as $game) {
            $formatted_results[] = array(
                'id' => $game->bgg_id,
                'name' => $game->name,
                'thumbnail' => $game->thumbnail,
                'year' => $game->year,
                'type' => $game->type,
                'subtypes' => $game->subtypes ? explode(',', $game->subtypes) : array(),
                'source' => 'local'
            );
        }
        
        wp_send_json_success($formatted_results);
    }

    /**
     * Handle updating list order
     */
    public function handle_update_list_order() {
        // Verify nonce
        check_ajax_referer('bgm_ajax_nonce', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        $user_id = get_current_user_id();
        $orders = isset($_POST['orders']) ? $_POST['orders'] : array();
        
        if (empty($orders) || !is_array($orders)) {
            wp_send_json_error('Invalid order data');
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'bgm_user_lists';
        $success = true;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($orders as $order) {
                $list_id = isset($order['id']) ? intval($order['id']) : 0;
                $new_order = isset($order['order']) ? intval($order['order']) : 0;
                
                if (!$list_id) {
                    throw new Exception('Invalid list ID');
                }
                
                // Verify ownership
                $list = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d AND user_id = %d",
                    $list_id,
                    $user_id
                ));
                
                if (!$list) {
                    throw new Exception('List not found or access denied');
                }
                
                // Update the order
                $result = $wpdb->update(
                    $table,
                    array('sort_order' => $new_order),
                    array('id' => $list_id),
                    array('%d'),
                    array('%d')
                );
                
                if ($result === false) {
                    throw new Exception('Error updating list order');
                }
            }
            
            // If we got here, commit the transaction
            $wpdb->query('COMMIT');
            wp_send_json_success('List orders updated successfully');
            
        } catch (Exception $e) {
            // If anything went wrong, rollback
            $wpdb->query('ROLLBACK');
            wp_send_json_error($e->getMessage());
        }
    }
} 