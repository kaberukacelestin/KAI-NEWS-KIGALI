<?php
/**
 * PUBLIC Journalist Registration Page
 * Anyone can apply; a superadmin must approve from manage-journalists.php
 */
require_once '../includes/config.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirm']        ?? '';
    $profile_picture = null;

    if (!empty($_FILES['profile_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (in_array($ext, $allowed)) {
            if (!is_dir(UPLOAD_PATH . 'profiles')) mkdir(UPLOAD_PATH . 'profiles', 0755, true);
            $fname = 'profile_' . time() . '_' . rand(100,999) . '.' . $ext;
            $fpath = UPLOAD_PATH . 'profiles/' . $fname;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $fpath)) {
                $profile_picture = 'profiles/' . $fname;
            }
        }
    }

    if (!$full_name || !$username || !$email || !$password) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        $error = "Username: 3–30 characters, lowercase letters, numbers, underscores only.";
    } else {
        // Check uniqueness
        $chk = db()->prepare("SELECT id FROM journalist_requests WHERE username=? OR email=?");
        $chk->bind_param("ss", $username, $email);
        $chk->execute();
        $dup = $chk->get_result()->fetch_assoc();

        $chk2 = db()->prepare("SELECT id FROM admins WHERE username=? OR email=?");
        $chk2->bind_param("ss", $username, $email);
        $chk2->execute();
        $dup2 = $chk2->get_result()->fetch_assoc();

        if ($dup || $dup2) {
            $error = "Username or email is already taken.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare("INSERT INTO journalist_requests (full_name, username, email, password, phone, profile_picture) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssss", $full_name, $username, $email, $hashed, $phone, $profile_picture);
            if ($stmt->execute()) {
                $success = "Your application has been submitted! A superadmin will review and activate your account.";
            } else {
                $error = "Database error. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Journalist Registration — KAI News Kigali</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { background: #f0f2f5; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
    .reg-card { background:#fff; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.1); width:100%; max-width:520px; padding:40px; }
    .reg-card .brand { text-align:center; margin-bottom:28px; }
    .reg-card .brand img { height:52px; margin-bottom:8px; }
    .reg-card .brand h2 { font-size:1.4rem; color:#1a1a2e; margin:0; }
    .reg-card .brand p { color:#666; font-size:.9rem; margin-top:4px; }
    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-weight:600; font-size:.85rem; color:#333; margin-bottom:6px; }
    .form-group input { width:100%; padding:10px 14px; border:1.5px solid #ddd; border-radius:8px; font-size:.95rem; box-sizing:border-box; transition:border .2s; }
    .form-group input:focus { border-color:#c0392b; outline:none; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .alert { padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:.9rem; }
    .alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .alert-error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
    .btn-submit { width:100%; padding:13px; background:#c0392b; color:#fff; border:none; border-radius:8px; font-size:1rem; font-weight:700; cursor:pointer; transition:background .2s; margin-top:8px; }
    .btn-submit:hover { background:#a93226; }
    .login-link { text-align:center; margin-top:16px; font-size:.85rem; color:#666; }
    .login-link a { color:#c0392b; text-decoration:none; font-weight:600; }
    .role-badge-info { background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:10px 14px; margin-bottom:20px; font-size:.85rem; color:#856404; }
    .role-badge-info i { margin-right:6px; }
  </style>
</head>
<body>
<div class="reg-card">
  <div class="brand">
    <img src="../assets/images/logo.png" alt="KAI News Kigali">
    <h2>Journalist Registration</h2>
    <p>Apply to join KAI News Kigali editorial team</p>
  </div>

  <div class="role-badge-info">
    <i class="fa fa-info-circle"></i>
    <strong>Journalist accounts</strong> can <strong>add, edit, and update news</strong> only.
    Ads, settings, and user management are admin-only.
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="POST" enctype="multipart/form-data">
    <div class="form-group">
      <label><i class="fa fa-user"></i> Full Name</label>
      <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name']??'') ?>" placeholder="Your full name" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><i class="fa fa-at"></i> Username</label>
        <input type="text" name="username" value="<?= htmlspecialchars($_POST['username']??'') ?>" placeholder="e.g. john_rw" required>
      </div>
      <div class="form-group">
        <label><i class="fa fa-phone"></i> Phone (optional)</label>
        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone']??'') ?>" placeholder="+250 7xx xxx xxx">
      </div>
    </div>
    <div class="form-group">
      <label><i class="fa fa-image"></i> Profile Picture (optional)</label>
      <input type="file" name="profile_image" accept="image/*">
    </div>
    <div class="form-group">
      <label><i class="fa fa-envelope"></i> Email Address</label>
      <input type="email" name="email" value="<?= htmlspecialchars($_POST['email']??'') ?>" placeholder="your@email.com" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><i class="fa fa-lock"></i> Password</label>
        <input type="password" name="password" placeholder="Min. 6 characters" required>
      </div>
      <div class="form-group">
        <label><i class="fa fa-lock"></i> Confirm Password</label>
        <input type="password" name="confirm" placeholder="Repeat password" required>
      </div>
    </div>
    <button type="submit" class="btn-submit"><i class="fa fa-paper-plane"></i> Submit Application</button>
  </form>
  <?php endif; ?>

  <p class="login-link"><a href="login.php"><i class="fa fa-arrow-left"></i> Back to Login</a></p>
</div>
</body>
</html>
