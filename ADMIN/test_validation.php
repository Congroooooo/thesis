<?php
// Test script to verify case-insensitive validation logic
require_once '../Includes/connection.php';

echo "<h3>Testing Case-Insensitive Validation Logic</h3>\n";

// Test scenarios
$testCases = [
    ['name' => 'Digital Arts', 'category' => 'SHS', 'abbreviation' => 'DA'],
    ['name' => 'digital arts', 'category' => 'SHS', 'abbreviation' => 'da'], // Should be duplicate
    ['name' => 'DIGITAL ARTS', 'category' => 'SHS', 'abbreviation' => 'DA'], // Should be duplicate
    ['name' => 'Computer Science', 'category' => 'COLLEGE STUDENT', 'abbreviation' => 'CS'],
    ['name' => 'computer science', 'category' => 'COLLEGE STUDENT', 'abbreviation' => 'cs'], // Should be duplicate
];

foreach ($testCases as $index => $testCase) {
    $name = $testCase['name'];
    $category = $testCase['category'];
    $abbreviation = $testCase['abbreviation'];
    
    echo "<h4>Test Case " . ($index + 1) . ": $name ($category) - $abbreviation</h4>\n";
    
    // Check for case-insensitive duplicates across ALL categories (same logic as in manage_programs.php)
    $checkStmt = $conn->prepare("SELECT id, name, abbreviation, category FROM programs_positions WHERE LOWER(name) = LOWER(?) OR (abbreviation != '' AND LOWER(abbreviation) = LOWER(?))");
    $checkStmt->execute([$name, $abbreviation]);
    $existing = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($existing)) {
        echo "<p style='color: red;'>❌ DUPLICATE DETECTED:</p>\n";
        foreach ($existing as $existingRecord) {
            echo "<ul><li>Existing: {$existingRecord['name']} (ID: {$existingRecord['id']}) - {$existingRecord['abbreviation']}</li></ul>\n";
        }
        
        // Check which field is duplicate for more specific error message
        $duplicateCheckName = $conn->prepare("SELECT id, name, category FROM programs_positions WHERE LOWER(name) = LOWER(?)");
        $duplicateCheckName->execute([$name]);
        $nameExists = $duplicateCheckName->fetch(PDO::FETCH_ASSOC);
        
        $duplicateCheckAbbr = $conn->prepare("SELECT id, abbreviation, category FROM programs_positions WHERE abbreviation != '' AND LOWER(abbreviation) = LOWER(?)");
        $duplicateCheckAbbr->execute([$abbreviation]);
        $abbrExists = $duplicateCheckAbbr->fetch(PDO::FETCH_ASSOC);
        
        if ($nameExists && $abbrExists) {
            $nameCategory = $nameExists['category'];
            $abbrCategory = $abbrExists['category'];
            echo "<p><strong>Error Message:</strong> Both the program/position name and abbreviation already exist in the system (Name in: {$nameCategory}, Abbreviation in: {$abbrCategory}).</p>\n";
        } elseif ($nameExists) {
            $existingCategory = $nameExists['category'];
            echo "<p><strong>Error Message:</strong> A program/position with this name already exists in the '{$existingCategory}' category.</p>\n";
        } elseif ($abbrExists) {
            $existingCategory = $abbrExists['category'];
            echo "<p><strong>Error Message:</strong> A program/position with this abbreviation already exists in the '{$existingCategory}' category.</p>\n";
        }
    } else {
        echo "<p style='color: green;'>✅ NO DUPLICATE - Would be allowed</p>\n";
    }
    
    echo "<hr>\n";
}

echo "<h3>Current Programs in Database:</h3>\n";
$allStmt = $conn->prepare("SELECT * FROM programs_positions ORDER BY category, name");
$allStmt->execute();
$allPrograms = $allStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Abbreviation</th><th>Active</th></tr>\n";
foreach ($allPrograms as $program) {
    echo "<tr>";
    echo "<td>{$program['id']}</td>";
    echo "<td>{$program['name']}</td>";
    echo "<td>{$program['category']}</td>";
    echo "<td>{$program['abbreviation']}</td>";
    echo "<td>" . ($program['is_active'] ? 'Yes' : 'No') . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";
?>