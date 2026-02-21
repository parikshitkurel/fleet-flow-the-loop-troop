Here is your properly spaced and cleanly formatted **README.md** version — ready to paste directly into GitHub without formatting issues:

---

# FleetFlow — Enterprise Fleet Management System

FleetFlow is a production-grade, role-aware fleet management and intelligence platform. Designed with a modern, high-performance aesthetic, it provides businesses with the tools needed to manage vehicles, drivers, trips, and financials in a single unified dashboard.

---

##  Key Features

* **Intelligent Dashboard**: Real-time KPI tracking for active fleet, maintenance status, utilization rate, and operational costs.
* **Role-Based Access Control (RBAC)**: Secure environments for Administrators, Fleet Managers, Dispatchers, Safety Officers, and Financial Analysts.
* **Vehicle Registry**: Comprehensive management of vehicle data, including automated license plate validation and service status toggling.
* **Driver Performance**: Safety scoring, license expiry tracking, duty status control, and compliance-based assignment blocking.
* **Trip Dispatcher**: Automated logistics handling with cargo capacity validation and lifecycle management.
* **Maintenance & Fuel Tracking**: Visual logs for service history, fuel records, and expenditure analysis.
* **Operational Analytics**: Fuel efficiency tracking, cost-per-kilometer calculations, and vehicle ROI monitoring.
* **Financial Reporting**: Export-ready CSV reports for audits and payroll processing.
* **Universal Search Bar**: A powerful, light-themed filtering system available across all data tables.

---

##  Core Workflow Logic

FleetFlow operates on a strict rule-based workflow engine:

### 1. Vehicle Intake

Newly added vehicles default to **Available** status.

### 2. Driver Compliance Validation

License category and expiry date are verified before assignment.

### 3. Dispatch Validation Rules

* Prevent trip creation if cargo weight exceeds vehicle capacity.
* Block assignment if driver license is expired.
* Block assignment if driver is Off Duty or Suspended.
* Block assignment if vehicle status is not Available.

### 4. Automatic Status Updates

* On dispatch → Vehicle & Driver marked **On Trip**.
* On trip completion → Vehicle & Driver reset to **Available**.
* On maintenance log creation → Vehicle marked **In Shop** and removed from dispatch pool.

### 5. Automated Cost Calculations

* Total Operational Cost = Fuel + Maintenance
* Cost per KM = Total Operational Cost / Distance
* Vehicle ROI = (Revenue − (Maintenance + Fuel)) / Acquisition Cost

---

##  Design Philosophy

FleetFlow utilizes a **premium, light-themed aesthetic** with a focus on visual stability and clarity:

* **Glassmorphism & Gradients**: Subtle visual effects for a modern look.
* **Dynamic Sidebar**: Smooth desktop collapse/expand behavior for optimized workspace.
* **Responsive Layout**: Fully compatible with desktop and tablet screen sizes.
* **Visual Hierarchy**: Clear status pills and KPI badges for at-a-glance data processing.
* **Data-First UI**: Structured tables with consistent alignment and scannable formatting.

---

##  Technical Stack

* **Backend**: PHP 8.2+
* **Database**: MySQL / MariaDB (PDO for secure transactions and prepared statements)
* **Frontend**: Vanilla JavaScript (ES6+), Modern CSS3 (Variables, Grid, Flexbox)
* **Architecture Pattern**: Modular PHP structure with separation of concerns
* **Authentication**: Session-based authentication with role validation middleware
* **Typography**: Inter (Google Fonts)
* **Icons**: Lucide Icons (SVG-based)

---

##  Database Architecture

FleetFlow follows a relational database structure ensuring referential integrity.

### Core Tables

* users
* roles
* vehicles
* drivers
* trips
* fuel_logs
* maintenance_logs
* expenses

### Relationships

* One Vehicle → Many Trips
* One Driver → Many Trips
* One Vehicle → Many Expenses
* One Trip → Linked Vehicle & Driver

Foreign key constraints enforce data consistency across modules.

---

##  Installation & Setup

### 1. Clone the Repository

```bash
git clone https://github.com/your-repo/fleetflow.git
```

### 2. Database Configuration

* Import `fleetflow.sql` into your MySQL/MariaDB environment.
* Update `config/database.php` with your local credentials.

### 3. Local Environment

* Use a local server like XAMPP or Laragon.
* Access the project at:

```
http://localhost/fleetflownew/
```

---

##  Default Roles & Access Levels

* **Administrator** — Full system control and user management.
* **Fleet Manager** — Vehicle lifecycle and maintenance control.
* **Dispatcher** — Trip creation and driver assignment.
* **Safety Officer** — Driver compliance and safety monitoring.
* **Financial Analyst** — Access to fuel logs, expenses, and ROI analytics.

---

##  Project Structure

```
/admin      → User management and enterprise-level settings  
/api        → Backend endpoints for AJAX-based status toggling and deletions  
/assets     → Compiled CSS, Vanilla JS, and image assets  
/config     → Core authentication and database connection logic  
/includes   → Reusable UI components (Sidebar, Topbar, Modals)  
/modules    → Feature-specific pages (Drivers, Vehicles, Trips, Maintenance, Analytics)
```




