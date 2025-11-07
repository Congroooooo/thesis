<?php
// Lightweight endpoint to return current stock for specific item_codes
// Input: POST item_codes[] (array of item_code strings)
// Output: { success: bool, stocks: { item_code: actual_quantity, ... } }

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/connection.php';

    // Read item codes from POST (allow GET fallback for testing)
    $codes = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['item_codes'])) {
            $codes = is_array($_POST['item_codes']) ? $_POST['item_codes'] : explode(',', $_POST['item_codes']);
        }
    } else {
        if (isset($_GET['item_codes'])) {
            $codes = is_array($_GET['item_codes']) ? $_GET['item_codes'] : explode(',', $_GET['item_codes']);
        }
    }

    // Sanitize and de-duplicate
    $codes = array_values(array_unique(array_filter(array_map('trim', $codes))));

    if (empty($codes)) {
        echo json_encode([ 'success' => true, 'stocks' => new stdClass() ]);
        exit;
    }

    // Build parameterized query
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $conn->prepare("SELECT item_code, actual_quantity FROM inventory WHERE item_code IN ($placeholders)");
    $stmt->execute($codes);

    $stocks = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stocks[$row['item_code']] = (int)$row['actual_quantity'];
    }

    echo json_encode([ 'success' => true, 'stocks' => $stocks ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load inventory snapshot',
        'error' => $e->getMessage()
    ]);
}
?>
