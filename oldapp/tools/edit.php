<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Authentication settings - CHANGE THESE!
$username = "admin";
$password = "YourSecurePassword"; // Change this to a secure password!
$session_timeout = 3600; // 1 hour session timeout

session_start();

// Check if the user is logged in
function isLoggedIn() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        // Check if session has expired
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $GLOBALS['session_timeout'])) {
            // Session expired, logout user
            session_unset();
            session_destroy();
            return false;
        }
        // Update last activity time
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

// Handle login
if (isset($_POST['login'])) {
    if ($_POST['username'] === $username && $_POST['password'] === $password) {
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
    } else {
        $login_error = "Invalid username or password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Function to connect to the database
function getDbConnection() {
    $conn = mysqli_connect("localhost", "u287639072_games", "uAJ5E9ZFvLtVqmQxaDhkRb", "u287639072_games");
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search' && isLoggedIn()) {
    $search_term = $_GET['term'] ?? '';
    $conn = getDbConnection();
    
    // Escape the search term for use in a LIKE query
    $search_term = mysqli_real_escape_string($conn, $search_term);
    
    // Query to search for games matching the term
    $sql = "SELECT bggid, name, thumb FROM gamedata WHERE name LIKE '%$search_term%' ORDER BY name LIMIT 10";
    $result = $conn->query($sql);
    
    $games = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $games[] = [
                'id' => $row['bggid'],
                'name' => $row['name'],
                'thumb' => $row['thumb']
            ];
        }
    }
    
    mysqli_close($conn);
    
    // Return results as JSON
    header('Content-Type: application/json');
    echo json_encode($games);
    exit;
}

// Handle AJAX get game details request
if (isset($_GET['action']) && $_GET['action'] === 'getGame' && isLoggedIn()) {
    $game_id = $_GET['id'] ?? '';
    $conn = getDbConnection();
    
    // Escape the game ID
    $game_id = mysqli_real_escape_string($conn, $game_id);
    
    // Query to get all game details
    $sql = "SELECT * FROM gamedata WHERE bggid = '$game_id'";
    $result = $conn->query($sql);
    
    $game = null;
    if ($result->num_rows > 0) {
        $game = $result->fetch_assoc();
        
        // Format date_added for display if it exists and is not null
        if (isset($game['date_added']) && $game['date_added']) {
            // Convert from MySQL date format (YYYY-MM-DD) to MM-DD-YYYY for display
            $date = new DateTime($game['date_added']);
            $game['date_added_formatted'] = $date->format('m-d-Y');
        } else {
            $game['date_added_formatted'] = '';
        }
    }
    
    mysqli_close($conn);
    
    // Return results as JSON
    header('Content-Type: application/json');
    echo json_encode($game);
    exit;
}

// Handle game update
if (isset($_POST['action']) && $_POST['action'] === 'updateGame' && isLoggedIn()) {
    $response = ['success' => false, 'message' => 'Unknown error occurred'];
    
    // Get form data
    $bggid = $_POST['bggid'] ?? '';
    $thumbnail = $_POST['thumb'] ?? '';
    $minplayers = $_POST['minplayers'] ?? '';
    $maxplayers = $_POST['maxplayers'] ?? '';
    $minplaytime = $_POST['minplaytime'] ?? '';
    $maxplaytime = $_POST['maxplaytime'] ?? '';
    $complexity = $_POST['complexity'] ?? '';
    $rating = $_POST['rating'] ?? '';
    $bgglink = $_POST['bgglink'] ?? '';
    $gamecats = $_POST['gamecats'] ?? '';
    $gamemechs = $_POST['gamemechs'] ?? '';
    $qty = $_POST['qty'] ?? '1';
    $qtyrented = $_POST['qtyrented'] ?? '0';
    $date_added = $_POST['date_added'] ?? '';
    
    // Connect to database
    $conn = getDbConnection();
    
    // Escape all values
    $bggid = mysqli_real_escape_string($conn, $bggid);
    $thumbnail = mysqli_real_escape_string($conn, $thumbnail);
    $minplayers = mysqli_real_escape_string($conn, $minplayers);
    $maxplayers = mysqli_real_escape_string($conn, $maxplayers);
    $minplaytime = mysqli_real_escape_string($conn, $minplaytime);
    $maxplaytime = mysqli_real_escape_string($conn, $maxplaytime);
    $complexity = mysqli_real_escape_string($conn, $complexity);
    $rating = mysqli_real_escape_string($conn, $rating);
    $bgglink = mysqli_real_escape_string($conn, $bgglink);
    $gamecats = mysqli_real_escape_string($conn, $gamecats);
    $gamemechs = mysqli_real_escape_string($conn, $gamemechs);
    $qty = mysqli_real_escape_string($conn, $qty);
    $qtyrented = mysqli_real_escape_string($conn, $qtyrented);
    
    // Format date_added from MM-DD-YYYY to YYYY-MM-DD for MySQL
    $date_added_mysql = '';
    if (!empty($date_added)) {
        // Parse the date from MM-DD-YYYY format
        $date_parts = explode('-', $date_added);
        if (count($date_parts) === 3) {
            // Rearrange to YYYY-MM-DD
            $date_added_mysql = $date_parts[2] . '-' . $date_parts[0] . '-' . $date_parts[1];
            
            // Validate the date
            if (!DateTime::createFromFormat('Y-m-d', $date_added_mysql)) {
                $response = [
                    'success' => false,
                    'message' => "Invalid date format. Please use MM-DD-YYYY format."
                ];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        } else {
            $response = [
                'success' => false,
                'message' => "Invalid date format. Please use MM-DD-YYYY format."
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
    
    $date_added_mysql = mysqli_real_escape_string($conn, $date_added_mysql);
    
    // Set current timestamp for last_updated
    $timestamp = date('Y-m-d H:i:s');
    
    // Update the game in the database
    $date_sql = empty($date_added_mysql) ? "date_added = NULL" : "date_added = '$date_added_mysql'";
    
    $sql = "UPDATE gamedata SET 
                minplayers = '$minplayers',
                maxplayers = '$maxplayers',
                minplaytime = '$minplaytime',
                maxplaytime = '$maxplaytime',
                complexity = '$complexity',
                rating = '$rating',
                gamecats = '$gamecats',
                gamemechs = '$gamemechs',
                qty = '$qty',
                qtyrented = '$qtyrented',
                $date_sql,
                last_updated = '$timestamp'
            WHERE bggid = '$bggid'";
    
    if ($conn->query($sql) === TRUE) {
        $response = [
            'success' => true,
            'message' => 'Game updated successfully!'
        ];
    } else {
        $response = [
            'success' => false,
            'message' => "Database error: " . $conn->error
        ];
    }
    
    mysqli_close($conn);
    
    // Return response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Editor Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        input[type="text"], input[type="number"], input[type="password"], input[type="date"], textarea {
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
        }
        input[readonly] {
            background-color: #f5f5f5;
            color: #666;
            cursor: not-allowed;
        }
        button, input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 16px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 1em;
            cursor: pointer;
            border-radius: 4px;
            margin-top: 10px;
        }
        .error {
            color: #f44336;
            margin: 15px 0;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 4px;
        }
        .success {
            color: #4caf50;
            margin: 15px 0;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 4px;
        }
        .search-results {
            position: absolute;
            width: 100%;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .search-results div {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        .search-results div:hover {
            background-color: #f5f5f5;
        }
        .search-container {
            position: relative;
            margin-bottom: 30px;
        }
        .result-item {
            display: flex;
            align-items: center;
        }
        .result-item img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
            object-fit: contain;
        }
        .edit-form {
            display: none;
            margin-top: 20px;
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .game-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .game-header img {
            width: 80px;
            height: 80px;
            margin-right: 20px;
            object-fit: contain;
        }
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-col {
            flex: 1;
        }
        .edit-btns {
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Game Editor Tool</h1>
        
        <?php if (isLoggedIn()): ?>
        <div style="text-align: right; margin-bottom: 20px;">
            <a href="?logout=1">Logout</a>
        </div>
        
        <div class="search-container">
            <h2>Search for a Game</h2>
            <input type="text" id="game-search" placeholder="Start typing to search for games...">
            <div id="search-results" class="search-results"></div>
        </div>
        
        <div id="edit-form" class="edit-form">
            <div id="game-header" class="game-header">
                <img id="game-thumb-preview" src="" alt="Game thumbnail">
                <h2 id="game-name"></h2>
            </div>
            
            <form id="update-form">
                <input type="hidden" id="bggid" name="bggid">
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="thumb">Thumbnail URL:</label>
                            <input type="text" id="thumb" name="thumb" readonly>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="bgglink">BGG Link:</label>
                            <input type="text" id="bgglink" name="bgglink" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="minplayers">Min Players:</label>
                            <input type="number" id="minplayers" name="minplayers" min="1">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="maxplayers">Max Players:</label>
                            <input type="number" id="maxplayers" name="maxplayers" min="1">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="minplaytime">Min Playtime (minutes):</label>
                            <input type="number" id="minplaytime" name="minplaytime" min="1">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="maxplaytime">Max Playtime (minutes):</label>
                            <input type="number" id="maxplaytime" name="maxplaytime" min="1">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="complexity">Complexity (0-5):</label>
                            <input type="number" id="complexity" name="complexity" min="0" max="5" step="0.01">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="rating">Rating (0-10):</label>
                            <input type="number" id="rating" name="rating" min="0" max="10" step="0.01">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="qty">Quantity:</label>
                            <input type="number" id="qty" name="qty" min="0">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="qtyrented">Quantity Rented:</label>
                            <input type="number" id="qtyrented" name="qtyrented" min="0">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="date_added">Date Added (MM-DD-YYYY):</label>
                            <input type="text" id="date_added" name="date_added" placeholder="MM-DD-YYYY">
                        </div>
                    </div>
                    <div class="form-col">
                        <!-- Empty column for alignment -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="gamecats">Categories (comma separated):</label>
                    <textarea id="gamecats" name="gamecats" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="gamemechs">Mechanics (comma separated):</label>
                    <textarea id="gamemechs" name="gamemechs" rows="2"></textarea>
                </div>
                
                <div class="edit-btns">
                    <button type="button" id="cancel-edit">Cancel</button>
                    <button type="submit" id="save-game">Save Changes</button>
                </div>
            </form>
        </div>
        
        <div id="status-message"></div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php">Back to Tools Home</a>
        </div>
        
        <?php else: ?>
            <div class="login-form">
                <?php if (isset($login_error)): ?>
                    <div class="error"><?php echo $login_error; ?></div>
                <?php endif; ?>
                
                <form method="post" style="max-width: 400px; margin: 0 auto;">
                    <h2>Login</h2>
                    <div style="margin-bottom: 15px;">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="login">Login</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (isLoggedIn()): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('game-search');
            const searchResults = document.getElementById('search-results');
            const editForm = document.getElementById('edit-form');
            const updateForm = document.getElementById('update-form');
            const cancelEditBtn = document.getElementById('cancel-edit');
            const statusMessage = document.getElementById('status-message');
            const gameNameElement = document.getElementById('game-name');
            const gameThumbPreview = document.getElementById('game-thumb-preview');
            const dateAddedInput = document.getElementById('date_added');
            
            // Date input validation
            dateAddedInput.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value === '') return; // Empty is allowed
                
                // Check if the format matches MM-DD-YYYY
                const regex = /^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])-\d{4}$/;
                if (!regex.test(value)) {
                    showStatusMessage('Please enter date in MM-DD-YYYY format.', 'error');
                    this.focus();
                }
            });
            
            // Set up search input event
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                const term = this.value.trim();
                
                // Clear any existing timeout
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                // Clear results if search term is too short
                if (term.length < 2) {
                    searchResults.innerHTML = '';
                    searchResults.style.display = 'none';
                    return;
                }
                
                // Set a timeout to reduce API calls while typing
                searchTimeout = setTimeout(function() {
                    fetchSearchResults(term);
                }, 300);
            });
            
            // Function to fetch search results
            function fetchSearchResults(term) {
                // Clear any existing status messages when searching for a new game
                statusMessage.innerHTML = '';
                
                fetch(`?action=search&term=${encodeURIComponent(term)}`)
                    .then(response => response.json())
                    .then(data => {
                        // Clear previous results
                        searchResults.innerHTML = '';
                        
                        if (data.length === 0) {
                            searchResults.style.display = 'none';
                            return;
                        }
                        
                        // Display results
                        data.forEach(game => {
                            const div = document.createElement('div');
                            div.className = 'result-item';
                            div.innerHTML = `
                                <img src="${game.thumb || 'placeholder.jpg'}" alt="${game.name}" onerror="this.src='placeholder.jpg'">
                                <span>${game.name}</span>
                            `;
                            div.addEventListener('click', function() {
                                loadGameDetails(game.id);
                                searchInput.value = game.name;
                                searchResults.style.display = 'none';
                            });
                            searchResults.appendChild(div);
                        });
                        
                        searchResults.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error fetching search results:', error);
                    });
            }
            
            // Function to load a game's details for editing
            function loadGameDetails(gameId) {
                // Clear any existing status messages
                statusMessage.innerHTML = '';
                
                fetch(`?action=getGame&id=${encodeURIComponent(gameId)}`)
                    .then(response => response.json())
                    .then(game => {
                        if (!game) {
                            showStatusMessage('Game not found.', 'error');
                            return;
                        }
                        
                        // Populate the form with game details
                        document.getElementById('bggid').value = game.bggid;
                        document.getElementById('thumb').value = game.thumb;
                        document.getElementById('minplayers').value = game.minplayers;
                        document.getElementById('maxplayers').value = game.maxplayers;
                        document.getElementById('minplaytime').value = game.minplaytime;
                        document.getElementById('maxplaytime').value = game.maxplaytime;
                        document.getElementById('complexity').value = game.complexity;
                        document.getElementById('rating').value = game.rating;
                        document.getElementById('bgglink').value = game.bgglink;
                        document.getElementById('gamecats').value = game.gamecats;
                        document.getElementById('gamemechs').value = game.gamemechs;
                        document.getElementById('qty').value = game.qty;
                        document.getElementById('qtyrented').value = game.qtyrented;
                        
                        // Set date_added value (in MM-DD-YYYY format)
                        document.getElementById('date_added').value = game.date_added_formatted || '';
                        
                        // Update header
                        gameNameElement.textContent = game.name;
                        gameThumbPreview.src = game.thumb || 'placeholder.jpg';
                        gameThumbPreview.onerror = function() {
                            this.src = 'placeholder.jpg';
                        };
                        
                        // Show the edit form
                        editForm.style.display = 'block';
                        
                        // Scroll to the form
                        editForm.scrollIntoView({ behavior: 'smooth' });
                    })
                    .catch(error => {
                        console.error('Error loading game details:', error);
                        showStatusMessage('Error loading game details. Please try again.', 'error');
                    });
            }
            
            // Handle form submission for updating a game
            updateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate date format before submission
                const dateValue = dateAddedInput.value.trim();
                if (dateValue !== '') {
                    const regex = /^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])-\d{4}$/;
                    if (!regex.test(dateValue)) {
                        showStatusMessage('Please enter date in MM-DD-YYYY format.', 'error');
                        dateAddedInput.focus();
                        return;
                    }
                }
                
                // Create form data object
                const formData = new FormData(updateForm);
                formData.append('action', 'updateGame');
                
                // Submit the update
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatusMessage(data.message, 'success');
                        // Update thumbnail preview if it was changed
                        gameThumbPreview.src = document.getElementById('thumb').value;
                    } else {
                        showStatusMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error updating game:', error);
                    showStatusMessage('Error updating game. Please try again.', 'error');
                });
            });
            
            // Handle cancel button
            cancelEditBtn.addEventListener('click', function() {
                editForm.style.display = 'none';
                searchInput.value = '';
                statusMessage.innerHTML = '';
            });
            
            // Function to show status messages
            function showStatusMessage(message, type) {
                statusMessage.innerHTML = `<div class="${type}">${message}</div>`;
                statusMessage.scrollIntoView({ behavior: 'smooth' });
            }
            
            // Close search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>