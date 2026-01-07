# PXL8 WordPress Plugin - Testing Guide

Manual smoke testing guide for Docker test environment.

## Prerequisites

- Docker Desktop installed and running
- PXL8 API key (test or production)
- pxl8-sdk-php directory at `../pxl8-sdk-php` (sibling directory)

## Step 1: Install Dependencies

```bash
cd /Users/chefbot/RiderProjects/pxl8/wordpress-plugin

# Copy SDK files to vendor directory
cp -r ../pxl8-sdk-php vendor/pxl8/sdk-php
```

This will copy the `pxl8-sdk-php` dependency into the plugin's vendor directory.

## Step 2: Start Docker Test Environment

```bash
# Start WordPress + MySQL
docker-compose -f docker-compose.test.yml up -d

# Wait for containers to be ready (30-60 seconds)
docker-compose -f docker-compose.test.yml ps
```

You should see:
- `pxl8-test-wordpress` (port 8888)
- `pxl8-test-db` (MySQL)

## Step 3: Initial WordPress Setup

1. Open browser: http://localhost:8888
2. Select language: **English**
3. Click **Let's go!**
4. Database connection details (should be pre-configured):
   - Database Name: `wordpress`
   - Username: `wordpress`
   - Password: `wordpress_password`
   - Database Host: `db`
   - Table Prefix: `wp_`
5. Click **Submit**
6. Click **Run the installation**
7. Site setup:
   - Site Title: **PXL8 Test Site**
   - Username: **admin**
   - Password: **admin123** (or strong password)
   - Email: your email
   - Search Engine Visibility: **unchecked**
8. Click **Install WordPress**
9. Log in with credentials

## Step 4: Activate Plugin

1. Go to **Plugins** → **Installed Plugins**
2. Find **PXL8 Image Optimization**
3. Click **Activate**

**Expected Result:**
- Plugin activates successfully
- No error messages
- "Plugin activated" notice appears

## Step 5: Configure Plugin Settings

1. Go to **Settings** → **PXL8**
2. Enter settings:
   - **Base URL**: `https://img.pxl8.ru` (default)
   - **API Key**: your PXL8 API key
   - **Enable Plugin**: ✅ checked
   - **Auto-Optimize**: ⬜ unchecked (default - OFF)
   - **Default Quality**: `85` (default)
   - **Default Format**: `auto` (default)
   - **Default Fit**: `cover` (default)
3. Click **Test Connection**

**Expected Result:**
- ✅ Success message: "Connected! Tenant: {tenant_name}"
- If fails: check API key, network connection

4. Click **Save Changes**

## Step 6: Test Auto-Optimize (Upload Handler)

1. Enable auto-optimize:
   - **Auto-Optimize**: ✅ checked
   - Click **Save Changes**

2. Go to **Media** → **Add New**

3. Upload a test image:
   - Use any JPG/PNG image (recommended: 500KB-2MB)
   - Drag & drop or click **Select Files**

4. Wait for upload to complete

5. Check attachment details:
   - Click on uploaded image
   - In right sidebar, scroll down
   - Look for custom fields (if visible)

**Expected Result:**
- Image uploads successfully
- Attachment has PXL8 metadata:
  - `_pxl8_image_id`: UUID (e.g., `a7f8e2d1-3c4b-5e6f-7a8b-9c0d1e2f3a4b`)
  - `_pxl8_status`: `ok`
  - `_pxl8_uploaded_at`: timestamp
  - `_pxl8_source_hash`: `sha256:...`

**Check logs** (if WP_DEBUG_LOG enabled):
```bash
docker exec pxl8-test-wordpress cat /var/www/html/wp-content/debug.log | grep PXL8
```

Look for:
- `[PXL8] [INFO] Starting PXL8 upload`
- `[PXL8] [INFO] PXL8 upload succeeded`

## Step 7: Test URL Rewriting

1. Go to **Media** → **Library**
2. Click on uploaded image
3. In right sidebar, click **View attachment page**

4. Right-click on image → **Inspect Element**
5. Check `<img>` tag `src` attribute

**Expected Result:**
- URL format: `https://img.pxl8.ru/{imageId}?w={width}&h={height}&fit=cover&format=auto&quality=85`
- NOT: `http://localhost:8888/wp-content/uploads/...`

**Test srcset:**
```html
<img
  src="https://img.pxl8.ru/{imageId}?w=800&h=600&fit=cover&format=auto&quality=85"
  srcset="
    https://img.pxl8.ru/{imageId}?w=300&fit=cover&format=auto&quality=85 300w,
    https://img.pxl8.ru/{imageId}?w=768&fit=cover&format=auto&quality=85 768w,
    https://img.pxl8.ru/{imageId}?w=1024&fit=cover&format=auto&quality=85 1024w
  "
/>
```

## Step 8: Test Quota Widget

1. Go to **Dashboard**
2. Look for **PXL8 Quota Usage** widget

**Expected Result:**
- Widget displays 3 progress bars:
  - **Storage**: used / limit (percentage)
  - **Bandwidth**: used / limit (percentage)
  - **Requests**: used / limit (percentage)
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

## Step 9: Test Failed Upload (Error Handling)

1. Disable plugin or use invalid API key:
   - Go to **Settings** → **PXL8**
   - Change **API Key** to invalid value (e.g., `invalid_key`)
   - Click **Save Changes**

2. Upload another test image:
   - Go to **Media** → **Add New**
   - Upload image

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

3. Restore valid API key:
   - Go to **Settings** → **PXL8**
   - Restore correct **API Key**
   - Click **Save Changes**

## Step 10: Test Plugin Deactivation

1. Go to **Plugins** → **Installed Plugins**
2. Find **PXL8 Image Optimization**
3. Click **Deactivate**

**Expected Result:**
- Plugin deactivates successfully
- Transient cache cleared (`pxl8_quota_cache` deleted)
- Options and metadata preserved (not deleted)
- Existing PXL8 URLs still work (data not removed)

**Check logs:**
```bash
docker exec pxl8-test-wordpress cat /var/www/html/wp-content/debug.log | grep PXL8 | tail -5
```

Look for:
- `[PXL8] Plugin deactivated - transient cache cleared`

## Step 11: Test Plugin Reactivation

1. Go to **Plugins** → **Installed Plugins**
2. Find **PXL8 Image Optimization**
3. Click **Activate**

**Expected Result:**
- Plugin activates successfully
- Settings preserved (API key, options intact)
- Quota widget reappears on dashboard
- URL rewriting resumes

## Step 12: Test Plugin Uninstall (Optional - DESTRUCTIVE)

⚠️ **WARNING**: This will delete all PXL8 data permanently!

1. Go to **Plugins** → **Installed Plugins**
2. Find **PXL8 Image Optimization**
3. Click **Deactivate** (if active)
4. Click **Delete**
5. Confirm deletion

**Expected Result:**
- Plugin files deleted
- `uninstall.php` executed
- All PXL8 options deleted:
  - `pxl8_base_url`
  - `pxl8_api_key`
  - `pxl8_enabled`
  - `pxl8_auto_optimize`
  - `pxl8_default_quality`
  - `pxl8_default_format`
  - `pxl8_default_fit`
- All transients deleted
- All attachment metadata deleted:
  - `_pxl8_image_id`
  - `_pxl8_status`
  - `_pxl8_last_error`
  - `_pxl8_uploaded_at`
  - `_pxl8_last_sync_at`
  - `_pxl8_source_hash`

**Check logs:**
```bash
docker exec pxl8-test-wordpress cat /var/www/html/wp-content/debug.log | grep PXL8 | tail -5
```

Look for:
- `[PXL8] Plugin uninstalled - all data deleted`

**Verify cleanup:**
- Go to **Media** → **Library**
- Click on previously uploaded image
- Image URL reverted to original WordPress URL (not PXL8)

## Step 13: Cleanup Test Environment

```bash
# Stop and remove containers
docker-compose -f docker-compose.test.yml down

# Optional: Remove volumes (delete all WordPress data)
docker-compose -f docker-compose.test.yml down -v
```

## Troubleshooting

### Plugin doesn't appear in Plugins list

```bash
# Check plugin directory is mounted
docker exec pxl8-test-wordpress ls -la /var/www/html/wp-content/plugins/pxl8

# Check composer dependencies
docker exec pxl8-test-wordpress ls -la /var/www/html/wp-content/plugins/pxl8/vendor
```

If `vendor/` is missing:
```bash
cd /Users/chefbot/RiderProjects/pxl8/wordpress-plugin
composer install
```

### SDK dependencies missing

```bash
# Copy SDK files on host (macOS)
cd /Users/chefbot/RiderProjects/pxl8/wordpress-plugin
cp -r ../pxl8-sdk-php vendor/pxl8/sdk-php

# Restart WordPress container
docker-compose -f docker-compose.test.yml restart wordpress
```

### Debug logs not appearing

1. Go to `wp-config.php`:
```bash
docker exec pxl8-test-wordpress cat /var/www/html/wp-config.php | grep WP_DEBUG
```

2. If not set, add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
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

## Success Criteria

✅ **Plugin Installation:**
- Plugin appears in Plugins list
- Activates without errors

✅ **Configuration:**
- Settings page loads correctly
- Test Connection succeeds
- Settings save successfully

✅ **Upload Handler:**
- Images upload successfully
- PXL8 metadata stored correctly
- Failed uploads don't break WordPress

✅ **URL Rewriting:**
- Images use PXL8 URLs (not WordPress URLs)
- srcset generated correctly
- Failed uploads fallback to original URLs

✅ **Quota Widget:**
- Widget displays on dashboard
- Progress bars show correct data
- Refresh button works

✅ **Deactivation:**
- Plugin deactivates cleanly
- Transient cache cleared
- Settings preserved

✅ **Uninstall:**
- All PXL8 data deleted
- URLs revert to original
- No orphaned data

## Next Steps

If all tests pass:
- ✅ Plugin ready for production
- ✅ Can package for WordPress Plugin Directory
- ✅ Can deploy to live WordPress site

If tests fail:
- Review error logs
- Check `docker-compose.test.yml` configuration
- Verify Composer dependencies
- Report issues to development team
