# LegalOps 2 (lops2)

Enterprise-grade legal practice management system for Indian law firms.
PHP 8 · MySQL · PHPAuth · No Composer required.

---

## Architecture

```
lops2/
├── app/
│   ├── Controllers/     One controller per module (CaseController, TaskController …)
│   └── Core/            Router, View renderer, Autoloader, helpers
├── config/
│   ├── app.php          DB credentials + constants — EDIT THIS
│   ├── bootstrap.php    PDO, PHPAuth, session, helpers
│   └── routes.php       All URL → Controller@method mappings
├── cron/                Daily/scheduled CLI scripts (CLI-only, 403 over HTTP)
├── database/
│   ├── schema.sql       Full schema for a fresh install
│   └── migrations/      Incremental migrations for existing installs
├── libs/                Vendored PHPAuth + calendar_sync engine
├── public/
│   ├── index.php        Front controller — the ONLY web-accessible PHP file
│   ├── .htaccess        Rewrites all traffic through index.php
│   └── assets/          CSS / JS served directly
├── resources/views/     PHP templates (layouts, partials, one folder per module)
└── storage/             Uploaded files (web-denied), logs
```

**Every URL** passes through `public/index.php` → `Router::dispatch()` →
`{Module}Controller@{action}` → `View::render('module/view', $data)`.

Clean URLs: `/cases`, `/cases/3`, `/tasks`, `/calendar`, `/api/search`, etc.
No `.php` in any URL the user ever sees.

---

## Setup (XAMPP / Windows)

1. Place the `lops2` folder inside `C:\xampp\htdocs\`.
2. Start Apache + MySQL in the XAMPP control panel.
3. Edit `config/app.php` if your DB credentials or folder name differ.
4. **Fresh install** — import `database/schema.sql` into a new database called `lops2`.
5. **Existing legalops install** — run the migration files in `database/migrations/` in order
   (they use `IF NOT EXISTS`, so they are idempotent).
6. Visit `http://localhost/lops2/public/`.

**Demo login:** `demo@legalops.local` / `LegalOps@123`

---

## Modules

| Module      | URL               | Notes |
|-------------|-------------------|-------|
| Dashboard   | `/dashboard`      | Per-user KPIs (admin sees firm-wide, members see their own) |
| Cases       | `/cases`, `/cases/{id}` | Full CRUD, court hearing date, document upload, linked tasks |
| Clients     | `/clients`, `/clients/{id}` | All 9 entity types, KYC, leadership history, contacts, documents |
| Tasks       | `/tasks`          | CRUD, search, assign, hold, status lifecycle |
| Calendar    | `/calendar`       | Month grid + Google/Microsoft two-way sync |
| Billing     | `/billing`        | India GST + GCC VAT invoicing (see `INVOICING.md`) |
| Settings    | `/settings`       | Admin-only: hearing reminder offset, OAuth credentials, team roles |
| Profile     | `/profile`        | Password, email, avatar |
| Storage     | `/storage/{scope}/{id}/{file}` | Auth-gated secure file serving |
| Search API  | `/api/search?q=`  | Cases + tasks + clients, JSON |

---

## Roles & access control

Two roles: `admin` and `member` (column on `phpauth_users`).

- **Admin** — sees all tasks, all calendars, firm-wide activity, the Settings page.
- **Member** — sees only tasks they own (assigned to or created by them) and their
  own calendar. The `?user=` calendar param is silently ignored for members.
- All ownership checks are enforced **server-side** on every mutating action, not
  just hidden in the UI.
- The first registered user is auto-promoted to admin by migration 003. Change roles
  from **Settings → Team & roles**.

---

## Court hearing reminder cron

Set a *Next hearing date* on any case. Then schedule:

```
# Windows Task Scheduler
"C:\xampp\php\php.exe" "C:\xampp\htdocs\lops2\cron\hearing_reminders.php"

# Linux / macOS
0 20 * * * php /path/to/lops2/cron/hearing_reminders.php
```

This creates a `priority=high` task N days before the hearing (N = 1 "tomorrow" or
2 "day after tomorrow", configurable in **Firm settings**). Re-running the same day
never duplicates the task.

For background two-way calendar sync, also schedule:

```
# Every 15 minutes
php /path/to/lops2/cron/calendar_sync.php
```

Both cron scripts return `403` if hit over HTTP.

---

## Calendar sync (Google / Microsoft)

1. Create an OAuth 2.0 app in Google Cloud Console **or** Azure / Entra admin center.
2. Add the redirect URI shown in **Firm settings** (`/lops2/public/calendar/callback`).
3. Paste the credentials into **Firm settings → Calendar integrations**.
4. Each user connects their own account from the **Calendar** page.

Two-way sync: tasks with a due date push as calendar events; events created directly
in Google or Outlook import back as tasks.

---

## Notes

- No Composer. PHPAuth is vendored in `libs/` directly.
- Sessions over cookies (set `uses_session = 1` in `phpauth_config`). Avoids the
  Apache subfolder cookie-path issues that show up on XAMPP/Windows. Flip if needed.
- `cookie_secure = 0` for plain `http://localhost`. Set to `1` behind HTTPS.
- No SMTP configured. Password reset links are shown on-screen (fine for local dev).
- Uploaded files live in `storage/uploads/` which is web-denied via `.htaccess`.
  They are served only through `/storage/{scope}/{id}/{file}` after an auth check.
