<?php
/**
 * Squoosh Settings Class
 * 
 * Handles plugin settings page and options
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('update_option_squoosh_edit_htaccess', function () {
    Squoosh_Settings::get_instance()->maybe_update_htaccess(squoosh_get_requested_values());
});

add_action('update_option_squoosh_max_execution_time', 'squoosh_update_htaccess_on_settings_change');
add_action('update_option_squoosh_memory_limit', 'squoosh_update_htaccess_on_settings_change');
add_action('update_option_squoosh_max_upload_size', 'squoosh_update_htaccess_on_settings_change');
add_action('update_option_squoosh_post_max_size', 'squoosh_update_htaccess_on_settings_change');

function squoosh_update_htaccess_on_settings_change()
{
    Squoosh_Settings::get_instance()->maybe_update_htaccess(squoosh_get_requested_values());
}

function squoosh_get_requested_values()
{
    return array(
        'upload' => get_option('squoosh_max_upload_size', '64M'),
        'post' => get_option('squoosh_post_max_size', '64M'),
        'memory' => get_option('squoosh_memory_limit', '256M'),
        'time' => get_option('squoosh_max_execution_time', 0),
    );
}


class Squoosh_Settings
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    public function maybe_update_htaccess($requested)
    {
        $htaccess_file = ABSPATH . '.htaccess';
        $backup_file = ABSPATH . '.htaccess.squoosh-backup';

        $enabled = get_option('squoosh_edit_htaccess', 'no');

        // If disabled → revert changes
        if ($enabled !== 'yes') {
            $this->remove_htaccess_block($htaccess_file);
            return 'disabled';
        }

        // Check writable
        if (!is_writable($htaccess_file)) {
            // Log for debugging, return status for UI
            error_log('Squoosh Plugin: .htaccess not writable. Override could not be applied.');
            return 'not_writable';
        }

        // Backup
        if (file_exists($htaccess_file)) {
            if (!@copy($htaccess_file, $backup_file)) {
                error_log('Squoosh Plugin: Failed to create .htaccess backup.');
            }
        }

        // Remove old block if exists
        $this->remove_htaccess_block($htaccess_file);

        // Build block
        $block = "\n# BEGIN Squoosh Plugin Settings\n";
        $block .= "php_value upload_max_filesize {$requested['upload']}\n";
        $block .= "php_value post_max_size {$requested['post']}\n";
        $block .= "php_value memory_limit {$requested['memory']}\n";
        $block .= "php_value max_execution_time {$requested['time']}\n";
        $block .= "php_value max_input_time {$requested['time']}\n";
        $block .= "# END Squoosh Plugin Settings\n";

        // Append block
        file_put_contents($htaccess_file, $block, FILE_APPEND);

        return 'updated';
    }


    private function remove_htaccess_block($htaccess_file)
    {
        if (!file_exists($htaccess_file) || !is_writable($htaccess_file)) {
            return;
        }

        $contents = file_get_contents($htaccess_file);
        $pattern = '/# BEGIN Squoosh Plugin Settings[\s\S]*?# END Squoosh Plugin Settings/';
        $new_contents = preg_replace($pattern, '', $contents);

        if ($new_contents !== null) {
            file_put_contents($htaccess_file, $new_contents);
        }
    }

    /**
     * Register settings
     */
    public function register()
    {
        // Register settings
        register_setting('squoosh_settings', 'squoosh_replace_mode', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_replace_mode'),
            'default' => 'backup'
        ));

        register_setting('squoosh_settings', 'squoosh_auto_replace_content', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_yes_no'),
            'default' => 'yes'
        ));


        register_setting('squoosh_settings', 'squoosh_default_format', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_format'),
            'default' => 'same'
        ));

        register_setting('squoosh_settings', 'squoosh_default_quality', array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_quality'),
            'default' => 75
        ));

        register_setting('squoosh_settings', 'squoosh_max_execution_time', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0 // 0 = unlimited
        ));

        register_setting('squoosh_settings', 'squoosh_memory_limit', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_memory_limit'),
            'default' => '256M'
        ));

        register_setting('squoosh_settings', 'squoosh_max_upload_size', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_upload_size'),
            'default' => '64M'
        ));

        register_setting('squoosh_settings', 'squoosh_post_max_size', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_upload_size'),
            'default' => '64M'
        ));

        register_setting('squoosh_settings', 'squoosh_edit_htaccess', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_yes_no'),
            'default' => 'no'
        ));

        add_settings_field(
            'squoosh_edit_htaccess',
            __('Edit .htaccess if needed', 'squoosh-media-editor'),
            array($this, 'render_edit_htaccess_field'),
            'squoosh_settings',
            'squoosh_general_section'
        );

        add_settings_field(
            'squoosh_post_max_size',
            __('Post Max Size', 'squoosh-media-editor'),
            array($this, 'render_post_max_size_field'),
            'squoosh_settings',
            'squoosh_general_section'
        );

        add_settings_field(
            'squoosh_max_execution_time',
            __('Max Execution Time', 'squoosh-media-editor'),
            array($this, 'render_execution_time_field'),
            'squoosh_settings',
            'squoosh_general_section'
        );

        add_settings_field(
            'squoosh_memory_limit',
            __('Memory Limit', 'squoosh-media-editor'),
            array($this, 'render_memory_limit_field'),
            'squoosh_settings',
            'squoosh_general_section'
        );

        add_settings_field(
            'squoosh_max_upload_size',
            __('Max Upload Size', 'squoosh-media-editor'),
            array($this, 'render_upload_size_field'),
            'squoosh_settings',
            'squoosh_general_section'
        );

        // Add settings section
        add_settings_section(
            'squoosh_general_section',
            __('General Settings', 'squoosh-media-editor'),
            array($this, 'render_section_description'),
            'squoosh_settings'
        );

        // Add settings fields
        add_settings_field(
            'squoosh_replace_mode',
            __('Replace Mode', 'squoosh-media-editor'),
            array($this, 'render_replace_mode_field'),
            'squoosh_settings',
            'squoosh_general_section'
        );

        add_settings_field(
            'squoosh_auto_replace_content',
            __('Auto-replace in Content', 'squoosh-media-editor'),
            array($this, 'render_auto_replace_field'),
            'squoosh_settings',
            'squoosh_general_section'
        );

        add_settings_field(
            'squoosh_default_format',
            __('Default Output Format', 'squoosh-media-editor'),
            array($this, 'render_format_field'),
            'squoosh_settings',
            'squoosh_general_section'
        );

        add_settings_field(
            'squoosh_default_quality',
            __('Default Quality', 'squoosh-media-editor'),
            array($this, 'render_quality_field'),
            'squoosh_settings',
            'squoosh_general_section'
        );
    }

    /**
     * Sanitize replace mode
     */
    public function sanitize_replace_mode($value)
    {
        return in_array($value, array('backup', 'delete')) ? $value : 'backup';
    }

    public function sanitize_memory_limit($value)
    {
        // Allow formats like "128M", "512M", "1G"
        $value = strtoupper(trim($value));
        return preg_match('/^\d+(M|G)$/', $value) ? $value : '256M';
    }

    public function sanitize_upload_size($value)
    {
        $value = strtoupper(trim($value));
        // Allow formats like "64M", "128M", "1G"
        return preg_match('/^\d+(M|G)$/', $value) ? $value : '64M';
    }


    /**
     * Sanitize yes/no
     */
    public function sanitize_yes_no($value)
    {
        return $value === 'yes' ? 'yes' : 'no';
    }

    /**
     * Sanitize format
     */
    public function sanitize_format($value)
    {
        $allowed = array('same', 'webp', 'avif', 'jpeg', 'png', 'jxl');
        return in_array($value, $allowed) ? $value : 'same';
    }

    /**
     * Sanitize quality
     */
    public function sanitize_quality($value)
    {
        $value = intval($value);
        return max(1, min(100, $value));
    }

    public function render_edit_htaccess_field()
    {
        $value = get_option('squoosh_edit_htaccess', 'no');
        ?>
        <label>
            <input type="checkbox" name="squoosh_edit_htaccess" value="yes" <?php checked($value, 'yes'); ?>>
            <?php esc_html_e('Edit .htaccess file for options not reflected via plugin settings', 'squoosh-media-editor'); ?>
        </label>
        <p class="description">
            <?php echo wp_kses_post(__('If enabled, the plugin will carefully update .htaccess when PHP ignores ini_set().<br><i>Clear your browser cache or force reload Ctrl+Shift+R after this operation (Recommended).</i>', 'squoosh-media-editor')); ?>
        </p>
        <?php
    }

    public function render_post_max_size_field()
    {
        $value = get_option('squoosh_post_max_size', '64M');
        ?>
        <input type="text" name="squoosh_post_max_size" value="<?php echo esc_attr($value); ?>">
        <p class="description">
            <?php esc_html_e('Set PHP post_max_size (e.g., 64M, 128M, 1G). Must be ≥ upload_max_filesize.', 'squoosh-media-editor'); ?>
        </p>
        <?php
    }

    public function render_execution_time_field()
    {
        $value = get_option('squoosh_max_execution_time', 0);
        ?>
        <input type="number" name="squoosh_max_execution_time" value="<?php echo esc_attr($value); ?>" min="0" step="30">
        <p class="description">
            <?php esc_html_e('Set maximum execution time in seconds (0 = unlimited).', 'squoosh-media-editor'); ?>
        </p>
        <?php
    }

    public function render_memory_limit_field()
    {
        $value = get_option('squoosh_memory_limit', '256M');
        ?>
        <input type="text" name="squoosh_memory_limit" value="<?php echo esc_attr($value); ?>">
        <p class="description">
            <?php esc_html_e('Set PHP memory limit (e.g., 256M, 512M, 1G).', 'squoosh-media-editor'); ?>
        </p>
        <?php
    }

    public function render_upload_size_field()
    {
        $value = get_option('squoosh_max_upload_size', '64M');
        ?>
        <input type="text" name="squoosh_max_upload_size" value="<?php echo esc_attr($value); ?>">
        <p class="description">
            <?php esc_html_e('Set maximum upload size (e.g., 64M, 128M, 1G). Must be ≤ post_max_size.', 'squoosh-media-editor'); ?>
        </p>
        <?php
    }



    /**
     * Render section description
     */
    public function render_section_description()
    {
        echo '<p>' . esc_html__('Configure how Squoosh handles image editing and bulk conversions.', 'squoosh-media-editor') . '</p>';
    }

    /**
     * Render replace mode field
     */
    public function render_replace_mode_field()
    {
        $value = get_option('squoosh_replace_mode', 'backup');
        ?>
        <fieldset>
            <label>
                <input type="radio" name="squoosh_replace_mode" value="backup" <?php checked($value, 'backup'); ?>>
                <?php esc_html_e('Backup & Replace', 'squoosh-media-editor'); ?>
                <p class="description">
                    <?php esc_html_e('Keep original file in media library as [{title} - Backup {date:month:year} {time}] folder before replacing', 'squoosh-media-editor'); ?>
                </p>
            </label>
            <br><br>
            <label>
                <input type="radio" name="squoosh_replace_mode" value="delete" <?php checked($value, 'delete'); ?>
                    id="squoosh-delete-mode">
                <?php esc_html_e('Delete & Replace', 'squoosh-media-editor'); ?>
            </label>
            <div id="squoosh-delete-warning" class="squoosh-warning"
                style="<?php echo $value === 'delete' ? '' : 'display:none;'; ?>">
                <span class="dashicons dashicons-warning"></span>
                <strong>
                    <?php esc_html_e('WARNING:', 'squoosh-media-editor'); ?>
                </strong>
                <?php esc_html_e('This will permanently delete the original file when saving. This action cannot be undone!', 'squoosh-media-editor'); ?>
            </div>
        </fieldset>
        <script>
            document.getElementById('squoosh-delete-mode').addEventListener('change', function () {
                document.getElementById('squoosh-delete-warning').style.display = this.checked ? 'block' : 'none';
            });
            document.querySelector('input[value="backup"]').addEventListener('change', function () {
                document.getElementById('squoosh-delete-warning').style.display = 'none';
            });
        </script>
        <?php
    }

    /**
     * Render auto-replace field
     */
    public function render_auto_replace_field()
    {
        $value = get_option('squoosh_auto_replace_content', 'yes');
        ?>
        <label>
            <input type="checkbox" name="squoosh_auto_replace_content" value="yes" <?php checked($value, 'yes'); ?>>
            <?php esc_html_e('Automatically update images in posts, pages, and WooCommerce products', 'squoosh-media-editor'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, featured images and gallery images will be updated to use the new file.', 'squoosh-media-editor'); ?>
        </p>
        <?php
    }

    /**
     * Render format field
     */
    public function render_format_field()
    {
        $value = get_option('squoosh_default_format', 'same');
        ?>
        <select name="squoosh_default_format">
            <option value="same" <?php selected($value, 'same'); ?>>
                <?php esc_html_e('Same as original', 'squoosh-media-editor'); ?>
            </option>
            <option value="webp" <?php selected($value, 'webp'); ?>>WebP</option>
            <option value="avif" <?php selected($value, 'avif'); ?>>AVIF</option>
            <option value="jpeg" <?php selected($value, 'jpeg'); ?>>JPEG (MozJPEG)</option>
            <option value="png" <?php selected($value, 'png'); ?>>PNG (OxiPNG)</option>
            <option value="jxl" <?php selected($value, 'jxl'); ?>>JPEG XL</option>
        </select>
        <p class="description">
            <?php esc_html_e('Default format for bulk conversions. Individual edits can use any format.', 'squoosh-media-editor'); ?>
        </p>
        <?php
    }

    /**
     * Render quality field
     */
    public function render_quality_field()
    {
        $value = get_option('squoosh_default_quality', 75);
        ?>
        <input type="range" name="squoosh_default_quality" min="1" max="100" value="<?php echo esc_attr($value); ?>"
            id="squoosh-quality-range">
        <span id="squoosh-quality-value">
            <?php echo esc_html($value); ?>
        </span>%
        <p class="description">
            <?php esc_html_e('Default quality for lossy formats (WebP, AVIF, JPEG, JPEG XL). Higher = better quality, larger file.', 'squoosh-media-editor'); ?>
        </p>
        <script>
            document.getElementById('squoosh-quality-range').addEventListener('input', function () {
                document.getElementById('squoosh-quality-value').textContent = this.value;
            });
        </script>
        <?php
    }


    public function squoosh_get_system_status()
    {
        return array(
            'memory_limit' => ini_get('memory_limit'),
            'execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'zip_extension' => class_exists('ZipArchive') ? 'Enabled' : 'Disabled',
            'requested_memory' => get_option('squoosh_memory_limit', '256M'),
            'requested_time' => get_option('squoosh_max_execution_time', 0),
            'requested_upload' => get_option('squoosh_max_upload_size', '64M'),
            'requested_postsize' => get_option('squoosh_post_max_size', '64M'),
            'htaccess_writable' => is_writable(ABSPATH . '.htaccess') ? 'Yes' : 'No',
        );
    }

    private function check_upload_post_mismatch($upload, $post)
    {
        // Convert shorthand (e.g. 600M, 1G) to bytes for comparison
        $toBytes = function ($val) {
            $val = trim($val);
            $last = strtolower(substr($val, -1));
            $num = (int) $val;
            switch ($last) {
                case 'g':
                    $num *= 1024 * 1024 * 1024;
                    break;
                case 'm':
                    $num *= 1024 * 1024;
                    break;
                case 'k':
                    $num *= 1024;
                    break;
            }
            return $num;
        };

        return $toBytes($post) < $toBytes($upload);
    }

    public function squoosh_status_line($label, $actual, $requested = null)
    {
        $match = ($requested === null || $actual == $requested);
        $color = $match ? 'limegreen' : 'orange';
        $output = $label . ': ' . esc_html($actual);
        if ($requested !== null) {
            $output .= ' (Requested: ' . esc_html($requested) . ')';
        }
        $output .= ' <span style="color:' . $color . ';">' . ($match ? '✔' : '⚠') . '</span>';
        return $output;
    }

    private function htaccess_block_exists($htaccess_file)
    {
        if (!file_exists($htaccess_file))
            return false;
        $contents = file_get_contents($htaccess_file);
        return strpos($contents, '# BEGIN Squoosh Plugin Settings') !== false;
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show notices if settings were saved
        if (isset($_GET['settings-updated'])) {
            $result = $this->maybe_update_htaccess(squoosh_get_requested_values());

            if ($result === 'not_writable') {
                add_settings_error(
                    'squoosh_messages',
                    'squoosh_htaccess_error',
                    __('.htaccess is not writable. Override could not be applied.', 'squoosh-media-editor'),
                    'error'
                );
            } elseif ($result === 'updated') {
                add_settings_error(
                    'squoosh_messages',
                    'squoosh_htaccess_updated',
                    __('.htaccess updated with new limits.', 'squoosh-media-editor'),
                    'updated'
                );
            } elseif ($result === 'disabled') {
                add_settings_error(
                    'squoosh_messages',
                    'squoosh_htaccess_disabled',
                    __('.htaccess override disabled. Block removed.', 'squoosh-media-editor'),
                    'updated'
                );
            } else {
                add_settings_error(
                    'squoosh_messages',
                    'squoosh_message',
                    __('Settings saved.', 'squoosh-media-editor'),
                    'updated'
                );
            }
        }

        // Run status check
        $status = $this->squoosh_get_system_status();
        $status['htaccess_override'] = $this->htaccess_block_exists(ABSPATH . '.htaccess');
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>

            <div class="squoosh-settings-header">
                <img src="<?php echo esc_url(SQUOOSH_PLUGIN_URL . 'c/icon-large-maskable-c2078ced.png'); ?>" alt="Squoosh"
                    class="squoosh-logo">
                <p>
                    <?php esc_html_e('Configure how the Squoosh image editor integrates with your WordPress Media Library.', 'squoosh-media-editor'); ?>
                </p>
            </div>

            <?php settings_errors('squoosh_messages'); ?>

            <div class="nav-tab-wrapper s-tabs">
                <a href="#squoosh-general" class="nav-tab nav-tab-active">General</a>
                <a href="#squoosh-import-export" class="nav-tab">Import/Export</a>
            </div>

            <div id="squoosh-general-tab" class="s-tab-content">
                <form action="options.php" method="post">
                    <?php
                    settings_fields('squoosh_settings');
                    do_settings_sections('squoosh_settings');
                    submit_button(__('Save Settings', 'squoosh-media-editor'));
                    ?>
                </form>
            </div>

            <div id="squoosh-import-export-tab" class="s-tab-content" style="display:none;">
                <div class="card" style="max-width: 100%; margin-top: 20px;">
                    <h2><?php esc_html_e('Export Media Library', 'squoosh-media-editor'); ?></h2>
                    <p><?php esc_html_e('Download a complete backup of your media library images and metadata in a single ZIP file.', 'squoosh-media-editor'); ?>
                    </p>

                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                        <input type="hidden" name="action" value="squoosh_export_library">
                        <?php wp_nonce_field('squoosh_export_nonce', 'nonce'); ?>
                        <button type="submit" class="button button-primary button-hero">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            <?php esc_html_e('Export Media Library', 'squoosh-media-editor'); ?>
                        </button>
                    </form>
                </div>

                <div class="card" style="max-width: 100%; margin-top: 20px;">
                    <h2><?php esc_html_e('Import Media Library', 'squoosh-media-editor'); ?></h2>
                    <p><?php esc_html_e('Restore your media library from a Squoosh backup ZIP file.', 'squoosh-media-editor'); ?>
                    </p>

                    <div class="import-options">
                        <p>
                            <label>
                                <input type="radio" name="import_mode" value="add" checked>
                                <strong><?php esc_html_e('Add to Library', 'squoosh-media-editor'); ?></strong>
                                <span class="description">-
                                    <?php esc_html_e('Adds images to existing library (safe).', 'squoosh-media-editor'); ?></span>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="radio" name="import_mode" value="replace">
                                <strong
                                    style="color: #d63638;"><?php esc_html_e('Replace Library', 'squoosh-media-editor'); ?></strong>
                                <span class="description" style="color: #d63638;">-
                                    <?php esc_html_e('DELETES all existing images first (destructive).', 'squoosh-media-editor'); ?></span>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" id="import_auto_replace" checked>
                                <?php esc_html_e('Auto-replace references in posts/products', 'squoosh-media-editor'); ?>
                            </label>
                        </p>
                    </div>

                    <div class="import-upload" style="margin-top: 20px;">
                        <input type="file" id="squoosh-import-file" accept=".zip">
                        <button type="button" id="squoosh-start-import" class="button button-primary">
                            <?php esc_html_e('Start Import', 'squoosh-media-editor'); ?>
                        </button>
                    </div>

                    <div id="import-console" class="code-block"
                        style="display:none; margin-top: 20px; background: #1e1e1e; color: #fff; padding: 15px; border-radius: 4px; font-family: monospace;">
                        <div id="import-status">Initializing...</div>
                        <div id="resource-stats"
                            style="margin-top: 10px; border-top: 1px solid #444; padding-top: 10px; font-size: 0.9em; opacity: 0.8;">
                        </div>
                    </div>
                </div>
                <div class="card" style="max-width: 100%; margin-top: 20px;">
                    <h2><?php esc_html_e('System Status', 'squoosh-media-editor'); ?></h2>
                    <p><?php esc_html_e('Check your system status here. If settings show error, Override the .htaccess file in General tab for the changes to reflect.', 'squoosh-media-editor'); ?>
                    </p>
                    <div id="system-status" style="margin-top: 20px;">
                        <div id="system-status-console" class="code-block"
                            style="background: #1e1e1e; color: #fff; padding: 15px; border-radius: 4px; font-family: monospace;">
                            <div id="system-status-output">
                                <?php echo $this->squoosh_status_line('Memory Limit', $status['memory_limit'], $status['requested_memory']); ?><br>
                                Htaccess Override: <?php echo $status['htaccess_override'] ? 'Active' : 'Not Active'; ?><br>
                                Htaccess Writable: <?php echo esc_html($status['htaccess_writable']); ?><br>
                                <?php echo $this->squoosh_status_line('Max Execution Time', $status['execution_time'], $status['requested_time']); ?><br>
                                <?php echo $this->squoosh_status_line('Upload Max Filesize', $status['upload_max_filesize'], $status['requested_upload']); ?><br>
                                <?php echo $this->squoosh_status_line('Post Max Size', $status['post_max_size'], $status['requested_postsize']); ?><br>
                                <?php if ($this->check_upload_post_mismatch($status['upload_max_filesize'], $status['post_max_size'])): ?>
                                    <span style="color:red;">
                                        Warning: Post Max Size is lower than Upload Max Filesize. Uploads may fail.
                                    </span><br>
                                <?php endif; ?>
                                Zip Extension:
                                <?php echo esc_html($status['zip_extension']); ?><br>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                jQuery(document).ready(function ($) {
                    // Tab switching
                    $('.nav-tab-wrapper a').click(function (e) {
                        e.preventDefault();
                        $('.nav-tab').removeClass('nav-tab-active');
                        $(this).addClass('nav-tab-active');
                        $('.s-tab-content').hide();
                        $($(this).attr('href') + '-tab').show();
                    });

                    // Import Logic
                    $('#squoosh-start-import').click(function () {
                        var fileInput = document.getElementById('squoosh-import-file');
                        var file = fileInput.files[0];

                        if (!file) {
                            alert('Please select a ZIP file.');
                            return;
                        }

                        // Reset UI
                        $('#import-console').show();
                        $('#import-status').html('<span style="color:#ffa500">Checking system resources...</span>');
                        $('#resource-stats').html('');

                        var data = new FormData();
                        data.append('action', 'squoosh_check_resources');
                        data.append('nonce', '<?php echo wp_create_nonce('squoosh_nonce'); ?>');
                        data.append('file_size', file.size);

                        // 1. Resource Check
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: data,
                            processData: false,
                            contentType: false,
                            success: function (response) {
                                if (response.success) {
                                    var r = response.data;
                                    var stats = 'File: ' + r.file_size_formatted + '<br>' +
                                        'Required Memory: ' + r.required.memory_formatted + ' (Limit: ' + r.current.memory_formatted + ')<br>' +
                                        'Required Time: ' + r.required.time_formatted + ' (Limit: ' + r.current.time_formatted + ')';

                                    $('#resource-stats').html(stats);
                                    $('#import-status').html('<span style="color:#46b450">Resources OK. Starting Upload...</span>');

                                    // 2. Start Session (Upload & Unzip)
                                    startImportSession(file);
                                } else {
                                    var r = response.data.data;
                                    $('#resource-stats').html('ERROR: ' + response.data.message);
                                    $('#import-status').html('<span style="color:#d63638">Resource Check Failed.</span>');
                                    if (r) {
                                        var stats = '<br>Required Memory: ' + r.required.memory_formatted + ' (Limit: ' + r.current.memory_formatted + ')<br>' +
                                            'Required Time: ' + r.required.time_formatted + ' (Limit: ' + r.current.time_formatted + ')';
                                        $('#resource-stats').append(stats);
                                    }
                                }
                            }
                        });
                    });

                    function startImportSession(file) {
                        $('#import-status').html('<span style="color:#ffa500">Uploading and Unzipping (this may take a while)...</span>');

                        var data = new FormData();
                        data.append('action', 'squoosh_import_session');
                        data.append('nonce', '<?php echo wp_create_nonce('squoosh_nonce'); ?>');
                        data.append('import_file', file);

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: data,
                            processData: false,
                            contentType: false,
                            success: function (response) {
                                if (response.success) {
                                    var sessionId = response.data.session_id;
                                    var totalFiles = response.data.total_files;
                                    $('#import-status').html('<span style="color:#46b450">Unzip complete. Found ' + totalFiles + ' files. Starting processing...</span>');

                                    // Add Progress Bar
                                    $('#resource-stats').append('<div id="squoosh-progress-wrapper" style="width:100%; background:#444; height:20px; margin-top:10px; border-radius:10px; overflow:hidden;"><div id="squoosh-progress-bar" style="width:0%; height:100%; background:#46b450; transition: width 0.3s;"></div></div><div id="squoosh-progress-text">0% (0/' + totalFiles + ')</div>');

                                    // 3. Start Batch Loop
                                    processBatch(sessionId, 0, totalFiles, {}, 0);
                                } else {
                                    $('#import-status').html('<span style="color:#d63638">Unzip Failed: ' + response.data.message + '</span>');
                                }
                            },
                            error: function (xhr, status, error) {
                                handleAjaxError(xhr, error);
                            }
                        });
                    }

                    // Recursive Batch Function
                    function processBatch(sessionId, offset, total, accumulatedIdMap, processedCount) {
                        var batchSize = 20; // 20 files per request

                        $.post(ajaxurl, {
                            action: 'squoosh_import_batch',
                            nonce: '<?php echo wp_create_nonce('squoosh_nonce'); ?>',
                            session_id: sessionId,
                            offset: offset,
                            limit: batchSize,
                            mode: $('input[name="import_mode"]:checked').val()
                        }, function (response) {
                            if (response.success) {
                                var batchProcessed = response.data.processed;
                                processedCount += batchProcessed;

                                // Merge ID maps
                                Object.assign(accumulatedIdMap, response.data.id_map); // ES6 merge

                                // Update Progress
                                var percent = Math.min(100, Math.round(((offset + batchSize) / total) * 100));
                                if (percent > 100) percent = 100;
                                updateProgress(percent, processedCount, total);

                                // Next Batch or Finalize
                                if (offset + batchSize < total) {
                                    processBatch(sessionId, offset + batchSize, total, accumulatedIdMap, processedCount);
                                } else {
                                    finalizeImport(sessionId, accumulatedIdMap);
                                }
                            } else {
                                $('#import-status').html('<span style="color:#d63638">Batch Error: ' + response.data.message + '</span>');
                            }
                        }).fail(function (xhr, status, error) {
                            handleAjaxError(xhr, error);
                            // Retry logic could go here, but for now we stop
                        });
                    }

                    function finalizeImport(sessionId, idMap) {
                        $('#import-status').html('<span style="color:#ffa500">Files imported. Fixing database references...</span>');

                        $.post(ajaxurl, {
                            action: 'squoosh_import_finalize',
                            nonce: '<?php echo wp_create_nonce('squoosh_nonce'); ?>',
                            session_id: sessionId,
                            auto_replace: $('#import_auto_replace').is(':checked'),
                            id_map: JSON.stringify(idMap) // Pass full map (careful with size, but for simple map it's usually ok)
                        }, function (response) {
                            if (response.success) {
                                $('#import-status').html('<span style="color:#46b450"><strong>' + response.data.message + '</strong></span>');
                                $('#resource-stats').append('<br><strong>Replacement Stats:</strong> ' + response.data.replacements + ' database references updated.');
                                updateProgress(100, 'Done', 'Done');
                            } else {
                                $('#import-status').html('<span style="color:#d63638">Finalize Error: ' + response.data.message + '</span>');
                            }
                        });
                    }

                    function updateProgress(percent, current, total) {
                        $('#squoosh-progress-bar').css('width', percent + '%');
                        $('#squoosh-progress-text').text(percent + '% (' + current + '/' + total + ')');
                    }

                    function handleAjaxError(xhr, error) {
                        var msg = 'Upload/Process failed: ' + error + '.';
                        if (xhr.status === 400 || xhr.status === 413) {
                            msg += ' <br><strong>Likely cause:</strong> The file is larger than your server\'s <code>upload_max_filesize</code> or <code>post_max_size</code>. <br>Please increase these values in your php.ini.';
                        } else if (xhr.status === 0) {
                            msg += ' <br><strong>Possible cause:</strong> The server terminated the connection. Check server logs.';
                        }
                        $('#import-status').html('<span style="color:#d63638">' + msg + '</span>');
                    }
                });
            </script>

            <div class="squoosh-info-box">
                <h3>
                    <?php esc_html_e('How to Use', 'squoosh-media-editor'); ?>
                </h3>
                <ol>
                    <li>
                        <?php esc_html_e('Go to Media → Library', 'squoosh-media-editor'); ?>
                    </li>
                    <li>
                        <?php esc_html_e('Hover over an image and click "Edit with Squoosh"', 'squoosh-media-editor'); ?>
                    </li>
                    <li>
                        <?php esc_html_e('Edit your image using the Squoosh editor', 'squoosh-media-editor'); ?>
                    </li>
                    <li>
                        <?php esc_html_e('Click "Save to WordPress" to save your changes', 'squoosh-media-editor'); ?>
                    </li>
                </ol>
                <p>
                    <strong>
                        <?php esc_html_e('Bulk Conversion:', 'squoosh-media-editor'); ?>
                    </strong>
                    <?php esc_html_e('Select multiple images in the Media Library and choose "Convert with Squoosh" from the Bulk Actions dropdown.', 'squoosh-media-editor'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}
