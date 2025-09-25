# PAMO: A Web Based Inventory Management and Ordering System for Purchasing Asset and Management Officer of STI College Lucena

This project is a web-based pre-order inventory system with a built-in e-commerce module tailored for STI College Lucena. It includes different user roles such as Admin, PAMO, and Students, each with distinct functionalities.

---

## 🗄️ Database Setup

The database structure is included in the file: `proware (3).sql`.
### 📦 Download the Database

You can download the SQL file from the `/sql` folder in this repository.

**Direct path:** [sql/proware.sql](proware(3).sql)

If you downloaded the project as a `.zip` file, you’ll find the database file inside the `sql` folder:


### 📥 How to Import the Database

1. Open your MySQL tool (e.g., phpMyAdmin, MySQL Workbench, or command line).
2. Create a new database, for example:

    ```sql
    CREATE DATABASE proware;
    ```

3. Import the SQL file:

    - **Using phpMyAdmin**:
        - Select the `proware` database.
        - Go to the **Import** tab.
        - Choose the `proware (3).sql` file and click **Go**.
---

## 🧾 Notes

- Make sure the database connection credentials in your PHP config match the database name (`proware`), username, and password.
- This SQL file contains the schema and initial data needed for the application to run properly.

---

## 🧠 User Roles

1. **Admin** – Manages user accounts.
2. **PAMO** – Manages inventory, handles orders, and updates stock.
3. **Student** – Views and pre-orders items.

---
