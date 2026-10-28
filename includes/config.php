<?php
// ============================================
// KAI NEWS KIGALI — CONFIG & DB CONNECTION
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL user
define('DB_PASS', '');           // Change to your MySQL password
define('DB_NAME', 'kai_news_kigali');

define('SITE_URL', 'http://localhost/kai_news');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL',  SITE_URL . '/uploads/');

// ---- Connect ----
function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset('utf8mb4');
        if ($conn->connect_error) {
            die("DB Error: " . $conn->connect_error);
        }
    }
    return $conn;
}

// ---- Helpers ----
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function canSeeViews(): bool {
    return isset($_SESSION['admin_id']) && in_array($_SESSION['admin_role'] ?? '', ['superadmin','journalist'], true);
}

function newsThumbnail($path) {
    $default = SITE_URL . '/assets/images/default-thumbnail.svg';
    $normalized = preg_replace('#^/?uploads/#', '', $path);
    if ($path && file_exists(UPLOAD_PATH . $normalized)) {
        return UPLOAD_URL . $normalized;
    }
    return $default;
}

function getYouTubeVideoId($url) {
    if (!$url) return false;
    $url = trim($url);
    if (preg_match('#(?:youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|v/|shorts/|live/|watch\?.*v=)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $matches)) {
        return $matches[1];
    }
    return false;
}

function getYouTubeThumbnailUrl($url, $quality = 'hqdefault') {
    $videoId = getYouTubeVideoId($url);
    if (!$videoId) {
        return false;
    }
    return "https://img.youtube.com/vi/{$videoId}/{$quality}.jpg";
}

function getSetting($key) {
    $db = db();
    $stmt = $db->prepare("SELECT value FROM settings WHERE key_name=?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? $result['value'] : '';
}

function getCategories() {
    return db()->query("SELECT * FROM categories ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
}

function profileThumbnail($path) {
    $default = SITE_URL . '/assets/images/default-avatar.svg';
    $normalized = preg_replace('#^/?uploads/#', '', $path);
    if ($path && file_exists(UPLOAD_PATH . $normalized)) {
        return UPLOAD_URL . $normalized;
    }
    return $default;
}

function ensureProfileColumns() {
    $db = db();
    $tables = [
        'admins' => 'profile_picture VARCHAR(500) DEFAULT NULL',
        'journalist_requests' => 'profile_picture VARCHAR(500) DEFAULT NULL',
    ];
    foreach ($tables as $table => $definition) {
        $res = $db->query("SHOW COLUMNS FROM `$table` LIKE 'profile_picture'");
        if ($res && $res->num_rows === 0) {
            $db->query("ALTER TABLE `$table` ADD COLUMN $definition");
        }
    }
}

ensureProfileColumns();

function getLang() {
    $lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'rw';
    return in_array($lang, ['rw','en','sw','fr']) ? $lang : 'rw';
}

function t($row, $field, $lang) {
    $key = $field . '_' . $lang;
    return (!empty($row[$key])) ? $row[$key] : ($row[$field . '_rw'] ?? '');
}

function languageTitleField($alias = 'n') {
    global $LANG;
    return $alias . '.title_' . $LANG;
}

function languageDescriptionField($alias = 'n') {
    global $LANG;
    return $alias . '.description_' . $LANG;
}

function languageContentFilter($alias = 'n') {
    $field = languageTitleField($alias);
    return "COALESCE($field, '') <> ''";
}

session_start();
$LANG = getLang();
if (isset($_GET['lang'])) $_SESSION['lang'] = $LANG;

$LABELS = [
    'rw' => [
        'home' => 'Ahabanza', 'latest' => 'Amakuru Mashya', 'read_more' => 'Soma Byinshi',
        'search' => 'Shakisha', 'share' => 'Sangira', 'advertise' => 'Kwamamaza',
        'contact' => 'Twandikire', 'views' => 'Ibyasomwe', 'by' => 'Na', 'written_by' => 'Byanditswe na',
        'related' => 'Amakuru Afitanye Isano', 'gallery' => 'Amafoto',
        'breaking' => 'IBYIHUTIRWA',
    ],
    'en' => [
        'home' => 'Home', 'latest' => 'Latest News', 'read_more' => 'Read More',
        'search' => 'Search', 'share' => 'Share', 'advertise' => 'Advertise',
        'contact' => 'Contact Us', 'views' => 'Views', 'by' => 'By', 'written_by' => 'Written by',
        'related' => 'Related News', 'gallery' => 'Photo Gallery',
        'breaking' => 'BREAKING',
    ],
    'sw' => [
        'home' => 'Nyumbani', 'latest' => 'Habari za Hivi Karibuni', 'read_more' => 'Soma Zaidi',
        'search' => 'Tafuta', 'share' => 'Shiriki', 'advertise' => 'Tangaza',
        'contact' => 'Wasiliana Nasi', 'views' => 'Maoni', 'by' => 'Na', 'written_by' => 'Kaliandikwa na',
        'related' => 'Habari Zinazohusiana', 'gallery' => 'Picha',
        'breaking' => 'HABARI ZA HARAKA',
    ],
    'fr' => [
        'home' => 'Accueil', 'latest' => 'Dernières nouvelles', 'read_more' => 'Lire la suite',
        'search' => 'Rechercher', 'share' => 'Partager', 'advertise' => 'Publicité',
        'contact' => 'Contactez-nous', 'views' => 'Vues', 'by' => 'Par', 'written_by' => 'Écrit par',
        'related' => 'Actualités connexes', 'gallery' => 'Galerie',
        'breaking' => 'ALERTE',
    ],
];

function L($key) {
    global $LABELS, $LANG;
    return $LABELS[$LANG][$key] ?? $LABELS['rw'][$key] ?? $key;
}

// ============================================
// NOTIFICATION SYSTEM FUNCTIONS
// ============================================
function createNotification($recipient_id, $type, $title, $message, $sender_id = null, $article_id = null) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO notifications (recipient_id, sender_id, type, title, message, related_article_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssii", $recipient_id, $sender_id, $type, $title, $message, $article_id);
    return $stmt->execute();
}

function getUnreadNotifications($admin_id, $limit = 10) {
    $db = db();
    $stmt = $db->prepare("SELECT n.*, a.full_name as sender_name FROM notifications n 
                          LEFT JOIN admins a ON n.sender_id = a.id 
                          WHERE n.recipient_id = ? AND n.is_read = 0 
                          ORDER BY n.created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $admin_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUnreadNotificationCount($admin_id) {
    $db = db();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'];
}

function markNotificationAsRead($notification_id) {
    $db = db();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    return $stmt->execute();
}

function markAllNotificationsAsRead($admin_id) {
    $db = db();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $admin_id);
    return $stmt->execute();
}

// ============================================
// AUDIT LOG / ACTIVITY LOGGING FUNCTIONS
// ============================================
function logActivity($admin_id, $action, $entity_type, $entity_id = null, $old_value = null, $new_value = null, $status = 'success') {
    $db = db();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    
    $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, entity_type, entity_id, old_value, new_value, ip_address, user_agent, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississsss", $admin_id, $action, $entity_type, $entity_id, $old_value, $new_value, $ip_address, $user_agent, $status);
    return $stmt->execute();
}

function getActivityLogs($limit = 50, $admin_id = null, $days = 30) {
    $db = db();
    $where = "WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params = [$days];
    
    if ($admin_id) {
        $where .= " AND admin_id = ?";
        $params[] = $admin_id;
    }
    
    $stmt = $db->prepare("SELECT l.*, a.full_name FROM activity_logs l 
                          JOIN admins a ON l.admin_id = a.id 
                          $where 
                          ORDER BY l.created_at DESC LIMIT ?");
    
    $types = str_repeat('i', count($params)) . 'i';
    $params[] = $limit;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
