<?php
session_start();
require_once '../../Includes/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportType = $_POST['reportType'];
    
    // Check if this is a clear filters request
    if (isset($_POST['clearFilters'])) {
        displayReport($reportType, null, null, $conn);
    } else {
        $startDate = $_POST['startDate'];
        $endDate = $_POST['endDate'];
        // Add one day to end date to include the entire end date
        $endDate = date('Y-m-d', strtotime($endDate . ' +1 day'));
        displayReport($reportType, $startDate, $endDate, $conn);
    }
}

function displayReport($reportType, $startDate, $endDate, $conn) {
    try {
        switch ($reportType) {
            case 'inventory':
                $sql = "SELECT * FROM inventory";
                $params = array();
                
                if ($startDate && $endDate) {
                    $sql .= " WHERE created_at >= :start_date AND created_at < :end_date";
                    $params[':start_date'] = $startDate;
                    $params[':end_date'] = $endDate;
                }
                
                $sql .= " ORDER BY created_at DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
                echo '<div class="report-table">';
                echo '<h3>Inventory Report</h3>';
                echo '<div class="scroll-table-container">';
                echo '<table>';
                echo '<thead><tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Beginning Quantity</th>
                        <th>New Delivery</th>
                        <th>Actual Quantity</th>
                        <th>Damage</th>
                        <th>Sold Quantity</th>
                        <th>Status</th>
                        <th>Date Delivered</th>
                      </tr></thead><tbody>';
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>{$row['item_code']}</td>";
                    echo "<td>{$row['item_name']}</td>";
                    echo "<td>{$row['category']}</td>";
                    echo "<td>{$row['beginning_quantity']}</td>";
                    echo "<td>{$row['new_delivery']}</td>";
                    echo "<td>{$row['actual_quantity']}</td>";
                    echo "<td>{$row['damage']}</td>";
                    echo "<td>{$row['sold_quantity']}</td>";
                    echo "<td>{$row['status']}</td>";
                    echo "<td>{$row['created_at']}</td>";
                    echo "</tr>";
                }
                echo '</tbody></table></div></div>';
                break;

            case 'sales':
                $sql = "SELECT s.*, i.item_name FROM sales s LEFT JOIN inventory i ON s.item_code = i.item_code";
                $params = array();
                
                if ($startDate && $endDate) {
                    $sql .= " WHERE s.sale_date >= :start_date AND s.sale_date < :end_date";
                    $params[':start_date'] = $startDate;
                    $params[':end_date'] = $endDate;
                }
                
                $sql .= " ORDER BY s.sale_date DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
                echo '<div class="report-table">';
                echo '<h3>Sales Report</h3>';
                echo '<div class="total-amount-display">';
                echo '<h4>Total Sales Amount: <span id="totalSalesAmount">₱0.00</span></h4>';
                echo '</div>';
                echo '<div class="scroll-table-container">';
                echo '<table>';
                echo '<thead><tr>
                        <th>Order Number</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Size</th>
                        <th>Quantity</th>
                        <th>Price Per Item</th>
                        <th>Total Amount</th>
                        <th>Sale Date</th>
                      </tr></thead><tbody>';
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>{$row['transaction_number']}</td>";
                    echo "<td>{$row['item_code']}</td>";
                    echo "<td>{$row['item_name']}</td>";
                    echo "<td>{$row['size']}</td>";
                    echo "<td>{$row['quantity']}</td>";
                    echo "<td>₱" . number_format($row['price_per_item'], 2) . "</td>";
                    echo "<td>₱" . number_format($row['total_amount'], 2) . "</td>";
                    echo "<td>{$row['sale_date']}</td>";
                    echo "</tr>";
                }
                echo '</tbody></table></div></div>';
                break;

            case 'audit':
                $sql = "SELECT * FROM activities";
                $params = array();
                
                if ($startDate && $endDate) {
                    $sql .= " WHERE timestamp >= :start_date AND timestamp < :end_date";
                    $params[':start_date'] = $startDate;
                    $params[':end_date'] = $endDate;
                }
                
                $sql .= " ORDER BY timestamp DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                
                echo '<div class="report-table">';
                echo '<h3>Audit Trail</h3>';
                echo '<div class="scroll-table-container">';
                echo '<table>';
                echo '<thead><tr>
                        <th>Date/Time</th>
                        <th>Action Type</th>
                        <th>Description</th>
                      </tr></thead><tbody>';
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>{$row['timestamp']}</td>";
                    echo "<td>{$row['action_type']}</td>";
                    echo "<td>{$row['description']}</td>";
                    echo "</tr>";
                }
                echo '</tbody></table></div></div>';
                break;
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?> 