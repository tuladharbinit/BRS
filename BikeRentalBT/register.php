<?php
include 'config.php';

$error = '';
if(isset($_POST['register'])){
  $name = trim($_POST['name']);
  $email = trim($_POST['email']);
  $password = $_POST['password'];
  $contact = trim($_POST['contact_number']);

  if($name === '' || $email === '' || $password === '' || $contact === ''){
    $error = 'Please fill all required fields.';
  } elseif(!preg_match('/^[a-zA-Z\s]+$/', $name)){
    $error = 'Name must not contain numbers.';
  } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
    $error = 'Invalid email address.';
  } elseif(strlen($password) < 6){
    $error = 'Password must be at least 6 characters.';
  } elseif(!preg_match('/^[0-9]{10}$/', $contact)){
    $error = 'Contact number must be exactly 10 digits.';
  } else {
    // ensure uploads dir
    if(!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);

    // check existing
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if(mysqli_stmt_num_rows($stmt) > 0){
      $error = 'Email already registered.';
    }
    mysqli_stmt_close($stmt);
  }

  if($error === ''){
    // handle upload
    if(isset($_FILES['document']) && $_FILES['document']['error'] === 0){
      $allowed = ['application/pdf','image/jpeg','image/png'];
      $type = $_FILES['document']['type'];
      if(!in_array($type, $allowed)){
        $error = 'Invalid document type (PDF/JPEG/PNG).';
      } elseif($_FILES['document']['size'] > 2 * 1024 * 1024){
        $error = 'Document too large (max 2MB).';
      } else {
        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $docname = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = __DIR__ . '/uploads/' . $docname;
        if(!move_uploaded_file($_FILES['document']['tmp_name'], $target)){
          $error = 'Upload failed.';
        }
      }
    } else {
      $docname = NULL;
    }
  }

  if($error === ''){
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "INSERT INTO users (name,email,password,contact_number,document) VALUES (?,?,?,?,?)");
    mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $hash, $contact, $docname);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Append to SQL file for data persistence
    $sql_file = __DIR__ . '/sql/bikerentalbt.sql';
    $insert_sql = "INSERT INTO users (name,email,password,contact_number,document) VALUES ('" . mysqli_real_escape_string($conn, $name) . "','" . mysqli_real_escape_string($conn, $email) . "','" . mysqli_real_escape_string($conn, $hash) . "','" . mysqli_real_escape_string($conn, $contact) . "','" . mysqli_real_escape_string($conn, $docname) . "');\n";
    file_put_contents($sql_file, $insert_sql, FILE_APPEND);

    header("Location: login.php?registered=1");
    exit;
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-bg">

<div class="auth-wrap">
  <div class="auth-card">
    <div class="site-logo" aria-hidden="true"></div>
    <h2>Create an account</h2>
    <p class="lead">Quickly create your account to start booking.</p>
    <?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <div id="client-error" class="error" style="display:none"></div>

    <form id="regForm" method="POST" enctype="multipart/form-data">
      <div class="form-row"><input id="name" class="field" name="name" placeholder="Full Name" required value="<?= isset($name)?htmlspecialchars($name):'' ?>"></div>
      <div class="form-row"><input id="email" class="field" type="email" name="email" placeholder="Email" autocomplete="email" required value="<?= isset($email)?htmlspecialchars($email):'' ?>"></div>
      <div class="form-row"><input id="password" class="field" type="password" name="password" placeholder="Password (min 6)" required></div>
      <div class="form-row"><input id="contact_number" class="field" type="tel" name="contact_number" placeholder="Contact Number" required value="<?= isset($contact)?htmlspecialchars($contact):'' ?>"></div>
      <div class="form-row">Document (PDF/JPEG/PNG, max 2MB): <input id="document" type="file" name="document" accept="application/pdf,image/*" required></div>
      <div class="auth-actions"><button class="btn" name="register">Register</button> <a class="small-link" href="login.php">Already have an account?</a></div>
    </form>
  </div>
</div>

<script>
document.getElementById('regForm').addEventListener('submit', function(e){
  var err = document.getElementById('client-error');
  err.style.display = 'none'; err.textContent = '';
  var name = document.getElementById('name').value.trim();
  var email = document.getElementById('email').value.trim();
  var password = document.getElementById('password').value;
  var contact = document.getElementById('contact_number').value.trim();
  var doc = document.getElementById('document').files[0];

  if(!name || !email || !password || !contact){
    err.textContent = 'Please fill all required fields.'; err.style.display='block'; e.preventDefault(); return;
  }
  if(/[0-9]/.test(name)){
    err.textContent='Name must not contain numbers.'; err.style.display='block'; e.preventDefault(); return;
  }
  var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if(!emailRe.test(email)){ err.textContent='Invalid email address.'; err.style.display='block'; e.preventDefault(); return; }
  if(password.length < 6){ err.textContent='Password must be at least 6 characters.'; err.style.display='block'; e.preventDefault(); return; }
  if(!/^[0-9]{10}$/.test(contact)){ err.textContent='Contact number must be exactly 10 digits.'; err.style.display='block'; e.preventDefault(); return; }
  if(!doc){ err.textContent='Please attach a document.'; err.style.display='block'; e.preventDefault(); return; }
  var allowed = ['application/pdf','image/jpeg','image/png'];
  if(allowed.indexOf(doc.type) === -1){ err.textContent='Document must be PDF/JPEG/PNG.'; err.style.display='block'; e.preventDefault(); return; }
  if(doc.size > 2*1024*1024){ err.textContent='Document too large (max 2MB).'; err.style.display='block'; e.preventDefault(); return; }
});
</script>

</body>
</html>
