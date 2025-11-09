# Cashier Modal Troubleshooting Guide

## Quick Fix Steps:

### 1. Clear Browser Cache

**The most common issue!**

- Press `Ctrl + Shift + Delete` (Windows) or `Cmd + Shift + Delete` (Mac)
- Select "Cached images and files"
- Click "Clear data"
- OR use hard refresh: `Ctrl + F5` or `Ctrl + Shift + R`

### 2. Check Browser Console

1. Open the PAMO dashboard
2. Press `F12` to open Developer Tools
3. Go to the "Console" tab
4. Look for these messages:
   - ✓ "Cashier modal script loaded"
   - ✓ "Checking cashier status..."
   - ✓ "Cashier status data: {success: true, is_set: false}"
   - ✓ "Cashier not set, showing modal"

If you see these, the feature is working!

### 3. Test the Feature Directly

Visit: `http://localhost/Proware/PAMO_PAGES/test_cashier_feature.php`

This will show you:

- ✓ If you're logged in as PAMO
- ✓ If the database table exists
- ✓ If the cashier is set for today
- ✓ If JavaScript files exist

### 4. Test the API Endpoint

Visit: `http://localhost/Proware/PAMO_PAGES/get_cashier_status.php`

Expected response if NOT set:

```json
{
  "success": true,
  "is_set": false,
  "cashier_name": null,
  "date": "2025-11-10"
}
```

Expected response if already set:

```json
{
  "success": true,
  "is_set": true,
  "cashier_name": "Juan Dela Cruz",
  "date": "2025-11-10"
}
```

### 5. Common Issues & Solutions

#### Issue: "Unauthorized - PAMO access required"

**Solution:** Make sure you're logged in as PAMO staff

- Check: role_category = 'EMPLOYEE'
- Check: program_abbreviation = 'PAMO'

#### Issue: Modal appeared once but not showing again

**Solution:** Cashier was already set for today

- Delete today's record to test again:
  ```sql
  DELETE FROM cashier_sessions WHERE date = CURDATE();
  ```
- Then refresh and login again

#### Issue: No console logs appear

**Solution:** JavaScript file not loading

- Check if file exists: `PAMO_JS/cashier-modal.js`
- Check file path in dashboard.php
- Clear browser cache (Ctrl + F5)

#### Issue: "cashier_sessions table doesn't exist"

**Solution:** Database table not created

- Run: `http://localhost/Proware/PAMO_PAGES/test_cashier_feature.php`
- If table missing, recreate it (contact developer)

### 6. Manual Test

If modal still doesn't appear, test manually:

1. Open browser console (F12)
2. Type this and press Enter:
   ```javascript
   showCashierModal();
   ```
3. Modal should appear immediately

If modal appears, the issue is with the automatic check.
If modal doesn't appear, there's a JavaScript error.

### 7. Check JavaScript Console for Errors

Look for red error messages like:

- "ReferenceError: showCashierModal is not defined"
- "Failed to fetch"
- "Unexpected token"

### 8. Verify Session

In the test page, check if session shows:

- Session user_id: [number]
- Session role_category: EMPLOYEE
- Session program_abbreviation: PAMO
- Is PAMO: YES

If "Is PAMO: NO", you're not logged in correctly.

---

## Expected Flow:

1. ✓ Login as PAMO staff
2. ✓ Redirected to dashboard
3. ✓ Page loads (500ms delay)
4. ✓ JavaScript checks: `get_cashier_status.php`
5. ✓ Response: `is_set = false`
6. ✓ Modal appears automatically
7. ✓ Enter cashier name
8. ✓ Click "Confirm & Continue"
9. ✓ Success notification
10. ✓ Modal closes

---

## Quick Commands:

**Reset cashier for today (to test again):**

```sql
DELETE FROM cashier_sessions WHERE date = CURDATE();
```

**Check current cashier:**

```sql
SELECT * FROM cashier_sessions WHERE date = CURDATE();
```

**View all cashier records:**

```sql
SELECT * FROM cashier_sessions ORDER BY date DESC;
```

---

## Still Not Working?

1. Go to: `http://localhost/Proware/PAMO_PAGES/test_cashier_feature.php`
2. Take a screenshot of the results
3. Open browser console (F12) and take a screenshot
4. Share both screenshots for debugging

---

## Changes Made (with cache busting):

- Added `?v=1.0` to JavaScript and CSS files to force browser reload
- Added console.log statements for debugging
- Created test page for troubleshooting
