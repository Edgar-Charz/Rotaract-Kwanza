# Rotaract Kwanza

Official website of Rotaract Club of Kwanza — a public site (events, projects,
donations/sponsorship, member applications) plus an admin dashboard for
running the club day-to-day.

## Requirements

- PHP 8.1+ with the `mysqli`, `fileinfo`, and `mbstring` extensions
- MySQL or MariaDB
- Apache with `mod_rewrite`, and `AllowOverride All` on the site's document
  root — the bundled `.htaccess` files are what block direct web access to
  `config/`, `classes/`, `includes/`, `assets/database/`, and `cron/`. On
  nginx or any host that doesn't honor `.htaccess`, those directories must be
  blocked at the server-config level instead, or they're directly browsable.

## Local setup (XAMPP or similar)

1. Place the project in your webroot (e.g. `htdocs/Rotaract_Kwanza`).
2. Create a database and import `assets/database/rotaract_kwanza.sql`.
3. Edit `config/Database.php` with your DB host/username/password/database
   name. It ships with XAMPP's local defaults (`root`, no password) — **this
   must be changed before deploying anywhere public.**
4. If you're picking up a database older than the SQL dump, bring it current
   by running the migrations instead of re-importing:

   ```bash
   php assets/database/migration_runner.php
   ```

   (Safe to run any time — each migration tracks itself in
   `schema_migrations` and skips if already applied.)
5. Visit `/admin/register.php` to create the first admin account (full
   `super_admin` access). This page only works once — the moment any admin
   account exists it permanently disables itself and redirects to the login
   page. Additional admins are created afterwards from inside the dashboard
   (Settings → Admins, `super_admin` only).
6. Make `admin/uploads/` writable by the web server user — member photos,
   event/project images, and gallery uploads are stored there.

## Before hosting this publicly

- [ ] `config/Database.php` has real production DB credentials, not the
      local defaults.
- [ ] `.htaccess` is actually being honored (`AllowOverride All` on Apache,
      or the equivalent block rules configured server-side otherwise) — try
      hitting `/config/Database.php` or `/assets/database/rotaract_kwanza.sql`
      directly in a browser; both should 403.
- [ ] `php.ini` on the host has `display_errors = Off` in production (the
      app also forces this itself in `includes/error_handler.php`, but
      matching it at the php.ini level is good practice too).
- [ ] `upload_max_filesize` / `post_max_size` are at least 5MB (the app's
      image upload cap) or uploads will fail silently.
- [ ] Outgoing mail actually delivers — the app uses PHP's built-in `mail()`
      (see `classes/Mailer.php`) for every confirmation/notification email
      (contact form, join applications, RSVPs, donations/pledges, etc.).
      Many hosts need this explicitly configured, and mail without
      SPF/DKIM tends to land in spam — swapping in real SMTP is a
      contained change if needed (`Mailer`'s structure mirrors PHPMailer
      on purpose).
- [ ] The birthday-email cron job is scheduled — it isn't triggered by the
      app itself. See the command in `cron/send_birthday_emails.php`.
- [ ] Don't upload `assets/database/seed_test_data.php`,
      `seed_leadership_history.php`, or `drop_leadership_history.php` to the
      production server — they're dev/seed-only and destructive; `.htaccess`
      blocks web access to them, but simply not deploying them is a better
      guarantee.
- [ ] `admin/uploads/` and `assets/database/backups/` (created by
      Settings → DB Backup) persist across deploys and are included in
      whatever backup process you use for the server — they're intentionally
      excluded from git (see `.gitignore`).
