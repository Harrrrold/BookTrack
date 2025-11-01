# BookTrack Deployment Guide

This guide will help you deploy BookTrack to a production server.

## Pre-Deployment Checklist

- [ ] Database configured for production
- [ ] All sensitive files excluded from Git
- [ ] HTTPS enabled
- [ ] Error logging configured
- [ ] Security settings updated
- [ ] Environment variables set
- [ ] Database backed up

## Deployment Options

### Option 1: Shared Hosting (cPanel, GoDaddy, etc.)

#### Step 1: Upload Files
1. Upload all files via FTP/SFTP to your hosting account
2. Ensure file structure is maintained:
   ```
   public_html/
   ├── api/
   ├── config/
   ├── assets/
   ├── database/
   └── [HTML files]
   ```

#### Step 2: Create Database
1. Go to cPanel → MySQL Databases
2. Create a new database (e.g., `yourname_booktrack`)
3. Create a database user with full privileges
4. Note down the database credentials

#### Step 3: Configure Database
1. Create `config/database.local.php` on the server:
   ```php
   <?php
   define('DB_HOST', 'localhost'); // Usually localhost on shared hosting
   define('DB_USER', 'yourname_dbuser');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'yourname_booktrack');
   ?>
   ```

#### Step 4: Import Database
1. Go to phpMyAdmin in cPanel
2. Select your database
3. Import `database/schema.sql`
4. Run `setup/setup_default_users.php` via browser

#### Step 5: Set Permissions
- Ensure PHP files are executable (usually 644)
- Ensure directories are readable (usually 755)

### Option 2: VPS/Cloud Server (DigitalOcean, AWS, etc.)

#### Step 1: Server Setup
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache, MySQL, PHP
sudo apt install apache2 mysql-server php php-mysqli php-json -y

# Enable Apache modules
sudo a2enmod rewrite headers
sudo systemctl restart apache2
```

#### Step 2: Database Setup
```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE booktrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'booktrack_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON booktrack.* TO 'booktrack_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Step 3: Deploy Application
```bash
# Clone or upload your files to /var/www/html/booktrack
cd /var/www/html
sudo git clone your-repo-url booktrack
# OR upload via SFTP

# Set permissions
sudo chown -R www-data:www-data /var/www/html/booktrack
sudo chmod -R 755 /var/www/html/booktrack
```

#### Step 4: Configure Apache Virtual Host
Create `/etc/apache2/sites-available/booktrack.conf`:
```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/html/booktrack
    
    <Directory /var/www/html/booktrack>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/booktrack_error.log
    CustomLog ${APACHE_LOG_DIR}/booktrack_access.log combined
</VirtualHost>
```

Enable site:
```bash
sudo a2ensite booktrack.conf
sudo systemctl reload apache2
```

#### Step 5: Configure Database
Create `config/database.local.php`:
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'booktrack_user');
define('DB_PASS', 'strong_password_here');
define('DB_NAME', 'booktrack');
?>
```

#### Step 6: SSL/HTTPS Setup (Let's Encrypt)
```bash
sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

### Option 3: Environment Variables (Recommended for Cloud)

Many cloud platforms support environment variables:

#### Heroku
```bash
heroku config:set DB_HOST=your_host
heroku config:set DB_USER=your_user
heroku config:set DB_PASS=your_password
heroku config:set DB_NAME=your_database
```

#### AWS Elastic Beanstalk
Create `.ebextensions/environment.config`:
```yaml
option_settings:
  aws:elasticbeanstalk:application:environment:
    DB_HOST: your_host
    DB_USER: your_user
    DB_PASS: your_password
    DB_NAME: your_database
    APP_ENV: production
```

#### DigitalOcean App Platform
Set environment variables in App Platform dashboard.

## Security Configuration

### 1. Update .htaccess for Production

Uncomment HTTPS redirect:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

Update CORS (if needed):
```apache
Header set Access-Control-Allow-Origin "https://yourdomain.com"
```

### 2. Set PHP Error Reporting

In `config/database.php`, ensure:
```php
if (getenv('APP_ENV') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}
```

### 3. Change Default Passwords

After deployment:
1. Change admin password
2. Remove default users (optional)
3. Use strong passwords

## Post-Deployment

### 1. Test All Features
- [ ] Registration works
- [ ] Login works
- [ ] Books load from database
- [ ] Search works
- [ ] Borrowing works
- [ ] Admin features work

### 2. Performance Optimization

Enable caching (if using shared hosting with caching):
- Check with your hosting provider

Optimize database:
```sql
-- Run periodically
OPTIMIZE TABLE books;
OPTIMIZE TABLE users;
OPTIMIZE TABLE borrowings;
```

### 3. Monitoring

Set up error logging:
- Check Apache error logs
- Check PHP error logs
- Monitor database performance

### 4. Backup Strategy

**Database Backup:**
```bash
# Daily backup script
mysqldump -u user -p booktrack > backup_$(date +%Y%m%d).sql
```

**Files Backup:**
- Back up `config/database.local.php`
- Back up uploaded files (if any)

## Troubleshooting

### 500 Internal Server Error
- Check `.htaccess` syntax
- Check PHP error logs
- Verify file permissions

### Database Connection Failed
- Verify credentials in `database.local.php`
- Check MySQL is running
- Verify user has correct privileges

### 403 Forbidden
- Check file permissions (644 for files, 755 for directories)
- Check Apache configuration
- Verify `.htaccess` is allowed

### CORS Errors
- Update CORS headers in `.htaccess`
- Ensure API URLs use HTTPS

## Quick Deployment Script

Create `deploy.sh`:
```bash
#!/bin/bash
# Quick deployment checklist

echo "BookTrack Deployment Checklist"
echo "==============================="
echo ""
echo "1. Database created? [y/n]"
read db_created

echo "2. Files uploaded? [y/n]"
read files_uploaded

echo "3. database.local.php configured? [y/n]"
read db_configured

echo "4. Database imported? [y/n]"
read db_imported

echo "5. Default users created? [y/n]"
read users_created

echo "6. HTTPS configured? [y/n]"
read https_configured

echo ""
echo "Next steps:"
echo "- Test registration"
echo "- Test login"
echo "- Test all features"
echo "- Monitor error logs"
```

## Support

For deployment issues:
1. Check error logs
2. Verify configuration files
3. Test database connection
4. Check file permissions

