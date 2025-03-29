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
define('BGM_PLUGIN_FILE', __FILE__);

// Load required files
require_once BGM_PLUGIN_DIR . 'includes/class-database.php';
require_once BGM_PLUGIN_DIR . 'includes/class-bgg-api.php';
require_once BGM_PLUGIN_DIR . 'includes/class-update-manager.php';
require_once BGM_PLUGIN_DIR . 'includes/class-user-manager.php';
require_once BGM_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once BGM_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
require_once BGM_PLUGIN_DIR . 'admin/class-admin.php';
require_once BGM_PLUGIN_DIR . 'public/class-public.php';

// Register activation/deactivation hooks
register_activation_hook(__FILE__, array('BGM_Database', 'activate'));
register_deactivation_hook(__FILE__, array('BGM_Database', 'deactivate'));

// Initialize the update manager
$bgm_update_manager = new BGM_Update_Manager();

// Initialize plugin functionality
add_action('init', function() {
    // Initialize shortcodes
    new BGM_Shortcodes();
    
    // Initialize AJAX handlers
    new BGM_Ajax_Handlers();
});

// Register actions for processing update batches
add_action('bgm_process_update_batch', array($bgm_update_manager, 'process_update_batch'));

// Enqueue scripts and styles for user functionality
add_action('wp_enqueue_scripts', function() {
    // Enqueue CSS
    wp_enqueue_style(
        'bgm-user-styles',
        BGM_PLUGIN_URL . 'assets/css/user.css',
        array(),
        BGM_VERSION
    );

    // Enqueue jQuery
    wp_enqueue_script('jquery');

    // Enqueue JavaScript
    wp_enqueue_script(
        'bgm-user-scripts',
        BGM_PLUGIN_URL . 'assets/js/user.js',
        array('jquery'),
        BGM_VERSION,
        true
    );

    // Localize script
    wp_localize_script(
        'bgm-user-scripts',
        'bgm_ajax',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bgm_ajax_nonce'),
            'edit_game_url' => add_query_arg('game_id', '', get_page_link(get_option('bgm_edit_game_page')))
        )
    );
});