# WordPress Plugin MVP - Technical Specification

> **Phase:** 9
> **Duration:** 5-7 days
> **Version:** 1.0.1 (Pragmatic MVP)
> **Last Updated:** 8 January 2026

---

## 0. Goals & Scope

### MVP Goals

1. **Settings & Test Connection**
   - User enters **Base URL** (tenant CNAME, e.g., `https://img.example.com`) + **API Key**
   - Click "Test Connection" → shows "OK" or error
   - Settings page with configuration options

2. **Auto-Optimize on Upload** (⚠️ **OFF by default**)
   - When user uploads image to Media Library **AND** auto-optimize is enabled
   - Plugin sends original to PXL8 (via `pxl8-sdk-php`)
   - Stores association (`imageId` ↔ `attachment_id`)
   - **Default: Disabled** (prevents unexpected quota consumption)

3. **URL Rewriting on Frontend**
   - Images displayed via WordPress APIs get PXL8 URLs with transformations
   - Original WordPress URLs replaced with PXL8 CDN URLs
   - Responsive images (srcset) fully supported
   - **⚠️ Only Media Library images supported** (no HTML content rewriting in v1)

4. **Quota Monitoring**
   - Admin dashboard shows current plan + limits + usage
   - "Refresh" button to update quota data

5. **Clean Deactivation/Uninstall**
   - Deactivate: disable hooks, clear transients
   - Uninstall: optionally remove options + metadata

### Explicit Boundaries (What We DON'T Do in v1)

- ❌ Auto-retry failed uploads (manual retry only)
- ❌ HTML content rewriting in `post_content` (only Media Library URLs)
- ❌ Non-Media Library uploads (themes, plugins with custom upload handlers)
- ❌ Page builders deep integration (Elementor, etc.) - later
- ❌ Advanced WebP/AVIF modes, LQIP, adaptive images - Phase 10+
- ❌ Multisite support - Phase 11+
- ❌ Batch migration of existing images - Phase 10
- ❌ Background upload queue (synchronous upload only)
- ❌ CDN auto-enable, DDoS protection, global edge logic (Phase 12+)

---

## 1. URL Format (Asset ID-Based)

**Strategy:** Asset ID-based URLs (most reliable)

### PXL8 API Response

When uploading via `POST /api/v1/images`:

```json
{
  "imageId": "a7f8e2d1-3c4b-5e6f-7a8b-9c0d1e2f3a4b",
  "originalUrl": "https://img.pxl8.ru/img/original/a7f8e2d1-3c4b-5e6f-7a8b-9c0d1e2f3a4b.jpg",
  "size": 1048576,
  "format": "jpeg",
  "width": 1920,
  "height": 1080
}
```

### WordPress Metadata

Store in `wp_postmeta` (per attachment):

```php
_pxl8_image_id     => "a7f8e2d1-3c4b-5e6f-7a8b-9c0d1e2f3a4b"  // UUID
_pxl8_status       => "ok" | "pending" | "failed"
_pxl8_last_error   => "Error message (truncated to 255 chars)"
_pxl8_uploaded_at  => 1704700800  // Unix timestamp
_pxl8_last_sync_at => 1704700800  // Unix timestamp (future re-sync, support)
_pxl8_source_hash  => "sha256:..." // Optional: heuristic idempotency (not strict deduplication)
```

**Note on Idempotency:**
- Hash is used as **heuristic idempotency**, not strict deduplication
- WordPress may re-save files (same image, different bytes) due to metadata stripping
- Hash is best-effort to avoid duplicate uploads

### Frontend URL Format

**Transform URL:**
```
https://{baseUrl}/img/transform/{imageId}.{format}?w={w}&h={h}&fit={fit}&q={q}
```

**Example:**
```
https://img.example.com/img/transform/a7f8e2d1-3c4b-5e6f.webp?w=800&h=600&fit=cover&q=85
```

**Original URL:**
```
https://{baseUrl}/img/original/{imageId}.{format}
```

---

## 2. Plugin Structure

### File Layout

```
wp-content/plugins/pxl8/
├── pxl8.php                          # Bootstrap (plugin header, constants, init)
├── uninstall.php                     # Cleanup on uninstall
├── composer.json                     # Dependencies (pxl8-sdk-php)
├── README.txt                        # WordPress.org plugin readme
├── assets/
│   ├── css/
│   │   └── admin.css                # Admin styles
│   └── js/
│       └── admin.js                 # Test Connection AJAX
├── includes/
│   ├── Plugin.php                   # Main plugin class (init hooks)
│   ├── Admin/
│   │   ├── SettingsPage.php        # Settings page UI
│   │   └── QuotaWidget.php          # Quota display widget
│   ├── Media/
│   │   ├── UploadHandler.php       # React to upload, send to PXL8
│   │   └── UrlRewriter.php         # Filters for URL rewriting
│   ├── Sdk/
│   │   └── ClientFactory.php       # Create Pxl8Client instances
│   ├── Storage/
│   │   ├── Options.php             # WP options management
│   │   └── AttachmentMeta.php      # Attachment metadata
│   └── Diagnostics/
│       └── Logger.php               # Logging (WP_DEBUG_LOG)
└── vendor/                          # Composer dependencies (pxl8-sdk-php)
```

### WordPress Options

**Option Group:** `pxl8_settings`

| Option Key | Type | Default | Description |
|------------|------|---------|-------------|
| `pxl8_base_url` | string | `https://img.pxl8.ru` | Base URL (tenant CNAME) |
| `pxl8_api_key` | string (secret) | `''` | API key (masked in UI, never logged) |
| `pxl8_enabled` | bool | `false` | Enable/disable plugin |
| `pxl8_auto_optimize` | bool | `false` | ⚠️ Auto-optimize on upload (OFF by default) |
| `pxl8_default_quality` | int | `85` | Default quality (1-100) |
| `pxl8_default_format` | string | `auto` | auto, webp, avif, jpg, png |
| `pxl8_default_fit` | string | `cover` | cover, contain, fill, crop |

**Security Policy:**
- ✅ API key stored **only** via `wp_options` (WordPress Options API)
- ✅ API key is **never logged** (even in debug mode)
- ✅ API key is **masked** in UI (shows first 15 chars + `***`)

### Attachment Metadata (per attachment)

| Meta Key | Type | Description |
|----------|------|-------------|
| `_pxl8_image_id` | string (UUID) | PXL8 image ID |
| `_pxl8_status` | string | `ok`, `pending`, `failed` |
| `_pxl8_last_error` | string | Error message (truncated) |
| `_pxl8_uploaded_at` | int | Unix timestamp (initial upload) |
| `_pxl8_last_sync_at` | int | Unix timestamp (last sync, for future re-sync) |
| `_pxl8_source_hash` | string | `sha256:...` (heuristic idempotency) |

---

## 3. Hooks & Behavior

### 3.1 URL Rewriting (Frontend)

**Scope:** Only Media Library images (attached to posts via WordPress Media APIs)

**Explicitly NOT Rewritten:**
- HTML content in `post_content` (hardcoded `<img>` tags)
- Images uploaded by themes/plugins outside Media Library
- Non-Media Library file handlers

---

#### Priority Hooks

**1. `wp_get_attachment_url` (Priority: 10)**

**Purpose:** Replace base URL for single attachments

```php
add_filter('wp_get_attachment_url', [$urlRewriter, 'filterAttachmentUrl'], 10, 2);

public function filterAttachmentUrl($url, $attachment_id) {
    // Skip if plugin disabled OR not image OR no _pxl8_image_id OR status != 'ok'
    if (!$this->shouldRewrite($attachment_id)) {
        return $url; // Return ORIGINAL URL (failed uploads keep WordPress URL)
    }

    $imageId = get_post_meta($attachment_id, '_pxl8_image_id', true);
    $baseUrl = get_option('pxl8_base_url');
    $format = $this->getOutputFormat(); // auto, webp, etc.

    return "{$baseUrl}/img/transform/{$imageId}.{$format}?q=85";
}

private function shouldRewrite($attachment_id) {
    // 1. Plugin must be enabled
    if (!get_option('pxl8_enabled')) {
        return false;
    }

    // 2. Must have PXL8 image ID
    $imageId = get_post_meta($attachment_id, '_pxl8_image_id', true);
    if (empty($imageId)) {
        return false;
    }

    // 3. Status must be 'ok' (NOT 'failed' or 'pending')
    $status = get_post_meta($attachment_id, '_pxl8_status', true);
    if ($status !== 'ok') {
        return false; // Failed uploads keep original WordPress URL
    }

    return true;
}
```

**2. `wp_get_attachment_image_src` (Priority: 10)**

**Purpose:** Replace URL with width/height known

```php
add_filter('wp_get_attachment_image_src', [$urlRewriter, 'filterImageSrc'], 10, 4);

public function filterImageSrc($image, $attachment_id, $size, $icon) {
    if (!$this->shouldRewrite($attachment_id) || $icon) {
        return $image;
    }

    // $image = [url, width, height, is_intermediate]
    $imageId = get_post_meta($attachment_id, '_pxl8_image_id', true);
    $w = $image[1] ?? null;
    $h = $image[2] ?? null;

    $url = $this->buildTransformUrl($imageId, ['w' => $w, 'h' => $h]);

    return [$url, $w, $h, $image[3]];
}
```

**3. `wp_calculate_image_srcset` (Priority: 10)**

**Purpose:** Replace srcset sources with PXL8 URLs

```php
add_filter('wp_calculate_image_srcset', [$urlRewriter, 'filterSrcset'], 10, 5);

public function filterSrcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    if (!$this->shouldRewrite($attachment_id)) {
        return $sources;
    }

    $imageId = get_post_meta($attachment_id, '_pxl8_image_id', true);

    foreach ($sources as $width => &$source) {
        $source['url'] = $this->buildTransformUrl($imageId, ['w' => $width]);
    }

    return $sources;
}
```

**4. `wp_get_attachment_image_attributes` (Priority: 10)**

**Purpose:** Ensure `src`, `srcset`, `sizes` are consistent

```php
add_filter('wp_get_attachment_image_attributes', [$urlRewriter, 'filterAttributes'], 10, 3);

public function filterAttributes($attr, $attachment, $size) {
    // Ensure all attributes use PXL8 URLs
    // Usually handled by previous filters, this is a safety net
    return $attr;
}
```

#### Transform Parameters Logic

| Scenario | Parameters |
|----------|-----------|
| Width + Height known | `w={w}&h={h}&fit=cover&q=85` |
| Width only (srcset) | `w={w}&fit=cover&q=85` |
| Height only | `h={h}&fit=cover&q=85` |
| No dimensions | `q=85` (original size) |

**Format:**
- Use `pxl8_default_format` option (auto, webp, avif, jpg, png)

**Quality:**
- Use `pxl8_default_quality` option (default: 85)

**Fit Mode:**
- Use `pxl8_default_fit` option (default: cover)

#### Exclusions (Do NOT Rewrite)

- Admin area (`is_admin()` returns true)
- Login page (`wp-login.php`)
- Preview mode (`is_preview()`)
- REST API admin requests (`/wp-json/wp/v2/...` with `_method=PUT`)

---

### 3.2 Auto-Optimize on Upload

**Scope:** Only Media Library uploads (via `wp_generate_attachment_metadata` hook)

**Explicitly NOT Supported in v1:**
- Theme/plugin uploads outside Media Library
- Custom upload handlers (e.g., `wp_handle_upload` bypassed)
- Bulk image imports (Phase 10+)

---

#### Hook: `wp_generate_attachment_metadata`

**Why this hook:**
- File is already uploaded to WordPress
- Image dimensions are available
- Metadata is being generated (perfect timing)

```php
add_filter('wp_generate_attachment_metadata', [$uploadHandler, 'handleUpload'], 10, 2);

public function handleUpload($metadata, $attachment_id) {
    // 1. Check if auto-optimize enabled
    if (!get_option('pxl8_auto_optimize', true)) {
        return $metadata;
    }

    // 2. Check if attachment is image
    if (!wp_attachment_is_image($attachment_id)) {
        return $metadata;
    }

    // 3. Check if already processed
    if (get_post_meta($attachment_id, '_pxl8_image_id', true)) {
        return $metadata; // Already optimized
    }

    // 4. Get file path
    $filePath = get_attached_file($attachment_id);
    if (!file_exists($filePath)) {
        return $metadata;
    }

    // 5. Upload to PXL8
    try {
        $client = $this->clientFactory->create();
        $response = $client->upload($filePath);

        // 6. Store metadata
        update_post_meta($attachment_id, '_pxl8_image_id', $response['imageId']);
        update_post_meta($attachment_id, '_pxl8_status', 'ok');
        update_post_meta($attachment_id, '_pxl8_uploaded_at', time());

        // Optional: store hash for idempotency
        $hash = 'sha256:' . hash_file('sha256', $filePath);
        update_post_meta($attachment_id, '_pxl8_source_hash', $hash);

    } catch (\Pxl8\Exceptions\Pxl8Exception $e) {
        // 7. Handle error (non-fatal)
        update_post_meta($attachment_id, '_pxl8_status', 'failed');
        update_post_meta($attachment_id, '_pxl8_last_error', substr($e->getMessage(), 0, 255));

        // Log error
        $this->logger->error('PXL8 upload failed', [
            'attachment_id' => $attachment_id,
            'error' => $e->getMessage(),
            'status_code' => $e->getStatusCode(),
        ]);

        // ⚠️ IMPORTANT: Failed uploads do NOT break WordPress
        // - WordPress attachment is created normally
        // - Original WordPress URL remains active (not replaced)
        // - Retry is MANUAL only (no auto-retry in v1)
    }

    return $metadata; // Don't break WordPress upload
}
```

**Error Handling Policy:**

When upload to PXL8 fails (`_pxl8_status = 'failed'`):

1. ✅ **WordPress upload continues normally** (non-fatal error)
2. ✅ **Original URL remains active** (URL rewriting skips failed uploads)
3. ✅ **Error logged** with `_pxl8_last_error` metadata
4. ✅ **Retry is MANUAL only** (admin must re-upload or manually trigger retry)
5. ❌ **NO automatic retry** in v1 (prevents infinite loops, quota waste)

#### Idempotency

**Problem:** User might re-upload same image
**Solution:** Check `_pxl8_source_hash` before uploading

```php
$existingHash = get_post_meta($attachment_id, '_pxl8_source_hash', true);
$newHash = 'sha256:' . hash_file('sha256', $filePath);

if ($existingHash === $newHash) {
    // Same file, skip upload
    return $metadata;
}
```

#### Large Files (Background Upload)

**MVP:** Synchronous upload (simple)

**Future (Phase 10):**
- If file > 10 MB → queue background job
- Use WP-Cron or Action Scheduler
- Set `_pxl8_status = 'pending'`

---

### 3.3 Settings Page + Test Connection

#### Settings Page UI

**Location:** `Settings → PXL8`

**Fields:**

```php
<form method="post" action="options.php">
    <?php settings_fields('pxl8_settings'); ?>

    <!-- Base URL -->
    <input type="text"
           name="pxl8_base_url"
           value="<?php echo esc_attr(get_option('pxl8_base_url')); ?>"
           placeholder="https://img.example.com">

    <!-- API Key (password field) -->
    <input type="password"
           name="pxl8_api_key"
           value="<?php echo esc_attr(get_option('pxl8_api_key')); ?>">

    <!-- Enable/Disable -->
    <input type="checkbox"
           name="pxl8_enabled"
           <?php checked(get_option('pxl8_enabled'), true); ?>>

    <!-- Auto-Optimize on Upload -->
    <input type="checkbox"
           name="pxl8_auto_optimize"
           <?php checked(get_option('pxl8_auto_optimize'), true); ?>>

    <!-- Default Quality -->
    <input type="number"
           name="pxl8_default_quality"
           value="<?php echo esc_attr(get_option('pxl8_default_quality', 85)); ?>"
           min="1" max="100">

    <!-- Default Format -->
    <select name="pxl8_default_format">
        <option value="auto">Auto</option>
        <option value="webp">WebP</option>
        <option value="avif">AVIF</option>
        <option value="jpg">JPEG</option>
        <option value="png">PNG</option>
    </select>

    <!-- Test Connection Button -->
    <button type="button" id="pxl8-test-connection" class="button">
        Test Connection
    </button>
    <div id="pxl8-test-result"></div>

    <?php submit_button(); ?>
</form>
```

#### Test Connection (AJAX)

**JavaScript (`assets/js/admin.js`):**

```javascript
jQuery('#pxl8-test-connection').on('click', function() {
    const $button = jQuery(this);
    const $result = jQuery('#pxl8-test-result');

    $button.prop('disabled', true).text('Testing...');

    jQuery.post(ajaxurl, {
        action: 'pxl8_test_connection',
        nonce: pxl8_admin.nonce,
        base_url: jQuery('input[name="pxl8_base_url"]').val(),
        api_key: jQuery('input[name="pxl8_api_key"]').val()
    }, function(response) {
        if (response.success) {
            $result.html('<span class="success">✅ ' + response.data.message + '</span>');
        } else {
            $result.html('<span class="error">❌ ' + response.data.message + '</span>');
        }
    }).always(function() {
        $button.prop('disabled', false).text('Test Connection');
    });
});
```

**PHP Handler:**

```php
add_action('wp_ajax_pxl8_test_connection', [$this, 'handleTestConnection']);

public function handleTestConnection() {
    // 1. Verify nonce
    check_ajax_referer('pxl8_admin_nonce', 'nonce');

    // 2. Check capability
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    // 3. Get credentials
    $baseUrl = sanitize_text_field($_POST['base_url'] ?? '');
    $apiKey = sanitize_text_field($_POST['api_key'] ?? '');

    // 4. Create client
    try {
        $client = new \Pxl8\Pxl8Client($apiKey, ['baseUrl' => $baseUrl]);

        // 5. Test connection (GET /api/tenants/me)
        $tenant = $client->getTenant();

        wp_send_json_success([
            'message' => sprintf('Connected! Tenant: %s', $tenant['name'])
        ]);

    } catch (\Pxl8\Exceptions\AuthenticationException $e) {
        wp_send_json_error(['message' => '401/403: Invalid API key']);
    } catch (\Pxl8\Exceptions\Pxl8Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
```

---

### 3.4 Quota Monitoring

#### UI Widget (in Settings Page)

```php
<div class="pxl8-quota-widget">
    <h3>Usage & Quota</h3>

    <p><strong>Plan:</strong> Free Tier</p>

    <div class="quota-item">
        <label>Storage:</label>
        <progress max="1073741824" value="<?php echo $usage['storage']['used']; ?>"></progress>
        <span><?php echo size_format($usage['storage']['used']); ?> / 1 GB</span>
    </div>

    <div class="quota-item">
        <label>Bandwidth:</label>
        <progress max="10737418240" value="<?php echo $usage['bandwidth']['used']; ?>"></progress>
        <span><?php echo size_format($usage['bandwidth']['used']); ?> / 10 GB</span>
    </div>

    <div class="quota-item">
        <label>Transforms:</label>
        <progress max="10000" value="<?php echo $usage['transforms']['used']; ?>"></progress>
        <span><?php echo number_format($usage['transforms']['used']); ?> / 10,000</span>
    </div>

    <button type="button" id="pxl8-refresh-quota" class="button">Refresh</button>
    <p class="description">Last updated: <?php echo human_time_diff($lastUpdate); ?> ago</p>
</div>
```

#### Caching Strategy

**Transient Cache:** 5 minutes (300 seconds)

```php
public function getUsageData() {
    $cacheKey = 'pxl8_usage_data';

    // Try cache first
    $cached = get_transient($cacheKey);
    if ($cached !== false) {
        return $cached;
    }

    // Fetch from API
    try {
        $client = $this->clientFactory->create();
        $usage = $client->getUsage();

        // Cache for 5 minutes
        set_transient($cacheKey, $usage, 300);

        return $usage;

    } catch (\Pxl8\Exceptions\Pxl8Exception $e) {
        $this->logger->error('Failed to fetch usage data', ['error' => $e->getMessage()]);
        return null;
    }
}
```

#### Refresh Button (AJAX)

```javascript
jQuery('#pxl8-refresh-quota').on('click', function() {
    jQuery.post(ajaxurl, {
        action: 'pxl8_refresh_quota',
        nonce: pxl8_admin.nonce
    }, function(response) {
        if (response.success) {
            location.reload(); // Reload page to show fresh data
        }
    });
});
```

```php
add_action('wp_ajax_pxl8_refresh_quota', [$this, 'handleRefreshQuota']);

public function handleRefreshQuota() {
    check_ajax_referer('pxl8_admin_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error();
    }

    // Clear cache
    delete_transient('pxl8_usage_data');

    wp_send_json_success();
}
```

---

### 3.5 Deactivation / Uninstall

#### Deactivation Hook

**File:** `pxl8.php`

```php
register_deactivation_hook(__FILE__, 'pxl8_deactivate');

function pxl8_deactivate() {
    // 1. Clear transient cache
    delete_transient('pxl8_usage_data');

    // 2. Unschedule cron jobs (if any)
    wp_clear_scheduled_hook('pxl8_background_upload');

    // 3. Do NOT delete options/metadata (user might reactivate)
}
```

#### Uninstall Hook

**File:** `uninstall.php`

```php
// Only run if WP_UNINSTALL_PLUGIN is defined
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user wants to delete data
$deleteData = get_option('pxl8_delete_data_on_uninstall', false);

if ($deleteData) {
    // Delete all options
    delete_option('pxl8_base_url');
    delete_option('pxl8_api_key');
    delete_option('pxl8_enabled');
    delete_option('pxl8_auto_optimize');
    delete_option('pxl8_default_quality');
    delete_option('pxl8_default_format');
    delete_option('pxl8_default_fit');

    // Delete all attachment metadata
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_pxl8_%'");

    // Clear transients
    delete_transient('pxl8_usage_data');
}
```

---

## 4. SDK Integration (pxl8-sdk-php)

### Composer Dependency

**File:** `composer.json`

```json
{
  "name": "pxl8/wordpress-plugin",
  "require": {
    "php": ">=7.4",
    "pxl8/sdk-php": "^1.0"
  },
  "autoload": {
    "psr-4": {
      "Pxl8\\WordPress\\": "includes/"
    }
  }
}
```

### Client Factory

**File:** `includes/Sdk/ClientFactory.php`

```php
namespace Pxl8\WordPress\Sdk;

use Pxl8\Pxl8Client;

class ClientFactory {
    public function create() {
        $apiKey = get_option('pxl8_api_key');
        $baseUrl = get_option('pxl8_base_url', 'https://img.pxl8.ru');

        if (empty($apiKey)) {
            throw new \Exception('PXL8 API key not configured');
        }

        return new Pxl8Client($apiKey, [
            'baseUrl' => $baseUrl,
            'maxRetries' => 3,
            'retryDelay' => 1000,
        ]);
    }
}
```

### Usage Examples

**Upload Image:**
```php
$client = $this->clientFactory->create();
$response = $client->upload($filePath);
// Returns: ['imageId' => UUID, 'originalUrl' => ..., 'size' => ..., ...]
```

**Build Transform URL:**
```php
$url = $client->getUrl($imageId, [
    'w' => 800,
    'h' => 600,
    'fit' => 'cover',
    'q' => 85,
    'format' => 'webp',
]);
// Returns: "https://img.pxl8.ru/img/transform/{imageId}.webp?w=800&h=600&fit=cover&q=85"
```

**Get Usage:**
```php
$usage = $client->getUsage();
// Returns: ['currentPeriod' => ['storage' => [...], 'bandwidth' => [...], 'transforms' => [...]]]
```

**Get Tenant Info (Test Connection):**
```php
$tenant = $client->getTenant();
// Returns: ['id' => ..., 'name' => ..., 'email' => ...]
```

---

## 5. Logging & Diagnostics

### Logger Implementation

**File:** `includes/Diagnostics/Logger.php`

```php
namespace Pxl8\WordPress\Diagnostics;

class Logger {
    public function error($message, $context = []) {
        if (!WP_DEBUG_LOG) {
            return;
        }

        $formatted = sprintf(
            '[PXL8] %s: %s | Context: %s',
            $message,
            json_encode($context),
            wp_json_encode($context)
        );

        error_log($formatted);
    }

    public function info($message, $context = []) {
        if (!WP_DEBUG || !WP_DEBUG_LOG) {
            return;
        }

        error_log("[PXL8] INFO: $message");
    }
}
```

### Diagnostics Page (Settings Tab)

**Info to Display:**
- Plugin version
- PHP version
- WordPress version
- Base URL (configured)
- API key status (masked: `pxl8_prod_...***`)
- Last quota fetch timestamp
- Recent errors (last 10 failed uploads)

```php
public function renderDiagnostics() {
    ?>
    <h3>Diagnostics</h3>
    <table class="widefat">
        <tr>
            <th>Plugin Version</th>
            <td><?php echo PXL8_VERSION; ?></td>
        </tr>
        <tr>
            <th>PHP Version</th>
            <td><?php echo PHP_VERSION; ?></td>
        </tr>
        <tr>
            <th>WordPress Version</th>
            <td><?php echo get_bloginfo('version'); ?></td>
        </tr>
        <tr>
            <th>Base URL</th>
            <td><?php echo esc_html(get_option('pxl8_base_url')); ?></td>
        </tr>
        <tr>
            <th>API Key</th>
            <td><?php echo $this->maskApiKey(get_option('pxl8_api_key')); ?></td>
        </tr>
    </table>

    <h4>Recent Errors (Last 10)</h4>
    <?php $this->renderRecentErrors(); ?>
    <?php
}

private function maskApiKey($apiKey) {
    if (empty($apiKey)) {
        return '(not set)';
    }
    return substr($apiKey, 0, 15) . '***';
}
```

---

## 6. Testing Strategy

### 6.1 Local Development Environment

**Docker Compose Setup:**

```yaml
version: '3.8'
services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8000:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
    volumes:
      - ./wordpress-plugin:/var/www/html/wp-content/plugins/pxl8

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
      MYSQL_ROOT_PASSWORD: root
```

### 6.2 Manual Smoke Tests

**Checklist:**

1. **✅ Install & Activate**
   - Install plugin
   - Activate
   - Settings page appears under `Settings → PXL8`

2. **✅ Test Connection**
   - Enter Base URL + API Key
   - Click "Test Connection"
   - See "✅ Connected! Tenant: ..."

3. **✅ Upload Image**
   - Upload image to Media Library
   - Check attachment metadata: `_pxl8_image_id` exists
   - Verify `_pxl8_status = 'ok'`

4. **✅ Frontend URL Rewriting**
   - Insert image in post via Block Editor
   - Publish post
   - View post on frontend
   - Inspect `<img src="...">` → should contain `{baseUrl}/img/transform/...`
   - Inspect `srcset` → all URLs should contain `{baseUrl}/img/transform/...`

5. **✅ Quota Monitoring**
   - Go to Settings → PXL8
   - See quota widget with storage/bandwidth/transforms
   - Click "Refresh" → data updates

6. **✅ Deactivate**
   - Deactivate plugin
   - View post → URLs revert to original WordPress URLs

7. **✅ Error Handling (401)**
   - Enter invalid API key
   - Click "Test Connection"
   - See error message: "❌ 401/403: Invalid API key"

8. **✅ Large File Upload**
   - Upload 20 MB image
   - Should not break WordPress (timeout handled gracefully)

### 6.3 Automated Tests (Simplified for v1)

**Goal:** Minimal viable testing - no over-engineering

**Required Tests:**

1. **✅ One Happy Path Integration Test** (PHPUnit + WP Test Suite)

```php
// tests/Integration/HappyPathTest.php
public function test_upload_and_rewrite_url() {
    // 1. Upload image to Media Library
    $attachment_id = $this->factory->attachment->create_upload_object(__DIR__ . '/fixtures/test.jpg');

    // 2. Simulate successful PXL8 upload
    update_post_meta($attachment_id, '_pxl8_image_id', 'test-image-id-123');
    update_post_meta($attachment_id, '_pxl8_status', 'ok');

    // 3. Verify URL rewriting
    $url = wp_get_attachment_url($attachment_id);

    $this->assertStringContainsString('img.example.com/img/transform/test-image-id-123', $url);
}
```

2. **✅ One Failure Test** (PHPUnit)

```php
// tests/Integration/FailureHandlingTest.php
public function test_failed_upload_keeps_original_url() {
    // 1. Upload image
    $attachment_id = $this->factory->attachment->create_upload_object(__DIR__ . '/fixtures/test.jpg');

    // 2. Simulate failed upload
    update_post_meta($attachment_id, '_pxl8_status', 'failed');
    update_post_meta($attachment_id, '_pxl8_last_error', '401 Unauthorized');

    // 3. Verify original URL remains (not rewritten)
    $url = wp_get_attachment_url($attachment_id);

    $this->assertStringContainsString('wp-content/uploads', $url); // WordPress original URL
    $this->assertStringNotContainsString('img.example.com', $url); // NOT rewritten
}
```

**❌ NOT Required in v1:**
- Unit tests for WordPress hooks (too expensive, low ROI)
- E2E tests (manual smoke tests sufficient)
- 100% code coverage (pragmatic coverage only)

---

## 7. Definition of Done (Checklist)

### Functional Requirements

- ✅ Settings page with all configuration options
- ✅ Test Connection button works (shows OK or error)
- ✅ Auto-optimize on upload (creates `_pxl8_image_id`)
- ✅ Frontend URL rewriting (`wp_get_attachment_url`, `srcset`)
- ✅ Quota monitoring widget (storage, bandwidth, transforms)
- ✅ Deactivate: URLs revert to original
- ✅ Uninstall: optionally removes data

### Performance Requirements

- ✅ No HTTP requests to PXL8 on frontend page load
- ✅ URL rewriting uses only metadata (no API calls)
- ✅ Quota data cached for 5 minutes (transient)

### Error Handling

- ✅ Upload errors do not break WordPress upload
- ✅ Test Connection shows user-friendly errors
- ✅ Failed uploads logged with `_pxl8_status = 'failed'`

### Compatibility

- ✅ Works with WordPress 5.9+
- ✅ Works with PHP 7.4+
- ✅ Works with Block Editor (Gutenberg)
- ✅ Works with Classic Editor

### Code Quality

- ✅ No PHP errors/warnings in `WP_DEBUG` mode
- ✅ Follows WordPress Coding Standards
- ✅ All user inputs sanitized/validated
- ✅ All database queries use prepared statements
- ✅ Nonces used for all AJAX requests

---

## 8. Work Plan (5-7 Days)

### Day 1: Foundation
- ✅ Project structure (files, directories)
- ✅ Plugin bootstrap (`pxl8.php`)
- ✅ Composer setup (`pxl8-sdk-php` dependency)
- ✅ Settings page skeleton
- ✅ Client factory
- ✅ Test Connection (AJAX)

### Day 2: Upload Handler
- ✅ `wp_generate_attachment_metadata` hook
- ✅ Upload to PXL8 (sync)
- ✅ Attachment metadata storage
- ✅ Error handling (non-fatal)
- ✅ Idempotency (hash check)

### Day 3: URL Rewriting
- ✅ `wp_get_attachment_url` filter
- ✅ `wp_get_attachment_image_src` filter
- ✅ `wp_calculate_image_srcset` filter
- ✅ Admin exclusions (`is_admin()`)
- ✅ Transform parameters logic

### Day 4: Quota Monitoring
- ✅ Quota widget UI
- ✅ Transient caching (5 min)
- ✅ Refresh button (AJAX)
- ✅ Display storage/bandwidth/transforms

### Day 5: Cleanup & Diagnostics
- ✅ Deactivation hook
- ✅ Uninstall hook
- ✅ Logger implementation
- ✅ Diagnostics page
- ✅ README.txt for WordPress.org

### Day 6-7: Testing & Polish
- ✅ Manual smoke tests (8 scenarios)
- ✅ PHPUnit tests (unit + integration)
- ✅ Fix compatibility issues
- ✅ Error message improvements
- ✅ Performance optimization

---

## 9. API Endpoints Reference

### From `pxl8-sdk-php`

**Upload Image:**
```php
$response = $client->upload($filePath);
// POST /api/v1/images
// Returns: ['imageId' => UUID, 'originalUrl' => ..., 'size' => ..., 'format' => ..., 'width' => ..., 'height' => ...]
```

**Get Tenant Info (Test Connection):**
```php
$tenant = $client->getTenant();
// GET /api/tenants/me
// Returns: ['id' => ..., 'name' => ..., 'email' => ...]
```

**Get Usage:**
```php
$usage = $client->getUsage();
// GET /api/tenants/me/usage
// Returns: ['currentPeriod' => ['storage' => ['used' => ..., 'limit' => ...], ...]]
```

**Build Transform URL:**
```php
$url = $client->getUrl($imageId, ['w' => 800, 'h' => 600, 'q' => 85, 'format' => 'webp']);
// Returns: "https://img.pxl8.ru/img/transform/{imageId}.webp?w=800&h=600&q=85"
// (No API call, just URL construction)
```

**Build Original URL:**
```php
$url = $client->getOriginalUrl($imageId);
// Returns: "https://img.pxl8.ru/img/original/{imageId}.jpg"
// (No API call, just URL construction)
```

**Delete Image:**
```php
$client->delete($imageId);
// DELETE /api/v1/images/{imageId}
```

---

## 10. Next Steps After MVP

### Phase 10: Enhancements (Post-MVP)
- Background upload queue (WP-Cron / Action Scheduler)
- Batch migration tool (optimize existing images)
- Advanced settings (per-image quality, custom fit modes)
- WebP/AVIF fallback (`<picture>` element)
- Manual retry button for failed uploads
- Bulk re-sync tool (update existing PXL8 images)

### Phase 11: Bitrix Module
- Similar architecture to WordPress plugin
- Uses `pxl8-sdk-php`
- Bitrix-specific hooks (`OnAfterFileAdd`, etc.)

---

**Last Updated:** 8 January 2026
**Version:** 1.0.1 (Pragmatic MVP)
**Status:** Ready for Implementation

---

## Summary of Key Decisions (v1.0.1)

| Decision | Rationale |
|----------|-----------|
| Auto-optimize OFF by default | Prevents unexpected quota consumption, shared hosting protection |
| Only Media Library uploads | Reduces complexity ×10, clear scope |
| HTML content NOT rewritten | Avoids regex hell, performance issues |
| Failed uploads keep original URL | Graceful degradation, no broken images |
| Manual retry only (no auto-retry) | Prevents infinite loops, quota waste |
| Simplified testing (1 happy + 1 failure) | Pragmatic MVP, no over-engineering |
| API key never logged | Security best practice |
| Hash as heuristic idempotency | WordPress may re-save files (EXIF stripping) |
