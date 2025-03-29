<?php
/**
 * Shortcodes for Board Game Manager
 */
class BGM_Shortcodes {
    private $user_manager;
    
    public function __construct() {
        $this->user_manager = new BGM_User_Manager();
        
        // Register shortcodes
        add_shortcode('bgm_my_lists', array($this, 'render_my_lists'));
        add_shortcode('bgm_manage_game_list', array($this, 'render_manage_game_list'));
        add_shortcode('bgm_add_game', array($this, 'render_add_game'));
        add_shortcode('bgm_edit_game', array($this, 'render_edit_game'));
    }
    
    /**
     * Render the My Lists page
     */
    public function render_my_lists($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your game lists.</p>';
        }
        
        $user_id = get_current_user_id();
        $lists = $this->user_manager->get_user_lists($user_id);
        $can_create = $this->user_manager->can_create_list($user_id);
        
        ob_start();
        ?>
        <div class="bgm-my-lists">
            <div class="bgm-create-list">
                <button type="button" id="bgm-create-list-btn">Create New List</button>
                <form id="bgm-create-list-form" style="display: none;">
                    <input type="text" name="list_name" placeholder="List Name" required>
                    <textarea name="list_description" placeholder="Description (optional)"></textarea>
                    <div class="form-actions">
                        <button type="submit" class="button button-primary">Create List</button>
                        <button type="button" class="button" id="bgm-cancel-create">Cancel</button>
                    </div>
                </form>
            </div>
            
            <div class="bgm-lists-container">
                <?php if (empty($lists)): ?>
                    <p>You haven't created any game lists yet.</p>
                <?php else: ?>
                    <?php 
                    $total_lists = count($lists);
                    foreach ($lists as $index => $list): 
                        $is_first = $index === 0;
                        $is_last = $index === ($total_lists - 1);
                    ?>
                        <div class="bgm-list-item" data-id="<?php echo esc_attr($list->id); ?>" data-order="<?php echo esc_attr($list->sort_order); ?>">
                            <div class="list-controls">
                                <?php if (!$is_first): ?>
                                    <button type="button" class="move-arrow move-up" title="Move list up"></button>
                                <?php endif; ?>
                                <div class="list-grab-handle" title="Drag to reorder"></div>
                                <?php if (!$is_last): ?>
                                    <button type="button" class="move-arrow move-down" title="Move list down"></button>
                                <?php endif; ?>
                            </div>
                            <div class="list-content">
                                <div class="list-header">
                                    <h3><?php echo esc_html($list->name); ?></h3>
                                    <button type="button" class="button button-edit" data-action="edit-list" data-id="<?php echo esc_attr($list->id); ?>" data-name="<?php echo esc_attr($list->name); ?>" data-description="<?php echo esc_attr($list->description); ?>">Edit</button>
                                </div>
                                <?php if (!empty($list->description)): ?>
                                    <p class="list-description"><?php echo esc_html($list->description); ?></p>
                                <?php endif; ?>
                                <div class="list-actions">
                                    <a href="<?php echo esc_url(add_query_arg('list_id', $list->id, get_page_link(get_option('bgm_list_games_page')))); ?>" class="button button-manage">Manage This List</a>
                                    <button type="button" class="button button-delete delete-list" data-id="<?php echo esc_attr($list->id); ?>">Delete This List</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Create list form toggle
            $('#bgm-create-list-btn').on('click', function() {
                $('#bgm-create-list-form').slideDown();
            });
            
            $('#bgm-cancel-create').on('click', function() {
                $('#bgm-create-list-form').slideUp();
            });
            
            // Helper function to get all current list names
            function getCurrentListNames() {
                var names = [];
                $('.bgm-list-item h3').each(function() {
                    names.push($(this).text().toLowerCase().trim());
                });
                return names;
            }
            
            // Create list form submission
            $('#bgm-create-list-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submitButton = $form.find('button[type="submit"]');
                var $nameInput = $form.find('input[name="list_name"]');
                var newListName = $nameInput.val().trim();
                
                // Check for duplicate name
                if (getCurrentListNames().includes(newListName.toLowerCase())) {
                    alert('A list with this name already exists. Please choose a different name.');
                    $nameInput.focus();
                    return false;
                }
                
                // Prevent double submission
                if ($form.data('submitting')) {
                    return false;
                }
                
                $form.data('submitting', true);
                $submitButton.prop('disabled', true).text('Creating...');
                
                var formData = $form.serialize();
                
                $.ajax({
                    url: bgm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bgm_create_list',
                        formData: formData,
                        security: bgm_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            var errorMessage = response.data || 'Error creating list';
                            if (errorMessage.includes('already exists')) {
                                errorMessage = 'A list with this name already exists. Please choose a different name.';
                                $nameInput.focus();
                            }
                            alert(errorMessage);
                            $form.data('submitting', false);
                            $submitButton.prop('disabled', false).text('Create List');
                        }
                    },
                    error: function() {
                        alert('Error creating list. Please try again.');
                        $form.data('submitting', false);
                        $submitButton.prop('disabled', false).text('Create List');
                    }
                });
            });
            
            // Delete list
            $('.delete-list').on('click', function() {
                if (!confirm('Are you sure you want to delete this list?')) {
                    return;
                }
                
                var listId = $(this).data('id');
                
                $.ajax({
                    url: bgm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bgm_delete_list',
                        list_id: listId,
                        security: bgm_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render games in a specific list
     */
    public function render_manage_game_list($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your games.</p>';
        }
        
        $list_id = isset($_GET['list_id']) ? intval($_GET['list_id']) : 0;
        if (!$list_id) {
            return '<p>Please select a list to view.</p>';
        }
        
        $user_id = get_current_user_id();
        $games = $this->user_manager->get_list_games($list_id, $user_id);
        $list = $this->user_manager->get_list($list_id, $user_id);
        
        if (!$list) {
            return '<p>List not found.</p>';
        }
        
        ob_start();
        ?>
        <div class="bgm-manage-game-list">
            <h2><?php echo esc_html($list->name); ?></h2>
            <?php if (!empty($list->description)): ?>
                <p><?php echo esc_html($list->description); ?></p>
            <?php endif; ?>
            
            <div class="bgm-list-actions" style="text-align: center; margin-bottom: 30px;">
                <button type="button" class="bgm-list-manager-addgame-button" data-action="add-game">Add Games</button>
            </div>

            <div id="bgm-add-game-form-container" style="display: none;">
                <div class="bgm-add-game">
                    <h2>Search and Add Games:</h2>
                    
                    <form id="bgm-add-game-form" method="post">
                        <?php 
                        wp_nonce_field('bgm_ajax_nonce', 'bgm_nonce');
                        wp_nonce_field('bgm_get_game_details', 'bgm_game_details_nonce');
                        ?>
                        <input type="hidden" name="action" value="bgm_add_game">
                        <input type="hidden" name="list_id" value="<?php echo esc_attr($list_id); ?>">
                        
                        <div class="bgm-search-filters">
                            <div class="bgm-search-row">
                                <div class="bgm-search-input">
                                    <input type="text" name="search" placeholder="Search for a game...">
                                    <div class="bgm-search-buttons">
                                        <button type="button" class="button" data-action="search">Search</button>
                                        <button type="button" class="button" data-action="cancel-add-game">Cancel</button>
                                    </div>
                                </div>
                                <div class="bgm-type-filters">
                                    <label>
                                        <input type="checkbox" name="thing_types[]" value="boardgame" checked> Board Games
                                    </label>
                                    <label>
                                        <input type="checkbox" name="thing_types[]" value="boardgameexpansion"> Expansions
                                    </label>
                                    <label>
                                        <input type="checkbox" name="thing_types[]" value="boardgameaccessory"> Accessories
                                    </label>
                                    <label>
                                        <input type="checkbox" name="thing_types[]" value="rpgitem"> RPG Items
                                    </label>
                                    <label>
                                        <input type="checkbox" name="thing_types[]" value="videogame"> Video Games
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="search-results" style="display: none;">
                            <div class="search-results-local">
                                <h3 class="database-title">Games in our Database</h3>
                                <div class="search-results-grid"></div>
                                <div class="no-local-results" style="display: none;">
                                    <p class="no-results-message">No matching games found in our database.</p>
                                </div>
                            </div>
                            
                            <div class="search-results-bgg" style="display: none;">
                                <div class="bgg-search-header">
                                    <p>Search BoardGameGeek:</p>
                                    <div class="bgg-search-buttons">
                                        <button type="button" class="button" data-action="search-bgg">Search BGG</button>
                                        <button type="button" class="button" data-action="cancel-add-game">Cancel</button>
                                    </div>
                                </div>
                                <div class="search-results-grid"></div>
                                <div class="load-more-container" style="text-align: center; margin-top: 20px; display: none;">
                                    <p class="load-more-text">Don't see the game you're looking for?</p>
                                    <button type="button" class="button" data-action="load-more">See More Results</button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="selected-game" style="display: none;">
                            <h3>Selected Game</h3>
                            <div class="selected-game-details"></div>
                            <input type="hidden" name="game_id" id="game_id">
                            
                            <?php if ($this->user_manager->can_add_custom_fields($user_id)): ?>
                            <div class="custom-fields">
                                <h4>Custom Fields</h4>
                                <div id="custom-fields-container"></div>
                                <button type="button" class="button" data-action="add-custom-field">Add Custom Field</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (empty($games)): ?>
                <p>No games in this list.</p>
            <?php else: ?>
                <div class="bgm-table-controls">
                    <div class="bgm-table-info">
                        <span class="total-games">Total Games: <strong><?php echo count($games); ?></strong></span>
                        <div class="items-per-page">
                            <label for="per-page">Show:</label>
                            <select id="per-page" class="per-page-select">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span>per page</span>
                        </div>
                    </div>
                    <div class="pagination top"></div>
                </div>

                <div class="bgm-compact-filters">
                    <div class="search-row">
                        <input type="text" data-filter="search" placeholder="Search this list...">
                    </div>
                    <div class="filters-grid">
                        <div class="filter-group">
                            <select name="filterPlayerCount" id="filterPlayerCount">
                                <option value="">Players (Any)</option>
                                <option value="999">2 Player only</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                                <option value="9">9</option>
                                <option value="10">10</option>
                                <option value="12">12+</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <select name="filterComplexity" id="filterComplexity">
                                <option value="">Complexity (Any)</option>
                                <option value="1">Easy</option>
                                <option value="2">Light</option>
                                <option value="3">Medium Light</option>
                                <option value="4">Medium</option>
                                <option value="5">Medium Heavy</option>
                                <option value="6">Heavy</option>
                            </select>
                            <input type="hidden" id="complexityMin" value="0">
                            <input type="hidden" id="complexityMax" value="9999">
                        </div>

                        <div class="filter-group">
                            <select name="filterGameTime" id="filterGameTime">
                                <option value="">Time (Any)</option>
                                <option value="1">1-15 min</option>
                                <option value="2">15-30 min</option>
                                <option value="3">30-60 min</option>
                                <option value="4">1-2 hrs</option>
                                <option value="5">2+ hrs</option>
                            </select>
                            <input type="hidden" id="min" value="0">
                            <input type="hidden" id="max" value="9999">
                        </div>

                        <div class="filter-group">
                            <select name="filterCategory" id="filterCategory">
                                <option value="">Category (Any)</option>
                                <?php 
                                if ($games) {
                                    $categories = array();
                                    foreach ($games as $game) {
                                        if (!empty($game->gamecats)) {
                                            $cats = explode(',', $game->gamecats);
                                            foreach ($cats as $cat) {
                                                $cat = trim($cat);
                                                if (!empty($cat)) {
                                                    $categories[$cat] = $cat;
                                                }
                                            }
                                        }
                                    }
                                    asort($categories);
                                    foreach ($categories as $category) {
                                        echo "<option value='" . esc_attr($category) . "'>" . esc_html($category) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <select name="filterMechanic" id="filterMechanic">
                                <option value="">Mechanic (Any)</option>
                                <?php 
                                if ($games) {
                                    $mechanics = array();
                                    foreach ($games as $game) {
                                        if (!empty($game->gamemechs)) {
                                            $mechs = explode(',', $game->gamemechs);
                                            foreach ($mechs as $mech) {
                                                $mech = trim($mech);
                                                if (!empty($mech)) {
                                                    $mechanics[$mech] = $mech;
                                                }
                                            }
                                        }
                                    }
                                    asort($mechanics);
                                    foreach ($mechanics as $mechanic) {
                                        echo "<option value='" . esc_attr($mechanic) . "'>" . esc_html($mechanic) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <button type="button" class="button" id="resetFilters">Clear All</button>
                        </div>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th class="sortable">Name</th>
                            <th class="sortable">Players</th>
                            <th class="sortable">Play Time</th>
                            <th class="sortable">Complexity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $game): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($game->thumb)): ?>
                                        <img src="<?php echo esc_url($game->thumb); ?>" height="50" alt="<?php echo esc_attr($game->name); ?>">
                                    <?php else: ?>
                                        <div class="no-image">No image</div>
                                    <?php endif; ?>
                                </td>
                                <td data-column="name">
                                    <?php if (!empty($game->bgglink)): ?>
                                        <strong><a href="<?php echo esc_url($game->bgglink); ?>" target="_blank" class="game-name-link" title="View on BGG"><?php echo esc_html($game->name); ?></a></strong>
                                    <?php else: ?>
                                        <strong><?php echo esc_html($game->name); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span data-column="minplayers"><?php echo esc_html($game->minplayers); ?></span>-<span data-column="maxplayers"><?php echo esc_html($game->maxplayers); ?></span>
                                </td>
                                <td>
                                    <span data-column="minplaytime"><?php echo esc_html($game->minplaytime); ?></span>-<span data-column="maxplaytime"><?php echo esc_html($game->maxplaytime); ?></span>
                                </td>
                                <td data-column="complexity"><?php echo number_format($game->complexity, 1); ?></td>
                                <td data-column="gamecats" style="display: none;"><?php echo esc_html($game->gamecats); ?></td>
                                <td data-column="gamemechs" style="display: none;"><?php echo esc_html($game->gamemechs); ?></td>
                                <td>
                                    <button type="button" class="button" data-action="edit-game" data-id="<?php echo esc_attr($game->id); ?>">Edit</button>
                                    <button type="button" class="button button-link-delete" data-action="remove-game" data-id="<?php echo esc_attr($game->list_item_id); ?>">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="bgm-table-controls bottom">
                    <div class="pagination bottom"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        /* WordPress theme overrides for spacing */
        .site-main {
            padding-top: 0 !important;
        }
        .site-content {
            padding-top: 0 !important;
        }
        article.page {
            margin-top: 0 !important;
        }
        .entry-header {
            margin-bottom: 1rem !important;
        }
        .entry-content {
            margin-top: 0 !important;
        }
        
        /* Plugin styles */
        .bgm-manage-game-list {
            max-width: 1200px;
            margin: 0 auto 2rem;
            padding: 0 20px;
        }
        .bgm-manage-game-list h2,
        .bgm-manage-game-list > p {
            text-align: center;
        }

        /* Add Games button styles */
        .bgm-list-actions {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .bgm-list-actions button {
            min-width: 120px;
        }

        /* Compact filters styles */
        .bgm-compact-filters {
            margin: 20px auto;
            max-width: 1200px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .bgm-compact-filters .search-row {
            margin-bottom: 12px;
            padding: 0 1px;
        }

        .bgm-compact-filters .search-row input {
            width: calc(100% - 2px);
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .bgm-compact-filters .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
        }

        .bgm-compact-filters .filter-group {
            position: relative;
        }

        .bgm-compact-filters select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            background-color: white;
            cursor: pointer;
        }

        .bgm-compact-filters #resetFilters {
            width: 100%;
            padding: 6px 8px;
            font-size: 13px;
            white-space: nowrap;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .bgm-compact-filters .filters-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .bgm-compact-filters .filters-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .game-card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: #fff;
        }
        .game-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .game-card img {
            width: 100%;
            height: 150px;
            object-fit: contain;
            border-radius: 2px;
            margin-bottom: 8px;
        }
        .game-card h4 {
            font-size: 0.9em;
            margin: 0 0 4px 0;
            line-height: 1.2;
            color: #333;
        }
        .game-card .game-year {
            font-size: 0.8em;
            color: #666;
            margin: 0 0 4px 0;
        }
        .game-card .game-rank {
            font-size: 0.8em;
            color: #666;
            margin: 0;
        }
        .game-card .source-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            padding: 2px 6px;
            font-size: 0.7em;
            border-radius: 3px;
            background: rgba(255,255,255,0.9);
        }
        .game-card .source-badge.local {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .no-image {
            width: 100%;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            color: #999;
            font-size: 0.8em;
            border-radius: 2px;
            margin-bottom: 8px;
        }
        
        /* Table styles */
        .wp-list-table {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            border-collapse: collapse;
        }
        .wp-list-table th {
            text-align: left;
            padding: 10px;
        }
        .wp-list-table td {
            padding: 10px;
            vertical-align: middle;
        }
        .wp-list-table img {
            display: block;
            max-width: 50px;
            height: auto;
        }
        /* Empty state message */
        .bgm-manage-game-list > p:last-child {
            text-align: center;
            margin: 20px 0;
        }
        .bgg-search-header {
            margin-bottom: 10px;
            text-align: center;
        }

        .bgg-search-header p {
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .bgg-search-buttons {
            margin-bottom: 10px;
        }

        .bgg-search-buttons button {
            min-width: 120px;
            padding: 6px 12px;
        }

        .search-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        /* Message styles */
        .no-results-message {
            color: #dc3545;
            font-weight: 500;
            margin: 10px 0;
            padding: 5px 0;
        }

        /* Add to the CSS section */
        .load-more-container {
            padding: 20px 0;
            border-top: 1px solid #eee;
            margin-top: 20px;
        }
        
        .load-more-text {
            color: #666;
            margin-bottom: 10px;
            font-size: 0.9em;
        }

        /* Add to the CSS section */
        .game-name-link {
            text-decoration: none;
            color: inherit;
            cursor: pointer;
            position: relative;
            display: inline-block;
        }

        .game-name-link:hover {
            color: #2271b1;
        }

        .game-name-link::before {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0s;
            pointer-events: none;
            margin-bottom: 5px;
            z-index: 1000;
        }

        .game-name-link::after {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.8);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0s;
            pointer-events: none;
            margin-bottom: -6px;
            z-index: 1000;
        }

        .game-name-link:hover::before,
        .game-name-link:hover::after {
            opacity: 1;
            visibility: visible;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render the Edit Game form
     */
    public function render_edit_game($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please log in to edit games.</p>';
        }
        
        $game_id = isset($_GET['game_id']) ? intval($_GET['game_id']) : 0;
        if (!$game_id) {
            return '<p>No game specified.</p>';
        }
        
        $user_id = get_current_user_id();
        $game = $this->user_manager->get_game_details($game_id, $user_id);
        
        if (!$game) {
            return '<p>Game not found.</p>';
        }
        
        ob_start();
        ?>
        <div class="bgm-edit-game">
            <h2>Edit Game</h2>
            
            <form id="bgm-edit-game-form" method="post">
                <?php wp_nonce_field('bgm_edit_game', 'bgm_nonce'); ?>
                <input type="hidden" name="action" value="bgm_edit_game">
                <input type="hidden" name="game_id" value="<?php echo esc_attr($game_id); ?>">
                
                <div class="game-details">
                    <h3><?php echo esc_html($game->name); ?></h3>
                    <p>Original data from BoardGameGeek:</p>
                    <ul>
                        <li>Players: <?php echo esc_html($game->minplayers . '-' . $game->maxplayers); ?></li>
                        <li>Time: <?php echo esc_html($game->minplaytime . '-' . $game->maxplaytime . ' min'); ?></li>
                        <li>Complexity: <?php echo esc_html($game->complexity); ?></li>
                    </ul>
                </div>
                
                <?php if ($this->user_manager->can_add_custom_fields($user_id)): ?>
                <div class="custom-fields">
                    <h4>Custom Fields</h4>
                    <div id="custom-fields-container">
                        <?php foreach ($game->custom_fields as $name => $value): ?>
                        <div class="custom-field">
                            <input type="text" name="custom_field_names[]" value="<?php echo esc_attr($name); ?>" required>
                            <input type="text" name="custom_field_values[]" value="<?php echo esc_attr($value); ?>" required>
                            <button type="button" class="remove-field button">Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="add-custom-field" class="button">Add Custom Field</button>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="button button-primary">Save Changes</button>
            </form>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Custom fields functionality
            $('#add-custom-field').on('click', function() {
                var fieldHtml = '<div class="custom-field">' +
                              '<input type="text" name="custom_field_names[]" placeholder="Field Name" required>' +
                              '<input type="text" name="custom_field_values[]" placeholder="Field Value" required>' +
                              '<button type="button" class="remove-field button">Remove</button>' +
                              '</div>';
                
                $('#custom-fields-container').append(fieldHtml);
            });
            
            $(document).on('click', '.remove-field', function() {
                $(this).parent().remove();
            });
            
            // Form submission
            $('#bgm-edit-game-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('custom_fields', JSON.stringify(getCustomFields()));
                formData.append('security', bgm_ajax.nonce);
                
                $.ajax({
                    url: bgm_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('Game updated successfully!');
                            window.location.reload();
                        } else {
                            alert('Error updating game');
                        }
                    }
                });
            });
            
            function getCustomFields() {
                var fields = {};
                $('.custom-field').each(function() {
                    var name = $(this).find('input[name="custom_field_names[]"]').val();
                    var value = $(this).find('input[name="custom_field_values[]"]').val();
                    if (name && value) {
                        fields[name] = value;
                    }
                });
                return fields;
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
} 