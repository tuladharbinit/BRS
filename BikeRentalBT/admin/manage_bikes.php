<?php
include '../config.php';
if(!isset($_SESSION['admin'])){
	header('Location: admin_login.php');
	exit;
}

// helper for upload
function saveImage($file){
	$allowed = ['image/jpeg','image/png','image/webp'];
	if(!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
	if(!in_array($file['type'], $allowed)) return null;
	$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
	$name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
	$destDir = __DIR__ . '/../assets/images/';
	if(!is_dir($destDir)) mkdir($destDir, 0755, true);
	$dest = $destDir . $name;
	if(move_uploaded_file($file['tmp_name'], $dest)){
		return 'assets/images/' . $name;
	}
	return null;
}

// Add bike
if(isset($_POST['add_bike'])){
	$brand = trim($_POST['brand']);
	$price = (int)$_POST['price'];
	if($price < 0){
		echo "<script>alert('Price cannot be negative'); window.location='manage_bikes.php';</script>";
		exit;
	}
	$img = saveImage($_FILES['image']);
	$stmt = mysqli_prepare($conn, "INSERT INTO bikes (brand, price, image) VALUES (?,?,?)");
	mysqli_stmt_bind_param($stmt, 'sis', $brand, $price, $img);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);

	// Append to SQL file for data persistence
	$sql_file = __DIR__ . '/../sql/bikerentalbt.sql';
	$insert_sql = "INSERT INTO bikes (brand, price, image) VALUES ('" . mysqli_real_escape_string($conn, $brand) . "'," . $price . ",'" . mysqli_real_escape_string($conn, $img) . "');\n";
	file_put_contents($sql_file, $insert_sql, FILE_APPEND);

	header('Location: manage_bikes.php');
	exit;
}

// Edit bike
if(isset($_POST['edit_bike'])){
	$id = (int)$_POST['id'];
	$brand = trim($_POST['brand']);
	$price = (int)$_POST['price'];
	if($price < 0){
		echo "<script>alert('Price cannot be negative'); window.location='manage_bikes.php?edit=" . $id . "';</script>";
		exit;
	}
	$img = null;
	if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK){
		$img = saveImage($_FILES['image']);
	}
	if($img){
		$stmt = mysqli_prepare($conn, "UPDATE bikes SET brand=?, price=?, image=? WHERE id=?");
		mysqli_stmt_bind_param($stmt, 'sisi', $brand, $price, $img, $id);
	} else {
		$stmt = mysqli_prepare($conn, "UPDATE bikes SET brand=?, price=? WHERE id=?");
		mysqli_stmt_bind_param($stmt, 'sii', $brand, $price, $id);
	}
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);

	// Append to SQL file
	$sql_file = __DIR__ . '/../sql/bikerentalbt.sql';
	if($img){
		$update_sql = "UPDATE bikes SET brand='" . mysqli_real_escape_string($conn, $brand) . "', price=" . $price . ", image='" . mysqli_real_escape_string($conn, $img) . "' WHERE id=" . $id . ";\n";
	} else {
		$update_sql = "UPDATE bikes SET brand='" . mysqli_real_escape_string($conn, $brand) . "', price=" . $price . " WHERE id=" . $id . ";\n";
	}
	file_put_contents($sql_file, $update_sql, FILE_APPEND);

	header('Location: manage_bikes.php');
	exit;
}

// Delete bike
if(isset($_GET['delete'])){
	$id = (int)$_GET['delete'];
	// optionally delete image file
	$r = mysqli_prepare($conn, "SELECT image FROM bikes WHERE id = ?");
	mysqli_stmt_bind_param($r, 'i', $id);
	mysqli_stmt_execute($r);
	mysqli_stmt_bind_result($r, $img_path);
	if(mysqli_stmt_fetch($r)){
		$img_file = __DIR__ . '/../' . $img_path;
		if($img_path && file_exists($img_file)){
			@unlink($img_file);
		}
	}
	mysqli_stmt_close($r);
	$stmt = mysqli_prepare($conn, "DELETE FROM bikes WHERE id = ?");
	mysqli_stmt_bind_param($stmt, 'i', $id);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);

	// Append to SQL file
	$sql_file = __DIR__ . '/../sql/bikerentalbt.sql';
	$delete_sql = "DELETE FROM bikes WHERE id = " . $id . ";\n";
	file_put_contents($sql_file, $delete_sql, FILE_APPEND);

	header('Location: manage_bikes.php');
	exit;
}

// fetch for edit
$editBike = null;
if(isset($_GET['edit'])){
	$id = (int)$_GET['edit'];
	$res = mysqli_prepare($conn, "SELECT * FROM bikes WHERE id = ?");
	mysqli_stmt_bind_param($res, 'i', $id);
	mysqli_stmt_execute($res);
	$editBike = mysqli_fetch_assoc(mysqli_stmt_get_result($res));
	mysqli_stmt_close($res);
}

$res = mysqli_query($conn, "SELECT * FROM bikes ORDER BY id ASC");
?>

<!DOCTYPE html>
<html>
<head>
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<link rel="stylesheet" href="../assets/css/style.css">
	<style>
		table{width:100%;border-collapse:collapse}
		th,td{padding:8px;border:1px solid #ddd;text-align:left}
		img.thumb{width:120px;height:70px;object-fit:cover;border-radius:6px}
		form.inline{display:flex;gap:8px;align-items:center}
		.form-row{margin:10px 0}
	</style>
</head>
<body>

<div class="admin-container">
  <aside class="sidebar">
    <h3>Admin Panel</h3>
    <nav>
      <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
      <a href="manage_bikes.php" class="nav-link active">Manage Bikes</a>
      <a href="manage_bookings.php" class="nav-link">Manage Bookings</a>
      <a href="manage_users.php" class="nav-link">Manage Users</a>
    </nav>
    <div style="margin-top:14px;border-top:1px solid #f1f5f9;padding-top:12px;color:#486581">Logged in as<br><strong><?= htmlspecialchars($_SESSION['admin']) ?></strong><br><a class="small-link" href="admin_change_password.php">Change Password</a> | <a class="small-link" href="../logout.php">Logout</a></div>
  </aside>

  <main class="main">
    <div class="admin-header">
      <div>
        <h2 class="admin-title">Manage Bikes</h2>
        <div style="color:#486581">Add, edit, and manage bike inventory</div>
      </div>
    </div>

    <div class="card">
	<?php if($editBike): ?>
		<h3>Edit Bike #<?= (int)$editBike['id'] ?></h3>
		<form method="POST" enctype="multipart/form-data">
			<input type="hidden" name="id" value="<?= (int)$editBike['id'] ?>">
			<div class="form-row">Brand: <input name="brand" value="<?= htmlspecialchars($editBike['brand']) ?>" required></div>
			<div class="form-row">Price: <input name="price" type="number" value="<?= (int)$editBike['price'] ?>" required></div>
			<div class="form-row">Image: <input type="file" name="image"></div>
			<div class="form-row"><button name="edit_bike">Save Changes</button> <a href="manage_bikes.php">Cancel</a></div>
		</form>
	<?php else: ?>
		<h3>Add New Bike</h3>
		<form method="POST" enctype="multipart/form-data">
			<div class="form-row">Brand: <input name="brand" required></div>
			<div class="form-row">Price: <input name="price" type="number" min="0" required></div>
			<div class="form-row">Image: <input type="file" name="image" required></div>
			<div class="form-row"><button name="add_bike">Add Bike</button></div>
		</form>
	<?php endif; ?>

	<h3>Existing Bikes</h3>
	<table>
		<tr><th>#</th><th>Image</th><th>Brand</th><th>Price</th><th>Actions</th></tr>
		<?php $sn = 1; while($row = mysqli_fetch_assoc($res)): ?>
			<tr>
				<td><?= $sn++ ?></td>
				<td><?php if($row['image']): ?><img class="thumb" src="../<?= htmlspecialchars($row['image']) ?>" alt="Bike image"><?php endif; ?></td>
				<td><?= htmlspecialchars($row['brand']) ?></td>
				<td>NPR <?= htmlspecialchars($row['price']) ?></td>
				<td>
					<a href="?edit=<?= (int)$row['id'] ?>">Edit</a> |
					<a href="?delete=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this bike?')">Delete</a>
				</td>
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

