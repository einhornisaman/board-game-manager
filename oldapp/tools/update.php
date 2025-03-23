<?php
// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bgg_updater_error.log');

// Database connection
function getDbConnection() {
    $conn = mysqli_connect("localhost", "u287639072_games", "uAJ5E9ZFvLtVqmQxaDhkRb", "u287639072_games");
    if (!$conn) {
        logMessage("Database connection failed: " . mysqli_connect_error());
        die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}

// Logging function
function logMessage($message) {
    $logFile = __DIR__ . '/bgg_updater.log';
    $timestamp = date('Y-m-d H:i:s', strtotime('-5 hours')); // EST time
    file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
    return $message;
}

// Function to get games that need updating
function getGamesToUpdate($conn, $timeFilter = null, $singleGameId = null, $offset = 0) {
    $where = "doupdate = 1";
    
    if ($singleGameId !== null) {
        $singleGameId = mysqli_real_escape_string($conn, $singleGameId);
        $where = "bggid = '$singleGameId'";
    } elseif ($timeFilter !== null) {
        $interval = "";
        switch ($timeFilter) {
            case "1min": $interval = "INTERVAL 1 MINUTE"; break;
            case "5min": $interval = "INTERVAL 5 MINUTE"; break;
            case "1hour": $interval = "INTERVAL 1 HOUR"; break;
            case "1day": $interval = "INTERVAL 1 DAY"; break;
            case "7days": $interval = "INTERVAL 7 DAY"; break;
            case "2weeks": $interval = "INTERVAL 14 DAY"; break;
            case "1month": $interval = "INTERVAL 1 MONTH"; break;
            default: $interval = ""; break;
        }
        
        if ($interval) {
            $where .= " AND (last_updated IS NULL OR last_updated < NOW() - $interval)";
        }
    }
    
    // Get total count first for pagination
    $countQuery = "SELECT COUNT(*) as total FROM gamedata WHERE $where";
    $countResult = mysqli_query($conn, $countQuery);
    $totalCount = 0;
    
    if ($countResult) {
        $countRow = mysqli_fetch_assoc($countResult);
        $totalCount = $countRow['total'];
    }
    
    $query = "SELECT bggid FROM gamedata WHERE $where ORDER BY last_updated ASC LIMIT 20 OFFSET $offset";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        logMessage("Query error: " . mysqli_error($conn));
        return ['games' => [], 'total' => 0];
    }
    
    $games = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $games[] = $row['bggid'];
    }
    
    return ['games' => $games, 'total' => $totalCount];
}

// Function to search for games in the database
function searchGames($conn, $searchTerm) {
    if (empty($searchTerm)) {
        return [];
    }
    
    $searchTerm = mysqli_real_escape_string($conn, $searchTerm);
    $query = "SELECT bggid, name, last_updated FROM gamedata WHERE name LIKE '%$searchTerm%' ORDER BY name ASC LIMIT 10";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        logMessage("Search query error: " . mysqli_error($conn));
        return [];
    }
    
    $games = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $lastUpdated = $row['last_updated'] ? date('Y-m-d H:i:s', strtotime($row['last_updated'])) : 'Never';
        $games[] = [
            'id' => $row['bggid'],
            'name' => $row['name'],
            'last_updated' => $lastUpdated
        ];
    }
    
    return $games;
}

// Function to update a game from BGG API
function updateGameFromBGG($gameId, $retryCount = 0) {
    $maxRetries = 3;
    $gameId = trim($gameId);
    
    logMessage("Fetching data for game ID: $gameId (Attempt " . ($retryCount + 1) . ")");
    
    $url = 'https://boardgamegeek.com/xmlapi2/thing?stats=1&id=' . $gameId;
    $xml = @simplexml_load_file($url);
    
    if ($xml === false) {
        if ($retryCount < $maxRetries) {
            logMessage("Failed to load XML from BGG. Retrying in " . (($retryCount + 1) * 2) . " seconds...");
            sleep(($retryCount + 1) * 2); // Progressive backoff
            return updateGameFromBGG($gameId, $retryCount + 1);
        } else {
            return [
                'success' => false, 
                'message' => logMessage("Error: Failed to load XML from BGG after $maxRetries attempts for game ID: $gameId")
            ];
        }
    }
    
    try {
        $conn = getDbConnection();
        
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

        $catNames = rtrim($catNames, ", ");
        $mechNames = rtrim($mechNames, ", ");

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
        
        // Set the timestamp in EST
        $timestamp = date('Y-m-d H:i:s', strtotime('-5 hours'));

        // Update the database
        $sql = "UPDATE gamedata SET 
                name='$name', 
                thumb='$thumbnail', 
                minplayers='$minplayers', 
                maxplayers='$maxplayers', 
                minplaytime='$minplaytime', 
                maxplaytime='$maxplaytime', 
                complexity='$complexity', 
                rating='$rating', 
                bgglink='$bgglink', 
                gamecats='$catNames', 
                gamemechs='$mechNames',
                last_updated='$timestamp'
                WHERE bggid='$id'";

        if ($conn->query($sql) === TRUE) {
            $conn->close();
            return [
                'success' => true,
                'message' => logMessage("Successfully updated game: $name (ID: $id)"),
                'name' => $name
            ];
        } else {
            $error = $conn->error;
            $conn->close();
            return [
                'success' => false,
                'message' => logMessage("Database error: $error for game ID: $id")
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => logMessage("Exception: " . $e->getMessage() . " for game ID: $gameId")
        ];
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $conn = getDbConnection();
    
    if ($_POST['action'] === 'get_games') {
        $timeFilter = isset($_POST['time_filter']) ? $_POST['time_filter'] : null;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $result = getGamesToUpdate($conn, $timeFilter, null, $offset);
        echo json_encode([
            'games' => $result['games'], 
            'count' => count($result['games']),
            'total' => $result['total'],
            'offset' => $offset
        ]);
    }
    else if ($_POST['action'] === 'update_game') {
        $gameId = $_POST['game_id'];
        $result = updateGameFromBGG($gameId);
        echo json_encode($result);
    }
    else if ($_POST['action'] === 'search_games') {
        $searchTerm = $_POST['search_term'];
        $games = searchGames($conn, $searchTerm);
        echo json_encode(['games' => $games]);
    }
    
    $conn->close();
    exit;
}

// Function to update a single game (for direct form submission)
function updateSingleGame($gameId) {
    $result = updateGameFromBGG($gameId);
    return $result;
}

// Handle form submission for single game updates
$singleGameResult = null;
if (isset($_POST['single_game_id']) && !empty($_POST['single_game_id'])) {
    $singleGameResult = updateSingleGame($_POST['single_game_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Games Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .progress-box {
            height: 300px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            background-color: #f9f9f9;
            font-family: monospace;
        }
        .controls {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        button, select, input[type="submit"] {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:disabled {
            background-color: #cccccc;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
        hr {
            margin: 20px 0;
            border: 0;
            border-top: 1px solid #eee;
        }
        .search-container {
            position: relative;
            margin-bottom: 20px;
        }
        .search-input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .search-results {
            position: absolute;
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            background-color: white;
            z-index: 1000;
            display: none;
        }
        .search-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        .search-item:hover {
            background-color: #f5f5f5;
        }
        .selected-game {
            margin-top: 10px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
            display: none;
        }
        .game-info {
            margin-bottom: 15px;
        }
        .update-btn {
            margin-top: 10px;
        }
        .loading {
            margin-left: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Update Games Tool</h1>
            
            <!-- Dynamic Game Search -->
            <div>
                <h2>Search and Update Game</h2>
                <div class="search-container">
                    <input type="text" id="gameSearch" class="search-input" placeholder="Type to search for games..." autocomplete="off">
                    <div id="searchResults" class="search-results"></div>
                </div>
                
                <div id="selectedGame" class="selected-game">
                    <div class="game-info">
                        <h3 id="gameName"></h3>
                        <p>BGG ID: <span id="gameId"></span></p>
                        <p>Last Updated: <span id="lastUpdated"></span></p>
                    </div>
                    <button id="updateSelectedBtn" class="update-btn">Update This Game</button>
                    <span id="updateLoading" class="loading">Updating...</span>
                    <div id="updateResult"></div>
                </div>
            </div>
            
            <hr>
            
            <!-- Single Game Update Form (keeping as fallback) -->
            <div>
                <h2>Update by BGG ID</h2>
                <form method="post">
                    <input type="text" name="single_game_id" placeholder="Enter Game ID" required>
                    <input type="submit" value="Update Game">
                </form>
                
                <?php if ($singleGameResult): ?>
                    <div class="<?php echo $singleGameResult['success'] ? 'success' : 'error'; ?>">
                        <?php echo $singleGameResult['message']; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <hr>
            
            <!-- Batch Update Section -->
            <h2>Batch Update Games</h2>
            <div class="controls">
                <select id="timeFilter">
                    <option value="">All flagged games</option>
                    <option value="1min">Not updated in 1 minute</option>
                    <option value="5min">Not updated in 5 minutes</option>
                    <option value="1hour">Not updated in 1 hour</option>
                    <option value="1day">Not updated in 1 day</option>
                    <option value="7days">Not updated in 7 days</option>
                    <option value="2weeks">Not updated in 2 weeks</option>
                    <option value="1month">Not updated in 1 month</option>
                </select>
                <button id="startBtn">Start Update</button>
                <button id="pauseBtn" disabled>Pause</button>
                <button id="resumeBtn" disabled>Resume</button>
            </div>
            
            <div id="status">Ready to start.</div>
            
            <div class="progress-box" id="progressBox"></div>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="index.php">Back to Tools Home</a>
    </div>

    <script>
        // Batch update functionality (keeping original code)
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        const resumeBtn = document.getElementById('resumeBtn');
        const timeFilter = document.getElementById('timeFilter');
        const statusEl = document.getElementById('status');
        const progressBox = document.getElementById('progressBox');
        
        let gamesToUpdate = [];
        let currentIndex = 0;
        let isPaused = false;
        let totalGames = 0;
        let processedGames = 0;
        let currentOffset = 0;
        
        function logToProgress(message, isError = false) {
            const line = document.createElement('div');
            line.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            if (isError) {
                line.classList.add('error');
            }
            progressBox.appendChild(line);
            progressBox.scrollTop = progressBox.scrollHeight;
        }
        
        function updateStatus(message) {
            statusEl.textContent = message;
        }
        
        async function fetchGamesToUpdate(offset = 0) {
            try {
                updateStatus('Fetching games to update...');
                
                const formData = new FormData();
                formData.append('action', 'get_games');
                formData.append('time_filter', timeFilter.value);
                formData.append('offset', offset);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                totalGames = data.total;
                
                if (offset === 0) {
                    logToProgress(`Found a total of ${totalGames} games that need updating.`);
                }
                
                if (data.count === 0) {
                    if (offset === 0) {
                        updateStatus('No games to update.');
                        logToProgress('No games found that match the criteria.');
                        startBtn.disabled = false;
                        pauseBtn.disabled = true;
                        return false;
                    } else {
                        // We've processed all games
                        updateStatus('All games updated.');
                        logToProgress(`Completed updating all ${processedGames} games.`);
                        startBtn.disabled = false;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        return false;
                    }
                }
                
                gamesToUpdate = data.games;
                updateStatus(`Fetched ${data.count} games (batch ${Math.floor(offset/20) + 1} of ${Math.ceil(totalGames/20)})`);
                logToProgress(`Fetched ${data.count} games (batch ${Math.floor(offset/20) + 1} of ${Math.ceil(totalGames/20)})`);
                currentOffset = offset;
                return true;
            } catch (error) {
                updateStatus('Error fetching games.');
                logToProgress(`Error fetching games: ${error.message}`, true);
                return false;
            }
        }
        
        async function updateGame(gameId) {
            try {
                const formData = new FormData();
                formData.append('action', 'update_game');
                formData.append('game_id', gameId);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    logToProgress(`Updated: ${result.name || gameId}`);
                } else {
                    logToProgress(result.message, true);
                }
                
                return result;
            } catch (error) {
                logToProgress(`Error updating game ${gameId}: ${error.message}`, true);
                return { success: false, message: error.message };
            }
        }
        
        async function processGames() {
            if (isPaused) {
                return;
            }
            
            if (currentIndex >= gamesToUpdate.length) {
                // Current batch is complete, fetch next batch
                currentIndex = 0;
                currentOffset += 20;
                processedGames += gamesToUpdate.length;
                
                const hasMoreGames = await fetchGamesToUpdate(currentOffset);
                if (!hasMoreGames) {
                    return; // All done
                }
            }
            
            const gameId = gamesToUpdate[currentIndex];
            updateStatus(`Updating game ${processedGames + currentIndex + 1} of ${totalGames} (ID: ${gameId})`);
            
            await updateGame(gameId);
            
            currentIndex++;
            
            // Add a small delay to avoid overwhelming the API
            setTimeout(processGames, 1000);
        }
        
        startBtn.addEventListener('click', async () => {
            startBtn.disabled = true;
            pauseBtn.disabled = false;
            resumeBtn.disabled = true;
            
            // Reset progress
            currentIndex = 0;
            currentOffset = 0;
            processedGames = 0;
            isPaused = false;
            progressBox.innerHTML = '';
            
            const hasGames = await fetchGamesToUpdate();
            if (hasGames) {
                processGames();
            } else {
                startBtn.disabled = false;
            }
        });
        
        pauseBtn.addEventListener('click', () => {
            isPaused = true;
            pauseBtn.disabled = true;
            resumeBtn.disabled = false;
            updateStatus('Update paused.');
            logToProgress('Update process paused.');
        });
        
        resumeBtn.addEventListener('click', () => {
            isPaused = false;
            pauseBtn.disabled = false;
            resumeBtn.disabled = true;
            updateStatus(`Resuming from game ${processedGames + currentIndex + 1} of ${totalGames}`);
            logToProgress('Update process resumed.');
            processGames();
        });
        
        // New dynamic search functionality
        const gameSearch = document.getElementById('gameSearch');
        const searchResults = document.getElementById('searchResults');
        const selectedGame = document.getElementById('selectedGame');
        const gameName = document.getElementById('gameName');
        const gameId = document.getElementById('gameId');
        const lastUpdated = document.getElementById('lastUpdated');
        const updateSelectedBtn = document.getElementById('updateSelectedBtn');
        const updateLoading = document.getElementById('updateLoading');
        const updateResult = document.getElementById('updateResult');
        
        let searchTimeout;
        
        gameSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            
            if (this.value.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchGames(this.value);
            }, 300);
        });
        
        async function searchGames(searchTerm) {
            try {
                const formData = new FormData();
                formData.append('action', 'search_games');
                formData.append('search_term', searchTerm);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                displaySearchResults(data.games);
            } catch (error) {
                console.error('Error searching games:', error);
            }
        }
        
        function displaySearchResults(games) {
            searchResults.innerHTML = '';
            
            if (games.length === 0) {
                const noResults = document.createElement('div');
                noResults.classList.add('search-item');
                noResults.textContent = 'No games found';
                searchResults.appendChild(noResults);
            } else {
                games.forEach(game => {
                    const item = document.createElement('div');
                    item.classList.add('search-item');
                    item.textContent = game.name;
                    item.dataset.id = game.id;
                    item.dataset.name = game.name;
                    item.dataset.lastUpdated = game.last_updated;
                    
                    item.addEventListener('click', function() {
                        selectGame(this.dataset.id, this.dataset.name, this.dataset.lastUpdated);
                    });
                    
                    searchResults.appendChild(item);
                });
            }
            
            searchResults.style.display = 'block';
        }
        
        function selectGame(id, name, lastUpdatedValue) {
            gameName.textContent = name;
            gameId.textContent = id;
            lastUpdated.textContent = lastUpdatedValue;
            
            selectedGame.style.display = 'block';
            searchResults.style.display = 'none';
            updateResult.innerHTML = '';
        }
        
        updateSelectedBtn.addEventListener('click', async function() {
            this.disabled = true;
            updateLoading.style.display = 'inline';
            updateResult.innerHTML = '';
            
            const result = await updateGame(gameId.textContent);
            
            updateLoading.style.display = 'none';
            this.disabled = false;
            
            updateResult.innerHTML = `<div class="${result.success ? 'success' : 'error'}">${result.message}</div>`;
            
            if (result.success) {
                lastUpdated.textContent = new Date().toLocaleString();
            }
        });
        
        // Close search results when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.search-container')) {
                searchResults.style.display = 'none';
            }
        });
    </script>
</body>
</html>