# AutoScout24 Motors Sync - Installation & Setup Guide

## 🚀 Quick Start (5 Minutes)

### Step 1: Upload Plugin
1. Upload the `as24-motors-sync` folder to `/wp-content/plugins/`
2. OR upload as ZIP file through WordPress admin → Plugins → Add New

### Step 2: Activate
1. Go to **Plugins** in WordPress admin
2. Find **AutoScout24 Motors Sync**
3. Click **Activate**

### Step 3: Configure API Credentials
1. Go to **Motors → AS24 Settings**
2. Enter your AutoScout24 API credentials:
   - Username: `2142078191-gma-cars`
   - Password: `707Aq1gxOvKE2JbN1CQ9teLI6au9BU`
3. Click **Test Connection** to verify
4. Click **Save Settings**

### Step 4: Configure Sync Settings
1. Go to **Import** tab
   - Enable **Auto Import**: ✓
   - Set **Import Frequency**: Daily (or as needed)
   - **Batch Size**: 50 (recommended)
   
2. Go to **Sync** tab
   - Enable **Auto Sync**: ✓ (recommended)
   - Set **Sync Frequency**: Hourly
   
3. Go to **Data Integrity** tab
   - Enable **Auto Cleanup Duplicates**: ✓
   - Enable **Auto Remove Orphans**: ✓
   - Set **Trash Retention**: 30 days
   
4. Click **Save Settings**

### Step 5: Initial Import
1. Go to **Motors → AS24 Sync** (Dashboard)
2. Click **Import All** to start first import
3. Wait for progress to complete
4. Verify listings appear in **Motors → Listings**

## ✅ What Happens After Activation

1. **Database Index Created**: Prevents duplicate inserts
2. **Log Directory Created**: `wp-content/uploads/as24-motors-sync-logs/`
3. **Sync History Table Created**: Tracks all operations
4. **Cron Jobs Scheduled**:
   - Daily cleanup at 3 AM
   - Auto-import (if enabled)
   - Auto-sync (if enabled)

## 📋 Verification Checklist

After installation, verify:

- [ ] Plugin activated without errors
- [ ] Motors plugin is active
- [ ] API credentials configured
- [ ] Connection test successful
- [ ] Dashboard displays stats correctly
- [ ] Initial import completes successfully
- [ ] Listings appear in Motors → Listings
- [ ] Images imported correctly
- [ ] No duplicate listings exist
- [ ] Cron jobs are scheduled
- [ ] Logs are being created

## 🔧 Configuration Options

### Import Settings
- **Auto Import**: Enable scheduled automatic imports
- **Import Frequency**: How often to import (hourly, daily, weekly)
- **Batch Size**: Listings per batch (default: 50, max: 50)

### Sync Settings
- **Auto Sync**: Enable automatic change detection
- **Sync Frequency**: How often to check for changes (hourly recommended)

### Data Integrity
- **Auto Cleanup Duplicates**: Daily duplicate scan and removal
- **Auto Remove Orphans**: Automatic deletion of removed listings
- **Trash Retention**: Days to keep in trash before permanent deletion (30 recommended)

## 🎯 Recommended Settings

### For High-Volume Dealers (200+ listings)
```
Auto Import: Enabled
Import Frequency: Daily
Auto Sync: Enabled
Sync Frequency: Hourly
Batch Size: 50
Duplicate Cleanup: Enabled
Orphan Cleanup: Enabled
```

### For Low-Volume Dealers (<100 listings)
```
Auto Import: Enabled
Import Frequency: Twice Daily
Auto Sync: Enabled
Sync Frequency: Hourly
Batch Size: 50
Duplicate Cleanup: Enabled
Orphan Cleanup: Enabled
```

## 🔄 Migrating from Old Plugin

If you're migrating from `autoscout24-importer`:

1. **Keep old plugin active** (both can run simultaneously)
2. **Install new plugin** following steps above
3. **Configure with same API credentials**
4. **Run test import** to verify functionality
5. **Compare results** - check listings match
6. **Disable auto-import on old plugin** to prevent conflicts
7. **Let new plugin run for 24 hours** to verify stability
8. **Deactivate old plugin** once satisfied
9. **Optional**: Delete old plugin to save space

### Data Notes
- Both plugins use the same meta field (`autoscout24-id`)
- Listings imported by either plugin will be recognized by both
- No data migration needed
- The new plugin will sync existing listings

## 🛠️ Troubleshooting

### Connection Test Fails
**Error**: "Connection failed"
**Solution**:
1. Verify API credentials are correct
2. Check server can reach `listing-search.api.autoscout24.com`
3. Check firewall/proxy settings
4. Enable WP_DEBUG to see detailed errors

### Import Timeout
**Error**: Import stops or times out
**Solution**:
1. Reduce batch size to 25
2. Increase PHP `max_execution_time` to 300
3. Increase PHP `memory_limit` to 256MB
4. Check server resources during import

### Duplicates Not Cleaned
**Solution**:
1. Go to Dashboard → Scan Duplicates
2. Review duplicate count
3. Manually trigger cleanup
4. Check logs for errors
5. Verify database index exists

### Orphans Not Detected
**Solution**:
1. Ensure auto-sync is enabled
2. Verify API credentials work
3. Manually trigger "Check Orphans"
4. Review orphan logs
5. Check if listings actually removed from AS24

## 📊 Performance Expectations

With 200 listings:
- **First Import**: 45-60 seconds
- **Sync Operation**: 15-20 seconds
- **Duplicate Scan**: 5-10 seconds
- **Orphan Check**: 10-15 seconds

With 500 listings:
- **First Import**: 2-3 minutes
- **Sync Operation**: 30-40 seconds
- **Duplicate Scan**: 10-15 seconds
- **Orphan Check**: 20-30 seconds

## 🆘 Getting Help

1. Check logs in **Settings → Logs** tab
2. Review **Recent Activity** on dashboard
3. Enable WP_DEBUG for detailed errors
4. Check server error logs
5. Contact plugin developer: shahadul.islam1@gmail.com

## 📅 Maintenance

### Daily
- Automatic cleanup runs at 3 AM
- Review dashboard for any alerts
- Check duplicate/orphan counts

### Weekly
- Review logs for errors
- Verify sync operations completing
- Check listing counts match AS24

### Monthly
- Review performance metrics
- Clean up old rotated logs if needed
- Verify all cron jobs running

## 🔒 Security Notes

- API credentials stored securely in WordPress database
- Logs protected with .htaccess (not web-accessible)
- All AJAX requests use nonce verification
- Database queries use prepared statements
- Input sanitization on all user inputs

## ⚡ Performance Tips

1. **Use Hourly Sync** instead of import for keeping data current
2. **Set batch size to 50** for optimal performance
3. **Enable auto-cleanup** to prevent duplicates and orphans
4. **Review logs weekly** to catch issues early
5. **Monitor server resources** during first import

## 🎉 You're All Set!

Your AutoScout24 Motors Sync plugin is now configured and ready to use. The plugin will:

✅ Automatically import new listings
✅ Detect and update changed listings
✅ Remove listings deleted from AutoScout24
✅ Prevent duplicate listings
✅ Clean up old trash automatically
✅ Log all operations for auditing

Enjoy your optimized, automated listing management!

