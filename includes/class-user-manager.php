<?php
/**
 * User-specific functionality for Board Game Manager
 */
class BGM_User_Manager {
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Get all game lists for a user
     */
    public function get_user_lists($user_id) {
        $table = $this->wpdb->prefix . 'bgm_user_lists';
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );
    }
    
    /**
     * Create a new game list for a user
     */
    public function create_list($user_id, $name, $description = '') {
        $table = $this->wpdb->prefix . 'bgm_user_lists';
        return $this->wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'name' => $name,
                'description' => $description
            ),
            array('%d', '%s', '%s')
        );
    }
    
    /**
     * Delete a game list and its items
     */
    public function delete_list($list_id, $user_id) {
        $lists_table = $this->wpdb->prefix . 'bgm_user_lists';
        $items_table = $this->wpdb->prefix . 'bgm_user_list_items';
        
        // Verify ownership
        $list = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $lists_table WHERE id = %d AND user_id = %d",
            $list_id,
            $user_id
        ));
        
        if (!$list) {
            return false;
        }
        
        // Delete list items
        $this->wpdb->delete($items_table, array('list_id' => $list_id), array('%d'));
        
        // Delete the list
        return $this->wpdb->delete($lists_table, array('id' => $list_id), array('%d'));
    }
    
    /**
     * Get games in a list with their custom data
     */
    public function get_list_games($list_id, $user_id) {
        $items_table = $this->wpdb->prefix . 'bgm_user_list_items';
        $games_table = $this->wpdb->prefix . 'bgm_games';
        $custom_data_table = $this->wpdb->prefix . 'bgm_custom_game_data';
        
        // Verify list ownership
        $list = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}bgm_user_lists WHERE id = %d AND user_id = %d",
            $list_id,
            $user_id
        ));
        
        if (!$list) {
            return false;
        }
        
        // Get games with their custom data
        $games = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT g.*, li.custom_data, li.id as list_item_id
            FROM $games_table g
            JOIN $items_table li ON g.id = li.game_id
            WHERE li.list_id = %d",
            $list_id
        ));
        
        // Get custom fields for each game
        foreach ($games as &$game) {
            $custom_fields = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT field_name, field_value 
                FROM $custom_data_table 
                WHERE user_id = %d AND game_id = %d",
                $user_id,
                $game->id
            ));
            
            $game->custom_fields = array();
            foreach ($custom_fields as $field) {
                $game->custom_fields[$field->field_name] = $field->field_value;
            }
        }
        
        return $games;
    }
    
    /**
     * Add a game to a list
     */
    public function add_game_to_list($list_id, $game_id, $user_id, $custom_data = null) {
        $items_table = $this->wpdb->prefix . 'bgm_user_list_items';
        
        // Verify list ownership
        $list = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}bgm_user_lists WHERE id = %d AND user_id = %d",
            $list_id,
            $user_id
        ));
        
        if (!$list) {
            return false;
        }
        
        return $this->wpdb->insert(
            $items_table,
            array(
                'list_id' => $list_id,
                'game_id' => $game_id,
                'custom_data' => $custom_data ? json_encode($custom_data) : null
            ),
            array('%d', '%d', '%s')
        );
    }
    
    /**
     * Remove a game from a list
     */
    public function remove_game_from_list($list_item_id, $user_id) {
        $items_table = $this->wpdb->prefix . 'bgm_user_list_items';
        $lists_table = $this->wpdb->prefix . 'bgm_user_lists';
        
        // Verify ownership through list
        $list_item = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT li.* FROM $items_table li
            JOIN $lists_table l ON li.list_id = l.id
            WHERE li.id = %d AND l.user_id = %d",
            $list_item_id,
            $user_id
        ));
        
        if (!$list_item) {
            return false;
        }
        
        return $this->wpdb->delete($items_table, array('id' => $list_item_id), array('%d'));
    }
    
    /**
     * Update custom field for a game
     */
    public function update_custom_field($user_id, $game_id, $field_name, $field_value) {
        $table = $this->wpdb->prefix . 'bgm_custom_game_data';
        
        return $this->wpdb->replace(
            $table,
            array(
                'user_id' => $user_id,
                'game_id' => $game_id,
                'field_name' => $field_name,
                'field_value' => $field_value
            ),
            array('%d', '%d', '%s', '%s')
        );
    }
    
    /**
     * Delete custom field for a game
     */
    public function delete_custom_field($user_id, $game_id, $field_name) {
        $table = $this->wpdb->prefix . 'bgm_custom_game_data';
        
        return $this->wpdb->delete(
            $table,
            array(
                'user_id' => $user_id,
                'game_id' => $game_id,
                'field_name' => $field_name
            ),
            array('%d', '%d', '%s')
        );
    }
    
    /**
     * Get user's subscription level from Paid Memberships Pro
     */
    public function get_user_subscription_level($user_id) {
        if (!function_exists('pmpro_getMembershipLevelForUser')) {
            return 0;
        }
        
        $level = pmpro_getMembershipLevelForUser($user_id);
        return $level ? $level->id : 0;
    }
    
    /**
     * Check if user can create more lists based on subscription
     */
    public function can_create_list($user_id) {
        $subscription_level = $this->get_user_subscription_level($user_id);
        $current_lists = count($this->get_user_lists($user_id));
        
        // Define limits based on subscription level
        $limits = array(
            0 => 1,    // Free users
            1 => 3,    // Basic subscription
            2 => 10,   // Premium subscription
            3 => 999   // Unlimited
        );
        
        $limit = isset($limits[$subscription_level]) ? $limits[$subscription_level] : 1;
        return $current_lists < $limit;
    }
    
    /**
     * Check if user can add custom fields based on subscription
     */
    public function can_add_custom_fields($user_id) {
        $subscription_level = $this->get_user_subscription_level($user_id);
        return $subscription_level >= 2; // Premium and above can add custom fields
    }

    /**
     * Get a single list by ID and verify ownership
     */
    public function get_list($list_id, $user_id) {
        $table = $this->wpdb->prefix . 'bgm_user_lists';
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $list_id,
            $user_id
        ));
    }

    /**
     * Update a list's name and description
     */
    public function update_list($list_id, $user_id, $name, $description = '') {
        $table = $this->wpdb->prefix . 'bgm_user_lists';
        
        // Verify ownership
        $list = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $list_id,
            $user_id
        ));
        
        if (!$list) {
            return false;
        }
        
        // Update list
        return $this->wpdb->update(
            $table,
            array(
                'name' => $name,
                'description' => $description,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $list_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
} 