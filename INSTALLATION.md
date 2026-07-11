# IMRS Installation Guide

## Quick Setup Steps

### Step 1: Database Setup

1. **Open phpMyAdmin** (usually at `http://localhost/phpmyadmin`)

2. **Create Database** (optional - the schema.sql will create it):
   - Click "New" in the left sidebar
   - Database name: `imrs_db`
   - Collation: `utf8mb4_general_ci`
   - Click "Create"

3. **Import Schema**:
   - Select the `imrs_db` database
   - Click "Import" tab
   - Choose file: `database/schema.sql`
   - Click "Go"

   OR manually execute the SQL file:
   - Click "SQL" tab
   - Copy and paste contents of `database/schema.sql`
   - Click "Go"

### Step 2: Configure Database Connection

1. Open `config/database.php`
2. Update these lines if your MySQL credentials are different:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');        // Your MySQL username
   define('DB_PASS', '');            // Your MySQL password
   define('DB_NAME', 'imrs_db');
   ```

### Step 3: Configure Base URL

1. Open `config/config.php`
2. Update the BASE_URL if your project is in a subdirectory:
   ```php
   define('BASE_URL', 'http://localhost/final project');
   ```
   
   If your project is directly in `www`, use:
   ```php
   define('BASE_URL', 'http://localhost/imrs');
   ```

### Step 4: Set File Permissions

**Windows (WAMP):**
- The `uploads` folder should already be created
- Right-click on `uploads` folder → Properties → Security
- Ensure the web server user has write permissions

**Linux/Mac:**
```bash
chmod 755 uploads
chmod -R 755 uploads
```

### Step 5: Test Installation

1. **Start WAMP/XAMPP** services (Apache and MySQL)

2. **Access the application**:
   - Open browser: `http://localhost/final project`
   - You should be redirected to the login page

3. **Create First User**:
   - Go to: `http://localhost/final project/auth/register.php`
   - Register as Admin (select "Admin" role)
   - Complete registration

4. **Login**:
   - Go to: `http://localhost/final project/auth/login.php`
   - Login with your credentials

## Default Data

The database schema includes:
- **Infrastructure Types**: Electrical, Plumbing, HVAC, Furniture, Building Structure, IT Equipment, Safety Equipment, Other
- **Locations**: Various locations in Main Building, Engineering Block, Library, etc.

## Creating Test Users

### Option 1: Via Registration Page
1. Logout (if logged in)
2. Go to registration page
3. Create users with different roles:
   - Student
   - Staff
   - Proctor
   - Office Head
   - Maintenance Team
   - Maintenance Manager

### Option 2: Via Database
You can manually insert users in the `users` table:
```sql
INSERT INTO users (username, email, password_hash, full_name, role, status) 
VALUES ('admin', 'admin@bit.edu.et', '$2y$10$...', 'Admin User', 'Admin', 'Active');
```

**Note**: Use `password_hash('yourpassword', PASSWORD_DEFAULT)` to generate password hash.

## Troubleshooting

### Issue: "Connection failed" error
- **Solution**: Check database credentials in `config/database.php`
- Ensure MySQL service is running
- Verify database `imrs_db` exists

### Issue: "Access Denied" errors
- **Solution**: Check file permissions on `uploads` folder
- Ensure Apache has write access

### Issue: Pages show blank/white screen
- **Solution**: 
  - Enable error display in `config/config.php` (already enabled)
  - Check PHP error logs
  - Verify all PHP files have proper syntax

### Issue: File uploads not working
- **Solution**:
  - Check `uploads` folder exists and has write permissions
  - Verify PHP `upload_max_filesize` and `post_max_size` settings
  - Check `.htaccess` file is present

### Issue: CSS/JS not loading
- **Solution**:
  - Verify `BASE_URL` in `config/config.php` is correct
  - Check browser console for 404 errors
  - Ensure `assets` folder exists with proper structure

## Next Steps

1. **Create Users**: Set up users for each role
2. **Test Workflow**: 
   - Submit a request as Student/Staff
   - Approve it as Proctor/Office Head
   - Assign it as Maintenance Manager
   - Complete it as Maintenance Team
3. **Customize**: 
   - Add more infrastructure types
   - Add more locations
   - Customize email templates (if implementing email notifications)

## Support

For additional help, check:
- README.md for general information
- Database schema comments in `database/schema.sql`
- PHP error logs in WAMP/XAMPP logs folder



