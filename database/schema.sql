-- BookTrack Database Schema
-- MySQL Database Setup for BookTrack Application

-- Create database
CREATE DATABASE IF NOT EXISTS booktrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE booktrack;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(10) DEFAULT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin', 'library_admin') DEFAULT 'user',
    profile_image VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Books table
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(50) DEFAULT NULL,
    category_id INT DEFAULT NULL,
    genre VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    publisher VARCHAR(255) DEFAULT NULL,
    publish_year INT DEFAULT NULL,
    pages INT DEFAULT NULL,
    language VARCHAR(50) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    call_number VARCHAR(50) DEFAULT NULL,
    availability ENUM('available', 'borrowed', 'reserved') DEFAULT 'available',
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    total_borrows INT DEFAULT 0,
    added_date DATE DEFAULT (CURRENT_DATE),
    last_borrowed DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_title (title),
    INDEX idx_author (author),
    INDEX idx_isbn (isbn),
    INDEX idx_category (category_id),
    INDEX idx_availability (availability)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Borrowings table
CREATE TABLE IF NOT EXISTS borrowings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    borrowed_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_book (book_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reservations table
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    reserved_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date TIMESTAMP NOT NULL,
    status ENUM('pending', 'available', 'cancelled', 'expired') DEFAULT 'pending',
    notification_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_book (book_id),
    INDEX idx_status (status),
    INDEX idx_expiry_date (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookmarks table
CREATE TABLE IF NOT EXISTS bookmarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_book (user_id, book_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_book (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('due', 'available', 'system', 'overdue', 'reminder') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    book_id INT DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System logs table
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    level ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_level (level),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('fiction', 'Fiction books and novels'),
('non-fiction', 'Non-fiction books'),
('science', 'Science and technology books'),
('history', 'Historical books'),
('biography', 'Biographies and autobiographies'),
('technology', 'Technology and computing books'),
('philosophy', 'Philosophy books'),
('art', 'Art and literature books')
ON DUPLICATE KEY UPDATE name=name;

-- Insert default admin user (password: admin123)
-- Password hash for 'admin123' using password_hash PHP function
INSERT INTO users (first_name, last_name, email, password, role, status) VALUES
('Admin', 'User', 'admin@booktrack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active')
ON DUPLICATE KEY UPDATE email=email;

-- Insert default regular user (password: user123)
-- Password hash for 'user123'
INSERT INTO users (first_name, last_name, email, password, role, status) VALUES
('Regular', 'User', 'user@booktrack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active')
ON DUPLICATE KEY UPDATE email=email;

-- Insert sample books
INSERT INTO books (title, author, isbn, category_id, genre, description, cover_image, publisher, publish_year, pages, language, location, call_number, availability, rating, total_reviews, total_borrows, added_date, last_borrowed) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '978-0743273565', 1, 'Literary Fiction', 'A story of the fabulously wealthy Jay Gatsby and his love for the beautiful Daisy Buchanan. Set in the Jazz Age on Long Island, near New York City, the novel depicts first-person narrator Nick Carraway''s interactions with mysterious millionaire Jay Gatsby and Gatsby''s obsession to reunite with his former lover, Daisy Buchanan.', 'assets/img/Background.jpg', 'Charles Scribner''s Sons', 1925, 180, 'English', 'Main Library - Fiction Section', 'FIC FIT', 'available', 4.2, 1250, 45, '2020-03-15', '2024-11-20'),
('To Kill a Mockingbird', 'Harper Lee', '978-0446310789', 1, 'Literary Fiction', 'The story of young Scout Finch and her father Atticus in a racially divided Alabama town. Through the eyes of Scout, a feisty six-year-old tomboy, we see the world of Maycomb, Alabama, through her father''s eyes as he defends a black man accused of a crime.', 'assets/img/Background.jpg', 'J. B. Lippincott & Co.', 1960, 281, 'English', 'Main Library - Fiction Section', 'FIC LEE', 'available', 4.3, 2100, 67, '2019-08-22', '2024-12-01'),
('1984', 'George Orwell', '978-0451524935', 1, 'Dystopian Fiction', 'A dystopian novel about totalitarianism and surveillance society. The story follows the life of Winston Smith, a low-ranking member of ''the Party'', who is frustrated by the omnipresent eyes of the party, and its sinister ruler Big Brother.', 'assets/img/Background.jpg', 'Secker & Warburg', 1949, 328, 'English', 'Main Library - Fiction Section', 'FIC ORW', 'available', 4.1, 1800, 38, '2021-01-10', '2024-10-15'),
('The Art of War', 'Sun Tzu', '978-0140439199', 7, 'Military Strategy', 'Ancient Chinese text on military strategy and tactics. The work contains a detailed explanation and analysis of the Chinese military, from weapons and strategy to rank and discipline.', 'assets/img/Background.jpg', 'Penguin Classics', -500, 273, 'Chinese (Translated)', 'Main Library - Philosophy Section', '355.02 SUN', 'available', 4.0, 890, 23, '2018-11-05', '2024-09-28'),
('Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', '978-0062316097', 2, 'History', 'A groundbreaking narrative of humanity''s creation and evolution. Harari explores the ways in which biology and history have defined us and enhanced our understanding of what it means to be ''human''.', 'assets/img/Background.jpg', 'Harper', 2011, 443, 'English', 'Main Library - History Section', '909 HAR', 'available', 4.4, 3200, 89, '2022-06-18', '2024-11-25')
ON DUPLICATE KEY UPDATE title=title;

