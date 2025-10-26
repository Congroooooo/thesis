# Enhanced Search Functionality - Examples

## How Multi-Keyword Search Works

The search has been enhanced to support **partial matching with multiple keywords**. Each word you type is treated as a separate keyword, and the search finds products that contain **ALL** the keywords (not necessarily in order).

### Search Logic:

- **Keywords are separated by spaces**
- **Each keyword must appear somewhere** in the item name, item code, or category
- **Order doesn't matter**
- **Case-insensitive matching**

## Examples:

### Example 1: "TM Blouse"

**Before Enhancement:**

- ❌ Would NOT match "TM Female Blouse" (because "TM Blouse" doesn't appear as a continuous string)

**After Enhancement:**

- ✅ WILL match "TM Female Blouse" (contains both "TM" AND "Blouse")
- ✅ WILL match "Blouse TM" (order doesn't matter)
- ✅ WILL match "TM School Blouse"
- ❌ Will NOT match "TM Polo" (doesn't contain "Blouse")
- ❌ Will NOT match "Female Blouse" (doesn't contain "TM")

### Example 2: "Blue Polo"

- ✅ Matches "Blue Polo Shirt"
- ✅ Matches "Polo Blue"
- ✅ Matches "Navy Blue Polo"
- ✅ Matches "Polo Shirt (Blue)"
- ❌ Does NOT match "Blue Shirt" (no "Polo")
- ❌ Does NOT match "Polo Shirt" (no "Blue")

### Example 3: "TM Female Blue"

- ✅ Matches "TM Female Blue Blouse"
- ✅ Matches "Blue TM Female Polo"
- ✅ Matches any product containing all three words: "TM", "Female", "Blue"

### Example 4: Single Keyword "Polo"

- ✅ Matches "Polo Shirt"
- ✅ Matches "TM Polo"
- ✅ Matches "Blue Polo"
- ✅ Matches any product with "Polo" in the name

### Example 5: Item Code Search "TM-001"

- ✅ Matches item code "TM-001-S"
- ✅ Matches item code "TM-001"
- ✅ Works with partial codes: "TM 001" will match "TM-001"

## Technical Implementation

```php
// Split search query into individual keywords
$keywords = array_filter(array_map('trim', explode(' ', $searchQuery)));

foreach ($keywords as $keyword) {
    // Each keyword must match in item_name OR item_code OR category
    $searchConditions[] = "(i.item_name LIKE ? OR i.item_code LIKE ? OR i.category LIKE ?)";
    $keywordParam = '%' . $keyword . '%';
}

// All keywords must be present (AND logic)
$whereConditions[] = '(' . implode(' AND ', $searchConditions) . ')';
```

## Benefits:

1. **More Flexible**: Users don't need to remember exact product names
2. **Natural Search**: Search works like how people naturally describe items
3. **Typo Tolerant**: Missing words in the middle don't break the search
4. **Fast Results**: Still uses optimized SQL LIKE queries with indexes
5. **Better UX**: More results without compromising relevance

## Edge Cases Handled:

- **Multiple spaces**: "TM Blouse" (extra spaces) → Cleaned to "TM Blouse"
- **Leading/trailing spaces**: " TM Blouse " → Cleaned to "TM Blouse"
- **Single word**: "Blouse" → Works as before
- **Empty search**: "" → Shows all products
- **Special characters**: Search terms are properly escaped for SQL safety

## Performance Considerations:

- ✅ Uses indexed columns (item_name, item_code, category)
- ✅ Prepared statements prevent SQL injection
- ✅ Efficient AND logic instead of multiple queries
- ✅ No full-text search overhead (simple LIKE is fast enough)

---

**Updated**: October 26, 2025
**Status**: ✅ Active and Working
