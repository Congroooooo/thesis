-- Database schema updates for enhanced admin management

-- 1. Add birthday column to account table
ALTER TABLE account ADD COLUMN birthday DATE AFTER last_name;

-- 2. Create programs_positions table for dynamic management
CREATE TABLE IF NOT EXISTS programs_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category ENUM('SHS', 'COLLEGE STUDENT', 'EMPLOYEE') NOT NULL,
    abbreviation VARCHAR(20),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name_category (name, category)
);

-- 3. Insert existing programs/positions into the new table
INSERT INTO programs_positions (name, category, abbreviation) VALUES
-- SHS Programs
('Science, Technology, Engineering, and Mathematics', 'SHS', 'STEM'),
('Humanities and Social Sciences', 'SHS', 'HUMMS'),
('Accountancy, Business, and Management', 'SHS', 'ABM'),
('Mobile App and Web Development', 'SHS', 'MAWD'),
('Digital Arts', 'SHS', 'DA'),
('Tourism Operations', 'SHS', 'TOPER'),
('Culinary Arts', 'SHS', 'CA'),

-- College Programs
('Bachelor of Science in Computer Science', 'COLLEGE STUDENT', 'BSCS'),
('Bachelor of Science in Information Technology', 'COLLEGE STUDENT', 'BSIT'),
('Bachelor of Science in Computer Engineering', 'COLLEGE STUDENT', 'BSCPE'),
('Bachelor of Science in Culinary Management', 'COLLEGE STUDENT', 'BSCM'),
('Bachelor of Science in Tourism Management', 'COLLEGE STUDENT', 'BSTM'),
('Bachelor of Science in Business Administration', 'COLLEGE STUDENT', 'BSBA'),
('Bachelor of Science in Multimedia Arts', 'COLLEGE STUDENT', 'BMMA'),

-- Employee Positions
('TEACHER', 'EMPLOYEE', 'TEACHER'),
('PAMO', 'EMPLOYEE', 'PAMO'),
('ADMIN', 'EMPLOYEE', 'ADMIN'),
('STAFF', 'EMPLOYEE', 'STAFF');

-- 4. Create audit log table for tracking admin actions
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_affected VARCHAR(50),
    record_id VARCHAR(50),
    old_values JSON,
    new_values JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45)
);
