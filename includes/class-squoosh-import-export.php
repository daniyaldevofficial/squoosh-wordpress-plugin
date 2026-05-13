<?php
/**
 * Squoosh Import/Export Handler
 * 
 * Handles exporting media library to ZIP and importing from ZIP
 * with robust resource validation.
 */

if (!defined('ABSPATH')) {
    exit;
}

$max_time = get_option('squoosh_max_execution_time', 0);
$memory = get_option('squoosh_memory_limit', '256M');
$upload = get_option('squoosh_max_upload_size', '64M');
$postsize = get_option('squoosh_post_max_size', '64M');

if ($max_time > 0) {
    @set_time_limit($max_time);
} else {
    @set_time_limit(0);
}

@ini_set('memory_limit', $memory);
@ini_set('upload_max_filesize', $upload);
@ini_set('post_max_size', $postsize);

$requested = array(
    'upload' => $upload,
    'post' => $postsize,
    'memory' => $memory,
    'time' => $max_time,
);

Squoosh_Settings::get_instance()->maybe_update_htaccess($requested);

class Squoosh_Import_Export
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

    private function init_hooks()
    {
        // AJAX handlers for pre-flight checks and processing
        add_action('wp_ajax_squoosh_check_resources', array($this, 'handle_resource_check'));

        // Batched Import Handlers
        add_action('wp_ajax_squoosh_import_session', array($this, 'handle_import_session_start'));
        add_action('wp_ajax_squoosh_import_batch', array($this, 'handle_import_batch'));
        add_action('wp_ajax_squoosh_import_finalize', array($this, 'handle_import_finalize'));

        // Export handler (admin-post)
        add_action('admin_post_squoosh_export_library', array($this, 'handle_export_request'));
    }

    /**
     * Algorithm to calculate required resources
     */
    public function check_system_resources($file_size_bytes)
    {
        // Constants for estimation
        $base_memory_overhead = 64 * 1024 * 1024; // 64MB base overhead
        $memory_multiplier = 0.5; // Estimate needing 0.5x file size in memory for buffering/processing

        $base_time_overhead = 30; // 30 seconds base
        $time_per_mb = 2; // 2 seconds per MB processing time

        // Calculate requirements
        $required_memory = $base_memory_overhead + ($file_size_bytes * $memory_multiplier);
        $file_size_mb = $file_size_bytes / (1024 * 1024);
        $required_time = $base_time_overhead + ($file_size_mb * $time_per_mb);

        // Get system limits
        $memory_limit = $this->parse_memory_limit(ini_get('memory_limit'));
        $time_limit = intval(ini_get('max_execution_time'));

        // If time limit is 0, it's unlimited
        $time_sufficient = ($time_limit === 0) || ($time_limit >= $required_time);

        // If memory limit is -1, it's unlimited
        $memory_sufficient = ($memory_limit === -1) || ($memory_limit >= $required_memory);

        return array(
            'success' => $time_sufficient && $memory_sufficient,
            'file_size_formatted' => size_format($file_size_bytes),
            'required' => array(
                'memory' => $required_memory,
                'memory_formatted' => size_format($required_memory),
                'time' => ceil($required_time),
                'time_formatted' => ceil($required_time) . 's'
            ),
            'current' => array(
                'memory' => $memory_limit,
                'memory_formatted' => $memory_limit === -1 ? 'Unlimited' : size_format($memory_limit),
                'time' => $time_limit,
                'time_formatted' => $time_limit === 0 ? 'Unlimited' : $time_limit . 's'
            )
        );
    }

    /**
     * Handle Resource Check AJAX
     */
    public function handle_resource_check()
    {
        if (!check_ajax_referer('squoosh_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $file_size = isset($_POST['file_size']) ? intval($_POST['file_size']) : 0;

        if ($file_size <= 0) {
            wp_send_json_error(array('message' => 'Invalid file size'));
        }

        $result = $this->check_system_resources($file_size);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(array(
                'message' => __('Insufficient system resources.', 'squoosh-media-editor'),
                'data' => $result
            ));
        }
    }

    /**
     * Handle Export Request
     * Streams a ZIP file to the browser
     */
    public function handle_export_request()
    {
        if (!current_user_can('manage_options') || !check_admin_referer('squoosh_export_nonce', 'nonce')) {
            wp_die('Unauthorized');
        }

        // Increase limits for export if possible
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');


        if (!class_exists('ZipArchive')) {
            error_log('Squoosh Export Error: ZipArchive class not found. PHP zip extension may be disabled.');
            wp_die('Export failed: PHP zip extension is not enabled on this server. Please enable it or contact your host.');
        }

        $upload_dir = wp_upload_dir();
        $zip_filename = 'squoosh-export-' . date('Y-m-d-His') . '.zip';

        // We output headers immediately to stream
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
        header('Pragma: no-cache');

        $zip = new ZipArchive();
        $tmp_file = tempnam(sys_get_temp_dir(), 'squoosh_zip');

        if ($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            error_log('Squoosh Export Error: Cannot create zip file at ' . $tmp_file);
            wp_die('Export failed: Cannot create zip file.');
        }

        // Add Manifest
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'inherit'
        ));

        $manifest = array();

        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);

            if (file_exists($file_path)) {
                $relative_path = 'uploads/' . basename($file_path);
                $zip->addFile($file_path, $relative_path);

                $manifest[] = array(
                    'id' => $attachment->ID,
                    'file' => $relative_path,
                    'guid' => $attachment->guid,
                    'title' => $attachment->post_title,
                    'caption' => $attachment->post_excerpt,
                    'description' => $attachment->post_content,
                    'alt' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                    'mime_type' => $attachment->post_mime_type,
                    'meta' => wp_get_attachment_metadata($attachment->ID),
                    'date' => $attachment->post_date
                );
            }
        }

        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        // Stream file
        readfile($tmp_file);
        unlink($tmp_file);
        exit;
    }


    /**
     * Helper to parse memory limit to bytes
     */
    private function parse_memory_limit($size)
    {
        if ($size === -1)
            return -1;

        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);

        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return round($size);
    }

    /**
     * Start Import Session
     * 1. Extract ZIP to temp dir
     * 2. Parse Manifest
     * 3. Return total count and session ID
     */
    public function handle_import_session_start()
    {
        if (!check_ajax_referer('squoosh_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        // Increase limits
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');

        if (empty($_FILES['import_file'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'squoosh-media-editor')));
        }

        $file = $_FILES['import_file'];
        $zip = new ZipArchive();

        if ($zip->open($file['tmp_name']) !== TRUE) {
            wp_send_json_error(array('message' => __('Cannot open ZIP file', 'squoosh-media-editor')));
        }

        // Create Session
        $session_id = uniqid('squoosh_import_');
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/squoosh-temp/' . $session_id;

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Extract Everything
        $zip->extractTo($temp_dir);
        $zip->close();

        // Read Manifest
        $manifest_file = $temp_dir . '/manifest.json';
        if (!file_exists($manifest_file)) {
            $this->recursive_rmdir($temp_dir);
            wp_send_json_error(array('message' => __('Invalid backup: manifest.json missing', 'squoosh-media-editor')));
        }

        $manifest = json_decode(file_get_contents($manifest_file), true);
        if (!is_array($manifest)) {
            $this->recursive_rmdir($temp_dir);
            wp_send_json_error(array('message' => __('Invalid manifest format', 'squoosh-media-editor')));
        }

        // Store session data loosely (could be transient, but file system is safer for large data)
        // We'll rely on the temp dir existence and manifest for the batch steps

        wp_send_json_success(array(
            'message' => __('Session started', 'squoosh-media-editor'),
            'session_id' => $session_id,
            'total_files' => count($manifest)
        ));
    }

    /**
     * Process Batch
     * Takes session_id, offset, limit
     */
    public function handle_import_batch()
    {
        if (!check_ajax_referer('squoosh_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $session_id = sanitize_text_field($_POST['session_id']);
        $offset = intval($_POST['offset']);
        $limit = intval($_POST['limit']);
        $mode = sanitize_text_field($_POST['mode']);

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/squoosh-temp/' . $session_id;
        $manifest_file = $temp_dir . '/manifest.json';

        if (!file_exists($manifest_file)) {
            wp_send_json_error(array('message' => __('Session expired or invalid', 'squoosh-media-editor')));
        }

        $manifest = json_decode(file_get_contents($manifest_file), true);
        $batch = array_slice($manifest, $offset, $limit);

        $base_dir = $upload_dir['basedir'];
        $imported_count = 0;
        $id_map = array();

        // If this is the FIRST batch and mode is REPLACE, wipe library
        if ($offset === 0 && $mode === 'replace') {
            global $wpdb;

            // BACKUP References (Rescue Phase)
            // Because wp_delete_attachment triggers hooks that might delete postmeta (like _thumbnail_id or _product_image_gallery in WC)
            $backup_data = array();

            // 1. Thumbnail IDs
            $backup_data['thumbnails'] = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'", ARRAY_A);

            // 2. Product Galleries
            $backup_data['galleries'] = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND meta_value != ''", ARRAY_A);

            file_put_contents($temp_dir . '/ref_backup.json', json_encode($backup_data));

            $existing_attachments = get_posts(array(
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_status' => 'any'
            ));
            foreach ($existing_attachments as $attachment) {
                wp_delete_attachment($attachment->ID, true);
            }
        }

        foreach ($batch as $item) {
            $source_file = $temp_dir . '/' . $item['file'];
            $target_file = $base_dir . '/' . date('Y/m', strtotime($item['date'])) . '/' . basename($item['file']);
            $target_dir = dirname($target_file);

            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }

            if (file_exists($source_file)) {
                // Move file (rename is faster than copy)
                if (@rename($source_file, $target_file)) {

                    // Prepare args
                    $attachment_data = array(
                        'post_mime_type' => $item['mime_type'],
                        'post_title' => $item['title'],
                        'post_content' => $item['description'],
                        'post_excerpt' => $item['caption'],
                        'post_status' => 'inherit',
                        'post_date' => $item['date']
                    );

                    // Insert
                    $new_id = wp_insert_attachment($attachment_data, $target_file);

                    if (!is_wp_error($new_id)) {
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $attach_data = wp_generate_attachment_metadata($new_id, $target_file);

                        // Restore Meta
                        if (isset($item['alt'])) {
                            update_post_meta($new_id, '_wp_attachment_image_alt', $item['alt']);
                        }

                        wp_update_attachment_metadata($new_id, $attach_data);

                        $id_map[$item['id']] = $new_id;
                        $imported_count++;
                    }
                }
            }
        }

        wp_send_json_success(array(
            'processed' => $imported_count,
            'id_map' => $id_map
        ));
    }

    /**
     * Finalize Import
     * 1. Run reference replacements
     * 2. Cleanup temp dir
     */
    public function handle_import_finalize()
    {
        if (!check_ajax_referer('squoosh_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $session_id = sanitize_text_field($_POST['session_id']);
        $auto_replace = $_POST['auto_replace'] === 'true';
        $id_map_json = stripslashes($_POST['id_map']);
        $id_map = json_decode($id_map_json, true);

        // Prep directories for cleanup lookup
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/squoosh-temp/' . $session_id;

        $replacements = 0;
        if ($auto_replace && is_array($id_map)) {
            $replacements = $this->replace_references($id_map, $temp_dir);
        }

        // Cleanup
        $this->recursive_rmdir($temp_dir);

        wp_send_json_success(array(
            'message' => __('Import completed successfully!', 'squoosh-media-editor'),
            'replacements' => $replacements
        ));
    }

    /**
     * Replace references using direct DB access for speed and robustness
     */
    private function replace_references($id_map, $temp_dir)
    {
        global $wpdb;
        $count = 0;

        // Restore from Backup (The "Safe" Strategy)
        $backup_file = $temp_dir . '/ref_backup.json';
        if (file_exists($backup_file)) {
            $backup = json_decode(file_get_contents($backup_file), true);

            // 1. Restore Thumbnails
            if (!empty($backup['thumbnails'])) {
                foreach ($backup['thumbnails'] as $row) {
                    $old_id = $row['meta_value'];
                    if (isset($id_map[$old_id])) {
                        $new_id = $id_map[$old_id];
                        // Direct update to ensure it points to new ID
                        $updated = update_post_meta($row['post_id'], '_thumbnail_id', $new_id);
                        if ($updated)
                            $count++;
                    }
                }
            }

            // 2. Restore Galleries
            if (!empty($backup['galleries'])) {
                foreach ($backup['galleries'] as $row) {
                    $old_ids = explode(',', $row['meta_value']);
                    $changed = false;
                    foreach ($old_ids as $k => $oid) {
                        $oid = trim($oid);
                        if (isset($id_map[$oid])) {
                            $old_ids[$k] = $id_map[$oid];
                            $changed = true;
                        }
                    }
                    if ($changed) {
                        $updated = update_post_meta($row['post_id'], '_product_image_gallery', implode(',', $old_ids));
                        if ($updated)
                            $count++;
                    }
                }
            }
        }

        foreach ($id_map as $old_id => $new_id) {
            if ($old_id == $new_id)
                continue;

            // 1. Featured Images (_thumbnail_id)
            $updated = $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $new_id),
                array('meta_key' => '_thumbnail_id', 'meta_value' => $old_id)
            );
            if ($updated)
                $count += $updated;

            // 2. Product Galleries (_product_image_gallery)
            // WooCommerce stores comma-separated strings "123,456,789"
            // We search for the old ID surrounded by commas, start, or end
            $like_queries = array(
                "meta_value = '{$old_id}'",           // Exact match
                "meta_value LIKE '{$old_id},%'",      // Start
                "meta_value LIKE '%,{$old_id},%'",    // Middle
                "meta_value LIKE '%,{$old_id}'"       // End
            );

            $sql = "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND (" . implode(' OR ', $like_queries) . ")";
            $results = $wpdb->get_results($sql);

            foreach ($results as $row) {
                $ids = explode(',', $row->meta_value);
                $key = array_search($old_id, $ids);
                if ($key !== false) {
                    $ids[$key] = $new_id;
                    $new_val = implode(',', $ids);
                    if ($wpdb->update($wpdb->postmeta, array('meta_value' => $new_val), array('meta_id' => $row->meta_id))) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private function recursive_rmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                        $this->recursive_rmdir($dir . DIRECTORY_SEPARATOR . $object);
                    else
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
            rmdir($dir);
        }
    }
}