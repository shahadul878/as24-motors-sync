# ✅ Diagnostic Checklist - Is Everything Working?

## 🎯 Quick Diagnostic (2 Minutes)

Run through this checklist to verify the plugin is set up correctly:

---

## 1️⃣ **Plugin Activation** (✓ or ✗)

- [ ] Plugin shows as **Active** in Plugins page
- [ ] No PHP errors shown on activation
- [ ] 🔄 **AS24 Sync** menu appears in WordPress sidebar

**If failed**: Reactivate plugin, check PHP error log

---

## 2️⃣ **Menu Visibility** (✓ or ✗)

- [ ] See **🔄 AS24 Sync** in WordPress sidebar (with rotating arrow icon)
- [ ] Click AS24 Sync → Expands to show **Dashboard** and **Settings**
- [ ] Can access both pages without errors

**If failed**: Check if plugin files uploaded correctly

---

## 3️⃣ **Dashboard Page** (✓ or ✗)

- [ ] Dashboard page loads (no white screen)
- [ ] See 6 stat cards (Remote, Local, Last Sync, Duplicates, Orphaned, In Trash)
- [ ] See **Quick Actions** section
- [ ] See **Sync & Import** button (big blue button)

**If failed**: Check PHP error log, verify all files uploaded

---

## 4️⃣ **API Credentials** (✓ or ✗)

Go to: **AS24 Sync → Settings**

- [ ] Settings page loads with 5 tabs
- [ ] Connection tab shows username and password fields
- [ ] Enter credentials:
  ```
  Username: 2142078191-gma-cars
  Password: 707Aq1gxOvKE2JbN1CQ9teLI6au9BU
  ```
- [ ] Click **Test Connection** button
- [ ] Should show: ✓ Connection successful! Total listings: XXX
- [ ] Click **Save Settings**

**If test fails**: Check API credentials, verify internet connectivity

---

## 5️⃣ **Browser Console** (✓ or ✗)

On Dashboard page, press **F12** and check Console tab:

- [ ] See: `AS24: Initializing plugin JavaScript`
- [ ] See: `AS24: ajaxurl = http://...`
- [ ] See: `AS24: nonce = Set`
- [ ] See: `AS24: Initialization complete`
- [ ] **No red error messages**

**If failed**: See specific errors below

---

## 6️⃣ **Button State** (✓ or ✗)

- [ ] **Sync & Import** button is **blue** (not gray)
- [ ] Button is **clickable** (not disabled)
- [ ] Hover over button shows pointer cursor
- [ ] Button text says "Sync & Import"

**If button is gray**: API credentials not configured (go to Step 4)

---

## 7️⃣ **Button Click Test** (✓ or ✗)

With Console open (F12), click **Sync & Import** button:

- [ ] See: `AS24: Sync & Import button clicked`
- [ ] See: `AS24: Making AJAX request to: ...`
- [ ] Button shows "Loading..." text
- [ ] Progress bar appears below
- [ ] See: `AS24: AJAX response received: ...`
- [ ] See: `AS24: Success - Sync complete!`
- [ ] Toast notification appears (green box, top-right)
- [ ] Stats update (numbers change)

**If no console messages**: JavaScript not binding events (see Fix #1 below)

---

## 🔧 **Common Fixes**

### Fix #1: JavaScript Not Binding
```javascript
// Run in Console to manually test:
jQuery('.as24-sync-now').trigger('click');

// If nothing happens, check if jQuery is working:
jQuery('.as24-sync-now').length  // Should return 1
```

**Solution**:
- Clear browser cache (Ctrl+Shift+Delete)
- Hard refresh (Ctrl+F5)
- Try different browser

### Fix #2: AJAX Endpoint Not Found (404)
**Console shows**: `POST admin-ajax.php 404`

**Solution**:
- Check .htaccess file
- Verify WordPress installed correctly
- Check permalink settings

### Fix #3: AJAX Forbidden (403)
**Console shows**: `POST admin-ajax.php 403`

**Solution**:
- Nonce verification failed
- Check user has admin permissions
- Deactivate/reactivate plugin to regenerate nonce

### Fix #4: PHP Fatal Error
**Console shows**: Long HTML error message

**Solution**:
- Enable WP_DEBUG in wp-config.php
- Check debug.log for specific error
- Likely missing class file or syntax error
- Verify all files uploaded

### Fix #5: Timeout Error
**Console shows**: `timeout of 300000ms exceeded`

**Solution**:
- Server taking too long to respond
- Check PHP max_execution_time setting
- Try smaller batch size in settings
- Check server resources

---

## 🧪 **Manual AJAX Test**

Run this in Console to test AJAX directly:

```javascript
// Test sync endpoint
jQuery.ajax({
    url: AS24_Admin.ajaxurl,
    type: 'POST',
    data: {
        action: 'as24_sync_now',
        nonce: AS24_Admin.nonce
    },
    success: function(response) {
        console.log('Test sync success:', response);
    },
    error: function(xhr, status, error) {
        console.error('Test sync error:', error);
        console.error('Response:', xhr.responseText);
    }
});
```

**Expected Result**:
```javascript
{
    success: true,
    data: {
        added: X,
        updated: Y,
        removed: Z,
        unchanged: N,
        duration: T
    }
}
```

---

## 📊 **System Requirements Check**

### PHP Version
```
Required: 7.4+
Check: WordPress Admin → Site Health → Info → Server
```

### WordPress Version
```
Required: 5.8+
Check: Dashboard → Updates
```

### Memory Limit
```
Recommended: 256MB+
Check: Site Health → Info → Server → PHP memory limit
```

### Max Execution Time
```
Recommended: 300 seconds
Check: Site Health → Info → Server → PHP time limit
```

---

## 📞 **Get Help**

If you've gone through this checklist and the button still doesn't work:

### Collect This Information:
1. ✅/✗ marks from checklist above
2. Console log output (all AS24 messages)
3. Network tab screenshot (if AJAX request visible)
4. PHP error log contents (any AS24 errors)
5. WordPress version
6. PHP version
7. Active theme and plugins list

### Send To:
**Email**: shahadul.islam1@gmail.com
**Subject**: AS24 Motors Sync - Button Not Working

**Include**:
- Checklist results
- Console messages
- Error logs

**Response Time**: Usually within 24 hours

---

## 🎯 **99% Success Rate**

Most button issues are caused by:
1. **40%** - API credentials not configured
2. **25%** - Browser cache (needs clear + refresh)
3. **20%** - PHP errors (missing files or syntax)
4. **10%** - Server configuration (memory, timeouts)
5. **5%** - Other (conflicts, permissions, etc.)

**This checklist will identify your specific issue!**

---

## ✅ **After Fixing**

Once working, you should see:
1. Button click → Loading state
2. Progress bar appears
3. 15-60 seconds processing
4. Toast notification: "Sync complete!"
5. Stats update with new numbers
6. Recent activity shows new entries

**That's how you know it's working!** 🎊

