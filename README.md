# Employee Daily Work Update &amp; Performance Evaluation System

An enterprise-ready, modular, and secure foundation for managing employee tasks, daily updates, and performance evaluations using **Vanilla PHP 8.1+ (Object-Oriented with PDO)** and **MySQL 8.0+**.

---

## 📁 Project Architecture & Directory Layout

```
├── config/
│   └── database.php                  # Database credentials & PDO options
├── database/
│   ├── schema.sql                    # Production SQL schema with FKs, indexes & seeds
│   └── seed.php                      # DB seeder for Admin, Manager, and Employee test accounts
├── public/                           # Web document root
│   ├── .htaccess                     # URL rewrite rules & security headers
│   ├── index.php                     # Front controller & auto-redirect
│   ├── login.php                     # Hardened authentication page (password_verify + CSRF)
│   ├── logout.php                    # Secure session destruction
│   ├── admin/
│   │   └── dashboard.php             # Admin-only dashboard (Auth::requireRole('Admin'))
│   ├── manager/
│   │   └── dashboard.php             # Manager portal (Auth::requireRole(['Manager', 'Admin']))
│   └── employee/
│       └── dashboard.php             # Employee portal (Auth::requireRole(['Employee', 'Manager', 'Admin']))
├── src/
│   ├── Core/
│   │   ├── Auth.php                  # RBAC & authentication service
│   │   ├── Csrf.php                  # Timing-attack-safe CSRF generator and validator
│   │   ├── Database.php              # Thread-safe Singleton PDO Connection & Query manager
│   │   ├── Env.php                   # Zero-dependency .env environment variable parser
│   │   ├── Session.php               # Hardened session manager (Strict, HttpOnly, Lax)
│   │   └── Exceptions/
│   │       └── DatabaseException.php # Sanitized exception preventing credential leaks
│   ├── Models/
│   │   └── User.php                  # User entity with secure password hashing & queries
│   ├── Views/
│   │   └── partials/
│   │       └── nav.php               # Role-aware navigation bar
│   └── autoload.php                  # PSR-4 standalone autoloader
├── .env.example                      # Environment configuration template
├── .env                              # Local runtime credentials (git-ignored)
├── .gitignore                        # Excludes vendor, logs, and sensitive .env
├── composer.json                     # Modern PSR-4 setup for optional Composer usage
├── seed.php                          # Root convenience wrapper for database/seed.php
└── README.md
```

---

## 🔐 Authentication & RBAC Highlights

1. **Password Hashing & Verification**:
   - Accounts use `PASSWORD_BCRYPT` with cost 12.
   - Authentication verifies hashes via `password_verify()`.
   - Automatic re-hashing via `password_needs_rehash()` if algorithm parameters are upgraded.

2. **Session Security ([`src/Core/Session.php`](file:///d:/PharmaDEM%20Solutions%20Assignment/src/Core/Session.php))**:
   - `HttpOnly: true` (prevents malicious JavaScript/XSS cookie exfiltration).
   - `SameSite: Lax` (mitigates Cross-Site Request Forgery).
   - `Session::regenerate()` called immediately on login to prevent **Session Fixation attacks**.
   - Single-use flash message support (`setFlash()`, `getFlash()`).

3. **RBAC Middleware Functions ([`src/Core/Auth.php`](file:///d:/PharmaDEM%20Solutions%20Assignment/src/Core/Auth.php))**:
   - `Auth::requireLogin($loginUrl = '/login.php')`: Halts unauthenticated visitors and preserves intended destination URL.
   - `Auth::requireRole($roles)`: Blocks users without appropriate roles, redirecting them to their respective dashboard with a toast warning.
   - `Auth::redirectIfAuthenticated()`: Forwards authenticated users away from the login page.
   - `Auth::isAdmin()`, `Auth::isManager()`, `Auth::isEmployee()`.

---

## 📊 Manager & Admin Modules

1. **Manager Overview Dashboard (`/manager/dashboard.php?tab=overview`)**:
   - Real-time KPI metrics: Total Tasks, Completed Tasks, Active In Progress, Overdue Tasks, and Today's Daily Update Compliance rate (`X / Y filed`).
   - Scoped by manager's department (with admin oversight across all departments).

2. **Task Assignment Engine (`/manager/dashboard.php?tab=overview`)**:
   - Task creation and team delegation form with CSRF protection.
   - Priority levels: `Low`, `Medium`, `High`, `Urgent`.
   - Date picker constraint (`min="today"`) and instructions/SOP references.

3. **Automated Overdue Tracker**:
   - Dynamically checks tasks past their `due_date` and transitions status to `'Overdue'` via `Task::updateOverdueTasks()`.
   - Triggers automatically upon dashboard loading and task queries to guarantee real-time SLA metrics.
   - Distinct overdue warning badges and dedicated status filter.

4. **Daily Updates Review Queue (`/manager/dashboard.php?tab=updates`)**:
   - Filter by `All`, `Awaiting Review`, and `Reviewed`.
   - Shows To-Do items, Completed deliverables, and Pending Blockers.
   - Inline feedback form allowing managers to write notes, approve, and record timestamps (`reviewed_at`).

5. **Performance Evaluation & Analytics (`/manager/dashboard.php?tab=analytics` & `?tab=evaluations`)**:
   - Evaluation scorecard input for: Task Completion, Punctuality, and Quality (0–100 scale) with live overall preview.
   - Qualitative assessment notes and auto-calculated stored overall score.
   - Real-time automated metrics comparison: Task Completion Rate (%), On-Time Delivery Rate (%), and Overdue Rate (%) alongside manager ratings.

6. **Compliance Reporting & Data Export (`/export.php`)**:
   - Clean UTF-8 CSV exports with Excel BOM:
     - `export.php?type=daily_updates&format=csv` (Work Logs)
     - `export.php?type=performance_summary&format=csv` (Completion Analytics)
     - `export.php?type=evaluations&format=csv` (Evaluation Scorecards)
   - Printable Executive HTML Reports formatted for `@media print` and PDF export.

7. **Admin Control Center (`/admin/dashboard.php`)**:
   - Cross-department staff distribution and department management.
   - Full system user directory with RBAC verification.

---

## 👥 Default Seed Test Accounts

Run the seeder using either CLI or browser:
```bash
php seed.php
# or
php database/seed.php
```

| Role | Email | Password | Allowed Dashboards |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@pharmadem.local` | `Password@123` | `/admin/dashboard.php`, `/manager/dashboard.php`, `/employee/dashboard.php` |
| **Manager** | `manager@pharmadem.local` | `Password@123` | `/manager/dashboard.php`, `/employee/dashboard.php` |
| **Employee** | `employee@pharmadem.local` | `Password@123` | `/employee/dashboard.php` |

*(Quick-autofill buttons are embedded directly into `/login.php` for seamless testing!)*

---

## 🚀 Running the Project

```bash
# 1. Start local PHP development server
php -S localhost:8000 -t public

# 2. Access portal in your browser:
# http://localhost:8000/login.php
```

