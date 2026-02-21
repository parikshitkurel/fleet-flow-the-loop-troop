
# FleetFlow — Enterprise Fleet Management System

FleetFlow is a production-grade, role-aware fleet management and operational intelligence platform. It is designed to replace manual fleet logbooks and fragmented tracking systems with a centralized, rule-driven digital solution.

The system enables organizations to manage vehicles, drivers, trips, maintenance activities, and financial performance within a single unified dashboard while enforcing operational compliance and data integrity.

---

## 1. System Overview

FleetFlow provides a structured environment where fleet operations are controlled through clearly defined workflows and validation rules.

The platform ensures:

* Centralized asset and driver management
* Real-time vehicle availability tracking
* Rule-based dispatch validation
* Automated maintenance status updates
* Financial performance monitoring
* Role-based secure access control

The system is built to reduce human error, increase operational transparency, and improve cost efficiency across logistics operations.

---

## 2. Core Functionalities

### 2.1 Intelligent Dashboard

The dashboard provides a real-time overview of fleet operations. It displays:

* Number of vehicles currently on trip
* Vehicles under maintenance
* Fleet utilization rate
* Operational cost summaries

The dashboard is designed for quick decision-making and visual clarity.

---

### 2.2 Role-Based Access Control (RBAC)

FleetFlow enforces strict access separation based on user roles.

Supported roles include:

* Administrator
* Fleet Manager
* Dispatcher
* Safety Officer
* Financial Analyst

Each role has restricted access to relevant modules, ensuring operational security and accountability.

---

### 2.3 Secure Authentication System

FleetFlow uses session-based authentication with server-side validation.

#### Login Process

* Users authenticate using registered email and password.
* Role verification determines accessible modules.

#### Forgot Password – OTP-Based Recovery

FleetFlow integrates an API-based OTP (One-Time Password) verification mechanism for secure password recovery.

When a user selects “Forgot Password”:

1. The system calls a backend API.
2. The API sends an OTP to the registered email address.
3. The user must enter the correct OTP for verification.
4. Password reset is permitted only after successful OTP validation.

This ensures secure and verified account recovery while preventing unauthorized access.

---

### 2.4 Vehicle Registry

The Vehicle Registry module provides complete CRUD operations for fleet assets.

Each vehicle record includes:

* Vehicle name and model
* Unique license plate number
* Maximum load capacity
* Odometer reading
* Current operational status

Vehicle statuses include:

* Available
* On Trip
* In Shop
* Retired

The system automatically updates vehicle availability based on dispatch and maintenance actions.

---

### 2.5 Driver Management and Performance Tracking

Driver profiles maintain operational and compliance data.

Each driver record includes:

* License category
* License expiry date
* Duty status (On Duty, Off Duty, Suspended)
* Safety score
* Trip completion rate

The system blocks trip assignments if:

* License is expired
* Driver is suspended
* Driver is off duty

This ensures regulatory compliance and operational safety.

---

### 2.6 Trip Dispatcher and Lifecycle Management

The Trip Dispatcher module controls logistics movement from creation to completion.

Trip lifecycle stages:

Draft → Dispatched → Completed → Cancelled

Validation rules enforced during dispatch:

* Cargo weight must not exceed vehicle capacity
* Vehicle must be Available
* Driver must be On Duty
* Driver license must be valid

Automatic updates:

* On dispatch, both vehicle and driver status change to On Trip.
* On completion, both revert to Available.
* If maintenance is logged, vehicle status changes to In Shop and is removed from the dispatch pool.

---

### 2.7 Maintenance and Fuel Logging

FleetFlow tracks operational costs per vehicle.

Maintenance logs include:

* Service type
* Date
* Cost

Fuel logs include:

* Fuel quantity (liters)
* Fuel cost
* Date

Automated calculations:

* Total Operational Cost = Fuel Cost + Maintenance Cost
* Cost per Kilometer = Total Operational Cost / Distance
* Vehicle ROI = (Revenue − (Maintenance + Fuel)) / Acquisition Cost

This enables financial transparency and performance tracking at the asset level.

---

### 2.8 Operational Analytics and Reporting

FleetFlow supports data-driven decision making through aggregated reporting.

Available insights:

* Fleet utilization analysis
* Fuel efficiency tracking
* Vehicle cost performance
* Financial summaries

Reports can be exported in CSV format for audits and payroll processing.

---

## 3. System Workflow Summary

1. A vehicle is added and defaults to Available status.
2. A driver is added and validated for license compliance.
3. Dispatcher creates a trip.
4. System validates cargo capacity and compliance rules.
5. On dispatch, vehicle and driver are marked On Trip.
6. On trip completion, status resets to Available.
7. If maintenance is logged, vehicle becomes In Shop and is hidden from dispatch.
8. Cost metrics are recalculated automatically based on logged fuel and maintenance data.

---

## 4. Technical Stack

Backend:

* PHP 8.2+
* Modular structure with separation of concerns
* PDO for secure database transactions
* Session-based authentication

Database:

* MySQL / MariaDB
* Relational schema with foreign key constraints
* Structured linking between vehicles, drivers, trips, and expenses

Frontend:

* Vanilla JavaScript (ES6+)
* Modern CSS3 (Flexbox, Grid, Variables)
* Structured data tables with dynamic filtering

UI Components:

* Reusable layout components (Sidebar, Topbar, Modals)
* Dynamic status indicators
* Universal search filtering system

---

## 5. Database Architecture

Core tables include:

* users
* roles
* vehicles
* drivers
* trips
* fuel_logs
* maintenance_logs
* expenses

Relational integrity ensures:

* One vehicle can have multiple trips
* One driver can have multiple trips
* One vehicle can have multiple maintenance and fuel logs
* Trips are linked to both driver and vehicle records

---

## 6. Project Structure

/admin
User management and enterprise-level controls

/api
Backend endpoints for AJAX requests, OTP handling, status toggling, and deletions

/assets
Compiled CSS, JavaScript, and static resources

/config
Database configuration and authentication logic

/includes
Reusable UI components

/modules
Feature-specific modules (Drivers, Vehicles, Trips, Maintenance, Analytics)

---

## 7. Installation and Setup

1. Clone the repository:

```bash
git clone https://github.com/your-repo/fleetflow.git
```

2. Import the database:

* Import `fleetflow.sql` into MySQL or MariaDB.
* Update `config/database.php` with your credentials.

3. Run locally:

* Use XAMPP, Laragon, or equivalent local server.
* Access via:

[http://localhost/fleetflownew/](http://localhost/fleetflownew/)


## 8. Future Enhancements

* Predictive maintenance alerts
* GPS-based live vehicle tracking
* REST API for third-party integrations
* Multi-branch fleet architecture
* Advanced analytical dashboards
