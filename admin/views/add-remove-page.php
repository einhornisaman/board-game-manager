<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Render the add/remove games page
 * 
 * @param array $search_results BGG search results if available
 * @param array $result Game import result if available
 * @param array $delete_result Game deletion result if available
 */
function bgm_render_add_remove_page($search_results = array(), $result = array(), $delete_result = array()) {
    ?>
    <div class="wrap">
        <h1>Add/Remove Games</h1>
        
        <?php
        // Display result messages if any
        if (!empty($result)) {
            $message_class = isset($result['success']) && $result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $message_class . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        }

        if (!empty($delete_result)) {
            $message_class = isset($delete_result['success']) && $delete_result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $message_class . ' is-dismissible"><p>' . esc_html($delete_result['message']) . '</p></div>';
        }
        ?>

        <div class="bgm-tabs">
            <ul class="bgm-tab-nav">
                <li class="active"><a href="#tab-add">Add Games from BGG</a></li>
                <li><a href="#tab-delete">Remove Games</a></li>
            </ul>
            
            <div class="bgm-tab-content">
                <!-- Add Games Tab -->
                <div id="tab-add" class="bgm-tab-pane active">
                    <h2>Search BoardGameGeek</h2>
                    <p>Search for games on BoardGameGeek to add to your collection.</p>
                    
                    <form method="post" action="">
                        <div style="display: flex; margin-bottom: 20px;">
                            <input type="text" name="bgg_search_term" placeholder="Search for games..." style="flex: 1; margin-right: 10px;" required>
                            <input type="submit" class="button button-primary" value="Search BGG">
                        </div>
                    </form>
                    
                    <?php if (!empty($search_results)) : ?>
                        <h3>Search Results</h3>
                        
                        <?php if (!isset($search_results['success']) || !$search_results['success']) : ?>
                            <div class="notice notice-error">
                                <p><?php echo esc_html($search_results['message']); ?></p>
                            </div>
                        <?php else : ?>
                            <p>Found <?php echo count($search_results['games']); ?> games. Click "Import" to add a game to your collection.</p>
                            
                            <div class="bgm-search-results">
                                <?php foreach ($search_results['games'] as $game) : ?>
                                    <div class="bgm-game-card">
                                        <?php if (!empty($game['thumbnail'])) : ?>
                                            <img src="<?php echo esc_url($game['thumbnail']); ?>" alt="<?php echo esc_attr($game['name']); ?>">
                                        <?php else : ?>
                                            <div class="bgm-no-image">No image</div>
                                        <?php endif; ?>
                                        
                                        <div class="bgm-game-info">
                                            <h4><?php echo esc_html($game['name']); ?></h4>
                                            <p class="bgm-game-year"><?php echo esc_html($game['year']); ?></p>
                                            
                                            <form method="post" action="">
                                                <input type="hidden" name="add_game_id" value="<?php echo esc_attr($game['id']); ?>">
                                                <button type="submit" class="button">Import</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Remove Games Tab -->
                <div id="tab-delete" class="bgm-tab-pane">
                    <h2>Remove Games</h2>
                    <p>Select games to remove from your collection.</p>
                    
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'bgm_games';
                    $games = $wpdb->get_results("SELECT id, name, thumb, bgg_id FROM $table_name ORDER BY name ASC");
                    
                    if (empty($games)) {
                        echo '<div class="notice notice-info"><p>No games found in your collection.</p></div>';
                    } else {
                    ?>
                        <div class="bgm-game-list">
                            <?php foreach ($games as $game) : ?>
                                <div class="bgm-game-card">
                                    <?php if (!empty($game->thumb)) : ?>
                                        <img src="<?php echo esc_url($game->thumb); ?>" alt="<?php echo esc_attr($game->name); ?>">
                                    <?php else : ?>
                                        <div class="bgm-no-image">No image</div>
                                    <?php endif; ?>
                                    
                                    <div class="bgm-game-info">
                                        <h4><?php echo esc_html($game->name); ?></h4>
                                        
                                        <form method="post" action="" class="bgm-delete-form">
                                            <input type="hidden" name="delete_game_id" value="<?php echo esc_attr($game->bgg_id); ?>">
                                            <button type="submit" class="button button-link-delete bgm-delete-btn" 
                                                    onclick="return confirm('Are you sure you want to delete this game? This cannot be undone.');">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* Tabs styling */
    .bgm-tabs {
        margin-top: 20px;
    }

    .bgm-tab-nav {
        display: flex;
        border-bottom: 1px solid #ccc;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .bgm-tab-nav li {
        margin-bottom: -1px;
    }

    .bgm-tab-nav li a {
        display: block;
        padding: 10px 15px;
        text-decoration: none;
        border: 1px solid transparent;
        border-bottom: none;
        color: #0073aa;
    }

    .bgm-tab-nav li.active a {
        border-color: #ccc;
        border-bottom-color: #f1f1f1;
        background: #f1f1f1;
        color: #23282d;
    }

    .bgm-tab-content {
        background: #f1f1f1;
        border: 1px solid #ccc;
        border-top: none;
        padding: 20px;
    }

    .bgm-tab-pane {
        display: none;
    }

    .bgm-tab-pane.active {
        display: block;
    }

    /* Game cards styling */
    .bgm-search-results, .bgm-game-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        grid-gap: 20px;
        margin-top: 20px;
    }

    .bgm-game-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .bgm-game-card img {
        width: 100%;
        height: 150px;
        object-fit: contain;
        background: #f9f9f9;
        padding: 10px;
    }

    .bgm-no-image {
        width: 100%;
        height: 150px;
        background: #f9f9f9;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
    }

    .bgm-game-info {
        padding: 15px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }

    .bgm-game-info h4 {
        margin-top: 0;
        margin-bottom: 5px;
    }

    .bgm-game-year {
        color: #666;
        margin-top: 0;
        margin-bottom: 15px;
    }

    .bgm-game-info form {
        margin-top: auto;
    }

    .bgm-delete-btn {
        color: #a00;
    }

    .bgm-delete-btn:hover {
        color: #dc3232;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Tab functionality
        $('.bgm-tab-nav a').on('click', function(e) {
            e.preventDefault();
            
            // Activate tab
            $('.bgm-tab-nav li').removeClass('active');
            $(this).parent().addClass('active');
            
            // Show corresponding tab pane
            var target = $(this).attr('href');
            $('.bgm-tab-pane').removeClass('active');
            $(target).addClass('active');
        });
    });
    </script>
    <?php
}