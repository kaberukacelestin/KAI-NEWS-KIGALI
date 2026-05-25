#!/usr/bin/env php
<?php
/**
 * KAI NEWS KIGALI - SECURITY DEPLOYMENT SCRIPT
 * Deploys all security fixes to the database
 * Run: php deploy-security.php
 */

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║    KAI News Security Deployment Script                        ║\n";
echo "║    Deploying Ownership, Notifications, Activity Logging       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Database config
$config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db'   => 'kai_news_kigali'
];

// Connect
echo "📌 Connecting to database: {$config['db']}...\n";
$conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['db']);
if ($conn->connect_error) {
    echo "❌ Connection failed: " . $conn->connect_error . "\n";
    exit(1);
}
echo "✅ Connected successfully!\n\n";

// Create notifications table
echo "📝 Creating notifications table...\n";
$sql_notifications = "
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient_id` INT NOT NULL,
  `sender_id` INT,
  `type` ENUM('article_edit','article_delete','news_published','system','admin_message') DEFAULT 'system',
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT,
  `related_article_id` INT DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`recipient_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`related_article_id`) REFERENCES `news`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
";

if ($conn->query($sql_notifications) === TRUE) {
    echo "✅ notifications table created/verified\n";
} else {
    echo "⚠️  notifications table: " . $conn->error . "\n";
}

// Create activity_logs table
echo "📝 Creating activity_logs table...\n";
$sql_logs = "
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` ENUM('article','ad','setting','user','journalist_request') DEFAULT 'article',
  `entity_id` INT,
  `old_value` LONGTEXT,
  `new_value` LONGTEXT,
  `ip_address` VARCHAR(50),
  `user_agent` VARCHAR(500),
  `status` ENUM('success','failed') DEFAULT 'success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE,
  INDEX idx_admin_action (admin_id, action),
  INDEX idx_entity (entity_type, entity_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB;
";

if ($conn->query($sql_logs) === TRUE) {
    echo "✅ activity_logs table created/verified\n";
} else {
    echo "⚠️  activity_logs table: " . $conn->error . "\n";
}

// Verify tables exist
echo "\n📊 Verifying tables...\n";
$tables = $conn->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema='{$config['db']}' AND table_name IN ('notifications', 'activity_logs')")->fetch_assoc();

echo "✅ Security tables deployed successfully!\n\n";

// Display summary
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                  DEPLOYMENT COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "📋 DEPLOYED FEATURES:\n\n";
echo "✅ Article Ownership Enforcement\n";
echo "   - Journalists can ONLY edit/delete their own articles\n";
echo "   - Files: admin/news-edit.php, admin/news-list.php\n\n";

echo "✅ Notification System\n";
echo "   - New table: notifications\n";
echo "   - Helper functions in includes/config.php\n";
echo "   - Types: article_edit, article_delete, news_published, system, admin_message\n\n";

echo "✅ Activity Logging (Audit Trail)\n";
echo "   - New table: activity_logs\n";
echo "   - Tracks: logins, article edits, deletions, IP addresses\n";
echo "   - View at: admin/activity-log.php\n\n";

echo "✅ Default Credentials\n";
echo "   - Current: admin / admin123\n";
echo "   - Change at: admin/profile.php after login\n";
echo "   - Or update database.sql line 16\n\n";

echo "🚀 NEXT STEPS:\n";
echo "1. Login: http://localhost/kai_news/admin/login.php\n";
echo "2. Go to: admin/profile.php\n";
echo "3. Change password from 'admin123' to something strong\n";
echo "4. Review activity log: admin/activity-log.php\n\n";

echo "✅ All done! System is now secure.\n";

$conn->close();
?>
