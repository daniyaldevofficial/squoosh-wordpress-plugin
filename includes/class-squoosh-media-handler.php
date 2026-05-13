<?php
/**
 * Squoosh Media Handler Class
 * 
 * Handles media library integration, row actions, and bulk conversions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Squoosh_Media_Handler
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Add row actions to media list
        add_filter('media_row_actions', array($this, 'add_row_actions'), 10, 2);

        // Add action to attachment edit screen
        add_action('attachment_submitbox_misc_actions', array($this, 'add_attachment_action'), 99);

        // Add bulk actions
        add_filter('bulk_actions-upload', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 10, 3);

        // Add admin notices
        add_action('admin_notices', array($this, 'bulk_action_notices'));

        // Add modal HTML to footer
        add_action('admin_footer', array($this, 'render_editor_modal'));
    }

    /**
     * Add row actions to media library
     */
    public function add_row_actions($actions, $post)
    {
        if (!$this->is_editable_image($post)) {
            return $actions;
        }

        $actions['squoosh_edit'] = sprintf(
            '<a href="#" class="squoosh-edit-link" data-attachment-id="%d" data-image-url="%s" title="%s">%s</a>',
            $post->ID,
            esc_url(wp_get_attachment_url($post->ID)),
            esc_attr__('Edit with Squoosh image optimizer', 'squoosh-media-editor'),
            esc_html__('Edit with Squoosh', 'squoosh-media-editor')
        );

        return $actions;
    }

    /**
     * Add action to attachment edit screen
     */
    public function add_attachment_action()
    {
        global $post;

        if (!$this->is_editable_image($post)) {
            return;
        }
        ?>
        <div class="misc-pub-section misc-pub-squoosh">
            <button type="button" class="button squoosh-edit-btn" data-attachment-id="<?php echo esc_attr($post->ID); ?>"
                data-image-url="<?php echo esc_url(wp_get_attachment_url($post->ID)); ?>">
                <span class="dashicons dashicons-image-filter" style="vertical-align: middle;"></span>
                <?php esc_html_e('Edit with Squoosh', 'squoosh-media-editor'); ?>
            </button>
        </div>
        <?php
    }

    /**
     * Check if attachment is an editable image
     */
    private function is_editable_image($post)
    {
        if (!$post || $post->post_type !== 'attachment') {
            return false;
        }

        $mime_type = get_post_mime_type($post);
        $editable_types = array(
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
            'image/bmp',
            'image/tiff'
        );

        return in_array($mime_type, $editable_types);
    }

    /**
     * Add bulk actions
     */
    public function add_bulk_actions($actions)
    {
        $actions['squoosh_convert'] = __('Convert with Squoosh', 'squoosh-media-editor');
        return $actions;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids)
    {
        if ($doaction !== 'squoosh_convert') {
            return $redirect_to;
        }

        // Filter to only images
        $image_ids = array();
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($this->is_editable_image($post)) {
                $image_ids[] = $post_id;
            }
        }

        if (empty($image_ids)) {
            $redirect_to = add_query_arg('squoosh_converted', 0, $redirect_to);
            $redirect_to = add_query_arg('squoosh_error', 'no_images', $redirect_to);
            return $redirect_to;
        }

        // Store IDs in transient for processing
        set_transient('squoosh_bulk_convert_' . get_current_user_id(), $image_ids, HOUR_IN_SECONDS);

        // Redirect to bulk convert page
        return admin_url('upload.php?page=squoosh-bulk-convert&batch=' . get_current_user_id());
    }

    /**
     * Display bulk action notices
     */
    public function bulk_action_notices()
    {
        if (!isset($_REQUEST['squoosh_converted'])) {
            return;
        }

        $count = intval($_REQUEST['squoosh_converted']);
        $error = isset($_REQUEST['squoosh_error']) ? sanitize_text_field($_REQUEST['squoosh_error']) : '';

        if ($error === 'no_images') {
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                esc_html__('No valid images were selected for conversion.', 'squoosh-media-editor')
            );
        } elseif ($count > 0) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                sprintf(
                    /* translators: %d: number of images converted */
                    _n('%d image converted successfully.', '%d images converted successfully.', $count, 'squoosh-media-editor'),
                    $count
                )
            );
        }
    }

    /**
     * Render bulk convert page
     */
    public function render_bulk_convert_page()
    {
        $batch_id = isset($_GET['batch']) ? intval($_GET['batch']) : 0;
        $image_ids = array();

        if ($batch_id) {
            $image_ids = get_transient('squoosh_bulk_convert_' . $batch_id);
            if (!$image_ids) {
                $image_ids = array();
            }
        }
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('Bulk Convert with Squoosh', 'squoosh-media-editor'); ?>
            </h1>

            <?php if (empty($image_ids)): ?>
                <div class="notice notice-info">
                    <p>
                        <?php esc_html_e('Select images from the Media Library and choose "Convert with Squoosh" from the Bulk Actions dropdown to begin.', 'squoosh-media-editor'); ?>
                    </p>
                </div>
                <p>
                    <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="button button-primary">
                        <?php esc_html_e('Go to Media Library', 'squoosh-media-editor'); ?>
                    </a>
                </p>
            <?php else: ?>
                <div class="squoosh-bulk-convert-container">
                    <div class="squoosh-bulk-settings">
                        <h2>
                            <?php esc_html_e('Conversion Settings', 'squoosh-media-editor'); ?>
                        </h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Output Format', 'squoosh-media-editor'); ?>
                                </th>
                                <td>
                                    <select id="squoosh-bulk-format">
                                        <option value="webp">WebP</option>
                                        <option value="avif">AVIF</option>
                                        <option value="jpeg">JPEG (MozJPEG)</option>
                                        <option value="png">PNG (OxiPNG)</option>
                                        <option value="jxl">JPEG XL</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <?php esc_html_e('Quality', 'squoosh-media-editor'); ?>
                                </th>
                                <td>
                                    <input type="range" id="squoosh-bulk-quality" min="1" max="100"
                                        value="<?php echo esc_attr(get_option('squoosh_default_quality', 75)); ?>">
                                    <span id="squoosh-bulk-quality-value">
                                        <?php echo esc_html(get_option('squoosh_default_quality', 75)); ?>
                                    </span>%
                                </td>
                            </tr>
                        </table>

                        <h3>
                            <?php esc_html_e('Images to Convert', 'squoosh-media-editor'); ?> (
                            <?php echo count($image_ids); ?>)
                        </h3>

                        <div class="squoosh-bulk-preview" id="squoosh-bulk-preview">
                            <?php foreach ($image_ids as $id):
                                $url = wp_get_attachment_thumb_url($id);
                                $title = get_the_title($id);
                                ?>
                                <div class="squoosh-bulk-item" data-id="<?php echo esc_attr($id); ?>"
                                    data-url="<?php echo esc_url(wp_get_attachment_url($id)); ?>">
                                    <img src="<?php echo esc_url($url); ?>" alt="<?php echo esc_attr($title); ?>">
                                    <div class="squoosh-bulk-item-status"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="squoosh-bulk-progress" id="squoosh-bulk-progress" style="display: none;">
                            <div class="squoosh-progress-bar">
                                <div class="squoosh-progress-fill" id="squoosh-progress-fill"></div>
                            </div>
                            <p id="squoosh-progress-text"></p>
                        </div>

                        <p class="submit">
                            <button type="button" id="squoosh-start-bulk" class="button button-primary button-hero">
                                <?php esc_html_e('Start Conversion', 'squoosh-media-editor'); ?>
                            </button>
                            <a href="<?php echo esc_url(admin_url('upload.php')); ?>" class="button button-secondary">
                                <?php esc_html_e('Cancel', 'squoosh-media-editor'); ?>
                            </a>
                        </p>
                    </div>

                    <!-- Hidden iframe for Squoosh processing -->
                    <iframe id="squoosh-worker-frame" src="<?php echo esc_url(SQUOOSH_PLUGIN_URL . 'index.html?wp_bulk=1'); ?>"
                        style="display: none;"></iframe>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render editor modal HTML
     */
    public function render_editor_modal()
    {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, array('upload', 'post'))) {
            return;
        }
        ?>
        <div id="squoosh-editor-modal" class="squoosh-modal" style="display: none;">
            <div class="squoosh-modal-overlay"></div>
            <div class="squoosh-modal-container">
                <div class="squoosh-modal-header">
                    <h2>
                        <?php esc_html_e('Squoosh Editor', 'squoosh-media-editor'); ?>
                    </h2>
                    <button type="button" class="squoosh-modal-close"
                        aria-label="<?php esc_attr_e('Close', 'squoosh-media-editor'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="squoosh-modal-content">
                    <iframe id="squoosh-editor-iframe" src="" frameborder="0"></iframe>
                </div>
                <div class="squoosh-modal-footer">
                    <div class="squoosh-modal-status" id="squoosh-modal-status"></div>
                    <button type="button" class="button button-secondary squoosh-modal-cancel">
                        <?php esc_html_e('Cancel', 'squoosh-media-editor'); ?>
                    </button>
                    <button type="button" class="button button-primary squoosh-modal-save" id="squoosh-save-btn" disabled>
                        <?php esc_html_e('Save to WordPress', 'squoosh-media-editor'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Backup original file to Media Library before replacing
     * Creates a new attachment with naming: original_title-backup{DD:MM:YYYY-HHmm}
     */
    public static function backup_original($attachment_id)
    {
        $file_path = get_attached_file($attachment_id);

        if (!$file_path || !file_exists($file_path)) {
            return false;
        }

        // Get original attachment info
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return false;
        }

        $original_title = $attachment->post_title;
        $upload_dir = wp_upload_dir();
        $pathinfo = pathinfo($file_path);
        $extension = $pathinfo['extension'];

        // Generate backup filename with timestamp: original_title-backup{DD:MM:YYYY-HHmm}
        $timestamp = current_time('d:m:Y-Hi'); // Format: 01:01:2026-0305
        $base_backup_name = sanitize_file_name($original_title . '-backup' . $timestamp);

        // Check for duplicates and add suffix if needed
        $backup_filename = $base_backup_name . '.' . $extension;
        $backup_path = $upload_dir['path'] . '/' . $backup_filename;
        $counter = 2;

        // Check if file already exists or if attachment with this name exists
        while (file_exists($backup_path) || self::attachment_name_exists($base_backup_name)) {
            $backup_filename = $base_backup_name . '-' . $counter . '.' . $extension;
            $backup_path = $upload_dir['path'] . '/' . $backup_filename;
            $base_backup_name_with_counter = $base_backup_name . '-' . $counter;

            // Re-check with counter
            if (!file_exists($backup_path) && !self::attachment_name_exists($base_backup_name_with_counter)) {
                $base_backup_name = $base_backup_name_with_counter;
                break;
            }
            $counter++;

            // Safety limit
            if ($counter > 100) {
                $backup_filename = $base_backup_name . '-' . uniqid() . '.' . $extension;
                $backup_path = $upload_dir['path'] . '/' . $backup_filename;
                break;
            }
        }

        // Copy the file to backup location
        if (!copy($file_path, $backup_path)) {
            return false;
        }

        // Create attachment in Media Library
        $backup_title = $original_title . ' - Backup ' . current_time('d/m/Y H:i');

        $attachment_data = array(
            'guid' => $upload_dir['url'] . '/' . $backup_filename,
            'post_mime_type' => get_post_mime_type($attachment_id),
            'post_title' => $backup_title,
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $attachment->post_parent, // Same parent as original
        );

        // Insert the attachment
        $backup_attachment_id = wp_insert_attachment($attachment_data, $backup_path);

        if (is_wp_error($backup_attachment_id)) {
            // Clean up copied file
            @unlink($backup_path);
            return false;
        }

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($backup_attachment_id, $backup_path);
        wp_update_attachment_metadata($backup_attachment_id, $attach_data);

        // Add meta to link backup to original
        update_post_meta($backup_attachment_id, '_squoosh_backup_of', $attachment_id);
        update_post_meta($backup_attachment_id, '_squoosh_backup_date', current_time('mysql'));

        return $backup_attachment_id;
    }

    /**
     * Check if an attachment with this name already exists
     */
    private static function attachment_name_exists($name)
    {
        global $wpdb;

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_title = %s LIMIT 1",
            $name
        ));

        return !empty($result);
    }

    /**
     * Update image references in posts and products
     */
    public static function update_image_references($old_attachment_id, $new_attachment_id = null)
    {
        global $wpdb;

        if (!$new_attachment_id) {
            $new_attachment_id = $old_attachment_id;
        }

        $old_url = wp_get_attachment_url($old_attachment_id);
        $new_url = wp_get_attachment_url($new_attachment_id);

        // Update featured images (post meta _thumbnail_id)
        // This is handled automatically when we update the attachment

        // Update content with new URL (if URL changed)
        if ($old_url !== $new_url) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)",
                $old_url,
                $new_url
            ));
        }

        // Update WooCommerce product gallery
        if (class_exists('WooCommerce')) {
            $gallery_posts = $wpdb->get_results($wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND meta_value LIKE %s",
                '%' . $wpdb->esc_like($old_attachment_id) . '%'
            ));

            foreach ($gallery_posts as $row) {
                $gallery_ids = explode(',', $row->meta_value);
                $updated = false;

                foreach ($gallery_ids as &$id) {
                    if (intval($id) === intval($old_attachment_id) && $new_attachment_id !== $old_attachment_id) {
                        $id = $new_attachment_id;
                        $updated = true;
                    }
                }

                if ($updated) {
                    update_post_meta($row->post_id, '_product_image_gallery', implode(',', $gallery_ids));
                }
            }
        }

        // Clear caches
        wp_cache_flush();
    }
}
