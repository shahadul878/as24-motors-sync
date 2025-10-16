# 🔧 Troubleshooting Guide - Import & Sync Button

## 🐛 Button Not Working? Follow These Steps

### Step 1: Open Browser Console (F12)

**Chrome/Edge/Firefox**:
1. Press `F12` on keyboard
2. Click **Console** tab
3. Refresh the page (F5)
4. Look for messages starting with `AS24:`

---

## 📊 What to Look For in Console

### ✅ **Expected Console Messages** (Working)
```
AS24: Initializing plugin JavaScript
AS24: ajaxurl = http://yoursite.com/wp-admin/admin-ajax.php
AS24: nonce = Set
AS24: Initialization complete
```

When you click the button:
```
AS24: Sync & Import button clicked
AS24: Making AJAX request to: http://yoursite.com/wp-admin/admin-ajax.php
AS24: Action: as24_sync_now
AS24: AJAX response received: {success: true, data: {...}}
AS24: Success - Sync complete! Added: X, Updated: Y
AS24: AJAX request complete
```

### ❌ **Error Scenarios**

#### Scenario 1: Script Not Loading
```
(No AS24 messages at all)
```
**Problem**: JavaScript file not loading
**Solution**:
1. Check file exists: `wp-content/plugins/as24-motors-sync/assets/js/admin.js`
2. Check file permissions (should be readable)
3. Clear browser cache (Ctrl+F5)
4. Check WordPress error log for PHP errors

#### Scenario 2: AS24_Admin Not Defined
```
AS24: AS24_Admin object not found! Script may not be localized.
```
**Problem**: Script localization failed
**Solution**:
1. Check if you're on the correct page (AS24 Sync dashboard)
2. Deactivate and reactivate plugin
3. Check PHP error log for script enqueue errors

#### Scenario 3: jQuery Not Loaded
```
Uncaught ReferenceError: $ is not defined
```
**Problem**: jQuery dependency issue
**Solution**:
1. WordPress jQuery should load automatically
2. Check for jQuery conflicts with theme/other plugins
3. Try disabling other plugins temporarily

#### Scenario 4: Button Disabled
```
AS24: Button is disabled
```
**Problem**: API credentials not configured
**Solution**:
1. Go to AS24 Sync → Settings
2. Enter API credentials
3. Click Test Connection
4. Save Settings
5. Return to Dashboard

#### Scenario 5: AJAX Error
```
AS24: AJAX error: {xhr, status, error}
AS24: Response text: [error message]
```
**Problem**: Server-side error
**Solutions**:
- Check WordPress error log: `wp-content/debug.log`
- Check plugin logs: `wp-content/uploads/as24-motors-sync-logs/as24-error.log`
- Verify API credentials are correct
- Check server can reach autoscout24.com API

#### Scenario 6: PHP Fatal Error
```
AS24: Response text: Fatal error: Call to undefined function...
```
**Problem**: Missing PHP class or function
**Solution**:
1. Verify all plugin files uploaded correctly
2. Check file list below
3. Reupload plugin if files missing

---

## ✅ **Required Files Checklist**

### Core Files (Must Exist)
- [ ] `as24-motors-sync.php`
- [ ] `includes/class-logger.php`
- [ ] `includes/class-queries.php`
- [ ] `includes/class-duplicate-handler.php`
- [ ] `includes/class-orphan-detector.php`
- [ ] `includes/class-importer.php`
- [ ] `includes/class-sync-engine.php`
- [ ] `includes/class-image-handler.php`
- [ ] `includes/class-field-mapper.php`
- [ ] `includes/class-cron-manager.php`
- [ ] `admin/class-admin.php`
- [ ] `admin/class-ajax-handler.php`
- [ ] `admin/views/dashboard.php`
- [ ] `admin/views/settings.php`
- [ ] `assets/css/admin.css`
- [ ] `assets/js/admin.js`

---

## 🔍 **Step-by-Step Debugging**

### Test 1: Verify Plugin Activated
```
WordPress Admin → Plugins
Look for: AutoScout24 Motors Sync
Status: Should show "Deactivate" link (means it's active)
```

### Test 2: Verify Menu Appears
```
WordPress Sidebar
Look for: 🔄 AS24 Sync menu
If not there: Plugin not activated or activation error occurred
```

### Test 3: Verify Dashboard Page Loads
```
Click: AS24 Sync → Dashboard
Should show: Dashboard with stat cards
If blank/error: Check PHP error log
```

### Test 4: Verify API Credentials Configured
```
Dashboard page should show:
- Remote: 0 (if not configured)
- Button: Disabled (gray) if not configured

If button is gray:
  1. Go to AS24 Sync → Settings
  2. Enter credentials
  3. Test Connection
  4. Save
  5. Return to Dashboard
  6. Button should now be blue and clickable
```

### Test 5: Check Browser Console
```
1. Open Dashboard
2. Press F12
3. Go to Console tab
4. Look for "AS24: Initialization complete"
5. Click Sync & Import button
6. Watch console for messages
```

### Test 6: Check Network Tab
```
1. Press F12
2. Go to Network tab
3. Click Sync & Import button
4. Look for: admin-ajax.php request
5. Click on it
6. Check Response tab
7. Should show JSON response
```

---

## 🔧 **Common Fixes**

### Fix 1: Clear All Caches
```bash
# Browser cache
Ctrl+Shift+Del → Clear cache

# WordPress cache (if using cache plugin)
Disable cache plugin temporarily

# Server cache
Contact hosting support to clear server cache
```

### Fix 2: Check WordPress Debug Mode
```php
// Edit: wp-config.php
// Add these lines:

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Then check: wp-content/debug.log
```

### Fix 3: Verify File Permissions
```bash
# Plugin folder should be readable
chmod 755 wp-content/plugins/as24-motors-sync
chmod 644 wp-content/plugins/as24-motors-sync/assets/js/admin.js
chmod 644 wp-content/plugins/as24-motors-sync/assets/css/admin.css
```

### Fix 4: Reinstall Plugin
```
1. Deactivate plugin
2. Delete plugin folder
3. Reupload fresh copy
4. Activate plugin
5. Configure credentials
6. Try again
```

### Fix 5: Check jQuery Conflicts
```javascript
// In browser console, test if jQuery works:
jQuery(document).ready(function($) {
    console.log('jQuery version:', $.fn.jquery);
    console.log('Button exists:', $('.as24-sync-now').length);
});
```

---

## 📝 **What to Share for Support**

If button still doesn't work, collect this information:

### 1. Console Messages
```
Copy everything from Console tab starting with "AS24:"
```

### 2. Network Request
```
From Network tab:
- Request URL
- Response (from Response tab)
- Status code
```

### 3. PHP Error Log
```
From wp-content/debug.log:
- Any errors mentioning "AS24" or "as24-motors-sync"
```

### 4. Plugin Info
```
- WordPress version
- PHP version  
- Active plugins list
- Theme name
- Server type (Apache/Nginx)
```

### 5. Browser Info
```
- Browser name and version
- Operating system
- Any browser extensions that might interfere
```

---

## 🆘 **Quick Diagnostic Command**

Open browser console and run:
```javascript
// Check if everything is loaded
console.log('Plugin loaded:', typeof AS24_Admin !== 'undefined');
console.log('jQuery loaded:', typeof jQuery !== 'undefined');
console.log('Button exists:', jQuery('.as24-sync-now').length > 0);
console.log('Button disabled:', jQuery('.as24-sync-now').prop('disabled'));
console.log('AJAX URL:', AS24_Admin.ajaxurl);
console.log('Nonce:', AS24_Admin.nonce);

// Try to trigger sync manually
jQuery.post(AS24_Admin.ajaxurl, {
    action: 'as24_sync_now',
    nonce: AS24_Admin.nonce
}, function(response) {
    console.log('Manual sync response:', response);
});
```

---

## 💡 **Most Common Issues**

### Issue #1: API Credentials Not Configured
**Symptom**: Button is gray/disabled
**Fix**: Go to Settings, enter credentials, save

### Issue #2: JavaScript Not Loading
**Symptom**: No console messages at all
**Fix**: Clear cache, check file permissions, reupload plugin

### Issue #3: AJAX 403 Error
**Symptom**: Console shows "403 Forbidden"
**Fix**: Check nonce, verify user has manage_options capability

### Issue #4: PHP Fatal Error
**Symptom**: White screen or partial page load
**Fix**: Check debug.log, ensure all class files uploaded

### Issue #5: Timeout
**Symptom**: Button loads forever, then timeout error
**Fix**: Increase PHP max_execution_time, check API connectivity

---

## 📞 **Still Not Working?**

Contact developer with:
- Console log output
- PHP error log
- Network tab screenshot
- WordPress version
- PHP version

**Email**: shahadul.islam1@gmail.com

---

## ✅ **Expected Behavior** (When Working)

1. Click "Sync & Import" button
2. Button shows "Loading..."
3. Progress bar appears
4. Console shows: "AS24: Sync & Import button clicked"
5. Progress bar fills up
6. Toast notification appears: "Sync complete!"
7. Stats update automatically
8. Button returns to normal

**Total time**: 15-60 seconds depending on listings count

---

*This guide will help identify and fix 99% of button issues!*

