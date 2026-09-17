# System App

Laravel 13 starter application for a school visitor system. It is configured to work on shared hosting without Node.js, Vite, or npm dependencies. Tailwind CSS is loaded from its CDN in `resources/views/home.blade.php`.

## Visitor data model

Google Sheets uses separate `Visitor Profile` and `Visit Logs` tabs. `Visitor Profile` stores one profile per person; `Visit Logs` stores one row per visit, with its own check-in, check-out, status, accountability, and creation timestamp. The Apps Script `ensureDatabase()` migration creates these tabs and moves any existing combined visit-log columns into `Visit Logs`. Subsequent check-ins append a new `VISIT-######` row, while checkout updates only the latest open visit. A visitor lookup response includes `visit_history` so the complete history can be displayed without changing the profile row.

After updating `google-apps-script/Code.gs`, paste it into the bound Apps Script project and deploy a new web-app version. Run `setupDatabase()` once, then run `installTrigger()` once if the form-submit trigger is not already installed.

## Install from scratch

Requirements: PHP 8.3+, Composer 2.x, and MySQL.

```bash
composer create-project laravel/laravel system-app "^13.0"
cd system-app
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Then set the MySQL placeholders in `.env` and run locally:

```bash
php artisan serve
```

Open http://localhost.

## Shared hosting deployment

1. Upload the project files and install Composer dependencies with `composer install --no-dev --optimize-autoloader` on the server, or upload the generated `vendor` directory.
2. Point the domain/document root at the project's `public` directory.
3. Copy `.env.example` to `.env`, set real database credentials, and run `php artisan key:generate` once.
4. Make `storage` and `bootstrap/cache` writable by the web server.
5. Run `php artisan config:cache` after changing production environment values.

The public entry point is `public/index.php`; do not expose the project root as the web document root.
