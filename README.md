# LegalOps

A login system + practice-management dashboard for a small law firm, built
on plain PHP + MySQL and [PHPAuth](https://github.com/PHPAuth/PHPAuth)
(vendored directly — no Composer needed).

## Setup (XAMPP / Windows)

1. Copy this `legalops` folder into `C:\xampp\htdocs\`.
2. Start Apache + MySQL in the XAMPP control panel.
3. Open phpMyAdmin → create a database called `legalops` → **Import** →
   select `sql/legalops.sql`. This creates every table (PHPAuth's tables
   plus the app's `legalops_*` tables) and seeds a demo login + sample
   cases/tasks/activity/billing entities/invoice.
4. If your DB user/password or folder name differ from the defaults,
   edit `config/config.php`.
5. **Invoicing (Billing module):** run `composer install` in the
   `legalops` folder to pull in `dompdf/dompdf`, the one dependency the
   invoice PDF renderer needs. The rest of the app stays plain PHP — see
   [`INVOICING.md`](INVOICING.md) for the full setup and how it's wired.
6. Visit `http://localhost/legalops`.

**Demo login:** `demo@legalops.local` / `LegalOps@123`

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
- Clients / Tasks / Calendar / Documents in the sidebar are placeholder
  pages (`modules.php`) — Dashboard, Cases, and Billing (invoicing for
  India + GCC — see `INVOICING.md`) are fully wired up. Say the word and
  any of the remaining placeholders can be built out next.
