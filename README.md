# Infrastructure Maintenance Reporting System (IMRS)

A PHP/MySQL web application for managing infrastructure maintenance requests at Bahir Dar Institute of Technology (BIT). It supports request submission, approvals, task assignment, notifications, reporting, and role-based access for students, staff, managers, and administrators.

## Overview

IMRS helps the maintenance office track issues from submission to completion. The workflow includes:

1. Request submission
2. Approval or rejection
3. Assignment to a maintenance team member
4. Progress updates and completion
5. Reporting and dashboard views for administrators and managers

## Key Features

- Secure login and role-based access control
- Maintenance request creation, tracking, and status updates
- Approval workflow for Proctor and Office Head
- Task assignment and maintenance team progress tracking
- In-app notifications for request updates
- File uploads for maintenance reports and attachments
- Admin dashboard and reporting tools
- Support for general users who may submit requests without full registration

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache / WAMP / XAMPP / LAMP environment
- Composer (for dependencies such as Dompdf)

## Quick Start

### 1. Create the Database

Create a MySQL database named `imrs_db`, then import the main schema:

```sql
source database/schema.sql
```

You can also import the file through phpMyAdmin.

### 2. Configure Database Access

Edit `config/database.php` and confirm your connection details:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'imrs_db');
```

### 3. Configure the Base URL

Edit `config/config.php` and update `BASE_URL` if your project is not running from the default local path:

```php
define('BASE_URL', 'http://localhost/final project');
```

### 4. Set Upload Permissions

Ensure the `uploads` folder exists and is writable by the web server.

### 5. Launch the Application

Open the application in your browser:

```text
http://localhost/final project/
```

You can then register or log in to start using the system.

## Optional General User Feature

If you want the public/general-user request flow, run the migration in `database/migration_add_general_user.sql` before using the public submission pages.

## Main User Roles

- Student
- Staff
- Proctor
- Office Head
- Maintenance Team
- Maintenance Manager
- Admin
- General User (optional public flow)

## Main Application Areas

- `auth/` – login, registration, logout
- `requests/` – user request creation and tracking
- `approvals/` – approval workflow
- `manager/` – manager dashboard and assignments
- `team/` – team task updates
- `admin/` – administration and reporting
- `public/` – public/general-user request pages
- `database/` – SQL schema and migrations
- `uploads/` – uploaded files and attachments

## Typical Workflow

1. A user submits a maintenance request.
2. The request is reviewed by the relevant approver.
3. A maintenance manager assigns the task.
4. The maintenance team updates the work status.
5. The request is completed and reported in the dashboard.

## Security Notes

- Passwords are hashed using PHP secure hashing functions.
- Prepared statements are used for database queries.
- Input values are sanitized to reduce XSS and injection risks.
- Role-based access is enforced across protected pages.

## Support

For setup issues, database errors, or access problems, refer to `INSTALLATION.md` and the project configuration files in `config/`.

## Version

Current version: 1.0.0

---

Developed for Bahir Dar Institute of Technology (BIT).



