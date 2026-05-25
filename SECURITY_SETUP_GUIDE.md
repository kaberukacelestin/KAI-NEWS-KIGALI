# KAI News Kigali — Security Setup Guide

## ⚠️ DEFAULT ADMIN CREDENTIALS

**DEFAULT CREDENTIALS (MUST BE CHANGED IMMEDIATELY):**
- Username: `admin`
- Password: `admin123`

---

## 📋 METHOD 1: Change Password in Admin UI (Recommended)

### Step-by-Step:

1. **Login to Admin Panel**
   - Go to: `http://localhost/kai_news/admin/login.php`
   - Enter username: `admin`
   - Enter password: `admin123`

2. **Navigate to Profile**
   - Click on your profile icon/name in top-right corner of dashboard
   - OR go directly to: `admin/profile.php`

3. **Update Credentials**
   - **Full Name** - Enter your full name (optional but recommended)
   - **Email** - Enter your email address
   - **Username** - Change from `admin` to something more secure
   - **New Password** - Enter a strong password (min 8 characters recommended)
   - **Re-enter Password** - Confirm the new password
   - Click **Update Credentials**

4. **Logout and Login Again**
   - Click Logout
   - Login with new username and password
   - Verify it works

---

## 💻 METHOD 2: Change Password in Database/Code (Direct)

### File to Modify:
📁 **File:** [database.sql](database.sql)  
📍 **Line:** 16

### Default Insert Statement:
```sql
-- Line 16-17 in database.sql
INSERT INTO `admins` (`username`, `email`, `password`, `full_name`, `role`)
VALUES ('admin', 'admin@kainews.rw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'superadmin');
```

### How to Generate New Password Hash:

**Option A: Using PHP CLI**
```bash
# Open PowerShell and run:
cd c:\xampp\php
php -r "echo password_hash('your_new_password', PASSWORD_BCRYPT);"
```

**Option B: Using Online Tool**
- Go to: https://www.php.net/manual/en/function.password-hash.php
- Or use a bcrypt online generator

### Steps to Change:

1. **Generate new password hash:**
   ```bash
   # Example: Generate hash for "MySecurePass2024"
   php -r "echo password_hash('MySecurePass2024', PASSWORD_BCRYPT);"
   # Output: $2y$10$xjQ3k5L9m2Z8n4P7q1R9...
   ```

2. **Update database.sql** (Line 16):
   ```sql
   INSERT INTO `admins` (`username`, `email`, `password`, `full_name`, `role`)
   VALUES ('admin', 'admin@kainews.rw', '$2y$10$xjQ3k5L9m2Z8n4P7q1R9...', 'Super Admin', 'superadmin');
   ```

3. **Re-import database** (after setup):
   ```sql
   -- In PhpMyAdmin or MySQL client:
   DROP TABLE IF EXISTS admins;
   -- Then re-run the INSERT statement
   ```

---

## 🔐 Security Improvements Implemented

✅ **Article Ownership Enforcement**
- Journalists can now ONLY edit/delete their own articles
- Editors and superadmins can edit any article
- Applied to: `admin/news-edit.php`, `admin/news-list.php`

✅ **Activity Logging (Audit Trail)**
- All actions logged: article edits, deletes, logins, failed logins
- Stores: who did it, what they did, when, IP address, user agent
- Check logs via: `admin/activity-log.php` (create this page to view logs)

✅ **Notification System**
- System can now send notifications to admins
- Types: article_edit, article_delete, news_published, system, admin_message
- Recipients: specific admins or all superadmins

---

## 📊 Database Tables Added

### 1. `notifications` Table
- Stores notifications for admins
- Types: article_edit, article_delete, news_published, system, admin_message
- Tracks: sender, recipient, message, related article, read status

### 2. `activity_logs` Table
- Audit trail of all admin actions
- Stores: admin, action, entity type, IP address, timestamp, success/failed status
- Indexed for fast queries

---

## 🛡️ Best Practices

1. ✅ Change default credentials immediately after setup
2. ✅ Use strong passwords (12+ characters, mix of uppercase, lowercase, numbers, symbols)
3. ✅ Don't share admin credentials via email or chat
4. ✅ Review activity logs regularly for suspicious actions
5. ✅ Mark journalists as `suspended` role to disable their account
6. ✅ Use different usernames for different admins

---

## 📝 Helper Functions Added to `includes/config.php`

### Notification Functions:
```php
createNotification($recipient_id, $type, $title, $message, $sender_id, $article_id)
getUnreadNotifications($admin_id, $limit)
getUnreadNotificationCount($admin_id)
markNotificationAsRead($notification_id)
markAllNotificationsAsRead($admin_id)
```

### Activity Logging Functions:
```php
logActivity($admin_id, $action, $entity_type, $entity_id, $old_value, $new_value, $status)
getActivityLogs($limit, $admin_id, $days)
```

---

## 🔧 Integration Examples

### Logging an Article Creation:
```php
logActivity($admin_id, 'create_article', 'article', $article_id, null, json_encode($article_data));
```

### Logging a Failed Login:
```php
logActivity($admin_id, 'login_failed', 'user', $admin_id, null, null, 'failed');
```

### Creating a Notification:
```php
createNotification($journalist_id, 'article_edit', 'Your Article Was Edited', 
                   'Your article "Title" was edited by Admin', $editor_id, $article_id);
```

---

## 🎯 Next Steps (Optional Enhancements)

1. **Create Activity Log Viewer Page** (`admin/activity-log.php`)
   - Display all logged activities
   - Filter by date, admin, action type
   - Show IP addresses and success/failed attempts

2. **Create Notifications Dashboard** (`admin/notifications.php`)
   - Show unread notifications count
   - Display notification list with read/unread status
   - Allow marking as read or deleting

3. **Add Email Alerts**
   - Send email when article is edited
   - Send email for critical actions (user deleted, settings changed)

4. **Two-Factor Authentication (2FA)**
   - Add TOTP/SMS verification to login

5. **Role-Based Permissions Expansion**
   - Add granular permissions (e.g., "can_edit_settings", "can_delete_ads")
   - Create permission matrix in database

---

## 📞 Support

For issues or questions, review the files:
- 🔗 [admin/profile.php](admin/profile.php) - Profile/credentials update page
- 🔗 [admin/login.php](admin/login.php) - Login with activity logging
- 🔗 [includes/config.php](includes/config.php) - Helper functions
- 🔗 [database.sql](database.sql) - Database schema with new tables
