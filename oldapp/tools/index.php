<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Authentication settings - CHANGE THESE!
$username = "admin";
$password = "PenisPump!"; // Change this to a secure password!
$session_timeout = 3600; // 1 hour session timeout

session_start();

// Check if the user is logged in
function isLoggedIn() {
    global $session_timeout;
    
    if (isset($_SESSION['tools_logged_in']) && $_SESSION['tools_logged_in'] === true) {
        // Check if session has expired
        if (isset($_SESSION['tools_last_activity']) && (time() - $_SESSION['tools_last_activity'] > $session_timeout)) {
            // Session expired, logout user
            $_SESSION['tools_logged_in'] = false;
            return false;
        }
        // Update last activity time
        $_SESSION['tools_last_activity'] = time();
        return true;
    }
    return false;
}

// Handle login
if (isset($_POST['login'])) {
    if ($_POST['username'] === $username && $_POST['password'] === $password) {
        $_SESSION['tools_logged_in'] = true;
        $_SESSION['tools_last_activity'] = time();
        
        // Also set the common session variables that your other tools might be using
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
    } else {
        $login_error = "Invalid username or password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to the login page
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Array of tools with their details
$tools = [
    [
        'id' => 'addremove',
        'name' => 'Add or Remove Games',
        'file' => 'addremove.php',
        'description' => 'Search for games on BoardGameGeek and add them to your database, or remove existing games.',
        'icon' => 'plus-minus.png'
    ],
    [
        'id' => 'edit',
        'name' => 'Edit Game Details',
        'file' => 'edit.php',
        'description' => 'Modify details of games already in your database such as player count, playtime, complexity, etc.',
        'icon' => 'edit.png'
    ],
    [
        'id' => 'update',
        'name' => 'Batch Update Games',
        'file' => 'update.php',
        'description' => 'Perform batch operations on multiple games at once, like updating quantities or refreshing game data.',
        'icon' => 'update.png'
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Management Tools</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        .container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        h1 {
            color: #2c3e50;
            margin: 0;
        }
        .logout-link {
            text-decoration: none;
            color: #e74c3c;
            font-weight: bold;
        }
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .tool-card {
            border: 1px solid #e1e1e1;
            border-radius: 8px;
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            background-color: #fff;
            height: 100%;
            box-sizing: border-box;
        }
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }
        .tool-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .tool-icon {
            width: 40px;
            height: 40px;
            margin-right: 15px;
            background-color: #ecf0f1;
            border-radius: 8px;
            padding: 8px;
            box-sizing: border-box;
        }
        .tool-name {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
        }
        .tool-description {
            flex-grow: 1;
            margin-bottom: 20px;
            color: #555;
        }
        .tool-link {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
            transition: background-color 0.2s;
        }
        .tool-link:hover {
            background-color: #2980b9;
        }
        .login-form {
            max-width: 500px; /* Increased from 400px */
            margin: 0 auto;
            padding: 40px; /* Increased padding */
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
            text-align: left;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .form-group input {
            width: 100%;
            max-width: 400px; /* This sets a max width on the input */
            padding: 12px; /* Slightly larger padding */
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
            display: block;
            margin: 0 auto; /* Center the input in the container */
        }
        .login-button {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            max-width: 400px; /* Match the input width */
            font-weight: bold;
            margin: 0 auto; /* Center the button */
            display: block;
        }
        .login-button:hover {
            background-color: #27ae60;
        }
        h1 {
            text-align: center;
            margin-bottom: 40px; /* Increased bottom margin for more space */
            color: #2c3e50;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .auth-note {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #ffeeba;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isLoggedIn()): ?>
            <header>
                <h1>Game Management Tools</h1>
                <a href="?logout=1" class="logout-link">Logout</a>
            </header>
            
            <p>Welcome to the game management dashboard. Select a tool to manage your board game database:</p>
            
            <div class="tools-grid">
                <?php foreach ($tools as $tool): ?>
                    <div class="tool-card">
                        <div class="tool-header">
                            <div class="tool-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <?php if ($tool['id'] === 'addremove'): ?>
                                        <!-- Plus/Minus icon -->
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <?php elseif ($tool['id'] === 'edit'): ?>
                                        <!-- Edit icon -->
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    <?php elseif ($tool['id'] === 'update'): ?>
                                        <!-- Refresh icon -->
                                        <polyline points="23 4 23 10 17 10"></polyline>
                                        <polyline points="1 20 1 14 7 14"></polyline>
                                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                    <?php endif; ?>
                                </svg>
                            </div>
                            <div class="tool-name"><?php echo htmlspecialchars($tool['name']); ?></div>
                        </div>
                        <div class="tool-description"><?php echo htmlspecialchars($tool['description']); ?></div>
                        <a href="<?php echo htmlspecialchars($tool['file']); ?>" class="tool-link">Open Tool</a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="auth-note">
                <strong>Note:</strong> If you're redirected to a login screen when clicking a tool, you may need to log in 
                separately for each tool. Each tool might use its own authentication system.
            </div>
            
        <?php else: ?>
            <div class="login-form">
                <h1>Game Tools Login</h1>
                
                <?php if (isset($login_error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" name="login" class="login-button">Login</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>