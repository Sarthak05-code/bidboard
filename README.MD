# BidBoard — Freelance Task Bidding Platform

A freelance task marketplace built with **plain PHP, HTML, CSS, and vanilla JS**. No framework is used for the core application — Composer is used only for a small number of well-established packages (see [Tech Stack](#tech-stack)). Runs on XAMPP.

Clients post tasks, freelancers bid on them **without needing an account**, and clients accept the best bid. Admins oversee the entire platform.

---

## Contents

- [Tech Stack](#tech-stack)
- [Roles](#roles)
- [Getting Started](#getting-started)
- [Configuration](#configuration)
- [Folder Structure](#folder-structure)
- [Features](#features)
- [Known Bugs & Areas to Watch](#known-bugs--areas-to-watch)
- [Changelog](#changelog)
- [Author](#author)

---

## Tech Stack

| Layer               | Technology                             |
| ------------------- | -------------------------------------- |
| Backend             | PHP (vanilla, no frameworks)           |
| Database            | MySQL via phpMyAdmin                   |
| Frontend            | HTML, CSS, Vanilla JS (AJAX via Fetch) |
| Server              | Apache + MySQL (XAMPP)                 |
| Email Verification  | Abstract API (Email Reputation)        |
| Email Notifications | PHPMailer (via Composer) + Gmail SMTP  |

---

## Roles

| Role       | Access                                                                                                         |
| ---------- | -------------------------------------------------------------------------------------------------------------- |
| Admin      | View/delete all tasks and bids, manage clients, review reports                                                 |
| Client     | Register, post tasks, view bids, accept/reject, mark complete, report bids                                     |
| Freelancer | Browse tasks, submit bids, check bid status by email, report tasks, receive email notifications on bid outcome |

---

## Getting Started

### Requirements

- XAMPP (Apache + MySQL)
- PHP 7.4 or higher
- [Composer](https://getcomposer.org/download/) — required for PHPMailer (email notifications)
- _(Optional)_ A free Abstract API key for email verification — see [Configuration](#configuration)
- _(Optional)_ A Gmail account with an App Password — see [Configuration](#configuration)

### Setup

1. **Clone or download** the repository into your XAMPP `htdocs` folder:

   ```cmd
   C:\xampp\htdocs\bidboard\
   ```

   > The folder must be named `bidboard` — all internal paths depend on it.

2. **Start Apache and MySQL** in the XAMPP Control Panel.

3. **Open phpMyAdmin** at `http://localhost/phpmyadmin`.

4. **Create a new database** named `bidboard`.

5. **Import** `sql/bidboard.sql` into that database.

6. **Copy** `includes/db.sample.php` to `includes/db.php`.
   The default XAMPP credentials are already set, so no changes are needed unless your MySQL uses a different password.

7. **Copy** `includes/email_helper.sample.php` to `includes/email_helper.php` and add your free Abstract API key (see [Configuration](#configuration)).
   If the key is left blank, email verification is skipped automatically and the app still works.

8. **Install PHP dependencies** with Composer:

   ```dos
   composer install
   ```

   This installs PHPMailer into a local `vendor/` folder (gitignored).

9. **Copy** `includes/mailer.sample.php` to `includes/mailer.php` and add your Gmail address and App Password (see [Configuration](#configuration)).
   If left blank, email sending will fail silently and the accept/reject flow continues to work normally without notifications.

10. **Open the app** at:

    ```
    http://localhost/bidboard/
    ```

> [!TIP]
> Run `setup.bat` after step 2 — it automates step 6 for you and prints the remaining steps.

### Default Admin Credentials

```
Username: admin
Password: admin123
```

> [!WARNING]
> Change this after your first login via phpMyAdmin if you deploy anywhere beyond localhost.

---

## Configuration

### Email Verification (Abstract API)

BidBoard optionally verifies that submitted emails are **real and deliverable** — not merely correctly formatted — using Abstract API's Email Reputation endpoint.

1. Sign up for free at [abstractapi.com](https://www.abstractapi.com/).
2. Subscribe to the **Email Reputation** product (free tier: 100 requests/month).
3. Copy your API key into `includes/email_helper.php`:

   ```php
   define("ABSTRACT_API_KEY", "your_key_here");
   ```

If no key is set, the check **fails open** (always passes), so the app remains fully functional without it.

### Email Notifications (PHPMailer + Gmail SMTP)

BidBoard optionally sends an email to the freelancer whenever their bid is accepted or rejected, using PHPMailer over Gmail's SMTP server.

1. Enable **2-Step Verification** on the Gmail account you want to send from.
2. Generate an **App Password** for "Mail" under _Google Account → Security → App Passwords_.
3. Copy your credentials into `includes/mailer.php`:

   ```php
   define('SMTP_EMAIL', 'your_gmail@gmail.com');
   define('SMTP_APP_PASSWORD', 'your16charapppassword'); // no spaces
   ```

If these are left blank or sending fails for any reason, the error is logged server-side and the accept/reject action continues normally — **a failed email never blocks the core bid workflow**.

---

## Folder Structure

```
bidboard/
├── index.php                 ← Public task listing (homepage) with AJAX pagination, search, and category filtering
├── task.php                  ← Single task view + bid submission form (with pitch character/letter counters) + report task link
├── bid_status.php            ← Freelancer bid history lookup by email
├── report.php                ← Shared report form for tasks (public) and bids (client-only)
├── setup.bat                 ← Automates first-time db.php setup
├── AGENTS.md                 ← Rules for AI coding agents (Codex, Claude Code, etc.) working in this repo
├── composer.json / .lock     ← PHPMailer dependency manifest
│
├── auth/
│   ├── admin_login.php       ← Admin login (rate limited)
│   ├── client_login.php      ← Client login (rate limited)
│   ├── client_register.php   ← Client registration
│   └── logout.php            ← Handles logout for both roles (session-isolated, cookie-safe)
│
├── client/
│   ├── dashboard.php         ← Client overview + task table
│   ├── post_task.php         ← Post a new task with real-time Unicode title & description validation
│   ├── task_bids.php         ← View bids on a task, accept/reject, report bids
│   ├── edit_task.php         ← Edit an open task
│   └── edit_profile.php      ← Update name, email, password
│
├── admin/
│   ├── dashboard.php         ← Platform-wide stats + recent tasks + pending reports
│   ├── tasks.php             ← All tasks with search + status filter + pagination
│   ├── bids.php              ← All bids with search + status filter
│   ├── clients.php           ← Manage clients, activate/deactivate
│   └── reports.php           ← Review and manage task/bid reports
│
├── actions/
│   ├── accept_bid.php        ← Accepts a bid, rejects all others, sets task in_progress, emails accepted freelancer
│   ├── reject_bid.php        ← Rejects a single bid, emails rejected freelancer
│   ├── update_task_status.php← Marks task as completed
│   ├── delete_task.php       ← Deletes an open task
│   └── update_report_status.php ← Admin reviews or dismisses a report
│
├── includes/
│   ├── db.php                ← MySQLi connection, CSRF helpers, rate limiting helpers
│   ├── email_helper.php      ← Abstract API email verification helper
│   ├── mailer.php            ← PHPMailer wrapper for bid accepted/rejected notifications
│   ├── auth_admin.php        ← Admin session guard
│   ├── auth_client.php       ← Client session guard
│   ├── header.php            ← Shared HTML header, security headers, navbar + context bar
│   └── footer.php            ← Shared HTML footer
│
├── css/
│   └── style.css             ← Consolidated global stylesheet, utility classes, dynamic counters, layouts
│
├── sql/
│   └── bidboard.sql          ← Full schema + default admin seed
│
├── vendor/                   ← Composer packages (PHPMailer) — gitignored, generated by `composer install`
│
└── .github/workflows/
    ├── manual-release.yml    ← Manual release with auto-generated changelog
    ├── php.yml               ← Main workflow to check syntax and builds
    └── commit-check.yml      ← Ensures PRs follow commit message conventions
```

---

## Features

### Core

- **Task lifecycle** — open → in progress → completed
- **Bid lifecycle** — pending → accepted / rejected
- **Asynchronous UI (AJAX)** — live homepage search, category filter dropdown, and pagination powered by modern `fetch()` and backend JSON responses without full-page reloads
- **Auto-reject** — accepting one bid automatically rejects all others on the same task
- **Duplicate bid prevention** — the same email cannot bid twice on the same task
- **Bid history** — freelancers check their bid status by entering their email (no account needed)
- **Admin search** — search tasks by title, description, or client; search bids by freelancer name, email, or task
- **Client edit profile** — update name, email, and password (requires current password confirmation)
- **Client edit task** — edit title, description, category, budget, and deadline while the task is open
- **Context bar** — a colored strip below the navbar shows the current role (guest / client / admin)
- **Currency display** — all amounts are shown in **Rs.** (Nepalese Rupees)
- **Email notifications** — freelancers receive an email via PHPMailer/Gmail SMTP when their bid is accepted or rejected

### Reporting

- **Report a task** — anyone can report a public task from the task detail page (no login needed)
- **Report a bid** — clients can report bids on their own tasks from the bid management page
- **Admin review** — admins see all reports on a dedicated page with pending / reviewed / dismissed status
- **Dashboard alert** — the pending report count is shown on the admin dashboard with a quick link

### Security

- **CSRF protection** — every state-changing form includes a per-session token, verified server-side before processing
- **Session isolation** — admin, client, and public/guest pages each use separate, explicitly named PHP sessions to prevent cross-role interference
- **Session regeneration** — a fresh session ID is issued on every successful login to prevent session fixation
- **Session cookie hardening** — logout explicitly expires the session cookie for the correct role only, so logging out of one role never affects another active session in the same browser
- **Rate limiting** — login attempts and bid submissions are capped per session within a rolling time window, backed by secondary IP tracking
- **Security headers** — `X-Content-Type-Options`, `X-Frame-Options`, and `X-XSS-Protection` headers are set on every page
- **Password hashing** — bcrypt via `password_hash()` / `password_verify()`
- **Prepared statements** — all database queries use parameterized queries throughout

### Validation & CSS Standards

- **Server-Side & Client-Side Synchronized Validation** on every form field (required fields; defense-in-depth even if JS is bypassed)
- **Real-Time Quality Validation (Regex & Character Counters):**
  - **Task Title:** must start with a letter (`^\p{L}`), minimum 10 characters, and at least 6 letters.
  - **Task Description:** minimum 30 characters and at least 20 letters.
  - **Bid Pitch:** real-time character and letter counting with live feedback.
- **Negative Value Prevention** — budget and bid price fields block a leading minus sign at input level, along with server-side `<= 0` checks.
- **Clean Styling Standards** — 100% legacy inline styles (`style="..."`) refactored into a single consolidated `style.css` using modular, reusable utility classes.
- **Email Deliverability Check** — Abstract API integration to verify real email deliverability (applied at client registration, profile changes, bid submission, and report submission).

### Developer Tooling & Workflow

- **High-Performance Editor Setup:** configured for lightweight development with Zed/LSP for instant response times and low RAM footprint.
- **Advanced Git Configuration:** global setup using custom aliases (`git save`, `git undo`, `git lg`, `git st`), rebase workflows (`pull.rebase=true`), histogram diffs, and automatic CRLF line-ending handling for Windows/XAMPP environments.
- **AGENTS.md** — a strict ruleset read by AI coding agents (Codex, Claude Code, etc.) to keep any AI-assisted contributions aligned with the project's plain-PHP philosophy.
- **Conventional Commits** — enforced on pull requests via GitHub Actions.

---

## Known Bugs & Areas to Watch

### Freelancer identity

Freelancers are identified **only by email** — there is no account or password. Anyone who knows a freelancer's email can look up their bid history. Duplicate bid prevention is email-based only.

### Admin password

The default admin password hash in `bidboard.sql` is a known placeholder. If login fails with `admin123`, run the `UPDATE` query in `sql/bidboard.sql` to replace it with a working hash.

### File paths

All internal links and redirects use the absolute path `/bidboard/`. If the folder inside `htdocs` is named anything other than `bidboard`, redirects and asset links will break.

### Email verification API limits

Abstract API's free tier caps at **100 requests/month**. If exceeded, `is_email_deliverable()` fails open (`true`) so the app keeps working.

### Email notification scope

`accept_bid.php` only emails the freelancer whose bid was explicitly accepted — it does **not** currently email auto-rejected freelancers.

---

## Changelog

| Version | Changes                                                                                                                             |
| ------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| v1      | Core platform — auth, task posting, bid submission, admin panel                                                                     |
| v2      | Duplicate bid prevention, admin search, client edit profile                                                                         |
| v3      | Freelancer bid history, client edit task, context bar                                                                               |
| v4      | CSRF protection, session regeneration, real-time validation, Abstract API email verification                                        |
| v5      | Reporting system — task reports, bid reports, admin review panel, session isolation fix                                             |
| v6      | Pagination on admin tasks + public homepage, CSRF on admin delete, filter preservation                                              |
| v7      | Rate limiting, security headers, session cookie logout fix, negative value prevention, Rs. currency display                         |
| v8      | PHPMailer email notifications on bid accept/reject, AGENTS.md guardrails                                                            |
| v9      | CSS refactoring (removed inline styles), AJAX search/pagination, strict letter & title validation, developer workflow optimizations |

---

## Author

Built as a college project by **Sarthak** — BCA, Kathmandu.
