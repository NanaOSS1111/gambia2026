# Gambia 2026 — Delegate Registration System

PHP-based delegate registration and management portal for the GAMBIA 2026 event, hosted at `gambia2026.ngocsocd.org`.

## Features

- **Public registration form** — delegate details, photo upload, supporting documents
- **Duplicate detection** — prevents double submissions on the same email/passport
- **Email confirmation** — PHPMailer-powered confirmation with PDF attachment
- **PDF generation** — nomination letters and delegate badges via Dompdf
- **Admin panel** — review, approve/reject, and export registrations (CSV)
- **Rate limiting** — submission throttle to prevent spam
- **GDPR cleanup** — scheduled removal of old personal data
- **reCAPTCHA v3** — bot protection on the public form

## Tech Stack

| Layer | Tool |
|---|---|
| Language | PHP 8+ |
| Database | MySQL (PDO) |
| Email | PHPMailer |
| PDF | Dompdf |
| Server | Apache (`.htaccess`) |
| Hosting | one.com shared hosting |
| Deploy | GitHub Actions → SFTP |

## Local / Server Setup

### 1. Clone and install dependencies

```bash
git clone git@github.com:NanaOSS1111/gambia2026.git
cd gambia2026
composer install
```

### 2. Configure credentials

```bash
cp db.example.php db.php
cp mail_config.example.php mail_config.php
```

Edit `db.php` with your MySQL credentials and `mail_config.php` with your SMTP details. **Never commit these files.**

### 3. Import the database schema

```bash
mysql -u youruser -p yourdb < setup.sql
```

### 4. Create the first admin account

Visit `/setup_admin.php` in your browser (one-time setup, then locked).

### 5. Uploads folder

The `uploads/` directory is created automatically on first run. Ensure the web server has write permission to it.

## Deployment (GitHub Actions)

Every push to `main` triggers an automatic SFTP deploy to one.com via [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml).

Required secrets in **Settings → Secrets and variables → Actions**:

| Secret | Description |
|---|---|
| `SFTP_HOST` | one.com SFTP hostname |
| `SFTP_PORT` | `22` |
| `SFTP_USER` | SFTP username |
| `SFTP_PASS` | SFTP password |
| `SFTP_PATH` | Remote path to the subdomain folder |

Files excluded from deployment: `uploads/`, `db.php`, `mail_config.php`, `error_log` — these live only on the server.

## Key Files

| File | Purpose |
|---|---|
| `index.php` | Public registration form |
| `process.php` | Form submission handler |
| `admin.php` | Admin login + dashboard |
| `view.php` | Single registration detail view |
| `export_csv.php` | CSV export of registrations |
| `badge.php` | Delegate badge PDF endpoint |
| `confirmation_pdf.php` | Confirmation letter PDF |
| `nomination_letter_pdf.php` | Nomination letter PDF |
| `mailer.php` | Shared email sending logic |
| `db.php` | Database connection (not in git) |
| `mail_config.php` | SMTP + reCAPTCHA config (not in git) |
| `setup.sql` | Database schema |
