-- FleetFlow ERP Database Schema

CREATE DATABASE IF NOT EXISTS fleetflow;
USE fleetflow;

-- Vehicles Table
CREATE TABLE IF NOT EXISTS vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id VARCHAR(50) UNIQUE NOT NULL,
  model VARCHAR(100) NOT NULL,
  capacity DECIMAL(10, 2) NOT NULL,
  odometer DECIMAL(10, 2) DEFAULT 0,
  status ENUM('Available', 'On Trip', 'In Shop', 'Suspended') DEFAULT 'Available',
  fuel_level DECIMAL(5, 2) DEFAULT 100,
  last_service_date DATE,
  next_service_km DECIMAL(10, 2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Drivers Table
CREATE TABLE IF NOT EXISTS drivers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  license_number VARCHAR(50) UNIQUE NOT NULL,
  license_expiry DATE NOT NULL,
  phone VARCHAR(20),
  email VARCHAR(100),
  risk_score INT DEFAULT 0,
  risk_level ENUM('Low', 'Medium', 'High') DEFAULT 'Low',
  safety_score INT DEFAULT 100,
  trip_completion_rate DECIMAL(5, 2) DEFAULT 100,
  fuel_efficiency_score DECIMAL(5, 2) DEFAULT 0,
  monthly_bonus_awarded BOOLEAN DEFAULT FALSE,
  status ENUM('Active', 'Suspended', 'Inactive') DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Trips Table
CREATE TABLE IF NOT EXISTS trips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id VARCHAR(50) UNIQUE NOT NULL,
  vehicle_id INT NOT NULL,
  driver_id INT NOT NULL,
  cargo_weight DECIMAL(10, 2) NOT NULL,
  origin VARCHAR(255) NOT NULL,
  destination VARCHAR(255) NOT NULL,
  status ENUM('Draft', 'Dispatched', 'On Trip', 'Completed', 'Cancelled') DEFAULT 'Draft',
  route_data JSON,
  estimated_distance DECIMAL(10, 2),
  actual_distance DECIMAL(10, 2),
  estimated_fuel DECIMAL(10, 2),
  actual_fuel DECIMAL(10, 2),
  start_time DATETIME,
  end_time DATETIME,
  qr_code VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  FOREIGN KEY (driver_id) REFERENCES drivers(id)
);

-- GPS Tracking Table
CREATE TABLE IF NOT EXISTS gps_tracking (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  trip_id INT,
  latitude DECIMAL(10, 8) NOT NULL,
  longitude DECIMAL(11, 8) NOT NULL,
  speed DECIMAL(5, 2) DEFAULT 0,
  heading DECIMAL(5, 2),
  timestamp DATETIME NOT NULL,
  status ENUM('On Trip', 'Available', 'Overspeed', 'Idle') DEFAULT 'Available',
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  FOREIGN KEY (trip_id) REFERENCES trips(id),
  INDEX idx_vehicle_timestamp (vehicle_id, timestamp)
);

-- Documents Table
CREATE TABLE IF NOT EXISTS documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_id INT,
  vehicle_id INT,
  document_type ENUM('License', 'Insurance', 'Permit', 'Registration') NOT NULL,
  document_number VARCHAR(100),
  expiry_date DATE NOT NULL,
  status ENUM('Verified', 'Pending', 'Rejected', 'Expiring Soon') DEFAULT 'Pending',
  file_path VARCHAR(255),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  verified_at TIMESTAMP NULL,
  FOREIGN KEY (driver_id) REFERENCES drivers(id),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

-- Maintenance Records Table
CREATE TABLE IF NOT EXISTS maintenance_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  maintenance_type VARCHAR(100) NOT NULL,
  service_date DATE,
  odometer_reading DECIMAL(10, 2),
  cost DECIMAL(10, 2),
  notes TEXT,
  predicted BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);

-- Driver Events Table (for risk scoring)
CREATE TABLE IF NOT EXISTS driver_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  driver_id INT NOT NULL,
  trip_id INT,
  event_type ENUM('Overspeed', 'Harsh Braking', 'Route Deviation', 'Emergency', 'Compliance Violation') NOT NULL,
  severity ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
  location_lat DECIMAL(10, 8),
  location_lng DECIMAL(11, 8),
  timestamp DATETIME NOT NULL,
  details JSON,
  FOREIGN KEY (driver_id) REFERENCES drivers(id),
  FOREIGN KEY (trip_id) REFERENCES trips(id)
);

-- Fuel Logs Table
CREATE TABLE IF NOT EXISTS fuel_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  trip_id INT,
  fuel_amount DECIMAL(10, 2) NOT NULL,
  fuel_cost DECIMAL(10, 2),
  odometer_reading DECIMAL(10, 2) NOT NULL,
  receipt_photo VARCHAR(255),
  anomaly_detected BOOLEAN DEFAULT FALSE,
  anomaly_reason TEXT,
  logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  FOREIGN KEY (trip_id) REFERENCES trips(id)
);

-- Alerts Table
CREATE TABLE IF NOT EXISTS alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type VARCHAR(50) NOT NULL,
  severity ENUM('Info', 'Warning', 'Critical') DEFAULT 'Info',
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  vehicle_id INT,
  driver_id INT,
  trip_id INT,
  read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
  FOREIGN KEY (driver_id) REFERENCES drivers(id),
  FOREIGN KEY (trip_id) REFERENCES trips(id)
);

-- Insert Sample Data
INSERT INTO vehicles (vehicle_id, model, capacity, odometer, status, fuel_level, next_service_km) VALUES
('V-001', 'Tata Ace', 750.00, 45230.00, 'Available', 85.00, 50000.00),
('V-002', 'Mahindra Bolero', 1200.00, 38920.00, 'On Trip', 65.00, 45000.00),
('V-003', 'Ashok Leyland Dost', 1000.00, 52100.00, 'In Shop', 90.00, 55000.00),
('V-004', 'Eicher Pro', 1500.00, 23450.00, 'Available', 75.00, 30000.00);

INSERT INTO drivers (name, license_number, license_expiry, phone, email, risk_score, risk_level, safety_score, trip_completion_rate, fuel_efficiency_score) VALUES
('Rajesh Kumar', 'DL-123456', '2025-12-31', '9876543210', 'rajesh@example.com', 25, 'Low', 95, 98.5, 92.3),
('Amit Singh', 'DL-234567', '2024-06-15', '9876543211', 'amit@example.com', 45, 'Medium', 88, 94.2, 89.7),
('Priya Sharma', 'DL-345678', '2026-03-20', '9876543212', 'priya@example.com', 15, 'Low', 98, 99.1, 95.2),
('Vikram Patel', 'DL-456789', '2025-08-10', '9876543213', 'vikram@example.com', 60, 'High', 75, 87.5, 82.1);

INSERT INTO trips (trip_id, vehicle_id, driver_id, cargo_weight, origin, destination, status, estimated_distance, start_time) VALUES
('TR-001', 1, 1, 650.00, 'Delhi', 'Mumbai', 'Completed', 1400.00, '2024-01-15 08:00:00'),
('TR-002', 2, 2, 1100.00, 'Mumbai', 'Pune', 'On Trip', 150.00, '2024-01-20 10:00:00'),
('TR-003', 4, 3, 1200.00, 'Bangalore', 'Chennai', 'Dispatched', 350.00, NULL);
