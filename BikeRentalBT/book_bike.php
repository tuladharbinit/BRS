<?php
include 'config.php';

if(!isset($_SESSION['user'])){
  header('Location: login.php');
  exit;
}

$user = $_SESSION['user'];
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $user);
mysqli_stmt_execute($stmt);
$u_result = mysqli_stmt_get_result($stmt);
if(mysqli_num_rows($u_result) === 0){
  mysqli_stmt_close($stmt);
  die('User not found');
}
$u = mysqli_fetch_assoc($u_result);
mysqli_stmt_close($stmt);
$user_id = (int)$u['id'];

$bike_id = isset($_GET['bike']) ? (int)$_GET['bike'] : 0;
if($bike_id <= 0){
  die('Invalid bike selected');
}

 $error = '';
if(isset($_POST['book'])){
  // Verify CSRF token
  if(!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])){
    $error = 'Security token invalid.';
  } else {
    $from = isset($_POST['from']) ? trim($_POST['from']) : '';
    $to = isset($_POST['to']) ? trim($_POST['to']) : '';

    if($from === '' || $to === ''){
      $error = 'Please provide both from and to dates.';
    } elseif($from > $to){
      $error = 'The start date must be before or equal to the end date.';
    } else {
      // check for overlapping confirmed bookings
      $conf = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookings WHERE bike_id = ? AND status = ? AND NOT (date_to < ? OR date_from > ?)");
      $confirmed_status = 'confirmed';
      mysqli_stmt_bind_param($conf, 'isss', $bike_id, $confirmed_status, $from, $to);
      mysqli_stmt_execute($conf);
      mysqli_stmt_bind_result($conf, $cnt);
      mysqli_stmt_fetch($conf);
      mysqli_stmt_close($conf);

      if($cnt > 0){
        $error = 'Selected bike is not available for those dates.';
      } else {
        $pending_status = 'pending';
        $stmt = mysqli_prepare($conn, "INSERT INTO bookings(user_id,bike_id,date_from,date_to,status) VALUES(?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iisss', $user_id, $bike_id, $from, $to, $pending_status);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Append to SQL file for data persistence
        $sql_file = __DIR__ . '/sql/bikerentalbt.sql';
        $insert_sql = "INSERT INTO bookings (user_id,bike_id,date_from,date_to,status) VALUES (" . $user_id . "," . $bike_id . ",'" . mysqli_real_escape_string($conn, $from) . "','" . mysqli_real_escape_string($conn, $to) . "','" . mysqli_real_escape_string($conn, $pending_status) . "');\n";
        file_put_contents($sql_file, $insert_sql, FILE_APPEND);

        header('Location: dashboard.php?book=success');
        exit;
      }
    }
  }
}

$bike_stmt = mysqli_prepare($conn, "SELECT * FROM bikes WHERE id = ?");
mysqli_stmt_bind_param($bike_stmt, 'i', $bike_id);
mysqli_stmt_execute($bike_stmt);
$bike_result = mysqli_stmt_get_result($bike_stmt);
$bike = mysqli_fetch_assoc($bike_result);
mysqli_stmt_close($bike_stmt);
?>

<h2>Book: <?= htmlspecialchars($bike['brand']) ?></h2>
<?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<style>
  .booking-form { max-width: 450px; margin: 30px auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.08); }
  .form-row { margin: 15px 0; }
  .form-row label { display: block; font-weight: 600; margin-bottom: 6px; }
  .form-row input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
  .price-info { background: #f5f8fb; padding: 15px; border-radius: 6px; margin: 15px 0; font-size: 14px; }
  .price-info .row { display: flex; justify-content: space-between; margin: 8px 0; }
  .price-info .total { font-weight: 600; font-size: 16px; color: #333; border-top: 1px solid #ddd; margin-top: 10px; padding-top: 10px; }
  .error { color: #b00020; margin-bottom: 12px; }
</style>
<div class="booking-form">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="form-row">
      <label for="from">From Date:</label>
      <input id="from" type="date" name="from" required>
    </div>
    <div class="form-row">
      <label for="to">To Date:</label>
      <input id="to" type="date" name="to" required>
    </div>
    
    <div class="price-info">
      <div class="row">
        <span>Price per day:</span>
        <span>NPR <?= htmlspecialchars($bike['price']) ?></span>
      </div>
      <div class="row">
        <span>Number of days:</span>
        <span id="days">0</span>
      </div>
      <div class="row total">
        <span>Total Amount:</span>
        <span id="total">NPR 0</span>
      </div>
    </div>
    
    <div class="form-row"><button class="btn" name="book" style="width: 100%; padding: 12px; background: #16a34a; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">Request Booking</button></div>
  </form>
</div>

<script>
  const price = <?= $bike['price'] ?>;
  const fromInput = document.getElementById('from');
  const toInput = document.getElementById('to');
  const daysDisplay = document.getElementById('days');
  const totalDisplay = document.getElementById('total');
  
  function calculateTotal() {
    if(fromInput.value && toInput.value) {
      const fromDate = new Date(fromInput.value);
      const toDate = new Date(toInput.value);
      const days = Math.floor((toDate - fromDate) / (1000 * 60 * 60 * 24)) + 1;
      if(days > 0) {
        daysDisplay.textContent = days;
        const total = days * price;
        totalDisplay.textContent = 'NPR ' + total.toLocaleString();
      }
    }
  }
  
  fromInput.addEventListener('change', calculateTotal);
  toInput.addEventListener('change', calculateTotal);
</script>
