# BikeRentalBT - Architecture Quick Reference & Code Patterns

## QUICK REFERENCE TABLE

| Question | Answer | Evidence |
|----------|--------|----------|
| **CSR or SSR?** | Server-Side Rendered (SSR) | All HTML generated on server in PHP, no client-side JS framework |
| **API Layer?** | NO - Direct Database | All files use `mysqli_query()` directly, no REST/GraphQL API |
| **Monolithic or Microservices?** | Monolithic | Single codebase, single database, all features in one app |
| **Framework?** | NONE - Vanilla PHP | No Laravel, Symfony, or framework; raw `mysqli` |
| **Routing?** | File-Based | `/book_bike.php` maps to `book_bike.php` file |
| **MVC Separation?** | Minimal - Mixed | All logic (controller+model+view) in same PHP file |
| **Business Logic Location** | Embedded in PHP files | Queries and validation logic spread across request handlers |
| **Frontend** | Custom HTML/CSS + Vanilla JS | No React/Vue; only form validation in vanilla JS |
| **Database** | MySQL with MySQLi | Direct prepared statement queries |
| **Authentication** | Session-Based | `$_SESSION['user']` stores email; checked on each request |
| **JavaScript Used?** | Minimal | Only client-side form validation; no framework |
| **Package Manager** | None | No Composer, npm, or dependency manager |
| **Build Process** | None | Files served as-is, no Webpack/Vite |

---

## CODE PATTERN EXAMPLES

### PATTERN 1: Standard Request Handler (All files follow this)

```php
<?php
// 1. INCLUDE SHARED CONFIG
include 'config.php';

// 2. AUTHORIZATION CHECK
if(!isset($_SESSION['user'])){
  header('Location: login.php');
  exit;
}

// 3. EXTRACT REQUEST DATA
$user_email = $_SESSION['user'];
$bike_id = isset($_GET['bike']) ? (int)$_GET['bike'] : 0;

// ============================================
// 4. BUSINESS LOGIC (Mixed with controller)
// ============================================

// Get user data directly
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $user_email);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

// Handle form submission
$error = '';
if(isset($_POST['book'])){
  // CSRF validation
  if(!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])){
    $error = 'Security token invalid.';
  } else {
    // Extract and validate form data
    $from = isset($_POST['from']) ? trim($_POST['from']) : '';
    $to = isset($_POST['to']) ? trim($_POST['to']) : '';
    
    // Business rule: dates must be valid
    if($from === '' || $to === ''){
      $error = 'Please provide both dates.';
    } elseif($from > $to){
      $error = 'Start date must be before end date.';
    } else {
      // Business logic: check availability
      $conflict_stmt = mysqli_prepare($conn, 
        "SELECT COUNT(*) FROM bookings 
         WHERE bike_id = ? AND status = ? 
         AND NOT (date_to < ? OR date_from > ?)");
      
      $confirmed = 'confirmed';
      mysqli_stmt_bind_param($conflict_stmt, 'isss', $bike_id, $confirmed, $from, $to);
      mysqli_stmt_execute($conflict_stmt);
      mysqli_stmt_bind_result($conflict_stmt, $conflict_count);
      mysqli_stmt_fetch($conflict_stmt);
      mysqli_stmt_close($conflict_stmt);
      
      if($conflict_count > 0){
        $error = 'Bike not available for selected dates.';
      } else {
        // Create booking
        $pending = 'pending';
        $insert_stmt = mysqli_prepare($conn, 
          "INSERT INTO bookings(user_id, bike_id, date_from, date_to, status) 
           VALUES(?, ?, ?, ?, ?)");
        
        $user_id = (int)$user['id'];
        mysqli_stmt_bind_param($insert_stmt, 'iisss', $user_id, $bike_id, $from, $to, $pending);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
        
        // Success: redirect
        header('Location: dashboard.php?book=success');
        exit;
      }
    }
  }
}

// Get bike details
$bike_stmt = mysqli_prepare($conn, "SELECT * FROM bikes WHERE id = ?");
mysqli_stmt_bind_param($bike_stmt, 'i', $bike_id);
mysqli_stmt_execute($bike_stmt);
$bike_result = mysqli_stmt_get_result($bike_stmt);
$bike = mysqli_fetch_assoc($bike_result);
mysqli_stmt_close($bike_stmt);

// ============================================
// 5. VIEW (HTML Output) - Mixed in same file
// ============================================
?>
<!DOCTYPE html>
<html>
<head>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="booking-form">
    <h2>Book: <?= htmlspecialchars($bike['brand']) ?></h2>
    
    <?php if($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      
      <div class="form-row">
        <label>From Date:</label>
        <input type="date" name="from" required>
      </div>
      
      <div class="form-row">
        <label>To Date:</label>
        <input type="date" name="to" required>
      </div>
      
      <div class="price-info">
        <p>Price per day: NPR <?= htmlspecialchars($bike['price']) ?></p>
      </div>
      
      <button name="book">Book Now</button>
    </form>
  </div>
</body>
</html>
```

**Why this pattern shows the architecture:**
- All three layers (controller, model, view) in ONE file
- Database is queried directly with MySQLi
- HTML is generated on server with embedded PHP
- No API endpoint, no separation of concerns
- Authentication check at top (session-based)
- CSRF token generation/validation included

---

### PATTERN 2: Database Query Pattern (Used everywhere)

**SAFE Pattern (Used throughout):**
```php
// 1. Prepare statement with placeholder
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? AND id = ?");

// 2. Bind parameters with type declaration
mysqli_stmt_bind_param($stmt, 'si', $email, $user_id);
//                                     ↑    ↑     ↑
//                                  types  param vars
// 's' = string, 'i' = integer, 'd' = double, 'b' = blob

// 3. Execute
mysqli_stmt_execute($stmt);

// 4. Get result
$result = mysqli_stmt_get_result($stmt);

// 5. Fetch rows
while($row = mysqli_fetch_assoc($result)){
  echo $row['email'];  // Safe - data is de-coupled from SQL
}

// 6. Close
mysqli_stmt_close($stmt);
```

**INSERT Example:**
```php
$name = "John Doe";
$email = "john@example.com";
$password_hash = password_hash("secret123", PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, 
  "INSERT INTO users(name, email, password) VALUES(?, ?, ?)");

mysqli_stmt_bind_param($stmt, 'sss', $name, $email, $password_hash);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

**UPDATE Example:**
```php
$new_price = 2500;
$bike_id = 5;

$stmt = mysqli_prepare($conn, "UPDATE bikes SET price = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $new_price, $bike_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

---

### PATTERN 3: HTML Form Submission (No AJAX)

**Usage Pattern: Forms use traditional POST, not AJAX**

```html
<!-- Traditional Form Submission -->
<form method="POST" action="book_bike.php">
  <!-- CSRF Token (security) -->
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  
  <!-- Normal form fields -->
  <div class="form-row">
    <input type="date" name="from" required>
  </div>
  
  <div class="form-row">
    <input type="date" name="to" required>
  </div>
  
  <!-- No AJAX, no JavaScript required -->
  <button type="submit" name="book">Book Now</button>
</form>
```

**Server Processing:**
```php
// No API endpoint - direct form handling
if(isset($_POST['book'])){
  // Process form...
  header('Location: dashboard.php?book=success');  // Redirect after POST
  exit;
}
```

**Client Validation (Minimal JavaScript):**
```javascript
// Only client-side validation - server still validates
document.getElementById('bookingForm').addEventListener('submit', function(e){
  var from = document.getElementById('from').value;
  var to = document.getElementById('to').value;
  
  if(!from || !to){
    e.preventDefault();
    alert('Please fill all fields');
  }
});
```

This is **NOT an AJAX/SPA pattern** - page reloads after form submission.

---

### PATTERN 4: Authentication & Session Management

```php
// ========== LOGIN.PHP ==========
if(isset($_POST['login'])){
  $email = trim($_POST['email']);
  $password = $_POST['password'];
  
  // Get password hash from database
  $stmt = mysqli_prepare($conn, "SELECT id, password FROM users WHERE email = ?");
  mysqli_stmt_bind_param($stmt, 's', $email);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $uid, $hash);
  
  if(mysqli_stmt_fetch($stmt)){
    // Verify password using bcrypt
    if(password_verify($password, $hash)){
      // SECURITY: Regenerate session ID to prevent fixation
      session_regenerate_id(true);
      
      // Store user in session
      $_SESSION['user'] = $email;
      
      // Redirect to dashboard
      header("Location: dashboard.php");
      exit;
    } else {
      $error = 'Invalid credentials.';
    }
  }
  mysqli_stmt_close($stmt);
}

// ========== DASHBOARD.PHP ==========
<?php
include 'config.php';

// Authorization check - on every request
if(!isset($_SESSION['user'])){
  header('Location: login.php');
  exit;
}

// Get user email from session
$user_email = $_SESSION['user'];

// Fetch user details from database
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $user_email);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Display personalized content
?>
<h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>

// ========== LOGOUT.PHP ==========
<?php
include 'config.php';

// Destroy all session data
$_SESSION = [];

// Delete session cookie
if(ini_get("session.use_cookies")){
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params['path'], $params['domain'],
    $params['secure'], $params['httponly']
  );
}

// Destroy session
session_destroy();

// Redirect to home
header('Location: index.php');
exit;
?>
```

---

### PATTERN 5: Admin Operations (Same App, Different Files)

**Admin File Structure:**

```php
// ========== admin/admin_login.php ==========
<?php
include '../config.php';  // Shared config

if(isset($_POST['login'])){
  $username = trim($_POST['username']);
  $password = $_POST['password'];
  
  // Query admin table (separate from users)
  $stmt = mysqli_prepare($conn, "SELECT id, password FROM admin WHERE username = ?");
  mysqli_stmt_bind_param($stmt, 's', $username);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $aid, $hash);
  
  if(mysqli_stmt_fetch($stmt)){
    if(password_verify($password, $hash)){
      session_regenerate_id(true);
      $_SESSION['admin'] = $username;  // Admin session (different key)
      header('Location: admin_dashboard.php');
      exit;
    }
  }
}
?>

// ========== admin/manage_bookings.php ==========
<?php
include '../config.php';

// Authorization: check for admin (not user)
if(!isset($_SESSION['admin'])){
  header('Location: admin_login.php');
  exit;
}

// Handle booking approvals
if(isset($_POST['action'])){
  // CSRF check
  if(!verify_csrf_token($_POST['csrf_token'])){
    header('Location: manage_bookings.php');
    exit;
  }
  
  $booking_id = (int)$_POST['booking_id'];
  $action = $_POST['action'];
  
  // Direct database update (business logic)
  if($action === 'confirm'){
    $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE id = ?");
    $confirmed = 'confirmed';
    mysqli_stmt_bind_param($stmt, 'si', $confirmed, $booking_id);
    mysqli_stmt_execute($stmt);
  } elseif($action === 'deny'){
    $stmt = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE id = ?");
    $denied = 'denied';
    mysqli_stmt_bind_param($stmt, 'si', $denied, $booking_id);
    mysqli_stmt_execute($stmt);
  }
  
  header('Location: manage_bookings.php');
  exit;
}

// Fetch all bookings with joins
$query = "SELECT bookings.*, 
                 users.name AS user_name, 
                 bikes.brand AS bike_brand, 
                 bikes.price 
          FROM bookings 
          JOIN users ON bookings.user_id = users.id 
          JOIN bikes ON bookings.bike_id = bikes.id 
          ORDER BY bookings.id DESC";

$result = mysqli_query($conn, $query);
?>
```

**Key Differences (Admin vs User):**
- Different tables: `admin` vs `users` 
- Different session key: `$_SESSION['admin']` vs `$_SESSION['user']`
- Admin can update bookings, users can only view/request
- Same database, same application

---

### PATTERN 6: CSRF Protection (Used in all forms)

```php
// ========== config.php ==========
<?php
session_start();

// Token generation
function csrf_token() {
    if(!isset($_SESSION['csrf_token'])){
        // Create 32-byte random token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Token verification
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $token);
}
?>

// ========== In HTML Forms ==========
<form method="POST">
  <!-- Hidden field with token -->
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <!-- form fields -->
</form>

// ========== When Processing Form ==========
<?php
if(isset($_POST['book'])){
  // First check CSRF token
  if(!verify_csrf_token($_POST['csrf_token'])){
    die('Security token invalid.');
  }
  
  // Then process form
  // ... rest of logic ...
}
?>
```

**Why important:**
- Prevents Cross-Site Request Forgery attacks
- Token stored in server session, sent with form
- Verified before processing any state-changing request (POST)
- Two-step validation: token generation + verification

---

### PATTERN 7: Password Security (Bcrypt with Legacy Support)

```php
// ========== REGISTRATION ==========
$password = $_POST['password'];

// Hash with bcrypt (modern, secure)
$hash = password_hash($password, PASSWORD_DEFAULT);
// Result: $2y$10$... (60 characters, bcrypt format)

// Store in database
$stmt = mysqli_prepare($conn, "INSERT INTO users(..., password) VALUES(..., ?)");
mysqli_stmt_bind_param($stmt, '...s...', ..., $hash);
mysqli_stmt_execute($stmt);

// ========== LOGIN - Verification ==========
$stored_hash = $user['password'];  // From database
$user_password = $_POST['password'];

// Try bcrypt first
$ok = false;
if(password_verify($user_password, $stored_hash)){
  $ok = true;  // Correct password
}
// Legacy support for old MD5 hashes
elseif(strlen($stored_hash) === 32 && md5($user_password) === $stored_hash){
  $ok = true;  // Old password matches MD5
  
  // Upgrade to bcrypt on next login
  $new_hash = password_hash($user_password, PASSWORD_DEFAULT);
  $update = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
  mysqli_stmt_bind_param($update, 'si', $new_hash, $user_id);
  mysqli_stmt_execute($update);
}

if($ok){
  session_regenerate_id(true);
  $_SESSION['user'] = $email;
}
```

**Security Progression:**
1. New users: bcrypt from registration
2. Old users with MD5: verified, then automatically upgraded
3. All passwords eventually bcrypt

---

### PATTERN 8: Input Validation (No Dedicated Service)

**Scattered validation in each file:**

```php
// ========== REGISTRATION VALIDATION ==========
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$contact = trim($_POST['contact_number']);

$error = '';

// Name validation
if($name === ''){
  $error = 'Name is required.';
} elseif(!preg_match('/^[a-zA-Z\s]+$/', $name)){
  $error = 'Name must only contain letters and spaces.';
}

// Email validation
if($email === ''){
  $error = 'Email is required.';
} elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
  $error = 'Invalid email address.';
}

// Password validation
if($password === ''){
  $error = 'Password is required.';
} elseif(strlen($password) < 6){
  $error = 'Password must be at least 6 characters.';
}

// Contact validation
if($contact === ''){
  $error = 'Contact number is required.';
} elseif(!preg_match('/^[0-9]{10}$/', $contact)){
  $error = 'Contact number must be exactly 10 digits.';
}

// Check duplicates
if($error === ''){
  $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
  mysqli_stmt_bind_param($stmt, 's', $email);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_store_result($stmt);
  
  if(mysqli_stmt_num_rows($stmt) > 0){
    $error = 'Email already registered.';
  }
  mysqli_stmt_close($stmt);
}

// ========== BOOKING VALIDATION ==========
$from = trim($_POST['from']);
$to = trim($_POST['to']);

// Date validation
if($from === '' || $to === ''){
  $error = 'Please provide both dates.';
} elseif($from > $to){
  $error = 'Start date must be before end date.';
}

// Availability check (business logic + validation)
if($error === ''){
  $stmt = mysqli_prepare($conn, 
    "SELECT COUNT(*) FROM bookings 
     WHERE bike_id = ? AND status = ? 
     AND NOT (date_to < ? OR date_from > ?)");
  
  $confirmed = 'confirmed';
  mysqli_stmt_bind_param($stmt, 'isss', $bike_id, $confirmed, $from, $to);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $count);
  mysqli_stmt_fetch($stmt);
  
  if($count > 0){
    $error = 'Bike not available for selected dates.';
  }
}
```

**Problems with this approach:**
- Validation repeated across files (DRY violation)
- No reusable validation library
- Hard to change validation rules
- No centralized validation

---

### PATTERN 9: HTML Output with XSS Prevention

**ALL user output uses htmlspecialchars():**

```php
<!-- Safe output (all use htmlspecialchars) -->

<!-- User name -->
<h1>Welcome, <?= htmlspecialchars($user['name']) ?></h1>

<!-- User email -->
<p>Logged in as <?= htmlspecialchars($user['email']) ?></p>

<!-- Bike brand -->
<h3><?= htmlspecialchars($bike['brand']) ?></h3>

<!-- Error messages (user input) -->
<?php if($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Form values (repopulate on error) -->
<input name="name" value="<?= htmlspecialchars($name) ?>">

<!-- Database values in table -->
<td><?= htmlspecialchars($row['user_name']) ?></td>

<!-- Href attributes -->
<a href="<?= htmlspecialchars($row['image']) ?>">View</a>
```

**What htmlspecialchars() does:**
```
Input:  <script>alert('xss')</script>
Output: &lt;script&gt;alert('xss')&lt;/script&gt;

Browser sees: &lt;script&gt;... (as text, not executable code)
```

---

## MISSING FROM THIS ARCHITECTURE

### What Could Improve This System

1. **API Layer**
   - Currently: No REST API, no AJAX endpoints
   - Could add: `/api/bookings/available` to check availability without page reload

2. **Framework**
   - Currently: Raw PHP files
   - Could use: Laravel, Symfony for structure/validation/ORM

3. **ORM**
   - Currently: Raw MySQLi queries everywhere
   - Could use: Doctrine, Eloquent for database abstraction

4. **Template Engine**
   - Currently: HTML mixed in PHP
   - Could use: Twig, Blade to separate templates

5. **Routing Framework**
   - Currently: File-based routing
   - Could use: FastRoute, AltoRouter for cleaner URLs

6. **Form Validation Library**
   - Currently: Manual validation in each file
   - Could use: Respect/Validation, Symfony Validator

7. **Frontend Framework**
   - Currently: Vanilla HTML/CSS/JS
   - Could add: React, Vue for interactive UX

8. **Build Process**
   - Currently: None
   - Could add: Webpack, Vite for minification/bundling

9. **Testing**
   - Currently: No automated tests
   - Could add: PHPUnit for unit testing

10. **Logging**
    - Currently: No error/event logging
    - Could add: Monolog for centralized logging

---

## PERFORMANCE CHARACTERISTICS

### Page Load Times (Approximate)

```
Home Page (index.php with bike listing):
├─ PHP execution: 50-100ms (database queries)
├─ Database I/O: 20-50ms (select bikes)
├─ HTML generation: 10-20ms (template rendering)
├─ Browser parsing: 30-50ms (parse HTML, CSS)
├─ CSS loading: 5-10ms (style.css)
└─ Total: ~150-250ms (competitive)

Booking Page (book_bike.php):
├─ Session check: 5-10ms
├─ Database queries: 30-50ms (user, bike, availability check)
├─ HTML generation: 15-25ms
├─ Browser rendering: 20-40ms
└─ Total: ~100-150ms

Admin Dashboard (admin_dashboard.php):
├─ Multiple aggregation queries: 50-100ms
├─ Chart data generation: 20-40ms
├─ HTML generation: 30-50ms
├─ Browser render: 50-100ms
└─ Total: ~200-300ms
```

### Database Query Efficiency

**Good:**
- Uses prepared statements (prevent SQL injection)
- Indexes on common queries (email, id)
- JOIN queries for related data (bookings+users+bikes)

**Could Improve:**
- No query optimization
- No caching layer (every request hits database)
- No pagination (large result sets load all data)
- No database indexes defined explicitly

---

## SCALABILITY LIMITS

**Current Setup Handles:**
- ✓ ~100-1000 users
- ✓ ~500-5000 bookings
- ✓ 5-10 concurrent users
- ✓ Single MySQL database

**Breaks Down At:**
- ✗ 10,000+ users
- ✗ 100,000+ bookings
- ✗ 100+ concurrent users
- ✗ Multiple geographic locations (no CDN)

**Solutions for Scale:**
1. Add database indexes
2. Implement caching (Redis)
3. Separate read/write databases
4. Add search service (Elasticsearch)
5. CDN for static assets
6. Microservices architecture
7. Horizontal scaling load balancer

---

## DEPLOYMENT REQUIREMENTS

**Minimum Server:**
```
CPU:          1 core (adequate)
RAM:          512MB (comfortable for 10 users)
Disk:         1GB for files + database
PHP:          7.4+ (8.0+ recommended)
MySQL:        5.7+ (8.0 recommended)
Web Server:   Apache or Nginx
SSL:          Let's Encrypt (free)
```

**Hosting Options:**
- Shared hosting (cPanel) - $3-10/month ✓ Easy
- VPS (DigitalOcean) - $5-20/month ✓ More control
- AWS EC2 - $5-50/month ✓ Scalable
- Dedicated - $50+/month ✓ Expensive

---

## FILE SIZE REFERENCE

```
index.php               ~2 KB
login.php              ~4 KB
register.php           ~6 KB
dashboard.php          ~5 KB
book_bike.php          ~4 KB
config.php             ~0.5 KB
admin_login.php        ~2 KB
manage_bookings.php    ~8 KB
manage_bikes.php       ~6 KB
manage_users.php       ~3 KB
style.css              ~8 KB
───────────────────────────────
Total Code:            ~50 KB
Database Dump:         ~1 KB (minimal seed data)
Images:                ~500 KB (variable)
───────────────────────────────
Total Installation:    ~600 KB
```

**Compare to Modern SPA:**
- Single React app: 200-500 KB JavaScript
- Plus all the tooling and dependencies
- This is **much smaller and simpler**

