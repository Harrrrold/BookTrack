# Team Setup Guide - BookTrack

**Quick setup for team members who just cloned the repository**

## Option 1: Shared Database (Recommended) 🌐

**Best for:** All team members working together on the same data

### Setup Steps:

1. **Get Database Credentials from Team Lead**
   - Ask your team lead for:
     - Database host (e.g., `db4free.net` or IP address)
     - Database username
     - Database password
     - Database name

2. **Create Local Config File**
   - Copy `config/database.local.php.example` to `config/database.local.php`
   - Open `config/database.local.php`
   - Update with the credentials you received:
     ```php
     <?php
     define('DB_HOST', 'db4free.net'); // or IP address
     define('DB_USER', 'your_team_user');
     define('DB_PASS', 'your_team_password');
     define('DB_NAME', 'your_team_database');
     ?>
     ```

3. **Test Connection**
   - Start XAMPP (Apache and MySQL)
   - Open: `http://localhost/BookTrack/test_db.php`
   - Should show success message

4. **Done!** ✅
   - You're now connected to the shared database
   - All team members will see the same data

---

## Option 2: Local Database (For Development) 💻

**Best for:** Each developer testing independently

### Setup Steps:

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start **Apache** and **MySQL**

2. **Import Database**
   - Open: `http://localhost/phpmyadmin`
   - Click **SQL** tab
   - Open `database/schema.sql` in a text editor
   - Copy entire contents and paste into SQL box
   - Click **Go**

3. **Create Default Users**
   - Open: `http://localhost/BookTrack/setup/setup_default_users.php`
   - Should show success messages

4. **Done!** ✅
   - Your local database is ready
   - You have your own isolated data

---

## Quick Start Checklist

### For Shared Database:
- [ ] Get credentials from team lead
- [ ] Create `config/database.local.php` with shared credentials
- [ ] Test connection: `http://localhost/BookTrack/test_db.php`
- [ ] Done!

### For Local Database:
- [ ] Start XAMPP (Apache + MySQL)
- [ ] Import `database/schema.sql` in phpMyAdmin
- [ ] Run `setup/setup_default_users.php`
- [ ] Test connection: `http://localhost/BookTrack/test_db.php`
- [ ] Done!

---

## Setting Up a Shared Database (For Team Lead)

### Recommended: Free Online Database Services

#### 1. **db4free.net** (Easiest, Free)

1. Go to: https://www.db4free.net
2. Click "Sign Up"
3. Fill in:
   - Username (will be your database user)
   - Password (choose a strong one)
   - Email
4. Confirm email
5. Login to control panel
6. Create database:
   - Click "Create Database"
   - Name: `booktrack` (or your choice)
7. Import schema:
   - Click phpMyAdmin link
   - Select your database
   - Import tab → Choose file → Select `database/schema.sql`
8. **Share with team:**
   ```
   Host: db4free.net
   Port: 3306
   Username: [your_username]
   Password: [your_password]
   Database: booktrack
   ```

#### 2. **RemotelyMySQL.com** (Free Alternative)

1. Visit: https://remotemysql.com
2. Sign up for free account
3. Create database
4. Get credentials
5. Share with team

#### 3. **FreeMySQLHosting.net** (Another Option)

1. Visit: https://www.freemysqlhosting.net
2. Sign up
3. Create database
4. Share credentials

### Setup Shared Database Script

Create a file `setup_shared_db.php` and share it with team:

```php
<?php
// One-time setup for shared database
require_once 'config/database.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed! Check your credentials.");
}

echo "✓ Connected to shared database successfully!\n";
echo "Database: " . DB_NAME . "\n\n";

// Test query
$result = $conn->query("SELECT COUNT(*) as count FROM books");
$row = $result->fetch_assoc();
echo "Books in database: " . $row['count'] . "\n";

$result = $conn->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetch_assoc();
echo "Users in database: " . $row['count'] . "\n";

closeDBConnection($conn);
?>
```

---

## Team Database Options Comparison

| Option | Pros | Cons | Best For |
|--------|------|------|----------|
| **Shared Online DB** | ✅ Everyone sees same data<br>✅ Easy setup<br>✅ Free options available | ❌ Requires internet<br>❌ May be slower | Most teams |
| **Local DB** | ✅ Fast<br>✅ No internet needed<br>✅ Isolated testing | ❌ Each person has different data<br>❌ Can't collaborate on data | Individual development |
| **Local Network DB** | ✅ Fast<br>✅ Shared data<br>✅ No internet needed | ❌ Same network required<br>❌ Host needs to configure | Office/same location |

---

## Step-by-Step: First Time Setup

### Step 1: Clone Repository
```bash
git clone [your-repo-url]
cd BookTrack
```

### Step 2: Choose Database Option

**If using shared database:**
1. Get credentials from team lead
2. Create `config/database.local.php`:
   ```php
   <?php
   define('DB_HOST', 'db4free.net'); // or host provided
   define('DB_USER', 'username_from_team_lead');
   define('DB_PASS', 'password_from_team_lead');
   define('DB_NAME', 'database_name_from_team_lead');
   ?>
   ```

**If using local database:**
1. Start XAMPP
2. Import `database/schema.sql`
3. Run `setup/setup_default_users.php`
4. `config/database.local.php` is optional (uses defaults)

### Step 3: Copy to XAMPP (If Needed)

If your project is not in `C:\xampp\htdocs\BookTrack`:
```bash
# Copy entire folder to htdocs
xcopy /E /I "C:\path\to\BookTrack" "C:\xampp\htdocs\BookTrack"
```

### Step 4: Test Setup

1. Open: `http://localhost/BookTrack/test_db.php`
2. Should show: ✓ Database connection successful!

### Step 5: Test Login

1. Go to: `http://localhost/BookTrack/login.html`
2. Login with:
   - Email: `admin@booktrack.com`
   - Password: `admin123`

---

## Troubleshooting

### "Database connection failed"
**Solution:**
- Check XAMPP MySQL is running
- Verify credentials in `config/database.local.php`
- Check database name exists

### "Access denied for user"
**Solution:**
- Verify username and password are correct
- Check user has permission to access database
- For shared DB: Verify host address is correct

### "Unknown database"
**Solution:**
- Database doesn't exist yet
- Ask team lead to create it, or create locally

### "Table doesn't exist"
**Solution:**
- Database schema not imported
- Run `database/schema.sql` in phpMyAdmin

### "404 Not Found"
**Solution:**
- Files not in `htdocs` folder
- Copy files to `C:\xampp\htdocs\BookTrack`

---

## Team Workflow Recommendations

### Option A: Shared Database (Recommended)
- ✅ Everyone uses same database
- ✅ See each other's data
- ✅ Test with real collaboration
- Setup: Team lead creates online DB, shares credentials

### Option B: Local + Shared for Testing
- ✅ Developers use local DB for testing
- ✅ Shared DB for demo/staging
- ✅ Switch between them easily
- Setup: Create two `database.local.php` files, switch as needed

---

## Security Notes

⚠️ **Important:**
- Never commit `config/database.local.php` to Git (it's in `.gitignore`)
- Share database credentials privately (DM, not public chat)
- Change default passwords in production
- Use strong passwords for shared databases

---

## Quick Reference

### Default Credentials (Local DB Only)
- Admin: `admin@booktrack.com` / `admin123`
- User: `user@booktrack.com` / `user123`

### File Locations
- Database config: `config/database.local.php` (create this)
- Database schema: `database/schema.sql`
- Test connection: `test_db.php`
- Setup users: `setup/setup_default_users.php`

### Key URLs (Local)
- Homepage: `http://localhost/BookTrack/`
- Login: `http://localhost/BookTrack/login.html`
- Test DB: `http://localhost/BookTrack/test_db.php`
- API Test: `http://localhost/BookTrack/api/books.php`

---

## Need Help?

1. **Check error messages** - They usually tell you what's wrong
2. **Verify XAMPP is running** - Both Apache and MySQL must be green
3. **Check browser console** - F12 → Console tab for JavaScript errors
4. **Check PHP errors** - Look in XAMPP error logs
5. **Ask team lead** - For shared database credentials

---

**That's it! You should be up and running! 🚀**

