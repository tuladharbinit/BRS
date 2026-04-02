<?php
include '../config.php';
if(!isset($_SESSION['admin'])){
  header('Location: admin_login.php');
  exit;
}

// Delete user
if(isset($_GET['delete'])){
  $id = (int)$_GET['delete'];
  $r = mysqli_prepare($conn, "SELECT document FROM users WHERE id = ?");
  mysqli_stmt_bind_param($r, 'i', $id);
  mysqli_stmt_execute($r);
  mysqli_stmt_bind_result($r, $doc);
  mysqli_stmt_fetch($r);
  mysqli_stmt_close($r);
  if($doc){
    $path = __DIR__ . '/../uploads/' . $doc;
    if(file_exists($path)) @unlink($path);
  }
  $d = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
  mysqli_stmt_bind_param($d, 'i', $id);
  mysqli_stmt_execute($d);
  mysqli_stmt_close($d);

  // Append to SQL file
  $sql_file = __DIR__ . '/../sql/bikerentalbt.sql';
  $delete_sql = "DELETE FROM users WHERE id = " . $id . ";\n";
  file_put_contents($sql_file, $delete_sql, FILE_APPEND);

  // Reset auto_increment to fill gaps
  $next_id_query = "SELECT COALESCE( (SELECT MIN(a.id + 1) FROM users a LEFT JOIN users b ON a.id + 1 = b.id WHERE b.id IS NULL), (SELECT MAX(id)+1 FROM users), 1 ) AS next_id";
  $result = mysqli_query($conn, $next_id_query);
  $next_id = mysqli_fetch_assoc($result)['next_id'];
  mysqli_free_result($result);
  mysqli_query($conn, "ALTER TABLE users AUTO_INCREMENT = $next_id");
  header('Location: manage_users.php');
  exit;
}

$res = mysqli_query($conn, "SELECT id, name, email, contact_number, document FROM users ORDER BY id DESC");
if(!$res){
  die('Database error: ' . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>table{width:100%;border-collapse:collapse}.thumb{width:140px;height:80px;object-fit:cover;border-radius:6px}th,td{padding:8px;border:1px solid #eee;text-align:left}</style>
</head>
<body>

<div class="admin-container">
  <aside class="sidebar">
    <h3>Admin Panel</h3>
    <nav>
      <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
      <a href="manage_bikes.php" class="nav-link">Manage Bikes</a>
      <a href="manage_bookings.php" class="nav-link">Manage Bookings</a>
      <a href="manage_users.php" class="nav-link active">Manage Users</a>
    </nav>
    <div style="margin-top:14px;border-top:1px solid #f1f5f9;padding-top:12px;color:#486581">Logged in as<br><strong><?= htmlspecialchars($_SESSION['admin']) ?></strong><br><a class="small-link" href="admin_change_password.php">Change Password</a> | <a class="small-link" href="../logout.php">Logout</a></div>
  </aside>

  <main class="main">
    <div class="admin-header">
      <div>
        <h2 class="admin-title">Manage Users</h2>
        <div style="color:#486581">View and manage registered users</div>
      </div>
    </div>

    <div class="card">
      <table>
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Contact</th><th>Document</th><th>Action</th></tr>
        <?php while($u = mysqli_fetch_assoc($res)): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= htmlspecialchars($u['contact_number']) ?></td>
            <td>
              <?php if($u['document']): ?>
                <a href="../uploads/<?= htmlspecialchars($u['document']) ?>" target="_blank">View</a>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td><a href="?delete=<?= (int)$u['id'] ?>" onclick="return confirm('Delete this user?')">Delete</a></td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </main>
</div>

<script>
// highlight nav
var links = document.querySelectorAll('.sidebar .nav-link');
links.forEach(function(a){ if(a.getAttribute('href') === window.location.pathname.split('/').pop()){ a.classList.add('active'); } });
</script>

</body>
</html>
