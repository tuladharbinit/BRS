# BikeRentalBT - Architecture Diagrams

## 1. REQUEST FLOW ARCHITECTURE (SSR Pattern)

```
┌─────────────────────────────────────────────────────────────────┐
│                     BROWSER / CLIENT                             │
│                                                                   │
│  1. User clicks "Book Bike" link                                 │
│     href="book_bike.php?bike=1"                                  │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ HTTP GET /book_bike.php?bike=1
                         │
┌────────────────────────▼────────────────────────────────────────┐
│                    WEB SERVER (Apache)                           │
│                                                                   │
│  Routes request to: book_bike.php                               │
└────────┬──────────────────────────────────────────────┬─────────┘
         │                                               │
         │ 1. Include config.php                        │
         │ 2. Check authorization                       │
         │                                               │
┌────────▼──────────────────────────────────────────────▼─────────┐
│                 PHP SCRIPT EXECUTION                             │
│                    (book_bike.php)                               │
│                                                                   │
│  ┌──────────────────────────────────────────────────┐            │
│  │ CONTROLLER LOGIC (Request handling)              │            │
│  ├──────────────────────────────────────────────────┤            │
│  │ - Check if user authenticated                   │            │
│  │ - Extract GET parameters (bike_id)              │            │
│  │ - Process POST data (if form submitted)         │            │
│  │ - Validate CSRF token                           │            │
│  └────────┬─────────────────────────────────────────┘            │
│           │                                                       │
│  ┌────────▼──────────────────────────────────────────┐            │
│  │ MODEL LOGIC (Business logic)                     │            │
│  ├────────────────────────────────────────────────────┤            │
│  │ - Validate form inputs                          │            │
│  │ - Check bike availability                       │            │
│  │ - Create booking record                         │            │
│  │ - Calculate pricing                             │            │
│  └────────┬───────────────────────────────────────────┘            │
│           │                                                       │
│  ┌────────▼──────────────────────────────────────────┐            │
│  │ VIEW LOGIC (HTML generation)                    │            │
│  ├────────────────────────────────────────────────────┤            │
│  │ - Generate HTML form                            │            │
│  │ - Display bike details                          │            │
│  │ - Render error/success messages                 │            │
│  │ - Return complete HTML page                     │            │
│  └────────┬───────────────────────────────────────────┘            │
└───────────┼─────────────────────────────────────────────────────┘
            │
            │ Database Queries
            │ (prepare, bind, execute)
            │
┌───────────▼─────────────────────────────────────────────────────┐
│                    MySQL DATABASE                                │
│                                                                   │
│  SELECT * FROM users WHERE email = ?                            │
│  SELECT * FROM bikes WHERE id = ?                               │
│  SELECT COUNT(*) FROM bookings WHERE bike_id = ? ...            │
│  INSERT INTO bookings(user_id, bike_id, ...) VALUES(?, ...)     │
│                                                                   │
│  ┌────────────────────────────┐                                   │
│  │ Tables:                    │                                   │
│  ├────────────────────────────┤                                   │
│  │ - users (20 rows)         │                                   │
│  │ - bikes (5 rows)          │                                   │
│  │ - bookings (40 rows)      │                                   │
│  │ - admin (1 row)           │                                   │
│  └────────────────────────────┘                                   │
└────────────────────────────────────────────────────────────────┘
            │
            │ Result set
            │
┌───────────▼──────────────────────────────────────────────────────┐
│              PHP SCRIPT (Continued)                               │
│                                                                   │
│  - Fetch rows into PHP variables                                │
│  - Build HTML with embedded PHP                                 │
│  - Generate complete page with all content                      │
│                                                                   │
│  <?php while($row = $result->fetch()) { ?>                      │
│    <div><?= htmlspecialchars($row['brand']) ?></div>           │
│  <?php } ?>                                                      │
└───────────┬──────────────────────────────────────────────────────┘
            │
            │ Complete HTML Response
            │ (200 lines of HTML)
            │
┌───────────▼──────────────────────────────────────────────────────┐
│                     WEB SERVER                                     │
│                                                                   │
│  Sends Response:                                                 │
│  HTTP/1.1 200 OK                                                │
│  Content-Type: text/html; charset=UTF-8                        │
│  Content-Length: 5234                                          │
│                                                                   │
│  <!DOCTYPE html>                                                 │
│  <html>                                                          │
│  <body>                                                          │
│  <h2>Book Bike: Yamaha FZ</h2>                                 │
│  <form method="POST">                                           │
│    <input type="date" name="from">                             │
│    <input type="date" name="to">                               │
│    <button>Book Now</button>                                   │
│  </form>                                                        │
│  </body>                                                         │
│  </html>                                                         │
└───────────┬──────────────────────────────────────────────────────┘
            │
            │ HTTP Response
            │
┌───────────▼──────────────────────────────────────────────────────┐
│                    BROWSER                                        │
│                                                                   │
│  3. Receives complete HTML                                       │
│  4. Parses HTML                                                 │
│  5. Loads CSS from style.css                                    │
│  6. Renders page                                                │
│  7. User sees booking form ready to use                        │
│                                                                   │
│  ╔════════════════════════════════════╗                         │
│  ║ Book: Yamaha FZ                    ║                         │
│  ║ Price per day: NPR 2000            ║                         │
│  ║ [From date input field]            ║                         │
│  ║ [To date input field]              ║                         │
│  ║ [Book Now button]                  ║                         │
│  ╚════════════════════════════════════╝                         │
│                                                                   │
│  8. User fills form and clicks "Book Now"                      │
│     Browser submits POST request to book_bike.php              │
└───────────┬──────────────────────────────────────────────────────┘
            │
            └─────────► Cycle repeats with new POST data
```

---

## 2. APPLICATION STRUCTURE (Monolithic)

```
Bike Rental System (Single Codebase)
│
├─── PUBLIC / USER LAYER (Root directory)
│    ├─ index.php                 ← Homepage & bike browsing
│    ├─ login.php                 ← User authentication
│    ├─ register.php              ← New user registration
│    ├─ dashboard.php             ← User's bookings & profile
│    ├─ book_bike.php             ← Create new booking
│    ├─ cancel_booking.php        ← Cancel existing booking
│    ├─ change_password.php       ← User password change
│    └─ logout.php                ← Session termination
│
├─── ADMIN LAYER (/admin subdirectory)
│    ├─ admin_login.php           ← Admin authentication
│    ├─ admin_dashboard.php       ← Admin overview & stats
│    ├─ manage_bikes.php          ← Add/Edit/Delete bikes
│    ├─ manage_bookings.php       ← Approve/Deny/Cancel bookings
│    ├─ manage_users.php          ← View/Delete users
│    └─ admin_change_password.php ← Admin password change
│
├─── CONFIGURATION
│    └─ config.php                ← Database connection + CSRF functions
│
├─── STATIC ASSETS (/assets)
│    ├─ css/
│    │  └─ style.css              ← All styling (no framework)
│    └─ images/
│       ├─ hero.jpg               ← Homepage background
│       ├─ bike1.jpg, bike2.jpg   ← Bike images
│       └─ harlay.jpg             ← Login background
│
├─── USER UPLOADS (/uploads)
│    └─ [Timestamp]_[hash].[ext]  ← User documents (PDF/JPEG/PNG)
│
└─── DATABASE (/sql)
     └─ bikerentalbt.sql          ← Initial schema & seed data

ALL FILES SHARE:
├─ Same database (bikerentalbt)
├─ Same session mechanism
├─ Same CSRF protection
└─ Same MySQLi connection
```

---

## 3. DATA FLOW (Direct Database Access - No API)

```
SCENARIO: User wants to book a bike

WITHOUT API (Current Implementation):
═══════════════════════════════════════════════════════════════

User Browser                PHP Application              MySQL Database
   │                              │                           │
   │─── Click "Book" ─────────────>│                           │
   │                               │                           │
   │                   book_bike.php executes:               │
   │                               │                           │
   │                               │──── SELECT * FROM users ─>│
   │                               │<─── User data ────────────│
   │                               │                           │
   │                               │──── SELECT * FROM bikes ──>│
   │                               │<─── Bike data ────────────│
   │                               │                           │
   │                               │ Validate dates locally    │
   │                               │                           │
   │                               │──── SELECT COUNT(*) FROM ─>│
   │                               │      bookings WHERE ... ───│
   │                               │<─── Count = 0 ────────────│
   │                               │                           │
   │                               │──── INSERT INTO bookings ─>│
   │                               │<─── Success ──────────────│
   │                               │                           │
   │<─────── Complete HTML Page ───│                           │
   │                               │                           │
   Display confirmation            │                           │


WITH API (Alternative Architecture - NOT Used):
═══════════════════════════════════════════════════════════════

User Browser      API Endpoint          PHP Application      MySQL Database
   │                   │                       │                    │
   │─ POST JSON ──────>│                       │                    │
   │                   │──── Validate ────────>│                    │
   │                   │<─── OK ───────────────│                    │
   │                   │                       │──── INSERT ────────>│
   │                   │                       │<──── Success ───────│
   │                   │<──── Return ID ───────│                    │
   │<─── Return JSON ──│                       │                    │
   │                   │                       │                    │
   JavaScript renders form
```

---

## 4. DATABASE SCHEMA

```
bikerentalbt
│
├── TABLE: users
│   ├─ id (INT, PK, AUTO_INCREMENT)
│   ├─ name (VARCHAR 100)
│   ├─ email (VARCHAR 100, UNIQUE)  ← Login identifier
│   ├─ password (VARCHAR 255)        ← bcrypt hash
│   ├─ contact_number (VARCHAR 20)
│   └─ document (VARCHAR 255)        ← Path to uploaded file
│
├── TABLE: bikes
│   ├─ id (INT, PK, AUTO_INCREMENT)
│   ├─ brand (VARCHAR 100)           ← "Yamaha FZ", "Honda Shine"
│   ├─ price (INT)                   ← Daily rental price (NPR)
│   ├─ image (VARCHAR 255)           ← Path to bike image
│   └─ status (VARCHAR 50)           ← "available" (not actively used)
│
├── TABLE: bookings
│   ├─ id (INT, PK, AUTO_INCREMENT)
│   ├─ user_id (INT, FK → users.id) ─────┐
│   ├─ bike_id (INT, FK → bikes.id) ─────┐
│   ├─ date_from (DATE)               Rental dates
│   ├─ date_to (DATE)                 │
│   └─ status (VARCHAR 50)        ─────┘ "pending" | "confirmed" | "denied" | "cancelled"
│
└── TABLE: admin
    ├─ id (INT, PK, AUTO_INCREMENT)
    ├─ username (VARCHAR 50)         ← "admin"
    └─ password (VARCHAR 255)        ← bcrypt hash (upgraded from MD5)

RELATIONSHIPS:
═════════════════════════════════════════

users (1) ──────────< (many) bookings
  id                        user_id

bikes (1) ──────────< (many) bookings
  id                        bike_id

admin (separate table - no FK relationship)
  ← Isolated from users, uses different authentication
```

---

## 5. SESSION & AUTHENTICATION FLOW

```
USER AUTHENTICATION
════════════════════════════════════════════════════════

┌─ Browser ─────────────────────────────────────────┐
│                                                    │
│ 1. User enters email/password                   │
│ 2. Submit: POST /login.php                      │
│ {email: "user@example.com", password: "..."}   │
│                                                    │
└──────────────────┬─────────────────────────────────┘
                   │
        ┌──────────▼──────────┐
        │   login.php         │
        │                    │
        │ if POST login:      │
        │  - Get email        │
        │  - Get password     │
        └──────────┬──────────┘
                   │
        ┌──────────▼──────────────────────────┐
        │ Query: SELECT password FROM users   │
        │        WHERE email = ?              │
        │                                     │
        │ Get hash from database              │
        └──────────┬──────────┬───────────────┘
                   │          │
         ┌─────────▼──┐  ┌────▼──────────┐
         │   Compare  │  │ password_verify│
         │  password  │  │    function    │
         └─────────┬──┘  └────┬──────────┘
                   │          │
              Match? ─────┬────
                      │
            ┌─────────▼──────────┐
            │ password_verify()   │
            │ succeeds: YES ✓     │
            └─────────┬──────────┘
                      │
            ┌─────────▼──────────────────────┐
            │ 1. session_regenerate_id(true) │
            │    (Prevent session fixation)  │
            │                                │
            │ 2. $_SESSION['user'] =         │
            │    'user@example.com'          │
            │                                │
            │ 3. header('Location: ...')     │
            │    Redirect to dashboard       │
            └─────────┬──────────────────────┘
                      │
            ┌─────────▼──────────────────┐
            │ Browser receives 302        │
            │ Redirect to dashboard.php   │
            └─────────┬──────────────────┘
                      │
            ┌─────────▼──────────────────┐
            │ Browser requests:           │
            │ GET /dashboard.php          │
            │ (with session cookie)       │
            └─────────┬──────────────────┘
                      │
            ┌─────────▼──────────────────────┐
            │ dashboard.php                  │
            │                                │
            │ Check: if(!isset(              │
            │ $_SESSION['user'])) {          │
            │   header('Location: login')    │
            │ }                              │
            │                                │
            │ $user = $_SESSION['user']     │
            │ Query database for user data   │
            │ Generate personalized page    │
            └─────────┬──────────────────────┘
                      │
            ┌─────────▼──────────────────┐
            │ Send HTML to browser        │
            │ with Set-Cookie header      │
            │ (session cookie)            │
            └─────────┬──────────────────┘
                      │
        ┌─────────────▼─────────────────┐
        │ Browser                       │
        │                              │
        │ Receives page                │
        │ Stores session cookie        │
        │ Shows: "Welcome, [name]"    │
        │                              │
        └──────────────────────────────┘

FOR SUBSEQUENT REQUESTS
═════════════════════════════════════════════════════

Browser Include Session Cookie:
  GET /dashboard.php
  Cookie: PHPSESSID=abc123def456...

  ↓

PHP retrieves session data from server storage
  $_SESSION['user'] is available
  User is authenticated


LOGOUT FLOW
═════════════════════════════════════════════════════

1. User clicks "Logout"
2. POST /logout.php
3. PHP destroys session:
   - $_SESSION = []
   - session_destroy()
   - Delete session cookie
4. Redirect to index.php
5. Next request, $_SESSION['user'] not set
6. User redirected to login.php
```

---

## 6. ROUTING MECHANISM (File-Based)

```
URL REQUEST TO FILE MAPPING
════════════════════════════════════════════════

REQUEST URL                              ROUTES TO              PURPOSE
────────────────────────────────────────────────────────────────────────

/index.php                              → index.php            ├─ Homepage
/                           (default)   → index.php            │ Browse bikes

/login.php                              → login.php            ├─ Login page
                                         (POST: authenticate)  │ Handle login
                                         
/register.php                           → register.php         ├─ Registration
                                         (POST: create user)   │

/dashboard.php                          → dashboard.php        ├─ User dashboard
                                                               │

/book_bike.php?bike=1                   → book_bike.php        ├─ Booking form
                                         (GET: show form)      │ (with bike_id)
                                         (POST: create booking)│

/cancel_booking.php                     → cancel_booking.php   ├─ Cancel booking
                                         (POST: mark cancelled) │

/change_password.php                    → change_password.php  ├─ Password change

/logout.php                             → logout.php           ├─ Logout


/admin/admin_login.php                  → admin_login.php      ├─ Admin login

/admin/admin_dashboard.php              → admin_dashboard.php  ├─ Admin dashboard
                                                               │ (stats/overview)

/admin/manage_bikes.php                 → manage_bikes.php     ├─ Bike management
                                         (GET: show list)      │
                                         (GET ?edit=1: edit)   │
                                         (POST: add/edit)      │
                                         (GET ?delete=1)       │

/admin/manage_bookings.php              → manage_bookings.php  ├─ Booking management
                                         (GET: show list)      │
                                         (POST: confirm/deny)  │

/admin/manage_users.php                 → manage_users.php     ├─ User management
                                         (GET: show list)      │
                                         (GET ?delete=1)       │

/admin/admin_change_password.php        → admin_change_pass... ├─ Admin password


HOW FILE-BASED ROUTING WORKS
══════════════════════════════════════════════════════════════════

1. User clicks link: href="book_bike.php?bike=1"
   
2. Browser makes HTTP request:
   GET /book_bike.php?bike=1
   
3. Web server (Apache) receives request:
   - Looks for file: /var/www/html/BikeRentalBT/book_bike.php
   - File exists? YES
   - Send file to PHP interpreter
   
4. PHP executes book_bike.php:
   - Access URL params: $_GET['bike']
   - Execute PHP code
   - Output HTML
   
5. Browser receives HTML response

NO URL REWRITING (no .htaccess):
═════════════════════════════════════════════════════

Unlike modern frameworks that use URL rewriting:
  /books/rent/1     → rewritten to → /api.php?action=books&id=1
  
This app uses direct file mapping:
  /book_bike.php?bike=1  → directly executes → /book_bike.php


FORM SUBMISSION (POST vs GET)
═════════════════════════════════════════════════════

GET METHOD (Data in URL):
  <a href="book_bike.php?bike=1">  ← Data visible in URL
  GET /book_bike.php?bike=1
  
POST METHOD (Data in body):
  <form method="POST">             ← Data hidden in request body
  POST /book_bike.php
  Body: {from: "2024-01", to: "2024-02", csrf_token: "..."}
```

---

## 7. SEPARATION OF CONCERNS (Low)

```
IDEAL MVC ARCHITECTURE
═════════════════════════════════════════════════════

  ┌─────────────────────────┐
  │   REQUEST (URL)         │
  └────────────┬────────────┘
               │
        ┌──────▼──────┐
        │  ROUTER     │  Routes URL to controller
        └──────┬──────┘
               │
        ┌──────▼──────────┐
        │  CONTROLLER     │  Handles request logic
        │  - Validate     │  - Determine what data needed
        │  - Coordinate   │  - Call model methods
        └──────┬──────────┘
               │
        ┌──────▼──────────┐
        │  MODEL          │  Business logic layer
        │  - Fetch data   │  - Validation rules
        │  - Save data    │  - Calculations
        │  - Rules        │  - Database queries
        └──────┬──────────┘
               │
        ┌──────▼──────────┐
        │  DATABASE       │  Persistent storage
        └──────┬──────────┘
               │
        ┌──────▼──────────┐
        │  Result objects │  Plain data
        └──────┬──────────┘
               │
        ┌──────▼──────────┐
        │  TEMPLATE/VIEW  │  Render presentation
        │  - HTML markup  │  - Client sees this
        │  - CSS styling  │
        └──────┬──────────┘
               │
        ┌──────▼──────────┐
        │  RESPONSE       │
        │  HTML to browser│
        └─────────────────┘


ACTUAL BIKERENTALBT ARCHITECTURE
═════════════════════════════════════════════════════

  ┌─────────────────────────┐
  │   REQUEST (URL)         │
  └────────────┬────────────┘
               │
        ┌──────▼──────────────────────────────┐
        │  PHP FILE (e.g., book_bike.php)    │
        │                                     │
        │  ┌──────────────────────────────┐  │
        │  │ CONTROLLER LOGIC             │  │
        │  │ - Check session              │  │
        │  │ - Extract $_GET/$_POST       │  │
        │  │ - Validate CSRF token        │  │
        │  └──────────┬───────────────────┘  │
        │             │                       │
        │  ┌──────────▼───────────────────┐  │
        │  │ MODEL LOGIC (MIXED IN)       │  │
        │  │ - Validate inputs            │  │
        │  │ - Business rules             │  │
        │  │ - Database queries directly  │  │
        │  │   $stmt = mysqli_prepare...  │  │
        │  │   mysqli_stmt_execute...     │  │
        │  └──────────┬───────────────────┘  │
        │             │                       │
        │  ┌──────────▼───────────────────┐  │
        │  │ VIEW LOGIC (MIXED IN)        │  │
        │  │ ?> HTML generation <?php    │  │
        │  │ - Echo variables             │  │
        │  │ - Loop through results       │  │
        │  │ - Generate form markup       │  │
        │  └──────────┬───────────────────┘  │
        │             │                       │
        └─────────────┼───────────────────────┘
                      │
        ┌─────────────▼───────────────┐
        │    DATABASE                 │
        │    (All queries here)       │
        └─────────────┬───────────────┘
                      │
        ┌─────────────▼───────────────┐
        │  RESPONSE                   │
        │  Complete HTML              │
        └─────────────────────────────┘


CONSEQUENCE: Everything Mixed in One File
═══════════════════════════════════════════════════════════════

┌─ book_bike.php ──────────────────────────────────────────┐
│                                                             │
│ <?php                                                      │
│   include 'config.php';                                   │
│                                                             │
│   // ===== CONTROLLER =====                              │
│   if(!isset($_SESSION['user'])) header('Location: ...');│
│   $bike_id = isset($_GET['bike']) ? (int)$_GET['bike']   │
│                                                             │
│   // ===== MODEL (Business Logic) =====                 │
│   if(isset($_POST['book'])){                             │
│     if(!verify_csrf_token($_POST['csrf_token'])){        │
│       $error = 'Invalid token';                         │
│     } else {                                             │
│       $from = trim($_POST['from']);                      │
│       if(strlen($from) === 0) $error = 'Empty date';    │
│                                                             │
│       // Database query for availability                 │
│       $conf = mysqli_prepare($conn, "SELECT COUNT(...)");│
│       mysqli_stmt_execute($conf);                         │
│       // Check results and business rules               │
│     }                                                     │
│   }                                                        │
│                                                             │
│   // ===== VIEW (HTML Output) =====                      │
│ ?>                                                         │
│ <!DOCTYPE html>                                          │
│ <h2>Book: <?= htmlspecialchars($bike['brand']) ?></h2> │
│ <form method="POST">                                     │
│   <input type="date" name="from" required>              │
│   <button name="book">Book Now</button>                 │
│ </form>                                                  │
│                                                             │
└──────────────────────────────────────────────────────────┘

EVERYTHING IS MIXED: Request handling → Business logic → View
```

---

## 8. SECURITY ARCHITECTURE

```
SECURITY LAYERS IMPLEMENTED
════════════════════════════════════════════════════════════

┌─────────────────────────────────────────────────────────┐
│ 1. SESSION SECURITY                                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ On login:                                              │
│   ├─ session_regenerate_id(true)  ← Prevent fixation  │
│   │  Generates new session ID                          │
│   ├─ $_SESSION['user'] = $email   ← Store user         │
│   └─ Browser gets Set-Cookie:     ← HttpOnly cookie    │
│      PHPSESSID=abc123; HttpOnly; Secure; SameSite=Lax │
│                                                         │
│ On subsequent requests:                                │
│   ├─ Browser sends: Cookie: PHPSESSID=abc123          │
│   ├─ PHP retrieves session from file storage           │
│   ├─ Check: if(!isset($_SESSION['user'])) exit;       │
│   └─ User is authenticated                             │
│                                                         │
│ On logout:                                             │
│   ├─ $_SESSION = [] (clear all)                        │
│   ├─ session_destroy()                                 │
│   ├─ Delete session cookie                             │
│   └─ Redirect to index.php                             │
│                                                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ 2. CSRF (Cross-Site Request Forgery) PROTECTION        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ In config.php (shared across all files):               │
│                                                         │
│   function csrf_token() {                              │
│     if(!isset($_SESSION['csrf_token'])) {              │
│       $_SESSION['csrf_token'] =                         │
│         bin2hex(random_bytes(32));                      │
│     }                                                   │
│     return $_SESSION['csrf_token'];                     │
│   }                                                      │
│                                                         │
│   function verify_csrf_token($token) {                 │
│     return isset($_SESSION['csrf_token']) &&           │
│            hash_equals(                                │
│              $_SESSION['csrf_token'], $token           │
│            );                                           │
│   }                                                      │
│                                                         │
│ In forms (all POST forms have token):                  │
│   <form method="POST">                                 │
│     <input type="hidden" name="csrf_token"             │
│            value="<?= csrf_token() ?>">               │
│     <!-- form fields -->                               │
│   </form>                                               │
│                                                         │
│ Verification on submit:                               │
│   if(!verify_csrf_token($_POST['csrf_token'])){        │
│     die('Security token invalid');                     │
│   }                                                     │
│                                                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ 3. SQL INJECTION PREVENTION                             │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ALL database queries use prepared statements:          │
│                                                         │
│   DANGEROUS (not used):                                │
│     $sql = "SELECT * FROM users WHERE email = '"       │
│             . $_POST['email'] . "'";                   │
│     mysqli_query($conn, $sql);  ← SQL injection risk   │
│                                                         │
│   SAFE (what's used):                                  │
│     $stmt = mysqli_prepare($conn,                      │
│       "SELECT * FROM users WHERE email = ?");          │
│                         ↑ Placeholder                  │
│     mysqli_stmt_bind_param($stmt, 's', $email);       │
│                             ↑ Type ('s'=string)        │
│     mysqli_stmt_execute($stmt);                        │
│                                                         │
│ Parameters bound separately from SQL code              │
│ Database never treats user input as SQL               │
│                                                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ 4. XSS (Cross-Site Scripting) PREVENTION                │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ DANGEROUS (not used):                                  │
│   <?= $user_input ?>              ← User code executed │
│   <h1>Hi <?= $_POST['name'] ?></h1>                   │
│        Attacker inputs: <script>alert('xss')</script> │
│                                                         │
│ SAFE (what's used):                                    │
│   <?= htmlspecialchars($user_input) ?>                │
│   Converts:                                            │
│   < becomes &lt;                                       │
│   > becomes &gt;                                       │
│   " becomes &quot;                                     │
│   & becomes &amp;                                      │
│                                                         │
│ Used for ALL user output:                              │
│   - Names: htmlspecialchars($u['name'])               │
│   - Emails: htmlspecialchars($u['email'])             │
│   - Submitted data: htmlspecialchars($error)          │
│   - Bike info: htmlspecialchars($row['brand'])        │
│                                                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ 5. PASSWORD SECURITY                                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Storage:                                               │
│   $hash = password_hash($password, PASSWORD_DEFAULT); │
│           ↓                                             │
│   Uses bcrypt algorithm (salt + hash automatically)   │
│   Stored as 60-character hash in database              │
│                                                         │
│ Verification:                                          │
│   if(password_verify($user_password, $hash)) {        │
│     // Password correct                                │
│   }                                                     │
│                                                         │
│ Legacy support:                                        │
│   // Old MD5 hashes still verified                     │
│   if(strlen($hash) === 32 && md5($pwd) === $hash){    │
│     // Then upgrade to bcrypt                          │
│     $new_hash = password_hash($pwd, PASSWORD_DEFAULT);│
│     // Store new hash in database                      │
│   }                                                     │
│                                                         │
│ Validation:                                            │
│   - Minimum 6 characters required                      │
│   - Must confirm (password_2 = password_confirm)       │
│                                                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ 6. FILE UPLOAD SECURITY                                 │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ function saveImage($file) {                            │
│   // 1. Check MIME type                               │
│   $allowed = ['image/jpeg','image/png','image/webp']; │
│   if(!in_array($file['type'], $allowed))              │
│     return null;                                       │
│                                                         │
│   // 2. Check file size                               │
│   if($file['size'] > 2 * 1024 * 1024)  // 2MB        │
│     return null;                                       │
│                                                         │
│   // 3. Generate safe filename                        │
│   $ext = pathinfo($file['name'], PATHINFO_EXTENSION);│
│   $name = time() . '_' . bin2hex(random_bytes(6))     │
│            . '.' . $ext;                               │
│                                                         │
│   // 4. Move to safe directory                        │
│   $dest = __DIR__ . '/uploads/' . $name;              │
│   move_uploaded_file($file['tmp_name'], $dest);       │
│                                                         │
│   return 'uploads/' . $name;                          │
│ }                                                       │
│                                                         │
│ Uploaded files stored in /uploads/ (outside web root) │
│ Named with timestamp + random hash to prevent guessing │
│                                                         │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ 7. INPUT VALIDATION                                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Email:                                                 │
│   filter_var($email, FILTER_VALIDATE_EMAIL)          │
│                                                         │
│ Names (letters only):                                  │
│   preg_match('/^[a-zA-Z\s]+$/', $name)               │
│                                                         │
│ Phone (10 digits):                                     │
│   preg_match('/^[0-9]{10}$/', $contact)              │
│                                                         │
│ Passwords:                                             │
│   strlen($password) >= 6                               │
│   Password confirmation matches                        │
│   Updated via password_verify() check                  │
│                                                         │
│ Dates:                                                 │
│   if($date_from > $date_to) error                      │
│   Check against database for conflicts                 │
│                                                         │
│ Numbers (user_id, bike_id):                           │
│   $id = (int)$_GET['id']                              │
│   Forces integer type (SQL injection prevention)       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 9. COMPARISON: CSR vs SSR

```
THIS SYSTEM (SSR) vs MODERN SPA (CSR)
═════════════════════════════════════════════════════════════

REQUEST FOR "Book Bike" PAGE
════════════════════════════════════════════════════════════

SERVER-SIDE RENDERED (BikeRentalBT - Current)
──────────────────────────────────────────────────────────

  1. Browser requests:
     GET /book_bike.php?bike=1

  2. Server processes:
     - Validates session
     - Queries database (SELECT users, bikes, bookings)
     - Renders HTML structure:
       <h2>Book: [bike_brand]</h2>
       <form>
         <input type="date" name="from">
         <input type="date" name="to">
         <p>Price: [calculated_price]/day</p>
       </form>

  3. Server sends response:
     STATUS: 200 OK
     BODY: Complete HTML (with CSS inline/linked)
     SIZE: ~5-10 KB of HTML

  4. Browser:
     - Parses HTML
     - Loads CSS
     - Renders page (VISIBLE)
     - Ready for user to book

  5. Form submission:
     POST /book_bike.php
     {from: "2024-01-01", to: "2024-01-05", csrf_token: "..."}

  6. Server:
     - Validates form
     - Checks availability (DB query)
     - Creates booking
     - Returns new page OR redirect

  ADVANTAGES:
  ✓ Simple to build and understand
  ✓ Server controls all logic
  ✓ Works without JavaScript
  ✓ Fast initial page load
  ✓ SEO friendly (complete HTML)
  
  DISADVANTAGES:
  ✗ Full page reload on every action
  ✗ No client-side interactivity
  ✗ Can't validate before submit
  ✗ No dynamic content updates
  ✗ Form submission shows loading
  ✗ Can't detect availability in real-time


CLIENT-SIDE RENDERED (Modern SPA - Not Used)
──────────────────────────────────────────────────────────

  1. Browser requests:
     GET /app.html

  2. Server sends:
     - Minimal HTML shell
     - JavaScript bundles (React, Vue, etc.)
     SIZE: ~100-200 KB of JS

  3. Browser:
     - Parses HTML (minimal)
     - Executes JavaScript
     - JavaScript initializes app

  4. JavaScript makes API request:
     GET /api/bikes/1
     {Accept: "application/json"}

  5. Server responds with JSON:
     {"id": 1, "brand": "Yamaha", "price": 2000, ...}

  6. JavaScript renders:
     - Creates DOM elements
     - Updates DOM with JSON data
     - Sets up event listeners

  7. Page becomes interactive (JavaScript controls everything)

  8. User fills form:
     - JavaScript validates on keypress
     - Real-time availability check (API calls)
     - Smooth UX

  9. Form submission:
     POST /api/bookings
     {
       bike_id: 1,
       date_from: "2024-01-01",
       date_to: "2024-01-05"
     }

  10. Server returns JSON:
      {success: true, booking_id: 42}

  11. JavaScript updates UI (no page reload):
      - Shows success message (animated)
      - Redirects to bookings page
      - No flicker, smooth transition

  ADVANTAGES:
  ✓ Smooth, fast interactions
  ✓ Real-time validation & feedback
  ✓ Desktop app-like experience
  ✓ No page flicker
  ✓ Can work offline (with caching)
  ✓ Decoupled frontend/backend
  ✓ Can build mobile apps against same API
  
  DISADVANTAGES:
  ✗ Large initial JavaScript download
  ✗ Requires JavaScript to function
  ✗ More complex development
  ✗ SEO challenges (JS rendering)
  ✗ More state to manage
  ✗ Testing more complicated
  ✗ Build process required (Webpack, etc.)
  ✗ Network round-trips for data


SIDE-BY-SIDE COMPARISON
════════════════════════════════════════════════════════════

Aspect                  │ SSR (Current)        │ CSR (Modern SPA)
────────────────────────┼──────────────────────┼──────────────────────
Initial Page Load       │ ~500ms (HTML ready)  │ ~3s (JS loads+runs)
Interaction Response    │ Page reload lag      │ Instant (JS handled)
SEO                     │ Excellent            │ Difficult
JavaScript Required     │ No (works without)   │ Yes (required)
Development Complexity  │ Simple               │ Complex
Codebase                │ Small                │ Large
API Layer Required      │ No                   │ Yes (crucial)
Real-time Features      │ Hard                 │ Easy
Mobile App Support      │ Difficult            │ Easy (same API)
Server Requirements     │ Minimal              │ Moderate
Scalability             │ Limited              │ Better
``````

---

## 10. DEPLOYMENT ARCHITECTURE

```
CURRENT DEVELOPMENT SETUP
═════════════════════════════════════════════════════════════

┌─ Developer Machine ─────────────────────────────────────┐
│                                                          │
│  Local Machine (Windows/Mac/Linux)                      │
│  │                                                       │
│  ├─ XAMPP Installation                                 │
│  │  └─ Apache Web Server (Port 80)                     │
│  │  └─ PHP 7.x or 8.x runtime                         │
│  │  └─ MySQL Database Server (Port 3306)              │
│  │                                                      │
│  ├─ Project Directory                                  │
│  │  └─ C:\xampp\htdocs\BikeRentalBT\                   │
│  │     ├─ *.php files                                  │
│  │     ├─ /admin/*                                     │
│  │     ├─ /assets/                                     │
│  │     └─ /uploads/                                    │
│  │                                                      │
│  ├─ Database                                            │
│  │  └─ MySQL/MariaDB                                   │
│  │     └─ bikerentalbt (database)                      │
│  │        ├─ users table                               │
│  │        ├─ bikes table                               │
│  │        ├─ bookings table                            │
│  │        └─ admin table                               │
│  │                                                      │
│  └─ File Editor/IDE                                    │
│     └─ VS Code, PhpStorm, etc.                         │
│                                                         │
└──────────────────────────────────────────────────────────┘

ACCESS:
  Local: http://localhost/BikeRentalBT/


TYPICAL DEPLOYMENT (Future)
═════════════════════════════════════════════════════════════

┌─ Web Server ────────────────────────────────────────────┐
│  (e.g., Bluehost, SiteGround, AWS EC2)                 │
│                                                         │
│  ├─ Web Root: /public_html or /var/www/html            │
│  │  └─ Copy all PHP files here                         │
│  │  └─ Copy /admin, /assets, /uploads dirs             │
│  │                                                      │
│  └─ Web Server Software                                │
│     ├─ Apache (httpd)                                  │
│     └─ PHP-FPM 7.x or 8.x                             │
│                                                         │
├─ Database Server ──────────────────────────────────────┤
│  (Same server or separate)                             │
│                                                         │
│  └─ MySQL/MariaDB                                      │
│     └─ Database: bikerentalbt                          │
│     └─ User: app_user (not root)                       │
│                                                         │
├─ File Permissions ─────────────────────────────────────┤
│  /uploads/ directory: writable (755)                   │
│  *.php files: 644                                       │
│  config.php: 600 (most restrictive)                    │
│                                                         │
└──────────────────────────────────────────────────────────┘

PROCESS:
  1. Purchase hosting
  2. Upload files via FTP/SFTP
  3. Create MySQL database
  4. Edit config.php with production credentials
  5. Import bikerentalbt.sql into database
  6. Set file permissions
  7. Test application


FOLDER STRUCTURE ON SERVER
═════════════════════════════════════════════════════════════

/public_html/
│
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── book_bike.php
├── cancel_booking.php
├── change_password.php
├── logout.php
├── config.php (!!!must protect!!!)
│
├── admin/
│   ├── admin_login.php
│   ├── admin_dashboard.php
│   ├── manage_bikes.php
│   ├── manage_bookings.php
│   ├── manage_users.php
│   └── admin_change_password.php
│
├── assets/
│   ├── css/
│   │   └── style.css
│   └── images/
│       ├── bike1.jpg
│       ├── bike2.jpg
│       ├── hero.jpg
│       └── harlay.jpg
│
├── uploads/                 (user documents)
│   └── [documents...]
│
└── sql/
    └── bikerentalbt.sql     (for reference/backup)


PRODUCTION CHECKLIST
═════════════════════════════════════════════════════════════

Database:
  ☐ Change default passwords (admin, root)
  ☐ Use non-root database user with limited permissions
  ☐ Regular backups scheduled
  ☐ Test backup restoration

Files:
  ☐ Set proper file permissions (not 777)
  ☐ Keep config.php out of web root if possible
  ☐ Remove sql/ directory from web root
  ☐ Protect /uploads/ directory

PHP/Server:
  ☐ Enable HTTPS/SSL certificate
  ☐ Update PHP to latest secure version
  ☐ Disable PHP errors in browser (show_errors = Off)
  ☐ Set proper session cookie flags (Secure, HttpOnly)
  
Application:
  ☐ Use strong default admin password
  ☐ Implement rate limiting on login
  ☐ Add logging for security events
  ☐ Regular security updates
  ☐ Monitor for suspicious activity
```

