# PXL8 WordPress Plugin

> **Status:** Phase 9 - In Development
> **Version:** 1.0.0
> **Last Updated:** 8 January 2026

Automatic image optimization with PXL8 CDN for WordPress.

## Features

- ✅ Auto-optimize images on upload
- ✅ On-the-fly transformations (resize, crop, format, quality)
- ✅ Responsive images (srcset) fully supported
- ✅ Quota monitoring dashboard
- ✅ Test Connection tool
- ✅ Zero database calls on frontend

## Requirements

- WordPress 5.9+
- PHP 7.4+
- Composer

## Installation

### For Development

```bash
# Clone repository
git clone git@github.com:GeorgyBatalov/pxl8-wordpress-plugin.git

# Install dependencies
cd pxl8-wordpress-plugin
composer install

# Symlink to WordPress plugins directory
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/pxl8
```

### For Production

1. Download latest release from GitHub
2. Extract to `wp-content/plugins/pxl8`
3. Activate in WordPress Admin → Plugins

## Configuration

1. Go to **Settings → PXL8**
2. Enter **Base URL** (e.g., `https://img.example.com`)
3. Enter **API Key** (from PXL8 dashboard)
4. Click **Test Connection**
5. Enable plugin
6. Configure default quality, format, fit mode

## Usage

Once configured, the plugin automatically:

1. **Uploads images to PXL8** when added to Media Library
2. **Replaces URLs** on frontend with PXL8 CDN URLs
3. **Generates responsive images** (srcset) with optimal sizes

### Example Output

**Before:**
```html
<img src="https://example.com/wp-content/uploads/2026/01/image.jpg" width="800">
```

**After:**
```html
<img src="https://img.example.com/img/transform/{imageId}.webp?w=800&q=85" width="800">
```

## Technical Specification

See [TECHNICAL_SPEC.md](./TECHNICAL_SPEC.md) for detailed technical specification (v1.0.1 Pragmatic MVP).

## Development

### Running Tests

```bash
# Unit tests
vendor/bin/phpunit tests/Unit

# Integration tests (requires WordPress test suite)
vendor/bin/phpunit tests/Integration
```

### Local Development Environment

```bash
# Docker Compose
docker-compose up -d

# WordPress will be available at http://localhost:8000
```

## Architecture

```
pxl8/
├── pxl8.php                    # Plugin bootstrap
├── composer.json               # Dependencies (pxl8-sdk-php)
├── includes/
│   ├── Plugin.php             # Main plugin class
│   ├── Admin/
│   │   ├── SettingsPage.php   # Settings UI
│   │   └── QuotaWidget.php    # Quota display
│   ├── Media/
│   │   ├── UploadHandler.php  # Auto-optimize on upload
│   │   └── UrlRewriter.php    # Frontend URL rewriting
│   ├── Sdk/
│   │   └── ClientFactory.php  # PXL8 SDK client factory
│   └── Storage/
│       ├── Options.php         # WordPress options
│       └── AttachmentMeta.php  # Attachment metadata
└── assets/
    ├── css/admin.css           # Admin styles
    └── js/admin.js            # Test Connection AJAX
```

## API Integration

Uses [pxl8-sdk-php](../pxl8-sdk-php) for all API calls:

- `$client->upload($filePath)` - Upload image
- `$client->getUrl($imageId, $options)` - Build transform URL
- `$client->getUsage()` - Get quota/usage stats
- `$client->getTenant()` - Test connection

## Hooks & Filters

### Filters (Frontend URL Rewriting)

- `wp_get_attachment_url` - Replace base URL
- `wp_get_attachment_image_src` - Replace URL with dimensions
- `wp_calculate_image_srcset` - Replace srcset sources

### Actions (Upload Handler)

- `wp_generate_attachment_metadata` - Auto-optimize on upload

## Support

- **Documentation**: https://docs.pxl8.ru
- **Email**: support@pxl8.ru
- **Issues**: https://github.com/GeorgyBatalov/pxl8-wordpress-plugin/issues

## License

MIT License - see LICENSE file for details.

---

**Made with ❤️ by the PXL8 team**
