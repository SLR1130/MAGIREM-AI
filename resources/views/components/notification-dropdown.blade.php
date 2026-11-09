<!-- Notification Dropdown Component -->
<div class="notification-dropdown">
    <button class="notification-toggle" id="notificationToggle" onclick="toggleNotificationDropdown()">
        <svg style="width: 1.2rem; height: 1.2rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>
        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
    </button>

    <div class="notification-dropdown-menu" id="notificationMenu">
        <div class="notification-header">
            <h5>Notifications</h5>
            <button class="notification-clear-btn" onclick="markAllNotificationsRead()" title="Mark all as read">
                <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </button>
        </div>

        <div class="notification-list" id="notificationList">
            <div class="notification-empty">No notifications</div>
        </div>

        <div class="notification-footer">
            <a href="#" class="notification-view-all">View all notifications</a>
        </div>
    </div>
</div>

<style>
.notification-dropdown {
    position: relative;
    display: inline-block;
}

.notification-toggle {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 0.4rem 0.8rem;
    border-radius: 0.5rem;
    transition: all 0.3s;
    font-size: 0.9rem;
    position: relative;
}

.notification-toggle:hover {
    background-color: rgba(196, 181, 253, 0.1);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
}

.notification-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    min-width: 350px;
    max-height: 500px;
    overflow-y: auto;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    display: none;
    flex-direction: column;
    z-index: 1000;
    margin-top: 0.5rem;
}

.notification-dropdown-menu.active {
    display: flex;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.notification-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
}

.notification-clear-btn {
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 0.375rem;
    transition: all 0.2s;
}

.notification-clear-btn:hover {
    background-color: #e5e7eb;
    color: #111827;
}

.notification-list {
    flex: 1;
    overflow-y: auto;
    min-height: 100px;
    max-height: 400px;
}

.notification-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: #9ca3af;
    font-size: 0.875rem;
}

.notification-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
}

.notification-item:hover {
    background-color: #f9fafb;
}

.notification-item.unread {
    background-color: #eff6ff;
    border-left: 3px solid #3b82f6;
    padding-left: calc(1rem - 3px);
}

.notification-item-content {
    flex: 1;
    min-width: 0;
}

.notification-item-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}

.notification-item-title {
    font-weight: 600;
    color: #111827;
    font-size: 0.875rem;
    margin: 0 0 0.25rem 0;
}

.notification-item-message {
    color: #6b7280;
    font-size: 0.8125rem;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.notification-item-time {
    color: #9ca3af;
    font-size: 0.75rem;
    white-space: nowrap;
    flex-shrink: 0;
}

.notification-item-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.notification-delete-btn {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    transition: all 0.2s;
}

.notification-delete-btn:hover {
    background-color: #fee2e2;
}

.notification-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid #e5e7eb;
    text-align: center;
    background: #f9fafb;
}

.notification-view-all {
    color: #3b82f6;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
}

.notification-view-all:hover {
    color: #1e40af;
    text-decoration: underline;
}

/* Priority colors */
.notification-priority-urgent {
    border-left-color: #dc2626;
}

.notification-priority-high {
    border-left-color: #f97316;
}

.notification-priority-normal {
    border-left-color: #3b82f6;
}

.notification-priority-low {
    border-left-color: #10b981;
}
</style>

<script>
    function toggleNotificationDropdown() {
        const menu = document.getElementById('notificationMenu');
        menu.classList.toggle('active');
        if (menu.classList.contains('active')) {
            loadNotifications();
        }
    }

    function loadNotifications() {
        const notificationUrl = window.location.pathname.includes('/student/') 
            ? '{{ route("student.notifications.get") }}'
            : window.location.pathname.includes('/counselor/') 
                ? '{{ route("counselor.notifications.get") }}'
                : '{{ route("admin.notifications.get") }}';

        fetch(notificationUrl)
            .then(response => response.json())
            .then(data => {
                displayNotifications(data.notifications, data.unreadCount);
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    function displayNotifications(notifications, unreadCount) {
        const list = document.getElementById('notificationList');
        const badge = document.getElementById('notificationBadge');

        // Update badge
        if (unreadCount > 0) {
            badge.textContent = unreadCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }

        // Display notifications
        if (notifications.length === 0) {
            list.innerHTML = '<div class="notification-empty">No notifications</div>';
            return;
        }

        list.innerHTML = notifications.map(notification => `
            <div class="notification-item ${!notification.is_read ? 'unread' : ''}" onclick="handleNotificationClick('${notification.link}')">
                <div class="notification-item-icon">${notification.icon || '📢'}</div>
                <div class="notification-item-content">
                    <div class="notification-item-title">${escapeHtml(notification.title)}</div>
                    <div class="notification-item-message">${escapeHtml(notification.message)}</div>
                </div>
                <div class="notification-item-time">${formatTime(notification.created_at)}</div>
            </div>
        `).join('');
    }

    function handleNotificationClick(link) {
        if (link) {
            window.location.href = link;
        }
    }

    function markNotificationAsRead(id) {
        const url = window.location.pathname.includes('/student/') 
            ? '{{ route("student.notifications.read", ":id") }}'
            : window.location.pathname.includes('/counselor/') 
                ? '{{ route("counselor.notifications.read", ":id") }}'
                : '{{ route("admin.notifications.read", ":id") }}';

        fetch(url.replace(':id', id), { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
    }

    function markAllNotificationsRead() {
        const url = window.location.pathname.includes('/student/') 
            ? '{{ route("student.notifications.read-all") }}'
            : window.location.pathname.includes('/counselor/') 
                ? '{{ route("counselor.notifications.read-all") }}'
                : '{{ route("admin.notifications.read-all") }}';

        fetch(url, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            })
            .catch(error => console.error('Error marking all as read:', error));
    }

    function deleteNotification(id) {
        const url = window.location.pathname.includes('/student/') 
            ? '{{ route("student.notifications.delete", ":id") }}'
            : window.location.pathname.includes('/counselor/') 
                ? '{{ route("counselor.notifications.delete", ":id") }}'
                : '{{ route("admin.notifications.delete", ":id") }}';

        fetch(url.replace(':id', id), { method: 'DELETE' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            })
            .catch(error => console.error('Error deleting notification:', error));
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        
        return date.toLocaleDateString();
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.notification-dropdown');
        if (dropdown && !dropdown.contains(event.target)) {
            document.getElementById('notificationMenu').classList.remove('active');
        }
    });

    // Load notifications on page load
    document.addEventListener('DOMContentLoaded', loadNotifications);

    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
</script>
