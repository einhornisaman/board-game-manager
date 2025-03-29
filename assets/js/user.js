jQuery(document).ready(function($) {
    // Function to update arrow visibility
    function updateArrowVisibility() {
        const $items = $('.bgm-list-item');
        const totalItems = $items.length;
        
        $items.each(function(index) {
            const $item = $(this);
            const isFirst = index === 0;
            const isLast = index === (totalItems - 1);
            
            // Remove any existing move arrows
            $item.find('.move-up, .move-down').remove();
            
            // Add arrows based on position
            const $controls = $item.find('.list-controls');
            if (!isFirst) {
                $controls.prepend('<button type="button" class="move-arrow move-up" title="Move list up"></button>');
            }
            if (!isLast) {
                $controls.append('<button type="button" class="move-arrow move-down" title="Move list down"></button>');
            }
        });
    }

    // Initialize arrow visibility on page load
    updateArrowVisibility();

    // Handle arrow clicks for list reordering
    $(document).on('click', '.move-arrow', function(e) {
        e.stopPropagation();
        const $button = $(this);
        const $listItem = $button.closest('.bgm-list-item');
        const moveUp = $button.hasClass('move-up');
        
        if (moveUp) {
            const $prevItem = $listItem.prev('.bgm-list-item');
            if ($prevItem.length) {
                $listItem.insertBefore($prevItem);
                updateArrowVisibility();
            }
        } else {
            const $nextItem = $listItem.next('.bgm-list-item');
            if ($nextItem.length) {
                $listItem.insertAfter($nextItem);
                updateArrowVisibility();
            }
        }
        
        // Update all list orders
        let newOrders = [];
        $('.bgm-list-item').each(function(index) {
            newOrders.push({
                id: $(this).data('id'),
                order: index
            });
        });
        
        // Send the update to the server
        $.ajax({
            url: bgm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bgm_update_list_order',
                orders: newOrders,
                security: bgm_ajax.nonce
            },
            error: function() {
                alert('Error updating list order. Please try again.');
            }
        });
    });
    
    // Initialize drag and drop for list reordering
    let draggedItem = null;
    let lists = $('.bgm-list-item');
    
    lists.each(function() {
        $(this).on('dragstart', function(e) {
            draggedItem = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        });
        
        $(this).on('dragend', function() {
            draggedItem = null;
            $(this).removeClass('dragging');
            $('.bgm-list-item').removeClass('drag-over');
            updateArrowVisibility();
            
            // Update all list orders
            let newOrders = [];
            $('.bgm-list-item').each(function(index) {
                newOrders.push({
                    id: $(this).data('id'),
                    order: index
                });
            });
            
            // Send the update to the server
            $.ajax({
                url: bgm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'bgm_update_list_order',
                    orders: newOrders,
                    security: bgm_ajax.nonce
                },
                error: function() {
                    alert('Error updating list order. Please try again.');
                }
            });
        });
        
        $(this).on('dragover', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            
            if (draggedItem !== this) {
                let rect = this.getBoundingClientRect();
                let midY = rect.top + rect.height / 2;
                
                if (e.originalEvent.clientY < midY) {
                    $(this).before(draggedItem);
                } else {
                    $(this).after(draggedItem);
                }
                updateArrowVisibility();
            }
        });
        
        $(this).on('dragenter', function(e) {
            e.preventDefault();
            if (draggedItem !== this) {
                $(this).addClass('drag-over');
            }
        });
        
        $(this).on('dragleave', function() {
            $(this).removeClass('drag-over');
        });
        
        $(this).on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
        });
        
        // Make the list items draggable
        $(this).attr('draggable', true);
    });

    // Create list form toggle
    $('#bgm-create-list-btn').on('click', function() {
        $('#bgm-create-list-form').slideDown();
    });

    $('#bgm-cancel-create').on('click', function() {
        $('#bgm-create-list-form').slideUp();
        $('#bgm-create-list-form')[0].reset();
    });

    // Create list form submission
    $('#bgm-create-list-form').on('submit', function(e) {
        e.preventDefault();

        // Prevent double submission
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        
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
                    alert(response.message || 'Error creating list');
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
        var $button = $(this);
        
        $.ajax({
            url: bgm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bgm_delete_list',
                list_id: listId,
                security: bgm_ajax.nonce
            },
            beforeSend: function() {
                $button.prop('disabled', true).text('Deleting...');
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error deleting list');
                    $button.prop('disabled', false).text('Delete');
                }
            },
            error: function() {
                alert('Error deleting list. Please try again.');
                $button.prop('disabled', false).text('Delete');
            }
        });
    });

    // Game list functionality
    if ($('.bgm-manage-game-list').length) {
        // Initialize table sorting and pagination state
        var currentSort = {
            column: 1, // Default sort by name
            direction: 'asc'
        };
        var currentPage = 1;
        var itemsPerPage = 20;
        var allRows = [];
        var filteredRows = [];

        // Add game form toggle
        $('[data-action="add-game"]').on('click', function() {
            $('#bgm-add-game-form-container').slideDown();
            // Ensure the search results are hidden initially
            $('#search-results').hide();
            $('.search-results-grid').empty();
            $('.no-local-results').hide();
            $('.search-results-bgg').hide();
        });
        
        // Cancel add game
        $('[data-action="cancel-add-game"]').on('click', function() {
            $('#bgm-add-game-form-container').slideUp();
            // Clear any search results and input
            $('#search-results').hide();
            $('#bgm-add-game-form input[name="search"]').val('');
            $('.search-results-grid').empty();
            $('.no-local-results').hide();
            $('.search-results-bgg').hide();
        });

        // Store all table rows
        allRows = $('.bgm-manage-game-list .wp-list-table tbody tr').toArray();
        filteredRows = allRows.slice();
        updatePagination();

        // Handle items per page change
        $('#per-page').on('change', function() {
            itemsPerPage = parseInt($(this).val());
            currentPage = 1;
            updatePagination();
            displayCurrentPage();
        });

        // Handle pagination clicks
        $(document).on('click', '.pagination button:not([disabled])', function() {
            if ($(this).hasClass('prev')) {
                currentPage--;
            } else if ($(this).hasClass('next')) {
                currentPage++;
            } else {
                currentPage = parseInt($(this).text());
            }
            displayCurrentPage();
            updatePagination();
        });

        function updatePagination() {
            var totalPages = Math.ceil(filteredRows.length / itemsPerPage);
            var paginationHtml = '';

            // Previous button
            paginationHtml += `<button class="prev" ${currentPage === 1 ? 'disabled' : ''}>«</button>`;

            // Page numbers
            for (var i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    paginationHtml += `<button class="${i === currentPage ? 'current' : ''}">${i}</button>`;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    paginationHtml += '<button disabled>...</button>';
                }
            }

            // Next button
            paginationHtml += `<button class="next" ${currentPage === totalPages ? 'disabled' : ''}>»</button>`;

            // Update both top and bottom pagination
            $('.pagination').html(paginationHtml);

            // Update total games count
            $('.total-games strong').text(filteredRows.length);
        }

        function displayCurrentPage() {
            var start = (currentPage - 1) * itemsPerPage;
            var end = start + itemsPerPage;
            var $tbody = $('.bgm-manage-game-list .wp-list-table tbody');
            
            $tbody.empty();
            filteredRows.slice(start, end).forEach(function(row) {
                $tbody.append(row);
            });
        }

        // Handle sort clicks
        $('.bgm-manage-game-list .wp-list-table th.sortable').on('click', function() {
            var column = $(this).index();
            var direction = 'asc';

            // If already sorting by this column, toggle direction
            if (currentSort.column === column) {
                direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            }

            // Update sort state
            currentSort.column = column;
            currentSort.direction = direction;

            // Update UI
            $(this).closest('tr').find('th').removeClass('sort-asc sort-desc');
            $(this).addClass('sort-' + direction);

            // Sort filtered rows
            sortTable(column, direction);

            // Update display
            currentPage = 1;
            updatePagination();
            displayCurrentPage();
        });

        function sortTable(column, direction) {
            filteredRows.sort(function(a, b) {
                var aValue = getCellValue(a, column);
                var bValue = getCellValue(b, column);

                // Handle numeric values
                if (!isNaN(aValue) && !isNaN(bValue)) {
                    aValue = parseFloat(aValue);
                    bValue = parseFloat(bValue);
                }

                if (aValue === bValue) return 0;
                
                if (direction === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
        }

        function getCellValue(row, column) {
            return $(row).children('td').eq(column).text().trim().toLowerCase();
        }

        // Filter functionality
        $('input[data-filter="search"]').on('input', function() {
            clearTimeout(window.searchTimeout);
            window.searchTimeout = setTimeout(filterGames, 300);
        });

        $('#filterPlayerCount, #filterComplexity, #filterGameTime, #filterCategory, #filterMechanic').on('change', filterGames);

        function filterGames() {
            // Reset filtered rows to all rows
            filteredRows = allRows.slice();
            
            // Get filter values
            var searchTerm = $('input[data-filter="search"]').val().toLowerCase();
            var playerCount = $('#filterPlayerCount').val();
            var complexity = $('#filterComplexity').val();
            var gameTime = $('#filterGameTime').val();
            var category = $('#filterCategory').val();
            var mechanic = $('#filterMechanic').val();
            
            // Apply filters
            filteredRows = filteredRows.filter(function(row) {
                var $row = $(row);
                var name = $row.find('[data-column="name"]').text().toLowerCase();
                var minPlayers = parseInt($row.find('[data-column="minplayers"]').text());
                var maxPlayers = parseInt($row.find('[data-column="maxplayers"]').text());
                var minTime = parseInt($row.find('[data-column="minplaytime"]').text());
                var maxTime = parseInt($row.find('[data-column="maxplaytime"]').text());
                var complexityValue = parseFloat($row.find('[data-column="complexity"]').text());
                var categories = $row.find('[data-column="gamecats"]').text().toLowerCase();
                var mechanics = $row.find('[data-column="gamemechs"]').text().toLowerCase();
                
                // Search filter
                if (searchTerm && !name.includes(searchTerm)) {
                    return false;
                }
                
                // Player count filter
                if (playerCount) {
                    if (playerCount === '999') {
                        // Special case for "2 Player only"
                        if (!(minPlayers <= 2 && maxPlayers === 2)) {
                            return false;
                        }
                    } else {
                        var count = parseInt(playerCount);
                        if (!(count >= minPlayers && count <= maxPlayers)) {
                            return false;
                        }
                    }
                }
                
                // Complexity filter
                if (complexity) {
                    var complexityMin = parseFloat($('#complexityMin').val());
                    var complexityMax = parseFloat($('#complexityMax').val());
                    if (!(complexityValue >= complexityMin && complexityValue <= complexityMax)) {
                        return false;
                    }
                }
                
                // Game time filter
                if (gameTime) {
                    var timeMin = parseInt($('#min').val());
                    var timeMax = parseInt($('#max').val());
                    // Check if either min or max time falls within the range
                    if (!((minTime >= timeMin && minTime <= timeMax) || 
                          (maxTime >= timeMin && maxTime <= timeMax) ||
                          (minTime <= timeMin && maxTime >= timeMax))) {
                        return false;
                    }
                }
                
                // Category filter
                if (category && !categories.includes(category.toLowerCase())) {
                    return false;
                }
                
                // Mechanic filter
                if (mechanic && !mechanics.includes(mechanic.toLowerCase())) {
                    return false;
                }
                
                return true;
            });
            
            // Reset to first page and update display
            currentPage = 1;
            updatePagination();
            displayCurrentPage();
        }

        // Clear filters
        $('#resetFilters').on('click', function() {
            // Reset all filter inputs
            $('input[data-filter="search"]').val('');
            $('#filterPlayerCount').val('');
            $('#filterComplexity').val('');
            $('#filterGameTime').val('');
            $('#filterCategory').val('');
            $('#filterMechanic').val('');
            
            // Reset hidden range inputs
            $('#min').val('0');
            $('#max').val('9999');
            $('#complexityMin').val('0');
            $('#complexityMax').val('9999');
            
            // Reset to showing all rows
            filteredRows = allRows.slice();
            currentPage = 1;
            
            // Reset sorting
            currentSort = {
                column: 1,
                direction: 'asc'
            };
            
            // Update UI
            $('.wp-list-table th').removeClass('sort-asc sort-desc');
            $('.wp-list-table th').eq(1).addClass('sort-asc');
            
            // Sort and display
            sortTable(1, 'asc');
            updatePagination();
            displayCurrentPage();
        });

        // Initial sort and display
        sortTable(currentSort.column, currentSort.direction);
        displayCurrentPage();
    }

    // Search functionality
    $('input[name="search"]').on('keypress', function(e) {
        // If Enter key is pressed
        if (e.which === 13) {
            e.preventDefault();
            $('[data-action="search"]').click();
        }
    });

    // Handle search button click
    $('[data-action="search"]').on('click', function() {
        var searchTerm = $('input[name="search"]').val();
        if (!searchTerm) return;
        
        var $button = $(this);
        var $searchResults = $('#search-results');
        var $localResults = $('.search-results-local');
        var $bggResults = $('.search-results-bgg');
        var $localGrid = $localResults.find('.search-results-grid');
        
        // Reset all results
        $localGrid.empty();
        $bggResults.hide().find('.search-results-grid').empty();
        $('.no-local-results').hide();
        $('#selected-game').hide();
        $('.selected-game-details').empty();
        
        // Add and show spinner
        $localGrid.append('<div class="spinner"></div>');
        $searchResults.show();
        $localResults.show(); // Always show local results section on new search
        
        // Get selected thing types
        var selectedTypes = [];
        $('input[name="thing_types[]"]:checked').each(function() {
            selectedTypes.push($(this).val());
        });
        
        // Ensure at least one type is selected
        if (selectedTypes.length === 0) {
            alert('Please select at least one game type.');
            $button.prop('disabled', false).text('Search');
            return;
        }
        
        // First search local database
        $.ajax({
            url: bgm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bgm_search_local',
                search: searchTerm,
                thing_types: selectedTypes,
                security: bgm_ajax.nonce
            },
            success: function(response) {
                displaySearchResults(response.data);
            },
            error: function() {
                $localGrid.html('<div class="error-message">Error searching games. Please try again.</div>');
            }
        });
    });

    function displaySearchResults(games) {
        var $localResults = $('.search-results-local');
        var $resultsGrid = $localResults.find('.search-results-grid');
        var $noResults = $localResults.find('.no-local-results');
        var $databaseTitle = $localResults.find('.database-title');
        var $bggResults = $('.search-results-bgg');
        
        $resultsGrid.empty();
        
        if (games.length === 0) {
            $resultsGrid.hide();
            $noResults.show();
            $databaseTitle.hide();
        } else {
            games.forEach(function(game) {
                var gameCard = createGameCard(game);
                $resultsGrid.append(gameCard);
            });
            $resultsGrid.show();
            $noResults.hide();
            $databaseTitle.show();
        }
        
        $('#search-results').show();
        $('.search-results-local').show();
        // Always show BGG search section after local search
        $bggResults.show();
    }

    // Store BGG search results globally for pagination
    let bggSearchResults = [];
    let currentBggPage = 0;
    const bggItemsPerPage = 10;

    // Handle manual BGG search
    $('[data-action="search-bgg"]').on('click', function() {
        var searchTerm = $('input[name="search"]').val();
        var selectedTypes = [];
        $('input[name="thing_types[]"]:checked').each(function() {
            selectedTypes.push($(this).val());
        });
        
        // Ensure we have a search term and at least one type selected
        if (!searchTerm) {
            alert('Please enter a search term');
            return;
        }
        if (selectedTypes.length === 0) {
            alert('Please select at least one game type');
            return;
        }
        
        // Hide local results
        $('.search-results-local').hide();
        
        // Reset pagination
        currentBggPage = 0;
        bggSearchResults = [];
        
        searchBGG(searchTerm, selectedTypes);
    });

    function searchBGG(searchTerm, selectedTypes) {
        var $bggResults = $('.search-results-bgg');
        var $bggGrid = $bggResults.find('.search-results-grid');
        var $loadMore = $bggResults.find('.load-more-container');
        
        // Clear previous results and show loading state
        $bggGrid.empty().append('<div class="spinner"></div>');
        $loadMore.hide();
        
        $.ajax({
            url: bgm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bgm_search_bgg',
                search: searchTerm,
                thing_types: selectedTypes,
                security: bgm_ajax.nonce
            },
            success: function(response) {
                $bggGrid.empty();
                
                if (response.success && Array.isArray(response.data)) {
                    if (response.data.length === 0) {
                        $bggGrid.html('<div class="no-results"><p>No games found on BoardGameGeek matching your search.</p></div>');
                        return;
                    }
                    
                    // Store all results
                    bggSearchResults = response.data;
                    
                    // Display first page
                    displayBggPage();
                    
                    // Show load more if there are more results
                    if (bggSearchResults.length > bggItemsPerPage) {
                        $loadMore.show();
                    }
                } else {
                    $bggGrid.html('<div class="error-message">' + (response.data || 'Error searching BoardGameGeek') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('BGG Search Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                $bggGrid.html('<div class="error-message">Error searching BoardGameGeek. Please try again.</div>');
            }
        });
    }

    // Handle load more button click
    $('[data-action="load-more"]').on('click', function() {
        currentBggPage++;
        displayBggPage();
    });

    function displayBggPage() {
        var $bggGrid = $('.search-results-bgg .search-results-grid');
        var $loadMore = $('.load-more-container');
        
        // Calculate start and end indices for current page
        const start = currentBggPage * bggItemsPerPage;
        const end = Math.min(start + bggItemsPerPage, bggSearchResults.length);
        
        // Get current page of results
        const currentPageResults = bggSearchResults.slice(start, end);
        
        // Add new results to grid
        currentPageResults.forEach(function(game) {
            var gameCard = createGameCard(game);
            $bggGrid.append(gameCard);
        });
        
        // Hide load more button if we've shown all results
        if (end >= bggSearchResults.length) {
            $loadMore.hide();
        }
    }

    function createGameCard(game) {
        var card = $('<div>', {
            class: 'game-card',
            'data-id': game.id,
            'data-name': game.name,
            style: 'margin: 0 auto;'
        });

        // Add source badge if provided
        if (game.source) {
            card.append($('<div>', {
                class: 'source-badge ' + game.source,
                text: game.source === 'local' ? 'In Database' : 'BGG'
            }));
        }

        // Add image or placeholder
        if (game.thumbnail) {
            card.append($('<img>', {
                src: game.thumbnail,
                alt: game.name
            }));
        } else {
            card.append($('<div>', {
                class: 'no-image',
                text: 'No image'
            }));
        }

        // Create title section container
        var titleSection = $('<div>', {
            class: 'title-section'
        });

        // Add game name
        titleSection.append($('<h4>', { text: game.name }));

        // Add title section to card
        card.append(titleSection);

        // Add year and rank below title section
        if (game.year) {
            card.append($('<p>', {
                class: 'game-year',
                text: game.year
            }));
        }
        if (game.rank) {
            card.append($('<p>', {
                class: 'game-rank',
                text: 'Rank: ' + (game.rank === 999999 ? 'Not Ranked' : game.rank || 'N/A')
            }));
        }

        return card;
    }
    
    // Handle filter changes without auto-refresh
    $('input[name="thing_types[]"]').on('change', function() {
        if ($('input[name="thing_types[]"]:checked').length === 0) {
            $(this).prop('checked', true);
            alert('At least one type must be selected.');
        }
    });
    
    // Select game
    $(document).on('click', '.game-card', function(e) {
        var gameId = $(this).data('id');
        
        $.ajax({
            url: bgm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bgm_get_game_details',
                game_id: gameId,
                security: $('#bgm_game_details_nonce').val()
            },
            beforeSend: function() {
                $('.selected-game-details').html('<div class="spinner"></div>');
                $('#selected-game').show();
            },
            success: function(response) {
                if (response.success) {
                    var game = response.data;
                    selectGame(game);
                } else {
                    alert(response.data || 'Error getting game details');
                    $('#selected-game').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error getting game details:', error);
                alert('Error getting game details. Please try again.');
                $('#selected-game').hide();
            }
        });
    });
    
    // Custom fields functionality
    $('[data-action="add-custom-field"]').on('click', function() {
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
    
    // Filter functionality
    $('[data-filter="players"], [data-filter="complexity"]').on('change', filterGames);
    $('[data-filter="search"]').on('input', function() {
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(filterGames, 300);
    });
    
    function filterGames() {
        // Reset filtered rows to all rows
        filteredRows = allRows.slice();
        
        // Get filter values
        var searchTerm = $('input[data-filter="search"]').val().toLowerCase();
        var playerCount = $('#filterPlayerCount').val();
        var complexity = $('#filterComplexity').val();
        var gameTime = $('#filterGameTime').val();
        var category = $('#filterCategory').val();
        var mechanic = $('#filterMechanic').val();
        
        // Apply filters
        filteredRows = filteredRows.filter(function(row) {
            var $row = $(row);
            var name = $row.find('[data-column="name"]').text().toLowerCase();
            var minPlayers = parseInt($row.find('[data-column="minplayers"]').text());
            var maxPlayers = parseInt($row.find('[data-column="maxplayers"]').text());
            var minTime = parseInt($row.find('[data-column="minplaytime"]').text());
            var maxTime = parseInt($row.find('[data-column="maxplaytime"]').text());
            var complexityValue = parseFloat($row.find('[data-column="complexity"]').text());
            var categories = $row.find('[data-column="gamecats"]').text().toLowerCase();
            var mechanics = $row.find('[data-column="gamemechs"]').text().toLowerCase();
            
            // Search filter
            if (searchTerm && !name.includes(searchTerm)) {
                return false;
            }
            
            // Player count filter
            if (playerCount) {
                if (playerCount === '999') {
                    // Special case for "2 Player only"
                    if (!(minPlayers <= 2 && maxPlayers === 2)) {
                        return false;
                    }
                } else {
                    var count = parseInt(playerCount);
                    if (!(count >= minPlayers && count <= maxPlayers)) {
                        return false;
                    }
                }
            }
            
            // Complexity filter
            if (complexity) {
                var complexityMin = parseFloat($('#complexityMin').val());
                var complexityMax = parseFloat($('#complexityMax').val());
                if (!(complexityValue >= complexityMin && complexityValue <= complexityMax)) {
                    return false;
                }
            }
            
            // Game time filter
            if (gameTime) {
                var timeMin = parseInt($('#min').val());
                var timeMax = parseInt($('#max').val());
                // Check if either min or max time falls within the range
                if (!((minTime >= timeMin && minTime <= timeMax) || 
                      (maxTime >= timeMin && maxTime <= timeMax) ||
                      (minTime <= timeMin && maxTime >= timeMax))) {
                    return false;
                }
            }
            
            // Category filter
            if (category && !categories.includes(category.toLowerCase())) {
                return false;
            }
            
            // Mechanic filter
            if (mechanic && !mechanics.includes(mechanic.toLowerCase())) {
                return false;
            }
            
            return true;
        });
        
        // Reset to first page and update display
        currentPage = 1;
        updatePagination();
        displayCurrentPage();
    }
    
    // Form submission
    $('#bgm-add-game-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        
        if ($form.data('submitting')) {
            return false;
        }
        
        $form.data('submitting', true);
        $submitButton.prop('disabled', true).text('Adding...');
        
        // Get form data
        var formData = new FormData(this);
        
        // Add the nonce
        formData.append('security', bgm_ajax.nonce);
        
        // Add custom fields if any exist
        if ($('#custom-fields-container').length) {
            formData.append('custom_fields', JSON.stringify(getCustomFields()));
        }
        
        $.ajax({
            url: bgm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || 'Error adding game. Please try again.');
                    $form.data('submitting', false);
                    $submitButton.prop('disabled', false).text('Add Game to List');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                alert('Error adding game. Please try again.');
                $form.data('submitting', false);
                $submitButton.prop('disabled', false).text('Add Game to List');
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
    
    // Remove game
    $('[data-action="remove-game"]').on('click', function() {
        if (!confirm('Are you sure you want to remove this game from the list?')) {
            return;
        }
        
        var itemId = $(this).data('id');
        
        $.ajax({
            url: bgm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bgm_remove_game',
                item_id: itemId,
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
    
    // Edit game
    $('[data-action="edit-game"]').on('click', function() {
        var gameId = $(this).data('id');
        window.location.href = bgm_ajax.edit_game_url + gameId;
    });

    // Edit list
    $('[data-action="edit-list"]').on('click', function() {
        var $listCard = $(this).closest('.bgm-list-card');
        var $form = $listCard.find('.bgm-edit-list-form');
        $form.slideDown();
    });
    
    $('[data-action="cancel-edit"]').on('click', function() {
        var $form = $(this).closest('.bgm-edit-list-form');
        $form.slideUp();
    });
    
    $('.bgm-edit-list-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        
        if ($form.data('submitting')) {
            return false;
        }
        
        $form.data('submitting', true);
        $submitButton.prop('disabled', true).text('Saving...');
        
        var listId = $form.data('id');
        var formData = $form.serialize();
        
        $.ajax({
            url: bgm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bgm_edit_list',
                list_id: listId,
                formData: formData,
                security: bgm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error updating list');
                    $form.data('submitting', false);
                    $submitButton.prop('disabled', false).text('Save Changes');
                }
            },
            error: function() {
                alert('Error updating list. Please try again.');
                $form.data('submitting', false);
                $submitButton.prop('disabled', false).text('Save Changes');
            }
        });
    });

    // Handle game selection
    function selectGame(game) {
        var $selectedGame = $('#selected-game');
        var $searchResults = $('#search-results');
        
        // Update selected game details
        var details = `
            <div class="selected-game">
                <div class="game-details">
                    <div class="game-content">
                        <div class="game-image">
                            <img src="${game.thumbnail || ''}" alt="${game.name}" class="game-thumbnail">
                        </div>
                        <div class="game-info">
                            <h3>${game.name} (${game.year})</h3>
                            <p>Players: ${game.minplayers}-${game.maxplayers}</p>
                            <p>Playing Time: ${game.minplaytime}-${game.maxplaytime} minutes</p>
                            <p>Complexity: ${game.complexity} / 5</p>
                            <p>Categories: ${game.categories}</p>
                            <p>Mechanics: ${game.mechanics}</p>
                        </div>
                    </div>
                    <div class="game-actions">
                        <input type="hidden" id="game_id" name="game_id" value="${game.id}">
                        <input type="hidden" id="list_id" name="list_id" value="${new URLSearchParams(window.location.search).get('list_id')}">
                        <input type="hidden" name="security" value="${bgm_ajax.nonce}">
                        <button type="button" class="button button-primary" data-action="add-to-list">Add Game to List</button>
                        <button type="button" class="button button-secondary" data-action="cancel-selection">Cancel</button>
                    </div>
                </div>
            </div>
        `;
        
        $selectedGame.find('.selected-game-details').html(details);
        
        // Hide search results and show selected game
        $searchResults.hide();
        $selectedGame.show();
    }

    // Handle cancel selection
    $(document).on('click', '[data-action="cancel-selection"]', function() {
        $('#selected-game').hide();
        $('#search-results').show();
        $('#game_id').val('');
        $('.selected-game-details').empty();
    });

    // Handle add game to list
    $(document).on('click', '[data-action="add-to-list"]', function() {
        var gameId = $('#game_id').val();
        // Try to get list ID from URL parameters first
        var urlParams = new URLSearchParams(window.location.search);
        var listId = urlParams.get('list_id') || $('#list_id').val();
        var $button = $(this);
        
        // Debug info
        console.log('Adding game to list:', {
            gameId: gameId,
            listId: listId,
            nonce: bgm_ajax.nonce,
            ajaxUrl: bgm_ajax.ajax_url,
            currentUrl: window.location.href
        });
        
        if (!gameId) {
            alert('No game selected');
            return;
        }

        if (!listId) {
            alert('No list ID found. Please try refreshing the page.');
            return;
        }
        
        // Prevent double submission
        if ($button.data('submitting')) {
            return;
        }
        
        $button.data('submitting', true)
            .prop('disabled', true)
            .text('Adding...');
        
        $.ajax({
            url: bgm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bgm_add_game_to_list',
                game_id: gameId,
                list_id: listId,
                security: bgm_ajax.nonce
            },
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || 'Error adding game to list');
                    $button.data('submitting', false)
                        .prop('disabled', false)
                        .text('Add Game to List');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error details:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Error adding game to list. Please try again.');
                $button.data('submitting', false)
                    .prop('disabled', false)
                    .text('Add Game to List');
            }
        });
    });

    // Set up filter change handlers
    $('input[data-filter="search"], #filterPlayerCount, #filterComplexity, #filterGameTime, #filterCategory, #filterMechanic').on('change', function() {
        // Update hidden inputs for complexity based on selection
        var complexityFilter = $('#filterComplexity').val();
        if (complexityFilter) {
            switch(complexityFilter) {
                case '1': // Easy
                    $('#complexityMin').val('0');
                    $('#complexityMax').val('1.49');
                    break;
                case '2': // Light
                    $('#complexityMin').val('1.5');
                    $('#complexityMax').val('1.99');
                    break;
                case '3': // Medium Light
                    $('#complexityMin').val('2');
                    $('#complexityMax').val('2.49');
                    break;
                case '4': // Medium
                    $('#complexityMin').val('2.5');
                    $('#complexityMax').val('2.99');
                    break;
                case '5': // Medium Heavy
                    $('#complexityMin').val('3');
                    $('#complexityMax').val('3.49');
                    break;
                case '6': // Heavy
                    $('#complexityMin').val('3.5');
                    $('#complexityMax').val('5');
                    break;
                default:
                    $('#complexityMin').val('0');
                    $('#complexityMax').val('9999');
            }
        }

        // Update hidden inputs for game time based on selection
        var gameTimeFilter = $('#filterGameTime').val();
        if (gameTimeFilter) {
            switch(gameTimeFilter) {
                case '1': // 1-15 minutes
                    $('#min').val('0');
                    $('#max').val('15');
                    break;
                case '2': // 15-30 minutes
                    $('#min').val('15');
                    $('#max').val('30');
                    break;
                case '3': // 30-60 minutes
                    $('#min').val('30');
                    $('#max').val('60');
                    break;
                case '4': // 1-2 hours
                    $('#min').val('60');
                    $('#max').val('120');
                    break;
                case '5': // 2+ hours
                    $('#min').val('120');
                    $('#max').val('9999');
                    break;
                default:
                    $('#min').val('0');
                    $('#max').val('9999');
            }
        }

        filterGames();
    });
}); 