<?php
require_once 'auth.php';
requirePermission('manage_journalists'); // Only superadmins/editors can view logs

$filter_admin = intval($_GET['admin_id'] ?? 0);
$filter_days = intval($_GET['days'] ?? 30);
$page = max(1, intval($_GET['p'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get all admins for filter dropdown
$admins = db()->query("SELECT id, full_name, role FROM admins ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// Get activity logs
$where = "WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL $filter_days DAY)";
if ($filter_admin) {
    $filter_admin = intval($filter_admin);
    $where .= " AND l.admin_id = $filter_admin";
}

$total = db()->query("SELECT COUNT(*) as n FROM activity_logs l $where")->fetch_assoc()['n'];
$pages = ceil($total / $per_page);

$logs = db()->query("SELECT l.*, a.full_name, a.role 
    FROM activity_logs l 
    JOIN admins a ON l.admin_id = a.id 
    $where 
    ORDER BY l.created_at DESC 
    LIMIT $per_page OFFSET $offset")->fetch_all(MYSQLI_ASSOC);

adminHeader("Activity Log / Ibigezweho bya Gahunda");
?>
<div class="admin-section">
  <h2>🔍 Activity & Audit Trail</h2>
  
  <!-- Filters -->
  <form method="GET" class="filter-form" style="margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap;">
    <div>
      <label>Admin User:</label>
      <select name="admin_id" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        <option value="">-- All Users --</option>
        <?php foreach ($admins as $adm): ?>
        <option value="<?= $adm['id'] ?>" <?= $filter_admin === $adm['id'] ? 'selected' : '' ?>>
          <?= e($adm['full_name']) ?> (<?= $adm['role'] ?>)
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div>
      <label>Period:</label>
      <select name="days" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        <option value="1" <?= $filter_days === 1 ? 'selected' : '' ?>>Last 24 Hours</option>
        <option value="7" <?= $filter_days === 7 ? 'selected' : '' ?>>Last 7 Days</option>
        <option value="30" <?= $filter_days === 30 ? 'selected' : '' ?>>Last 30 Days</option>
        <option value="90" <?= $filter_days === 90 ? 'selected' : '' ?>>Last 90 Days</option>
      </select>
    </div>
    
    <button type="submit" class="btn-sm edit">🔍 Filter</button>
    <a href="activity-log.php" class="btn-sm" style="padding: 8px 15px; background: #95a5a6;">Reset</a>
  </form>

  <!-- Stats -->
  <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; display: flex; gap: 30px;">
    <div>
      <strong>Total Logs:</strong> <?= number_format($total) ?>
    </div>
    <div>
      <strong>Period:</strong> Last <?= $filter_days ?> day(s)
    </div>
    <div>
      <strong>User:</strong> <?= $filter_admin ? (db()->query("SELECT full_name FROM admins WHERE id=$filter_admin")->fetch_assoc()['full_name']) : 'All' ?>
    </div>
  </div>

  <!-- Activity Log Table -->
  <table class="admin-table" style="width: 100%; border-collapse: collapse;">
    <thead>
      <tr style="background: #34495e; color: white;">
        <th>Date/Time</th>
        <th>User</th>
        <th>Action</th>
        <th>Entity Type</th>
        <th>Entity ID</th>
        <th>IP Address</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
      <tr>
        <td colspan="7" style="text-align: center; padding: 20px; color: #7f8c8d;">
          No activity logs found for the selected filters.
        </td>
      </tr>
      <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <tr style="border-bottom: 1px solid #ddd;">
          <td style="font-size: 12px;">
            <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
          </td>
          <td>
            <strong><?= e($log['full_name']) ?></strong>
            <br><span style="font-size: 11px; color: #7f8c8d;"><?= ucfirst($log['role']) ?></span>
          </td>
          <td style="font-weight: 500; color: #2980b9;">
            <?= str_replace('_', ' ', strtoupper($log['action'])) ?>
          </td>
          <td style="text-transform: capitalize; font-size: 13px;">
            <?= $log['entity_type'] ?>
          </td>
          <td style="text-align: center;">
            <?= $log['entity_id'] ?: '-' ?>
          </td>
          <td style="font-size: 12px; color: #7f8c8d;">
            <?= e($log['ip_address']) ?>
          </td>
          <td style="font-weight: 500;">
            <span style="padding: 3px 8px; border-radius: 3px; font-size: 11px;
              <?= $log['status'] === 'success' ? 'background: #d5f4e6; color: #27ae60;' : 'background: #fadbd8; color: #c0392b;' ?>">
              <?= strtoupper($log['status']) ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="pagination" style="margin-top: 20px; text-align: center;">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="activity-log.php?p=<?= $i ?>&admin_id=<?= $filter_admin ?>&days=<?= $filter_days ?>" 
         class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <!-- Export Button -->
  <div style="margin-top: 20px;">
    <a href="activity-log-export.php?admin_id=<?= $filter_admin ?>&days=<?= $filter_days ?>" 
       class="btn-sm edit" style="text-decoration: none; display: inline-block; padding: 8px 15px; background: #16a085; color: white; border-radius: 4px;">
      📥 Export as CSV
    </a>
  </div>

  <!-- Info Box -->
  <div style="margin-top: 30px; padding: 15px; background: #ecf0f1; border-left: 4px solid #3498db; border-radius: 4px; font-size: 13px;">
    <strong>ℹ️ Activity Log Information:</strong>
    <ul style="margin: 10px 0 0 20px;">
      <li><strong>Action Types:</strong> login, login_failed, create_article, edit_article, delete_article, create_ad, edit_ad, delete_ad, update_settings, manage_journalist</li>
      <li><strong>Entity Types:</strong> user, article, ad, setting, journalist_request</li>
      <li><strong>Status:</strong> Shows whether action succeeded or failed</li>
      <li><strong>IP Address:</strong> Useful for detecting unauthorized access attempts</li>
      <li><strong>All times:</strong> Server timezone (UTC or configured)</li>
    </ul>
  </div>
</div>

<?php
// Simple CSS for the table if not already included
?>
<style>
  .admin-table {
    width: 100%;
    border-collapse: collapse;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 4px;
    overflow: hidden;
  }
  
  .admin-table th {
    background: #34495e;
    color: white;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #2c3e50;
  }
  
  .admin-table td {
    padding: 12px;
    border-bottom: 1px solid #ecf0f1;
  }
  
  .admin-table tbody tr:hover {
    background: #f8f9fa;
  }
  
  .page-btn {
    display: inline-block;
    padding: 6px 10px;
    margin: 0 2px;
    border: 1px solid #bdc3c7;
    border-radius: 3px;
    text-decoration: none;
    color: #2c3e50;
    cursor: pointer;
    transition: all 0.2s;
  }
  
  .page-btn:hover {
    background: #ecf0f1;
  }
  
  .page-btn.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
  }
</style>

<?php adminFooter(); ?>
