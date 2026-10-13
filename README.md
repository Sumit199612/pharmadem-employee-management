# PharmaDEM - Employee Work Update & Performance Evaluation System

Web-based employee management portal built with PHP (OOP + PDO) and MySQL. Supports role-based access control (Admin, Manager, Employee), task management, daily work updates, performance scoring, and CSV/print reporting.

## Features

- **Authentication & Roles**: Role-based access control for Admin, Manager, and Employee roles.
- **Task Management**: Create, assign, prioritize, and track task statuses (Pending, In Progress, Completed, Overdue).
- **Daily Work Logs**: Daily to-do, completed items, blockers, and manager review notes.
- **Performance Evaluations**: Period-based scorecards (completion, punctuality, quality) and automated performance metrics.
- **Reporting & Exports**: Export daily logs and performance summaries to CSV or printable format.
- **Security**: Prepared statements across all queries, CSRF validation, bcrypt password hashing, and cookie session security.

## Tech Stack

- **Backend**: PHP 8.2 (Vanilla OOP, PDO)
- **Database**: MySQL 8.0
- **Frontend**: Tailwind CSS (CDN), Vanilla JavaScript
- **Containerization**: Docker & Docker Compose
- **Web Server**: Apache (`mod_rewrite` enabled)

## Project Structure

```
├── config/
│   └── database.php          # Database configuration
├── database/
│   ├── schema.sql            # Table schema, indexes, and initial data
│   └── seed.php              # Seed script for default accounts & sample data
├── docker/
│   └── apache/
│       └── vhost.conf        # Apache virtual host configuration
├── public/                   # Document root
│   ├── admin/                # Admin dashboards
│   ├── employee/             # Employee portal (tasks, daily updates, history)
│   ├── manager/              # Manager dashboard (team tasks, reviews, evaluations)
│   ├── export.php            # CSV and printable report exporter
│   ├── index.php             # Front controller redirect
│   ├── login.php             # User login
│   └── logout.php            # Session termination
├── src/
│   ├── Core/                 # Auth, Database, Session, Csrf, Env
│   ├── Models/               # User, Task, DailyUpdate, Evaluation
│   ├── Views/partials/       # Navigation & layout partials
│   └── autoload.php          # PSR-4 autoloader
├── .env.example              # Sample environment configuration
├── docker-compose.yml        # Docker service definitions
├── Dockerfile                # PHP Apache container image
└── seed.php                  # Root CLI runner for database seeder
```

## Getting Started

### Option 1: Docker (Recommended)

1. Clone the repository and navigate into the project directory:
   ```bash
   git clone https://github.com/Sumit199612/pharmadem-employee-management.git
   cd pharmadem-employee-management
   ```

2. Copy the environment file:
   ```bash
   cp .env.example .env
   ```

3. Build and start containers:
   ```bash
   docker compose up -d --build
   ```

4. The application services will be available at:
   - **Web Application**: http://localhost:8080
   - **phpMyAdmin**: http://localhost:8081
   - **MySQL**: localhost:3307

### Option 2: Local PHP + MySQL

1. Ensure PHP 8.1+ and MySQL are installed.
2. Create the database and import the schema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
3. Copy `.env.example` to `.env` and set your database credentials.
4. Run the seed script:
   ```bash
   php seed.php
   ```
5. Start the local server:
   ```bash
   php -S localhost:8000 -t public
   ```
   Open `http://localhost:8000` in your browser.

## Default Credentials

All seed accounts use the default password: `Password@123`

| Role | Email | Accessible Dashboards |
| :--- | :--- | :--- |
| **Admin** | `admin@pharmadem.local` | `/admin/dashboard.php`, `/manager/dashboard.php`, `/employee/dashboard.php` |
| **Manager** | `manager@pharmadem.local` | `/manager/dashboard.php`, `/employee/dashboard.php` |
| **Employee** | `employee@pharmadem.local` | `/employee/dashboard.php` |

## Environment Configuration

Configuration variables can be defined in `.env`:

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=employee_management_db
DB_USER=root
DB_PASS=
APP_ENV=development
APP_DEBUG=true
```

When running in Docker, Docker Compose provides these environment variables to the container automatically.
