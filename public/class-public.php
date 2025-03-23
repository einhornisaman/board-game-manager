<?php
/**
 * Public-facing functionality for Board Game Manager
 */
class BGM_Public {
    /**
     * Constructor - register public hooks
     */
    public function __construct() {
        add_shortcode('board_game_collection', array($this, 'games_shortcode'));
    }
    
    /**
     * Shortcode for displaying games
     */
    public function games_shortcode() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        $games = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC", ARRAY_A);
        
        ob_start();
        
        if (empty($games)) {
            echo '<p>No games found in the collection.</p>';
        } else {
            echo '<div class="bgm-games-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">';
            
            foreach ($games as $game) {
                echo '<div class="bgm-game-card" style="border: 1px solid #ddd; border-radius: 5px; padding: 10px; text-align: center;">';
                echo '<img src="' . esc_url($game['thumb']) . '" style="height: 100px; margin-bottom: 10px;" alt="' . esc_attr($game['name']) . '">';
                echo '<h3 style="margin: 0 0 5px; font-size: 16px;">' . esc_html($game['name']) . '</h3>';
                echo '<p style="margin: 0; font-size: 14px;">' . esc_html($game['minplayers'] . '-' . $game['maxplayers']) . ' players | ' . esc_html($game['minplaytime'] . '-' . $game['maxplaytime']) . ' min</p>';
                if (!empty($game['year_published'])) {
                    echo '<p style="margin: 5px 0 0; font-size: 12px;">(' . esc_html($game['year_published']) . ')</p>';
                }
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        return ob_get_clean();
    }
}

// Initialize the public class
$bgm_public = new BGM_Public();