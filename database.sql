CREATE DATABASE cefi_reservation;
USE cefi_reservation;

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    capacity INT NOT NULL,
    status ENUM('AVAILABLE','MAINTENANCE','CLOSED') DEFAULT 'AVAILABLE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fb_user_id VARCHAR(100) NOT NULL,
    fb_name VARCHAR(100) NOT NULL,
    facility_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose TEXT,
    status ENUM('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES facilities(id)
);

-- predifine facilities
INSERT INTO facilities (name, description, capacity, status)
VALUES 
('FORVM GYM', 'basketball court with seating area.', 10, 'AVAILABLE'),
('Conference Room', 'conference room with projector and seating for 20.', 20, 'AVAILABLE');



-- username: admin, password: admin123 (hashed using BisayaCRYPT)
INSERT INTO admins (username, password)
VALUES ('admin', '$2y$10$4zBw69eKY/1GOVpuYsVMu.5P94LhxgQH7vs7xSn82qCtxX74pHb.e');

-- SPECIAL OCCASIONS TABLE
CREATE TABLE special_occasions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    occasion_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,         -- optional multi-day events
    type ENUM('HOLIDAY','SCHOOL_EVENT','BLOCKED','ANNOUNCEMENT') DEFAULT 'SCHOOL_EVENT',
    description TEXT,
    color VARCHAR(7) DEFAULT '#8e44ad', -- hex color for the chip on calendar
    is_recurring TINYINT(1) DEFAULT 0,  -- yearly recurring flag
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed some sample occasions
INSERT INTO special_occasions (title, occasion_date, type, description, color) VALUES
('Independence Day', '2026-06-12', 'HOLIDAY', 'National Holiday in the Philippines', '#e74c3c'),
('CEFI Foundation Day', '2026-03-15', 'SCHOOL_EVENT', 'Foundation day celebrations', '#8e44ad');

-- AUDIT LOGS TABLE
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(50) NOT NULL, -- e.g., 'CREATE', 'UPDATE', 'DELETE', 'LOGIN'
    entity_type VARCHAR(50) NOT NULL, -- e.g., 'RESERVATION', 'FACILITY', 'OCCASION'
    entity_id INT,
    details TEXT,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);