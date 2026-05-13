/**
 * Squoosh Media Editor - Bulk Conversion JavaScript
 * Handles bulk image conversion using Squoosh workers
 */

(function ($) {
    'use strict';

    let workerFrame = null;
    let isWorkerReady = false;
    let conversionQueue = [];
    let currentIndex = 0;
    let totalImages = 0;
    let completedImages = 0;
    let errorCount = 0;
    let isProcessing = false;

    /**
     * Initialize bulk conversion
     */
    function init() {
        console.log('Squoosh Bulk Conversion initialized');

        bindEvents();
        setupMessageListener();

        // Get worker iframe reference
        workerFrame = document.getElementById('squoosh-worker-frame');

        if (workerFrame) {
            console.log('Worker iframe found:', workerFrame.src);

            // Handle iframe load event
            workerFrame.onload = function () {
                console.log('Worker iframe loaded');
            };
        } else {
            console.log('No worker iframe found on this page');
        }
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Quality slider update
        $(document).on('input', '#squoosh-bulk-quality', function () {
            $('#squoosh-bulk-quality-value').text(this.value);
        });

        // Start bulk conversion - use event delegation
        $(document).on('click', '#squoosh-start-bulk', function (e) {
            e.preventDefault();
            console.log('Start Conversion button clicked');
            startBulkConversion();
        });
    }

    /**
     * Setup message listener for worker frame
     */
    function setupMessageListener() {
        window.addEventListener('message', function (event) {
            const data = event.data;

            if (!data || !data.type) {
                return;
            }

            console.log('Received message:', data.type);

            switch (data.type) {
                case 'squoosh-worker-ready':
                    handleWorkerReady();
                    break;
                case 'squoosh-convert-done':
                    handleConversionDone(data);
                    break;
                case 'squoosh-convert-error':
                    handleConversionError(data);
                    break;
            }
        });
    }

    /**
     * Handle worker ready
     */
    function handleWorkerReady() {
        console.log('Worker is ready!');
        isWorkerReady = true;

        // If we have a pending queue, start processing
        if (conversionQueue.length > 0 && isProcessing) {
            processNextImage();
        }
    }

    /**
     * Start bulk conversion process
     */
    function startBulkConversion() {
        console.log('Starting bulk conversion...');

        const format = $('#squoosh-bulk-format').val();
        const quality = parseInt($('#squoosh-bulk-quality').val(), 10);

        console.log('Format:', format, 'Quality:', quality);

        // Build queue from preview items
        conversionQueue = [];
        $('#squoosh-bulk-preview .squoosh-bulk-item').each(function () {
            const $item = $(this);
            conversionQueue.push({
                id: $item.data('id'),
                url: $item.data('url'),
                format: format,
                quality: quality,
                element: $item
            });
        });

        console.log('Queue built with', conversionQueue.length, 'images');

        if (conversionQueue.length === 0) {
            showToast('No images to convert', 'error');
            return;
        }

        totalImages = conversionQueue.length;
        completedImages = 0;
        errorCount = 0;
        currentIndex = 0;
        isProcessing = true;

        // Disable UI
        $('#squoosh-start-bulk').prop('disabled', true).text('Converting...');
        $('#squoosh-bulk-format, #squoosh-bulk-quality').prop('disabled', true);

        // Show progress
        $('#squoosh-bulk-progress').show();
        updateProgress();

        // Reset all item statuses
        $('.squoosh-bulk-item').removeClass('processing done error');

        // Check if worker is ready
        if (isWorkerReady) {
            console.log('Worker ready, starting processing');
            processNextImage();
        } else {
            console.log('Worker not ready yet, waiting...');
            // Check if iframe exists and try to wait for it
            if (workerFrame) {
                // Give it more time to load
                setTimeout(function () {
                    if (!isWorkerReady) {
                        console.log('Worker still not ready after timeout, processing directly');
                        // Process without worker - do it directly
                        processDirectly();
                    }
                }, 3000);
            } else {
                // No iframe, process directly
                console.log('No worker iframe, processing directly');
                processDirectly();
            }
        }
    }

    /**
     * Process images directly without worker iframe
     */
    async function processDirectly() {
        console.log('Processing directly (no worker)');

        for (let i = 0; i < conversionQueue.length; i++) {
            currentIndex = i;
            const item = conversionQueue[i];
            item.element.addClass('processing');

            try {
                const result = await convertImageDirect(item.url, item.format, item.quality);

                // Save to WordPress
                await saveConvertedImageAsync(item.id, result.imageData, result.mimeType);

                item.element.removeClass('processing').addClass('done');
                completedImages++;
            } catch (error) {
                console.error('Conversion error:', error);
                item.element.removeClass('processing').addClass('error');
                errorCount++;
            }

            updateProgress();
        }

        finishBulkConversion();
    }

    /**
     * Convert image directly using canvas
     */
    async function convertImageDirect(url, format, quality) {
        // Fetch image
        const response = await fetch(url);
        const blob = await response.blob();

        // Load into image
        const img = new Image();
        img.crossOrigin = 'anonymous';

        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
            img.src = URL.createObjectURL(blob);
        });

        // Create canvas
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);

        // Convert to requested format
        let outputMime, outputBlob;
        const q = (quality || 75) / 100;

        switch (format) {
            case 'webp':
                outputMime = 'image/webp';
                outputBlob = await new Promise(r => canvas.toBlob(r, 'image/webp', q));
                break;
            case 'jpeg':
                outputMime = 'image/jpeg';
                outputBlob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', q));
                break;
            case 'png':
                outputMime = 'image/png';
                outputBlob = await new Promise(r => canvas.toBlob(r, 'image/png'));
                break;
            default:
                outputMime = 'image/webp';
                outputBlob = await new Promise(r => canvas.toBlob(r, 'image/webp', q));
        }

        // Clean up
        URL.revokeObjectURL(img.src);

        // Convert to base64
        const reader = new FileReader();
        const base64 = await new Promise((resolve) => {
            reader.onload = () => resolve(reader.result);
            reader.readAsDataURL(outputBlob);
        });

        return {
            imageData: base64,
            mimeType: outputMime
        };
    }

    /**
     * Save converted image async
     */
    function saveConvertedImageAsync(attachmentId, imageData, mimeType) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: squooshData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'squoosh_save_image',
                    nonce: squooshData.nonce,
                    attachment_id: attachmentId,
                    image_data: imageData,
                    mime_type: mimeType,
                    filename: ''
                },
                success: function (response) {
                    if (response.success) {
                        resolve(response);
                    } else {
                        reject(new Error(response.data?.message || 'Save failed'));
                    }
                },
                error: function (xhr, status, error) {
                    reject(new Error(error));
                }
            });
        });
    }

    /**
     * Process next image in queue (via worker)
     */
    function processNextImage() {
        if (currentIndex >= conversionQueue.length) {
            // All done
            finishBulkConversion();
            return;
        }

        const item = conversionQueue[currentIndex];
        item.element.addClass('processing');

        console.log('Processing image', currentIndex + 1, 'of', conversionQueue.length);

        // Send to worker for conversion
        if (workerFrame && workerFrame.contentWindow) {
            workerFrame.contentWindow.postMessage({
                type: 'wp-convert-image',
                id: item.id,
                url: item.url,
                format: item.format,
                quality: item.quality,
                index: currentIndex
            }, '*');
        } else {
            console.error('Worker frame not available');
            // Fall back to direct processing
            processDirectly();
        }
    }

    /**
     * Handle successful conversion from worker
     */
    function handleConversionDone(data) {
        console.log('Conversion done for index:', data.index);

        const item = conversionQueue[data.index];

        if (item) {
            item.element.removeClass('processing').addClass('done');
        }

        // Save to WordPress
        saveConvertedImage(data);
    }

    /**
     * Save converted image to WordPress
     */
    function saveConvertedImage(data) {
        $.ajax({
            url: squooshData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'squoosh_save_image',
                nonce: squooshData.nonce,
                attachment_id: data.id,
                image_data: data.imageData,
                mime_type: data.mimeType,
                filename: ''
            },
            success: function (response) {
                if (response.success) {
                    completedImages++;
                } else {
                    errorCount++;
                    const item = conversionQueue[data.index];
                    if (item) {
                        item.element.removeClass('done').addClass('error');
                    }
                }
                updateProgress();
                moveToNext();
            },
            error: function () {
                errorCount++;
                const item = conversionQueue[data.index];
                if (item) {
                    item.element.removeClass('done').addClass('error');
                }
                updateProgress();
                moveToNext();
            }
        });
    }

    /**
     * Handle conversion error from worker
     */
    function handleConversionError(data) {
        console.error('Conversion error for index:', data.index, data.message);

        const item = conversionQueue[data.index];

        if (item) {
            item.element.removeClass('processing').addClass('error');
        }

        errorCount++;
        updateProgress();
        moveToNext();
    }

    /**
     * Move to next image
     */
    function moveToNext() {
        currentIndex++;
        processNextImage();
    }

    /**
     * Update progress display
     */
    function updateProgress() {
        const processed = completedImages + errorCount;
        const percentage = totalImages > 0 ? Math.round((processed / totalImages) * 100) : 0;

        $('#squoosh-progress-fill').css('width', percentage + '%');
        $('#squoosh-progress-text').text(
            'Processed ' + processed + ' of ' + totalImages + ' images' +
            (errorCount > 0 ? ' (' + errorCount + ' errors)' : '')
        );
    }

    /**
     * Finish bulk conversion
     */
    function finishBulkConversion() {
        console.log('Bulk conversion finished:', completedImages, 'completed,', errorCount, 'errors');

        isProcessing = false;
        $('#squoosh-start-bulk').prop('disabled', false).text('Start Conversion');
        $('#squoosh-bulk-format, #squoosh-bulk-quality').prop('disabled', false);

        const message = 'Conversion complete! ' + completedImages + ' images converted' +
            (errorCount > 0 ? ', ' + errorCount + ' errors' : '');

        showToast(message, errorCount > 0 ? 'error' : 'success');

        // Redirect back to media library after delay
        setTimeout(function () {
            window.location.href = squooshData.ajaxUrl.replace('admin-ajax.php', 'upload.php') +
                '?squoosh_converted=' + completedImages;
        }, 2000);
    }

    /**
     * Show toast notification
     */
    function showToast(message, type) {
        // Try using SquooshAdmin if available
        if (typeof SquooshAdmin !== 'undefined' && SquooshAdmin.showToast) {
            SquooshAdmin.showToast(message, type);
            return;
        }

        // Fallback: create our own toast
        $('.squoosh-toast').remove();

        const $toast = $('<div class="squoosh-toast ' + type + '">' + message + '</div>');
        $('body').append($toast);

        setTimeout(function () {
            $toast.addClass('show');
        }, 10);

        setTimeout(function () {
            $toast.removeClass('show');
            setTimeout(function () {
                $toast.remove();
            }, 300);
        }, 4000);
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
