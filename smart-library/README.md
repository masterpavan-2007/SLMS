# Smart Library Management System (SLMS)

A complete, functional college mini-project for managing a library's books,
students, staff, circulation (issue/return), reservations, fines, payments,
and reports — built with plain PHP, MySQL, and vanilla HTML/CSS/JS (no
frameworks), designed to run on XAMPP.

---

## 1. Features

**Library Owner / Admin**
- Dashboard with live stats and Chart.js graphs (issues vs returns, category
  distribution, most borrowed books)
- Full CRUD for books, categories, authors, publishers
- Manage students and staff (add / edit / remove)
- View & issue books, process returns, manage reservations
- Fine management (mark paid / waive), payment history
- Reports (11 report types) with CSV export and print-ready layout
- Configurable library settings (borrow period, fine per day, max fine,
  grace period, max books per student, reservation validity)

**Reception / Librarian**
- Daily dashboard (issued/returned today, overdue, pending reservations)
- Issue a book (validates student, availability, borrowing limit, unpaid fines)
- Return a book (automatic late-day and fine calculation)
- Register & search students, view borrowing history
- Search the catalog, manage reservations, record fine payments
- Daily / overdue reports with print support

**Student**
- Personal dashboard with "Recommended for You" (based on most-borrowed category)
- Browse/search the catalog and reserve unavailable books
- View currently issued books, renew (if not overdue and no pending fines)
- View reservations (cancel pending ones), fines, and payment history
- Notifications inbox, profile editing, and password change

**Security**
- PHP sessions, `password_hash()` / `password_verify()` (bcrypt)
- Prepared statements (PDO) everywhere — no raw SQL concatenation
- CSRF tokens on every form
- Role-based access control (`requireRole()` blocks cross-role URL access)
- Output escaping via `e()` helper (XSS protection)
- Generic error messages — no raw DB/PHP errors shown to users

---

## 2. Technology Stack

| Layer      | Technology                          |
|------------|--------------------------------------|
| Frontend   | HTML5, CSS3 (custom design system), vanilla JavaScript |
| Backend    | PHP 8+ (PDO, no framework)          |
| Database   | MySQL / MariaDB                      |
| Server     | Apache via XAMPP                     |
| Icons      | Font Awesome 6 (CDN)                 |
| Charts     | Chart.js 4 (CDN)                     |

---

## 3. System Requirements

- XAMPP (or any Apache + MySQL + PHP 8.0+ stack)
- A modern browser
- No internet connection required to run it, **except** that Font Awesome
  and Chart.js are loaded from a CDN — if you need a fully offline version,
  download those two libraries locally and update the `<link>`/`<script>`
  tags in `includes/header.php` and `includes/footer.php`.

---

## 4. Installation Steps (XAMPP)

1. **Copy the project** into your XAMPP `htdocs` folder so the path is:
   ```
   xampp/htdocs/smart-library/
   ```
2. **Start Apache and MySQL** from the XAMPP Control Panel.
3. **Import the database**:
   - Open `http://localhost/phpmyadmin`
   - Click **New** → create nothing manually; instead go to the **Import** tab
   - Choose the file `database/smart_library.sql` and click **Go**
   - This creates the `smart_library` database, all tables, and sample data.
4. **Check the DB config** in `config/database.php` — the defaults
   (`localhost` / `root` / no password) match a stock XAMPP install. Edit if
   your setup differs.
5. **Open the app**:
   ```
   http://localhost/smart-library/
   ```
   You'll be redirected to the login page.

---

## 5. Default Login Credentials

Password for **every** sample account below is: **`Password@123`**

| Role      | Email                          |
|-----------|---------------------------------|
| Admin     | admin@library.com              |
| Librarian | ravi.librarian@library.com     |
| Librarian | sneha.librarian@library.com    |
| Student   | aarav@student.com              |
| Student   | diya@student.com               |
| Student   | (and 8 more sample students — see `database/smart_library.sql`) |

New students/staff registered through the app default to the same password
unless a custom one is entered.

---

## 6. Folder Structure

```
smart-library/
├── config/database.php          PDO connection settings
├── admin/                       Admin-only pages (dashboard, CRUD, reports, settings)
├── librarian/                   Librarian-only pages (issue/return, students, reports)
├── student/                     Student-only pages (browse, reservations, fines, profile)
├── auth/                        login / logout / forgot-password / reset-password
├── includes/                    Shared header, footer, sidebar, navbar, functions, auth-check
├── assets/css/style.css         Design system (cards, tables, forms, badges, modals)
├── assets/js/script.js          Sidebar toggle, modals, password show/hide, confirmations
├── uploads/books/                For future book cover uploads
├── database/smart_library.sql   Full schema + sample data
└── index.php                    Redirects to login or the correct dashboard
```

---

## 7. User Roles

Three roles, enforced server-side via PHP sessions + `requireRole()`:
`admin`, `librarian`, `student`. Attempting to open another role's URL
directly returns a 403 page rather than the page content.

---

## 8. Database Description

16 tables: `users`, `students`, `staff`, `categories`, `authors`,
`publishers`, `books`, `book_copies`, `book_issues`, `book_returns`,
`reservations`, `fines`, `payments`, `notifications`, `library_settings`,
`activity_logs`. All foreign keys and indexes are defined in
`database/smart_library.sql`, with comments above each table.

Key business logic lives in `includes/functions.php`:
- `calculateFine($lateDays, $settings)` — respects grace period and max fine
- `requireRole($roles)` — access control
- `notify()` / `logActivity()` — notifications and audit trail

---

## 9. Testing Instructions

1. Log in as **admin** → check the dashboard charts render, add a test book,
   add a test student, check Reports → CSV export and Print.
2. Log in as **librarian** → issue a book to a student, then return it a
   few days "late" (you can edit `issue_date`/`due_date` in phpMyAdmin to
   simulate lateness) to confirm the fine calculates correctly.
3. Log in as **student** → browse books, reserve an unavailable title, view
   "My Issued Books", try renewing, check notifications.
4. Try navigating to another role's URL directly while logged in (e.g. a
   student opening `/admin/dashboard.php`) — you should see a 403 page.

---

## 10. Future Scope / Known Limitations

- No email delivery is configured; the "forgot password" flow displays the
  reset link on-screen instead of emailing it (swap in PHPMailer + SMTP for
  production use).
- Book cover image upload is scaffolded (`uploads/books/`) but not wired
  into the Add/Edit Book form — add a `file` input and `move_uploaded_file()`
  call in `admin/books.php` to enable it.
- `book_copies` (per-copy barcode tracking) is created in the schema but not
  yet used by the UI, which currently tracks copies as a simple counter on
  `books.total_copies` / `books.available_copies`. Wire it up if you need
  copy-level (barcode) tracking.
- No automated overdue-reminder cron/email job; overdue detection is
  computed live on each page load instead.
- Font Awesome and Chart.js are loaded from a CDN, so an internet
  connection is needed unless you vendor them locally.

---

Built to be demonstration-ready for a college project presentation or viva:
every button performs a real, database-backed operation — nothing here is
a static mockup.
