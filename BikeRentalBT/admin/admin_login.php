<?php
include '../config.php';

if(isset($_SESSION['admin'])){
  header('Location: admin_dashboard.php');
  exit;
}

$login_error = '';
if(isset($_POST['login'])){
  $username = trim($_POST['username']);
  $password = $_POST['password'];
  $login_error = 'Invalid username or password.';

  $stmt = mysqli_prepare($conn, "SELECT id, password FROM admin WHERE username = ?");
  mysqli_stmt_bind_param($stmt, 's', $username);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $aid, $hash);
  if(mysqli_stmt_fetch($stmt)){
    $ok = false;
    if(password_verify($password, $hash)){
      $ok = true;
    } elseif(strlen($hash) === 32 && md5($password) === $hash){
      $ok = true; // legacy md5 admin
    }
    if($ok){
      session_regenerate_id(true);
      $_SESSION['admin'] = $username;
      header('Location: admin_dashboard.php');
      exit;
    }
  }
  mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-bg">

<div class="auth-wrap">
  <div class="auth-card">
    <div class="site-logo" aria-hidden="true"></div>
    <h2>Admin Sign In</h2>
    <p class="lead">Manage bikes and bookings</p>
    <?php if($login_error): ?><div class="error"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-row"><input class="field" name="username" placeholder="Username"></div>
      <div class="form-row"><input class="field" type="password" name="password" placeholder="Password"></div>
      <div class="auth-actions"><button class="btn" name="login">Sign in</button></div>
    </form>
  </div>
</div>

</body>
</html>
