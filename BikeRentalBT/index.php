<?php include 'config.php'; ?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="hero" style="background-image:url('assets/images/hero.jpg')">
  <div class="hero-overlay"></div>
  <div class="hero-content">
    <h1>Ride The City</h1>
    <p>Affordable rentals • Flexible bookings • Great rides</p>
    <div class="hero-buttons">
      <a class="btn" href="login.php">User Login</a>
      <a class="btn" href="register.php">Register</a>
      <a class="btn btn-alt" href="admin/admin_login.php">Admin Login</a>
    </div>
  </div>
</header>

<section class="about">
  <div class="container">
    <h2>About BikeRentalBT</h2>
    <p>BikeRentalBT makes renting a bike simple and affordable. Choose from a selection of well-maintained bikes, upload your license, and book for the days you need.</p>
    <p>Contact Us : 9818123456</p>
    <p>Address: Thamel, Kathmandu</p>
  </div>
</section>

<section class="browse">
  <div class="container">
    <h2>Available Bikes</h2>
    <div class="bike-container">
    <?php
    $result = mysqli_query($conn, "SELECT * FROM bikes");
    while($row = mysqli_fetch_assoc($result)){
    ?>
    <div class="bike-card">
      <img src="<?= htmlspecialchars($row['image']) ?>">
      <h3><?= htmlspecialchars($row['brand']) ?></h3>
      <p>NPR <?= htmlspecialchars($row['price']) ?> / day</p>
      <?php
        $bike_id = (int)$row['id'];
        if(isset($_SESSION['user'])){
          $link = "book_bike.php?bike={$bike_id}";
        } else {
          $link = "login.php?bike={$bike_id}";
        }
      ?>
      <a class="btn" href="<?= $link ?>">Rent Now</a>
    </div>
    <?php } ?>
    </div>
  </div>
</section>

</body>
</html>
