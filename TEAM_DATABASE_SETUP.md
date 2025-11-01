# Team Database Sharing Guide

This guide explains how to set up a shared database so all team members can work on the same BookTrack database.

## Option 1: Local Network Sharing (Recommended for Same Network)

If all team members are on the same local network (same WiFi/office):

### On the Host Computer (Person with the database):

1. **Get your IP address:**
   - Open Command Prompt
   - Type: `ipconfig`
   - Find "IPv4 Address" (e.g., `192.168.1.100`)

2. **Allow MySQL remote access:**
   - Edit `C:\xampp\mysql\bin\my.ini` (or create if it doesn't exist)
   - Find the `[mysqld]` section
   - Add or modify:
     ```ini
     [mysqld]
     bind-address = 0.0.0.0
     ```
   - Save the file
   - Restart MySQL in XAMPP Control Panel

3. **Create a MySQL user for team access:**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Click "User accounts" tab
   - Click "Add user account"
   - Set:
     - **User name**: `booktrack_team` (or any name)
     - **Host name**: Select `Any host (%)`
     - **Password**: Create a strong password (e.g., `BookTrack2025!`)
     - **Database for user account**: Select "Grant all privileges on database `booktrack`"
   - Click "Go"

### On Team Member Computers:

1. **Update database configuration:**
   - Open `config/database.php`
   - Change:
     ```php
     define('DB_HOST', '192.168.1.100');  // Host computer's IP
     define('DB_USER', 'booktrack_team');
     define('DB_PASS', 'BookTrack2025!');  // Password you created
     define('DB_NAME', 'booktrack');
     ```

2. **Test connection:**
   - Open: `http://localhost/BookTrack/test_db.php`
   - Should show success message

## Option 2: Online Database (Recommended for Remote Teams)

Use a cloud database service:

### Using Free Hosting Services:

1. **Create account on free MySQL hosting:**
   - **FreeMySQLDatabase.com**
   - **db4free.net**
   - **RemotelyMySQL.com**
   - **FreeSQLDatabase.com**

2. **Get database credentials:**
   - They'll provide: Host, Username, Password, Database name
   - Example:
     ```
     Host: db4free.net
     Port: 3306
     Username: booktrack_user
     Password: your_password
     Database: booktrack_db
     ```

3. **Import database:**
   - Use phpMyAdmin from the hosting service
   - Import `database/schema.sql`

4. **Update configuration for all team members:**
   - Edit `config/database.php`:
     ```php
     define('DB_HOST', 'db4free.net');
     define('DB_USER', 'booktrack_user');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'booktrack_db');
     ```

### Using Professional Services (Paid):

- **Amazon RDS** (Free tier available)
- **Google Cloud SQL**
- **DigitalOcean Managed Databases**
- **Azure Database for MySQL**

## Option 3: Git Configuration File (Secure)

Keep database credentials out of Git:

1. **Create `config/database.local.php` (not in Git):**
   ```php
   <?php
   // Local database configuration
   // This file is NOT committed to Git
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'booktrack');
   ?>
   ```

2. **Update `config/database.php` to use local config:**
   ```php
   <?php
   // Try to load local config first
   if (file_exists(__DIR__ . '/database.local.php')) {
       require_once __DIR__ . '/database.local.php';
   } else {
       // Default/fallback configuration
       define('DB_HOST', 'localhost');
       define('DB_USER', 'root');
       define('DB_PASS', '');
       define('DB_NAME', 'booktrack');
   }
   
   // Rest of the file...
   ```

3. **Add to `.gitignore`:**
   ```
   config/database.local.php
   ```

4. **Create `config/database.local.php.example`:**
   ```php
   <?php
   // Copy this file to database.local.php and update with your settings
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'booktrack');
   ?>
   ```

5. **Each team member:**
   - Copies `database.local.php.example` to `database.local.php`
   - Updates with their database credentials
   - File is not committed to Git

## Security Best Practices

⚠️ **Important:**

1. **Never commit passwords to Git**
   - Use `.gitignore` for sensitive files
   - Use environment variables or local config files

2. **Use strong passwords**
   - Minimum 12 characters
   - Mix of uppercase, lowercase, numbers, symbols

3. **Limit database access**
   - Only grant necessary privileges
   - Use different users for different environments

4. **Use HTTPS in production**
   - Encrypt data in transit

## Quick Setup Checklist

### For Host (Database Owner):
- [ ] Export database schema
- [ ] Set up remote access (Option 1) or create online database (Option 2)
- [ ] Share credentials securely (use private message, not public chat)
- [ ] Test connection from another computer

### For Team Members:
- [ ] Get database credentials from host
- [ ] Update `config/database.php` with shared credentials
- [ ] Test connection using `test_db.php`
- [ ] Run `setup/setup_default_users.php` (if needed)

## Troubleshooting

**Error: "Access denied for user"**
- Check username and password are correct
- Verify user has proper permissions
- Check if user is allowed to connect from your IP

**Error: "Can't connect to MySQL server"**
- Check if MySQL is running on host
- Verify IP address is correct
- Check firewall settings
- For online databases: Check host address and port

**Error: "Unknown database"**
- Verify database name is correct
- Make sure database was created on the server
- Check if you have permission to access the database

## Recommended Approach

For most teams, **Option 2 (Online Database)** is best because:
- ✅ Works from anywhere
- ✅ No network configuration needed
- ✅ Easy to set up
- ✅ Free options available
- ✅ Automatic backups (on paid plans)

For local development teams, **Option 1 (Local Network)** works well:
- ✅ Fast (local network)
- ✅ No internet required
- ✅ Full control

For security-conscious teams, **Option 3 (Git Config)** is essential:
- ✅ Keeps passwords out of version control
- ✅ Each developer can use their own local database
- ✅ Easy to maintain

---

**Need help?** Share your specific scenario and we can recommend the best approach!

