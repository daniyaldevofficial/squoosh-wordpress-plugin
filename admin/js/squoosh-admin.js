/**
 * Squoosh Media Editor - Admin JavaScript
 * Handles the editor modal and communication with Squoosh iframe
 */

(function ($) {
    'use strict';

    // Global state
    let currentAttachmentId = null;
    let currentImageUrl = null;
    let squooshIframe = null;
    let isEditorReady = false;
    let pendingImageData = null;

    /**
     * Initialize the plugin
     */
    function init() {
        bindEvents();
        setupMessageListener();
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Edit with Squoosh links in media library
        $(document).on('click', '.squoosh-edit-link, .squoosh-edit-btn', function (e) {
            e.preventDefault();
            const attachmentId = $(this).data('attachment-id');
            const imageUrl = $(this).data('image-url');
            openEditor(attachmentId, imageUrl);
        });

        // Modal close button
        $(document).on('click', '.squoosh-modal-close, .squoosh-modal-cancel, .squoosh-modal-overlay', function (e) {
            if ($(e.target).is('.squoosh-modal-overlay, .squoosh-modal-close, .squoosh-modal-cancel, .squoosh-modal-close *')) {
                closeEditor();
            }
        });

        // Save button
        $(document).on('click', '#squoosh-save-btn', function () {
            if (!$(this).prop('disabled')) {
                requestImageFromSquoosh();
            }
        });

        // Keyboard shortcuts
        $(document).on('keydown', function (e) {
            if ($('#squoosh-editor-modal').is(':visible')) {
                // ESC to close
                if (e.keyCode === 27) {
                    closeEditor();
                }
                // Ctrl+S to save
                if (e.ctrlKey && e.keyCode === 83) {
                    e.preventDefault();
                    if (!$('#squoosh-save-btn').prop('disabled')) {
                        requestImageFromSquoosh();
                    }
                }
            }
        });
    }

    /**
     * Setup postMessage listener for communication with Squoosh iframe
     */
    function setupMessageListener() {
        window.addEventListener('message', function (event) {
            // Verify origin
            if (!event.origin.includes(window.location.host)) {
                return;
            }

            const data = event.data;

            if (!data || !data.type) {
                return;
            }

            switch (data.type) {
                case 'squoosh-ready':
                    handleSquooshReady();
                    break;
                case 'squoosh-image-loaded':
                    handleImageLoaded(data);
                    break;
                case 'squoosh-image-data':
                    handleImageData(data);
                    break;
                case 'squoosh-error':
                    handleSquooshError(data);
                    break;
                case 'squoosh-save-ready':
                    handleSaveReady(data);
                    break;
            }
        });
    }

    /**
     * Open the Squoosh editor modal
     */
    function openEditor(attachmentId, imageUrl) {
        currentAttachmentId = attachmentId;
        currentImageUrl = imageUrl;
        isEditorReady = false;

        // Show modal
        $('#squoosh-editor-modal').show();
        $('body').css('overflow', 'hidden');

        // Reset save button
        $('#squoosh-save-btn').prop('disabled', true);
        updateStatus(squooshData.strings.editWithSquoosh + '...');

        // Load Squoosh in iframe with WordPress integration flag
        const editorUrl = squooshData.editorUrl + '?wp_edit=1&t=' + Date.now();
        squooshIframe = document.getElementById('squoosh-editor-iframe');
        squooshIframe.src = editorUrl;
    }

    /**
     * Close the editor modal
     */
    function closeEditor() {
        $('#squoosh-editor-modal').hide();
        $('body').css('overflow', '');

        // Clear iframe
        if (squooshIframe) {
            squooshIframe.src = 'about:blank';
        }

        // Reset state
        currentAttachmentId = null;
        currentImageUrl = null;
        isEditorReady = false;
        pendingImageData = null;
    }

    /**
     * Handle Squoosh editor ready
     */
    function handleSquooshReady() {
        isEditorReady = true;

        // Send image to Squoosh
        if (currentImageUrl && squooshIframe) {
            squooshIframe.contentWindow.postMessage({
                type: 'wp-load-image',
                url: currentImageUrl,
                attachmentId: currentAttachmentId
            }, '*');
        }
    }

    /**
     * Handle image loaded in Squoosh
     */
    function handleImageLoaded(data) {
        updateStatus('Image loaded. Edit and click Save when ready.');
        $('#squoosh-save-btn').prop('disabled', false);
    }

    /**
     * Request image data from Squoosh
     */
    function requestImageFromSquoosh() {
        if (!squooshIframe || !isEditorReady) {
            return;
        }

        $('#squoosh-save-btn').prop('disabled', true).addClass('saving');
        updateStatus(squooshData.strings.saving);

        squooshIframe.contentWindow.postMessage({
            type: 'wp-get-image'
        }, '*');
    }

    /**
     * Handle save ready signal from Squoosh
     */
    function handleSaveReady(data) {
        // Enable save button when user has made edits
        if (data.hasOutput) {
            $('#squoosh-save-btn').prop('disabled', false);
        }
    }

    /**
     * Handle image data received from Squoosh
     */
    function handleImageData(data) {
        if (!data.imageData || !data.mimeType) {
            showToast(squooshData.strings.error + ': No image data received', 'error');
            $('#squoosh-save-btn').prop('disabled', false).removeClass('saving');
            return;
        }

        // Check if delete mode and confirm
        if (squooshData.replaceMode === 'delete') {
            if (!confirm(squooshData.strings.confirmDelete)) {
                $('#squoosh-save-btn').prop('disabled', false).removeClass('saving');
                updateStatus('Save cancelled.');
                return;
            }
        }

        // Send to server
        saveImageToWordPress(data.imageData, data.mimeType, data.filename);
    }

    /**
     * Handle Squoosh error
     */
    function handleSquooshError(data) {
        showToast(squooshData.strings.error + ': ' + (data.message || 'Unknown error'), 'error');
        $('#squoosh-save-btn').prop('disabled', false).removeClass('saving');
    }

    /**
     * Save image to WordPress via AJAX
     */
    function saveImageToWordPress(imageData, mimeType, filename) {
        $.ajax({
            url: squooshData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'squoosh_save_image',
                nonce: squooshData.nonce,
                attachment_id: currentAttachmentId,
                image_data: imageData,
                mime_type: mimeType,
                filename: filename || ''
            },
            success: function (response) {
                $('#squoosh-save-btn').removeClass('saving');

                if (response.success) {
                    showToast(squooshData.strings.saved, 'success');
                    closeEditor();

                    // Refresh media library if we're on that page
                    if (typeof wp !== 'undefined' && wp.media && wp.media.frame) {
                        wp.media.frame.content.get().collection.fetch();
                    }

                    // Reload page to show updated image
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else {
                    showToast(squooshData.strings.error + ': ' + (response.data.message || 'Unknown error'), 'error');
                    $('#squoosh-save-btn').prop('disabled', false);
                }
            },
            error: function (xhr, status, error) {
                $('#squoosh-save-btn').removeClass('saving').prop('disabled', false);
                showToast(squooshData.strings.error + ': ' + error, 'error');
            }
        });
    }

    /**
     * Update modal status text
     */
    function updateStatus(message) {
        $('#squoosh-modal-status').text(message);
    }

    /**
     * Show toast notification
     */
    function showToast(message, type) {
        // Remove existing toast
        $('.squoosh-toast').remove();

        const $toast = $('<div class="squoosh-toast ' + type + '">' + message + '</div>');
        $('body').append($toast);

        // Trigger animation
        setTimeout(function () {
            $toast.addClass('show');
        }, 10);

        // Auto-hide after 4 seconds
        setTimeout(function () {
            $toast.removeClass('show');
            setTimeout(function () {
                $toast.remove();
            }, 300);
        }, 4000);
    }

    // Initialize on document ready
    $(document).ready(init);

    // Expose functions for external use
    window.SquooshAdmin = {
        openEditor: openEditor,
        closeEditor: closeEditor,
        showToast: showToast
    };

})(jQuery);
