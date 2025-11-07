<?php
// Lightweight endpoint to return variant (size) sets for base item codes
// Input: POST base_codes as comma-separated string or array
// Output: { success: true, variants: { [base_code]: { sizes:[], item_codes:[], prices:[], stocks:[], total_stock: int, image: string, name?: string, category?: string } } }

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/connection.php';

    // Support both POST and GET
    $baseCodes = [];
    if (isset($_POST['base_codes'])) {
        if (is_array($_POST['base_codes'])) {
            $baseCodes = $_POST['base_codes'];
        } else {
            $baseCodes = array_filter(array_map('trim', explode(',', (string)$_POST['base_codes'])));
        }
    } elseif (isset($_GET['base_codes'])) {
        if (is_array($_GET['base_codes'])) {
            $baseCodes = $_GET['base_codes'];
        } else {
            $baseCodes = array_filter(array_map('trim', explode(',', (string)$_GET['base_codes'])));
        }
    }

    if (empty($baseCodes)) {
        echo json_encode(['success' => true, 'variants' => []]);
        exit;
    }

    // Normalize and unique base codes
    $baseCodes = array_values(array_unique(array_map(function($c) {
        // Base code is the part before first '-'
        $c = trim((string)$c);
        if ($c === '') return '';
        $parts = explode('-', $c, 2);
        return $parts[0];
    }, $baseCodes)));

    $variantsMap = [];

    // Query per base code to keep it simple and safe
    $stmt = $conn->prepare("SELECT item_code, item_name, price, actual_quantity, image_path, category, sizes FROM inventory WHERE item_code = ? OR item_code LIKE CONCAT(?, '-%') ORDER BY item_code ASC");

    foreach ($baseCodes as $base) {
        if ($base === '') continue;

        if (!$stmt->execute([$base, $base])) {
            continue;
        }

        $sizes = [];
        $itemCodes = [];
        $prices = [];
        $stocks = [];
        $totalStock = 0;
        $image = '';
        $name = '';
        $category = '';

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $item_code = $row['item_code'];
            $price = isset($row['price']) ? (float)$row['price'] : 0;
            $stock = isset($row['actual_quantity']) ? (int)$row['actual_quantity'] : 0;
            $img = $row['image_path'];
            $nm = $row['item_name'] ?? '';
            $cat = $row['category'] ?? '';

            // Determine size: many rows store a single size in sizes column
            $rowSizes = array_map('trim', explode(',', (string)$row['sizes']));
            $size = isset($rowSizes[0]) ? $rowSizes[0] : '';

            $sizes[] = $size;
            $itemCodes[] = $item_code;
            $prices[] = $price;
            $stocks[] = $stock;
            $totalStock += $stock;
            if (!$image) {
                if (!empty($img)) {
                    if (strpos($img, 'uploads/') === false) {
                        $image = '../uploads/itemlist/' . $img;
                    } else {
                        $image = '../' . ltrim($img, '/');
                    }
                } else {
                    $image = '../uploads/itemlist/default.png';
                }
            }
            if (!$name && $nm) $name = $nm;
            if (!$category && $cat) $category = $cat;
        }

        if (!empty($itemCodes)) {
            // Ensure unique by item_code (in case duplicates)
            $combined = [];
            foreach ($itemCodes as $i => $code) {
                $combined[] = [
                    'code' => $code,
                    'size' => $sizes[$i] ?? '',
                    'price' => $prices[$i] ?? 0,
                    'stock' => $stocks[$i] ?? 0,
                ];
            }
            // Sort by size for stable output (optional)
            usort($combined, function($a, $b) {
                return strnatcasecmp($a['size'], $b['size']);
            });

            $variantsMap[$base] = [
                'sizes' => array_values(array_map(function($x){ return (string)$x['size']; }, $combined)),
                'item_codes' => array_values(array_map(function($x){ return (string)$x['code']; }, $combined)),
                'prices' => array_values(array_map(function($x){ return (float)$x['price']; }, $combined)),
                'stocks' => array_values(array_map(function($x){ return (int)$x['stock']; }, $combined)),
                'total_stock' => (int)$totalStock,
                'image' => $image,
                'name' => $name,
                'category' => $category,
            ];
        } else {
            // No variants found for this base
            $variantsMap[$base] = [
                'sizes' => [],
                'item_codes' => [],
                'prices' => [],
                'stocks' => [],
                'total_stock' => 0,
                'image' => '../uploads/itemlist/default.png',
                'name' => '',
                'category' => '',
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'variants' => $variantsMap,
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
    exit;
}
