<?php
header('Content-Type: application/json');
require_once '../Includes/connection.php'; // PDO $conn

$data = json_decode(file_get_contents("php://input"), true);
$itemId = $data['itemId'];
$newPrice = $data['newPrice'];

// Start transaction
$conn->beginTransaction();

try {
    // Get current price
    $sql = "SELECT price, item_name FROM inventory WHERE item_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Item not found");
    }

    $oldPrice = $item['price'];

    // Update price
    $sql = "UPDATE inventory SET price = ? WHERE item_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$newPrice, $itemId]);

    // Log the activity with price change details
    $activity_description = "Price updated for {$item['item_name']} ({$itemId}) - Old price: ₱" . number_format($oldPrice, 2) . ", New price: ₱" . number_format($newPrice, 2);
    $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Edit Price', ?, ?, ?, NOW())";
    $stmt = $conn->prepare($log_activity_query);
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt->execute([$activity_description, $itemId, $user_id]);

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Price updated successfully',
        'old_price' => $oldPrice,
        'new_price' => $newPrice
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn instanceof PDO && $conn->inTransaction()) { $conn->rollBack(); }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>