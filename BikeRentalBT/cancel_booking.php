<?php
include 'config.php';

if(!isset($_SESSION['user'])){
  header('Location: login.php'); exit;
}

$user_email = $_SESSION['user'];
$stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $user_email);
mysqli_stmt_execute($stmt);
$u_result = mysqli_stmt_get_result($stmt);
if(mysqli_num_rows($u_result) === 0){
  mysqli_stmt_close($stmt);
  header('Location: dashboard.php?cancel=error'); exit;
}
$u = mysqli_fetch_assoc($u_result);
mysqli_stmt_close($stmt);
$user_id = (int)$u['id'];

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['booking_id']) || !isset($_POST['csrf_token'])){
  header('Location: dashboard.php?cancel=error'); exit;
}

// Verify CSRF token
if(!verify_csrf_token($_POST['csrf_token'])){
  header('Location: dashboard.php?cancel=error'); exit;
}

$bid = (int)$_POST['booking_id'];

// verify ownership
$v = mysqli_prepare($conn, "SELECT status, date_to FROM bookings WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($v, 'ii', $bid, $user_id);
mysqli_stmt_execute($v);
mysqli_stmt_bind_result($v, $status, $date_to);
if(!mysqli_stmt_fetch($v)){
  mysqli_stmt_close($v);
  header('Location: dashboard.php?cancel=notfound'); exit;
}
mysqli_stmt_close($v);

if($status === 'cancelled'){
  header('Location: dashboard.php?cancel=already'); exit;
}

if($date_to < date('Y-m-d')){
  header('Location: dashboard.php?cancel=expired'); exit;
}

// perform cancellation (mark as cancelled)
$u2 = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE id = ? AND user_id = ?");
$cancelled_status = 'cancelled';
mysqli_stmt_bind_param($u2, 'sii', $cancelled_status, $bid, $user_id);
mysqli_stmt_execute($u2);
mysqli_stmt_close($u2);

// Append to SQL file
$sql_file = __DIR__ . '/sql/bikerentalbt.sql';
$update_sql = "UPDATE bookings SET status = 'cancelled' WHERE id = " . $bid . ";\n";
file_put_contents($sql_file, $update_sql, FILE_APPEND);

header('Location: dashboard.php?cancel=success');
exit;

?>
