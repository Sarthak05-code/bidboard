# AGENTS.md — BidBoard Project Rules

This file governs how any AI coding agent (Codex, Claude Code, Cursor, Copilot Workspace, etc.) must behave when working in this repository. These rules are **non-negotiable** unless the project owner explicitly overrides them in a direct prompt for a single task.

---

## 1. Core Philosophy — DO NOT VIOLATE

BidBoard is an academic project with one deliberate, central constraint:

> **Plain PHP, HTML, CSS, and vanilla JavaScript only. No frameworks. No Composer packages, except the two explicitly listed in section 3.**

This is not a limitation to "fix" or "modernize." It is the entire point of the project. The evaluator expects to see raw PHP logic, not framework abstractions.

**NEVER, under any circumstances, without explicit direct instruction from the project owner in that exact session:**

- Install or suggest Laravel, Symfony, CodeIgniter, Slim, or any PHP framework
- Install or suggest a PHP router library (e.g. FastRoute, AltoRouter)
- Install or suggest a templating engine (e.g. Twig, Blade, Smarty)
- Install or suggest an ORM (e.g. Eloquent, Doctrine)
- Convert `.php` files to use Blade syntax, Twig syntax, or any templating DSL
- Install a frontend framework or build tool (React, Vue, Next.js, Vite, Webpack, Tailwind CLI)
- Add npm/Node.js as a project dependency for anything other than optional local tooling explicitly requested
- Restructure the folder layout to match a framework convention (e.g. `app/`, `resources/views/`, `routes/web.php`)
  If asked to "improve" or "modernize" the codebase in a vague way, the agent must **ask for clarification** on what specifically needs improving, rather than defaulting to introducing a framework or build tool.

---

## 2. Existing Architecture — Preserve This Structure

The project uses this fixed structure. Do not reorganize it:

```
bidboard/
├── index.php, task.php, bid_status.php, report.php
├── auth/        → login, registration, logout (session-based, role-isolated)
├── client/      → client-only pages (guarded by includes/auth_client.php)
├── admin/       → admin-only pages (guarded by includes/auth_admin.php)
├── actions/     → POST-only backend handlers, no HTML output, always redirect
├── includes/    → db.php, auth guards, header.php, footer.php, mailer.php, email_helper.php
├── css/         → style.css only, no CSS framework
├── sql/         → schema files
└── vendor/      → Composer packages (see section 3) — gitignored
```

- Pages that render HTML **must** include `includes/header.php` at the top and `includes/footer.php` at the bottom.
- Pages must set `$page_title` and `$nav_context` before including `header.php`.
- `actions/*.php` files never render HTML — they process a POST request and `header('Location: ...')` redirect.
- Session names are explicit and role-specific: `bidboard_client`, `bidboard_admin`, `bidboard_public`. Never consolidate these into one session name.

---

## 3. Approved Composer Packages — This Is the Full List

Composer is used **only** for these two packages. Do not add any other package without explicit direct instruction:

- `phpmailer/phpmailer` — for sending bid accepted/rejected notification emails via Gmail SMTP
- (Previously considered but replaced with raw cURL: `abstractapi/php-email-validation` — the project uses a hand-written cURL implementation in `includes/email_helper.php` instead. Do not reintroduce this package or suggest replacing the cURL implementation unless explicitly asked.)
  If a task seems to require a new package, **stop and ask the project owner first**, explaining what the package would do and why raw PHP isn't a reasonable alternative. Do not install it preemptively.

---

## 4. Security Patterns — Follow These Exactly, Do Not Simplify

The project has hand-built the following security mechanisms. These exist specifically to demonstrate understanding of the underlying mechanics for academic evaluation — **do not replace them with a library**, and do not remove or weaken them when editing nearby code:

- **CSRF protection**: every state-changing form includes `generate_csrf_token()` in a hidden field; every POST handler calls `verify_csrf_token($_POST['csrf_token'] ?? '')` before processing.
- **Rate limiting**: `is_rate_limited()` (session-based) and `is_ip_rate_limited()` (IP-based backup) in `includes/db.php`, applied to login and bid submission endpoints.
- **Session security**: `session_regenerate_id(true)` on every successful login; explicit `setcookie()` expiration on logout, scoped only to the relevant session name, never affecting other roles' sessions.
- **Password hashing**: `password_hash()` / `password_verify()` with bcrypt. Never suggest a different hashing scheme.
- **Prepared statements**: every SQL query uses `$conn->prepare()` + `bind_param()`. Never write a raw concatenated SQL query, even for "simple" queries.
- **Security headers**: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection` are set at the top of `includes/header.php`. Do not remove.
- **Input validation**: every form field has both server-side PHP validation (the real enforcement layer) and, where practical, real-time client-side JavaScript validation (UX layer only — never the sole line of defense).
  If a change touches any file containing these patterns, preserve them exactly. When merging or refactoring, **never silently drop a function or check** — if a merge conflict occurs here, flag it explicitly rather than auto-resolving.

---

## 5. Database Rules

- Table names and existing schema in `sql/bidboard.sql` are fixed. Do not rename columns or tables without explicit instruction.
- All new queries must use prepared statements with `bind_param()`.
- `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` is enabled in `includes/db.php` — do not disable this.
- Foreign keys use `ON DELETE CASCADE` where already established. Preserve this behavior unless explicitly told to change it.

---

## 6. When Adding a New Feature

1. Confirm the feature can be built in plain PHP/HTML/CSS/JS before considering any package.
2. If a form is involved, it must include: CSRF token, server-side validation, and — where a numeric or name field is involved — the existing real-time JS validation pattern used elsewhere in the project (see `task.php`, `client/post_task.php` for reference patterns).
3. New pages must follow the `$page_title` / `$nav_context` / `header.php` / `footer.php` pattern.
4. New POST handlers in `actions/` must verify CSRF and never output HTML.
5. Currency is always displayed as `Rs.` (Nepalese Rupees), never `$`.

---

## 7. Git and Commit Practices

- **NEVER merge a branch into `main` without explicit, direct confirmation from the project owner for that specific merge.** Creating a branch, making commits, and opening a PR is fine to do proactively when asked to build a feature — merging that PR into `main` is not. Always stop after the branch/PR is ready and wait for the owner to review and say "merge this" before running `git merge` or `git push origin main`.
- **NEVER push directly to `main`.** All changes go through a feature branch first, even for small fixes, unless the project owner explicitly says to push straight to `main` for that specific change.
- **NEVER force-push (`git push --force`) to any branch**, including feature branches, without explicit confirmation — force-pushing can silently discard commits.
- Before opening a PR or asking for a merge, the agent must summarize exactly what changed and why, so the project owner can review before approving — do not merge silently even if the changes seem trivial.
- Commit messages should follow conventional commit prefixes: `feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, `ci:`, `perf:`, `test:`, `build:`, `revert:`, `style:` — case-insensitive, colon may have surrounding spaces.
- When resolving a merge conflict, always read both sides of the conflict markers manually. Never auto-accept "theirs" or "ours" for a whole file without confirming no functionality is silently dropped in the process.
- After any merge, the agent should note which functions/features existed in both branches and confirm both are present in the resolved result.

---

## 8. What To Do If Unsure

If a requested change seems to conflict with any rule in this file, the agent must **stop and ask the project owner directly** rather than making an assumption. Do not silently work around these rules by technically complying while defeating their intent (e.g., "vanilla JS only" does not mean "vanilla JS plus a JS framework loaded from a CDN").

---

**Summary for quick reference**: plain PHP, no frameworks, two approved Composer packages only (PHPMailer, and no others), preserve all existing security patterns exactly, Rs. not $, ask before introducing anything new.
