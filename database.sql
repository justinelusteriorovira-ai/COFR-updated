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

-- Predefined facilities (original)
INSERT INTO facilities (name, description, capacity, status, price_per_hour, price_per_day, open_time, close_time)
VALUES
('FORVM GYM', 'Basketball court with seating area.', 10, 'AVAILABLE', 0.00, 0.00, '07:00:00', '20:00:00'),
('Conference Room', 'Conference room with projector and seating for 20.', 20, 'AVAILABLE', 0.00, 0.00, '07:00:00', '20:00:00');

-- Additional predefined facilities
INSERT INTO facilities (name, description, capacity, status, price_per_hour, price_per_day, open_time, close_time, min_duration_hours, max_duration_hours)
VALUES
(
    'Audio-Visual Room',
    'Fully equipped AV room with large screen, surround sound system, and 50-seat theater-style arrangement. Ideal for seminars, film screenings, and presentations.',
    50, 'AVAILABLE', 200.00, 1500.00, '07:00:00', '20:00:00', 1, 8
),
(
    'Open Pavilion',
    'Covered outdoor pavilion suitable for large gatherings, socials, and cultural events. Features built-in stage and PA system.',
    150, 'AVAILABLE', 500.00, 3500.00, '06:00:00', '22:00:00', 2, 12
),
(
    'Computer Laboratory',
    'Air-conditioned computer lab with 40 workstations, high-speed internet, and a projector. Available for training sessions and workshops.',
    40, 'AVAILABLE', 300.00, 2000.00, '07:00:00', '19:00:00', 1, 6
);

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

-- ─── Test Reservations — Online Users (Facebook) ───────
INSERT INTO reservations (
    fb_user_id, user_email, user_phone, fb_name,
    facility_id, reservation_date, start_time, end_time,
    purpose, duration_hours, total_cost, num_attendees,
    status, reservation_type, user_type, id_number, host_person
) VALUES
(
    'fb_100001', 'juan.delacruz@email.com', '09171234567', 'Juan Dela Cruz',
    1, '2026-03-20', '08:00:00', '10:00:00',
    'Basketball practice session for the intramural team', 2.0, 0.00, 10,
    'APPROVED', 'ONLINE', 'FACEBOOK', 'STU-2024-001', 'Juan Dela Cruz'
),
(
    'fb_100002', 'maria.santos@email.com', '09289876543', 'Maria Santos',
    2, '2026-03-22', '13:00:00', '16:00:00',
    'Department meeting and quarterly review presentation', 3.0, 0.00, 18,
    'PENDING', 'ONLINE', 'FACEBOOK', 'STU-2024-002', 'Maria Santos'
),
(
    'fb_100003', 'carlo.reyes@email.com', '09351122334', 'Carlo Reyes',
    3, '2026-03-25', '09:00:00', '12:00:00',
    'Leadership seminar for student council officers', 3.0, 600.00, 45,
    'PENDING_VERIFICATION', 'ONLINE', 'FACEBOOK', 'STU-2024-003', 'Carlo Reyes'
);

-- ─── Test Reservations — Walk-In Clients ───────────────
INSERT INTO reservations (
    fb_user_id, user_email, user_phone, fb_name,
    facility_id, reservation_date, start_time, end_time,
    purpose, duration_hours, total_cost, num_attendees,
    status, reservation_type, user_type, id_number, host_person
) VALUES
(
    'WALKIN_001', 'ana.villanueva@gmail.com', '09421234567', 'Ana Villanueva',
    4, '2026-03-18', '14:00:00', '18:00:00',
    'Community outreach program and livelihood skills workshop', 4.0, 14000.00, 120,
    'APPROVED', 'WALK_IN', 'WALK_IN', 'GOV-ID-78123', 'Ana Villanueva'
),
(
    'WALKIN_002', 'roberto.lim@company.com', '09509988776', 'Roberto Lim',
    5, '2026-03-19', '08:00:00', '11:00:00',
    'IT training and software orientation for new employees', 3.0, 900.00, 35,
    'APPROVED', 'WALK_IN', 'WALK_IN', 'EMP-2025-099', 'Roberto Lim'
),
(
    'WALKIN_003', NULL, '09661239090', 'Barangay Punta Representatives',
    4, '2026-04-05', '16:00:00', '21:00:00',
    'Barangay fiesta celebration and community gathering', 5.0, 17500.00, 140,
    'ON_HOLD', 'WALK_IN', 'WALK_IN', 'BRGY-CERT-441', 'Kagawad Nilo Flores'
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

-- Original seed occasions
INSERT INTO special_occasions (title, occasion_date, type, description, color) VALUES
('Independence Day', '2026-06-12', 'HOLIDAY', 'National Holiday in the Philippines', '#e74c3c'),
('CEFI Foundation Day', '2026-03-15', 'SCHOOL_EVENT', 'Foundation day celebrations', '#8e44ad');

-- Additional calendar events
INSERT INTO special_occasions (title, occasion_date, end_date, type, description, color, is_recurring) VALUES
(
    'Holy Week Break',
    '2026-03-30', '2026-04-05',
    'BLOCKED',
    'No facility reservations during Holy Week observance. Campus closed.',
    '#e67e22', 0
),
(
    'Acquaintance Party & Welcome Ceremony',
    '2026-04-10', NULL,
    'SCHOOL_EVENT',
    'Annual acquaintance party for new and returning students. Open Pavilion reserved for the whole day.',
    '#27ae60', 0
),
(
    'Linggo ng Wika Celebration',
    '2026-08-17', '2026-08-21',
    'SCHOOL_EVENT',
    'Week-long celebration of Filipino language and culture. Various indoor events scheduled across facilities.',
    '#2980b9', 1
),
(
    'Founding Anniversary of CEFI — No Classes',
    '2026-09-05', NULL,
    'HOLIDAY',
    'Institutional holiday. Reservations suspended for the day.',
    '#e74c3c', 0
),
(
    'Facility Maintenance Week',
    '2026-05-04', '2026-05-08',
    'BLOCKED',
    'Annual preventive maintenance for all facilities. No reservations accepted during this period.',
    '#7f8c8d', 0
);

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