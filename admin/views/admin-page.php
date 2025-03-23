<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Display the main admin page content
 */
function bgm_render_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bgm_games';

    // Add nonce for AJAX operations
    wp_nonce_field('bgm_edit_game_nonce', 'bgm_edit_game_nonce');

    // Handle game search if performed
    $search_term = isset($_GET['game_search']) ? sanitize_text_field($_GET['game_search']) : '';
    $where_clause = empty($search_term) ? '' : $wpdb->prepare("WHERE name LIKE %s", '%' . $wpdb->esc_like($search_term) . '%');

    // Get games from database with search filter if applicable
    $games = $wpdb->get_results(
        "SELECT * FROM $table_name $where_clause ORDER BY name ASC", 
        ARRAY_A
    );
    ?>

    <div class="wrap">
        <h1>Master Game List</h1>
        <p>Manage List Here.</p>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=bgm-add-games'); ?>" class="button button-primary">Add Games</a>
            </div>
            
            <div class="alignright">
                <form method="get">
                    <input type="hidden" name="page" value="board-game-manager">
                    <input type="search" id="game-search" name="game_search" 
                        value="<?php echo esc_attr($search_term); ?>" 
                        placeholder="Search by name...">
                    <input type="submit" class="button" value="Search">
                    <?php if (!empty($search_term)) : ?>
                        <a href="<?php echo admin_url('admin.php?page=board-game-manager'); ?>" class="button">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            <br class="clear">
        </div>

        <?php if (empty($games)) : ?>
            <div class="notice notice-info">
                <p>
                    <?php echo empty($search_term) 
                        ? 'No games found in your collection. Add games to get started.' 
                        : 'No games match your search criteria.'; 
                    ?>
                </p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="60">Image</th>
                        <th>Name</th>
                        <th width="100">Players</th>
                        <th width="80">Year</th>
                        <th width="80">Rating</th>
                        <th width="100">Complexity</th>
                        <th width="80">Actions</th>
                        <th width="80">Delete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $game) : ?>
                        <tr id="game-row-<?php echo esc_attr($game['id']); ?>">
                            <td>
                                <?php if (!empty($game['thumb'])) : ?>
                                    <img src="<?php echo esc_url($game['thumb']); ?>" height="50" alt="<?php echo esc_attr($game['name']); ?>">
                                <?php else : ?>
                                    <div class="no-image">No image</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($game['name']); ?></strong>
                                <?php if (!empty($game['bgglink'])) : ?>
                                    <div class="row-actions">
                                        <span class="view">
                                            <a href="<?php echo esc_url($game['bgglink']); ?>" target="_blank">View on BGG</a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($game['minplayers'] . '-' . $game['maxplayers']); ?></td>
                            <td><?php echo esc_html($game['year_published']); ?></td>
                            <td><?php echo number_format($game['rating'], 1); ?></td>
                            <td><?php echo number_format($game['complexity'], 1); ?></td>
                            <td>
                                <button type="button" 
                                    class="button edit-game" 
                                    data-id="<?php echo esc_attr($game['id']); ?>">
                                    Edit
                                </button>
                            </td>
                            <td>
                                <button type="button" 
                                    class="button button-link-delete delete-game" 
                                    data-id="<?php echo esc_attr($game['bgg_id']); ?>"
                                    data-name="<?php echo esc_attr($game['name']); ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Edit Game Modal -->
    <div id="edit-game-modal" class="bgm-modal" style="display: none;">
        <div class="bgm-modal-content">
            <span class="bgm-close">&times;</span>
            <h2>Edit Game</h2>
            <form id="edit-game-form" method="post">
                <input type="hidden" id="edit_game_id" name="game_id" value="">
                <?php wp_nonce_field('bgm_update_game', 'bgm_edit_nonce'); ?>
                
                <div class="form-field">
                    <label for="edit_name">Name:</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-field">
                    <label for="edit_thumb">Thumbnail URL:</label>
                    <input type="url" id="edit_thumb" name="thumb">
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_minplayers">Min Players:</label>
                        <input type="number" id="edit_minplayers" name="minplayers" min="1" max="99">
                    </div>
                    <div class="form-field">
                        <label for="edit_maxplayers">Max Players:</label>
                        <input type="number" id="edit_maxplayers" name="maxplayers" min="1" max="99">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_minplaytime">Min Play Time (min):</label>
                        <input type="number" id="edit_minplaytime" name="minplaytime" min="1">
                    </div>
                    <div class="form-field">
                        <label for="edit_maxplaytime">Max Play Time (min):</label>
                        <input type="number" id="edit_maxplaytime" name="maxplaytime" min="1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_complexity">Complexity (1-5):</label>
                        <input type="number" id="edit_complexity" name="complexity" min="1" max="5" step="0.01">
                    </div>
                    <div class="form-field">
                        <label for="edit_rating">Rating (1-10):</label>
                        <input type="number" id="edit_rating" name="rating" min="1" max="10" step="0.1">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label for="edit_year_published">Year Published:</label>
                        <input type="number" id="edit_year_published" name="year_published" min="1" max="<?php echo date('Y'); ?>">
                    </div>
                    <div class="form-field">
                        <label for="edit_qty">Quantity:</label>
                        <input type="number" id="edit_qty" name="qty" min="0">
                    </div>
                    <div class="form-field">
                        <label for="edit_qtyrented">Qty Rented:</label>
                        <input type="number" id="edit_qtyrented" name="qtyrented" min="0">
                    </div>
                </div>
                
                <div class="form-field">
                    <label for="edit_publisher">Publisher:</label>
                    <input type="text" id="edit_publisher" name="publisher">
                </div>
                
                <div class="form-field">
                    <label for="edit_designer">Designer:</label>
                    <input type="text" id="edit_designer" name="designer">
                </div>
                
                <div class="form-field">
                    <label for="edit_gamecats">Game Categories:</label>
                    <input type="text" id="edit_gamecats" name="gamecats">
                    <p class="description">Separate multiple categories with commas</p>
                </div>
                
                <div class="form-field">
                    <label for="edit_gamemechs">Game Mechanics:</label>
                    <input type="text" id="edit_gamemechs" name="gamemechs">
                    <p class="description">Separate multiple mechanics with commas</p>
                </div>
                
                <div class="form-field">
                    <label for="edit_description">Description:</label>
                    <textarea id="edit_description" name="description" rows="5"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">Update Game</button>
                    <button type="button" class="button bgm-cancel">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
        <div id="delete-game-modal" class="bgm-modal" style="display: none;">
            <div class="bgm-modal-content">
                <span class="bgm-close">&times;</span>
                <h2>Confirm Deletion</h2>
                <p>Are you sure you want to delete <strong id="delete-game-name"></strong>?</p>
                <p>This action cannot be undone.</p>
                
                <form id="delete-game-form" method="post">
                    <input type="hidden" id="delete_game_id" name="game_id" value="">
                    <?php wp_nonce_field('bgm_delete_game', 'bgm_delete_nonce'); ?>
                    
                    <div class="form-actions">
                        <button type="button" class="button bgm-cancel-delete">Cancel</button>
                        <button type="submit" class="button button-link-delete">Delete Game</button>
                    </div>
                </form>
            </div>
        </div>
    <?php
}