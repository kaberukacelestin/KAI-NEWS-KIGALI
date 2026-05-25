# KAI News Kigali - Permissions & Notifications System Audit

## Executive Summary
The kai_news codebase has a **basic permission system** with 3 roles (superadmin, editor, journalist) but has **critical gaps**:
- ✅ Role-based access control exists
- ✅ Settings page restricted to superadmin
- ✅ Permission guards on main pages
- ❌ **No per-article ownership checks** - Journalists can edit each other's articles
- ❌ **No notification system** - Completely missing
- ❌ **No audit trail** - No activity logging

---

## 1. Current Role Structure

### Database Table: `admins`
```
Columns: id, username, email, password, full_name, profile_picture, role, created_at
```

### Roles Defined:
| Role | Description | Permissions |
|------|-------------|-------------|
| **superadmin** | Full system access | add_news, edit_news, delete_news, manage_ads, manage_settings, manage_journalists |
| **editor** | Content & ad management | add_news, edit_news, delete_news, manage_ads |
| **journalist** | Content creation | add_news, edit_news |
| **suspended** | Account disabled | (none - auto-logout on login) |

---

## 2. Permission System Architecture

### Implementation Location:
- **File**: `admin/auth.php`
- **Functions**:
  - `canDo(string $action): bool` (lines 24-32) - Checks if current role can perform action
  - `requirePermission(string $action): void` (lines 34-38) - Guard function that redirects if denied

### Session Management:
```php
$_SESSION['admin_id']   // User ID from admins table
$_SESSION['admin_name'] // User's full_name
$_SESSION['admin_role'] // User's role
```

### How It Works:
1. User logs in via `admin/login.php` → Sets session variables
2. Each protected page calls `require_once 'auth.php'` which:
   - Checks if logged in
   - Checks if role='suspended' → logout
   - Checks permissions via `canDo()` or `requirePermission()`

### Permission Check Pattern:
```php
requirePermission('add_news');  // Fails if role not in ['journalist','editor','superadmin']
requirePermission('manage_settings');  // Fails if role != 'superadmin'
```

---

## 3. Current Permission Enforcement

### Pages Using Permission Guards:

| Page | Permission Required | Current Behavior |
|------|-------------------|------------------|
| `news-add.php` | add_news | ✓ Properly guarded |
| `news-edit.php` | edit_news | ✓ Guarded but NO ownership check |
| `news-list.php` | edit_news | ✓ Shows all articles to all editors/superadmins |
| `ads.php` | manage_ads | ✓ Properly guarded |
| `settings.php` | manage_settings | ✓ Superadmin only |
| `manage-journalists.php` | Direct role check | ✓ Superadmin only (hardcoded check) |
| `dashboard.php` | Session check only | ✓ Everyone can view |

### Permission Error Handling:
- Unauthorized access → Redirect to `dashboard.php?err=access`
- Error message displayed: "Access denied — your journalist account does not have permission to do that."

---

## 4. Settings Page Access Control

### File: `admin/settings.php`

**Access Restriction**: `requirePermission('manage_settings')` (line 3)
- Only `superadmin` role can access
- Anyone else gets redirected to dashboard with error

**Managed Settings**:
- Site Information: name, tagline, email, phone, address
- Social Media: Facebook, X (Twitter), Instagram, YouTube URLs
- Live Events: URL, title, description, time, button text, thumbnail
- Advertisement Video: URL, file upload (30MB max), title, description
- WhatsApp Number
- Breaking News Ticker (separated by |)
- Footer Text

**Storage**: `settings` table (key-value store)
- No role-specific settings
- No partial access (all-or-nothing superadmin access)
- Uses `INSERT INTO settings ... ON DUPLICATE KEY UPDATE` pattern

**No Audit Trail**: 
- No tracking of who changed what setting or when
- Settings have no update_at timestamp

---

## 5. News Add/Edit Permission Checks

### File: `admin/news-add.php`
✅ **Permission Guard**: `requirePermission('add_news')` (line 3)
- Journalist, editor, superadmin can add

✅ **Author Tracking**: 
- New articles stored with `admin_id = $_SESSION['admin_id']` (line 50)

### File: `admin/news-edit.php`
✅ **Permission Guard**: `requirePermission('edit_news')` (line 3)
- Journalist, editor, superadmin can edit

❌ **SECURITY GAP**: NO ownership check
- Any journalist can edit ANY article (not just their own)
- No check like: `if ($admin_role == 'journalist' && $article['admin_id'] != $admin_id) DENY`

### File: `admin/news-list.php`
✓ **List View Guard**: `requirePermission('edit_news')` is checked elsewhere (via auth.php require)

❌ **SECURITY GAP**: No filtering
- Shows ALL articles to anyone with edit_news permission
- Journalists see articles by other journalists → can edit them

❌ **Delete Without Separate Check**:
- Delete handler (lines 4-22) only checks for `?delete=ID` parameter
- Uses only `requirePermission('edit_news')` which allows journalists
- No `requirePermission('delete_news')` separate check
- No ownership validation before delete

**Delete Query** (line 17):
```php
db()->query("DELETE FROM news WHERE id=$del_id");  // No admin_id filter!
```

---

## 6. Notifications System Status: **DOES NOT EXIST**

### What's Missing:
1. ❌ No `notifications` table
2. ❌ No notification creation code
3. ❌ No notification UI/dashboard
4. ❌ No email alert system
5. ❌ No in-app notification display
6. ❌ No notification preferences

### Grep Search Results:
Searched entire codebase for: `notify|notification|alert|email`
- Found only: email display in footer/contact pages, no notification engine

### Current Workarounds:
- Uses basic alert messages: `<div class="alert alert-success">`
- Only for form submission feedback (not system events)
- No persistent notification records

---

## 7. Critical Security Gaps Summary

### Gap #1: Article Ownership Not Enforced
**Severity**: HIGH  
**Affected Pages**: news-edit.php, news-list.php (delete)  
**Issue**: Journalists can edit/delete articles created by other journalists

**Current Code** (news-edit.php line 6):
```php
if (!$id) { header("Location: news-list.php"); exit; }
$article = db()->query("SELECT * FROM news WHERE id=$id")->fetch_assoc();
// No check: if ($admin_role == 'journalist' && $article['admin_id'] != $admin_id)
```

**Required Fix**:
```php
if ($admin_role == 'journalist' && $article['admin_id'] != $admin_id) {
    header("Location: news-list.php?err=access");
    exit;
}
```

### Gap #2: Delete Permission Not Properly Guarded
**Severity**: HIGH  
**Location**: news-list.php (lines 4-22)  
**Issue**: Delete uses only 'edit_news' permission instead of separate 'delete_news' check

**Current Code**:
```php
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $article = db()->query("SELECT thumbnail FROM news WHERE id=$del_id")->fetch_assoc();
    if ($article) {
        // ... cleanup code ...
        db()->query("DELETE FROM news WHERE id=$del_id");  // No auth check here!
    }
}
```

**Should Have**:
```php
requirePermission('delete_news');  // Add this at top of file
// ... then in delete handler ...
if ($admin_role == 'journalist') {
    header("Location: news-list.php?err=access");
    exit;
}
```

### Gap #3: No Notification System
**Severity**: MEDIUM  
**Impact**: No way to alert journalists of important events  
**Missing Events**:
- Article published/approved
- Article reached view milestone
- New journalist applications (for superadmin)
- Settings changed
- User role changed

### Gap #4: No Activity Audit Trail
**Severity**: MEDIUM  
**Impact**: Cannot track who did what when  
**Missing Data**:
- Who created/edited/deleted article
- When changes were made
- What values were changed
- Who modified settings

### Gap #5: List View Shows All Articles
**Severity**: MEDIUM  
**Location**: news-list.php (lines 31-36)  
**Issue**: Journalists see all articles in the system

**Current Code**:
```php
$news = db()->query("SELECT n.*, c.name_rw as cat_name
    FROM news n JOIN categories c ON n.category_id=c.id $where
    ORDER BY n.created_at DESC LIMIT $per_page OFFSET $offset")->fetch_all(MYSQLI_ASSOC);
```

**Should Filter for Journalists**:
```php
if ($admin_role == 'journalist') {
    $where .= " AND n.admin_id = $admin_id";
}
```

---

## 8. Journalist Management Workflow

### File: `admin/manage-journalists.php`

**Access Control**: Hardcoded superadmin check (line 9)
```php
if ($admin_role !== 'superadmin') {
    header("Location: dashboard.php");
    exit;
}
```

**Workflow**:
1. Journalists register via `admin/journalist-register.php` → Create `journalist_requests` record
2. Superadmin reviews pending requests on `manage-journalists.php`
3. Superadmin approves → Creates actual `admins` record with role='journalist'
4. Or superadmin rejects → Marks request as rejected

**Journalist Statuses**:
- `pending` - Awaiting superadmin review
- `approved` - Account created, journalist can login
- `rejected` - Application denied

**Actions on Journalist Accounts**:
- ✓ Approve pending request → Create admin account
- ✓ Reject request → Mark rejected
- ✓ Toggle suspend/unsuspend → Change role between 'journalist' and 'suspended'
- ✓ Delete account
- ✓ Manually add journalist

❌ **Missing**: No notification sent to journalist when approved/rejected

---

## 9. Recommended Improvements (Priority Order)

### PRIORITY 1: Fix Critical Security Gaps

#### Fix 1.1: Enforce Article Ownership for Journalists
**File**: `admin/news-edit.php`  
**Change**: Add ownership check before allowing edit
```php
// After loading article:
if ($admin_role == 'journalist' && $article['admin_id'] != $admin_id) {
    header("Location: news-list.php?err=access");
    exit;
}
```

#### Fix 1.2: Require Delete Permission + Ownership Check
**File**: `admin/news-list.php`  
**Changes**:
1. Add `requirePermission('delete_news')` at top of file
2. Add ownership check in delete handler
3. Journalists can only delete their own articles

**Code**:
```php
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    
    // Check permission
    if ($admin_role == 'journalist') {
        header("Location: news-list.php?err=access");
        exit;
    }
    
    // For editors, check ownership
    if ($admin_role == 'editor') {
        $check = db()->query("SELECT admin_id FROM news WHERE id=$del_id");
        if ($check && $check->num_rows > 0) {
            $article = $check->fetch_assoc();
            if ($article['admin_id'] != $admin_id) {
                header("Location: news-list.php?err=access");
                exit;
            }
        }
    }
    
    // ... then proceed with delete
}
```

#### Fix 1.3: Filter Articles for Journalists
**File**: `admin/news-list.php`  
**Change**: Modify query to filter by author for journalists
```php
$where = "WHERE 1=1";
if ($search) {
    $s = db()->real_escape_string($search);
    $where .= " AND (n.title_rw LIKE '%$s%' OR n.title_en LIKE '%$s%')";
}
// Add journalist filter:
if ($admin_role == 'journalist') {
    $where .= " AND n.admin_id = $admin_id";
}
```

### PRIORITY 2: Create Notification System

#### New Database Tables:
```sql
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title_rw VARCHAR(255),
    title_en VARCHAR(255),
    message_rw TEXT,
    message_en TEXT,
    related_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX (user_id, is_read)
);

CREATE TABLE notification_preferences (
    admin_id INT PRIMARY KEY,
    email_on_new_journalist TINYINT(1) DEFAULT 1,
    email_on_article_published TINYINT(1) DEFAULT 0,
    email_on_role_change TINYINT(1) DEFAULT 1,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);
```

#### Notification Events to Trigger:
1. **new_journalist_request** - When journalist registers (→ superadmin)
2. **journalist_approved** - When application approved (→ journalist)
3. **journalist_rejected** - When application rejected (→ journalist)
4. **article_published** - When article published (→ author if draft→published)
5. **article_view_milestone** - At 100, 1000, 10000 views (→ author)
6. **settings_changed** - When settings updated (→ all superadmins)

#### UI Components Needed:
1. Notification bell icon in admin sidebar
2. Unread count badge
3. Notification dropdown menu (recent 5 notifications)
4. Notification detail page with mark-as-read

### PRIORITY 3: Add Activity Audit Trail

#### New Table:
```sql
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50),
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (admin_id) REFERENCES admins(id),
    INDEX (admin_id, created_at),
    INDEX (entity_type, entity_id)
);
```

#### Events to Log:
- create_article, update_article, delete_article
- update_settings
- create_journalist, update_journalist, delete_journalist
- create_ad, update_ad, delete_ad

### PRIORITY 4: Extended Editor Role Options

Currently settings are superadmin-only. Could optionally add:
- `manage_site_settings` permission for editors
- Or create separate `site_manager` role

---

## 10. Testing Recommendations

### Security Tests to Run:

1. **Test Journalist Article Isolation**:
   - Journalist A creates article → Try to edit as Journalist B (should fail)
   - Try to delete as Journalist B (should fail)

2. **Test Editor Delete Restriction**:
   - Editor creates article
   - Try to delete article created by another editor (should fail if owner-restricted)
   - Try to delete journalist's article (should succeed if editor)

3. **Test Settings Access**:
   - Try to access `admin/settings.php` as journalist (should fail)
   - Try to access as editor (should fail)
   - Access as superadmin (should succeed)

4. **Test Suspended Account**:
   - Change admin role to 'suspended' in database
   - Try to login (should fail with custom message)

---

## 11. Database Schema Notes

### Current Tables Related to Permissions:

```
admins
├── id (PK)
├── username (UNIQUE)
├── email (UNIQUE)
├── password (bcrypt)
├── full_name
├── profile_picture (VARCHAR 500)
├── role ENUM('superadmin','editor','journalist','suspended')
└── created_at

news
├── id (PK)
├── category_id (FK)
├── admin_id (FK) ← Author tracking (not always checked!)
├── title_rw, title_en, title_sw, title_fr
├── description_rw, description_en, description_sw, description_fr
├── thumbnail
├── inline_images (JSON)
├── is_headline (TINYINT)
├── is_featured (TINYINT)
├── status ENUM('published','draft')
├── views
├── created_at
└── updated_at

settings
├── key_name (VARCHAR 100, PK)
└── value (TEXT)
```

### Missing Tables for Complete Permission System:
- `notifications`
- `notification_preferences`
- `activity_logs`

---

## Summary Table: What Exists vs. What's Needed

| Feature | Status | Notes |
|---------|--------|-------|
| Role-based permissions | ✅ Exists | 3 roles, basic action checks |
| Role hierarchy | ✅ Partial | No enforcement of hierarchy ordering |
| Settings access control | ✅ Exists | Superadmin only |
| Article ownership tracking | ✅ Exists | Stored in db, not enforced |
| Article ownership enforcement | ❌ Missing | SECURITY GAP |
| Delete permission guard | ⚠️ Partial | Uses edit_news instead of delete_news |
| Audit trail | ❌ Missing | No logging of who did what |
| Notification system | ❌ Missing | No tables or code |
| Email alerts | ❌ Missing | No email integration |
| In-app notifications UI | ❌ Missing | No notification components |
| Activity logs UI | ❌ Missing | No admin panel for logs |

