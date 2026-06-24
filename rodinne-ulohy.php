<?php
/**
 * Plugin Name: ekidio
 * Plugin URI: https://example.com/ekidio
 * Description: Týždenný plán rodinných domácich prác s automatickou rotáciou úloh medzi deťmi
 * Version: 1.8.2
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: rodinne-ulohy
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('RODINNE_ULOHY_VERSION', '1.8.2');
define('RODINNE_ULOHY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RODINNE_ULOHY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RODINNE_ULOHY_PLUGIN_BASENAME', plugin_basename(__FILE__));
// Developer tools are enabled only in debug environments by default.
// You can override by defining RODINNE_ULOHY_DEV_TOOLS in wp-config.php.
if (!defined('RODINNE_ULOHY_DEV_TOOLS')) {
    define('RODINNE_ULOHY_DEV_TOOLS', (defined('WP_DEBUG') && WP_DEBUG));
}

// Include required files
require_once RODINNE_ULOHY_PLUGIN_DIR . 'includes/class-rodinne-ulohy-database.php';
require_once RODINNE_ULOHY_PLUGIN_DIR . 'includes/class-rodinne-ulohy-ajax.php';
require_once RODINNE_ULOHY_PLUGIN_DIR . 'includes/class-rodinne-ulohy-rotation.php';
require_once RODINNE_ULOHY_PLUGIN_DIR . 'includes/class-rodinne-ulohy-shortcode.php';
require_once RODINNE_ULOHY_PLUGIN_DIR . 'includes/class-rodinne-ulohy-rest.php';
require_once RODINNE_ULOHY_PLUGIN_DIR . 'includes/class-rodinne-ulohy-admin.php';

/**
 * Main plugin class
 */
class Rodinne_Ulohy {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_filter('cron_schedules', array($this, 'add_weekly_schedule'));
        
        // Initialize components
        Rodinne_Ulohy_Database::get_instance();
        Rodinne_Ulohy_Ajax::get_instance();
        Rodinne_Ulohy_Rotation::get_instance();
        Rodinne_Ulohy_Shortcode::get_instance();
        Rodinne_Ulohy_Rest::get_instance();
        Rodinne_Ulohy_Admin::get_instance();
    }
    
    public function activate() {
        Rodinne_Ulohy_Database::create_tables();
        Rodinne_Ulohy_Database::maybe_migrate(); // Run migrations (version-guarded)

        // Schedule automatic rotation (single-event, configurable in app settings).
        Rodinne_Ulohy_Rotation::clear_rotation_schedule();
        Rodinne_Ulohy_Rotation::get_instance()->ensure_rotation_scheduled(true);
        
        // Schedule daily reset for daily tasks
        if (!wp_next_scheduled('rodinne_ulohy_daily_reset')) {
            // Schedule for every day at 22:50 (to count points for the same day)
            wp_schedule_event(strtotime('tomorrow 22:50'), 'daily', 'rodinne_ulohy_daily_reset');
        }
        
        flush_rewrite_rules();
    }
    
    public function add_weekly_schedule($schedules) {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = array(
                'interval' => 604800, // 7 days in seconds
                'display' => __('Raz týždenne', 'rodinne-ulohy')
            );
        }
        return $schedules;
    }
    
    public function deactivate() {
        Rodinne_Ulohy_Rotation::clear_rotation_schedule();
        wp_clear_scheduled_hook('rodinne_ulohy_daily_reset');
        wp_clear_scheduled_hook('rodinne_ulohy_weekend_penalty');
        flush_rewrite_rules();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('rodinne-ulohy', false, dirname(RODINNE_ULOHY_PLUGIN_BASENAME) . '/languages');
    }
    
    public function enqueue_frontend_assets() {
        // Frontend assets if needed
    }
    
}

// Initialize the plugin
Rodinne_Ulohy::get_instance();

