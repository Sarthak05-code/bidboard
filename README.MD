# BidBoard — Freelance Task Bidding Platform

BidBoard is a freelance task marketplace built with **plain PHP**, HTML, CSS, and vanilla JavaScript. Clients post tasks, freelancers can submit bids without creating an account, and clients review and accept bids. Administrators oversee tasks, bids, users, and reports.

The core web application uses no PHP framework. PDF report rendering is delegated to a compiled Rust CLI utility that uses a headless Chromium browser.

## Contents

- [Tech stack](#tech-stack)
- [Roles](#roles)
- [Getting started](#getting-started)
- [Configuration](#configuration)
- [Project structure](#project-structure)
- [Features](#features)
- [Security](#security)
- [Known issues and areas to watch](#known-issues-and-areas-to-watch)
- [Changelog](#changelog)
- [Author](#author)

## Tech Stack

| Layer | Technology |
| --- | --- |
| Web backend | Vanilla PHP (no web framework) |
| System utilities | Rust CLI binaries for document and other heavy system tasks |
| PDF engine | Rust with Chromium via the `headless_chrome` crate |
| Database | MySQL, managed with phpMyAdmin |
| Frontend | HTML, CSS, and vanilla JavaScript (AJAX via Fetch) |
| Server | Apache and MySQL through XAMPP |
| Email verification | Abstract API Email Reputation endpoint |
| Email notifications | PHPMailer (Composer) with Gmail SMTP |

## Roles

| Role | Access |
| --- | --- |
| **Admin** | View and delete tasks and bids, manage clients, and review reports. |
| **Client** | Register, post tasks, view bids, accept or reject bids, mark tasks complete, and report bids. |
| **Freelancer** | Browse tasks, submit bids, check bid status by email, preview and download styled Rust-generated PDF reports, and report tasks. |

## Getting started

### Requirements

- Windows with [XAMPP](https://www.apachefriends.org/) installed
- PHP 7.4 or higher
- Composer (required for PHPMailer)
- Google Chrome or Microsoft Edge installed on the host machine for the Rust headless Chromium PDF engine
- **Optional:** Rust and Cargo, if you want to rebuild the PDF utility. A precompiled `bin/bidboard-pdf.exe` is included.
- **Optional:** an Abstract API key for email verification
- **Optional:** a Gmail account with an App Password for email notifications

### Installation

1. Clone or extract the project into XAMPP's `htdocs` directory:

   ```text
   C:\xampp\htdocs\bidboard\
   ```

   The project folder must be named **`bidboard`** because internal paths depend on the `/bidboard/` root.

2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

3. Open phpMyAdmin at <http://localhost/phpmyadmin>, create a database named `bidboard`, and import [`sql/bidboard.sql`](sql/bidboard.sql).

4. From the project root, copy the sample configuration files:

   ```bat
   copy includes\db.sample.php includes\db.php
   copy includes\email_helper.sample.php includes\email_helper.php
   copy includes\mailer.sample.php includes\mailer.php
   ```

   Add your database settings and, if needed, your API and SMTP credentials as described in [Configuration](#configuration).

5. Install the PHP dependencies from the project root:

   ```bat
   composer install
   ```

6. (Optional) Rebuild the Rust PDF generator if you have modified its source. Run these commands from the project root:

   ```bat
   cd tools\bidboard-pdf
   cargo build --release
   copy target\release\bidboard-pdf.exe ..\..\bin\
   ```

7. Open BidBoard at <http://localhost/bidboard/>.

> **Tip:** Run `setup.bat` after starting Apache and MySQL. It automates the initial `includes/db.php` setup and prints the remaining setup instructions.

### Default admin account

The default admin credentials are:

- **Username:** `admin`
- **Password:** `admin123`

Change the default password before exposing the application beyond a local development environment.

## Configuration

### Email verification (Abstract API)

Add your Abstract API key to `includes/email_helper.php`:

```php
define("ABSTRACT_API_KEY", "your_key_here");
```

If no key is configured, the email check **fails open** and returns `true` automatically.

### Email notifications (PHPMailer and Gmail SMTP)

Add your Gmail address and Gmail App Password to `includes/mailer.php`:

```php
define('SMTP_EMAIL', 'your_gmail@gmail.com');
define('SMTP_APP_PASSWORD', 'your16charapppassword');
```

Use a Gmail **App Password**, not your regular account password.

## Project structure

```text
bidboard/
├── index.php                     # Public task listing with AJAX search, filters, and pagination
├── task.php                      # Single task view and bid submission form
├── bid_status.php                # Freelancer bid lookup and PDF preview/download controls
├── export_pdf.php                # Passes JSON payloads to the Rust utility and streams PDFs
├── report.php                    # Shared task and bid report form
├── setup.bat                     # Assists with first-time database configuration
├── AGENTS.md                     # Guidance for AI coding agents
├── composer.json / composer.lock # PHP dependency manifests
├── bin/
│   └── bidboard-pdf.exe           # Precompiled Rust PDF utility
├── tools/
│   └── bidboard-pdf/
│       ├── Cargo.toml
│       ├── template.html          # HTML/CSS report template
│       └── src/
│           └── main.rs            # Rust JSON reader and Chromium PDF builder
├── auth/                          # Login, registration, and session management
├── client/                        # Client dashboard, task creation, and bid review
├── admin/                         # Admin dashboard, user management, and reports
├── actions/                       # State-changing endpoints
├── includes/                      # Database, auth guards, security helpers, and mailer
├── css/                           # Global stylesheet
├── sql/                           # Database schema and seed data
└── uploads/                       # Temporary assets and contract attachments
```

## Features

### Marketplace workflows

- Task lifecycle: **open → in progress → completed**
- Bid lifecycle: **pending → accepted / rejected**
- Freelancers can browse tasks and submit a bid without creating an account.
- Duplicate bids are prevented by allowing only one bid per email address per task.
- Bid status can be looked up by email.
- Accepting a bid automatically rejects the other pending bids for that task and sends notification emails to affected freelancers.

### PDF reports and Rust integration

- PHP passes report data to the compiled Rust CLI through temporary JSON files in `uploads/temp/`.
- The Rust utility renders styled reports using the HTML/CSS template and a headless Chromium browser.
- Reports support two delivery modes:
  - **Preview** (`mode=preview`): streams the PDF for viewing in the browser.
  - **Download** (`mode=download`): downloads the report as `BidBoard_History.pdf`.

### Asynchronous interface

- AJAX-powered homepage search, category filtering, and pagination update results without a full page reload.
- The admin task list also supports pagination and search.

### Reporting and account tools

- Clients can report bids; freelancers can report tasks.
- Admins can review reports and manage platform activity.
- Client tools include profile and task editing.

## Security

- CSRF tokens are validated on state-changing endpoints.
- Sessions are regenerated on authentication and isolated by role.
- Login and bid actions are rate-limited using IP/session checks.
- SQL queries use parameterized MySQLi prepared statements.
- Temporary JSON payload files isolate PHP-to-Rust data transfer and avoid Windows shell-argument escaping issues.
- Security headers and secure session-cookie handling are included.

## Known issues and areas to watch

### Headless Chrome dependency

PDF export requires a Chromium-based browser—Google Chrome, Microsoft Edge, or Brave—to be installed and available to the process running XAMPP. If PDF generation fails, verify the browser installation and the Rust utility's browser path/configuration.

### Temporary directory permissions

Ensure Apache can write to `uploads/temp/`. The project notes `0777` as a possible permission setting; use the least permissive setting that works for your local environment, and avoid broad write permissions on a public server.

### File paths

Internal links assume the project is located at `C:\xampp\htdocs\bidboard\` and served from `/bidboard/`. Renaming or moving the project requires updating the relevant paths.

## Changelog

| Version | Changes |
| --- | --- |
| **v1** | Core platform: authentication, task posting, bid submission, and admin panel. |
| **v2** | Duplicate bid prevention, admin search, and client profile editing. |
| **v3** | Freelancer bid history, client task editing, and context bar. |
| **v4** | CSRF protection, session regeneration, real-time validation, and Abstract API email verification. |
| **v5** | Task and bid reporting, admin report review panel, and session isolation fix. |
| **v6** | Pagination for admin tasks and public homepage, CSRF protection for admin deletion, and filter preservation. |
| **v7** | Rate limiting, security headers, logout session-cookie fix, negative-value prevention, and Rs. currency display. |
| **v8** | PHPMailer notifications for accepted and rejected bids, plus `AGENTS.md` guardrails. |
| **v9** | CSS refactoring, AJAX search and pagination, and stricter letter/title validation. |
| **v10** | Rust PDF integration with `headless_chrome`, HTML/CSS template engine, preview/download handlers, and auto-reject email cascade. |

## Author

Built as a college project by **Sarthak** — BCA, Kathmandu.
