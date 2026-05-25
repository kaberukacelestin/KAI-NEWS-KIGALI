<?php
/**
 * Manage Journalist Requests & Accounts
 * Only accessible by superadmin role
 */
require_once 'auth.php';

// Restrict to superadmin only
if ($admin_role !== 'superadmin') {
    header("Location: dashboard.php");
    exit;
}

$msg = '';

// Approve a pending request → create actual admin account
if (isset($_GET['approve'])) {
    $rid = intval($_GET['approve']);
    $req = db()->query("SELECT * FROM journalist_requests WHERE id=$rid AND status='pending'")->fetch_assoc();
    if ($req) {
        $stmt = db()->prepare("INSERT INTO admins (username, email, password, full_name, profile_picture, role) VALUES (?,?,?,?,?,'journalist')");
        $stmt->bind_param("sssss", $req['username'], $req['email'], $req['password'], $req['full_name'], $req['profile_picture']);
        if ($stmt->execute()) {
            db()->query("UPDATE journalist_requests SET status='approved' WHERE id=$rid");
            $msg = "✅ Journalist account approved and created for <strong>" . htmlspecialchars($req['full_name']) . "</strong>.";
        }
    }
}

// Reject request
if (isset($_GET['reject'])) {
    $rid = intval($_GET['reject']);
    db()->query("UPDATE journalist_requests SET status='rejected' WHERE id=$rid");
    $msg = "Request rejected.";
}

// Deactivate / reactivate journalist account
if (isset($_GET['toggle'])) {
    $aid = intval($_GET['toggle']);
    // We use a simple approach: change role between journalist and 'suspended'
    $cur = db()->query("SELECT role FROM admins WHERE id=$aid")->fetch_assoc();
    if ($cur) {
        $new_role = ($cur['role'] === 'journalist') ? 'suspended' : 'journalist';
        db()->query("UPDATE admins SET role='$new_role' WHERE id=$aid");
        $msg = "Account status updated.";
    }
}

// Delete journalist account
if (isset($_GET['delete_account'])) {
    $aid = intval($_GET['delete_account']);
    db()->query("DELETE FROM admins WHERE id=$aid AND role IN ('journalist','suspended')");
    $msg = "Account deleted.";
}

// Add new journalist manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_journalist'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($full_name && $username && $email && $password) {
        // Check if username or email already exists
        $check = db()->query("SELECT id FROM admins WHERE username='$username' OR email='$email'");
        if ($check->num_rows > 0) {
            $msg = "❌ Username or email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = db()->prepare("INSERT INTO admins (username, email, password, full_name, role) VALUES (?,?,?,?,'journalist')");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
            if ($stmt->execute()) {
                $msg = "✅ New journalist <strong>" . htmlspecialchars($full_name) . "</strong> created successfully.";
            }
        }
    } else {
        $msg = "❌ Please fill in all fields.";
    }
}

// Fetch pending requests
$pending = db()->query("SELECT * FROM journalist_requests WHERE status='pending' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$history = db()->query("SELECT * FROM journalist_requests WHERE status != 'pending' ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

// Fetch journalist accounts
$journalists = db()->query("SELECT * FROM admins WHERE role IN ('journalist','suspended') ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

adminHeader("Manage Journalists");
?>
<div class="admin-section">

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

<!-- ADD NEW JOURNALIST MANUALLY -->
<h3 style="margin-top:0"><i class="fa fa-plus-circle" style="color:#3498db"></i> Add New Journalist Manually</h3>
<form method="POST" style="max-width:600px; background:#f9f9f9; padding:20px; border-radius:8px; margin-bottom:30px;">
  <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
    <div>
      <label style="display:block; font-weight:600; margin-bottom:5px;">Full Name *</label>
      <input type="text" name="full_name" required placeholder="Enter full name" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; font-family:inherit;">
    </div>
    <div>
      <label style="display:block; font-weight:600; margin-bottom:5px;">Username *</label>
      <input type="text" name="username" required placeholder="Enter username" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; font-family:inherit;">
    </div>
    <div>
      <label style="display:block; font-weight:600; margin-bottom:5px;">Email *</label>
      <input type="email" name="email" required placeholder="Enter email" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; font-family:inherit;">
    </div>
    <div>
      <label style="display:block; font-weight:600; margin-bottom:5px;">Password *</label>
      <input type="password" name="password" required placeholder="Enter password" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; font-family:inherit;">
    </div>
  </div>
  <button type="submit" name="add_journalist" style="margin-top:15px; padding:10px 20px; background:#3498db; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:600;">
    <i class="fa fa-user-plus"></i> Create Journalist Account
  </button>
</form>

<hr style="margin:30px 0">

<!-- PENDING REQUESTS -->
<h3><i class="fa fa-clock" style="color:#e67e22"></i> Pending Applications (<?= count($pending) ?>)</h3>

<?php if (empty($pending)): ?>
  <p style="color:#999;padding:20px 0">No pending requests.</p>
<?php else: ?>
<table class="admin-table">
  <thead><tr><th>Photo</th><th>Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Applied</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($pending as $r): ?>
  <tr>
    <td><img src="<?= profileThumbnail($r['profile_picture']) ?>" class="table-thumb" alt="<?= e($r['full_name']) ?>"></td>
    <td><?= e($r['full_name']) ?></td>
    <td><code><?= e($r['username']) ?></code></td>
    <td><?= e($r['email']) ?></td>
    <td><?= e($r['phone'] ?: '—') ?></td>
    <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
    <td>
      <a href="?approve=<?= $r['id'] ?>" class="btn-sm edit" onclick="return confirm('Approve and create account for <?= e($r['full_name']) ?>?')"><i class="fa fa-check"></i> Approve</a>
      <a href="?reject=<?= $r['id'] ?>"  class="btn-sm del"  onclick="return confirm('Reject this application?')"><i class="fa fa-times"></i> Reject</a>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<hr style="margin:30px 0">

<!-- ACTIVE JOURNALIST ACCOUNTS -->
<h3><i class="fa fa-users" style="color:#27ae60"></i> Journalist Accounts (<?= count($journalists) ?>)</h3>

<?php if (empty($journalists)): ?>
  <p style="color:#999;padding:20px 0">No journalist accounts yet.</p>
<?php else: ?>
<table class="admin-table">
  <thead><tr><th>Photo</th><th>Name</th><th>Username</th><th>Email</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($journalists as $j): ?>
  <tr>
    <td><img src="<?= profileThumbnail($j['profile_picture']) ?>" class="table-thumb" alt="<?= e($j['full_name']) ?>"></td>
    <td><?= e($j['full_name']) ?></td>
    <td><code><?= e($j['username']) ?></code></td>
    <td><?= e($j['email']) ?></td>
    <td><span class="badge <?= $j['role']==='journalist'?'published':'draft' ?>">
      <?= $j['role']==='journalist' ? '✅ Active' : '⛔ Suspended' ?>
    </span></td>
    <td><?= date('d/m/Y', strtotime($j['created_at'])) ?></td>
    <td>
      <a href="?toggle=<?= $j['id'] ?>" class="btn-sm edit">
        <i class="fa fa-<?= $j['role']==='journalist'?'ban':'check' ?>"></i>
        <?= $j['role']==='journalist'?'Suspend':'Reactivate' ?>
      </a>
      <a href="?delete_account=<?= $j['id'] ?>" class="btn-sm del" onclick="return confirm('Delete this account permanently?')"><i class="fa fa-trash"></i></a>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<hr style="margin:30px 0">

<!-- REQUEST HISTORY -->
<h3><i class="fa fa-history"></i> Recent Request History</h3>
<table class="admin-table">
  <thead><tr><th>Name</th><th>Username</th><th>Status</th><th>Date</th></tr></thead>
  <tbody>
  <?php foreach ($history as $h): ?>
  <tr>
    <td><?= e($h['full_name']) ?></td>
    <td><code><?= e($h['username']) ?></code></td>
    <td><span class="badge <?= $h['status']==='approved'?'published':'draft' ?>"><?= $h['status'] ?></span></td>
    <td><?= date('d/m/Y', strtotime($h['created_at'])) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

</div>
<?php adminFooter(); ?>
