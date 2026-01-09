# PXL8 WordPress Plugin

> **Status:** ✅ MVP Ready for Testing
> **Version:** 1.0.0
> **Last Updated:** 9 January 2026

Automatic image optimization with PXL8 CDN for WordPress.

---

## 📋 Current Status

### ✅ Completed (Days 1-6)

| Component | Status | Description |
|-----------|--------|-------------|
| **Day 1: Settings & SDK Integration** | ✅ Complete | Settings page, API key management, Test Connection |
| **Day 2: Upload Handler** | ✅ Complete | Auto-optimize on upload, attachment metadata |
| **Day 3: URL Rewriting** | ✅ Complete | Frontend URL replacement, srcset generation |
| **Day 4: Quota Monitoring** | ✅ Complete | Dashboard widget with transient caching |
| **Day 5: Cleanup & Uninstall** | ✅ Complete | Complete data removal on uninstall |
| **Day 6: Testing Environment** | ✅ Complete | Docker Compose test setup |
| **Bug Fixes (Session 7)** | ✅ Complete | SDK autoloader + Settings link |

### 🔧 Recent Fixes (Commit 36c5f69)

1. **SDK Autoloader Issue**
   - **Problem:** "Composer dependencies are missing" error in admin
   - **Fix:** Added `Pxl8\` namespace to composer.json autoload
   - **Status:** ✅ Resolved

2. **Settings Page Link Missing**
   - **Problem:** No "Settings" link visible on plugins page
   - **Fix:** Added `plugin_action_links` filter
   - **Status:** ✅ Resolved

### ✅ What Works Now

- ✅ Plugin activates without errors in WordPress admin
- ✅ Settings page accessible via **Settings → PXL8**
- ✅ **[Settings]** link visible on Plugins page
- ✅ No "dependencies missing" error
- ✅ All options saved to WordPress database
- ✅ Docker test environment running (http://localhost:8888)

### ⏳ What's Not Tested Yet

- ⏳ Test Connection button (requires valid API key)
- ⏳ Image upload to PXL8 API
- ⏳ URL rewriting on frontend
- ⏳ Quota widget display
- ⏳ Error handling (failed uploads)

---

## 🚀 Quick Start (Resume Work)

### 1. Start Docker Test Environment

```bash
cd /Users/chefbot/RiderProjects/pxl8/wordpress-plugin

# Start WordPress + MySQL containers
docker-compose -f docker-compose.test.yml up -d

# Wait 30 seconds for containers to initialize
# WordPress will be available at http://localhost:8888
```

### 2. Access WordPress Admin

```
URL: http://localhost:8888/wp-admin
Username: admin
Password: admin123
```

### 3. Plugin Already Activated

The PXL8 plugin is already active. No need to activate again.

### 4. Continue Testing

Follow the **Testing Guide** below to test remaining functionality.

---

## 🧪 Testing Guide

### Prerequisites

- ✅ Docker containers running
- ✅ WordPress accessible at http://localhost:8888
- ⚠️ Valid PXL8 API key (required for API tests)

### Test 1: Settings Page Verification ✅ DONE

1. Go to http://localhost:8888/wp-admin
2. Check **Settings → PXL8** in sidebar
3. Verify form fields visible:
   - Base URL
   - API Key
   - Enable Plugin checkbox
   - Auto-Optimize checkbox
   - Default Quality (85)
   - Default Format (auto)
   - Default Fit (cover)

**Expected Result:** ✅ All fields visible, no errors

**Status:** ✅ VERIFIED

---

### Test 2: Test Connection (Requires API Key)

1. Go to **Settings → PXL8**
2. Enter credentials:
   - **Base URL:** `https://img.pxl8.ru` (default)
   - **API Key:** `your_pxl8_api_key_here`
3. Click **Test Connection** button

**Expected Result:**
- ✅ Success: "Connected! Tenant: {tenant_name}"
- ❌ Failure: "Connection failed: {error_message}"

**Status:** ⏳ NOT TESTED (no API key yet)

---

### Test 3: Auto-Optimize Upload

1. Go to **Settings → PXL8**
2. Enable:
   - ✅ **Enable Plugin**
   - ✅ **Auto-Optimize on Upload**
3. Click **Save Changes**
4. Go to **Media → Add New**
5. Upload a test image (JPG or PNG, 500KB-2MB)
6. After upload completes, click on uploaded image
7. Check attachment metadata (Custom Fields section)

**Expected Result:**
- Attachment has PXL8 metadata:
  - `_pxl8_image_id`: UUID (e.g., `a7f8e2d1-...`)
  - `_pxl8_status`: `ok`
  - `_pxl8_uploaded_at`: timestamp
  - `_pxl8_source_hash`: `sha256:...`

**Check logs:**
```bash
docker exec pxl8-test-wordpress cat /var/www/html/wp-content/debug.log | grep PXL8
```

Look for:
- `[PXL8] [INFO] Starting PXL8 upload`
- `[PXL8] [INFO] PXL8 upload succeeded`

**Status:** ⏳ NOT TESTED

---

### Test 4: URL Rewriting

1. Go to **Media → Library**
2. Click on uploaded image
3. Click **View attachment page**
4. Right-click on image → **Inspect Element**
5. Check `<img>` tag `src` attribute

**Expected Result:**
```html
<img src="https://img.pxl8.ru/{imageId}?w=800&h=600&fit=cover&format=auto&quality=85">
```

**NOT:**
```html
<img src="http://localhost:8888/wp-content/uploads/...">
```

**Status:** ⏳ NOT TESTED

---

### Test 5: Quota Widget

1. Go to **Dashboard**
2. Look for **PXL8 Quota Usage** widget

**Expected Result:**
- Widget displays 3 progress bars:
  - **Storage:** used / limit (percentage)
  - **Bandwidth:** used / limit (percentage)
  - **Requests:** used / limit (percentage)
- Color coding:
  - Green (< 80%)
  - Orange (80-95%)
  - Red (> 95%)

3. Click **Refresh Quota** button

**Expected Result:**
- Button shows "Refreshing..." state
- After 1-2 seconds: "✅ Quota refreshed successfully"
- Page reloads automatically
- Updated quota data displayed

**Status:** ⏳ NOT TESTED

---

### Test 6: Error Handling (Failed Upload)

1. Go to **Settings → PXL8**
2. Change **API Key** to invalid value (e.g., `invalid_key`)
3. Click **Save Changes**
4. Go to **Media → Add New**
5. Upload another test image

**Expected Result:**
- Image uploads successfully to WordPress (upload NOT blocked)
- Original WordPress URL used (not PXL8 URL)
- Attachment metadata:
  - `_pxl8_status`: `failed`
  - `_pxl8_last_error`: error message

**Check logs:**
```bash
docker exec pxl8-test-wordpress cat /var/www/html/wp-content/debug.log | grep PXL8
```

Look for:
- `[PXL8] [ERROR] PXL8 upload failed`

**Status:** ⏳ NOT TESTED

---

## 📚 Detailed Testing Guide

For comprehensive testing with 13 test scenarios, see **[TESTING.md](./TESTING.md)**.

---

## 🔧 Troubleshooting

### Plugin doesn't appear in Plugins list

```bash
# Check plugin directory is mounted
docker exec pxl8-test-wordpress ls -la /var/www/html/wp-content/plugins/pxl8

# Check vendor directory exists
docker exec pxl8-test-wordpress ls -la /var/www/html/wp-content/plugins/pxl8/vendor
```

If `vendor/` is missing, regenerate autoloader:

```bash
cd /Users/chefbot/RiderProjects/pxl8/wordpress-plugin
docker run --rm -v "$(pwd)":/app -w /app composer:latest dump-autoload
```

### "Composer dependencies are missing" error

**Fixed in commit 36c5f69.** If you still see this:

```bash
# Regenerate autoloader
cd /Users/chefbot/RiderProjects/pxl8/wordpress-plugin
docker run --rm -v "$(pwd)":/app -w /app composer:latest dump-autoload

# Restart WordPress container
docker-compose -f docker-compose.test.yml restart wordpress
```

### Settings page not accessible

Check that SettingsPage is initialized:

```bash
docker exec pxl8-test-wordpress wp --allow-root plugin list --status=active
```

If plugin not active:

```bash
docker exec pxl8-test-wordpress wp --allow-root plugin activate pxl8
```

### Test Connection fails

1. Check API key is correct
2. Check network connectivity:

```bash
docker exec pxl8-test-wordpress curl -I https://img.pxl8.ru
```

3. Check PHP cURL extension:

```bash
docker exec pxl8-test-wordpress php -m | grep curl
```

---

## 🏗️ Architecture

```
pxl8-wordpress-plugin/
├── pxl8.php                        # Plugin bootstrap
├── composer.json                   # Dependencies + autoloader config
├── docker-compose.test.yml         # Docker test environment
├── TESTING.md                      # Comprehensive test guide
├── includes/
│   ├── Plugin.php                  # Main plugin class
│   ├── Admin/
│   │   ├── SettingsPage.php       # Settings UI + Test Connection
│   │   └── QuotaWidget.php        # Dashboard quota widget
│   ├── Media/
│   │   ├── UploadHandler.php      # Auto-optimize on upload
│   │   └── UrlRewriter.php        # Frontend URL rewriting
│   ├── Sdk/
│   │   └── ClientFactory.php      # PXL8 SDK client factory
│   └── Storage/
│       ├── Options.php             # WordPress options wrapper
│       └── AttachmentMeta.php      # Attachment metadata wrapper
├── assets/
│   ├── css/admin.css               # Admin styles
│   └── js/admin.js                 # Test Connection AJAX
├── vendor/
│   ├── autoload.php                # Composer autoloader
│   └── pxl8/sdk-php/               # PXL8 SDK (copied from ../pxl8-sdk-php)
└── uninstall.php                   # Complete data cleanup on uninstall
```

---

## 🔌 API Integration

Uses [pxl8-sdk-php](../pxl8-sdk-php) for all API calls:

```php
// Create client
$client = $clientFactory->create($apiKey, $baseUrl);

// Test connection
$tenant = $client->getTenant();

// Upload image
$result = $client->upload($filePath);
// Returns: ['imageId' => 'uuid', 'url' => 'https://...']

// Generate transform URL
$url = $client->getUrl($imageId, [
    'w' => 800,
    'h' => 600,
    'fit' => 'cover',
    'format' => 'auto',
    'quality' => 85
]);

// Get quota usage
$usage = $client->getUsage();
// Returns: ['storage' => [...], 'bandwidth' => [...], 'requests' => [...]]
```

---

## 🪝 WordPress Hooks

### Actions

| Hook | Description | File |
|------|-------------|------|
| `plugins_loaded` | Initialize plugin | pxl8.php:38 |
| `admin_menu` | Register settings page | SettingsPage.php:39 |
| `admin_init` | Register settings | SettingsPage.php:40 |
| `wp_dashboard_setup` | Add quota widget | QuotaWidget.php:23 |
| `wp_generate_attachment_metadata` | Auto-optimize on upload | UploadHandler.php:28 |
| `wp_ajax_pxl8_test_connection` | Test Connection AJAX | SettingsPage.php:47 |
| `wp_ajax_pxl8_refresh_quota` | Refresh Quota AJAX | QuotaWidget.php:24 |

### Filters

| Hook | Description | File |
|------|-------------|------|
| `plugin_action_links_pxl8/pxl8.php` | Add Settings link | SettingsPage.php:44 |
| `wp_get_attachment_url` | Replace image URLs | UrlRewriter.php:30 |
| `wp_get_attachment_image_src` | Replace URLs with dimensions | UrlRewriter.php:31 |
| `wp_calculate_image_srcset` | Replace srcset sources | UrlRewriter.php:32 |

---

## 🛠️ Development Commands

### Docker Management

```bash
# Start containers
docker-compose -f docker-compose.test.yml up -d

# Stop containers
docker-compose -f docker-compose.test.yml down

# View logs
docker-compose -f docker-compose.test.yml logs -f wordpress

# Restart WordPress (after code changes)
docker-compose -f docker-compose.test.yml restart wordpress

# Remove all containers + volumes (clean slate)
docker-compose -f docker-compose.test.yml down -v
```

### WordPress CLI (wp-cli)

```bash
# Activate plugin
docker exec pxl8-test-wordpress wp --allow-root plugin activate pxl8

# Deactivate plugin
docker exec pxl8-test-wordpress wp --allow-root plugin deactivate pxl8

# List active plugins
docker exec pxl8-test-wordpress wp --allow-root plugin list --status=active

# Check options
docker exec pxl8-test-wordpress wp --allow-root option get pxl8_base_url
docker exec pxl8-test-wordpress wp --allow-root option list --search="pxl8_*"

# View debug log
docker exec pxl8-test-wordpress cat /var/www/html/wp-content/debug.log | grep PXL8
```

### Composer

```bash
# Regenerate autoloader
docker run --rm -v "$(pwd)":/app -w /app composer:latest dump-autoload

# Install dependencies (if needed)
docker run --rm -v "$(pwd)":/app -w /app composer:latest install
```

---

## 📦 Installation (For Production - Not Ready Yet)

⚠️ **DO NOT USE IN PRODUCTION YET** - Testing in progress.

Once testing is complete:

1. Download latest release from GitHub
2. Extract to `wp-content/plugins/pxl8`
3. Run `composer install` in plugin directory
4. Activate in WordPress Admin → Plugins

---

## 📝 Next Steps

### Immediate (Session 8)

1. ✅ **Test Connection** with valid API key
2. ✅ **Upload Test Image** and verify PXL8 metadata
3. ✅ **Verify URL Rewriting** on frontend
4. ✅ **Test Quota Widget** display
5. ✅ **Test Error Handling** (invalid API key)

### Future (Phase 10+)

- 📊 Analytics dashboard
- 🔄 Bulk optimize existing images
- 🎨 Visual image editor
- 📱 Mobile app integration
- 🌍 Multi-tenant support

---

## 📄 Documentation

- **Technical Spec:** [TECHNICAL_SPEC.md](./TECHNICAL_SPEC.md)
- **Testing Guide:** [TESTING.md](./TESTING.md)
- **API Docs:** https://docs.pxl8.ru

---

## 📞 Support

- **Email:** support@pxl8.ru
- **Issues:** https://github.com/GeorgyBatalov/pxl8-wordpress-plugin/issues
- **Docs:** https://docs.pxl8.ru

---

## 📜 License

MIT License - see LICENSE file for details.

---

**Made with ❤️ by the PXL8 team**
