<?php
include '../config.php';
if(!isset($_SESSION['admin'])){
  header('Location: admin_login.php');
  exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<?php
// gather stats
$users_count = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users"))[0];
$bikes_count = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM bikes"))[0];
$bookings_count = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM bookings"))[0];
$pending_count = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM bookings WHERE status='pending'"))[0];

$recent = mysqli_query($conn, "SELECT bookings.id, users.name AS user_name, bikes.brand AS bike_brand, bookings.date_from, bookings.date_to, bookings.status
FROM bookings
JOIN users ON bookings.user_id = users.id
JOIN bikes ON bookings.bike_id = bikes.id
WHERE DATE_FORMAT(bookings.date_from, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
ORDER BY bookings.id DESC LIMIT 8");
// bookings per month (last 6 months)
$monthly = mysqli_query($conn, "SELECT DATE_FORMAT(date_from, '%Y-%m') AS ym, COUNT(*) AS cnt FROM bookings GROUP BY ym ORDER BY ym DESC LIMIT 6");
$months = [];
$counts = [];
while($m = mysqli_fetch_assoc($monthly)){
  $months[] = $m['ym'];
  $counts[] = (int)$m['cnt'];
}
// reverse to chronological order
$months = array_reverse($months);
$counts = array_reverse($counts);
?>

<div class="admin-container">
  <aside class="sidebar">
    <h3>Admin Panel</h3>
    <nav>
      <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
      <a href="manage_bikes.php" class="nav-link">Manage Bikes</a>
      <a href="manage_bookings.php" class="nav-link">Manage Bookings</a>
      <a href="manage_users.php" class="nav-link">Manage Users</a>
    </nav>
    <div style="margin-top:14px;border-top:1px solid #f1f5f9;padding-top:12px;color:#486581">Logged in as<br><strong><?= htmlspecialchars($_SESSION['admin']) ?></strong><br><a class="small-link" href="admin_change_password.php">Change Password</a> | <a class="small-link" href="../logout.php">Logout</a></div>
  </aside>

  <main class="main">
    <div class="admin-header" style="align-items:flex-start">
      <div>
        <h2 class="admin-title">Dashboard</h2>
        <div style="color:#486581">Overview of system activity</div>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="row"><div class="icon">📋</div><div><div class="num"><?= $users_count ?></div><div class="label">Registered Users</div></div></div>
      </div>
      <div class="stat-card">
        <div class="row"><div class="icon">🚲</div><div><div class="num"><?= $bikes_count ?></div><div class="label">Bikes</div></div></div>
      </div>
      <div class="stat-card">
        <div class="row"><div class="icon">📅</div><div><div class="num"><?= $bookings_count ?></div><div class="label">Total Bookings</div></div></div>
      </div>
      <div class="stat-card">
        <div class="row"><div class="icon">⏳</div><div><div class="num"><?= $pending_count ?></div><div class="label">Pending Requests</div></div></div>
      </div>
    </div>

    <div class="chart-wrap">
      <h3>Bookings (last months)</h3>
      <canvas id="bookingsChart" height="110"></canvas>
    </div>

    <div class="recent-table">
      <h3>Recent Bookings</h3>
      <table>
        <thead><tr><th>#</th><th>User</th><th>Bike</th><th>From</th><th>To</th><th>Status</th></tr></thead>
        <tbody>
          <?php while($r = mysqli_fetch_assoc($recent)): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= htmlspecialchars($r['user_name']) ?></td>
              <td><?= htmlspecialchars($r['bike_brand']) ?></td>
              <td><?= htmlspecialchars($r['date_from']) ?></td>
              <td><?= htmlspecialchars($r['date_to']) ?></td>
              <td class="status"><?php $s=$r['status']; $cls = $s==='confirmed'?'confirmed':($s==='denied'?'denied':'pending'); ?><span class="badge <?= $cls ?>"><?= htmlspecialchars(ucfirst($s)) ?></span></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script>
// highlight current nav link
var links = document.querySelectorAll('.sidebar .nav-link');
links.forEach(function(a){
  if(a.getAttribute('href') === window.location.pathname.split('/').pop()){
    a.classList.add('active');
  }
});
</script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function(){
    var labels = <?= json_encode($months) ?>;
    var data = <?= json_encode($counts) ?>;
    var ctx = document.getElementById('bookingsChart');
    if(ctx && labels.length){
      new Chart(ctx, {
        type: 'line',
        data: { labels: labels, datasets: [{ label: 'Bookings', data: data, fill: true, backgroundColor: 'rgba(0,122,255,0.08)', borderColor: '#007aff', tension: 0.35, pointRadius:4 }] },
        options: { plugins:{ legend:{ display:false } }, scales:{ x:{ grid:{ display:false } }, y:{ beginAtZero:true, grid:{ color:'#f1f5f9' } } }
      });
    }
  })();
</script>

</body>
</html>
