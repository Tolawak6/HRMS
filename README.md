# ESS HRMS — Human Resource Management System

Internal HR platform for the **Ethiopian Statistical Service (ESS)**. It centralises employee
records, organizational structure, leave, personnel documents, notifications and workforce
reporting for three roles: administrators, HR staff and employees (self-service).

This repository originally contained a flat set of PHP scripts with SQL injection, plain-text
passwords, GET-based deletes and no access control. It has been rebuilt in place around a single
bootstrap, shared modules, prepared statements, server-enforced roles, CSRF protection, an audit
trail and a consistent design system — while keeping the existing database, branding and scope.

---

## 1. Technology

| Layer | Choice |
|---|---|
| Runtime | PHP 8.1+ (developed and tested on PHP 8.5.12) |
| Database | MySQL 5.7+ / 8.0 (tested on MySQL 5.7.29) |
| Data access | `mysqli` with prepared statements (no ORM, no Composer) |
| Frontend | Server-rendered PHP, vendored Bootstrap 5.3.3, Bootstrap Icons SVG sprite, one custom stylesheet |
| Assets | No CDN, no external fonts, no inline scripts (CSP-friendly) |
| Server | Apache (`/.htaccess`) or PHP built-in server (`router.php`) |

Required PHP extensions: `mysqli`, `mbstring`, `fileinfo`, `session`, `json`.

---

## 2. Quick start

```bash
# 1. Configuration
cp .env.example .env
#    edit DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD
#    set APP_ENV=production and APP_DEBUG=false on a real deployment

# 2. Create the database and run migrations (safe to re-run)
php bin/migrate.php --create-database
php bin/migrate.php --status

# 3. Create the first administrator (no default credentials ship with the app)
php bin/create_admin.php \
  --username=admin --first-name=System --last-name=Administrator --email=admin@example.org
#    the password is read from standard input and must be changed at first sign-in

# 4. Optional: demonstration data (refuses to run in production)
php bin/seed_demo.php --confirm

# 5. Health check
php bin/healthcheck.php

# 6. Serve locally
php -S 0.0.0.0:8000 router.php
#    open http://localhost:8000/login.php
```

`router.php` is only for local development. On Apache, the included `.htaccess` files deny direct
access to `app/`, `config/`, `database/`, `bin/`, `includes/`, `storage/`, `uploads/` and dotfiles.
Verify that `AllowOverride All` is enabled for the project directory.

### Production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS enforced.
- Dedicated MySQL user with rights only on the HRMS schema.
- `STORAGE_PATH` outside the document root when possible; otherwise keep the shipped deny rules.
- `SESSION_SAMESITE=Lax` for normal deployments; `None` only for HTTPS cross-site embedding.
- Schedule `mysqldump` backups **and** backups of `storage/photos` and `storage/uploads`.
- Point the web server at the project root (a `public/` split is not required but recommended).
- Keep `storage/` and `.env` readable only by the web-server user.

---

## 3. Roles and permissions

| Capability | Administrator | HR staff | Employee |
|---|:--:|:--:|:--:|
| Dashboard | ✓ | ✓ | ✓ (self-service) |
| Employee records: view / create / edit / archive / restore | ✓ | ✓ | — |
| Own profile and contact details | ✓ | ✓ | ✓ |
| Departments and positions | ✓ | ✓ | — |
| Leave requests (own) | ✓ | ✓ | ✓ |
| Review/approve/reject leave | ✓ | ✓ | — |
| Leave types (configuration) | ✓ | — | — |
| Leave entitlements / balances | ✓ | ✓ | — |
| Documents (all employees) | ✓ | ✓ | own only |
| Reports and CSV export | ✓ | ✓ | — |
| Staff accounts (admin/HR) | ✓ | — | — |
| Audit log | ✓ | — | — |

Permissions are enforced in PHP on every request (`require_roles()`, `can_access_employee()`,
`document_can_access()`), not in the interface. Hiding a button is never the only control.

Additional rules:

- An administrator cannot change their own role or deactivate their own account.
- The last active administrator cannot be deactivated.
- Archived employees cannot sign in, cannot be edited, and have pending leave cancelled.
- Restoring an employee forces a password change at next sign-in.

---

## 4. Feature overview

**Employees** — searchable directory (name, number, username, email), filters for department,
status, sex and archived state, sortable columns, pagination, CSV export of the current view.
Create/edit covers identity, employment, contact and account details with per-field validation.
Profiles show employment, contact, account state, leave balances/history, documents and photo.
Archive/restore preserves history; photos are stored privately and streamed through `photo.php`.

**Organization** — departments and positions with unique names/codes, employee counts, active
toggles, and server-side validation that a position belongs to the selected department.

**Leave** — configurable leave types with default annual entitlement, per-employee/year overrides,
overlap detection, weekend-aware working-day calculation, balance enforcement inside a transaction,
employee cancellation, and approval/rejection with reviewer, timestamp, note and notification.
Rejection requires a reason. Requests cannot span two calendar years.

**Documents** — PDF/DOCX/XLSX/JPG/PNG/WebP up to 5 MB, extension and MIME checks, random stored
names, storage outside the web root, access checks per employee, safe download headers, deletion
that removes both row and file. Original filenames are sanitised and never used as a path.

**Reports** — live headcount, active/on-leave totals, new hires, archived records, distribution by
department, sex, employment type and status, leave usage by type/status, hires by year, and CSV
export. All figures come from the database; nothing is hard-coded.

**Notifications and audit** — in-app notifications for account, leave and document events; an
append-only audit log records actor, action, entity, description, IP address and timestamp.
Passwords and tokens are never written to logs.

**Public site** — a restrained public home/about built on the existing ESS logo and the
institution's existing about text. No new logo was invented and no affiliation is claimed.

---

## 5. Visual design

The palette is sampled from `images/logo.png` rather than chosen by eye:

| Token | Value | Use |
|---|---|---|
| `--ess-blue` / `--ess-primary` | `#2454a8` | links, primary buttons, active states, KPI rails |
| `--ess-green` | `#009c48` | positive metrics, active nav marker, charts |
| `--ess-gold` | `#c09c78` | counters, secondary metrics, notices |
| `--ess-green-ink` | `#06763a` | green text and success buttons (raw green is 3.6:1) |
| `--ess-gold-ink` | `#8a6033` | tan text (raw tan is 2.5:1) |
| `--ess-gold-strong` | `#a9773f` | tan data bars (clears 3:1 on the track) |
| `--ess-sidebar` | `#143c70` | navigation rail, derived from the logo blue |

The three logo hues appear together as a 3px rule on the sidebar header, under page
titles and on the sign-in panel — a single, restrained brand device instead of
decorating every surface.

Contrast targets (WCAG 2.1 AA): body text 15.0:1 on white, muted text 5.5:1,
sidebar links 9.4:1, sidebar section labels 5.7:1, white-on-primary buttons 7.2:1.
Where a raw logo hue cannot carry text, an ink variant is used and the raw hue is
reserved for icons, rules and data marks.

## 6. Security controls

- Prepared statements everywhere (`Database` wrapper); identifiers used in dynamic SQL are
  validated/quoted.
- Server-side validation and normalisation for every write; field-level error messages.
- `password_hash()` / `password_verify()` only; legacy plain-text rows are transparently upgraded
  on the next successful sign-in; `must_change_password` is enforced before any other page.
- CSRF token on every POST, verified centrally in `app/bootstrap.php`.
- Session hardening: HTTP-only, SameSite=Lax, strict mode, ID regeneration on login and
  periodically, idle and absolute timeouts.
- Login throttling by username and IP; generic error messages (no account enumeration).
- Role checks on every protected route, including object-level checks for employees and documents.
- Upload validation by extension, detected MIME and image dimensions; private storage; random names.
- Security headers: CSP, nosniff, referrer policy, permissions policy, no-store on HTML.
- Output escaping via `e()`; CSV formula injection neutralised with `csv_safe()`.
- Audit logging without secrets; application logs redact password/token-like context keys.
- Apache deny rules plus `router.php` keep `.env`, source, logs and uploads unreachable.

---

## 7. Database

Three additive migrations are included:

| Migration | Purpose |
|---|---|
| `2024_01_01_000001_baseline_legacy.php` | Normalises the legacy `users` table, adds `username`, roles, timestamps, indexes |
| `2024_01_02_000002_hr_modules.php` | Departments, positions, employment fields, leave, documents, notifications, audit, login attempts |
| `2024_01_03_000003_constraints_indexes.php` | Normalises emails, unique email index (when data allows), status/document indexes, document foreign keys (when data allows) |

Properties:

- Additive and re-runnable; `bin/migrate.php` tracks applied migrations in `schema_migrations`.
- No table drops, no data resets. Destructive operations are not part of any migration.
- `leave_balances` has `UNIQUE (user_id, leave_type_id, year)` so entitlement upserts are safe.
- `users` has unique `username` and `employee_number`; `email` becomes unique when no duplicates exist.
- If legacy data contains duplicate emails or orphan attachments, the third migration records a note
  and skips that constraint instead of deleting rows. Resolve the data and re-run.

Backup before upgrading:

```bash
mysqldump --single-transaction --routines --triggers hrms > hrms-$(date +%F).sql
tar -czf hrms-storage-$(date +%F).tar.gz storage/
```

---

## 8. Project structure

```
.
├── index.php, login.php, logout.php      entry points (index redirects)
├── dashboard.php                          role-aware dashboard
├── profile.php, notifications.php         self-service
├── photo.php                              authenticated photo stream
├── home.php, about_us.php                 public site
├── account/password.php                   forced/voluntary password change
├── employees/                             directory, create, edit, view, CSV, photo upload
├── organization/                          departments, positions
├── leave/                                 self-service, request, review, types, balances
├── documents/                             register and protected download
├── reports/                               summary, department, demographics, leave, export
├── admin/                                 staff accounts, audit log
├── includes/layout, includes/partials     shell, flash, errors, pagination, employee form
├── app/                                   bootstrap, auth, validation, modules
├── config/app.php                         configuration loader
├── database/                              schema helper, migrator, migrations
├── bin/                                   migrate, create admin, seed demo, health check
├── assets/                                vendored Bootstrap, icons, app.css, app.js, avatar
├── images/                                ESS logo (existing asset)
├── storage/                               logs, documents, photos (not web-accessible)
├── uploads/                               legacy location; blocked from direct access
├── router.php                             development server router
└── .htaccess                              Apache hardening
```

---

## 9. Testing performed

All commands below were executed in the development environment (PHP 8.5.12, MySQL 5.7.29,
PHP built-in server) against a disposable database seeded with fictional records.

### Static checks

```bash
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
node --check assets/js/app.js
php bin/healthcheck.php
```

Result: **70 PHP files — no syntax errors**; JavaScript parses; health check passes all runtime,
storage and database checks (3 migrations applied, unique email index, no duplicate employee
numbers, no orphaned attachments).

### Migrations

```bash
php bin/migrate.php --create-database   # 3 migrations applied
php bin/migrate.php                     # 0 applied, 3 already applied (idempotent)
php bin/migrate.php --status
```

### HTTP checks (authenticated sessions)

Every page below returned **200** with no new PHP warnings in `storage/logs/php-error.log`:

`dashboard.php`, `employees/index.php` (including department/status/sex/archived/sort filters and
archived view), `employees/create.php`, `employees/edit.php?id=22`, `employees/view.php?id=22`,
`employees/export.php`, `photo.php?id=22`, `organization/departments.php`,
`organization/positions.php` (+ department filter), `leave/index.php`, `leave/request.php`,
`leave/requests.php` (+ status/year filters), `leave/types.php`,
`leave/balances.php?user=22&year=2026`, `documents/index.php` (+ employee filter),
`documents/download.php?id=1`, `reports/index.php`, `reports/by_department.php`,
`reports/demographics.php`, `reports/leave.php?year=2026`, `reports/export.php`,
`admin/users.php` (+ filters), `admin/audit.php` (+ action/date filters), `profile.php`,
`notifications.php`, `home.php`, `about_us.php`, `login.php`.

Legacy entry points (`admin_dashboard.php`, `hr_dashboard.php`, `ess_dashboard.php`,
`hr_staff_dashboard.php`, `ess_staff_dashboard.php`, `overview.php`) redirect: authenticated users
to the new dashboard/reports, anonymous visitors to the login page.

### Role enforcement

| Request | admin | HR staff | employee |
|---|---|---|---|
| `employees/index.php` | 200 | 200 | **403** |
| `admin/users.php` | 200 | **403** | **403** |
| `admin/audit.php` | 200 | **403** | **403** |
| `leave/types.php` | 200 | **403** | **403** |
| `leave/requests.php` | 200 | 200 | **403** |
| `reports/index.php` | 200 | 200 | **403** |
| `leave/balances.php` | 200 | 200 | **403** |
| `documents/download.php?id=4` (another employee's file) | 200 | 200 | **403** |
| `employees/create.php` | 200 | 200 | **403** |

### Functional and security scenarios

- **Create employee:** invalid submit re-renders with field errors; non-existent department/position
  rejected; valid submit created the record and redirected to the profile; duplicate employee number
  returned *“This employee number is already assigned.”*; password stored with `password_hash()`.
- **Edit/archive/restore:** edits persisted; archive set `archived_at`, disabled sign-in and kept
  history; restore reactivated the account and forced a password change. Self-service login while
  archived is refused.
- **Search and export:** search found matching records; a no-match query rendered the empty state;
  CSV export returned `text/csv`, a BOM, a correct header row and the matching record.
- **Leave:** employee submitted a 5-working-day request; a cross-year request was rejected; a request
  exceeding the remaining balance was rejected with *“exceeds the available balance of 12 working
  day(s) for 2026”*; cancellation worked; HR approval updated the balance by 3 days and created a
  notification; rejection without a reason was refused; rejection with a reason persisted reviewer,
  timestamp and note.
- **Documents:** upload stored a random filename outside the web root, download returned the exact
  bytes with `Content-Disposition: attachment`, deleting removed both the row and the file. A PHP
  file disguised as a document was refused; a PDF uploaded as a profile photo was refused.
- **Profile:** invalid phone number rejected; valid contact details persisted; photo upload stored
  privately and `photo.php` returned `image/png` with a private cache header.
- **Notifications:** “mark all as read” set the unread counter to zero.
- **CSRF:** a POST without a token was rejected and changed nothing.
- **SQL injection:** `' OR '1'='1` as a username failed authentication with a generic error.
- **XSS:** `<script>alert(1)</script>` supplied through the search parameter was rendered escaped.
- **Login throttling:** after five failures the next attempt returned the throttling message.
- **Direct file access:** `.env`, `app/`, `config/`, `storage/logs/`, migrations and stored uploads
  returned **404** through the development router; static assets and the logo returned 200.

### Not verified

- No graphical browser was available in the build environment. Layout, colour contrast and
  keyboard behaviour were reviewed in code but **not visually verified** in Chrome/Firefox/Safari.
- External-SVG `<use>` icon references render in current Chrome/Firefox/Edge; very old Safari
  versions may not display sprite icons.
- No automated unit/integration test suite (no Composer/PHPUnit in the original project). Checks
  above were executed manually with `php -l`, `bin/healthcheck.php` and `curl`.
- Email delivery, SMS, payroll, recruitment/onboarding, biometric attendance and multi-language
  UI are out of scope and were not added.

---

## 10. Troubleshooting

### Sign-in returns to the login page without an error

Check `storage/logs/app.log`:

- `Session cookie missing on POST` — the browser blocked or dropped the session cookie.
  This happens when the app is opened inside a cross-site iframe and the cookie is `SameSite=Lax`.
  Set `SESSION_SAMESITE=None` (HTTPS only) and reload the page.
- `CSRF validation failed` — the form was submitted with a stale token, usually a tab left open
  across a deployment or after `php bin/migrate.php`. Reload the page and submit again.
- `Too many failed sign-in attempts` — throttling is active. Wait `LOGIN_WINDOW_MINUTES` (default 15),
  or clear it deliberately: `DELETE FROM login_attempts;`.

### "This account is inactive. Please contact Human Resources."

The account has `is_active = 0` or an `archived_at` timestamp. HR can restore it from the employee
record (**Employees → open record → Restore**).

### Password accepted but the dashboard is refused

The account has `must_change_password = 1`; every page redirects to `account/password.php` until a
new password is set. That is the intended flow for accounts created by HR.

### Embedded previews (iframe hosting)

A cross-site iframe requires the cookie to be `SameSite=None; Secure`, so the app must be reached
over HTTPS and `SESSION_SAMESITE=None` must be set. Plain `http://` development should keep
`SESSION_SAMESITE=Lax`; a `None` cookie without HTTPS is rejected by browsers and the browser
console will report it.

## 11. Known limitations and follow-ups

1. **Photos of the original sample employees** in `uploads/images/` were removed from the
   repository because they appeared to be personal photographs and were unreferenced by the data.
2. Document/photo storage defaults to `storage/` inside the project. It is protected by
   `.htaccess` and `router.php`, but moving `STORAGE_PATH` outside the web root is still preferable.
3. Employee selects in the document and entitlement screens load up to 1,000 records; a searchable
   picker is recommended once headcount grows.
4. The audit log is append-only by application convention, not by database trigger; a DBA can add
   a trigger or a restricted database user if tamper resistance is required.
5. Notifications are in-app only; there is no email/SMS transport yet.
6. Leave requests may not cross calendar years; this is deliberate but should be confirmed against
   ESS leave policy.
7. The interface is English-only; Ethiopic/Amharic labels would need translation files.
8. CSV exports are capped at 10,000 rows to protect memory; scheduled reports would need batching.

---

## 12. Upgrading an existing installation

1. Back up the database and `storage/` (command above).
2. Deploy the new files; keep your existing `.env` values (or create `.env` from `.env.example`).
3. Run `php bin/migrate.php --status`, then `php bin/migrate.php`.
4. Run `php bin/healthcheck.php`.
5. Sign in. Legacy plain-text passwords are upgraded automatically on the first successful login;
   accounts created by HR are marked `must_change_password`.
6. Old dashboard URLs redirect; old `edit_*.php` / `delete_*.php` scripts were removed because they
   allowed unauthenticated and cross-role modifications.

No table is dropped and no employee record is deleted by the upgrade.
