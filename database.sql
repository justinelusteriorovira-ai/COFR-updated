-- ═══════════════════════════════════════════════════════
-- CEFI ONLINE FACILITY RESERVATION — Full Schema
-- Generated: 2026-03-12  (matches live codebase)
-- ═══════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS cefi_reservation;
USE cefi_reservation;

-- ─── Admins ────────────────────────────────────────────
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- username: admin, password: admin123
INSERT INTO admins (username, password)
VALUES ('admin', '$2y$10$4zBw69eKY/1GOVpuYsVMu.5P94LhxgQH7vs7xSn82qCtxX74pHb.e');

-- ─── Facilities ────────────────────────────────────────
CREATE TABLE facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    capacity INT NOT NULL,
    status ENUM('AVAILABLE','MAINTENANCE','CLOSED') DEFAULT 'AVAILABLE',
    price_per_hour DECIMAL(10,2) DEFAULT 0,
    price_per_day DECIMAL(10,2) DEFAULT 0,
    open_time TIME DEFAULT '07:00:00',
    close_time TIME DEFAULT '20:00:00',
    advance_days_required INT DEFAULT 2,
    min_duration_hours INT DEFAULT 1,
    max_duration_hours INT DEFAULT 8,
    allowed_days VARCHAR(20) DEFAULT '1,2,3,4,5,6',
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Predefined facilities
INSERT INTO facilities (name, description, capacity, status)
VALUES
('FORVM GYM', 'Basketball court with seating area.', 10, 'AVAILABLE'),
('Conference Room', 'Conference room with projector and seating for 20.', 20, 'AVAILABLE');

-- ─── Reservations ──────────────────────────────────────
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fb_user_id VARCHAR(100) NOT NULL,
    user_email VARCHAR(255),
    user_phone VARCHAR(20),
    fb_name VARCHAR(100) NOT NULL,
    facility_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose TEXT,
    duration_hours DECIMAL(4,1),
    total_cost DECIMAL(10,2),
    num_attendees INT,
    status ENUM('PENDING','APPROVED','REJECTED','PENDING_VERIFICATION','EXPIRED','CANCELLED','ON_HOLD','WAITLISTED') DEFAULT 'PENDING',
    reject_reason TEXT,
    approval_reason TEXT,
    cancel_reason TEXT,
    cancelled_at DATETIME,
    admin_notes TEXT,
    verification_deadline DATETIME,
    verified_at DATETIME,
    reservation_type ENUM('ONLINE','WALK_IN') DEFAULT 'ONLINE',
    user_type VARCHAR(20) DEFAULT 'FACEBOOK',
    id_number VARCHAR(50),
    host_person VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES facilities(id)
);

-- ─── Special Occasions ────────────────────────────────
CREATE TABLE special_occasions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    occasion_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    type ENUM('HOLIDAY','SCHOOL_EVENT','BLOCKED','ANNOUNCEMENT') DEFAULT 'SCHOOL_EVENT',
    description TEXT,
    color VARCHAR(7) DEFAULT '#8e44ad',
    is_recurring TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed special occasions
INSERT INTO special_occasions (title, occasion_date, type, description, color) VALUES
('Independence Day', '2026-06-12', 'HOLIDAY', 'National Holiday in the Philippines', '#e74c3c'),
('CEFI Foundation Day', '2026-03-15', 'SCHOOL_EVENT', 'Foundation day celebrations', '#8e44ad');

-- ─── Audit Logs ────────────────────────────────────────
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    details TEXT,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
);