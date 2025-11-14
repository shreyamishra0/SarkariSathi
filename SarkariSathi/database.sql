CREATE DATABASE IF NOT EXISTS sarkari_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sarkari_connect;

-- Users table (updated to include admin and make email required)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role ENUM('citizen', 'officer', 'admin') NOT NULL,
    phone VARCHAR(15) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    office_name VARCHAR(100),
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

-- Sections table
CREATE TABLE IF NOT EXISTS sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    officer_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    required_docs TEXT,
    estimated_days INT,
    fee_amount DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Posts table
CREATE TABLE IF NOT EXISTS posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    officer_id INT NOT NULL,
    section_id INT NOT NULL,
    post_type ENUM('video', 'photo', 'text') NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    media_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    INDEX idx_section (section_id)
) ENGINE=InnoDB;

-- Queue table
CREATE TABLE IF NOT EXISTS queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    citizen_id INT NOT NULL,
    section_id INT NOT NULL,
    application_id INT NULL,
    visit_type ENUM('submission', 'pickup', 'inquiry') NOT NULL,
    queue_date DATE NOT NULL,
    time_slot TIME NOT NULL,
    queue_number VARCHAR(10) NOT NULL,
    status ENUM('booked', 'checked_in', 'in_service', 'completed', 'no_show') DEFAULT 'booked',
    checked_in_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    INDEX idx_queue_date (queue_date, status),
    INDEX idx_citizen (citizen_id)
) ENGINE=InnoDB;

-- Applications table
CREATE TABLE IF NOT EXISTS applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    citizen_id INT NOT NULL,
    section_id INT NOT NULL,
    tracking_number VARCHAR(20) UNIQUE NOT NULL,
    status ENUM('submitted', 'document_verification', 'biometric_processing', 
                'external_verification', 'printing', 'ready_for_pickup', 
                'completed', 'rejected') DEFAULT 'submitted',
    officer_notes TEXT,
    rejection_reason TEXT,
    submitted_date DATE NOT NULL,
    ready_date DATE NULL,
    pickup_date DATE NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_tracking (tracking_number),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Status history table
CREATE TABLE IF NOT EXISTS status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    application_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL,
    notes TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    section_id INT NULL,
    application_id INT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation (sender_id, receiver_id),
    INDEX idx_unread (receiver_id, is_read)
) ENGINE=InnoDB;

-- Complaints table
CREATE TABLE IF NOT EXISTS complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    citizen_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(50),
    description TEXT NOT NULL,
    location VARCHAR(200),
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    image_path VARCHAR(500),
    status ENUM('pending', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending',
    admin_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50),
    title VARCHAR(200),
    message TEXT NOT NULL,
    link VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read)
) ENGINE=InnoDB;

-- Admin logs table (for security tracking)
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Insert demo admin account
-- Password: admin123
INSERT INTO users (role, phone, name, email, password_hash, is_verified) VALUES
('admin', '9800000000', 'System Administrator', 'admin@sarkarisathi.gov.np', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON DUPLICATE KEY UPDATE email = email;

-- Insert demo officer and citizen with required email
-- Password for both: password123
INSERT INTO users (role, phone, name, email, password_hash, office_name, is_verified) VALUES
('officer', '9841234567', 'Ram Sharma', 'ram.sharma@government.gov.np', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kathmandu District Office', 1),
('citizen', '9851234567', 'Sita Devi', 'sita.devi@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 1)
ON DUPLICATE KEY UPDATE email = email;