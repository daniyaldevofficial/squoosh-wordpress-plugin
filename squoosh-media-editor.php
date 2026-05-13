<?php
/**
 * Plugin Name: Squoosh Media Editor
 * Plugin URI: https://daniyaldev.com/squoosh-wordpress-plugin
 * Description: Edit WordPress Media Library images directly with the Squoosh WordPress Plugin. Compress, convert, and optimize images without leaving your dashboard.
 * Version: 1.0.0
 * Author: Daniyal Dev
 * Author URI: https://daniyaldev.com
 * License: Apache-2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Text Domain: squoosh-wordpress-plugin
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SQUOOSH_VERSION', '1.0.0');
define('SQUOOSH_PLUGIN_FILE', __FILE__);
define('SQUOOSH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SQUOOSH_PLUGIN_URL', plugin_dir_url(__FILE__));
require_once plugin_dir_path( __FILE__ ) . 'includes/class-squoosh-update.php';
new DD_Plugin_Updater( '', 'squoosh-wordpress', 'squoosh-wordpress/squoosh-media-editor.php' );

/**
 * Main plugin class
 */
class Squoosh_Media_Editor
{

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies()
    {
        require_once SQUOOSH_PLUGIN_DIR . 'includes/class-squoosh-settings.php';
        require_once SQUOOSH_PLUGIN_DIR . 'includes/class-squoosh-media-handler.php';
        require_once SQUOOSH_PLUGIN_DIR . 'includes/class-squoosh-ajax.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Activation/deactivation
        register_activation_hook(SQUOOSH_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(SQUOOSH_PLUGIN_FILE, array($this, 'deactivate'));

        // Admin hooks - Only load if is_admin
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('admin_menu', array($this, 'add_settings_menu'));
            add_action('admin_init', array($this, 'register_settings'));

            // Initialize Admin components
            Squoosh_Settings::get_instance();
            Squoosh_Media_Handler::get_instance();
            Squoosh_Ajax::get_instance();

            require_once SQUOOSH_PLUGIN_DIR . 'includes/class-squoosh-import-export.php';
            Squoosh_Import_Export::get_instance();
        }

        // Initialize Frontend components
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Create backup directory
        if (!file_exists(SQUOOSH_BACKUP_DIR)) {
            wp_mkdir_p(SQUOOSH_BACKUP_DIR);

            // Add .htaccess to protect backups
            $htaccess = SQUOOSH_BACKUP_DIR . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Options -Indexes\n");
            }
        }

        // Set default options
        $defaults = array(
            'replace_mode' => 'backup', // 'backup' or 'delete'
            'auto_replace_content' => 'yes',
            'default_format' => 'same', // 'same', 'webp', 'avif', 'jpeg', 'png'
            'default_quality' => 75,
        );

        foreach ($defaults as $key => $value) {
            if (get_option('squoosh_' . $key) === false) {
                update_option('squoosh_' . $key, $value);
            }
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        flush_rewrite_rules();
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on media pages and our settings page
        $allowed_hooks = array(
            'upload.php',
            'post.php',
            'post-new.php',
            'settings_page_squoosh-settings',
            'media_page_squoosh-bulk-convert'
        );

        // Also load on attachment edit page
        global $post;
        $is_attachment = ($hook === 'post.php' && $post && $post->post_type === 'attachment');

        if (!in_array($hook, $allowed_hooks) && !$is_attachment) {
            return;
        }

        // Admin CSS
        wp_enqueue_style(
            'squoosh-admin',
            SQUOOSH_PLUGIN_URL . 'admin/css/squoosh-admin.css',
            array(),
            SQUOOSH_VERSION
        );

        // Admin JS
        wp_enqueue_script(
            'squoosh-admin',
            SQUOOSH_PLUGIN_URL . 'admin/js/squoosh-admin.js',
            array('jquery'),
            SQUOOSH_VERSION,
            true
        );

        // Localize script
        wp_localize_script('squoosh-admin', 'squooshData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('squoosh_nonce'),
            'editorUrl' => SQUOOSH_PLUGIN_URL . 'index.html',
            'pluginUrl' => SQUOOSH_PLUGIN_URL,
            'replaceMode' => get_option('squoosh_replace_mode', 'backup'),
            'autoReplace' => get_option('squoosh_auto_replace_content', 'yes'),
            'defaultFormat' => get_option('squoosh_default_format', 'same'),
            'defaultQuality' => get_option('squoosh_default_quality', 75),
            'strings' => array(
                'editWithSquoosh' => __('Edit with Squoosh', 'squoosh-media-editor'),
                'saving' => __('Saving...', 'squoosh-media-editor'),
                'saved' => __('Image saved successfully!', 'squoosh-media-editor'),
                'error' => __('Error saving image', 'squoosh-media-editor'),
                'confirmDelete' => __('WARNING: This will permanently delete the original file. Continue?', 'squoosh-media-editor'),
                'converting' => __('Converting images...', 'squoosh-media-editor'),
                'converted' => __('Images converted successfully!', 'squoosh-media-editor'),
            )
        ));

        // Bulk conversion JS (on media library and bulk convert page)
        if ($hook === 'upload.php' || $hook === 'media_page_squoosh-bulk-convert') {
            wp_enqueue_script(
                'squoosh-bulk',
                SQUOOSH_PLUGIN_URL . 'admin/js/squoosh-bulk.js',
                array('jquery', 'squoosh-admin'),
                SQUOOSH_VERSION,
                true
            );
        }
    }

    /**
     * Add settings menu
     */
    public function add_settings_menu()
    {
        add_options_page(
            __('Squoosh Editor Settings', 'squoosh-media-editor'),
            __('Squoosh Editor', 'squoosh-media-editor'),
            'manage_options',
            'squoosh-settings',
            array(Squoosh_Settings::get_instance(), 'render_settings_page')
        );

        // Add bulk convert submenu under Media
        add_media_page(
            __('Bulk Convert with Squoosh', 'squoosh-media-editor'),
            __('Squoosh Bulk Convert', 'squoosh-media-editor'),
            'upload_files',
            'squoosh-bulk-convert',
            array(Squoosh_Media_Handler::get_instance(), 'render_bulk_convert_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        Squoosh_Settings::get_instance()->register();
    }
}

// Initialize plugin
add_action('plugins_loaded', array('Squoosh_Media_Editor', 'get_instance'));
