# fleet-flow-the-loop-troop
# FleetFlow –Modular Fleet & Logistics Management System

FleetFlow is a centralized, rule-based fleet and logistics management system designed to replace manual logbooks and fragmented tools. It optimizes fleet lifecycle management, ensures driver safety and compliance, and provides real-time financial and operational insights.

Problem Statement

Traditional fleet operations rely heavily on manual records and spreadsheets, leading to:
- Inefficient vehicle utilization
- Unsafe driver assignments
- Missed maintenance schedules
- Poor fuel and expense tracking
- Lack of actionable analytics

FleetFlow solves these issues by providing a single digital platform that automates workflows, enforces business rules, and enables data-driven decision-making.

Objectives

- Centralize fleet, driver, trip, and expense data
- Automate dispatching with safety and capacity validations
- Track vehicle maintenance and lifecycle
- Monitor driver compliance and performance
- Analyze operational costs and vehicle profitability
- Replace manual processes with real-time digital workflows

Target Users

Fleet Managers
- Monitor vehicle health and lifecycle
- Manage maintenance schedules
- Track fleet utilization

Dispatchers
- Create and manage trips
- Assign drivers and vehicles
- Validate cargo loads

Safety Officers
- Monitor driver license validity
- Track safety scores
- Suspend non-compliant drivers

Financial Analysts
- Audit fuel and maintenance costs
- Track operational expenses
- Analyze ROI and profitability

Core Features & Pages

1. Login & Authentication
- Secure email/password login
- Forgot password support
- Role-Based Access Control (RBAC)


2. Command Center (Dashboard)
Key KPIs:
- Active Fleet (vehicles on trip)
- Maintenance Alerts
- Utilization Rate
- Pending Cargo

Filters:
- Vehicle type
- Status
- Region

3. Vehicle Registry (Asset Management)
- Add, update, and retire vehicles
- Track:
  - Vehicle model
  - License plate (unique ID)
  - Load capacity
  - Odometer
- Toggle vehicle status (Available / Out of Service)


4. Trip Dispatcher & Management
- Assign available drivers and vehicles
- Enforce capacity validation
  
Cargo Weight ≤ Vehicle Max Capacity

- Trip lifecycle:
- Draft → Dispatched → Completed → Cancelled

5. Maintenance & Service Logs
- Log maintenance activities
- Automatic logic:
- Vehicle status switches to **In Shop**
- Vehicle removed from dispatcher selection

6. Expense & Fuel Logging
- Record fuel quantity, cost, and date
- Log maintenance expenses
- Auto-calculate:

Total Operational Cost = Fuel + Maintenance

7. Driver Performance & Safety Profiles
- License expiry tracking (blocks assignment if expired)
- Driver safety scores
- Trip completion rates
- Driver status:
- On Duty
- Off Duty
- Suspended

8. Operational Analytics & Financial Reports
- Fuel efficiency (km/L)
- Vehicle ROI:

(Revenue – (Fuel + Maintenance)) / Acquisition Cost

- Export reports (CSV / PDF)
- Monthly payroll and compliance audits

Workflow Example

1. Add vehicle **Van-05** (500kg capacity) → Status: Available  
2. Add driver **Alex** → License verified  
3. Dispatch trip with 450kg cargo → Validation passed  
4. Trip completed → Vehicle & driver become Available  
5. Maintenance logged → Vehicle status becomes In Shop  
6. Fuel and expense logs update analytics automatically  




