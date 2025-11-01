# BookTrack Backend Setup Guide

This guide will help you set up the MySQL database and PHP backend for the BookTrack application using XAMPP.

## Prerequisites

- XAMPP installed on your system
- Basic knowledge of MySQL and PHP

## Step 1: Start XAMPP Services

1. Open the **XAMPP Control Panel**
2. Start the following services:
   - **Apache** (for running PHP)
   - **MySQL** (for the database)
3. Ensure both services show a green "Running" status

## Step 2: Create the Database

### Option A: Using phpMyAdmin (Recommended)

1. Open your web browser and go to: `http://localhost/phpmyadmin`
2. Click on the **"SQL"** tab at the top
3. Open the file `database/schema.sql` from this project in a text editor
4. Copy the entire contents of the SQL file
5. Paste it into the SQL query box in phpMyAdmin
6. Click **"Go"** to execute the SQL
7. **Important**: After importing, run the setup script to create default users with proper passwords:
   - Navigate to: `http://localhost/BookTrack/setup/setup_default_users.php`
   - This will create default admin and user accounts with proper password hashes

### Option B: Using MySQL Command Line

1. Open Command Prompt (Windows) or Terminal (Mac/Linux)
2. Navigate to your XAMPP MySQL bin directory:
   ```bash
   cd C:\xampp\mysql\bin  # Windows
   # or
   cd /Applications/XAMPP/bin  # Mac
   ```
3. Run MySQL:
   ```bash
   mysql -u root -p
   ```
   (Press Enter if there's no password)
4. Execute the schema file:
   ```sql
   source C:\Users\JohnR\OneDrive\Documents\GitHub\BookTrack\BookTrack\database\schema.sql
   ```
   (Adjust the path to match your project location)

### Option C: Import via phpMyAdmin

1. Go to `http://localhost/phpmyadmin`
2. Click on **"Import"** tab
3. Click **"Choose File"** and select `database/schema.sql`
4. Click **"Go"** at the bottom

## Step 3: Configure Database Connection

1. Open the file: `config/database.php`
2. Verify the database credentials match your XAMPP MySQL setup:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Empty by default in XAMPP
   define('DB_NAME', 'booktrack');
   ```
3. If you've changed the MySQL root password in XAMPP, update `DB_PASS` accordingly

## Step 4: Place Project Files in XAMPP

1. Copy your entire BookTrack project folder to:
   ```
   C:\xampp\htdocs\BookTrack  # Windows
   # or
   /Applications/XAMPP/htdocs/BookTrack  # Mac
   ```
   
   **OR**
   
   If you're working from your current location, you can create a virtual host (see Step 6 for alternative).

2. Ensure the folder structure is:
   ```
   BookTrack/
   ├── api/
   ├── config/
   ├── database/
   ├── assets/
   └── [all HTML files]
   ```

## Step 5: Test the Setup

### Test Database Connection

1. Create a test file `test_db.php` in your project root:
   ```php
   <?php
   require_once 'config/database.php';
   $conn = getDBConnection();
   if ($conn) {
       echo "Database connection successful!<br>";
       echo "Database: " . DB_NAME . "<br>";
       
       // Test query
       $result = $conn->query("SELECT COUNT(*) as count FROM books");
       $row = $result->fetch_assoc();
       echo "Books in database: " . $row['count'];
       closeDBConnection($conn);
   } else {
       echo "Database connection failed!";
   }
   ?>
   ```
2. Open in browser: `http://localhost/BookTrack/test_db.php`
3. You should see a success message with the book count

### Test API Endpoints

1. Test authentication endpoint:
   - Open: `http://localhost/BookTrack/api/auth.php?action=check`
   - You should see JSON response (likely an error since not logged in, but it confirms the API is working)

2. Test books endpoint:
   - Open: `http://localhost/BookTrack/api/books.php`
   - You should see JSON with sample books

## Step 6: Access Your Application

Open your web browser and navigate to:
```
http://localhost/BookTrack/
```

You can now:
- View the homepage
- Register new users
- Login with default credentials:
  - **Admin**: admin@booktrack.com / admin123
  - **User**: user@booktrack.com / user123

## Step 7: API Endpoints Reference

All API endpoints are located in the `api/` directory:

### Authentication
- `POST api/auth.php?action=login` - User login
- `POST api/auth.php?action=register` - User registration
- `GET api/auth.php?action=check` - Check session
- `DELETE api/auth.php?action=logout` - Logout

### Books
- `GET api/books.php` - Get all books
- `GET api/books.php?id={id}` - Get book by ID
- `GET api/books.php?action=search&q={query}` - Search books
- `POST api/books.php` - Create book (Admin only)
- `PUT api/books.php?id={id}` - Update book (Admin only)
- `DELETE api/books.php?id={id}` - Delete book (Admin only)

### Borrowings
- `GET api/borrowings.php?action=my` - Get user's borrowings
- `POST api/borrowings.php?action=borrow` - Borrow a book
- `POST api/borrowings.php?action=return` - Return a book

### Reservations
- `GET api/reservations.php?action=my` - Get user's reservations
- `POST api/reservations.php?action=create` - Create reservation
- `DELETE api/reservations.php?id={id}` - Cancel reservation

### Bookmarks
- `GET api/bookmarks.php` - Get user's bookmarks
- `POST api/bookmarks.php?book_id={id}` - Add bookmark
- `DELETE api/bookmarks.php?book_id={id}` - Remove bookmark

### Notifications
- `GET api/notifications.php` - Get notifications
- `PUT api/notifications.php?id={id}&action=read` - Mark as read
- `DELETE api/notifications.php?id={id}` - Delete notification

### Dashboard
- `GET api/dashboard.php` - Get user dashboard stats
- `GET api/dashboard.php?type=admin` - Get admin dashboard stats

### Users
- `GET api/users.php?action=profile` - Get own profile
- `PUT api/users.php?action=profile` - Update profile
- `GET api/users.php?action=list` - List all users (Admin only)

### Logs
- `GET api/logs.php` - Get system logs (Admin only)
- `DELETE api/logs.php?id={id}` - Delete log (Admin only)

## Default Data

The database schema includes:
- **Default Categories**: fiction, non-fiction, science, history, biography, technology, philosophy, art
- **Default Users**:
  - Admin: admin@booktrack.com / admin123
  - User: user@booktrack.com / user123
- **Sample Books**: 5 sample books are pre-loaded

## Troubleshooting

### Database Connection Errors

**Error: "Access denied for user 'root'@'localhost'"**
- Solution: Check your MySQL password in `config/database.php`
- Or reset MySQL root password in XAMPP

**Error: "Unknown database 'booktrack'"**
- Solution: Make sure you ran the schema.sql file to create the database

**Error: "Table doesn't exist"**
- Solution: Re-run the schema.sql file to create all tables

### PHP Errors

**Error: "Call to undefined function mysqli_connect()"**
- Solution: Make sure PHP mysqli extension is enabled in `php.ini`:
  ```ini
   extension=mysqli
   ```

**Error: "Headers already sent"**
- Solution: Make sure there's no output before `header()` calls in API files

### Apache Errors

**Error: "403 Forbidden"**
- Solution: Check file permissions and ensure Apache has access to the directory

**Error: "404 Not Found"**
- Solution: Verify your files are in the correct `htdocs` directory

### Session Issues

**Sessions not working**
- Solution: Check that `session.save_path` in `php.ini` is writable, or add this to your PHP files:
  ```php
  session_save_path(__DIR__ . '/sessions');
  mkdir(__DIR__ . '/sessions', 0777, true);
  ```

## Security Notes

⚠️ **Important for Production:**

1. **Change default passwords** - Update default user passwords immediately
2. **Database credentials** - Store credentials securely, never commit to version control
3. **Enable HTTPS** - Always use HTTPS in production
4. **Input validation** - All inputs are validated, but review before production
5. **SQL Injection** - All queries use prepared statements
6. **XSS Protection** - Sanitize output in frontend
7. **Session security** - Configure secure session settings for production

## Next Steps

1. **Update frontend HTML files** to use the API endpoints instead of localStorage/mock data
2. **Test all functionality** with the database
3. **Customize** categories, book data, and user roles as needed
4. **Add features** like book cover uploads, advanced search, etc.

## Support

If you encounter issues:
1. Check the XAMPP error logs: `C:\xampp\apache\logs\error.log`
2. Check PHP error logs: `C:\xampp\php\logs\php_error_log`
3. Check MySQL error logs in XAMPP Control Panel → MySQL → Logs

## File Structure

```
BookTrack/
├── api/                    # PHP API endpoints
│   ├── auth.php           # Authentication
│   ├── books.php          # Books CRUD
│   ├── borrowings.php     # Book borrowing
│   ├── reservations.php   # Book reservations
│   ├── bookmarks.php      # User bookmarks
│   ├── notifications.php   # User notifications
│   ├── dashboard.php      # Dashboard stats
│   ├── users.php          # User management
│   └── logs.php           # System logs (Admin)
├── config/
│   └── database.php       # Database configuration
├── database/
│   └── schema.sql         # Database schema
└── [HTML files]           # Frontend files
```

Happy coding! 📚

