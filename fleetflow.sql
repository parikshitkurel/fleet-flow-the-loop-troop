-- FleetFlow Database Schema & Seed Data
-- Compatible with MySQL 5.7+ / MariaDB 10+

CREATE DATABASE IF NOT EXISTS fleetflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fleetflow;

-- ─────────────────────────────────────────
-- USERS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','fleet_manager','dispatcher','safety_officer','financial_analyst') NULL DEFAULT NULL,
    status ENUM('active','pending','suspended') DEFAULT 'pending',
    phone VARCHAR(10) NULL,
    otp_code VARCHAR(10) NULL,
    otp_expires DATETIME NULL,
    otp_attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- ─────────────────────────────────────────
-- VEHICLES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model VARCHAR(100) NOT NULL,
    license_plate VARCHAR(20) NOT NULL UNIQUE,
    max_capacity DECIMAL(8,2) NOT NULL COMMENT 'in kg',
    odometer INT NOT NULL DEFAULT 0 COMMENT 'in km',
    status ENUM('Available','On Trip','In Shop','Out of Service') NOT NULL DEFAULT 'Available',
    year YEAR,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_license (license_plate)
);

-- ─────────────────────────────────────────
-- DRIVERS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    license_number VARCHAR(50) NOT NULL UNIQUE,
    license_expiry DATE NOT NULL,
    safety_score TINYINT NOT NULL DEFAULT 100 COMMENT '0-100',
    status ENUM('Available','On Duty','Suspended') NOT NULL DEFAULT 'Available',
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_expiry (license_expiry)
);

-- ─────────────────────────────────────────
-- TRIPS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    driver_id INT NOT NULL,
    origin VARCHAR(150) NOT NULL,
    destination VARCHAR(150) NOT NULL,
    cargo_description VARCHAR(255),
    cargo_weight DECIMAL(8,2) NOT NULL COMMENT 'in kg',
    status ENUM('Draft','Dispatched','Completed','Cancelled') NOT NULL DEFAULT 'Draft',
    scheduled_date DATE NOT NULL,
    completed_date DATE,
    distance_km INT COMMENT 'filled on completion',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON UPDATE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON UPDATE CASCADE,
    INDEX idx_status (status),
    INDEX idx_date (scheduled_date)
);

-- ─────────────────────────────────────────
-- MAINTENANCE LOGS
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id VARCHAR(12) NULL UNIQUE COMMENT 'Auto-generated: LOG-XXXXXX',
    vehicle_id INT NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    description TEXT,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    service_date DATE NOT NULL,
    technician VARCHAR(100),
    status ENUM('Scheduled','In Progress','Completed') DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON UPDATE CASCADE,
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_date (service_date)
);

-- ─────────────────────────────────────────
-- FUEL EXPENSES
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS fuel_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    liters DECIMAL(8,2) NOT NULL,
    cost_per_liter DECIMAL(6,3) NOT NULL,
    total_cost DECIMAL(10,2) GENERATED ALWAYS AS (liters * cost_per_liter) STORED,
    odometer_reading INT NOT NULL,
    expense_date DATE NOT NULL,
    station VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON UPDATE CASCADE,
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_date (expense_date)
);

-- ─────────────────────────────────────────
-- SEED DATA
-- ─────────────────────────────────────────

-- Admin: '12345678' | Demo staff: 'Fleet@123'
INSERT INTO users (name, email, password, role, status, phone) VALUES
('Parikshit Kurel', 'parikshitkurel@gmail.com', '$2y$10$F6Q.nurTghGyz1.k5Zopdu8.g3OmJEZKUY3R081DwuIeEOq1kAxA6', 'admin', 'active', '9826278811'),
('Lochan Garg', 'lochangarg006@gmail.com', '$2y$10$9SCLlq/lIXpAdHCuat2o4.mAP0/QjYJHIXVbVXD2eCWc76Pk1rmKu', 'fleet_manager', 'active', '9754981695'),
('Aashmita Tiwari', 'aashmitatiwari@gmail.com', '$2y$10$t89uOLPIdxEotGcJECG0SeZccD.5wkiqv1Nm1PEMAImIor6IvZK3.', 'financial_analyst', 'active', '9098280466'),
('Prince Jaiswal', 'prince.jaiswal@gmail.com', '$2y$10$E808tLEqYDk8DwBHrUPo1.3K2UTTJy04ma0716dgjf.8zmpQ4eP1.', 'dispatcher', 'active', '9302256107'),
('Aakarshan Indori', 'indori.aakarshan@gmail.com', '$2y$10$9Ek7/cmvmpF4zIflqNweDuz3L1N152pLJx1rlTDPaHjOyQ8rN4Nme', 'safety_officer', 'active', '8236009709');

-- Vehicles
INSERT INTO vehicles (model, license_plate, max_capacity, odometer, status, year) VALUES
('Tata Ace Gold',          'MP09 AB 1234', 1000, 42500,  'Available',     2022),
('Ashok Leyland Dost',     'DL01 CV 4582', 1250, 28300,  'On Trip',       2021),
('Mahindra Bolero Pickup', 'MH12 TR 9087', 1700, 60800,  'In Shop',       2020),
('Tata Intra V30',         'RJ14 GA 3321', 1300, 15700,  'Available',     2023),
('Eicher Pro 2049',        'UP32 BN 5566', 2500, 78400,  'Out of Service',2019);

-- Drivers
INSERT INTO drivers (name, license_number, license_expiry, safety_score, status, phone) VALUES
('Mahesh Yadav',    'DL-IND-2024-01', '2026-08-15', 98,  'Available', '9876500001'),
('Suresh Chauhan',  'DL-IND-2024-02', '2025-03-10', 91,  'On Duty',   '9876500002'),
('Rakesh Meena',    'DL-IND-2024-03', '2024-11-30', 76,  'Available', '9876500003'),
('Deepak Tiwari',   'DL-IND-2024-04', '2026-12-01', 95,  'Available', '9876500004'),
('Vijay Pal',       'DL-IND-2024-05', '2027-05-20', 88,  'Suspended', '9876500005'),
('Manoj Kumar',     'DL-IND-2024-06', '2026-09-30', 100, 'Available', '9876500006');

-- Trips
INSERT INTO trips (vehicle_id, driver_id, origin, destination, cargo_description, cargo_weight, status, scheduled_date, completed_date, distance_km) VALUES
(2, 2, 'Indore, MP',   'Bhopal, MP',   'Auto Parts',      850,   'Dispatched', CURDATE(), NULL,       195),
(1, 1, 'Delhi',        'Jaipur, RJ',   'Retail Goods',    500,   'Completed',  DATE_SUB(CURDATE(),INTERVAL 3 DAY), DATE_SUB(CURDATE(),INTERVAL 2 DAY), 270),
(4, 4, 'Mumbai, MH',   'Pune, MH',     'Electronics',     900,   'Completed',  DATE_SUB(CURDATE(),INTERVAL 7 DAY), DATE_SUB(CURDATE(),INTERVAL 6 DAY), 150),
(1, 6, 'Ahmedabad, GJ','Mumbai, MH',   'Medical Supplies',400,   'Draft',      DATE_ADD(CURDATE(),INTERVAL 2 DAY), NULL,       530);

-- Maintenance Logs
INSERT INTO maintenance_logs (vehicle_id, service_type, description, cost, service_date, technician, status) VALUES
(3, 'Engine Service',     'Oil change and filter replacement',            4500.00, DATE_SUB(CURDATE(),INTERVAL 2 DAY), 'Suresh Verma',   'In Progress'),
(5, 'Brake Repair',       'Brake pad replacement and drum cleaning',      3200.00, DATE_SUB(CURDATE(),INTERVAL 14 DAY),'Amit Kashyap',   'Completed'),
(1, 'General Checkup',    'Routine maintenance and alignment',            1500.00, DATE_SUB(CURDATE(),INTERVAL 30 DAY),'Suresh Verma',   'Completed'),
(2, 'Tyre Rotation',      'Alignment and wheel balancing',                 1200.00, DATE_SUB(CURDATE(),INTERVAL 5 DAY), 'Amit Kashyap',   'Completed');

-- Fuel Expenses
INSERT INTO fuel_expenses (vehicle_id, liters, cost_per_liter, odometer_reading, expense_date, station) VALUES
(1, 40.00, 95.45, 42000, DATE_SUB(CURDATE(),INTERVAL 4 DAY),  'HP Petrol - Indore'),
(2, 60.00, 96.52, 27800,  DATE_SUB(CURDATE(),INTERVAL 1 DAY),  'Indian Oil - Delhi'),
(4, 45.00, 94.48, 15400,  DATE_SUB(CURDATE(),INTERVAL 8 DAY),  'Bharat Petroleum - Jaipur'),
(1, 35.00, 95.50, 41500, DATE_SUB(CURDATE(),INTERVAL 12 DAY), 'HP Petrol - Bhopal'),
(3, 50.00, 95.44, 60300,  DATE_SUB(CURDATE(),INTERVAL 3 DAY),  'Indian Oil - Mumbai'),
(2, 45.00, 96.55, 27200,  DATE_SUB(CURDATE(),INTERVAL 9 DAY),  'Bharat Petroleum - Pune');
