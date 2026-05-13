<?php
/**
 * Squoosh AJAX Handler Class
 * 
 * Handles all AJAX requests for image saving and bulk operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Squoosh_Ajax
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
     * Initialize AJAX hooks
     */
    private function init_hooks()
    {
        // Save edited image
        add_action('wp_ajax_squoosh_save_image', array($this, 'handle_save_image'));

        // Bulk convert single image
        add_action('wp_ajax_squoosh_convert_single', array($this, 'handle_convert_single'));

        // Get image data for editor
        add_action('wp_ajax_squoosh_get_image', array($this, 'handle_get_image'));
    }

    /**
     * Handle save image AJAX request
     */
    public function handle_save_image()
    {
        // Verify nonce
        if (!check_ajax_referer('squoosh_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'squoosh-media-editor')));
        }

        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to edit images.', 'squoosh-media-editor')));
        }

        // Get parameters
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $image_data = isset($_POST['image_data']) ? $_POST['image_data'] : '';
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : '';
        $mime_type = isset($_POST['mime_type']) ? sanitize_mime_type($_POST['mime_type']) : '';

        if (!$attachment_id || !$image_data) {
            wp_send_json_error(array('message' => __('Missing required data.', 'squoosh-media-editor')));
        }

        // Validate attachment exists
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(array('message' => __('Invalid attachment.', 'squoosh-media-editor')));
        }

        // Decode base64 image data
        $image_data = str_replace('data:' . $mime_type . ';base64,', '', $image_data);
        $image_data = str_replace(' ', '+', $image_data);
        $decoded_image = base64_decode($image_data);

        if (!$decoded_image) {
            wp_send_json_error(array('message' => __('Failed to decode image data.', 'squoosh-media-editor')));
        }

        // Get current file info
        $current_file = get_attached_file($attachment_id);
        $current_dir = dirname($current_file);
        $replace_mode = get_option('squoosh_replace_mode', 'backup');
        $auto_replace = get_option('squoosh_auto_replace_content', 'yes') === 'yes';

        // Backup if needed
        $backup_attachment_id = null;
        if ($replace_mode === 'backup') {
            $backup_attachment_id = Squoosh_Media_Handler::backup_original($attachment_id);
            if (!$backup_attachment_id) {
                // Continue anyway, but log warning
                error_log('Squoosh: Failed to create backup in Media Library for attachment ' . $attachment_id);
            } else {
                error_log('Squoosh: Created backup attachment ID ' . $backup_attachment_id . ' for original ' . $attachment_id);
            }
        }

        // Determine new filename
        $new_extension = $this->get_extension_from_mime($mime_type);
        $original_pathinfo = pathinfo($current_file);

        // If format changed, create new filename
        if ($original_pathinfo['extension'] !== $new_extension) {
            $new_filename = $original_pathinfo['filename'] . '.' . $new_extension;
            $new_file = $current_dir . '/' . $new_filename;

            // Handle filename conflicts
            $counter = 1;
            while (file_exists($new_file) && $new_file !== $current_file) {
                $new_filename = $original_pathinfo['filename'] . '-' . $counter . '.' . $new_extension;
                $new_file = $current_dir . '/' . $new_filename;
                $counter++;
            }
        } else {
            $new_file = $current_file;
        }

        // Save new image
        $saved = file_put_contents($new_file, $decoded_image);

        if (!$saved) {
            wp_send_json_error(array('message' => __('Failed to save image file.', 'squoosh-media-editor')));
        }

        // Delete old file if format changed and mode is delete
        if ($new_file !== $current_file) {
            if ($replace_mode === 'delete') {
                @unlink($current_file);
            }

            // Delete old thumbnails
            $this->delete_attachment_thumbnails($attachment_id);
        }

        // Update attachment metadata
        update_attached_file($attachment_id, $new_file);

        // Update post mime type
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_mime_type' => $mime_type
        ));

        // Regenerate thumbnails
        $attach_data = wp_generate_attachment_metadata($attachment_id, $new_file);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        // Update references in content if enabled
        if ($auto_replace) {
            Squoosh_Media_Handler::update_image_references($attachment_id);
        }

        // Get updated URL
        $new_url = wp_get_attachment_url($attachment_id);

        wp_send_json_success(array(
            'message' => __('Image saved successfully!', 'squoosh-media-editor'),
            'url' => $new_url,
            'attachment_id' => $attachment_id,
            'backup_id' => $backup_attachment_id
        ));
    }

    /**
     * Handle single image conversion for bulk operations
     */
    public function handle_convert_single()
    {
        // Verify nonce
        if (!check_ajax_referer('squoosh_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'squoosh-media-editor')));
        }

        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('You do not have permission to convert images.', 'squoosh-media-editor')));
        }

        // Get parameters
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $image_data = isset($_POST['image_data']) ? $_POST['image_data'] : '';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'webp';

        if (!$attachment_id || !$image_data) {
            wp_send_json_error(array('message' => __('Missing required data.', 'squoosh-media-editor')));
        }

        // Get mime type from format
        $mime_types = array(
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'jxl' => 'image/jxl'
        );

        $mime_type = isset($mime_types[$format]) ? $mime_types[$format] : 'image/webp';

        // Process similar to save_image
        $_POST['mime_type'] = $mime_type;
        $_POST['filename'] = ''; // Will be auto-generated

        $this->handle_save_image();
    }

    /**
     * Get image data for editor
     */
    public function handle_get_image()
    {
        // Verify nonce
        if (!check_ajax_referer('squoosh_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'squoosh-media-editor')));
        }

        $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0;

        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID.', 'squoosh-media-editor')));
        }

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(array('message' => __('Invalid attachment.', 'squoosh-media-editor')));
        }

        $url = wp_get_attachment_url($attachment_id);
        $metadata = wp_get_attachment_metadata($attachment_id);

        wp_send_json_success(array(
            'url' => $url,
            'filename' => basename(get_attached_file($attachment_id)),
            'mime_type' => get_post_mime_type($attachment_id),
            'width' => isset($metadata['width']) ? $metadata['width'] : 0,
            'height' => isset($metadata['height']) ? $metadata['height'] : 0,
            'filesize' => filesize(get_attached_file($attachment_id))
        ));
    }

    /**
     * Get file extension from mime type
     */
    private function get_extension_from_mime($mime_type)
    {
        $extensions = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/jxl' => 'jxl',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/qoi' => 'qoi',
            'image/webp2' => 'wp2'
        );

        return isset($extensions[$mime_type]) ? $extensions[$mime_type] : 'jpg';
    }

    /**
     * Delete attachment thumbnails
     */
    private function delete_attachment_thumbnails($attachment_id)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);

        if (!$metadata || !isset($metadata['sizes'])) {
            return;
        }

        $file = get_attached_file($attachment_id);
        $dir = dirname($file);

        foreach ($metadata['sizes'] as $size => $data) {
            $thumb_file = $dir . '/' . $data['file'];
            if (file_exists($thumb_file)) {
                @unlink($thumb_file);
            }
        }
    }
}
