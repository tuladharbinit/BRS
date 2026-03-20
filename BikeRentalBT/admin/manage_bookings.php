<?php
include '../config.php';
if(!isset($_SESSION['admin'])){
  header('Location: admin_login.php');
  exit;
}

// handle actions (use POST with CSRF tokens for security)
if(isset($_POST['action'])){
  if(!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])){
    header('Location: manage_bookings.php'); exit;
  }

  $id = (int)$_POST['booking_id'];
  $action = $_POST['action'];

  if($action === 'confirm'){
    $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE id = ?");
    $confirmed = 'confirmed';
    mysqli_stmt_bind_param($stmt, 'si', $confirmed, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  } elseif($action === 'deny'){
    $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE id = ?");
    $denied = 'denied';
    mysqli_stmt_bind_param($stmt, 'si', $denied, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  } elseif($action === 'cancel'){
    // Check if booking is still active (date_to not passed)
    $check_stmt = mysqli_prepare($conn, "SELECT date_to FROM bookings WHERE id = ? AND status = ?");
    $confirmed = 'confirmed';
    mysqli_stmt_bind_param($check_stmt, 'is', $id, $confirmed);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_bind_result($check_stmt, $date_to);
    if(mysqli_stmt_fetch($check_stmt)){
      if($date_to >= date('Y-m-d')){
        $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE id = ?");
        $cancelled = 'cancelled';
        mysqli_stmt_bind_param($stmt, 'si', $cancelled, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
      }
    }
    mysqli_stmt_close($check_stmt);
  }

  header('Location: manage_bookings.php'); exit;
}

$res = mysqli_query($conn, "SELECT bookings.*, users.name, bikes.brand, bikes.price 
FROM bookings 
JOIN users ON bookings.user_id=users.id
JOIN bikes ON bookings.bike_id=bikes.id ORDER BY bookings.id DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="admin-container">
  <aside class="sidebar">
    <h3>Admin Panel</h3>
    <nav>
      <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
      <a href="manage_bikes.php" class="nav-link">Manage Bikes</a>
      <a href="manage_bookings.php" class="nav-link active">Manage Bookings</a>
      <a href="manage_users.php" class="nav-link">Manage Users</a>
    </nav>
    <div style="margin-top:14px;border-top:1px solid #f1f5f9;padding-top:12px;color:#486581">Logged in as<br><strong><?= htmlspecialchars($_SESSION['admin']) ?></strong><br><a class="small-link" href="admin_change_password.php">Change Password</a> | <a class="small-link" href="../logout.php">Logout</a></div>
  </aside>

  <main class="main">
    <div class="admin-header">
      <div>
        <h2 class="admin-title">Manage Bookings</h2>
        <div style="color:#486581">Approve or deny user booking requests</div>
      </div>
    </div>

    <div class="card">
      <table class="recent-table" style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid #eef4fb">
            <th style="padding:10px">#</th>
            <th style="padding:10px">User</th>
            <th style="padding:10px">Bike</th>
            <th style="padding:10px">From</th>
            <th style="padding:10px">To</th>
            <th style="padding:10px">Amount</th>
            <th style="padding:10px">Status</th>
            <th style="padding:10px;text-align:right">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = mysqli_fetch_assoc($res)): ?>
            <tr style="border-bottom:1px solid #f5f8fb">
              <td style="padding:10px;vertical-align:middle"><?= (int)$row['id'] ?></td>
              <td style="padding:10px;vertical-align:middle"><?= htmlspecialchars($row['name']) ?></td>
              <td style="padding:10px;vertical-align:middle"><?= htmlspecialchars($row['brand']) ?></td>
              <td style="padding:10px;vertical-align:middle"><?= htmlspecialchars($row['date_from']) ?></td>
              <td style="padding:10px;vertical-align:middle"><?= htmlspecialchars($row['date_to']) ?></td>
              <td style="padding:10px;vertical-align:middle"><?php 
                $from = new DateTime($row['date_from']);
                $to = new DateTime($row['date_to']);
                $days = $from->diff($to)->days + 1;
                $total = $days * (int)$row['price'];
                echo 'NPR ' . number_format($total);
              ?></td>
              <td style="padding:10px;vertical-align:middle" class="status">
                <?php $s = $row['status']; $cls = $s==='confirmed'?'confirmed':($s==='denied'?'denied':'pending'); ?>
                <span class="badge <?= $cls ?>"><?= htmlspecialchars(ucfirst($s)) ?></span>
              </td>
              <td style="padding:10px;vertical-align:middle;text-align:right">
                <?php if($row['status'] === 'pending'): ?>
                  <form style="display:inline" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="booking_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="action" value="confirm">
                    <button class="btn" type="submit" onclick="return confirm('Confirm this booking?')" style="background:#16a34a">Confirm</button>
                  </form>
                  <form style="display:inline" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="booking_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="action" value="deny">
                    <button class="btn small" type="submit" onclick="return confirm('Deny this booking?')" style="margin-left:8px">Deny</button>
                  </form>
                <?php elseif($row['status'] === 'confirmed'): ?>
                  <?php if($row['date_to'] >= date('Y-m-d')): ?>
                    <form style="display:inline" method="POST">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="booking_id" value="<?= (int)$row['id'] ?>">
                      <input type="hidden" name="action" value="cancel">
                      <button class="btn small" type="submit" onclick="return confirm('Cancel this booking?')" style="margin-left:8px;background:#dc2626">Cancel</button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
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
