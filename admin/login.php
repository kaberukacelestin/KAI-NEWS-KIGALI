<?php
require_once '../includes/config.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare("SELECT * FROM admins WHERE username=? OR email=?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_role'] = $admin['role'];
        
        // Log successful login for audit trail
        logActivity($admin['id'], 'login', 'user', $admin['id'], null, null, 'success');
        
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Amazina cyangwa ijambo banga si yo. / Invalid credentials.";
        
        // Log failed login attempt for security
        if ($admin) {
            logActivity($admin['id'], 'login_failed', 'user', $admin['id'], null, null, 'failed');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="rw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — KAI News Kigali</title>
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Source Sans 3', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0d1f3c;
    position: relative;
    overflow: hidden;
  }

  /* Animated background circles */
  body::before, body::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    opacity: 0.07;
  }
  body::before {
    width: 600px; height: 600px;
    background: #c8102e;
    top: -200px; right: -150px;
    animation: float 8s ease-in-out infinite;
  }
  body::after {
    width: 400px; height: 400px;
    background: #1a4080;
    bottom: -120px; left: -100px;
    animation: float 10s ease-in-out infinite reverse;
  }
  @keyframes float {
    0%,100% { transform: translateY(0) scale(1); }
    50%      { transform: translateY(-30px) scale(1.05); }
  }

  .page-wrap {
    display: flex;
    width: 900px;
    max-width: 96vw;
    min-height: 520px;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 40px 80px rgba(0,0,0,0.5);
    position: relative;
    z-index: 1;
  }

  /* LEFT PANEL — brand */
  .brand-panel {
    flex: 1;
    background: linear-gradient(145deg, #162d54 0%, #0d1f3c 60%, #1a0a14 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 50px 40px;
    position: relative;
    overflow: hidden;
  }
  .brand-panel::before {
    content: '';
    position: absolute;
    width: 300px; height: 300px;
    background: rgba(200,16,46,0.12);
    border-radius: 50%;
    bottom: -80px; right: -80px;
  }
  .brand-panel::after {
    content: '';
    position: absolute;
    width: 180px; height: 180px;
    background: rgba(26,64,128,0.2);
    border-radius: 50%;
    top: -40px; left: -40px;
  }
  .brand-logo {
    width: 180px;
    margin-bottom: 30px;
    position: relative;
    z-index: 1;
    filter: drop-shadow(0 8px 24px rgba(0,0,0,0.4));
  }
  .brand-tagline {
    color: rgba(255,255,255,0.9);
    font-size: 1.1rem;
    font-weight: 700;
    text-align: center;
    line-height: 1.5;
    position: relative;
    z-index: 1;
    margin-bottom: 10px;
  }
  .brand-sub {
    color: rgba(255,255,255,0.45);
    font-size: 0.82rem;
    text-align: center;
    position: relative;
    z-index: 1;
    letter-spacing: 1px;
    text-transform: uppercase;
  }
  .brand-divider {
    width: 40px; height: 3px;
    background: #c8102e;
    border-radius: 2px;
    margin: 18px auto;
    position: relative;
    z-index: 1;
  }

  /* RIGHT PANEL — form */
  .form-panel {
    width: 400px;
    background: #ffffff;
    padding: 50px 44px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .form-panel h2 {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0d1f3c;
    margin-bottom: 6px;
  }
  .form-panel p.subtitle {
    font-size: 13px;
    color: #9ca3af;
    margin-bottom: 32px;
  }
  .form-panel p.subtitle span { color: #c8102e; font-weight: 700; }

  .alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #c8102e;
    padding: 11px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .form-group { margin-bottom: 22px; }
  .form-group label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    margin-bottom: 7px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
  }

  .input-wrap {
    position: relative;
    display: flex;
    align-items: center;
  }
  .input-wrap .icon {
    position: absolute;
    left: 14px;
    color: #9ca3af;
    font-size: 14px;
    pointer-events: none;
  }
  .input-wrap input {
    width: 100%;
    padding: 13px 14px 13px 42px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-family: 'Source Sans 3', sans-serif;
    font-size: 14px;
    color: #111827;
    background: #f9fafb;
    outline: none;
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
  }
  .input-wrap input:focus {
    border-color: #c8102e;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(200,16,46,0.10);
  }
  .input-wrap .toggle-pass {
    position: absolute;
    right: 14px;
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    font-size: 14px;
    padding: 4px;
    transition: color 0.2s;
  }
  .input-wrap .toggle-pass:hover { color: #c8102e; }

  .btn-login {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #c8102e 0%, #a00d25 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-family: 'Source Sans 3', sans-serif;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.2s;
    letter-spacing: 0.3px;
    margin-top: 8px;
    box-shadow: 0 4px 16px rgba(200,16,46,0.35);
  }
  .btn-login:hover {
    background: linear-gradient(135deg, #0d1f3c 0%, #162d54 100%);
    box-shadow: 0 4px 16px rgba(13,31,60,0.35);
    transform: translateY(-1px);
  }
  .btn-login:active { transform: translateY(0); }

  .form-footer {
    margin-top: 24px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
  }
  .back-link {
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s;
  }
  .back-link:hover { color: #0d1f3c; }
  .back-link i { font-size: 11px; }

  /* Responsive */
  @media (max-width: 700px) {
    .brand-panel { display: none; }
    .form-panel { width: 100%; padding: 40px 28px; border-radius: 20px; }
    .page-wrap { border-radius: 20px; }
  }
</style>
</head>
<body>
<div class="page-wrap">

  <!-- LEFT: Brand Panel -->
  <div class="brand-panel">
    <img src="../assets/images/logo.png" alt="KAI News Kigali" class="brand-logo">
    <div class="brand-divider"></div>
    <p class="brand-tagline">Amakuru Ahuza Kigali<br>n'Isi Yose</p>
    <p class="brand-sub">News Connecting Kigali &amp; the World</p>
  </div>

  <!-- RIGHT: Login Form -->
  <div class="form-panel">
    <h2>Admin Login</h2>
    <p class="subtitle">Welcome back — <span>KAI News Kigali</span></p>

    <?php if ($error): ?>
    <div class="alert-error">
      <i class="fa fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username / Email</label>
        <div class="input-wrap">
          <i class="fa fa-user icon"></i>
          <input type="text" name="username" required autocomplete="username"
                 placeholder="Enter username or email"
                 value="<?= e($_POST['username'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Password / Ijambo banga</label>
        <div class="input-wrap">
          <i class="fa fa-lock icon"></i>
          <input type="password" name="password" id="passField" required
                 autocomplete="current-password" placeholder="Enter password">
          <button type="button" class="toggle-pass" onclick="togglePass()" title="Show/hide password">
            <i class="fa fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="fa fa-sign-in-alt"></i> Injira / Login
      </button>
    </form>

    <div class="form-footer">
      <a href="../index.php" class="back-link">
        <i class="fa fa-arrow-left"></i> Garuka ku rubuga / Back to site
      </a>
    </div>
  </div>

</div>

<script>
function togglePass() {
  const f = document.getElementById('passField');
  const i = document.getElementById('eyeIcon');
  if (f.type === 'password') { f.type = 'text'; i.className = 'fa fa-eye-slash'; }
  else { f.type = 'password'; i.className = 'fa fa-eye'; }
}
</script>
</body>
</html>
