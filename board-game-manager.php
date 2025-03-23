<?php
/**
 * Plugin Name: Board Game Manager
 * Description: Manage and display board game collections for cafes
 * Version: 1.1.0
 * Author: Your Name
 * Text Domain: board-game-manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('BGM_VERSION', '1.1.0');
define('BGM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BGM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load required files
require_once BGM_PLUGIN_DIR . 'includes/class-database.php';
require_once BGM_PLUGIN_DIR . 'includes/class-bgg-api.php';
require_once BGM_PLUGIN_DIR . 'admin/class-admin.php';
require_once BGM_PLUGIN_DIR . 'public/class-public.php';

// Register activation/deactivation hooks
register_activation_hook(__FILE__, array('BGM_Database', 'activate'));
register_deactivation_hook(__FILE__, array('BGM_Database', 'deactivate'));