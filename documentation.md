# Squoosh WordPress Plugin - Comprehensive Documentation

## Overview

The Squoosh WordPress Plugin is a powerful image optimization and editing solution that integrates Google's Squoosh image compression technology directly into the WordPress Media Library. This plugin provides seamless image editing, compression, format conversion, and bulk processing capabilities without leaving the WordPress dashboard.

## Core Features

### 1. **Image Editor Integration**
- **Seamless Squoosh Integration**: Embeds the full Squoosh image editor within WordPress admin
- **Real-time Preview**: Side-by-side comparison of original and optimized images
- **Multiple Format Support**: JPEG, PNG, WebP, AVIF, JXL, and QOI formats
- **Advanced Compression**: Quality control and size optimization algorithms
- **Non-destructive Editing**: Preserves original images with backup options

### 2. **Media Library Integration**
- **Row Actions**: Quick "Edit with Squoosh" buttons in media library list view
- **Attachment Edit Screen**: Dedicated editing button in attachment details
- **Bulk Operations**: Process multiple images simultaneously
- **Smart Detection**: Automatically identifies editable image formats

### 3. **Advanced Settings Management**
- **Backup System**: Automatic backup creation before image modification
- **Replace Modes**: Choose between backup or delete original files
- **Auto-replace Content**: Option to update content with optimized images
- **Server Configuration**: PHP limits and performance optimization settings
- **.htaccess Management**: Automatic server configuration for better performance

### 4. **Bulk Conversion System**
- **Queue Processing**: Efficient batch processing of multiple images
- **Worker Architecture**: Background processing using hidden iframes
- **Progress Tracking**: Real-time conversion progress with status updates
- **Error Handling**: Comprehensive error reporting and recovery
- **Format Standardization**: Convert entire media library to preferred formats

### 5. **Service Worker Integration**
- **Offline Capability**: Service worker for offline image processing
- **Asset Management**: Intelligent caching of Squoosh assets and WASM modules
- **Performance Optimization**: Preloading and resource management
- **WebAssembly Support**: Native WASM module loading for image processing

## Technical Architecture

### Core Plugin Structure

#### Main Plugin File (`squoosh-media-editor.php`)
The plugin's entry point implements the singleton pattern and handles:
- Plugin initialization and dependency loading
- WordPress hook registration
- Asset enqueuing for admin interfaces
- Settings and menu creation
- Activation/deactivation routines

**Key Components:**
```php
class Squoosh_Media_Editor {
    private static $instance = null;
    
    // Singleton pattern implementation
    public static function get_instance()
    
    // Dependency management
    private function load_dependencies()
    
    // WordPress integration hooks
    private function init_hooks()
    
    // Asset management
    public function enqueue_admin_assets($hook)
}
```

#### Settings Management (`includes/class-squoosh-settings.php`)
Comprehensive settings system with:
- Plugin configuration interface
- Server performance optimization
- .htaccess management for PHP limits
- Import/export functionality
- Security and permission settings

**Key Features:**
- Automatic .htaccess modification for PHP limits
- Backup and restore functionality
- Import/export of plugin settings
- Multi-site compatibility

#### Media Handler (`includes/class-squoosh-media-handler.php`)
Core media library integration providing:
- Row actions in media library
- Attachment edit screen integration
- Bulk operation management
- Modal rendering for editor interface

**Integration Points:**
- `media_row_actions` filter for quick edit links
- `attachment_submitbox_misc_actions` for edit screen buttons
- `bulk_actions-upload` for batch processing

#### AJAX Handler (`includes/class-squoosh-ajax.php`)
Secure AJAX processing for:
- Image saving and replacement
- Bulk conversion operations
- Image data retrieval
- Security validation and permissions

**Security Features:**
- WordPress nonce verification
- User capability checking
- File validation and sanitization
- Error handling and logging

### Frontend Architecture

#### Admin JavaScript (`admin/js/squoosh-admin.js`)
Main frontend controller handling:
- Modal management and UI interactions
- Iframe communication with Squoosh editor
- Message passing between WordPress and Squoosh
- Event handling and user interactions

**Core Functions:**
```javascript
// Modal management
function openEditor(attachmentId, imageUrl)
function closeEditor()

// Communication handlers
function setupMessageListener()
function handleSquooshReady()
function handleImageData(data)

// User interactions
function bindEvents()
function requestImageFromSquoosh()
```

#### Bulk Processing (`admin/js/squoosh-bulk.js`)
Advanced bulk conversion system featuring:
- Worker iframe management
- Queue processing system
- Progress tracking and reporting
- Error handling and recovery

**Processing Flow:**
1. Initialize worker iframe
2. Build conversion queue
3. Process images sequentially
4. Handle success/error states
5. Update progress indicators

#### WordPress Bridge (`squoosh-bridge.js`)
Critical communication layer between WordPress and Squoosh:
- Message passing protocol
- Image loading and extraction
- Format detection and conversion
- Bulk mode worker setup

**Bridge Functions:**
```javascript
// WordPress integration
function loadImageFromUrl(url, attachmentId)
function sendImageToParent()
function convertImageForBulk(data)

// Utility functions
function findDownloadLink()
function detectFormatFromUI()
function captureViaCanvas()
```

### Service Worker Architecture

#### Main Service Worker (`serviceworker.js`)
Advanced service worker implementation providing:
- Asset caching and management
- Offline functionality
- WebAssembly module loading
- Performance optimization

**Asset Management:**
- Dynamic asset name resolution
- Version-based cache invalidation
- Preloading of critical resources
- Background sync capabilities

#### Service Worker Bridge (`sw-bridge.894ac.js`)
Minimized bridge for service worker communication:
- IndexedDB integration for data storage
- Message passing between main thread and service worker
- Shared image handling
- Offline state management

### Styling and UI

#### Admin Stylesheet (`admin/css/squoosh-admin.css`)
Comprehensive styling system for:
- Settings page layout with gradient headers
- Modal overlays with backdrop blur effects
- Progress indicators and status messages
- Responsive design and accessibility
- WordPress admin integration

**Design Features:**
- Modern gradient backgrounds
- Smooth transitions and animations
- Accessibility-compliant color contrasts
- Mobile-responsive layouts

## Feature Deep Dive

### 1. **Image Editor Modal System**

The modal system provides a full-screen editing experience:

{{screenshot_for_editor_modal}}

**Features:**
- **Full-screen Modal**: 95vw x 95vh with dark theme
- **Iframe Integration**: Seamless Squoosh editor embedding
- **Communication Bridge**: Real-time data exchange
- **Keyboard Shortcuts**: ESC to close, Ctrl+S to save
- **Responsive Design**: Adapts to different screen sizes

**Technical Implementation:**
```javascript
// Modal creation and management
function openEditor(attachmentId, imageUrl) {
    // Create modal overlay
    // Initialize iframe with Squoosh URL
    // Setup message listeners
    // Handle loading states
}

// Message handling protocol
window.addEventListener('message', function(event) {
    switch(event.data.type) {
        case 'squoosh-ready':
            // Load image into editor
            break;
        case 'squoosh-image-data':
            // Process and save image
            break;
    }
});
```

### 2. **Backup and Restore System**

Comprehensive backup system ensures data safety:

{{screenshot_for_backup_system}}

**Features:**
- **Automatic Backups**: Creates backup before any modification
- **Media Library Integration**: Backups stored as separate attachments
- **Original Preservation**: Maintains file metadata and associations
- **Restore Functionality**: One-click restoration to original state
- **Storage Management**: Configurable backup retention policies

**Implementation Details:**
```php
public static function backup_original($attachment_id) {
    // Get original file information
    // Create backup directory if needed
    // Copy original file to backup location
    // Create new attachment post for backup
    // Store backup metadata
    return $backup_attachment_id;
}
```

### 3. **Bulk Conversion Engine**

Powerful batch processing system for multiple images:

{{screenshot_for_bulk_conversion}}

**Features:**
- **Queue Management**: Efficient processing of large image sets
- **Worker Architecture**: Background processing without UI blocking
- **Progress Tracking**: Real-time status updates and progress bars
- **Error Recovery**: Continues processing despite individual failures
- **Format Standardization**: Convert entire library to preferred formats

**Processing Flow:**
1. **Selection**: Choose images from media library
2. **Configuration**: Set target format and quality settings
3. **Queue Building**: Create processing queue with metadata
4. **Worker Processing**: Process images using hidden iframes
5. **Progress Updates**: Real-time status communication
6. **Completion**: Final report with success/failure statistics

### 4. **Settings and Configuration**

Comprehensive settings interface for plugin management:

{{screenshot_for_settings_page}}

**Configuration Categories:**

#### General Settings
- **Replace Mode**: Backup vs. Delete original files
- **Auto-replace Content**: Update posts/pages with optimized images
- **Default Format**: Preferred output format for conversions
- **Default Quality**: Standard compression quality

#### Performance Settings
- **PHP Memory Limit**: Allocate memory for large image processing
- **Max Execution Time**: Time limits for processing operations
- **Upload Max Filesize**: Maximum file size for uploads
- **Post Max Size**: Maximum POST request size

#### Advanced Options
- **.htaccess Management**: Automatic server configuration
- **Debug Mode**: Enhanced logging for troubleshooting
- **Multi-site Support**: Network-wide configuration options
- **Import/Export**: Settings backup and migration

### 5. **Service Worker Integration**

Advanced service worker for enhanced performance:

{{screenshot_for_service_worker}}

**Capabilities:**
- **Offline Processing**: Continue image editing without internet
- **Asset Caching**: Intelligent caching of Squoosh resources
- **WebAssembly Loading**: Efficient WASM module management
- **Background Sync**: Process images in background

**Technical Features:**
```javascript
// Service worker initialization
self.addEventListener('install', function(event) {
    // Cache essential assets
    // Load WebAssembly modules
    // Setup offline handlers
});

// Asset management
const ASSET_NAMES = [
    "logo-99b7d28c.svg",
    "mozjpeg_enc-f6bf569c.wasm",
    "webp_enc-a8223a7d.wasm",
    // ... more assets
];
```

## Integration Points

### WordPress Hooks and Filters

The plugin integrates with WordPress through numerous hooks:

```php
// Media Library Integration
add_filter('media_row_actions', array($this, 'add_row_actions'), 10, 2);
add_action('attachment_submitbox_misc_actions', array($this, 'add_attachment_action'), 99);

// Bulk Operations
add_filter('bulk_actions-upload', array($this, 'add_bulk_actions'));
add_filter('handle_bulk_actions-upload', array($this, 'handle_bulk_actions'), 10, 3);

// Admin Interface
add_action('admin_menu', array($this, 'add_settings_menu'));
add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

// AJAX Handlers
add_action('wp_ajax_squoosh_save_image', array($this, 'handle_save_image'));
add_action('wp_ajax_squoosh_convert_single', array($this, 'handle_convert_single'));
```

### Database Integration

The plugin uses WordPress options for configuration:
- `squoosh_replace_mode`: Backup or delete behavior
- `squoosh_auto_replace_content`: Content replacement setting
- `squoosh_default_format`: Preferred output format
- `squoosh_default_quality`: Default compression quality
- `squoosh_edit_htaccess`: .htaccess modification permission

### File System Integration

**Backup Management:**
- Creates backup directories with .htaccess protection
- Maintains original file metadata
- Handles file permissions and security

**Image Processing:**
- Temporary file handling during processing
- Atomic file operations to prevent corruption
- Cleanup of temporary resources

## Security Features

### Input Validation and Sanitization
- **WordPress Nonces**: CSRF protection for all AJAX requests
- **Capability Checking**: User permission verification
- **File Validation**: MIME type and size validation
- **Path Traversal Prevention**: Secure file path handling

### Data Protection
- **Secure File Handling**: Proper file permissions
- **Backup Protection**: .htaccess protection for backup directories
- **Input Sanitization**: All user inputs properly sanitized
- **Error Handling**: Secure error message display

## Performance Optimization

### Client-Side Optimizations
- **Lazy Loading**: On-demand loading of Squoosh editor
- **Message Passing**: Efficient iframe communication
- **Worker Architecture**: Background processing without UI blocking
- **Asset Caching**: Service worker for resource management

### Server-Side Optimizations
- **Memory Management**: Configurable PHP memory limits
- **Processing Limits**: Time and size constraints
- **Queue Processing**: Efficient batch operations
- **Resource Cleanup**: Automatic cleanup of temporary files

## Troubleshooting and Debugging

### Common Issues and Solutions

#### Memory Limit Errors
- **Symptoms**: Processing fails on large images
- **Solution**: Increase PHP memory limit in settings
- **Location**: Settings > Squoosh Editor > Performance

#### Permission Issues
- **Symptoms**: Unable to save or backup files
- **Solution**: Check file permissions and .htaccess writability
- **Debug**: Enable debug mode for detailed error logs

#### Service Worker Issues
- **Symptoms**: Offline functionality not working
- **Solution**: Clear service worker cache and reload
- **Debug**: Check browser console for SW errors

### Debug Mode
Enable debug mode in settings for:
- Detailed error logging
- Processing status information
- Performance metrics
- Resource usage tracking

## Future Enhancements

### Planned Features
- **AI-Powered Optimization**: Smart compression based on content
- **Cloud Processing**: Optional cloud-based processing for large files
- **Advanced Analytics**: Detailed compression statistics and reporting
- **API Integration**: REST API for external integrations
- **Multi-language Support**: Internationalization and localization

### Performance Improvements
- **WebGL Acceleration**: GPU-accelerated image processing
- **Progressive Loading**: Streamlined loading for large image sets
- **Smart Caching**: Predictive asset caching
- **Batch Optimization**: Improved queue management algorithms

## Conclusion

The Squoosh WordPress Plugin represents a sophisticated integration of modern web technologies with the WordPress ecosystem. By combining Google's Squoosh image compression technology with WordPress's robust media management system, it provides users with a powerful, efficient, and user-friendly image optimization solution.

The plugin's architecture demonstrates best practices in WordPress development, including proper use of hooks and filters, secure AJAX handling, comprehensive error management, and thoughtful user experience design. Its modular structure ensures maintainability and extensibility, while its performance optimizations ensure it can handle demanding workloads.

Whether you're a content creator looking to optimize images for better performance, a developer managing large media libraries, or a site administrator seeking to improve page load times, the Squoosh WordPress Plugin provides the tools and features needed to achieve your goals efficiently and effectively.
