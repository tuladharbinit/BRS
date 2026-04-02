# BikeRentalBT - Executive Summary

## Overview
BikeRentalBT is a **traditional server-side rendered PHP web application** with a monolithic architecture. It demonstrates classic web development patterns from the 2000s-2010s era, with direct database access, no external frameworks, and simple file-based routing.

---

## ANSWERS TO YOUR 8 QUESTIONS

### 1. Is this CSR or SSR?

**ANSWER: Server-Side Rendered (SSR)**

The application generates complete HTML on the server and sends it to the browser. When a user requests a page, the PHP script:
1. Connects to the database
2. Executes queries
3. Loops through results 
4. Generates complete HTML markup
5. Sends full HTML to browser

**Evidence:**
- All pages use `<?php echo htmlspecialchars($variable) ?>` to render data
- No JavaScript framework (React, Vue, Angular)
- No API endpoints returning JSON
- Forms use traditional POST/GET, not AJAX
- Page reload on every form submission

**Example:**
```php
<?php
// In index.php
$result = mysqli_query($conn, "SELECT * FROM bikes");
while($row = mysqli_fetch_assoc($result)){
?>
  <div class="bike-card">
    <h3><?= htmlspecialchars($row['brand']) ?></h3>
    <!-- HTML generated on server -->
  </div>
<?php } ?>
```

**Modern Alternative:** This could be CSR with React API + separate backend, but it's not.

---

### 2. Is there an API layer?

**ANSWER: NO - Direct Database Access**

There is **zero API layer**. Every PHP file directly queries the MySQL database using MySQLi.

**Pattern:**
```
Browser → PHP File → MySQLi Query → MySQL Database
Browser ← HTML (complete page)
```

**Not like:**
```
Browser → REST API → Database
Browser ← JSON response → JavaScript renders
```

**Evidence - All files follow this pattern:**

- **index.php**: `mysqli_query($conn, "SELECT * FROM bikes")`
- **login.php**: `mysqli_query($conn, "SELECT id, password FROM users WHERE email = ?")`
- **book_bike.php**: `mysqli_query($conn, "INSERT INTO bookings...")`
- **admin/manage_bookings.php**: Direct UPDATE queries

**Implication:**
- Can't build mobile app against this system
- Can't separate backend from frontend
- Database schema tightly coupled to views
- Every request generates new HTML

---

### 3. Is this monolithic or microservices?

**ANSWER: Monolithic - Single Codebase**

All functionality (user features, admin features, database access) exists in one application.

**Evidence:**
```
BikeRentalBT/ (single application)
├── User features (root PHP files)
├── Admin features (/admin/ subdirectory)
├── Shared database (bikerentalbt)
├── Shared configuration (config.php)
└── Single deployment unit
```

**Features in one app:**
- User registration, login, booking
- Admin authentication, management
- Same database tables accessed by both
- Single session mechanism
- Single CSRF protection shared

**NOT microservices because:**
- No separate services (user service, booking service)
- No inter-service communication
- No separate deployments
- Tightly coupled to single database

**If it were microservices:**
```
User Service → Database A
Booking Service → Database B
Admin Service → Database C
(communicate via REST APIs)
```

---

### 4. What frameworks and libraries are used?

**ANSWER: NONE - Vanilla PHP**

| Category | Status | Used |
|----------|--------|------|
| **PHP Framework** | ❌ Not used | No Laravel, Symfony, WordPress, CodeIgniter |
| **ORM** | ❌ Not used | MySQLi (raw queries) |
| **Template Engine** | ❌ Not used | PHP itself is the template language |
| **JavaScript Framework** | ❌ Not used | Vanilla JavaScript only (form validation) |
| **CSS Framework** | ❌ Not used | Custom CSS written manually |
| **Build Tool** | ❌ Not used | No Webpack, Gulp, Vite, Parcel |
| **Package Manager** | ❌ Not used | No npm, Composer, Yarn |
| **Routing Framework** | ❌ Not used | File-based routing (/book_bike.php → book_bike.php) |
| **Testing Framework** | ❌ Not used | No automated tests |

**What IS used:**
- PHP (language)
- MySQLi (database access library - built-in)
- HTML5, CSS3, JavaScript (browser technologies)
- Apache web server

**Security functions (built-in to PHP):**
- `password_hash()` - bcrypt hashing
- `password_verify()` - bcrypt verification
- `bin2hex(random_bytes())` - random token generation
- `htmlspecialchars()` - XSS prevention
- `filter_var()` - email validation
- `preg_match()` - regex validation
- `mysqli_prepare()` - prepared statements (SQL injection prevention)

---

### 5. How are requests routed and handled?

**ANSWER: File-Based Routing**

URLs map directly to PHP files on the file system.

```
URL Request                    → File Executed
────────────────────────────────────────────────
/index.php                     → index.php
/login.php                     → login.php
/register.php                  → register.php
/dashboard.php                 → dashboard.php
/book_bike.php?bike=1          → book_bike.php (bike_id in $_GET)
/cancel_booking.php            → cancel_booking.php
/admin/admin_login.php         → admin/admin_login.php
/admin/manage_bikes.php        → admin/manage_bikes.php (?edit=1 or ?delete=1)
/admin/manage_bookings.php     → admin/manage_bookings.php
```

**How it works:**

1. **User clicks link:**
   ```html
   <a href="book_bike.php?bike=1">Book</a>
   ```

2. **Browser requests:**
   ```
   GET /book_bike.php?bike=1
   ```

3. **Web server (Apache) finds file and executes:**
   ```
   /var/www/html/BikeRentalBT/book_bike.php
   ```

4. **PHP outputs HTML:**
   ```
   <!DOCTYPE html>
   <h2>Book Bike...</h2>
   ...
   ```

5. **Browser displays page**

**Request Handler Pattern (all files follow this):**

```php
<?php
// 1. Set up
include 'config.php';

// 2. Check auth
if(!isset($_SESSION['user'])) header('Location: login.php');

// 3. Extract data
$user = $_SESSION['user'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 4. Handle POST
if(isset($_POST['action'])){
  // Process form...
  header('Location: next_page.php');
  exit;
}

// 5. Fetch data
$stmt = mysqli_prepare($conn, "SELECT * FROM table WHERE id = ?");
// Execute query...

// 6. Render HTML
?>
<!DOCTYPE html>
...
<?php echo htmlspecialchars($variable); ?>
...
```

**Comparison:**
- Modern framework routing: `/api/bookings` → `BookingController@store()` → JSON
- This routing: `/book_bike.php` → direct PHP execution → HTML

---

### 6. Is there separation of concerns (MVC)?

**ANSWER: Minimal - Mixed Concerns**

**Ideal MVC:**
```
Model (business logic) → Controller (requests) → View (presentation)
(separate files)
```

**BikeRentalBT Reality:**
```
PHP File contains everything:
├─ Controller logic (request handling)
├─ Model logic (database queries + business rules)
└─ View logic (HTML generation)
```

**Example - All in book_bike.php:**

```php
<?php
include 'config.php';

// ===== CONTROLLER LOGIC =====
if(!isset($_SESSION['user'])) header('Location: login.php');
$bike_id = isset($_GET['bike']) ? (int)$_GET['bike'] : 0;

// ===== MODEL LOGIC (mixed in) =====
if(isset($_POST['book'])){
  if(!verify_csrf_token($_POST['csrf_token'])){
    $error = 'Invalid token';
  } else {
    $from = trim($_POST['from']);
    $to = trim($_POST['to']);
    
    // Business rule: validate dates
    if($from > $to){
      $error = 'Invalid date range';
    } else {
      // Business logic: check availability
      $stmt = mysqli_prepare($conn, 
        "SELECT COUNT(*) FROM bookings 
         WHERE bike_id = ? AND status = ? 
         AND NOT (date_to < ? OR date_from > ?)");
      // ... execute and check ...
      
      if($no_conflicts){
        // Create booking (database write)
        $stmt = mysqli_prepare($conn, 
          "INSERT INTO bookings(...) VALUES(...)");
        // ... execute ...
      }
    }
  }
}

// Get data
$stmt = mysqli_prepare($conn, "SELECT * FROM bikes WHERE id = ?");
// ... execute ...

// ===== VIEW LOGIC (mixed in) =====
?>
<!DOCTYPE html>
<h2>Book: <?= htmlspecialchars($bike['brand']) ?></h2>
<?php if($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form method="POST">
  <!-- form fields -->
</form>
```

**Separation exists at directory level:**
- `/` = User features
- `/admin/` = Admin features  
- `/config.php` = Database setup
- `/assets/` = CSS/images

But **no separation within files** between logic and presentation.

**Problems with mixed concerns:**
- Can't unit test logic (tied to web layer)
- Can't reuse validation logic (repeated in files)
- Hard to refactor (changes impact multiple aspects)
- Tight coupling (view depends on exact DB schema)

---

### 7. Where does business logic reside?

**ANSWER: Embedded in PHP Files**

Business logic is **scattered throughout request handlers** with no centralized location.

**Business Logic Distribution:**

| Feature | Business Logic Location | Examples |
|---------|--------------------------|----------|
| **Authentication** | login.php, admin_login.php | Password verification, session creation |
| **Registration** | register.php | Email uniqueness, input validation, document upload |
| **Booking** | book_bike.php | Availability check, date validation, pricing |
| **Booking Management** | manage_bookings.php | Approval workflow, status transitions |
| **Cancellation** | cancel_booking.php | Date check (can't cancel past bookings) |
| **Password** | change_password.php | Current pwd verification, new pwd validation |
| **Admin Tasks** | manage_bikes.php, manage_users.php | CRUD operations |

**Example: Booking Business Rules (in book_bike.php)**

```php
// Validation Rule 1: Dates must be valid
if($from === '' || $to === ''){
  $error = 'Dates required';
}

// Validation Rule 2: Start before end
if($from > $to){
  $error = 'Invalid date range';
}

// Business Rule 3: Check availability (database query)
$stmt = mysqli_prepare($conn, 
  "SELECT COUNT(*) FROM bookings 
   WHERE bike_id = ? AND status = ? 
   AND NOT (date_to < ? OR date_from > ?)");

$confirmed = 'confirmed';
mysqli_stmt_bind_param($stmt, 'isss', $bike_id, $confirmed, $from, $to);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $conflict_count);
mysqli_stmt_fetch($stmt);

if($conflict_count > 0){
  $error = 'Bike unavailable';
}

// Business Rule 4: Price calculation (in admin display)
$days = ($to - $from)->days + 1;
$total_price = $days * $bike_price;
```

**Example: Cancellation Business Rules (in cancel_booking.php)**

```php
// Rule 1: Can't cancel if already cancelled
if($status === 'cancelled'){
  header('Location: dashboard.php?cancel=already');
  exit;
}

// Rule 2: Can't cancel after rental date
if($date_to < date('Y-m-d')){
  header('Location: dashboard.php?cancel=expired');
  exit;
}

// Rule 3: Mark as cancelled (state change)
$stmt = mysqli_prepare($conn, "UPDATE bookings SET status = ? WHERE id = ?");
$cancelled = 'cancelled';
mysqli_stmt_bind_param($stmt, 'si', $cancelled, $booking_id);
mysqli_stmt_execute($stmt);
```

**Problems:**
- **Duplication:** Date validation repeated in multiple files
- **No reusability:** Can't call validation from elsewhere
- **No testing:** Business logic tied to HTTP request/response
- **Hard to find:** Business rules scattered across files

---

### 8. How is the frontend structured?

**ANSWER: Custom HTML/CSS + Minimal Vanilla JavaScript**

**Frontend Stack:**
- **HTML5:** Server generates on-the-fly
- **CSS3:** Custom styles in `assets/css/style.css`
- **JavaScript:** Minimal vanilla JS for form validation only
- **No framework:** No React, Vue, Angular, Svelte, etc.

**Frontend Files:**

```
assets/
├── css/
│   └── style.css         (~8 KB, all styling)
└── images/
    ├── hero.jpg          (homepage background)
    ├── bike1.jpg         (bike images)
    ├── harlay.jpg        (login background)
    └── Other images
```

**HTML Generation Pattern:**

```php
<!-- Template in PHP -->
<?php
$bikes = [];
$result = mysqli_query($conn, "SELECT * FROM bikes");
while($row = mysqli_fetch_assoc($result)){
  $bikes[] = $row;
}
?>

<!DOCTYPE html>
<html>
  <body>
    <div class="bike-container">
      <?php foreach($bikes as $bike): ?>
        <div class="bike-card">
          <img src="<?= htmlspecialchars($bike['image']) ?>">
          <h3><?= htmlspecialchars($bike['brand']) ?></h3>
          <p>NPR <?= htmlspecialchars($bike['price']) ?> / day</p>
          <a class="btn" href="book_bike.php?bike=<?= (int)$bike['id'] ?>">
            Rent Now
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </body>
</html>
```

**CSS (No Framework):**

```css
/* Custom grid layout */
.bike-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.bike-card {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,.1);
  transition: transform 0.2s;
}

.bike-card:hover {
  transform: translateY(-4px);
}

.btn {
  background: linear-gradient(90deg, #ff7a00, #ff4d00);
  color: white;
  padding: 10px 16px;
  border-radius: 10px;
  border: none;
  cursor: pointer;
}
```

**JavaScript (Minimal Vanilla):**

```javascript
// Only form validation - NO framework
(function(){
  var form = document.getElementById('loginForm');
  form.addEventListener('submit', function(e){
    var email = document.getElementById('email').value.trim();
    var password = document.getElementById('password').value;
    
    if(!email || !password){
      e.preventDefault();
      alert('Please fill all fields');
    }
  });
})();
```

**Frontend Characteristics:**

| Aspect | Implementation |
|--------|-----------------|
| **Rendering** | Server-side (no CSR) |
| **Interactivity** | Minimal (form validation only) |
| **State Management** | Session (server-side) |
| **Data Fetching** | Form POST/GET (no AJAX) |
| **Real-time Updates** | Page reload required |
| **Animations** | CSS only (no JavaScript) |
| **Responsive Design** | CSS media queries |
| **Accessibility** | Basic HTML semantics |

**What's NOT used:**
- React, Vue, Angular, Svelte
- jQuery, Axios, Fetch API
- Bootstrap, Tailwind CSS
- Build tools (Webpack, Vite)
- NPM packages
- State management (Redux, Vuex)

---

## ARCHITECTURE IN ONE PICTURE

```
TRADITIONAL SERVER-SIDE RENDERED MONOLITHIC PHP APP
════════════════════════════════════════════════════════════════

Browser Request
    │
    ├─→ /index.php
    ├─→ /login.php
    ├─→ /book_bike.php?bike=1
    └─→ /admin/manage_bookings.php
         │
         └─ File-based routing (no framework)

         ↓

PHP File Execution (book_bike.php example)
    ├─ Authorize (check $_SESSION)
    ├─ Extract data (GET/POST)
    ├─ Business logic (validation, calculations)
    ├─ Database queries (MySQLi, prepared statements)
    └─ Generate HTML output
         │
         └─ All three concerns (controller/model/view) mixed

         ↓

Database
    ├─ users table
    ├─ bikes table
    ├─ bookings table
    └─ admin table
         (MySQLi direct queries, no ORM, no API)

         ↓

HTML Response
    ├─ Complete HTML page
    ├─ Linked CSS (style.css)
    ├─ Inline JavaScript (form validation only)
    └─ No API, no JSON
         │
         └─ Browser renders and displays

         ↓

Form Submission
    └─ POST /book_bike.php → Page reload
       (same cycle repeats with form data)
```

---

## STRENGTHS

✓ **Simple & Understandable** - Easy to read and modify
✓ **Low Overhead** - No framework artifacts or configuration
✓ **Fast Deployment** - Just copy files to web server
✓ **Minimal Dependencies** - Only needs PHP + MySQL (built-in)
✓ **Security Basics** - Prepared statements, CSRF protection, password hashing
✓ **Small Footprint** - ~50 KB total code (excluding images)
✓ **Direct Control** - No abstraction layers (good for learning, bad for scale)

---

## WEAKNESSES

✗ **Mixed Concerns** - Logic and presentation tangled in files
✗ **Not Unit Testable** - Can't test business logic in isolation
✗ **Code Duplication** - Validation repeated across files
✗ **Not Scalable** - Single database, monolithic deployment
✗ **No API** - Can't build mobile/desktop clients
✗ **Tight Coupling** - Views depend on exact database schema
✗ **Manual Organization** - No framework conventions
✗ **Real-time Difficult** - Every action requires page reload
✗ **No Caching** - Every request hits database
✗ **Poor for Teams** - Multiple developers cause conflicts

---

## COMPARISON TO MODERN ARCHITECTURES

### Modern SPA with API
```
React/Vue Frontend ←→ REST API ←→ Laravel/Node Backend ←→ Database
                 (JSON)                                    (Separated)
```

### Current Architecture
```
Server-Side PHP ←→ Database
(File)             (Tightly coupled)
```

---

## CONCLUSION

BikeRentalBT is a **textbook example of traditional server-side rendered web development**. It's:

- **Monolithic:** All code in one codebase
- **Server-rendered:** HTML generated on server
- **Direct database access:** No API layer
- **Framework-less:** Pure PHP with MySQLi
- **Simple routing:** File-based mapping
- **Mixed concerns:** Logic embedded in views
- **Production-ready:** For small projects
- **Not scalable:** Beyond 1000 users would require refactoring

It demonstrates **how web applications worked before modern frameworks** and serves as a good foundation for learning, but would need significant refactoring for enterprise-scale systems.

---

## DOCUMENTATION FILES CREATED

1. **ARCHITECTURE_ANALYSIS.md** - Comprehensive deep-dive with code examples
2. **ARCHITECTURE_DIAGRAMS.md** - Visual flowcharts and architecture diagrams
3. **QUICK_REFERENCE.md** - Code patterns and quick lookups
4. **ARCHITECTURE_SUMMARY.md** - This file (executive summary)

See these files in the project root for detailed information.

