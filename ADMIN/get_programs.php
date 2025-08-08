<?php
header('Content-Type: application/json');
require_once '../Includes/connection.php';

try {
    $category = $_GET['category'] ?? '';
    
    if (empty($category)) {
        throw new Exception('Category parameter is required');
    }
    
    // First check if programs_positions table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'programs_positions'");
    if ($tableCheck->rowCount() == 0) {
        // Table doesn't exist, return fallback data
        $fallbackPrograms = [
            'SHS' => [
                ['name' => 'Science, Technology, Engineering, and Mathematics (STEM)', 'abbreviation' => 'STEM'],
                ['name' => 'Humanities and Social Sciences (HUMMS)', 'abbreviation' => 'HUMMS'],
                ['name' => 'Accountancy, Business, and Management (ABM)', 'abbreviation' => 'ABM'],
                ['name' => 'Mobile App and Web Development (MAWD)', 'abbreviation' => 'MAWD'],
                ['name' => 'Digital Arts (DA)', 'abbreviation' => 'DA'],
                ['name' => 'Tourism Operations (TOPER)', 'abbreviation' => 'TOPER'],
                ['name' => 'Culinary Arts (CA)', 'abbreviation' => 'CA']
            ],
            'COLLEGE STUDENT' => [
                ['name' => 'Bachelor of Science in Computer Science (BSCS)', 'abbreviation' => 'BSCS'],
                ['name' => 'Bachelor of Science in Information Technology (BSIT)', 'abbreviation' => 'BSIT'],
                ['name' => 'Bachelor of Science in Computer Engineering (BSCPE)', 'abbreviation' => 'BSCPE'],
                ['name' => 'Bachelor of Science in Culinary Management (BSCM)', 'abbreviation' => 'BSCM'],
                ['name' => 'Bachelor of Science in Tourism Management (BSTM)', 'abbreviation' => 'BSTM'],
                ['name' => 'Bachelor of Science in Business Administration (BSBA)', 'abbreviation' => 'BSBA'],
                ['name' => 'Bachelor of Science in Multimedia Arts (BMMA)', 'abbreviation' => 'BMMA']
            ],
            'EMPLOYEE' => [
                ['name' => 'TEACHER', 'abbreviation' => 'TEACHER'],
                ['name' => 'PAMO', 'abbreviation' => 'PAMO'],
                ['name' => 'ADMIN', 'abbreviation' => 'ADMIN'],
                ['name' => 'STAFF', 'abbreviation' => 'STAFF']
            ]
        ];
        
        echo json_encode($fallbackPrograms[$category] ?? []);
        exit;
    }
    
    // Fetch active programs for the specified category
    $stmt = $conn->prepare("SELECT id, name, abbreviation, description FROM programs_positions WHERE category = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$category]);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no programs found, return fallback for that category
    if (empty($programs)) {
        $fallbackPrograms = [
            'SHS' => [
                ['name' => 'Science, Technology, Engineering, and Mathematics (STEM)', 'abbreviation' => 'STEM'],
                ['name' => 'Humanities and Social Sciences (HUMMS)', 'abbreviation' => 'HUMMS'],
                ['name' => 'Accountancy, Business, and Management (ABM)', 'abbreviation' => 'ABM'],
                ['name' => 'Mobile App and Web Development (MAWD)', 'abbreviation' => 'MAWD'],
                ['name' => 'Digital Arts (DA)', 'abbreviation' => 'DA'],
                ['name' => 'Tourism Operations (TOPER)', 'abbreviation' => 'TOPER'],
                ['name' => 'Culinary Arts (CA)', 'abbreviation' => 'CA']
            ],
            'COLLEGE STUDENT' => [
                ['name' => 'Bachelor of Science in Computer Science (BSCS)', 'abbreviation' => 'BSCS'],
                ['name' => 'Bachelor of Science in Information Technology (BSIT)', 'abbreviation' => 'BSIT'],
                ['name' => 'Bachelor of Science in Computer Engineering (BSCPE)', 'abbreviation' => 'BSCPE'],
                ['name' => 'Bachelor of Science in Culinary Management (BSCM)', 'abbreviation' => 'BSCM'],
                ['name' => 'Bachelor of Science in Tourism Management (BSTM)', 'abbreviation' => 'BSTM'],
                ['name' => 'Bachelor of Science in Business Administration (BSBA)', 'abbreviation' => 'BSBA'],
                ['name' => 'Bachelor of Science in Multimedia Arts (BMMA)', 'abbreviation' => 'BMMA']
            ],
            'EMPLOYEE' => [
                ['name' => 'TEACHER', 'abbreviation' => 'TEACHER'],
                ['name' => 'PAMO', 'abbreviation' => 'PAMO'],
                ['name' => 'ADMIN', 'abbreviation' => 'ADMIN'],
                ['name' => 'STAFF', 'abbreviation' => 'STAFF']
            ]
        ];
        
        echo json_encode($fallbackPrograms[$category] ?? []);
        exit;
    }
    
    echo json_encode($programs);
    
} catch (Exception $e) {
    // Return fallback data on any error
    $fallbackPrograms = [
        'SHS' => [
            ['name' => 'Science, Technology, Engineering, and Mathematics (STEM)', 'abbreviation' => 'STEM'],
            ['name' => 'Humanities and Social Sciences (HUMMS)', 'abbreviation' => 'HUMMS'],
            ['name' => 'Accountancy, Business, and Management (ABM)', 'abbreviation' => 'ABM'],
            ['name' => 'Mobile App and Web Development (MAWD)', 'abbreviation' => 'MAWD'],
            ['name' => 'Digital Arts (DA)', 'abbreviation' => 'DA'],
            ['name' => 'Tourism Operations (TOPER)', 'abbreviation' => 'TOPER'],
            ['name' => 'Culinary Arts (CA)', 'abbreviation' => 'CA']
        ],
        'COLLEGE STUDENT' => [
            ['name' => 'Bachelor of Science in Computer Science (BSCS)', 'abbreviation' => 'BSCS'],
            ['name' => 'Bachelor of Science in Information Technology (BSIT)', 'abbreviation' => 'BSIT'],
            ['name' => 'Bachelor of Science in Computer Engineering (BSCPE)', 'abbreviation' => 'BSCPE'],
            ['name' => 'Bachelor of Science in Culinary Management (BSCM)', 'abbreviation' => 'BSCM'],
            ['name' => 'Bachelor of Science in Tourism Management (BSTM)', 'abbreviation' => 'BSTM'],
            ['name' => 'Bachelor of Science in Business Administration (BSBA)', 'abbreviation' => 'BSBA'],
            ['name' => 'Bachelor of Science in Multimedia Arts (BMMA)', 'abbreviation' => 'BMMA']
        ],
        'EMPLOYEE' => [
            ['name' => 'TEACHER', 'abbreviation' => 'TEACHER'],
            ['name' => 'PAMO', 'abbreviation' => 'PAMO'],
            ['name' => 'ADMIN', 'abbreviation' => 'ADMIN'],
            ['name' => 'STAFF', 'abbreviation' => 'STAFF']
        ]
    ];
    
    $category = $_GET['category'] ?? '';
    echo json_encode($fallbackPrograms[$category] ?? []);
}
?>
