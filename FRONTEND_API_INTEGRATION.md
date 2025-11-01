# Frontend to API Integration Guide

This guide shows how to connect your HTML frontend pages to the PHP API backend.

## Current Status

Your frontend currently uses:
- `localStorage` for storing data
- Mock/sample data in JavaScript
- No real database connection

## Goal

Connect frontend to backend API so:
- Data comes from MySQL database
- Users can register/login
- Books are loaded from database
- All features work with real data

## Quick Example: Update book_search.html

Here's how to update `book_search.html` to use the API:

### Current Code (Uses Mock Data):
```javascript
const sampleBooks = [
    { id: 1, title: "The Great Gatsby", ... },
    // ... more mock books
];

function performSearch() {
    // Filters mock data
    const filteredBooks = sampleBooks.filter(...);
    displayResults(filteredBooks);
}
```

### Updated Code (Uses API):
```javascript
// Load books from API
async function loadBooks() {
    try {
        const response = await fetch('api/books.php');
        const data = await response.json();
        
        if (data.success) {
            return data.books;
        } else {
            console.error('Failed to load books');
            return [];
        }
    } catch (error) {
        console.error('Error loading books:', error);
        return [];
    }
}

async function performSearch() {
    const query = document.getElementById('searchQuery').value;
    const category = document.getElementById('categoryFilter').value;
    const availability = document.getElementById('availabilityFilter').value;
    
    // Show loading
    document.getElementById('loadingSpinner').classList.remove('d-none');
    
    try {
        // Build search URL
        let url = 'api/books.php?action=search';
        if (query) url += `&q=${encodeURIComponent(query)}`;
        if (category) url += `&category=${encodeURIComponent(category)}`;
        if (availability) url += `&availability=${encodeURIComponent(availability)}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            displayResults(data.books);
        } else {
            console.error('Search failed:', data.message);
            displayResults([]);
        }
    } catch (error) {
        console.error('Search error:', error);
        displayResults([]);
    } finally {
        document.getElementById('loadingSpinner').classList.add('d-none');
    }
}
```

## API Integration Helper Functions

Create a shared JavaScript file `assets/js/api.js`:

```javascript
// API Base URL
const API_BASE = 'api/';

// Helper function for API calls
async function apiCall(endpoint, options = {}) {
    try {
        const url = API_BASE + endpoint;
        const response = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('API call failed:', error);
        return { success: false, message: 'Network error' };
    }
}

// Authentication functions
async function login(email, password) {
    return await apiCall('auth.php?action=login', {
        method: 'POST',
        body: JSON.stringify({ email, password })
    });
}

async function register(userData) {
    return await apiCall('auth.php?action=register', {
        method: 'POST',
        body: JSON.stringify(userData)
    });
}

async function checkAuth() {
    return await apiCall('auth.php?action=check');
}

async function logout() {
    return await apiCall('auth.php?action=logout', {
        method: 'DELETE'
    });
}

// Books functions
async function getBooks() {
    return await apiCall('books.php');
}

async function getBook(id) {
    return await apiCall(`books.php?id=${id}`);
}

async function searchBooks(query, filters = {}) {
    const params = new URLSearchParams({ action: 'search', ...filters });
    if (query) params.append('q', query);
    return await apiCall(`books.php?${params}`);
}

// Borrowings functions
async function borrowBook(bookId, dueDays = 14) {
    return await apiCall('borrowings.php?action=borrow', {
        method: 'POST',
        body: JSON.stringify({ book_id: bookId, due_days: dueDays })
    });
}

async function returnBook(borrowingId) {
    return await apiCall('borrowings.php?action=return', {
        method: 'POST',
        body: JSON.stringify({ borrowing_id: borrowingId })
    });
}

async function getMyBorrowings() {
    return await apiCall('borrowings.php?action=my');
}

// Reservations functions
async function createReservation(bookId) {
    return await apiCall('reservations.php?action=create', {
        method: 'POST',
        body: JSON.stringify({ book_id: bookId })
    });
}

async function getMyReservations() {
    return await apiCall('reservations.php?action=my');
}

async function cancelReservation(reservationId) {
    return await apiCall(`reservations.php?id=${reservationId}`, {
        method: 'DELETE'
    });
}

// Bookmarks functions
async function getBookmarks() {
    return await apiCall('bookmarks.php');
}

async function addBookmark(bookId) {
    return await apiCall(`bookmarks.php?book_id=${bookId}`, {
        method: 'POST'
    });
}

async function removeBookmark(bookId) {
    return await apiCall(`bookmarks.php?book_id=${bookId}`, {
        method: 'DELETE'
    });
}

// Notifications functions
async function getNotifications(filter = 'all') {
    return await apiCall(`notifications.php?filter=${filter}`);
}

async function markNotificationRead(id) {
    return await apiCall(`notifications.php?id=${id}&action=read`, {
        method: 'PUT'
    });
}

// Dashboard functions
async function getDashboard(type = 'user') {
    return await apiCall(`dashboard.php?type=${type}`);
}
```

## Step-by-Step: Update login.html

1. **Replace the demo credentials check:**
```javascript
// OLD:
const credentials = {
    'admin@booktrack.com': { password: 'admin123', role: 'admin' },
    // ...
};

// NEW:
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    
    setLoading(true);
    
    try {
        const response = await fetch('api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Store user info in sessionStorage
            sessionStorage.setItem('userEmail', data.user.email);
            sessionStorage.setItem('userFullName', data.user.full_name);
            sessionStorage.setItem('userRole', data.user.role);
            sessionStorage.setItem('userId', data.user.id);
            sessionStorage.setItem('isLoggedIn', 'true');
            
            // Redirect based on role
            if (data.user.role === 'admin') {
                window.location.href = 'admin_Dasboard.html';
            } else {
                window.location.href = 'user_Dashboard.html';
            }
        } else {
            setLoading(false);
            showError(document.getElementById('password'), data.message || 'Invalid email or password');
        }
    } catch (error) {
        setLoading(false);
        alert('Login failed. Please try again.');
    }
});
```

## Priority Files to Update

Update in this order:

1. ✅ **login.html** - Already updated above
2. ✅ **register.html** - Already has API integration
3. **book_search.html** - Connect to books API
4. **book_detail.html** - Load book from API
5. **user_Dashboard.html** - Load stats from dashboard API
6. **my_Book.html** - Load user's books from borrowings API
7. **bookmark.html** - Load bookmarks from API
8. **notifications.html** - Load notifications from API

## Testing Your Integration

1. **Open browser console** (F12)
2. **Check Network tab** - See API requests
3. **Check for errors** - JavaScript errors will show in console
4. **Test each feature** - Register → Login → Search → etc.

## Common Issues

**CORS Errors:**
- Make sure API returns proper headers
- `.htaccess` file handles this

**401 Unauthorized:**
- User not logged in
- Session expired
- Need to call login API first

**404 Not Found:**
- Check API file paths are correct
- Verify files are in `api/` directory

**500 Server Error:**
- Check PHP error logs
- Verify database connection
- Check API file syntax

## Next Steps

1. Create `assets/js/api.js` with helper functions
2. Include it in your HTML files: `<script src="assets/js/api.js"></script>`
3. Update each HTML file one by one
4. Test thoroughly after each update

