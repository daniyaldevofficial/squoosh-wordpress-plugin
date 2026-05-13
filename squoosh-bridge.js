/**
 * Squoosh Bridge - WordPress Integration
 * 
 * This script is loaded by Squoosh when in WordPress integration mode (wp_edit=1).
 * It handles communication between WordPress admin and the Squoosh editor.
 */

(function () {
    'use strict';

    // Check if we're in WordPress mode
    const urlParams = new URLSearchParams(window.location.search);
    const isWpEdit = urlParams.get('wp_edit') === '1';
    const isWpBulk = urlParams.get('wp_bulk') === '1';

    if (!isWpEdit && !isWpBulk) {
        return;
    }

    /**
     * Initialize WordPress bridge
     */
    function init() {
        console.log('Squoosh WordPress Bridge initialized');

        // Setup message listener
        window.addEventListener('message', handleParentMessage);

        // Notify parent that Squoosh is ready
        notifyParent('squoosh-ready', {});

        // For bulk mode, set up worker mode
        if (isWpBulk) {
            setupBulkMode();
        } else {
            // Start polling for download links
            pollForDownloadLinks();
        }
    }

    /**
     * Handle messages from parent window (WordPress admin)
     */
    function handleParentMessage(event) {
        const data = event.data;

        if (!data || !data.type) {
            return;
        }

        switch (data.type) {
            case 'wp-load-image':
                loadImageFromUrl(data.url, data.attachmentId);
                break;
            case 'wp-get-image':
                sendImageToParent();
                break;
            case 'wp-convert-image':
                convertImageForBulk(data);
                break;
        }
    }

    /**
     * Load image from URL into Squoosh
     */
    async function loadImageFromUrl(url, attachmentId) {
        try {
            console.log('Loading image:', url);

            // Fetch the image as blob
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error('Failed to fetch image: ' + response.statusText);
            }

            const blob = await response.blob();

            // Create a File object
            const filename = url.split('/').pop().split('?')[0];
            const file = new File([blob], filename, { type: blob.type });

            // Find the file input and trigger it
            const fileInput = document.querySelector('input[type="file"]');

            if (fileInput) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                console.log('Triggered file input change');
            }

            // Notify parent after delay
            setTimeout(() => {
                notifyParent('squoosh-image-loaded', { attachmentId: attachmentId });
            }, 1500);

        } catch (error) {
            console.error('Error loading image:', error);
            notifyParent('squoosh-error', { message: error.message });
        }
    }

    /**
     * Poll for download links to notify parent when output is ready
     */
    function pollForDownloadLinks() {
        setInterval(() => {
            const downloadLink = findDownloadLink();
            if (downloadLink) {
                notifyParent('squoosh-save-ready', { hasOutput: true });
            }
        }, 2000);
    }

    /**
     * Find download link in Squoosh (anchor with blob: href)
     */
    function findDownloadLink() {
        // Squoosh creates an anchor with href="blob:..." and download="filename.ext"
        const anchors = document.querySelectorAll('a[download][href^="blob:"]');

        // We want the RIGHT side (output) download link
        // Squoosh has two sides, we typically want the second one
        if (anchors.length >= 1) {
            // Get the last one (usually the output)
            return anchors[anchors.length - 1];
        }

        // Also check for any anchor with blob href
        const blobAnchors = document.querySelectorAll('a[href^="blob:"]');
        if (blobAnchors.length >= 1) {
            return blobAnchors[blobAnchors.length - 1];
        }

        return null;
    }

    /**
     * Send current output image to parent window
     */
    async function sendImageToParent() {
        try {
            console.log('Finding download link...');

            // Find the download anchor with blob URL
            const downloadLink = findDownloadLink();

            if (!downloadLink) {
                console.log('No download link found, falling back to canvas...');
                const result = await captureViaCanvas();
                sendResult(result.blob, result.mimeType, result.filename);
                return;
            }

            const blobUrl = downloadLink.href;
            const filename = downloadLink.download || 'image.webp';

            console.log('Found download link:', blobUrl, 'Filename:', filename);

            // Fetch the blob from the URL
            const response = await fetch(blobUrl);
            const blob = await response.blob();

            console.log('Fetched blob:', blob.type, blob.size, 'bytes');

            // Get mime type from blob or filename
            let mimeType = blob.type;
            if (!mimeType || mimeType === 'application/octet-stream') {
                mimeType = getMimeFromFilename(filename);
            }

            sendResult(blob, mimeType, filename);

        } catch (error) {
            console.error('Error getting image:', error);
            notifyParent('squoosh-error', { message: error.message });
        }
    }

    /**
     * Send result to parent
     */
    async function sendResult(blob, mimeType, filename) {
        // Convert to base64
        const reader = new FileReader();
        const base64Promise = new Promise((resolve, reject) => {
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
        });
        reader.readAsDataURL(blob);
        const base64 = await base64Promise;

        console.log('Sending to WordPress:', mimeType, filename, 'Size:', blob.size);

        notifyParent('squoosh-image-data', {
            imageData: base64,
            mimeType: mimeType,
            filename: filename
        });
    }

    /**
     * Get MIME type from filename extension
     */
    function getMimeFromFilename(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const mimeMap = {
            'jpg': 'image/jpeg',
            'jpeg': 'image/jpeg',
            'png': 'image/png',
            'webp': 'image/webp',
            'avif': 'image/avif',
            'jxl': 'image/jxl',
            'gif': 'image/gif',
            'qoi': 'image/qoi'
        };
        return mimeMap[ext] || 'image/webp';
    }

    /**
     * Fallback: Capture via canvas
     */
    async function captureViaCanvas() {
        console.log('Using canvas fallback...');

        // Try to detect format from UI
        let detectedFormat = detectFormatFromUI();
        console.log('Detected format from UI:', detectedFormat);

        // Find the output canvas
        const canvases = document.querySelectorAll('canvas');
        const outputCanvas = canvases.length > 1 ? canvases[canvases.length - 1] : canvases[0];

        if (!outputCanvas) {
            throw new Error('No canvas found');
        }

        // Map to supported canvas formats
        const formatMimeMap = {
            'mozjpeg': 'image/jpeg',
            'jpeg': 'image/jpeg',
            'webp': 'image/webp',
            'png': 'image/png',
            'oxipng': 'image/png',
            'avif': 'image/webp', // Fallback - canvas doesn't support avif
            'jxl': 'image/webp',   // Fallback - canvas doesn't support jxl
            'qoi': 'image/png'     // Fallback
        };

        const mimeType = formatMimeMap[detectedFormat] || 'image/webp';
        const ext = mimeType.split('/')[1];

        const blob = await new Promise((resolve) => {
            outputCanvas.toBlob(resolve, mimeType, 0.92);
        });

        return {
            blob: blob,
            mimeType: mimeType,
            filename: 'image.' + ext
        };
    }

    /**
     * Detect the selected format from Squoosh's UI
     */
    function detectFormatFromUI() {
        // Method 1: Look for format dropdown/select
        const selects = document.querySelectorAll('select');
        for (const select of selects) {
            const options = select.querySelectorAll('option');
            for (const option of options) {
                if (option.selected) {
                    const text = option.textContent.toLowerCase();
                    if (text.includes('mozjpeg')) return 'mozjpeg';
                    if (text.includes('webp')) return 'webp';
                    if (text.includes('avif')) return 'avif';
                    if (text.includes('oxipng') || text.includes('png')) return 'png';
                    if (text.includes('jxl') || text.includes('jpeg xl')) return 'jxl';
                    if (text.includes('qoi')) return 'qoi';
                }
            }
        }

        // Method 2: Look for active/selected buttons or tabs
        const allElements = document.querySelectorAll('button, [role="tab"], [role="option"]');
        for (const el of allElements) {
            const isSelected = el.getAttribute('aria-selected') === 'true' ||
                el.classList.contains('active') ||
                el.classList.contains('selected');

            if (isSelected) {
                const text = (el.textContent || '').toLowerCase().trim();
                if (text === 'mozjpeg') return 'mozjpeg';
                if (text === 'webp') return 'webp';
                if (text === 'avif') return 'avif';
                if (text === 'oxipng') return 'png';
                if (text === 'jxl' || text === 'jpeg xl') return 'jxl';
            }
        }

        // Method 3: Check the download filename if visible
        const downloadLink = findDownloadLink();
        if (downloadLink && downloadLink.download) {
            const ext = downloadLink.download.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg'].includes(ext)) return 'mozjpeg';
            if (ext === 'webp') return 'webp';
            if (ext === 'avif') return 'avif';
            if (ext === 'png') return 'png';
            if (ext === 'jxl') return 'jxl';
        }

        return 'webp'; // Default fallback
    }

    /**
     * Setup bulk conversion mode
     */
    function setupBulkMode() {
        // Hide the main UI
        document.body.style.visibility = 'hidden';

        console.log('Bulk mode initialized, notifying parent...');

        // Notify parent that worker is ready
        notifyParent('squoosh-worker-ready', {});
    }

    /**
     * Convert image for bulk operation
     */
    async function convertImageForBulk(data) {
        try {
            console.log('Converting image:', data.url, 'to', data.format);

            // Fetch image
            const response = await fetch(data.url);
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
            const quality = (data.quality || 75) / 100;

            switch (data.format) {
                case 'webp':
                    outputMime = 'image/webp';
                    outputBlob = await new Promise(r => canvas.toBlob(r, 'image/webp', quality));
                    break;
                case 'jpeg':
                    outputMime = 'image/jpeg';
                    outputBlob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', quality));
                    break;
                case 'png':
                    outputMime = 'image/png';
                    outputBlob = await new Promise(r => canvas.toBlob(r, 'image/png'));
                    break;
                default:
                    outputMime = 'image/webp';
                    outputBlob = await new Promise(r => canvas.toBlob(r, 'image/webp', quality));
            }

            // Clean up
            URL.revokeObjectURL(img.src);

            if (!outputBlob) {
                throw new Error('Failed to encode image');
            }

            // Convert to base64
            const reader = new FileReader();
            const base64 = await new Promise((resolve) => {
                reader.onload = () => resolve(reader.result);
                reader.readAsDataURL(outputBlob);
            });

            console.log('Conversion done:', data.id, outputMime, outputBlob.size);

            notifyParent('squoosh-convert-done', {
                id: data.id,
                index: data.index,
                imageData: base64,
                mimeType: outputMime
            });

        } catch (error) {
            console.error('Bulk conversion error:', error);
            notifyParent('squoosh-convert-error', {
                id: data.id,
                index: data.index,
                message: error.message
            });
        }
    }

    /**
     * Send message to parent window
     */
    function notifyParent(type, data) {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: type,
                ...data
            }, '*');
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
