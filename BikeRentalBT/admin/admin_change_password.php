<?php
include '../config.php';

if(!isset($_SESSION['admin'])){
  header('Location: admin_login.php');
  exit;
}

$error = '';
$success = '';

if(isset($_POST['change'])){
  $admin = $_SESSION['admin'];
  $current = $_POST['current_password'];
  $new_pwd = $_POST['new_password'];
  $confirm = $_POST['confirm_password'];

  if($current === '' || $new_pwd === '' || $confirm === ''){
    $error = 'Please fill all fields.';
  } elseif(strlen($new_pwd) < 6){
    $error = 'New password must be at least 6 characters.';
  } elseif($new_pwd !== $confirm){
    $error = 'New passwords do not match.';
  } else {
    // Get current password hash
    $stmt = mysqli_prepare($conn, "SELECT password FROM admin WHERE username = ?");
    mysqli_stmt_bind_param($stmt, 's', $admin);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $hash);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Verify current password (support both legacy MD5 and new password_hash)
    $pwd_ok = false;
    if(password_verify($current, $hash)){
      $pwd_ok = true;
    } elseif(strlen($hash) === 32 && md5($current) === $hash){
      $pwd_ok = true; // legacy md5 admin
    }

    if(!$pwd_ok){
      $error = 'Current password is incorrect.';
    } else {
      // Update to new password with password_hash (SECURE)
      $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
      $stmt = mysqli_prepare($conn, "UPDATE admin SET password = ? WHERE username = ?");
      mysqli_stmt_bind_param($stmt, 'ss', $new_hash, $admin);
      if(mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        $success = 'Password changed successfully!';
      } else {
        $error = 'Failed to update password.';
      }
    }
  }
}

$admin = htmlspecialchars($_SESSION['admin']);
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .change-pwd-form { max-width: 450px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
    .form-row { margin: 15px 0; }
    .form-row label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; }
    .form-row input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
    .form-row input:focus { outline: none; border-color: #007bff; box-shadow: 0 0 0 3px rgba(0,123,255,.1); }
    .error { color: #b00020; margin: 12px 0; }
    .success { color: #22863a; margin: 12px 0; }
    .form-actions { margin-top: 20px; }
    .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .btn:hover { background: #0056b3; }
    .back-link { display: block; margin-top: 15px; text-align: center; }
    .back-link a { color: #007bff; text-decoration: none; }
    .back-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<div class="change-pwd-form">
  <h2>Change Admin Password</h2>
  <p style="color: #666; margin-bottom: 20px;">Logged in as <strong><?= $admin ?></strong></p>
  
  <?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST">
    <div class="form-row">
      <label for="current">Current Password</label>
      <input id="current" type="password" name="current_password" required>
    </div>
    
    <div class="form-row">
      <label for="new">New Password</label>
      <input id="new" type="password" name="new_password" placeholder="Min 6 characters" required>
    </div>
    
    <div class="form-row">
      <label for="confirm">Confirm New Password</label>
      <input id="confirm" type="password" name="confirm_password" required>
    </div>
    
    <div class="form-actions">
      <button class="btn" name="change">Change Password</button>
    </div>
  </form>

  <div class="back-link">
    <a href="admin_dashboard.php">← Back to Dashboard</a>
  </div>
</div>

</body>
</html>
