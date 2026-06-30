# LegalOps

A login system + practice-management dashboard for a small law firm, built
on plain PHP + MySQL and [PHPAuth](https://github.com/PHPAuth/PHPAuth)
(vendored directly — no Composer needed).

## Setup (XAMPP / Windows)

1. Copy this `legalops` folder into `C:\xampp\htdocs\`.
2. Start Apache + MySQL in the XAMPP control panel.
3. **Fresh install:** open phpMyAdmin → create a database called `legalops`
   → **Import** → select `sql/legalops.sql`. This creates every table
   (PHPAuth's, cases, clients, and billing/invoicing) and seeds demo data
   for all of them.
   **Already running an earlier copy?** Don't re-import the whole file —
   it'll wipe your data. Instead run `sql/migrations/002_clients_module.sql`
   (and any other migration added since), which only adds what's new and
   is safe to run on top of what you already have.
4. If your DB user/password or folder name differ from the defaults,
   edit `config/config.php`.
5. **Invoicing (Billing module):** run `composer install` in the
   `legalops` folder to pull in `dompdf/dompdf`, the one dependency the
   invoice PDF renderer needs. The rest of the app stays plain PHP — see
   [`INVOICING.md`](INVOICING.md) for the full setup and how it's wired.
6. Visit `http://localhost/legalops`.

**Demo login:** `demo@legalops.local` / `LegalOps@123`

## Clients module

Covers individual, family (HUF), proprietorship, partnership, OPC,
private/public limited company, association and trust clients:

- **Onboarding** — each client moves through draft → KYC pending → KYC
  verified → active (or inactive), tracked on `clients.php` and the
  client's own page.
- **KYC** — PAN, address, email and phone for the client itself, plus a
  separate KYC record per leader (PAN, ID proof, DIN where relevant).
- **Leadership, with change history** — what "leadership" means adapts
  to the entity type (Karta for a family, Partners for a partnership,
  Directors for a company, Trustees for a trust, etc — see
  `includes/client_types.php`). For single-leader types (individual,
  family, proprietorship), adding a new leader automatically closes out
  the previous one with an end date, so there's an audit trail of who
  led the client and when. Multi-leader types just accumulate, with an
  explicit "End" action per leader.
- **Secondary contacts** — separate from leadership: accountants,
  ops contacts, anyone else the firm deals with day to day.
- **Documents** — uploaded files (PDF/JPG/PNG/DOC/DOCX, 5MB cap) are
  stored outside the web root's reach at `uploads/clients/{id}/` under a
  random filename, denied to direct access by `uploads/.htaccess`, and
  only served back through `download.php` after an auth check.
- **Registration numbers** apply to every entity type except Individual
  (and are optional, not required, for Family) — the label changes per
  type: CIN for companies, firm registration/LLPIN for partnerships,
  GST/Shop Act/MSME for proprietorships, and so on.

## Tasks &amp; Calendar module

- **Tasks** — full CRUD, search (title/notes/matter), priority, due
  date/time, and a status lifecycle of pending → in progress → hold →
  done. Putting a task on hold asks for an optional reason, shown on the
  task card. Assignment: admins can assign any task to any team member;
  members can only ever assign tasks to themselves (enforced server-side,
  not just hidden in the UI).
- **Access control** — there are now two roles, `admin` and `member`
  (see the `role` column on `phpauth_users`). Admins see and manage
  every task and every calendar across the firm. Members only ever see
  their own — this is enforced on every query, not just hidden in the
  UI, and double-checked again before any edit/delete/hold action goes
  through. The very first user is auto-promoted to admin by the
  migration; change roles afterwards from **Firm settings → Team & roles**
  (admin only).
- **Court hearing reminders** — set a "Next hearing date" (and optional
  time) on a case in `cases.php`, then schedule
  `cron/hearing_reminders.php` to run once a day. It creates a task for
  whoever opened the case, timed either "tomorrow" or "the day after
  tomorrow" before the hearing — configurable per firm at **Firm
  settings → Court hearing reminders**. Re-running it the same day won't
  create duplicates.
- **Calendar** — a month-grid view of tasks by due date. Members only
  ever see their own calendar; admins get a team-member switcher.
  Two-way sync with Google Calendar and Microsoft Outlook Calendar is
  available per user (each person connects their own account from the
  Calendar page) — tasks with a due date push out as events, and events
  added directly in Google/Outlook import back as tasks. An admin has to
  add OAuth app credentials first, from **Firm settings**, which also
  shows the exact redirect URI to register with Google/Microsoft.
  Schedule `cron/calendar_sync.php` every 10–15 minutes for sync to run
  in the background, in addition to the "Sync now" button.
- Both cron scripts are CLI-only (they refuse to run if hit over HTTP)
  and the `cron/` folder is denied to direct web access either way, same
  pattern as `config/`, `libs/`, and `sql/`.

## Notes

- Sessions, not cookies: `uses_session` is set to `1` in `phpauth_config`
  so PHPAuth stores its session hash in PHP's native `$_SESSION` rather
  than a cookie. This avoids the cookie-path/SameSite headaches that show
  up when hosting under an Apache subfolder on Windows. Flip it back to
  cookie mode in `sql/legalops.sql` (or directly in the `phpauth_config`
  table) if you'd rather use cookies.
- `cookie_secure` is `0` because this runs on plain `http://localhost`.
  Set it to `1` once the app is behind HTTPS.
- No SMTP is configured. Password resets show the reset link on screen
  instead of emailing it — fine for local use; wire up `smtp_*` settings
  in `phpauth_config` (or a custom mailer) before going further than that.
- Profile fields (`full_name`, `job_title`, `avatar_color`) live as extra
  columns on `phpauth_users`, since PHPAuth's `addUser()`/`updateUser()`
  write straight into named columns on that table.
- Documents in the sidebar is still a placeholder page (`modules.php`).
  Dashboard, Cases, Clients, Tasks, Calendar, and Billing (invoicing for
  India + GCC — see `INVOICING.md`) are fully wired up. Say the word and
  Documents can be built out next.
