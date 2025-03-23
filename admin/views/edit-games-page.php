<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1>Edit Games</h1>
    
    <?php if (isset($update_result)): ?>
        <div class="notice notice-<?php echo $update_result['success'] ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($update_result['message']); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($game) && $game): ?>
        <!-- Edit Game Form -->
        <div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">
            <h2>Edit Game: <?php echo esc_html($game['name']); ?></h2>
            
            <form method="post" action="<?php echo admin_url('admin.php?page=bgm-edit-games'); ?>">
                <input type="hidden" name="game_id" value="<?php echo esc_attr($game['id']); ?>">
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="name">Game Name</label>
                            </th>
                            <td>
                                <input name="name" type="text" id="name" value="<?php echo esc_attr($game['name']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="thumb">Thumbnail URL</label>
                            </th>
                            <td>
                                <input name="thumb" type="url" id="thumb" value="<?php echo esc_url($game['thumb']); ?>" class="regular-text">
                                <p class="description">Current thumbnail:</p>
                                <img src="<?php echo esc_url($game['thumb']); ?>" style="max-height: 100px; margin-top: 10px;">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="minplayers">Player Count</label>
                            </th>
                            <td>
                                <input name="minplayers" type="number" id="minplayers" value="<?php echo esc_attr($game['minplayers']); ?>" class="small-text"> to 
                                <input name="maxplayers" type="number" id="maxplayers" value="<?php echo esc_attr($game['maxplayers']); ?>" class="small-text"> players
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="minplaytime">Playing Time</label>
                            </th>
                            <td>
                                <input name="minplaytime" type="number" id="minplaytime" value="<?php echo esc_attr($game['minplaytime']); ?>" class="small-text"> to 
                                <input name="maxplaytime" type="number" id="maxplaytime" value="<?php echo esc_attr($game['maxplaytime']); ?>" class="small-text"> minutes
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="complexity">Complexity</label>
                            </th>
                            <td>
                                <input name="complexity" type="number" id="complexity" value="<?php echo esc_attr($game['complexity']); ?>" class="small-text" step="0.01" min="1" max="5">
                                <p class="description">Scale of 1 (easy) to 5 (complex)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="rating">Rating</label>
                            </th>
                            <td>
                                <input name="rating" type="number" id="rating" value="<?php echo esc_attr($game['rating']); ?>" class="small-text" step="0.1" min="1" max="10">
                                <p class="description">Scale of 1 to 10</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="year_published">Year Published</label>
                            </th>
                            <td>
                                <input name="year_published" type="number" id="year_published" value="<?php echo esc_attr($game['year_published']); ?>" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="gamecats">Categories</label>
                            </th>
                            <td>
                                <textarea name="gamecats" id="gamecats" rows="3" class="large-text"><?php echo esc_textarea($game['gamecats']); ?></textarea>
                                <p class="description">Comma-separated list of categories</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="gamemechs">Mechanics</label>
                            </th>
                            <td>
                                <textarea name="gamemechs" id="gamemechs" rows="3" class="large-text"><?php echo esc_textarea($game['gamemechs']); ?></textarea>
                                <p class="description">Comma-separated list of mechanics</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="publisher">Publisher</label>
                            </th>
                            <td>
                                <input name="publisher" type="text" id="publisher" value="<?php echo esc_attr($game['publisher']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="designer">Designer</label>
                            </th>
                            <td>
                                <input name="designer" type="text" id="designer" value="<?php echo esc_attr($game['designer']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="bgglink">BGG Link</label>
                            </th>
                            <td>
                                <input name="bgglink" type="url" id="bgglink" value="<?php echo esc_url($game['bgglink']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="qty">Quantity</label>
                            </th>
                            <td>
                                <input name="qty" type="number" id="qty" value="<?php echo esc_attr($game['qty']); ?>" class="small-text" min="0">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="qtyrented">Quantity Rented</label>
                            </th>
                            <td>
                                <input name="qtyrented" type="number" id="qtyrented" value="<?php echo esc_attr($game['qtyrented']); ?>" class="small-text" min="0">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="description">Description</label>
                            </th>
                            <td>
                                <textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea($game['description']); ?></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="edit_game_submit" id="submit" class="button button-primary" value="Update Game">
                    <a href="<?php echo admin_url('admin.php?page=bgm-edit-games'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        
    <?php else: ?>
        <!-- Game Search Form (Non-Real-Time) -->
        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>Search for a Game to Edit</h2>
            
            <form method="post" action="">
                <p>
                    <input type="text" name="search_term" placeholder="Enter a game name to search" class="regular-text">
                    <input type="submit" name="search_submit" class="button button-primary" value="Search">
                </p>
                <p class="description">Search for a game in your collection to edit.</p>
            </form>
            
            <?php
            // Process search when form is submitted
            if (isset($_POST['search_submit']) && !empty($_POST['search_term'])) {
                $search_term = sanitize_text_field($_POST['search_term']);
                
                global $wpdb;
                $table_name = $wpdb->prefix . 'bgm_games';
                
                $games = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, name FROM $table_name WHERE name LIKE %s ORDER BY name LIMIT 20",
                    '%' . $wpdb->esc_like($search_term) . '%'
                ));
                
                if (empty($games)) {
                    echo '<div class="notice notice-warning inline"><p>No games found matching "' . esc_html($search_term) . '"</p></div>';
                } else {
                    echo '<h3>Search Results</h3>';
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>Name</th><th width="120">Action</th></tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($games as $game) {
                        echo '<tr>';
                        echo '<td>' . esc_html($game->name) . '</td>';
                        echo '<td>';
                        echo '<a href="' . esc_url(admin_url('admin.php?page=bgm-edit-games&game_id=' . $game->id)) . '" class="button button-primary">Edit</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody></table>';
                }
            }
            ?>
        </div>
    <?php endif; ?>
</div>