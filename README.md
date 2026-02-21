# FleetFlow — Enterprise Fleet Management System

FleetFlow is a production-grade, role-aware fleet management and intelligence platform. Designed with a modern, high-performance aesthetic, it provides businesses with the tools needed to manage vehicles, drivers, trips, and financials in a single unified dashboard.

## 🚀 Key Features

-   **Intelligent Dashboard**: Real-time KPI tracking for active fleet, maintenance status, and operational costs.
-   **Role-Based Access Control (RBAC)**: secure environments for Administrators, Fleet Managers, Dispatchers, Safety Officers, and Financial Analysts.
-   **Vehicle Registry**: Comprehensive management of vehicle data, including automated license plate validation.
-   **Driver Performance**: Safety scoring, license expiry tracking, and status management.
-   **Trip Dispatcher**: Automated logistics handling with cargo capacity validation.
-   **Maintenance & Fuel Tracking**: Visual logs for service history and expenditure analysis.
-   **Universal Search Bar**: A powerful, light-themed filtering system available across all data tables.

## 🎨 Design Philosophy

FleetFlow utilizes a **premium, light-themed aesthetic** with a focus on visual stability and clarity:
-   **Glassmorphism & Gradients**: Subtle visual effects for a modern look.
-   **Dynamic Sidebar**: Smooth desktop collapse/expand behavior for optimized workspace.
-   **Responsive Layout**: Fully compatible with desktop and tablet screen sizes.
-   **Visual Hierarchy**: Clear status pills and KPI badges for at-a-glance data processing.

## 🛠️ Technical Stack

-   **Backend**: PHP 8.2+
-   **Database**: MySQL / MariaDB (PDO for secure transactions)
-   **Frontend**: Vanilla JavaScript (ES6+), Modern CSS3 (Variables, Grid, Flexbox)
-   **Typography**: Inter (Google Fonts)
-   **Icons**: Lucide Icons (SVG-based)

## 📦 Installation & Setup

1.  **Clone the Repository**:
    ```bash
    git clone https://github.com/your-repo/fleetflow.git
    ```
2.  **Database Configuration**:
    -   Import `fleetflow.sql` into your MySQL/MariaDB environment.
    -   Update `config/database.php` with your local credentials.
3.  **Local Environment**:
    -   Ensure you are using a local server like XAMPP or Laragon.
    -   Project should be accessible at `http://localhost/fleetflownew/`.

## 📜 Project Structure

-   `/admin`: User management and enterprise-level settings.
-   `/api`: Backend endpoints for AJAX-based status toggling and deletions.
-   `/assets`: Compiled CSS, Vanilla JS, and image assets.
-   `/config`: Core authentication and database connection logic.
-   `/includes`: Reusable UI components (Sidebar, Topbar, Modals).
-   `/modules`: Feature-specific pages (Drivers, Vehicles, Trips, etc.).

---

