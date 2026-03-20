<?php
include 'config.php';

if(!isset($_SESSION['user'])){
  header('Location: login.php');
  exit;
}

$email = $_SESSION['user'];
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$u_result = mysqli_stmt_get_result($stmt);
$u = mysqli_fetch_assoc($u_result);
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .available-bikes { margin-top: 40px; }
    .bikes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
    .bike-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,.08); transition: transform 0.2s; }
    .bike-card:hover { transform: translateY(-4px); }
    .bike-card img { width: 100%; height: 150px; object-fit: cover; }
    .bike-info { padding: 15px; }
    .bike-info h3 { margin: 0 0 8px 0; font-size: 16px; }
    .bike-info p { margin: 0 0 12px 0; color: #666; font-size: 14px; }
    .bike-info .btn { display: block; text-align: center; background: #007bff; color: white; padding: 10px; border-radius: 4px; text-decoration: none; font-size: 14px; cursor: pointer; border: none; }
    .bike-info .btn:hover { background: #0056b3; }
  </style>
</head>
<body>
<div class="dashboard">
  <div class="dashboard-header">
    <div>
      <h1>Welcome, <?= htmlspecialchars($u['name']) ?></h1>
      <div class="muted">Logged in as <?= htmlspecialchars($u['email']) ?> — <a href="change_password.php">Change Password</a> | <a href="logout.php">Logout</a></div>
    </div>
    <div>
      <?php if(isset($_GET['book']) && $_GET['book'] === 'success'): ?>
        <div class="success">Booking requested — the admin will confirm it shortly.</div>
      <?php endif; ?>

      <?php if(isset($_GET['cancel'])): ?>
        <?php if($_GET['cancel']==='success'): ?>
          <div class="success">Booking cancelled.</div>
        <?php elseif($_GET['cancel']==='already'): ?>
          <div class="error">Booking was already cancelled.</div>
        <?php elseif($_GET['cancel']==='expired'): ?>
          <div class="error">Cannot cancel booking after the end date has passed.</div>
        <?php else: ?>
          <div class="error">Unable to cancel booking.</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php
  // stats
  $uid = (int)$u['id'];
  $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookings WHERE user_id = ?");
  mysqli_stmt_bind_param($stmt, 'i', $uid);
  mysqli_stmt_execute($stmt);
  $total = mysqli_stmt_get_result($stmt);
  $total = (int)mysqli_fetch_row($total)[0];
  mysqli_stmt_close($stmt);

  $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = ?");
  $status = 'confirmed';
  mysqli_stmt_bind_param($stmt, 'is', $uid, $status);
  mysqli_stmt_execute($stmt);
  $confirmed = mysqli_stmt_get_result($stmt);
  $confirmed = (int)mysqli_fetch_row($confirmed)[0];
  mysqli_stmt_close($stmt);

  $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = ?");
  $status = 'pending';
  mysqli_stmt_bind_param($stmt, 'is', $uid, $status);
  mysqli_stmt_execute($stmt);
  $pending = mysqli_stmt_get_result($stmt);
  $pending = (int)mysqli_fetch_row($pending)[0];
  mysqli_stmt_close($stmt);
  ?>

  <div class="stats-grid">
    <div class="card stat-card">
      <div class="num"><?= $total ?></div>
      <div class="label">Total bookings</div>
    </div>
    <div class="card stat-card">
      <div class="num"><?= $confirmed ?></div>
      <div class="label">Confirmed</div>
    </div>
    <div class="card stat-card">
      <div class="num"><?= $pending ?></div>
      <div class="label">Pending</div>
    </div>
  </div>

  <h2>Your Bookings</h2>
<?php
$stmt = mysqli_prepare($conn, "SELECT bookings.*, bikes.brand, bikes.price FROM bookings JOIN bikes ON bookings.bike_id = bikes.id WHERE bookings.user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);
  if(mysqli_num_rows($res) === 0){
    echo '<p class="muted">You have no bookings yet. Browse our <a href="index.php">bikes</a> to request one.</p>';
  } else {
    echo '<ul class="bookings-list">';
    while($r = mysqli_fetch_assoc($res)){
      $status = htmlspecialchars($r['status']);
      $badge = '<span class="badge '.($r['status']=='pending'?'pending':($r['status']=='confirmed'?'confirmed':'denied')).'">'. $status .'</span>';
      $from = new DateTime($r['date_from']);
      $to = new DateTime($r['date_to']);
      $days = $from->diff($to)->days + 1;
      $total = $days * (int)$r['price'];
      echo '<li class="booking-item">';
      echo '<div class="booking-meta"><div class="title">'.htmlspecialchars($r['brand']).' '.$badge.'</div>';
      echo '<div class="dates">'.htmlspecialchars($r['date_from']).' → '.htmlspecialchars($r['date_to']).' (' . $days . ' days)</div>';
      echo '<div class="amount" style="color: #666; font-size: 14px; margin-top: 5px;"><strong>Total: NPR ' . number_format($total) . '</strong></div></div>';
      if($r['status'] !== 'cancelled' && $r['date_to'] >= date('Y-m-d')){
        echo '<div><form style="display:inline" method="POST" action="cancel_booking.php"><input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '"><input type="hidden" name="booking_id" value="'.(int)$r['id'].'"><button class="btn small" type="submit" name="cancel">Cancel</button></form></div>';
      }
      echo '</li>';
    }
    echo '</ul>';
  }
?>

  <div class="available-bikes">
    <h2>Available Bikes to Rent</h2>
    <p class="muted">Browse and book from our available bikes</p>
    <div class="bikes-grid">
      <?php
      $bikes_res = mysqli_query($conn, "SELECT * FROM bikes");
      while($bike = mysqli_fetch_assoc($bikes_res)){
      ?>
      <div class="bike-card">
        <img src="<?= htmlspecialchars($bike['image']) ?>" alt="<?= htmlspecialchars($bike['brand']) ?>">
        <div class="bike-info">
          <h3><?= htmlspecialchars($bike['brand']) ?></h3>
          <p>NPR <?= htmlspecialchars($bike['price']) ?> / day</p>
          <a class="btn" href="book_bike.php?bike=<?= (int)$bike['id'] ?>">Book Now</a>
        </div>
      </div>
      <?php } ?>
    </div>
  </div>
</body>
</html>
