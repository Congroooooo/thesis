# Size Display Ordering Enhancement

## Problem

When users selected sizes in the multi-size product addition modal, the size detail forms were displayed in the order they were clicked (e.g., XL, S, One Size), rather than in logical size order.

## Solution

Implemented a hierarchical size ordering system that displays size details from smallest to largest, regardless of the order they were selected.

## Size Display Order

The sizes are now displayed in this order:

1. **One Size** (0)
2. **XS** (1)
3. **S** (2)
4. **M** (3)
5. **L** (4)
6. **XL** (5)
7. **XXL** (6)
8. **3XL** (7)
9. **4XL** (8)
10. **5XL** (9)
11. **6XL** (10)
12. **7XL** (11)

## Key Features

### Separate Ordering Systems

- **Item Code Generation**: Uses `getSizeNumber()` which maintains the original numbering (One Size = 12)
- **Display Ordering**: Uses `getSizeDisplayOrder()` which puts One Size first (One Size = 0)

This ensures:

- Item codes remain consistent (e.g., One Size still generates `-012`)
- Display order is logical (One Size appears first in the list)

### Automatic Sorting

- `sortSizeDetails()` function automatically sorts size forms by display order
- Triggered every time a size is added via `toggleSizeDetails()`
- Uses `data-size-order` attribute for efficient sorting

### Visual Flow Example

**User Selection Order**: XL → S → One Size  
**Display Order**: One Size → S → XL  
**Generated Codes**:

- One Size: `BASEITEM-012`
- S: `BASEITEM-002`
- XL: `BASEITEM-005`

## Implementation Details

### Functions Modified

1. **`toggleSizeDetails()`**: Added `sortSizeDetails()` call after adding sizes
2. **`addSizeDetailForm()`**: Added `data-size-order` attribute using display order
3. **`getSizeDisplayOrder()`**: New function for logical display ordering
4. **`sortSizeDetails()`**: New function to sort existing size detail forms

### HTML Structure

Each size detail item now includes:

```html
<div class="size-detail-item" data-size="S" data-size-order="2"></div>
```

The `data-size-order` attribute enables efficient sorting while preserving all form data and interactions.

## Benefits

- **Better UX**: Logical size progression is easier to understand
- **Consistent Display**: Always shows sizes in the same order
- **Maintained Functionality**: Item code generation remains unchanged
- **Preserved Data**: Sorting doesn't affect form values or functionality
