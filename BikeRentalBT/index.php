<?php include 'config.php'; ?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/css/style.css?v=2">
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

<section class="dark-footer">
  <div class="footer-grid">
    <div class="footer-col">
      <h3>About BikeRentalBT</h3>
      <p>When it comes to motorbike hire and tours in general BikeRentalBT has been a household name in the game whether it be just renting a bike or scooter</p>
    </div>
    
    <div class="footer-col">
      <h3>Address</h3>
      <p>Thamel, Kathmandu</p>
      <p>P: +977 01-5348111</p>
      <p>E: bikerentalbt@gmail.com</p>
    </div>
    
    <div class="footer-col">
      <h3>Required Documents for Foreign Citizens</h3>
      <ul>
        <li>Visa Valid Passport</li>
        <li>Driving License Compulsory. (Better If You've International Driving Licences)</li>
      </ul>
    </div>
    
    <div class="footer-col">
      <h3>Required Documents for Neplease Citizens</h3>
      <ul>
        <li>Valid Passport or Citizenship</li>
        <li>Driving License (Compulsory)</li>
      </ul>
    </div>
  </div>
  <div class="copyright">
    <p>&copy; <?php echo date('Y'); ?> BikeRentalBT. All rights reserved.</p>
  </div>
</section>

</body>
</html>
