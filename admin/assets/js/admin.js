jQuery(document).ready(function($) {
    // Variables
    var modal = $('#edit-game-modal');
    var form = $('#edit-game-form');
    
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

    // Function to close the delete modal
    function closeDeleteModal() {
        $('#delete-game-modal').css('display', 'none');
        $('#delete_game_id').val('');
    }
});