jQuery(document).ready(function($) {
    // Variables
    var modal = $('#edit-game-modal');
    var form = $('#edit-game-form');
    
    // Add a clear button next to the search field if it doesn't exist
    if ($('#clear-search').length === 0) {
        $('#game-search').after('<button type="button" id="clear-search" class="button" style="margin-right: 10px;">Clear</button>');
    }
    
    // Edit button functionality
    $('.edit-game').on('click', function() {
        var gameId = $(this).data('id');
        loadGameData(gameId);
    });
    
    // Modal close functionality
    $('.bgm-close, .bgm-cancel').on('click', function() {
        closeModal();
    });
    
    // Close modal when clicking outside of it
    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            closeModal();
        }
    });
    
    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        var submitBtn = form.find('button[type="submit"]');
        var originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Updating...');
        
        // Get form data
        var formData = $(this).serialize();
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bgm_update_game_ajax',
                formData: formData
            },
            success: function(response) {
                submitBtn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>' + response.message + '</p></div>')
                        .insertAfter('.wrap h1')
                        .delay(3000)
                        .fadeOut(400, function() {
                            $(this).remove();
                        });
                    
                    // Update the table row data
                    updateTableRow(response.game);
                    
                    // Close the modal
                    closeModal();
                } else {
                    // Show error message in the form
                    $('<div class="notice notice-error"><p>' + response.message + '</p></div>')
                        .insertAfter(form.find('h2'))
                        .delay(3000)
                        .fadeOut(400, function() {
                            $(this).remove();
                        });
                }
            },
            error: function() {
                submitBtn.prop('disabled', false).text(originalText);
                
                // Show generic error message
                $('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>')
                    .insertAfter(form.find('h2'))
                    .delay(3000)
                    .fadeOut(400, function() {
                        $(this).remove();
                    });
            }
        });
    });
    
    // Real-time search functionality
    var searchTimeout;
    $('#game-search').on('input', function() {
        clearTimeout(searchTimeout);
        var searchTerm = $(this).val();
        var $searchField = $(this);
        
        // Get per_page value from the dropdown
        var perPage = $('select[name="per_page"]').val() || 20;
        var currentPage = 1; // Start at page 1 for new searches
        
        // Show a loading indicator
        if ($('#search-loading-indicator').length === 0) {
            $(this).after('<span id="search-loading-indicator" class="bgm-loading" style="display: none;"></span>');
        }
        
        $('#search-loading-indicator').show();
        
        // Set a small timeout to prevent too many requests while typing
        searchTimeout = setTimeout(function() {
            performSearch(searchTerm, currentPage, perPage, $searchField);
        }, 500);
    });
    
    // Handle the clear button click - SINGLE HANDLER
    $('#clear-search').on('click', function() {
        $('#game-search').val('');
        performSearch('', 1, $('select[name="per_page"]').val() || 20, $('#game-search'));
        $('#game-search').focus();
    });
    
    // Delete button functionality
    $('.delete-game').on('click', function() {
        var gameId = $(this).data('id');
        var gameName = $(this).data('name');
        
        // Set values in the delete confirmation modal
        $('#delete-game-name').text(gameName);
        $('#delete_game_id').val(gameId);
        
        // Show delete modal
        $('#delete-game-modal').css('display', 'block');
    });

    // Close delete modal when clicking the close button or cancel button
    $('.bgm-close, .bgm-cancel-delete').on('click', function() {
        closeDeleteModal();
    });

    // Close delete modal when clicking outside of it
    $(window).on('click', function(event) {
        if ($(event.target).is($('#delete-game-modal'))) {
            closeDeleteModal();
        }
    });

    // Handle delete form submission
    $('#delete-game-form').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Deleting...');
        
        // Get form data
        var formData = $(this).serialize();
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bgm_delete_game_ajax',
                formData: formData
            },
            success: function(response) {
                submitBtn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>' + response.message + '</p></div>')
                        .insertAfter('.wrap h1')
                        .delay(3000)
                        .fadeOut(400, function() {
                            $(this).remove();
                        });
                    
                    // Remove the table row
                    $('#game-row-' + response.game_id).fadeOut(400, function() {
                        $(this).remove();
                    });
                    
                    // Close the modal
                    closeDeleteModal();
                } else {
                    // Show error message in the form
                    $('<div class="notice notice-error"><p>' + response.message + '</p></div>')
                        .insertAfter($('#delete-game-form').find('h2'))
                        .delay(3000)
                        .fadeOut(400, function() {
                            $(this).remove();
                        });
                }
            },
            error: function() {
                submitBtn.prop('disabled', false).text(originalText);
                
                // Show generic error message
                $('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>')
                    .insertAfter($('#delete-game-form').find('h2'))
                    .delay(3000)
                    .fadeOut(400, function() {
                        $(this).remove();
                    });
            }
        });
    });
    
    // HELPER FUNCTIONS
    
    // Function to load game data for editing
    function loadGameData(gameId) {
        // Clear previous form data
        form.find('.notice').remove();
        
        // Show loading in modal
        modal.find('h2').text('Loading game data...');
        modal.css('display', 'block');
        form.hide();
        
        // Get game data via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'bgm_get_game_ajax',
                game_id: gameId,
                security: $('#bgm_edit_game_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    // Populate form with game data
                    var game = response.game;
                    
                    $('#edit_game_id').val(game.id);
                    $('#edit_name').val(game.name);
                    $('#edit_thumb').val(game.thumb);
                    $('#edit_minplayers').val(game.minplayers);
                    $('#edit_maxplayers').val(game.maxplayers);
                    $('#edit_minplaytime').val(game.minplaytime);
                    $('#edit_maxplaytime').val(game.maxplaytime);
                    $('#edit_complexity').val(game.complexity);
                    $('#edit_rating').val(game.rating);
                    $('#edit_year_published').val(game.year_published);
                    $('#edit_publisher').val(game.publisher);
                    $('#edit_designer').val(game.designer);
                    $('#edit_gamecats').val(game.gamecats);
                    $('#edit_gamemechs').val(game.gamemechs);
                    $('#edit_qty').val(game.qty);
                    $('#edit_qtyrented').val(game.qtyrented);
                    $('#edit_description').val(game.description);
                    
                    // Update modal title and show form
                    modal.find('h2').text('Edit Game: ' + game.name);
                    form.show();
                } else {
                    // Show error
                    modal.find('h2').text('Error');
                    $('<div class="notice notice-error"><p>' + response.message + '</p></div>')
                        .insertAfter(modal.find('h2'));
                    
                    // Add close button
                    $('<p><button type="button" class="button bgm-close-error">Close</button></p>')
                        .insertAfter(modal.find('.notice'))
                        .on('click', function() {
                            closeModal();
                        });
                }
            },
            error: function() {
                // Show error
                modal.find('h2').text('Error');
                $('<div class="notice notice-error"><p>Failed to load game data. Please try again.</p></div>')
                    .insertAfter(modal.find('h2'));
                
                // Add close button
                $('<p><button type="button" class="button bgm-close-error">Close</button></p>')
                    .insertAfter(modal.find('.notice'))
                    .on('click', function() {
                        closeModal();
                    });
            }
        });
    }
    
    // Function to update the table row after successful edit
    function updateTableRow(game) {
        var row = $('#game-row-' + game.id);
        
        // Update the table cells
        row.find('td:nth-child(1) img').attr('src', game.thumb).attr('alt', game.name);
        row.find('td:nth-child(2) strong').text(game.name);
        row.find('td:nth-child(3)').text(game.minplayers + '-' + game.maxplayers);
        row.find('td:nth-child(4)').text(game.year_published);
        row.find('td:nth-child(5)').text(parseFloat(game.rating).toFixed(1));
        row.find('td:nth-child(6)').text(parseFloat(game.complexity).toFixed(1));
        
        // Highlight the updated row
        row.addClass('updated-row');
        setTimeout(function() {
            row.removeClass('updated-row');
        }, 3000);
    }
    
    // Function to close the modal
    function closeModal() {
        modal.css('display', 'none');
        form[0].reset();
    }
    
    // Function to close the delete modal
    function closeDeleteModal() {
        $('#delete-game-modal').css('display', 'none');
        $('#delete_game_id').val('');
    }
    
    // Function to perform the search with pagination support
    function performSearch(searchTerm, page, perPage, $searchField) {
        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'bgm_search_games_ajax',
                term: searchTerm,
                per_page: perPage,
                paged: page,
                security: $('#bgm_edit_game_nonce').val()
            },
            success: function(response) {
                $('#search-loading-indicator').hide();
                
                if (response.success) {
                    // Update the table with search results
                    updateGameTable(response);
                    
                    // Update pagination controls
                    updatePagination(response, searchTerm);
                    
                    // Store the search term as a data attribute for pagination use
                    $('body').data('current-search', searchTerm);
                } else {
                    // Show error message
                    $('.wp-list-table tbody').html('<tr><td colspan="8">Error: ' + response.message + '</td></tr>');
                }
                
                // Important: Restore focus to the search field
                $searchField.focus();
            },
            error: function() {
                $('#search-loading-indicator').hide();
                // Show error message
                $('.wp-list-table tbody').html('<tr><td colspan="8">Error searching games. Please try again.</td></tr>');
                
                // Important: Restore focus to the search field
                $searchField.focus();
            }
        });
    }
    
    // Function to update the game table with search results
    function updateGameTable(response) {
        // Clear the current table body
        $('.wp-list-table tbody').empty();
        
        if (response.data.length > 0) {
            // Populate the table with the results
            $.each(response.data, function(index, game) {
                var gameRow = createGameRow(game);
                $('.wp-list-table tbody').append(gameRow);
            });
            
            // Update the displaying count
            $('.displaying-num').text(response.matching_count + ' games');
            
            // Rebind edit and delete buttons
            bindRowActions();
        } else {
            // Show no results message
            $('.wp-list-table tbody').append('<tr><td colspan="8">No games found matching your search.</td></tr>');
            $('.displaying-num').text('0 games');
        }
    }
    
    // Function to update pagination controls
    function updatePagination(response, searchTerm) {
        var totalPages = response.total_pages;
        var currentPage = response.current_page;
        var perPage = response.per_page;
        
        // Update total pages display
        $('.total-pages').text(totalPages);
        
        // Update current page input
        $('.current-page').val(currentPage);
        
        // Update pagination links
        var $paginationLinks = $('.pagination-links');
        
        // Clear existing links
        $paginationLinks.empty();
        
        if (totalPages > 1) {
            // First page link
            if (currentPage > 1) {
                $paginationLinks.append(
                    '<a class="first-page button" data-page="1"><span class="screen-reader-text">First page</span><span aria-hidden="true">«</span></a>'
                );
            } else {
                $paginationLinks.append(
                    '<span class="first-page button disabled" aria-hidden="true">«</span>'
                );
            }
            
            // Previous page link
            if (currentPage > 1) {
                $paginationLinks.append(
                    '<a class="prev-page button" data-page="' + (currentPage - 1) + '"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></a>'
                );
            } else {
                $paginationLinks.append(
                    '<span class="prev-page button disabled" aria-hidden="true">‹</span>'
                );
            }
            
            // Current page indicator
            $paginationLinks.append(
                '<span class="paging-input">' +
                    '<input class="current-page" type="text" name="paged" value="' + currentPage + '" size="1">' +
                    '<span class="tablenav-paging-text"> of <span class="total-pages">' + totalPages + '</span></span>' +
                '</span>'
            );
            
            // Next page link
            if (currentPage < totalPages) {
                $paginationLinks.append(
                    '<a class="next-page button" data-page="' + (currentPage + 1) + '"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>'
                );
            } else {
                $paginationLinks.append(
                    '<span class="next-page button disabled" aria-hidden="true">›</span>'
                );
            }
            
            // Last page link
            if (currentPage < totalPages) {
                $paginationLinks.append(
                    '<a class="last-page button" data-page="' + totalPages + '"><span class="screen-reader-text">Last page</span><span aria-hidden="true">»</span></a>'
                );
            } else {
                $paginationLinks.append(
                    '<span class="last-page button disabled" aria-hidden="true">»</span>'
                );
            }
            
            // Bind click events to the pagination links
            $('.pagination-links a.button').off('click').on('click', function() {
                var page = $(this).data('page');
                var currentSearch = $('body').data('current-search') || '';
                
                // Show loading indicator
                if ($('#search-loading-indicator').length === 0) {
                    $('#game-search').after('<span id="search-loading-indicator" class="bgm-loading" style="display: none;"></span>');
                }
                $('#search-loading-indicator').show();
                
                // Perform search with new page
                performSearch(currentSearch, page, perPage, $('#game-search'));
                
                return false;
            });
            
            // Handle manual page input
            $('.current-page').off('keydown').on('keydown', function(e) {
                if (e.keyCode === 13) { // Enter key
                    e.preventDefault();
                    var page = parseInt($(this).val());
                    if (isNaN(page) || page < 1 || page > totalPages) {
                        return false;
                    }
                    
                    var currentSearch = $('body').data('current-search') || '';
                    performSearch(currentSearch, page, perPage, $('#game-search'));
                }
            });
        }
    }
    
    // Function to create a table row for a game
    function createGameRow(game) {
        var row = $('<tr id="game-row-' + game.id + '"></tr>');
        
        // Thumbnail cell
        var thumbnailCell = $('<td></td>');
        if (game.thumb) {
            thumbnailCell.html('<img src="' + game.thumb + '" height="50" alt="' + game.name + '">');
        } else {
            thumbnailCell.html('<div class="no-image">No image</div>');
        }
        row.append(thumbnailCell);
        
        // Name cell
        var nameCell = $('<td></td>');
        nameCell.html('<strong>' + game.name + '</strong>');
        if (game.bgglink) {
            nameCell.append('<div class="row-actions"><span class="view"><a href="' + game.bgglink + '" target="_blank">View on BGG</a></span></div>');
        }
        row.append(nameCell);
        
        // Players cell
        row.append('<td>' + game.minplayers + '-' + game.maxplayers + '</td>');
        
        // Year cell
        row.append('<td>' + game.year + '</td>');
        
        // Rating cell
        row.append('<td>' + parseFloat(game.rating).toFixed(1) + '</td>');
        
        // Complexity cell
        row.append('<td>' + parseFloat(game.complexity).toFixed(1) + '</td>');
        
        // Edit button cell
        var editCell = $('<td></td>');
        editCell.html('<button type="button" class="button edit-game" data-id="' + game.id + '">Edit</button>');
        row.append(editCell);
        
        // Delete button cell
        var deleteCell = $('<td></td>');
        deleteCell.html('<button type="button" class="button button-link-delete delete-game" data-id="' + game.bgg_id + '" data-name="' + game.name + '">Delete</button>');
        row.append(deleteCell);
        
        return row;
    }
    
    // Function to rebind action buttons after table refresh
    function bindRowActions() {
        // Rebind edit buttons
        $('.edit-game').off('click').on('click', function() {
            var gameId = $(this).data('id');
            loadGameData(gameId);
        });
        
        // Rebind delete buttons
        $('.delete-game').off('click').on('click', function() {
            var gameId = $(this).data('id');
            var gameName = $(this).data('name');
            
            // Set values in the delete confirmation modal
            $('#delete-game-name').text(gameName);
            $('#delete_game_id').val(gameId);
            
            // Show delete modal
            $('#delete-game-modal').css('display', 'block');
        });
    }
});