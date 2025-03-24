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
        <p>Search for games on BoardGameGeek to add to your collection. Results are sorted by BGG rank.</p>
        
        <form method="post" action="" id="bgg-search-form">
        <?php wp_nonce_field('bgm_edit_game_nonce', 'bgm_edit_game_nonce'); ?>
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
                
            <div style="display: flex; margin-bottom: 20px; margin-top: 20px;">
                <input type="text" name="bgg_search_term" placeholder="Search for games..." style="flex: 1; margin-right: 10px;" value="<?php echo isset($_POST['bgg_search_term']) ? esc_attr($_POST['bgg_search_term']) : ''; ?>">
                <input type="submit" name="search_bgg" class="button button-primary" value="Search BGG">
            </div>
            
            <div style="margin-bottom: 20px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;">
                <h4>Or Import Game by ID</h4>
                <div style="display: flex;">
                    <input type="number" name="bgg_id_input" id="bgg_id_input" placeholder="Enter BGG ID..." style="flex: 1; margin-right: 10px;" min="1">
                    <button type="button" id="fetch_by_id_btn" class="button button-secondary">Fetch Game</button>
                </div>
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
                    <p>Found <?php echo count($search_results['games']); ?> games. Results are sorted by BGG rank. Click "Import" to add a game to your collection.</p>
                    
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
                                        <?php if(isset($game['rank']) && $game['rank'] < 999999): ?>
                                            <span class="bgm-game-rank">
                                                <strong>BGG Rank:</strong> <?php echo esc_html($game['rank']); ?>
                                            </span>
                                        <?php elseif(isset($game['rank'])): ?>
                                            <span class="bgm-game-rank">
                                                <strong>BGG Rank:</strong> Unranked
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
    
    <!-- Game Confirmation Modal -->
    <div id="game-confirmation-modal" class="bgm-modal" style="display: none;">
        <div class="bgm-modal-content" style="max-width: 600px;">
            <span class="bgm-modal-close">&times;</span>
            <h2>Confirm Game Import</h2>
            <div id="game-confirm-content" class="bgm-confirm-content">
                <div class="bgm-confirm-loading">
                    <span class="spinner is-active"></span>
                    <p>Fetching game data...</p>
                </div>
                <div class="bgm-confirm-details" style="display: none;">
                    <div class="bgm-confirm-header">
                        <img id="confirm-game-thumb" src="" alt="Game thumbnail">
                        <div>
                            <h3 id="confirm-game-name"></h3>
                            <p id="confirm-game-year"></p>
                        </div>
                    </div>
                    <table class="widefat" style="margin: 15px 0;">
                        <tr>
                            <th>BGG ID:</th>
                            <td id="confirm-game-id"></td>
                        </tr>
                        <tr>
                            <th>Players:</th>
                            <td id="confirm-game-players"></td>
                        </tr>
                        <tr>
                            <th>Playtime:</th>
                            <td id="confirm-game-playtime"></td>
                        </tr>
                        <tr>
                            <th>Complexity:</th>
                            <td id="confirm-game-complexity"></td>
                        </tr>
                        <tr>
                            <th>BGG Rank:</th>
                            <td id="confirm-game-rank"></td>
                        </tr>
                    </table>
                    
                    <form method="post" action="" id="confirm-import-form">
                        <input type="hidden" id="confirm-add-game-id" name="add_game_id" value="">
                        <div class="bgm-confirm-actions">
                            <button type="button" class="button bgm-cancel-import">Cancel</button>
                            <button type="submit" class="button button-primary" id="confirm-import-btn">Add to Collection</button>
                        </div>
                    </form>
                </div>
                <div class="bgm-confirm-error" style="display: none;">
                    <p class="notice notice-error"></p>
                    <button type="button" class="button bgm-cancel-import">Close</button>
                </div>
            </div>
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

    .bgm-game-meta {
        color: #666;
        margin-top: 0;
        margin-bottom: 15px;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .bgm-game-rank {
        color: #d94e4e;
        font-size: 0.9em;
    }

    .bgm-game-info form {
        margin-top: auto;
    }

    /* Modal styles */
    .bgm-modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
    }

    .bgm-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #ddd;
        width: 80%;
        max-width: 800px;
        border-radius: 4px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .bgm-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .bgm-modal-close:hover,
    .bgm-modal-close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }

    /* Confirmation modal specific styles */
    .bgm-confirm-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }

    .bgm-confirm-header img {
        width: 100px;
        height: 100px;
        object-fit: contain;
        background: #f9f9f9;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-right: 15px;
    }

    .bgm-confirm-header h3 {
        margin: 0 0 5px 0;
    }

    .bgm-confirm-actions {
        margin-top: 20px;
        text-align: right;
    }

    .bgm-confirm-actions button {
        margin-left: 10px;
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
            
            // Show loading indicator when form is submitted
            $('#bgg-search-form').on('submit', function() {
                // Only require the search term if the search button was clicked
                if (document.activeElement.name === 'search_bgg') {
                    if ($('input[name="bgg_search_term"]').val().trim() === '') {
                        alert('Please enter a search term');
                        return false;
                    }
                    $('#search-button').prop('disabled', true).val('Searching...');
                    $('#search-loading').show();
                }
                return true; // Allow form submission to proceed
            });
            
            // Handle "Fetch Game by ID" button click
            $('#fetch_by_id_btn').on('click', function() {
                const bggId = $('#bgg_id_input').val().trim();
                
                if (bggId === '' || isNaN(bggId) || bggId <= 0) {
                    alert('Please enter a valid BGG ID');
                    return;
                }
                
                // Show the confirmation modal
                $('#game-confirmation-modal').css('display', 'block');
                $('.bgm-confirm-details, .bgm-confirm-error').hide();
                $('.bgm-confirm-loading').show();
                
                // Fetch game data from the BGG API directly
                fetchGameDataFromBGG(bggId);
            });
            
            // Close modal when clicking the close button
            $('.bgm-modal-close, .bgm-cancel-import').on('click', function() {
                $('#game-confirmation-modal').css('display', 'none');
            });
            
            // Close modal when clicking outside of it
            $(window).on('click', function(event) {
                if ($(event.target).is('#game-confirmation-modal')) {
                    $('#game-confirmation-modal').css('display', 'none');
                }
            });
            
            // Function to fetch game data from BGG
            function fetchGameDataFromBGG(gameId) {
                // Fetch the game data from the BGG API
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bgm_fetch_game_by_id',
                        game_id: gameId,
                        security: $('#bgm_edit_game_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update the confirmation details
                            populateConfirmationModal(response.data);
                        } else {
                            // Show error message
                            $('.bgm-confirm-loading').hide();
                            $('.bgm-confirm-error').show().find('p').text(response.message || 'Failed to fetch game data. Please try again.');
                        }
                    },
                    error: function() {
                        $('.bgm-confirm-loading').hide();
                        $('.bgm-confirm-error').show().find('p').text('Error connecting to the server. Please try again.');
                    }
                });
            }
            
            // Function to populate confirmation modal with game data
            function populateConfirmationModal(game) {
                // Update the game details in the modal
                $('#confirm-game-name').text(game.name);
                $('#confirm-game-year').text(game.year_published ? 'Published: ' + game.year_published : '');
                $('#confirm-game-id').text(game.id);
                $('#confirm-game-players').text(game.minplayers + ' - ' + game.maxplayers + ' players');
                $('#confirm-game-playtime').text(game.minplaytime + ' - ' + game.maxplaytime + ' minutes');
                $('#confirm-game-complexity').text(parseFloat(game.complexity).toFixed(2) + ' / 5.00');
                
                // Handle rank information
                if (game.rank && game.rank < 999999) {
                    $('#confirm-game-rank').text('#' + game.rank);
                } else {
                    $('#confirm-game-rank').text('Unranked');
                }
                
                // Set the thumbnail if available
                if (game.thumbnail) {
                    $('#confirm-game-thumb').attr('src', game.thumbnail).show();
                } else {
                    $('#confirm-game-thumb').hide();
                }
                
                // Set the hidden input for form submission
                $('#confirm-add-game-id').val(game.id);
                
                // Hide loading and show details
                $('.bgm-confirm-loading').hide();
                $('.bgm-confirm-details').show();
            }
        });
    </script>
    <?php
}