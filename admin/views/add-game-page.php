<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Render the add games page
 * 
 * @param array $search_results BGG search results if available
 * @param array $result Game import result if available
 */
function bgm_render_add_game_page($search_results = array(), $result = array()) {
    ?>
    <div class="wrap">
        <h1>Add Games</h1>
        
        <?php
        // Display result messages if any
        if (!empty($result)) {
            $message_class = isset($result['success']) && $result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $message_class . ' is-dismissible"><p>';
            
            // Check if message exists and display appropriate content
            if (!empty($result['message'])) {
                echo esc_html($result['message']);
            } else if (!empty($result['name'])) {
                // Fallback to showing the game name if message is missing
                echo 'Game "' . esc_html($result['name']) . '" ' . 
                    (!empty($result['action']) ? esc_html($result['action']) : 'added') . 
                    ' successfully!';
            } else {
                // Generic success message if nothing else is available
                echo 'Operation completed successfully.';
            }
            
            echo '</p></div>';
        }
        ?>

        <div class="bgm-search-section">
        <h2>Search BoardGameGeek</h2>
        <p>Search for games on BoardGameGeek to add to your collection.</p>
        
        <form method="post" action="" id="bgg-search-form">
            <div class="bgm-thing-filters">
                <p style="margin-top: 0;"><strong>Filter by type:</strong></p>
                <label>
                    <input type="checkbox" name="thing_types[]" value="boardgame" 
                        <?php echo (empty($_POST['thing_types']) || (is_array($_POST['thing_types']) && in_array('boardgame', $_POST['thing_types']))) ? 'checked' : ''; ?>> 
                    Board Game
                </label>
                <label>
                    <input type="checkbox" name="thing_types[]" value="boardgameexpansion" 
                        <?php echo (is_array($_POST['thing_types']) && in_array('boardgameexpansion', $_POST['thing_types'])) ? 'checked' : ''; ?>> 
                    Expansion
                </label>
                <label>
                    <input type="checkbox" name="thing_types[]" value="boardgameaccessory" 
                        <?php echo (is_array($_POST['thing_types']) && in_array('boardgameaccessory', $_POST['thing_types'])) ? 'checked' : ''; ?>> 
                    Accessory
                </label>
                <label>
                    <input type="checkbox" name="thing_types[]" value="rpgitem" 
                        <?php echo (is_array($_POST['thing_types']) && in_array('rpgitem', $_POST['thing_types'])) ? 'checked' : ''; ?>> 
                    RPG Item
                </label>
                <label>
                    <input type="checkbox" name="thing_types[]" value="videogame" 
                        <?php echo (is_array($_POST['thing_types']) && in_array('videogame', $_POST['thing_types'])) ? 'checked' : ''; ?>> 
                    Video Game
                </label>
            </div>
                
            <div style="display: flex; margin-bottom: 20px;">
                <input type="text" name="bgg_search_term" placeholder="Search for games..." style="flex: 1; margin-right: 10px;" required value="<?php echo isset($_POST['bgg_search_term']) ? esc_attr($_POST['bgg_search_term']) : ''; ?>">
                <input type="submit" name="search_bgg" class="button button-primary" value="Search BGG">
            </div>
            <div id="search-loading" class="bgm-loading-indicator" style="display: none;">
                <span class="spinner is-active"></span>
                <span class="loading-text">Searching BoardGameGeek... This may take a moment.</span>
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
                                    <p class="bgm-game-meta">
                                        <span class="bgm-game-year"><?php echo esc_html($game['year']); ?></span>
                                        <?php if(!empty($game['type'])): ?>
                                            <span class="bgm-game-type">
                                                (<?php 
                                                    $type_label = '';
                                                    switch($game['type']) {
                                                        case 'boardgame': $type_label = 'Board Game'; break;
                                                        case 'boardgameexpansion': $type_label = 'Expansion'; break;
                                                        case 'boardgameaccessory': $type_label = 'Accessory'; break;
                                                        case 'rpgitem': $type_label = 'RPG Item'; break;
                                                        case 'videogame': $type_label = 'Video Game'; break;
                                                        default: $type_label = $game['type'];
                                                    }
                                                    echo esc_html($type_label);
                                                ?>)
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    
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
    </div>
    
    <style>
    /* Game cards styling */
    .bgm-search-results {
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
    </style>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Ensure at least one checkbox is checked
            $('.bgm-thing-filters input[type="checkbox"]').on('change', function() {
                if ($('.bgm-thing-filters input[type="checkbox"]:checked').length === 0) {
                    $(this).prop('checked', true);
                    alert('At least one type must be selected.');
                }
            });
            
            // Make sure the form submits correctly
            $('form').on('submit', function() {
                console.log('Form submitted');
                return true; // Allow form submission to proceed
            });
        });
    </script>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Ensure at least one checkbox is checked
        $('.bgm-thing-filters input[type="checkbox"]').on('change', function() {
            if ($('.bgm-thing-filters input[type="checkbox"]:checked').length === 0) {
                $(this).prop('checked', true);
                alert('At least one type must be selected.');
            }
        });
        
        // Show loading indicator when form is submitted
        $('#bgg-search-form').on('submit', function() {
            $('#search-button').prop('disabled', true).val('Searching...');
            $('#search-loading').show();
            return true; // Allow form submission to proceed
        });
    });
    </script>
    <?php
}