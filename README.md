# PAMO: Web-Based Inventory Management and Ordering System

[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange.svg)](https://mysql.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED.svg)](https://docker.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A comprehensive web-based inventory management and pre-ordering system specifically designed for the Purchasing Asset and Management Officer (PAMO) of STI College Lucena. This system streamlines inventory operations, facilitates student pre-orders, provides robust administrative controls, and features automated strike management, scheduled cron jobs, and Docker deployment support.

## 📋 Table of Contents

- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Installation & Setup](#-installation--setup)
- [Usage Instructions](#-usage-instructions)
- [Project Structure](#-project-structure)
- [Database Schema](#-database-schema)
- [User Roles & Permissions](#-user-roles--permissions)
- [Screenshots](#-screenshots)
- [Contributing](#-contributing)
- [Authors](#-authors)
- [Acknowledgments](#-acknowledgments)

## ✨ Features

### 🛡️ Admin Panel

- **User Account Management** - Create, update, and manage student & employee accounts
- **Role-Based Access Control** - Assign and manage user permissions (Admin, PAMO, Student)
- **Program & Position Management** - Configure academic programs and staff positions
- **Strike Management System** - Monitor and reset user strikes for policy violations
- **Bulk Status Updates** - Efficiently manage multiple user accounts simultaneously
- **Password Reset Utility** - Administrative password recovery for users
- **System Configuration** - Global settings and maintenance controls

### 📦 PAMO (Inventory Management)

- **Real-time Inventory Tracking** - Monitor stock levels and item availability with live updates
- **Order Management** - Process, approve, void, and track all customer orders
- **Pre-order System** - Handle special orders for out-of-stock items with automated workflows
- **Comprehensive Reporting** - Generate detailed sales, inventory, and financial reports
- **Content Management** - Update homepage banners, announcements, and FAQs
- **Dashboard Analytics** - Visual insights with charts for sales trends and inventory performance
- **Monthly Inventory Archiving** - Automatic historical data snapshots for audit trails
- **Category & Subcategory Management** - Dynamic product classification system
- **Image Upload & Management** - Multi-image support for products with preview and editing
- **Walk-in Sales Tracking** - Record non-pre-order transactions

### 🎓 Student Portal

- **Product Catalog** - Browse items by category with dynamic filtering
- **Shopping Cart System** - Add, modify quantities, and manage cart items with real-time updates
- **Pre-order Functionality** - Place orders for unavailable items with estimated availability
- **Order History & Tracking** - View current orders, past transactions, and order status
- **Profile Management** - Update personal information (name, birthday, program, ID number)
- **Inquiry System** - Submit questions with threaded replies and read receipts
- **Notification Center** - Real-time alerts for order updates, approvals, and system messages
- **Receipt Downloads** - PDF receipts for approved/completed orders
- **Strike Status Visibility** - View account strikes and cooldown periods

### 🔧 Advanced System Features

- **3-Strike Policy Enforcement** - Automated account deactivation after 3 unclaimed orders
- **Cooldown Mechanism** - 15-minute ordering restriction after receiving a strike
- **Automated Void Processing** - Cron job to void unpaid orders after 15 minutes
- **Real-time Notifications** - WebSocket-style instant updates for order status changes
- **PDF Receipt Generation** - Dual-copy receipts (student + cashier) with transaction numbers
- **Excel Export** - Export inventory, orders, and reports to XLSX format
- **Secure Authentication** - BCrypt password hashing and session management
- **Activity Logging** - Comprehensive audit trail for all administrative actions
- **Responsive Design** - Fully mobile-compatible interface with touch-friendly controls
- **Docker Deployment** - Containerized setup with PHP-FPM, Nginx, and scheduled tasks
- **Image Optimization** - Automatic compression and resizing for uploaded product images
- **Mailbox System** - Inbox for inquiries with reply threading and read status
- **Period Helper** - Smart date range utilities for reporting (daily, weekly, monthly, custom)

## 🛠️ Tech Stack

### Backend

- **PHP 8.2+** - Server-side scripting with PDO for secure database operations
- **MySQL 8.0+** - Relational database with remote hosting support (AlwaysData)
- **Nginx** - High-performance web server for production deployment
- **PHP-FPM** - FastCGI Process Manager for optimized PHP execution

### Frontend

- **HTML5** - Semantic markup and structure
- **CSS3** - Modern styling with custom stylesheets and Flexbox/Grid layouts
- **JavaScript (ES6+)** - Interactive functionality, AJAX operations, and async/await patterns
- **Chart.js** - Data visualization for dashboard analytics

### Libraries & Dependencies

- **DomPDF** (v3.1+) - PDF generation for receipts and reports
- **PhpSpreadsheet** (v4.2+) - Excel file generation and manipulation (XLSX export)
- **Font Awesome** (v6.4.0) - Icon library for UI elements
- **AOS** (v2.3.1) - Animate On Scroll library for smooth animations
- **Google Fonts (Poppins)** - Typography enhancement
- **Bootstrap** (v4.5.2) - CSS framework for responsive design
- **jQuery** (v3.5.1) - DOM manipulation and AJAX requests
- **Intervention Image** - PHP image handling and optimization

### DevOps & Infrastructure

- **Docker** - Containerization with multi-stage builds
- **Docker Compose** - Multi-container orchestration (app + web services)
- **Cron** - Scheduled task execution (automated void processing)
- **Git** - Version control with GitHub integration
- **XAMPP** - Local development environment (Apache + MySQL + PHP)
- **Composer** - PHP dependency management and autoloading

## 🚀 Installation & Setup

### Prerequisites

#### Option A: Local Development (XAMPP)

- XAMPP (or equivalent LAMP/WAMP stack)
- PHP 8.2 or higher
- MySQL 8.0 or higher
- Composer (for dependency management)

#### Option B: Docker Deployment (Recommended for Production)

- Docker Engine 20.10+
- Docker Compose 2.0+
- Port 3000 available

### Step 1: Clone the Repository

```bash
git clone https://github.com/Congroooooo/thesis.git
cd thesis
```

### Step 2: Set up XAMPP Environment

1. Start **Apache** and **MySQL** services in XAMPP Control Panel
2. Copy the project folder to `C:\xampp\htdocs\` (Windows) or `/opt/lampp/htdocs/` (Linux)

### Step 3: Install Dependencies

```bash
composer install
```

### Step 4: Database Setup

1. Open **phpMyAdmin** in your browser: `http://localhost/phpmyadmin`
2. Create a new database:
   ```sql
   CREATE DATABASE proware;
   ```
3. Import the database structure:
   - Select the `proware` database
   - Go to **Import** tab
   - Choose `sql/proware.sql` file from the project directory
   - Click **Go** to import

### Step 5: Configure Database Connection

Edit `Includes/connection.php` with your database credentials:

```php
// For local development (XAMPP)
$host = 'localhost';
$db = 'proware';
$user = 'root';
$password = '';

// For production (remote MySQL)
$host = 'mysql-nicko.alwaysdata.net';
$db = 'nicko_proware';
$user = 'your_username';
$password = 'your_password';
```

### Step 6: Set Permissions

Ensure the following directories have write permissions:

```bash
chmod -R 755 uploads/
chmod -R 755 vendor/
chmod -R 755 cron/
```

### Step 7: Access the Application

#### XAMPP (Local):

```
http://localhost/Proware/
```

#### Docker (Containerized):

```
http://localhost:3000/
```

---

## 🐳 Docker Deployment (Alternative Setup)

### Quick Start with Docker

1. **Build and Run Containers:**

   ```bash
   docker-compose up -d --build
   ```

2. **Access the Application:**

   ```
   http://localhost:3000
   ```

3. **View Logs:**

   ```bash
   docker-compose logs -f
   ```

4. **Stop Containers:**
   ```bash
   docker-compose down
   ```

### Docker Architecture

- **`proware_app`** - PHP 8.2-FPM container with application logic
- **`proware_web`** - Nginx Alpine container serving static assets
- **Cron Jobs** - Automated void processing runs inside app container
- **Volumes** - Hot-reload support for development

### Production Deployment Notes

- Modify `docker-compose.yml` to remove volume mounts for production
- Set environment variables for sensitive credentials
- Configure Nginx SSL/TLS certificates in `nginx/default.conf`
- Adjust PHP memory limits in Dockerfile if handling large files

## 📖 Usage Instructions

### Admin Access

- **URL**: `http://localhost/Proware/ADMIN/admin_page.php`
- **Default Credentials**: Create admin account through initial setup
- **Functions**:
  - Manage user accounts and roles
  - Configure system programs and positions
  - Monitor system activities
  - Generate administrative reports

### PAMO Dashboard

- **URL**: `http://localhost/Proware/PAMO_PAGES/dashboard.php`
- **Access**: PAMO role required
- **Functions**:
  - Monitor inventory levels
  - Process and approve orders
  - Generate reports and analytics
  - Manage content and announcements
  - Handle pre-order requests

### Student Portal

- **URL**: `http://localhost/Proware/Pages/home.php`
- **Access**: Student account required
- **Functions**:
  - Browse product catalog
  - Add items to cart and place orders
  - Submit pre-orders for unavailable items
  - Track order status and history
  - Manage profile and preferences

## 📁 Project Structure

```
Proware/
├── ADMIN/                          # Admin panel files
│   ├── add_account.php             # Student account creation
│   ├── add_employee_account.php    # Employee account creation
│   ├── admin_page.php              # Main admin dashboard
│   ├── bulk_update_status.php      # Bulk user status management
│   ├── manage_programs.php         # Program/position configuration
│   ├── reset_password.php          # Admin password reset utility
│   └── update_status.php           # Individual user status updates
├── ADMIN CSS/                      # Admin-specific stylesheets
│   └── manage_programs.css         # Program management UI styles
├── Backend/                        # Core backend logic
│   ├── generate_receipt.php        # PDF receipt generation (dual-copy)
│   ├── ProOrderDetailsLogic.php    # Order processing & void logic
│   └── get_latest_transaction_number.php  # Transaction ID generator
├── cron/                           # Scheduled tasks
│   ├── void_cron_simple.php        # Automated void processing
│   ├── heroku_void.php             # Cloud deployment void script
│   └── void_cron.log               # Cron execution logs
├── CSS/                            # Global stylesheets
│   ├── global.css                  # Shared UI components
│   ├── login.css                   # Authentication pages
│   ├── cart.css                    # Shopping cart styles
│   ├── profile.css                 # User profile UI
│   └── [25+ other stylesheets]     # Page-specific styles
├── Images/                         # Static images and assets
│   ├── STI-LOGO.png               # Institution branding
│   └── [product images]           # Uploaded product photos
├── Includes/                       # Shared PHP includes
│   ├── connection.php              # Database PDO connection
│   ├── Header.php                  # Common header component
│   ├── footer.php                  # Common footer component
│   ├── session_start.php           # Session initialization
│   ├── notifications.php           # Notification system
│   ├── notification_operations.php # Notification CRUD operations
│   ├── strike_management.php       # 3-strike policy enforcement
│   ├── admin_strike_management.php # Admin strike utilities
│   ├── cart_operations.php         # Cart CRUD operations
│   ├── order_operations.php        # Order processing utilities
│   ├── image_helpers.php           # Image upload/compression
│   ├── image_manager.php           # Image CRUD operations
│   ├── MonthlyInventoryManager.php # Inventory archiving
│   ├── period_helper.php           # Date range utilities
│   └── fetch_replies.php           # Inbox reply threading
├── Javascript/                     # Client-side JavaScript
│   ├── ProHome.js                  # Homepage interactions
│   ├── ProItemList.js              # Product catalog logic
│   ├── preorder.js                 # Pre-order functionality
│   ├── login.js                    # Authentication handlers
│   ├── notification-modal.js       # Notification UI
│   └── [10+ other scripts]         # Page-specific JS
├── Pages/                          # Main application pages
│   ├── home.php                    # Landing page
│   ├── login.php                   # Authentication
│   ├── MyCart.php                  # Shopping cart
│   ├── MyOrders.php                # Order history & tracking
│   ├── profile.php                 # User profile management
│   ├── ProItemList.php             # Product catalog
│   ├── ProCheckout.php             # Checkout process
│   ├── ProOrderDetails.php         # Order detail view
│   ├── ProPreOrder.php             # Pre-order submission
│   ├── about.php                   # About page
│   ├── faq.php                     # FAQ page
│   ├── get_notifications.php       # Notification API
│   └── submit_question.php         # Inquiry submission
├── PAMO_PAGES/                     # PAMO dashboard pages
│   ├── dashboard.php               # Main PAMO dashboard
│   ├── inventory.php               # Inventory management
│   ├── orders.php                  # Order processing
│   ├── preorder.php                # Pre-order management
│   ├── reports.php                 # Report generation
│   ├── settings.php                # PAMO settings
│   ├── view_inquiries.php          # Inquiry mailbox
│   └── [additional PAMO pages]     # Other PAMO features
├── PAMO_DASHBOARD_BACKEND/         # PAMO API endpoints
│   ├── [content management APIs]   # Homepage/banner updates
│   ├── [analytics APIs]            # Dashboard chart data
│   └── [settings APIs]             # Configuration endpoints
├── PAMO_PREORDER_BACKEND/          # Pre-order API handlers
│   ├── [pre-order CRUD APIs]       # Create, read, update, delete
│   └── [pre-order processing]      # Approval workflows
├── PAMO Inventory backend/         # Inventory API handlers
│   ├── add_item.php                # Add new product
│   ├── edit_image.php              # Image editing
│   ├── api_categories_list.php     # Category API
│   ├── api_subcategories_list.php  # Subcategory API
│   └── [30+ inventory APIs]        # Full inventory CRUD
├── nginx/                          # Nginx configuration
│   └── default.conf                # Web server config
├── uploads/                        # User uploaded files
│   └── [product images]            # Dynamic image storage
├── vendor/                         # Composer dependencies
│   ├── dompdf/                     # PDF generation library
│   ├── phpoffice/                  # Excel manipulation
│   └── [other libraries]           # Third-party packages
├── .dockerignore                   # Docker build exclusions
├── .gitignore                      # Git exclusions
├── Dockerfile                      # Docker image definition
├── docker-compose.yml              # Multi-container orchestration
├── docker-entrypoint.sh            # Container startup script
├── composer.json                   # PHP dependencies
├── composer.lock                   # Dependency lock file
├── index.php                       # Application entry point
└── README.md                       # Project documentation
```

## 🗄️ Database Schema

The database consists of key tables:

### Core Tables

- **`account`** - User authentication and profile data
  - Fields: `id`, `first_name`, `last_name`, `birthday`, `id_number`, `email`, `password`, `role_category`, `program_or_position`, `status`, `pre_order_strikes`, `last_strike_time`, `date_created`
- **`inventory`** - Product catalog and stock information
  - Fields: `id`, `item_code`, `item_name`, `category`, `subcategory`, `size`, `price`, `quantity`, `images` (JSON array), `date_added`, `last_updated`
- **`orders`** - Customer order records
  - Fields: `id`, `user_id`, `order_number`, `items` (JSON), `total_amount`, `status`, `approved_by`, `approved_at`, `created_at`, `payment_deadline`, `reason_for_void`
- **`cart`** - Shopping cart items
  - Fields: `id`, `user_id`, `item_code`, `quantity`, `added_at`

### Communication & Tracking

- **`inquiries`** - Customer support tickets with threaded replies
  - Fields: `id`, `user_id`, `question`, `status`, `created_at`, `mailbox_read`
- **`inquiry_replies`** - Admin responses to inquiries
  - Fields: `id`, `inquiry_id`, `reply_text`, `replied_by`, `replied_at`, `read_status`
- **`notifications`** - System notifications for users
  - Fields: `id`, `user_id`, `title`, `message`, `type`, `related_order_id`, `is_read`, `created_at`

### Administrative

- **`activities`** - Audit log for administrative actions
  - Fields: `id`, `user_id`, `action_type`, `action_description`, `ip_address`, `timestamp`
- **`programs_positions`** - Academic programs and staff positions
  - Fields: `id`, `name`, `abbreviation`, `type` (program/position), `created_at`

### Inventory Management

- **`monthly_inventory`** - Historical inventory snapshots for reporting
  - Fields: `id`, `month`, `year`, `inventory_data` (JSON), `created_at`
- **`walk_in_slips`** - Non-pre-order transaction records
  - Fields: `id`, `slip_number`, `items` (JSON), `total_amount`, `created_by`, `created_at`

### Content Management

- **`homepage_content`** - Dynamic homepage banners and announcements
  - Fields: `id`, `section_type`, `content_data` (JSON), `updated_at`, `updated_by`

## 👥 User Roles & Permissions

### 🛡️ Admin (role_category: 'EMPLOYEE', program_abbreviation: 'ADMIN')

**Full System Access:**

- ✅ Create and manage user accounts (students & employees)
- ✅ Reset user passwords
- ✅ Update user status (active/inactive)
- ✅ View and reset user strikes
- ✅ Bulk status updates for multiple accounts
- ✅ Configure programs and positions
- ✅ System monitoring and audit logs
- ✅ Role assignment and permission management

**Access Restrictions:**

- ❌ Cannot directly process orders (PAMO responsibility)
- ❌ Cannot modify inventory (PAMO responsibility)

### 📊 PAMO (role_category: 'EMPLOYEE', program_abbreviation: 'PAMO')

**Inventory & Operations:**

- ✅ Add, edit, delete inventory items
- ✅ Upload and manage product images (multi-image support)
- ✅ Manage categories and subcategories
- ✅ Process and approve customer orders
- ✅ Void orders with reason tracking
- ✅ Handle pre-orders and availability updates
- ✅ Generate sales and inventory reports (PDF/Excel)
- ✅ Update homepage content (banners, announcements)
- ✅ Manage FAQs and informational content
- ✅ View and respond to customer inquiries
- ✅ Record walk-in sales transactions
- ✅ Access dashboard analytics and charts

**Access Restrictions:**

- ❌ Cannot create/delete user accounts (Admin only)
- ❌ Cannot reset passwords (Admin only)
- ❌ Cannot modify user roles (Admin only)

### 🎓 Student (role_category: 'STUDENT' or 'SHS' or 'COLLEGE STUDENT')

**Shopping & Orders:**

- ✅ Browse product catalog by category/subcategory
- ✅ Add items to cart and modify quantities
- ✅ Place pre-orders for available items
- ✅ View order history and status
- ✅ Download PDF receipts for approved orders
- ✅ Receive real-time notifications for order updates

**Account Management:**

- ✅ View and update profile information (name, birthday, program)
- ✅ View strike count and cooldown status
- ✅ Submit inquiries to PAMO
- ✅ View threaded replies to inquiries

**Restrictions & Policies:**

- ⚠️ 3-Strike Policy: Account deactivated after 3 unclaimed orders
- ⚠️ 15-Minute Cooldown: Cannot order for 15 minutes after receiving a strike
- ⚠️ 15-Minute Payment Window: Orders auto-void if unpaid within deadline
- ❌ Cannot access admin or PAMO dashboards
- ❌ Cannot modify inventory or process orders

### 🔒 Authentication & Session Rules

- **Password Generation:** `lastName + birthdayMMDDYYYY` (e.g., "smith01151998")
- **Email Generation (Students):** `lastName.lastSixIDDigits@lucena.sti.edu.ph`
- **Email Generation (Employees):** `firstName.lastName@lucena.sti.edu.ph`
- **Session Timeout:** 30 minutes of inactivity
- **Password Hashing:** BCrypt with default cost factor
- **Failed Login Handling:** Account locked after 5 consecutive failures

## 📸 Screenshots

> **Note**: Screenshots will be added here to showcase the system interface and functionality.

### Admin Dashboard

_[Screenshot placeholder - Admin panel overview]_

### PAMO Inventory Management

_[Screenshot placeholder - Inventory management interface]_

### Student Shopping Interface

_[Screenshot placeholder - Student portal and cart system]_

### Order Processing Workflow

_[Screenshot placeholder - Order management system]_

## 🔄 Recent Updates & Changelog

### Version 2.0 (November 2025)

- ✅ **Docker Support** - Added containerization with Docker Compose
- ✅ **Strike Management System** - Implemented 3-strike policy with cooldown
- ✅ **Automated Void Processing** - Cron job for unpaid order auto-void
- ✅ **Monthly Inventory Archiving** - Historical snapshots for auditing
- ✅ **Multi-Image Product Support** - Upload multiple images per product
- ✅ **Notification Center** - Real-time alerts for order updates
- ✅ **Inquiry Reply Threading** - Mailbox system with read receipts
- ✅ **Period Helper Utility** - Smart date range selection for reports
- ✅ **Bulk Status Updates** - Admin can manage multiple users at once
- ✅ **Extension Name Field Removed** - Simplified account creation form

### Version 1.0 (Initial Release)

- ✅ Basic inventory management
- ✅ Order processing system
- ✅ User authentication and roles
- ✅ PDF receipt generation
- ✅ Shopping cart functionality

---

## 🤝 Contributing

This project is part of an academic thesis. For suggestions or improvements:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/improvement`)
3. Commit your changes (`git commit -am 'Add new feature'`)
4. Push to the branch (`git push origin feature/improvement`)
5. Create a Pull Request

### Coding Standards

- Follow PSR-12 PHP coding standards
- Use meaningful variable names (camelCase for PHP, snake_case for database)
- Comment complex logic blocks
- Validate and sanitize all user inputs
- Use prepared statements for all database queries

## 👨‍💻 Authors

**Development Team - STI College Lucena**

- **Balmes, Nicko** - Lead Developer & Full Stack Developer
- **De Vera, Arron** - UI/UX Designer & Frontend Developer
- **Garcia, Reyn Alduz** - Project Manager & Frontend Developer
- **Ibarra, Lander** - UI/UX Designer & Frontend Developer

## 🙏 Acknowledgments

- **Thesis Advisors** - For guidance and technical supervision

---

### 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

### 📞 Support

For technical support or questions:

- **Email**: nckoblms@gmail.com
- **Institution**: STI College Lucena
- **Project**: Thesis - PAMO Inventory Management System

---

_This system was developed as part of an academic thesis project for STI College Lucena, focusing on modernizing inventory management and improving operational efficiency through web-based automation._
