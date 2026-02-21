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
    role ENUM('fleet_manager','dispatcher','safety_officer','financial_analyst') NOT NULL DEFAULT 'dispatcher',
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

-- Users (passwords are bcrypt of 'password123')
INSERT INTO users (name, email, password, role) VALUES
('Alice Morgan',    'manager@fleetflow.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'fleet_manager'),
('Bob Chen',        'dispatch@fleetflow.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'dispatcher'),
('Carol Stevens',   'safety@fleetflow.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'safety_officer'),
('David Park',      'finance@fleetflow.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'financial_analyst');

-- Vehicles
INSERT INTO vehicles (model, license_plate, max_capacity, odometer, status, year) VALUES
('Volvo FH16 750',      'TRK-001-AA', 24000, 142500, 'Available',     2021),
('Mercedes Actros 1845','TRK-002-BB', 22000, 98300,  'On Trip',       2020),
('MAN TGX 18.510',      'TRK-003-CC', 20000, 210800, 'In Shop',       2019),
('DAF XF 480',          'TRK-004-DD', 18000, 55700,  'Available',     2022),
('Scania R500',         'TRK-005-EE', 26000, 178400, 'Out of Service',2018);

-- Drivers
INSERT INTO drivers (name, license_number, license_expiry, safety_score, status, phone) VALUES
('James Wilson',   'DL-2024-001', '2026-08-15', 98,  'Available', '+1-555-0101'),
('Maria Santos',   'DL-2024-002', '2025-03-10', 91,  'On Duty',   '+1-555-0102'),
('Kevin O\'Brien',  'DL-2024-003', '2024-11-30', 76,  'Available', '+1-555-0103'),
('Lisa Zhang',     'DL-2024-004', '2026-12-01', 95,  'Available', '+1-555-0104'),
('Omar Hassan',    'DL-2024-005', '2027-05-20', 88,  'Suspended', '+1-555-0105'),
('Tanya Brooks',   'DL-2024-006', '2026-09-30', 100, 'Available', '+1-555-0106');

-- Trips
INSERT INTO trips (vehicle_id, driver_id, origin, destination, cargo_description, cargo_weight, status, scheduled_date, completed_date, distance_km) VALUES
(2, 2, 'Chicago, IL',  'Detroit, MI',   'Auto Parts',      18500, 'Dispatched', CURDATE(), NULL,       450),
(1, 1, 'Dallas, TX',   'Houston, TX',   'Retail Goods',    12000, 'Completed',  DATE_SUB(CURDATE(),INTERVAL 3 DAY), DATE_SUB(CURDATE(),INTERVAL 2 DAY), 390),
(4, 4, 'Phoenix, AZ',  'Las Vegas, NV', 'Electronics',     9800,  'Completed',  DATE_SUB(CURDATE(),INTERVAL 7 DAY), DATE_SUB(CURDATE(),INTERVAL 6 DAY), 480),
(1, 6, 'Seattle, WA',  'Portland, OR',  'Building Supplies',14000,'Draft',      DATE_ADD(CURDATE(),INTERVAL 2 DAY), NULL,       280);

-- Maintenance Logs
INSERT INTO maintenance_logs (vehicle_id, service_type, description, cost, service_date, technician, status) VALUES
(3, 'Engine Overhaul',    'Full engine rebuild due to oil leak',          4500.00, DATE_SUB(CURDATE(),INTERVAL 2 DAY), 'Mike Turner',    'In Progress'),
(5, 'Brake Replacement',  'Front and rear brake pads and rotors replaced',1200.00, DATE_SUB(CURDATE(),INTERVAL 14 DAY),'Sam Rodriguez',  'Completed'),
(1, 'Oil Change',         'Synthetic 15W-40 full service',                 350.00, DATE_SUB(CURDATE(),INTERVAL 30 DAY),'Mike Turner',    'Completed'),
(2, 'Tire Rotation',      'All 6 tires rotated and balanced',              280.00, DATE_SUB(CURDATE(),INTERVAL 5 DAY), 'Sam Rodriguez',  'Completed');

-- Fuel Expenses
INSERT INTO fuel_expenses (vehicle_id, liters, cost_per_liter, odometer_reading, expense_date, station) VALUES
(1, 180.00, 1.45, 142000, DATE_SUB(CURDATE(),INTERVAL 4 DAY),  'Shell - I-35'),
(2, 220.00, 1.52, 97800,  DATE_SUB(CURDATE(),INTERVAL 1 DAY),  'Pilot - I-94'),
(4, 150.00, 1.48, 55400,  DATE_SUB(CURDATE(),INTERVAL 8 DAY),  'Love\'s - US-60'),
(1, 195.00, 1.50, 141500, DATE_SUB(CURDATE(),INTERVAL 12 DAY), 'TA - I-45'),
(3, 160.00, 1.44, 210300, DATE_SUB(CURDATE(),INTERVAL 3 DAY),  'Petro - I-10'),
(2, 200.00, 1.55, 97200,  DATE_SUB(CURDATE(),INTERVAL 9 DAY),  'Pilot - I-90');
