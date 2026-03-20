<?php
include 'config.php';

$login_error = '';
if(isset($_POST['login'])){
  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';

  if($email === '' || $password === ''){
    $login_error = 'Please enter email and password.';
  } else {
    $stmt = mysqli_prepare($conn, "SELECT id, password FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $uid, $hash);
    if(mysqli_stmt_fetch($stmt)){
      // prefer password_verify; support legacy md5 hashes by fallback
      $ok = false;
      if(password_verify($password, $hash)){
        $ok = true;
      } elseif(strlen($hash) === 32 && md5($password) === $hash){
        // rehash to stronger algorithm
        $new = password_hash($password, PASSWORD_DEFAULT);
        $u2 = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($u2, 'si', $new, $uid);
        mysqli_stmt_execute($u2);
        mysqli_stmt_close($u2);
        $ok = true;
      }

      if($ok){
        session_regenerate_id(true);
        $_SESSION['user'] = $email;
        $redirect = "dashboard.php";
        if(isset($_GET['bike'])){
          $redirect = "book_bike.php?bike=" . (int)$_GET['bike'];
        }
        header("Location: " . $redirect);
        exit;
      } else {
        $login_error = 'Invalid credentials.';
      }
    } else {
      $login_error = 'Invalid credentials.';
    }
    mysqli_stmt_close($stmt);
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>.inline-note{color:#6b7785;font-size:0.95rem;margin-top:10px}</style>
</head>
<body class="auth-bg">

  <div class="auth-wrap">
    <div class="auth-card">
      <div class="site-logo" aria-hidden="true"></div>
      <h2>Welcome back</h2>
      <p class="lead">Sign in to manage your bookings and rent a bike.</p>

      <?php if(isset($_GET['registered']) && $_GET['registered']=='1'): ?>
        <div class="success">Registration successful — please login.</div>
      <?php endif; ?>

      <div id="login-error" class="error" style="display: <?= $login_error ? 'block' : 'none' ?>;"><?= $login_error ? htmlspecialchars($login_error) : '' ?></div>

      <form id="loginForm" method="POST">
        <div class="form-row"><input id="email" class="field" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" placeholder="Email" autocomplete="email"></div>
        <div class="form-row"><input id="password" class="field" type="password" name="password" placeholder="Password" autocomplete="current-password"></div>
        <div class="auth-actions">
          <button class="btn" name="login">Sign in</button>
          <a class="btn btn-alt" href="register.php">Register</a>
        </div>
      </form>

      
    </div>
  </div>

  <script>
  (function(){
    var form = document.getElementById('loginForm');
    form.addEventListener('submit', function(e){
      var err = document.getElementById('login-error'); err.style.display='none'; err.textContent='';
      var email = document.getElementById('email').value.trim();
      var pass = document.getElementById('password').value;
      if(!email || !pass){ err.textContent='Please enter email and password.'; err.style.display='block'; e.preventDefault(); }
    });
  })();
  </script>

</body>
</html>
