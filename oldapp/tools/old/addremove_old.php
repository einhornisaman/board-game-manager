<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/game_manager_error.log');

// Authentication settings - CHANGE THESE!
$username = "admin";
$password = "PenisPump!"; // Change this to a secure password!
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

// Database connection
function getDbConnection() {
    $conn = mysqli_connect("localhost", "u287639072_games", "uAJ5E9ZFvLtVqmQxaDhkRb", "u287639072_games");
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}

// Variables for different operations
$conn = null;
$bgg_search_results = [];
$bgg_search_error = null;
$db_search_results = [];
$db_search_error = null;
$add_result = null;
$delete_result = null;
$current_mode = 'add'; // Default mode

// Set mode based on query parameter
if (isset($_GET['mode']) && in_array($_GET['mode'], ['add', 'delete'])) {
    $current_mode = $_GET['mode'];
}

// AJAX handlers for database search
if (isset($_GET['db_search']) && isLoggedIn()) {
    header('Content-Type: application/json');
    $search = mysqli_real_escape_string(getDbConnection(), $_GET['db_search']);
    
    $conn = getDbConnection();
    $query = "SELECT bggid, name FROM gamedata WHERE name LIKE '%$search%' ORDER BY name LIMIT 10";
    $result = mysqli_query($conn, $query);
    
    $games = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $games[] = [
            'id' => $row['bggid'],
            'name' => htmlspecialchars($row['name'])
        ];
    }
    
    echo json_encode($games);
    mysqli_close($conn);
    exit;
}

// Handle BGG search
if (isset($_POST['bgg_search_term']) && !empty($_POST['bgg_search_term']) && isLoggedIn()) {
    $search_term = urlencode($_POST['bgg_search_term']);
    $search_url = "https://boardgamegeek.com/xmlapi2/search?type=boardgame,boardgameexpansion&query=$search_term";
    
    $xml = @simplexml_load_file($search_url);
    
    if ($xml === false) {
        $bgg_search_error = "Failed to connect to BoardGameGeek API. Please try again later.";
    } else {
        // Check if we got results
        $total = (int)$xml['total'];
        
        if ($total > 0) {
            foreach ($xml->item as $item) {
                $game_id = (string)$item['id'];
                $name = "";
                
                // Find primary name
                foreach ($item->name as $name_node) {
                    if ((string)$name_node['type'] === 'primary') {
                        $name = (string)$name_node['value'];
                        break;
                    }
                }
                
                // If no primary name found, use the first name
                if (empty($name) && isset($item->name[0])) {
                    $name = (string)$item->name[0]['value'];
                }
                
                // Year published
                $year = (isset($item->yearpublished)) ? (string)$item->yearpublished['value'] : 'N/A';
                
                // Add to results if we have a name
                if (!empty($name)) {
                    $bgg_search_results[] = [
                        'id' => $game_id,
                        'name' => $name,
                        'year' => $year
                    ];
                }
            }
        }
        
        if (empty($bgg_search_results)) {
            $bgg_search_error = "No games found matching your search term.";
        }
    }
}

// Handle adding a game
if (isset($_POST['add_game_id']) && !empty($_POST['add_game_id']) && isLoggedIn()) {
    $game_id = $_POST['add_game_id'];
    $url = 'https://boardgamegeek.com/xmlapi2/thing?stats=1&id=' . $game_id;
    $xml = @simplexml_load_file($url);

    if ($xml === false) {
        $add_result = [
            'success' => false,
            'message' => "Error: Failed to load game data from BGG. Please try again later."
        ];
    } else {
        try {
            $id = (string)$xml->item->attributes()->id;
            $name_actual = (string)$xml->item->name[0]->attributes()->value;
            $name = str_replace("'", "", $name_actual);
            $thumbnail = (string)$xml->item->thumbnail;
            $minplayers = (string)$xml->item->minplayers['value'];
            $maxplayers = (string)$xml->item->maxplayers['value'];
            $minplaytime = (string)$xml->item->minplaytime['value'];
            $maxplaytime = (string)$xml->item->maxplaytime['value'];
            $complexity_actual = (string)$xml->item->statistics->ratings->averageweight['value'];
            $complexity = round((float)$complexity_actual, 2);
            $rating_actual = (string)$xml->item->statistics->ratings->average['value'];
            $rating = round((float)$rating_actual, 2);
            $bgglink = 'https://boardgamegeek.com/boardgame/' . $id;

            $catNames = "";
            $mechNames = "";
            
            foreach ($xml->item->link as $link) {
                if ((string)$link->attributes()->type == "boardgamecategory") {
                    $catNames .= (string)$link->attributes()->value . ", ";
                } elseif ((string)$link->attributes()->type == "boardgamemechanic") {
                    $mechNames .= (string)$link->attributes()->value . ", ";
                }
            }

            // Remove the trailing ", " from the category and mechanic names
            $catNames = rtrim($catNames, ", ");
            $mechNames = rtrim($mechNames, ", ");

            // Connect to your MySQL database
            $conn = getDbConnection();

            // Escape all values to prevent SQL syntax errors
            $name = mysqli_real_escape_string($conn, $name);
            $thumbnail = mysqli_real_escape_string($conn, $thumbnail);
            $minplayers = mysqli_real_escape_string($conn, $minplayers);
            $maxplayers = mysqli_real_escape_string($conn, $maxplayers);
            $minplaytime = mysqli_real_escape_string($conn, $minplaytime);
            $maxplaytime = mysqli_real_escape_string($conn, $maxplaytime);
            $complexity = mysqli_real_escape_string($conn, $complexity);
            $rating = mysqli_real_escape_string($conn, $rating);
            $bgglink = mysqli_real_escape_string($conn, $bgglink);
            $catNames = mysqli_real_escape_string($conn, $catNames);
            $mechNames = mysqli_real_escape_string($conn, $mechNames);

            // Set current timestamp in EST for last_updated
            $timestamp = date('Y-m-d H:i:s', strtotime('-5 hours'));

            // Insert the data into the gamedata table
            $sql = "INSERT INTO gamedata (bggid,name,thumb,minplayers,maxplayers,minplaytime,maxplaytime,complexity,rating,bgglink,gamecats,gamemechs,qty,qtyrented,last_updated) 
            VALUES ('$id','$name','$thumbnail','$minplayers','$maxplayers','$minplaytime','$maxplaytime','$complexity','$rating','$bgglink','$catNames','$mechNames','1','0','$timestamp')
            ON DUPLICATE KEY UPDATE 
                name=VALUES(name), 
                thumb=VALUES(thumb), 
                minplayers=VALUES(minplayers), 
                maxplayers=VALUES(maxplayers), 
                minplaytime=VALUES(minplaytime), 
                maxplaytime=VALUES(maxplaytime), 
                complexity=VALUES(complexity), 
                rating=VALUES(rating), 
                bgglink=VALUES(bgglink), 
                gamecats=VALUES(gamecats), 
                gamemechs=VALUES(gamemechs),
                last_updated=VALUES(last_updated)";

            if ($conn->query($sql) === TRUE) {
                $add_result = [
                    'success' => true,
                    'id' => $id,
                    'name' => $name,
                    'thumbnail' => $thumbnail,
                    'minplayers' => $minplayers,
                    'maxplayers' => $maxplayers,
                    'minplaytime' => $minplaytime,
                    'maxplaytime' => $maxplaytime,
                    'complexity' => $complexity,
                    'rating' => $rating,
                    'catNames' => $catNames,
                    'mechNames' => $mechNames,
                    'bgglink' => $bgglink
                ];
            } else {
                $add_result = [
                    'success' => false,
                    'message' => "Database error: " . $conn->error
                ];
            }
        } catch (Exception $e) {
            $add_result = [
                'success' => false,
                'message' => "Error processing game data: " . $e->getMessage()
            ];
        }
    }
}

// Handle game deletion
if (isset($_POST['delete_game_id']) && !empty($_POST['delete_game_id']) && isLoggedIn()) {
    $game_id = mysqli_real_escape_string(getDbConnection(), $_POST['delete_game_id']);
    
    $conn = getDbConnection();
    
    // Get the game name first for confirmation message
    $nameQuery = "SELECT name FROM gamedata WHERE bggid = '$game_id'";
    $nameResult = mysqli_query($conn, $nameQuery);
    $gameName = "";
    
    if ($nameResult && mysqli_num_rows($nameResult) > 0) {
        $gameRow = mysqli_fetch_assoc($nameResult);
        $gameName = $gameRow['name'];
        
        // Now delete the game
        $query = "DELETE FROM gamedata WHERE bggid = '$game_id'";
        if (mysqli_query($conn, $query)) {
            $delete_result = [
                'success' => true,
                'message' => "Game '$gameName' (ID: $game_id) has been deleted successfully."
            ];
        } else {
            $delete_result = [
                'success' => false,
                'message' => "Error deleting game: " . mysqli_error($conn)
            ];
        }
    } else {
        $delete_result = [
            'success' => false,
            'message' => "Game with ID $game_id not found."
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add or Remove Games Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .mode-selector {
            display: flex;
            margin-bottom: 20px;
            background: #f5f5f5;
            border-radius: 4px;
            overflow: hidden;
        }
        .mode-selector a {
            flex: 1;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            color: #333;
            font-weight: bold;
        }
        .mode-selector a.active {
            background-color: #4CAF50;
            color: white;
        }
        .search-form, .results, .game-details {
            margin-bottom: 30px;
        }
        input[type="text"], input[type="password"] {
            padding: 8px;
            width: 70%;
            box-sizing: border-box;
            margin-right: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
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
        }
        .delete-btn {
            background-color: #f44336;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .action-btn {
            padding: 5px 10px;
            margin: 2px;
            border-radius: 3px;
        }
        .back-link {
            display: inline-block;
            margin: 20px 0;
            text-decoration: none;
            color: #4CAF50;
            font-weight: bold;
        }
        .game-details {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .game-details img {
            margin: 10px 0;
        }
        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }
        .login-form input {
            width: 100%;
            margin-bottom: 15px;
        }
        .suggestion-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
            border: 1px solid #ccc;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            position: absolute;
            width: 70%;
            background: white;
            z-index: 1000;
        }
        .suggestion-list li {
            padding: 10px;
            cursor: pointer;
            background-color: #f9f9f9;
            border-bottom: 1px solid #ddd;
        }
        .suggestion-list li:hover {
            background-color: #e9e9e9;
        }
        .search-container {
            position: relative;
        }
        .confirmation-dialog {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .confirmation-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 5px;
            width: 50%;
            max-width: 500px;
        }
        .confirmation-buttons {
            text-align: right;
            margin-top: 20px;
        }
        .cancel-btn {
            background-color: #ccc;
            margin-right: 10px;
        }
        .selected-game-info {
            margin: 15px 0;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Add or Remove Games Tool</h1>
            
            <?php if (isLoggedIn()): ?>
            <div>
                <a href="?logout=1">Logout</a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (isLoggedIn()): ?>
            <!-- Mode selector -->
            <div class="mode-selector">
                <a href="?mode=add" class="<?php echo $current_mode === 'add' ? 'active' : ''; ?>">Search & Add Games</a>
                <a href="?mode=delete" class="<?php echo $current_mode === 'delete' ? 'active' : ''; ?>">Delete Games</a>
            </div>
            
            <!-- Add Game Mode -->
            <?php if ($current_mode === 'add'): ?>
                
                <?php if (isset($add_result) && $add_result['success']): ?>
                    <div class="success">Game added to database successfully!</div>
                    
                    <div class="game-details">
                        <h2><?php echo $add_result['name']; ?></h2>
                        <img src="<?php echo $add_result['thumbnail']; ?>" alt="<?php echo $add_result['name']; ?>" width="150">
                        <p><strong>Game ID:</strong> <?php echo $add_result['id']; ?></p>
                        <p><strong>Players:</strong> <?php echo $add_result['minplayers']; ?> - <?php echo $add_result['maxplayers']; ?></p>
                        <p><strong>Playtime:</strong> <?php echo $add_result['minplaytime']; ?> - <?php echo $add_result['maxplaytime']; ?> minutes</p>
                        <p><strong>Complexity:</strong> <?php echo $add_result['complexity']; ?> / 5</p>
                        <p><strong>Rating:</strong> <?php echo $add_result['rating']; ?> / 10</p>
                        <p><strong>Categories:</strong> <?php echo $add_result['catNames']; ?></p>
                        <p><strong>Mechanics:</strong> <?php echo $add_result['mechNames']; ?></p>
                        <p><a href="<?php echo $add_result['bgglink']; ?>" target="_blank">View on BoardGameGeek</a></p>
                    </div>
                    
                    <a href="?mode=add" class="back-link">← Search for another game</a>
                
                <?php elseif (isset($add_result) && !$add_result['success']): ?>
                    <div class="error"><?php echo $add_result['message']; ?></div>
                    <a href="?mode=add" class="back-link">← Go back to search</a>
                
                <?php elseif (!empty($bgg_search_results)): ?>
                    <h2>Search Results from BoardGameGeek</h2>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Game Name</th>
                                <th>Year</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bgg_search_results as $game): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($game['name']); ?></td>
                                    <td><?php echo $game['year']; ?></td>
                                    <td>
                                        <form method="post">
                                            <input type="hidden" name="add_game_id" value="<?php echo $game['id']; ?>">
                                            <button type="submit" class="action-btn">Add to Database</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <a href="?mode=add" class="back-link">← New search</a>
                
                <?php else: ?>
                    <div class="search-form">
                        <h2>Search</h2>
                        <form method="post" action="?mode=add">
                            <input type="text" name="bgg_search_term" placeholder="Enter game name..." required>
                            <button type="submit">Search</button>
                        </form>
                        
                        <?php if ($bgg_search_error): ?>
                            <div class="error"><?php echo $bgg_search_error; ?></div>
                        <?php endif; ?>
                        
                        <p style="margin-top: 20px;">
                            <em>Search for a game and add it to database.</em>
                        </p>
                    </div>
                <?php endif; ?>
            
            <!-- Delete Game Mode -->
            <?php elseif ($current_mode === 'delete'): ?>
                
                <?php if ($delete_result): ?>
                    <div class="<?php echo $delete_result['success'] ? 'success' : 'error'; ?>">
                        <?php echo $delete_result['message']; ?>
                    </div>
                <?php endif; ?>
                
                <h2>Delete Game from Database</h2>
                
                <div class="search-container">
                    <input type="text" id="dbGameSearch" placeholder="Start typing a game name...">
                    <ul id="suggestionList" class="suggestion-list"></ul>
                </div>
                
                <form id="deleteForm" method="post" style="display:none;">
                    <input type="hidden" id="deleteGameId" name="delete_game_id">
                    <div id="selectedGameInfo" class="selected-game-info"></div>
                    <button type="button" id="confirmDeleteBtn" class="delete-btn">Delete this Game</button>
                </form>
                
                <div id="confirmationDialog" class="confirmation-dialog">
                    <div class="confirmation-content">
                        <h3>Confirm Deletion</h3>
                        <p>Are you sure you want to delete <span id="confirmGameName"></span>?</p>
                        <p><strong>This action cannot be undone.</strong></p>
                        <div class="confirmation-buttons">
                            <button id="cancelBtn" class="cancel-btn">Cancel</button>
                            <button id="confirmBtn" class="delete-btn">Delete</button>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
        
        <?php else: ?>
            <!-- Login Form -->
            <div class="login-form">
                <?php if (isset($login_error)): ?>
                    <div class="error"><?php echo $login_error; ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <h2>Login</h2>
                    <div>
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div>
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="login">Login</button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($conn): mysqli_close($conn); endif; ?>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="index.php">Back to Tools Home</a>
    </div>

    <?php if (isLoggedIn() && $current_mode === 'delete'): ?>
    <script>
        const dbGameSearch = document.getElementById('dbGameSearch');
        const suggestionList = document.getElementById('suggestionList');
        const deleteForm = document.getElementById('deleteForm');
        const deleteGameId = document.getElementById('deleteGameId');
        const selectedGameInfo = document.getElementById('selectedGameInfo');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const confirmationDialog = document.getElementById('confirmationDialog');
        const confirmGameName = document.getElementById('confirmGameName');
        const cancelBtn = document.getElementById('cancelBtn');
        const confirmBtn = document.getElementById('confirmBtn');
        
        let selectedGameName = '';
        
        // Live search for database games
        dbGameSearch.addEventListener('input', async function() {
            const searchTerm = this.value.trim();
            
            if (searchTerm.length < 2) {
                suggestionList.style.display = 'none';
                return;
            }
            
            try {
                const response = await fetch(`?db_search=${encodeURIComponent(searchTerm)}`);
                const games = await response.json();
                
                suggestionList.innerHTML = '';
                
                if (games.length === 0) {
                    suggestionList.style.display = 'none';
                    return;
                }
                
                games.forEach(game => {
                    const li = document.createElement('li');
                    li.textContent = game.name;
                    li.dataset.id = game.id;
                    li.addEventListener('click', function() {
                        selectGame(this.dataset.id, this.textContent);
                    });
                    suggestionList.appendChild(li);
                });
                
                suggestionList.style.display = 'block';
            } catch (error) {
                console.error('Error fetching search results:', error);
            }
        });
        
        // Select game from search results
        function selectGame(id, name) {
            deleteGameId.value = id;
            selectedGameName = name;
            dbGameSearch.value = name;
            suggestionList.style.display = 'none';
            
            selectedGameInfo.innerHTML = `
                <strong>Selected Game:</strong> ${name} (ID: ${id})
            `;
            
            deleteForm.style.display = 'block';
        }
        
        // Confirmation dialog
        confirmDeleteBtn.addEventListener('click', function() {
            confirmGameName.textContent = selectedGameName;
            confirmationDialog.style.display = 'block';
        });
        
        cancelBtn.addEventListener('click', function() {
            confirmationDialog.style.display = 'none';
        });
        
        confirmBtn.addEventListener('click', function() {
            deleteForm.submit();
        });
        
        // Close suggestions if clicking elsewhere
        document.addEventListener('click', function(event) {
            if (event.target !== dbGameSearch && event.target !== suggestionList) {
                suggestionList.style.display = 'none';
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>