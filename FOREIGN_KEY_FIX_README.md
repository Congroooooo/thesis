# Foreign Key Constraint Fix

## Issue Identified

The error was caused by a foreign key constraint violation in the `activities` table:

```
SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`proware`.`activities`, CONSTRAINT `fk_activities_inventory` FOREIGN KEY (`item_code`) REFERENCES `inventory` (`item_code`) ON UPDATE CASCADE)
```

## Root Cause

In the multi-size product addition flow, we were trying to insert an activity log using the base item code (e.g., `USHPWV001`), but this base code doesn't exist in the `inventory` table. The inventory table only contains the individual size-specific codes (e.g., `USHPWV001-001`, `USHPWV001-002`, etc.).

## Solution Applied

### 1. Activity Log Fix

**Before:**

```php
$stmt->execute([$description, $base_item_code, $user_id]);
```

**After:**

```php
$first_item_code = !empty($inserted_items) ? $inserted_items[0] : $base_item_code;
$stmt->execute([$description, $first_item_code, $user_id]);
```

This ensures we use an actual item code that exists in the inventory table.

### 2. Prefix Check Fix

**Before:**

```php
$prefix = explode('-', $base_item_code)[0];
$check_stmt = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE item_code LIKE CONCAT(?, '-%')");
$check_stmt->execute([$prefix]);
```

**After:**

```php
$check_stmt = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE item_code LIKE CONCAT(?, '-%')");
$check_stmt->execute([$base_item_code]);
```

This correctly uses the full base item code as the prefix for checking existing items.

### 3. Session Handling

Added proper session handling to ensure user_id is available:

```php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

## Expected Behavior Now

1. User enters base code: `USHPWV001`
2. Selects sizes: XS, S, M
3. System generates codes: `USHPWV001-001`, `USHPWV001-002`, `USHPWV001-003`
4. Creates 3 inventory entries with the generated codes
5. Creates 1 activity log using `USHPWV001-001` (first generated code)
6. Sends notifications to students

## Testing Checklist

- [ ] Add product with single size (should work)
- [ ] Add product with multiple sizes (should work)
- [ ] Verify item codes are generated correctly
- [ ] Check activity log is created with proper item_code reference
- [ ] Confirm inventory entries are created for each size
- [ ] Verify notifications are sent to students

## If Issues Persist

1. Check database constraints on `activities` table
2. Verify `inventory` table structure
3. Check if there are other foreign key constraints
4. Review session variables and user authentication
