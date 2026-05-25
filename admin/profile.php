<?php
require_once 'auth.php';

$success = $error = '';
$admin_id = intval($_SESSION['admin_id'] ?? 0);

// Fetch current admin profile
$stmt = db()->prepare("SELECT id, username, email, full_name, role, profile_picture FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    $error = "Admin profile not found";
    exit;
}

// Handle profile updates (credentials)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_credentials'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($full_name) || empty($email) || empty($username)) {
        $error = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if username is unique (excluding current admin)
        $checkStmt = db()->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $checkStmt->bind_param("si", $username, $admin_id);
        $checkStmt->execute();
        $check = $checkStmt->get_result();
        if ($check->num_rows > 0) {
            $error = "Username already exists";
        } else {
            // Update with or without password
            if (!empty($password)) {
                $pwd_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = db()->prepare("UPDATE admins SET full_name = ?, email = ?, username = ?, password = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $full_name, $email, $username, $pwd_hash, $admin_id);
            } else {
                $stmt = db()->prepare("UPDATE admins SET full_name = ?, email = ?, username = ? WHERE id = ?");
                $stmt->bind_param("sssi", $full_name, $email, $username, $admin_id);
            }
            
            if ($stmt->execute()) {
                $success = "Credentials updated successfully!";
                $admin['full_name'] = $full_name;
                $admin['email'] = $email;
                $admin['username'] = $username;
            } else {
                $error = "Database error: " . $stmt->error;
            }
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    
    if ($file['error'] === 0) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($file['type'], $allowed) && $file['size'] <= 5242880) { // 5MB max
            
            // Create uploads/profiles directory if not exists
            if (!is_dir(UPLOAD_PATH . 'profiles')) {
                mkdir(UPLOAD_PATH . 'profiles', 0755, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $admin_id . '_' . time() . '.' . $ext;
            $filepath = UPLOAD_PATH . 'profiles/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old profile picture if exists
                if (!empty($admin['profile_picture'])) {
                    $oldPath = preg_replace('#^/?uploads/#', '', $admin['profile_picture']);
                    if (file_exists(UPLOAD_PATH . $oldPath)) {
                        unlink(UPLOAD_PATH . $oldPath);
                    }
                }
                
                // Update database
                $rel_path = 'profiles/' . $filename;
                $stmt = db()->prepare("UPDATE admins SET profile_picture = ? WHERE id = ?");
                $stmt->bind_param("si", $rel_path, $admin_id);
                
                if ($stmt->execute()) {
                    $success = "Profile picture updated successfully!";
                    $admin['profile_picture'] = $rel_path;
                } else {
                    $error = "Database error: " . $stmt->error;
                }
            } else {
                $error = "Failed to upload file";
            }
        } else {
            $error = "Invalid file type or size (max 5MB). Allowed: JPG, PNG, GIF, WebP";
        }
    } else {
        $error = "File upload error";
    }
}

adminHeader("My Profile");
?>

<div class="admin-form-page">
  <?php if ($success): ?><div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><i class="fa fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>

  <h2 style="margin-bottom: 30px;">My Profile</h2>

  <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 40px; max-width: 1000px;">
    
    <!-- Profile Picture Section -->
    <div>
      <h3 style="color: var(--navy); margin-bottom: 20px; font-size: 1rem;">Profile Picture</h3>
      
      <div style="position: relative; border-radius: 8px; overflow: hidden; background: var(--light2); aspect-ratio: 1; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
        <img src="<?= profileThumbnail($admin['profile_picture']) ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
      </div>

      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label for="profile_image">Upload New Picture</label>
          <input type="file" id="profile_image" name="profile_image" accept="image/*" required>
          <small style="color: var(--muted); display: block; margin-top: 8px;">Max 5MB • JPG, PNG, GIF, WebP</small>
          <div style="background: rgba(200,16,46,0.08); border-left: 3px solid var(--red); padding: 10px 12px; margin-top: 12px; border-radius: 4px; font-size: 12px; color: var(--text);">
            <strong style="color: var(--red);">📸 Your Profile Photo as Author Credit</strong><br>
            This photo will appear on all your articles as your author profile picture. Make sure it's a clear, professional headshot!
          </div>
        </div>
        <button type="submit" class="btn-publish" style="width: 100%;">
          <i class="fa fa-upload"></i> Upload Picture
        </button>
      </form>
    </div>

    <!-- Credentials Section -->
    <div>
      <h3 style="color: var(--navy); margin-bottom: 20px; font-size: 1rem;">Account Information</h3>
      
      <form method="POST">
        <input type="hidden" name="update_credentials" value="1">
        
        <div class="form-group">
          <label for="full_name">Full Name</label>
          <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($admin['full_name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required>
        </div>

        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
        </div>

        <div class="form-group">
          <label for="role">Role</label>
          <input type="text" value="<?= ucfirst($admin['role']) ?>" disabled style="background: var(--light2); cursor: not-allowed;">
        </div>

        <div class="form-group">
          <label for="password">New Password <small style="color: var(--muted);">(leave blank to keep current)</small></label>
          <input type="password" id="password" name="password" placeholder="Enter new password (optional)">
        </div>

        <button type="submit" class="btn-publish" style="width: 100%;">
          <i class="fa fa-save"></i> Update Profile
        </button>
      </form>
    </div>
  </div>

</div>

<?php adminFooter(); ?>
