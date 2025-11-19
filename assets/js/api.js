/**
 * BookTrack API Helper Functions
 * Shared API functions for all pages
 */

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
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('API call failed:', error);
        return { success: false, message: 'Network error: ' + error.message };
    }
}

// Authentication functions
async function apiLogin(email, password) {
    return await apiCall('auth.php?action=login', {
        method: 'POST',
        body: JSON.stringify({ email, password })
    });
}

async function apiRegister(userData) {
    return await apiCall('auth.php?action=register', {
        method: 'POST',
        body: JSON.stringify(userData)
    });
}

async function apiCheckAuth() {
    return await apiCall('auth.php?action=check');
}

async function apiLogout() {
    return await apiCall('auth.php?action=logout', {
        method: 'DELETE'
    });
}

// Books functions
async function apiGetBooks() {
    return await apiCall('books.php');
}

async function apiGetBook(id) {
    return await apiCall(`books.php?id=${id}`);
}

async function apiSearchBooks(query = '', filters = {}) {
    const params = new URLSearchParams({ action: 'search' });
    if (query) params.append('q', query);
    if (filters.category) params.append('category', filters.category);
    if (filters.availability) params.append('availability', filters.availability);
    return await apiCall(`books.php?${params}`);
}

async function apiCreateBook(bookData) {
    return await apiCall('books.php', {
        method: 'POST',
        body: JSON.stringify(bookData)
    });
}

async function apiUpdateBook(bookId, bookData) {
    return await apiCall(`books.php?id=${encodeURIComponent(bookId)}`, {
        method: 'PUT',
        body: JSON.stringify(bookData)
    });
}

async function apiDeleteBook(bookId) {
    return await apiCall(`books.php?id=${encodeURIComponent(bookId)}`, {
        method: 'DELETE'
    });
}

// Logs functions
async function apiGetLogs(params = {}) {
    const searchParams = new URLSearchParams();
    if (params.level) searchParams.append('level', params.level);
    if (params.user_id) searchParams.append('user_id', params.user_id);
    if (params.limit) searchParams.append('limit', params.limit);
    if (params.offset) searchParams.append('offset', params.offset);
    const query = searchParams.toString();
    const endpoint = query ? `logs.php?${query}` : 'logs.php';
    return await apiCall(endpoint);
}

async function apiGetLog(logId) {
    return await apiCall(`logs.php?id=${encodeURIComponent(logId)}`);
}

async function apiDeleteLog(logId) {
    return await apiCall(`logs.php?id=${encodeURIComponent(logId)}`, {
        method: 'DELETE'
    });
}

// Borrowings functions
async function apiBorrowBook(bookId, dueDays = 14) {
    return await apiCall('borrowings.php?action=borrow', {
        method: 'POST',
        body: JSON.stringify({ book_id: bookId, due_days: dueDays })
    });
}

async function apiReturnBook(borrowingId) {
    return await apiCall('borrowings.php?action=return', {
        method: 'POST',
        body: JSON.stringify({ borrowing_id: borrowingId })
    });
}

async function apiGetMyBorrowings(status = '') {
    const url = status ? `borrowings.php?action=my&status=${status}` : 'borrowings.php?action=my';
    return await apiCall(url);
}

// Reservations functions
async function apiCreateReservation(bookId) {
    return await apiCall('reservations.php?action=create', {
        method: 'POST',
        body: JSON.stringify({ book_id: bookId })
    });
}

async function apiGetMyReservations() {
    return await apiCall('reservations.php?action=my');
}

async function apiCancelReservation(reservationId) {
    return await apiCall(`reservations.php?id=${reservationId}`, {
        method: 'DELETE'
    });
}

// Bookmarks functions
async function apiGetBookmarks() {
    return await apiCall('bookmarks.php');
}

async function apiAddBookmark(bookId) {
    return await apiCall(`bookmarks.php?book_id=${bookId}`, {
        method: 'POST'
    });
}

async function apiRemoveBookmark(bookId) {
    return await apiCall(`bookmarks.php?book_id=${bookId}`, {
        method: 'DELETE'
    });
}

// Notifications functions
async function apiGetNotifications(filter = 'all') {
    return await apiCall(`notifications.php?filter=${filter}`);
}

async function apiMarkNotificationRead(id) {
    return await apiCall(`notifications.php?id=${id}&action=read`, {
        method: 'PUT'
    });
}

async function apiMarkAllNotificationsRead() {
    return await apiCall('notifications.php?action=read-all', {
        method: 'PUT'
    });
}

async function apiDeleteNotification(id) {
    return await apiCall(`notifications.php?id=${id}`, {
        method: 'DELETE'
    });
}

async function apiClearAllNotifications() {
    return await apiCall('notifications.php?action=clear-all', {
        method: 'DELETE'
    });
}

// Dashboard functions
async function apiGetDashboard(type = 'user') {
    return await apiCall(`dashboard.php?type=${type}`);
}

// User functions
async function apiGetProfile(userId = null) {
    const url = userId ? `users.php?action=profile&id=${userId}` : 'users.php?action=profile';
    return await apiCall(url);
}

async function apiUpdateProfile(profileData, userId = null) {
    const url = userId ? `users.php?action=profile&id=${userId}` : 'users.php?action=profile';
    return await apiCall(url, {
        method: 'PUT',
        body: JSON.stringify(profileData)
    });
}

// Admin/user management functions
async function apiGetUsers(params = {}) {
    const searchParams = new URLSearchParams({ action: 'list', ...params });
    return await apiCall(`users.php?${searchParams.toString()}`);
}

async function apiCreateUser(userData) {
    return await apiCall('users.php?action=create', {
        method: 'POST',
        body: JSON.stringify(userData)
    });
}

async function apiDeleteUser(userId) {
    return await apiCall(`users.php?id=${encodeURIComponent(userId)}`, {
        method: 'DELETE'
    });
}

async function apiUpdateUser(userId, userData) {
    return await apiUpdateProfile(userData, userId);
}

// Utility: Check if user is logged in
function isLoggedIn() {
    return sessionStorage.getItem('isLoggedIn') === 'true';
}

// Utility: Get current user info
function getCurrentUser() {
    return {
        id: sessionStorage.getItem('userId'),
        email: sessionStorage.getItem('userEmail'),
        fullName: sessionStorage.getItem('userFullName'),
        role: sessionStorage.getItem('userRole')
    };
}

// Utility: Require authentication (redirect if not logged in)
function requireAuth() {
    if (!isLoggedIn()) {
        window.location.href = 'login.html';
        return false;
    }
    return true;
}

// Utility: Require admin role
function requireAdmin() {
    const user = getCurrentUser();
    if (!isLoggedIn() || (user.role !== 'library_admin' && user.role !== 'library_moderator')) {
        window.location.href = 'login.html';
        return false;
    }
    return true;
}

