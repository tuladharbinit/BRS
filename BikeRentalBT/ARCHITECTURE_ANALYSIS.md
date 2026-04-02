# BikeRentalBT - Comprehensive Architectural Analysis

## Executive Summary
BikeRentalBT is a **traditional server-side rendered (SSR) monolithic PHP application** with direct database access. It follows a basic procedural/imperative programming model with minimal separation of concerns, no framework, and direct MySQLi queries throughout the application.

---

## 1. RENDERING ARCHITECTURE: Server-Side Rendered (SSR)

### Definition
All HTML is generated on the **server and sent to the client**. There is **NO client-side rendering framework** (no React, Vue, Angular, etc.).

### Evidence & Examples

#### Index.php - Server-Side Query & Rendering
```php
<?php include 'config.php'; ?>
<!DOCTYPE html>
<html>
  <!-- Frontend markup -->
  <section class="browse">
    <div class="container">
      <h2>Available Bikes</h2>
      <div class="bike-container">
      <?php
      // SERVER-SIDE QUERY: Database query happens on server
      $result = mysqli_query($conn, "SELECT * FROM bikes");
      
      // SERVER-SIDE RENDERING: HTML generated on server
      while($row = mysqli_fetch_assoc($result)){
      ?>
      <div class="bike-card">
        <img src="<?= htmlspecialchars($row['image']) ?>">
        <h3><?= htmlspecialchars($row['brand']) ?></h3>
        <p>NPR <?= htmlspecialchars($row['price']) ?> / day</p>
        <!-- Server decides link based on session -->
        <a class="btn" href="<?= isset($_SESSION['user']) ? "book_bike.php?bike={$bike_id}" : "login.php?bike={$bike_id}" ?>">
          Rent Now
        </a>
      </div>
      <?php } ?>
    </div>
  </section>
</html>
```

**Key Point**: 
- Page loads with full HTML from server
- No JavaScript needed to fetch data
- Client receives complete, styled HTML
- No API calls from frontend to fetch bike data

#### Dashboard.php - Complete Server-Side Stats Generation
```php
<?php
// server-side authentication check
if(!isset($_SESSION['user'])){
  header('Location: login.php');  // Server redirects if not logged in
  exit;
}

// Server-side stats calculation
$uid = (int)$u['id'];
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookings WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $uid);
mysqli_stmt_execute($stmt);
$total = mysqli_stmt_get_result($stmt);
$total = (int)mysqli_fetch_row($total)[0];  // Result computed server-side

// Server outputs pre-rendered stats to HTML
?>
<div class="stats-grid">
  <div class="card stat-card">
    <div class="num"><?= $total ?></div>  <!-- Already computed on server -->
    <div class="label">Total bookings</div>
  </div>
</div>
```

### Why This is SSR (Not CSR)
1. **All data fetching happens on server** - No JavaScript fetch/axios calls
2. **HTML is complete** - doesn't need JavaScript to render
3. **Navigation via HTTP requests** - not SPA routing
4. **Forms use traditional POST/GET** - standard HTTP form submission

---

## 2. API LAYER: Direct Database Queries (No API)

### Architecture
**Direct mysqli database access** - There is **NO separate API layer** (no REST, GraphQL, or microservices).

### Evidence & Examples

#### Direct Query Pattern in Multiple Files

**login.php** - Direct database query
```php
if(isset($_POST['login'])){
  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';

  // DIRECT DATABASE QUERY - No API intermediary
  $stmt = mysqli_prepare($conn, "SELECT id, password FROM users WHERE email = ?");
  mysqli_stmt_bind_param($stmt, 's', $email);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_bind_result($stmt, $uid, $hash);
  
  if(mysqli_stmt_fetch($stmt)){
    // Server verifies password directly
    if(password_verify($password, $hash)){
      session_regenerate_id(true);
      $_SESSION['user'] = $email;  // Set session server-side
      header("Location: dashboard.php");
    }
  }
}
```

**book_bike.php** - Direct data access
```php
// Gets user data directly from database
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $user);
mysqli_stmt_execute($stmt);
$u_result = mysqli_stmt_get_result($stmt);
$u = mysqli_fetch_assoc($u_result);

// Gets bike directly from database
$bike_stmt = mysqli_prepare($conn, "SELECT * FROM bikes WHERE id = ?");
mysqli_stmt_bind_param($bike_stmt, 'i', $bike_id);
mysqli_stmt_execute($bike_stmt);
$bike_result = mysqli_stmt_get_result($bike_stmt);
$bike = mysqli_fetch_assoc($bike_result);

// Direct write to database
if(isset($_POST['book'])){
  // ... validation code ...
  $pending_status = 'pending';
  $stmt = mysqli_prepare($conn, "INSERT INTO bookings(user_id,bike_id,date_from,date_to,status) VALUES(?,?,?,?,?)");
  mysqli_stmt_bind_param($stmt, 'iisss', $user_id, $bike_id, $from, $to, $pending_status);
  mysqli_stmt_execute($stmt);
}
```

**admin/manage_bookings.php** - Admin operations directly on DB
```php
if(isset($_POST['action'])){
  $id = (int)$_POST['booking_id'];
  $action = $_POST['action'];

  if($action === 'confirm'){
    // DIRECT DB UPDATE - No API layer
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
  }
  header('Location: manage_bookings.php');
}
```

### Comparison with API Architecture
**Without API (Current):**
```
Browser → PHP File → Direct MySQLi Query → Database
Browser ← PHP File (Full HTML Response)
```

**With REST API (Not used):**
```
Browser → JavaScript → REST API Endpoint → Database
Browser ← JSON Response → JavaScript renders HTML
```

### Implications
- All business logic is embedded in PHP files
- Database schema is tightly coupled to views
- Each request results in a full page reload
- No data-only response format (always HTML responses)

---

## 3. APPLICATION STYLE: Monolithic Single Codebase

### Definition
All functionality (auth, user management, bookings, admin features) exists in **a single PHP application** with no separation into microservices or separate deployed units.

### Monolithic Structure
```
BikeRentalBT/
├── Root Level (User-facing features)
│   ├── index.php                 → Home page & bike browsing
│   ├── login.php                 → User authentication
│   ├── register.php              → User registration
│   ├── dashboard.php             → User bookings dashboard
│   ├── book_bike.php             → Booking creation
│   ├── cancel_booking.php        → Booking cancellation
│   ├── change_password.php       → User password management
│   ├── logout.php                → Session termination
│
├── Admin Subdirectory (Admin features - same codebase)
│   ├── admin_login.php           → Admin authentication
│   ├── admin_dashboard.php       → Admin overview
│   ├── manage_bikes.php          → Bike CRUD operations
│   ├── manage_bookings.php       → Booking management
│   ├── manage_users.php          → User management
│   ├── admin_change_password.php → Admin password management
│
├── Shared Components
│   ├── config.php                → Database connection & CSRF functions
│   └── assets/ (CSS, images)
└── Data
    └── sql/bikerentalbt.sql      → Schema & initial data
```

### Evidence: All Features in Single App

User operations and admin operations are in the **same codebase**, accessing the **same database**:

```php
// config.php - SHARED across all files
$conn = mysqli_connect("localhost", "root", "", "bikerentalbt");

// SHARED CSRF protection function
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
```

**Both user and admin features:**
- Read from same 4 tables: `users`, `bikes`, `bookings`, `admin`
- Write to same database
- Share same session mechanism
- Share same CSRF protection

### NOT Microservices
There are **no separate services**, APIs, or independently deployable units:
- No separation between user and admin services
- No separate deployment artifacts
- No inter-service communication
- Single MySQL database for everything

---

## 4. FRAMEWORKS & LIBRARIES: None (Vanilla PHP)

### What's Used
- **Language**: Pure PHP (no framework)
- **Database**: MySQLi (built-in PHP library)
- **Frontend**: Plain HTML + CSS (no framework)
- **JavaScript**: Minimal, vanilla JavaScript (only for form validation)
- **Security**: Built-in functions (`password_hash`, `bin2hex`, `random_bytes`, `htmlspecialchars`, `filter_var`, `preg_match`)

### What's NOT Used
No ORM, no routing framework, no template engine, no build tools:

| Category | NOT Used | Why |
|----------|----------|-----|
| Web Framework | Laravel, Symfony, CodeIgniter, WordPress | Using raw PHP |
| ORM | Doctrine, Eloquent, Propel | Using raw MySQLi queries |
| Template Engine | Twig, Blade, Smarty | Using PHP as template language |
| JavaScript Framework | React, Vue, Angular | Pure SSR with vanilla JS |
| CSS Framework | Bootstrap, Tailwind, Foundation | Custom CSS in `style.css` |
| Build Tool | Webpack, Vite, Gulp | No build process |
| Package Manager | Composer | No dependencies |

### Code Example: Vanilla PHP Throughout

**register.php - Shows framework-free validation:**
```php
// No validation library - manual validation
if($name === '' || $email === '' || $password === '' || $contact === ''){
  $error = 'Please fill all required fields.';
} elseif(!preg_match('/^[a-zA-Z\s]+$/', $name)){
  $error = 'Name must not contain numbers.';
} elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
  $error = 'Invalid email address.';
} elseif(strlen($password) < 6){
  $error = 'Password must be at least 6 characters.';
} elseif(!preg_match('/^[0-9]{10}$/', $contact)){
  $error = 'Contact number must be exactly 10 digits.';
}

// No ORM - direct mysqli
$stmt = mysqli_prepare($conn, "INSERT INTO users (name,email,password,contact_number,document) VALUES (?,?,?,?,?)");
mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $hash, $contact, $docname);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

**Minimal JavaScript (login.php):**
```javascript
// Vanilla JavaScript - no library
(function(){
  var form = document.getElementById('loginForm');
  form.addEventListener('submit', function(e){
    var err = document.getElementById('login-error');
    err.style.display='none';
    err.textContent='';
    var email = document.getElementById('email').value.trim();
    var pass = document.getElementById('password').value;
    if(!email || !pass){
      err.textContent='Please enter email and password.';
      err.style.display='block';
      e.preventDefault();
    }
  });
})();
```

**No CSS Framework - Custom CSS (style.css):**
```css
/* All styling defined manually */
.bike-container {
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(250px,1fr));
  gap:20px;
  padding:20px;
}

.bike-card {
  background:#fff;
  padding:15px;
  border-radius:10px;
  box-shadow:0 4px 10px rgba(0,0,0,.1);
  text-align:center;
}

.btn {
  background:linear-gradient(90deg,#ff7a00,#ff4d00);
  color:white;
  padding:10px 16px;
  border-radius:10px;
  display:inline-block;
  border:none;
  font-weight:700;
}
```

---

## 5. REQUEST ROUTING & HANDLING

### Routing Method: File-Based
Each PHP file directly corresponds to a URL route. **No routing framework or URL rewriting** (no `.htaccess`).

### Routing Architecture
```
URL Pattern:          Maps To:                        Purpose:
─────────────────────────────────────────────────────────────
/index.php            → index.php                    Homepage
/login.php            → login.php                    User login
/register.php         → register.php                 User registration
/dashboard.php        → dashboard.php                User dashboard
/book_bike.php?bike=1 → book_bike.php               Create booking
/cancel_booking.php   → cancel_booking.php          Cancel booking
/change_password.php  → change_password.php         Change password
/logout.php           → logout.php                  Logout

/admin/admin_login.php           → admin_login.php          Admin login
/admin/admin_dashboard.php       → admin_dashboard.php      Admin dashboard
/admin/manage_bikes.php          → manage_bikes.php         Manage bikes
/admin/manage_bookings.php       → manage_bookings.php      Manage bookings
/admin/manage_users.php          → manage_users.php         User management
/admin/admin_change_password.php → admin_change_password.php Admin password
```

### Request Flow Example: Booking Creation

**Step 1: User navigates to bike page**
```php
// index.php generates link with bike_id
<a href="<?= isset($_SESSION['user']) ? "book_bike.php?bike={$bike_id}" : "login.php" ?>">
  Rent Now
</a>
```

**Step 2: book_bike.php processes the request**
```php
<?php
include 'config.php';

// 1. Extract from URL
if(!isset($_SESSION['user'])){
  header('Location: login.php');
  exit;
}

$bike_id = isset($_GET['bike']) ? (int)$_GET['bike'] : 0;

// 2. Handle POST form submission
if(isset($_POST['book'])){
  // Validate CSRF token
  if(!verify_csrf_token($_POST['csrf_token'])){
    $error = 'Security token invalid.';
  } else {
    // Extract form data
    $from = trim($_POST['from']);
    $to = trim($_POST['to']);
    
    // Validate dates
    if($from > $to){
      $error = 'The start date must be before or equal to the end date.';
    } else {
      // Check availability directly in database
      $conf = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookings 
                                    WHERE bike_id = ? AND status = ? 
                                    AND NOT (date_to < ? OR date_from > ?)");
      $confirmed_status = 'confirmed';
      mysqli_stmt_bind_param($conf, 'isss', $bike_id, $confirmed_status, $from, $to);
      mysqli_stmt_execute($conf);
      
      if($cnt > 0){
        $error = 'Selected bike is not available for those dates.';
      } else {
        // Insert into database
        $pending_status = 'pending';
        $stmt = mysqli_prepare($conn, "INSERT INTO bookings(user_id,bike_id,date_from,date_to,status) 
                                      VALUES(?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iisss', $user_id, $bike_id, $from, $to, $pending_status);
        mysqli_stmt_execute($stmt);
        
        // 3. Redirect on success
        header('Location: dashboard.php?book=success');
        exit;
      }
    }
  }
}

// 4. Render form HTML
?>
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <input type="date" name="from" required>
  <input type="date" name="to" required>
  <button name="book">Book Now</button>
</form>
```

### Request Handling Pattern
Every PHP file follows this pattern:

1. **Include config** for DB connection
2. **Check authorization** (session check)
3. **Process POST/GET** (if form submitted)
4. **Fetch data** (for display)
5. **Output HTML** (render view)

---

## 6. SEPARATION OF CONCERNS: Minimal MVC Pattern

### Current Structure: Mixed Concerns
**This is NOT a strict MVC architecture.** Each file mixes Model, View, and Controller logic.

### Example: book_bike.php (Everything in One File)

```php
<?php
include 'config.php';  // ← Shared connection

// ====== CONTROLLER LAYER ======
if(!isset($_SESSION['user'])){
  header('Location: login.php');
  exit;
}

$user = $_SESSION['user'];

// ====== MODEL LAYER (Business Logic) ======
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $user);
mysqli_stmt_execute($stmt);
$u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if(isset($_POST['book'])){
  // BUSINESS LOGIC: Validation
  if(!verify_csrf_token($_POST['csrf_token'])){
    $error = 'Security token invalid.';
  } else {
    $from = trim($_POST['from']);
    $to = trim($_POST['to']);
    
    if($from > $to){
      $error = 'Start date must be before end date.';
    } else {
      // BUSINESS LOGIC: Check availability
      $conf = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookings 
                                    WHERE bike_id = ? AND status = ? 
                                    AND NOT (date_to < ? OR date_from > ?)");
      // ... execute and check ...
      
      // BUSINESS LOGIC: Create booking
      $stmt = mysqli_prepare($conn, "INSERT INTO bookings(...) VALUES(...)");
      // ... execute ...
    }
  }
}

// ====== VIEW LAYER (HTML Output) ======
?>
<h2>Book: <?= htmlspecialchars($bike['brand']) ?></h2>
<?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="POST">
  <input type="date" name="from" required>
  <input type="date" name="to" required>
  <button name="book">Book Now</button>
</form>
```

### Comparison: MVC vs Current Structure

| Aspect | Ideal MVC | BikeRentalBT |
|--------|-----------|--------------|
| **Model** | Separate class/file | Inline queries in PHP files |
| **View** | Template file | HTML in same PHP file |
| **Controller** | Separate class | Script logic in same PHP file |
| **Data Access** | Through Model methods | Direct MySQLi in any file |
| **Business Logic** | In Model | Scattered across files |
| **Presentation** | In View | Mixed with logic in same file |

### What Separation Exists
1. **config.php** - Centralized database connection
2. **Root files** - User features
3. **admin/** - Admin features (loose organization)
4. **assets/** - CSS/images separated

But **no real separation** between business logic and presentation in individual files.

---

## 7. BUSINESS LOGIC LOCATION

### Where Business Logic Resides
**Embedded directly in PHP files** - No dedicated business logic layer.

### Examples of Business Logic in Different Files

#### Book Booking Logic (book_bike.php)
```php
// Date validation
if($from > $to){
  $error = 'The start date must be before or equal to the end date.';
}

// Availability check - BUSINESS LOGIC
$conf = mysqli_prepare($conn, "SELECT COUNT(*) FROM bookings 
                              WHERE bike_id = ? AND status = ? 
                              AND NOT (date_to < ? OR date_from > ?)");
$confirmed_status = 'confirmed';
mysqli_stmt_bind_param($conf, 'isss', $bike_id, $confirmed_status, $from, $to);
mysqli_stmt_execute($conf);
mysqli_stmt_bind_result($conf, $cnt);
mysqli_stmt_fetch($conf);

// Check for conflicts
if($cnt > 0){
  $error = 'Selected bike is not available for those dates.';
}
```

#### Price Calculation (manage_bookings.php)
```php
// Price calculation logic - part of admin display
$from = new DateTime($row['date_from']);
$to = new DateTime($row['date_to']);
$days = $from->diff($to)->days + 1;
$total = $days * (int)$row['price'];
echo 'NPR ' . number_format($total);
```

#### Authentication Logic (login.php)
```php
// Password verification logic
$ok = false;
if(password_verify($password, $hash)){
  $ok = true;
} elseif(strlen($hash) === 32 && md5($password) === $hash){
  // Legacy MD5 support
  $new = password_hash($password, PASSWORD_DEFAULT);
  // Migration logic
  $ok = true;
}

if($ok){
  session_regenerate_id(true);
  $_SESSION['user'] = $email;
}
```

#### Cancellation Logic (cancel_booking.php)
```php
// Business rule: Can't cancel after end date
if($status === 'cancelled'){
  header('Location: dashboard.php?cancel=already');
  exit;
}

if($date_to < date('Y-m-d')){
  // Can't cancel expired bookings
  header('Location: dashboard.php?cancel=expired');
  exit;
}

// Mark as cancelled
$u2 = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE id = ? AND user_id = ?");
$cancelled_status = 'cancelled';
mysqli_stmt_bind_param($u2, 'sii', $cancelled_status, $bid, $user_id);
mysqli_stmt_execute($u2);
```

### Business Logic Distribution
```
Core Business Logic scattered across:
├── Authentication:      login.php, admin_login.php
├── Registration:        register.php
├── Booking Creation:    book_bike.php
├── Booking Management:  manage_bookings.php, cancel_booking.php
├── User Management:     manage_users.php
├── Bike Management:     manage_bikes.php
├── Password/Access:     change_password.php, admin_change_password.php
└── Validation:          In each file (no shared validation service)
```

### Implication
- **High coupling** between views and business logic
- **Difficult to test** (unit tests require web context)
- **Code duplication** (validation rules repeated in multiple files)
- **Hard to reuse** logic across features

---

## 8. FRONTEND ARCHITECTURE

### Frontend Structure
Pure **HTML + CSS + Minimal Vanilla JavaScript** - No framework.

### Frontend Files
```
assets/
├── css/
│   └── style.css          → All styling for app
└── images/
    ├── hero.jpg           → Homepage hero background
    ├── bike1.jpg, bike2.jpg → Bike images
    └── Other images
```

### Frontend Pattern: SSR with Progressive Enhancement

#### 1. Server generates complete HTML
**index.php** serves complete bike listing HTML:
```html
<div class="bike-container">
  <?php while($row = mysqli_fetch_assoc($result)){ ?>
  <div class="bike-card">
    <img src="<?= htmlspecialchars($row['image']) ?>">
    <h3><?= htmlspecialchars($row['brand']) ?></h3>
    <p>NPR <?= htmlspecialchars($row['price']) ?> / day</p>
    <a class="btn" href="book_bike.php?bike=<?= $bike_id ?>">Rent Now</a>
  </div>
  <?php } ?>
</div>
```

#### 2. CSS provides styling
**style.css** - No framework, custom CSS Grid/Flexbox:
```css
.bike-container {
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(250px,1fr));
  gap:20px;
  padding:20px;
}

.bike-card {
  background:#fff;
  padding:15px;
  border-radius:10px;
  box-shadow:0 4px 10px rgba(0,0,0,.1);
  transition:transform 0.2s;
}

.bike-card:hover {
  transform:translateY(-4px);
}

.btn {
  background:linear-gradient(90deg,#ff7a00,#ff4d00);
  color:white;
  padding:10px 16px;
  border-radius:10px;
  cursor:pointer;
  border:none;
  font-weight:700;
}
```

#### 3. Minimal JavaScript for validation
**login.php** - Only client-side validation:
```javascript
(function(){
  var form = document.getElementById('loginForm');
  form.addEventListener('submit', function(e){
    var err = document.getElementById('login-error');
    err.style.display='none';
    err.textContent='';
    
    var email = document.getElementById('email').value.trim();
    var pass = document.getElementById('password').value;
    
    if(!email || !pass){
      err.textContent='Please enter email and password.';
      err.style.display='block';
      e.preventDefault();
    }
  });
})();
```

### Frontend Characteristics
| Aspect | Implementation |
|--------|-----------------|
| **Rendering** | Server-side (server generates HTML) |
| **Data Fetching** | Via form POST/GET (not AJAX) |
| **Interactivity** | Minimal (form validation only) |
| **Framework** | None (vanilla HTML/CSS/JS) |
| **Build Process** | None (files served as-is) |
| **Single Page App** | No (full page reloads on navigation) |
| **State Management** | Session-based (server-side) |
| **Browser API Usage** | Minimal (basic DOM manipulation) |

### No JavaScript Dependencies
- No jQuery, no React, no Vue, no Angular
- No npm packages
- No bundler (Webpack, Vite, etc.)
- No transpilation

---

## DATABASE ARCHITECTURE

### Schema Overview
```sql
Database: bikerentalbt

┌──────────────────────────────────────┐
│            Database Tables           │
├──────────────────────────────────────┤

Table: users
├─ id (INT, PK, AUTO_INCREMENT)
├─ name (VARCHAR 100)
├─ email (VARCHAR 100, UNIQUE)
├─ password (VARCHAR 255)
├─ contact_number (VARCHAR 20)
└─ document (VARCHAR 255) - Upload path

Table: bikes
├─ id (INT, PK, AUTO_INCREMENT)
├─ brand (VARCHAR 100)
├─ price (INT)
├─ image (VARCHAR 255)
└─ status (VARCHAR 50) DEFAULT 'available'

Table: bookings
├─ id (INT, PK, AUTO_INCREMENT)
├─ user_id (INT, FK → users.id)
├─ bike_id (INT, FK → bikes.id)
├─ date_from (DATE)
├─ date_to (DATE)
└─ status (VARCHAR 50) DEFAULT 'pending'

Table: admin
├─ id (INT, PK, AUTO_INCREMENT)
├─ username (VARCHAR 50)
└─ password (VARCHAR 255)

Relationships:
├─ users --< bookings (1-to-many)
├─ bikes --< bookings (1-to-many)
└─ NO direct relation to admin (separate authentication)
```

### MySQLi Direct Query Pattern
All queries use **prepared statements with parameterized binding**:

```php
// Example: User registration
$hash = password_hash($password, PASSWORD_DEFAULT);

// Parameterized query prevents SQL injection
$stmt = mysqli_prepare($conn, "INSERT INTO users (name,email,password,contact_number,document) 
                              VALUES (?,?,?,?,?)");

// Type-safe binding
mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $hash, $contact, $docname);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
```

---

## SECURITY FEATURES

### Implemented Security Measures

#### 1. CSRF Protection (Session-based tokens)
```php
// config.php - Token generation
function csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Usage in forms
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <!-- form fields -->
</form>

// Verification
if(!verify_csrf_token($_POST['csrf_token'])){
  $error = 'Security token invalid.';
}
```

#### 2. Password Security
- Uses `password_hash()` with bcrypt (PASSWORD_DEFAULT)
- Legacy MD5 detection with automatic bcrypt migration
- Minimum 6-character validation

```php
// Hashing
$hash = password_hash($password, PASSWORD_DEFAULT);

// Verification
if(password_verify($password, $hash)){
  // Correct password
}
```

#### 3. SQL Injection Prevention
All queries use prepared statements with parameterized binding:
```php
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $email);  // Type-safe
mysqli_stmt_execute($stmt);
```

#### 4. XSS Prevention
All user input output to HTML uses `htmlspecialchars()`:
```php
<div><?= htmlspecialchars($user_input) ?></div>
```

#### 5. Session Security
```php
// Session regeneration on login
session_regenerate_id(true);
$_SESSION['user'] = $email;
```

#### 6. File Upload Validation
```php
function saveImage($file){
  $allowed = ['image/jpeg','image/png','image/webp'];
  
  // MIME type check
  if(!in_array($file['type'], $allowed)) return null;
  
  // Size check
  if($file['size'] > 2 * 1024 * 1024) return null;
  
  // Safe filename generation
  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  $name = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  
  // Move to safe directory
  move_uploaded_file($file['tmp_name'], $dest);
}
```

#### 7. Input Validation
Multiple validation strategies:
```php
// Email validation
if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
  $error = 'Invalid email address.';
}

// Name validation (letters only)
if(!preg_match('/^[a-zA-Z\s]+$/', $name)){
  $error = 'Name must not contain numbers.';
}

// Phone number validation
if(!preg_match('/^[0-9]{10}$/', $contact)){
  $error = 'Contact number must be exactly 10 digits.';
}

// Date range validation
if($from > $to){
  $error = 'Start date must be before end date.';
}
```

---

## DEPLOYMENT & ENVIRONMENT

### Current Setup
```
Server:       XAMPP (Apache + PHP + MySQL)
Database:     MySQL (via MySQLi)
Host:         localhost
Database:     bikerentalbt
User:         root (default, no password)

Directory:    C:\xampp\htdocs\BikeRentalBT\
Web Root:     BikeRentalBT/
```

### File Locations
```
Project Root:     BikeRentalBT/
├── User features: Root level PHP files
├── Admin:         /admin/ subdirectory
├── Static assets: /assets/css/, /assets/images/
├── Uploads:       /uploads/ (user documents)
├── Database:      SQL schema in /sql/bikerentalbt.sql
└── Config:        config.php (database connection)
```

---

## ARCHITECTURAL SUMMARY TABLE

| Aspect | Implementation |
|--------|-----------------|
| **Rendering Model** | Server-Side Rendered (SSR) |
| **API Layer** | None - Direct database queries |
| **Application Style** | Monolithic single codebase |
| **Framework** | None (vanilla PHP) |
| **Web Framework** | None |
| **ORM** | None (MySQLi direct) |
| **Routing** | File-based (no routing framework) |
| **Frontend Framework** | None (HTML/CSS/vanilla JS) |
| **CSS Framework** | None (custom CSS) |
| **Template Engine** | PHP itself |
| **Separation of Concerns** | Minimal (mixed in files) |
| **Business Logic** | Embedded in PHP files |
| **Authentication** | Session-based with CSRF tokens |
| **Database** | MySQL with MySQLi |
| **Prepared Statements** | Yes (SQL injection prevention) |
| **JavaScript Usage** | Minimal (form validation only) |
| **Single Page App** | No (traditional multi-page) |
| **Build Process** | None |
| **Package Manager** | None (no dependencies) |

---

## DEVELOPMENT IMPLICATIONS

### Advantages of Current Architecture
1. **Simple deployment** - Just copy PHP files to web root
2. **Low infrastructure** - No build process, package managers
3. **Minimal dependencies** - Works with basic XAMPP
4. **Easy to understand** - Straightforward request/response cycle
5. **PHP-only skills needed** - No complex tooling

### Disadvantages & Technical Debt
1. **Poor separation of concerns** - Logic and presentation mixed
2. **Not unit testable** - Business logic tied to web layer
3. **Code duplication** - Validation logic repeated across files
4. **Tight coupling** - Views depend on specific DB schema
5. **Scalability issues** - Direct DB queries everywhere
6. **Difficult refactoring** - Changes impact multiple files
7. **Not scalable to microservices** - Monolithic design
8. **Limited reusability** - Logic can't be shared across projects
9. **No API** - Can't build mobile/desktop apps against this
10. **Manual file organization** - No framework conventions

### Future Improvement Path
```
Current: Vanilla PHP → Transition Options:
├── Step 1: Add simple routing (AltoRouter, FastRoute)
├── Step 2: Separate business logic to Model classes
├── Step 3: Use template engine (Twig, Blade)
├── Step 4: Implement basic MVC structure
└── Step 5: Migrate to framework (Laravel, Symfony)

Or alternative:
├── Rebuild as REST API (Laravel, Django, Node.js)
├── Build separate frontend (React, Vue, Angular)
└── Decouple database layer (Doctrine ORM)
```

---

## CONCLUSION

**BikeRentalBT is a classic server-side rendered monolithic PHP application** with:
- Direct database access (no API layer)
- Embedded business logic in view files
- Minimal separation of concerns
- No frameworks or modern tooling
- Traditional session-based authentication
- Vanilla HTML/CSS/JavaScript frontend
- File-based routing
- Decent security practices (CSRF, prepared statements, XSS prevention)

It represents a **traditional PHP web application model** suitable for small projects but would benefit from refactoring for larger-scale development.
