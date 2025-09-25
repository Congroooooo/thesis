# PAMO: Web-Based Inventory Management and Ordering System

[![PHP](https://img.shields.io/badge/PHP-8.0+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A comprehensive web-based inventory management and pre-ordering system specifically designed for the Purchasing Asset and Management Officer (PAMO) of STI College Lucena. This system streamlines inventory operations, facilitates student pre-orders, and provides robust administrative controls through a modern web interface.

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

- **User Account Management** - Create, update, and manage user accounts
- **Role-Based Access Control** - Assign and manage user permissions
- **Program & Position Management** - Configure academic programs and positions
- **System Configuration** - Global system settings and maintenance

### 📦 PAMO (Inventory Management)

- **Real-time Inventory Tracking** - Monitor stock levels and item availability
- **Order Management** - Process, approve, and track student orders
- **Comprehensive Reporting** - Generate detailed inventory and sales reports
- **Content Management** - Update homepage content and announcements
- **Pre-order Management** - Handle special orders and requests
- **Dashboard Analytics** - Visual insights into inventory performance

### 🎓 Student Portal

- **Product Catalog** - Browse available items by category
- **Shopping Cart System** - Add, modify, and manage cart items
- **Pre-order Functionality** - Place orders for out-of-stock items
- **Order History** - Track current and past orders
- **Profile Management** - Update personal information and preferences
- **Inquiry System** - Submit questions and receive responses

### 🔧 System Features

- **Responsive Design** - Fully mobile-compatible interface
- **Real-time Notifications** - Instant updates on order status
- **PDF Receipt Generation** - Automatic receipt creation for completed orders
- **Excel Export** - Export reports and data to spreadsheet format
- **Secure Authentication** - Password hashing and session management
- **Activity Logging** - Comprehensive audit trail for all actions

## 🛠️ Tech Stack

### Backend

- **PHP 8.0+** - Server-side scripting with PDO for database operations
- **MySQL 8.0+** - Relational database management system

### Frontend

- **HTML5** - Semantic markup and structure
- **CSS3** - Modern styling with custom stylesheets
- **JavaScript (ES6+)** - Interactive functionality and AJAX operations

### Libraries & Dependencies

- **DomPDF** (v3.1+) - PDF generation for receipts and reports
- **PhpSpreadsheet** (v4.2+) - Excel file generation and manipulation
- **Font Awesome** (v6.4.0) - Icon library for UI elements
- **AOS** (v2.3.1) - Animate On Scroll library for smooth animations
- **Google Fonts** - Typography enhancement
- **Bootstrap** (v4.5.2) - CSS framework for responsive design
- **jQuery** (v3.5.1) - DOM manipulation and AJAX requests

### Development Environment

- **XAMPP** - Local development server (Apache + MySQL + PHP)
- **Composer** - PHP dependency management

## 🚀 Installation & Setup

### Prerequisites

- XAMPP (or equivalent LAMP/WAMP stack)
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Composer (for dependency management)

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

Edit the database connection file:

```php

$host = 'localhost';
$db = 'proware';
$user = 'root';
$password = '';
```

### Step 6: Set Permissions

Ensure the following directories have write permissions:

- `uploads/` - For file uploads
- `vendor/` - For Composer dependencies

### Step 7: Access the Application

Open your browser and navigate to:

```
http://localhost/Proware/
```

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

- **URL**: `http://localhost/Proware/PAMO PAGES/dashboard.php`
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
├── ADMIN/                      # Admin panel files
├── ADMIN CSS/                  # Admin-specific stylesheets
├── Backend/                    # Core backend logic
│   ├── generate_receipt.php    # PDF receipt generation
│   ├── ProOrderDetailsLogic.php # Order processing logic
│   └── get_latest_transaction_number.php
├── CSS/                        # Global stylesheets
├── Images/                     # Static images and assets
├── Includes/                   # Shared PHP includes
│   ├── connection.php          # Database connection
│   ├── Header.php              # Common header component
│   └── notifications.php       # Notification system
├── Javascript/                 # Client-side JavaScript
├── Pages/                      # Main application pages
│   ├── home.php               # Landing page
│   ├── login.php              # Authentication
│   ├── MyCart.php             # Shopping cart
│   └── profile.php            # User profile
├── PAMO PAGES/                # PAMO dashboard pages
├── PAMO_DASHBOARD_BACKEND/    # PAMO API endpoints
├── PAMO_PREORDER_BACKEND/     # Pre-order API handlers
├── PAMO ORDER BACKEND/        # Order management APIs
├── sql/                       # Database schema
│   └── proware.sql           # Main database file
├── uploads/                   # User uploaded files
├── vendor/                    # Composer dependencies
├── composer.json              # PHP dependencies
└── README.md                  # Project documentation
```

## 🗄️ Database Schema

The database consists of key tables:

- **`account`** - User authentication and profile data
- **`inventory`** - Product catalog and stock information
- **`orders`** - Customer order records
- **`cart`** - Shopping cart items
- **`inquiries`** - Customer support tickets
- **`notifications`** - System notifications
- **`activities`** - Audit log for system actions
- **`programs_positions`** - Academic programs and staff positions

## 👥 User Roles & Permissions

### 🛡️ Admin

- Full system access and configuration
- User account management
- System monitoring and maintenance
- Role assignment and permissions

### 📊 PAMO (Purchasing Asset Management Officer)

- Inventory management and tracking
- Order processing and approval
- Report generation and analytics
- Content management capabilities

### 🎓 Student

- Product browsing and purchasing
- Order tracking and history
- Profile management
- Inquiry submission

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

## 🤝 Contributing

This project is part of an academic thesis. For suggestions or improvements:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/improvement`)
3. Commit your changes (`git commit -am 'Add new feature'`)
4. Push to the branch (`git push origin feature/improvement`)
5. Create a Pull Request

## 👨‍💻 Authors

**Development Team - STI College Lucena**

- **Balmes, Nicko** - Lead Developer && Full Stack Developer
- **De Vera, Arron** - UI / UX Desginer && Frontend Developer
- **Garcia Reyn Alduz** - UI / UX Desginer && Frontend Developer
- **Ibarra, Lander** - UI / UX Desginer && Frontend Developer

## 🙏 Acknowledgments

- **STI College Lucena** - For providing the project opportunity and requirements
- **Thesis Advisors** - For guidance and technical supervision
- **PHP Community** - For excellent documentation and resources
- **Open Source Libraries** - DomPDF, PhpSpreadsheet, and other dependencies
- **Font Awesome & Google Fonts** - For UI enhancement resources

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
