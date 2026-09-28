# MEDGRID

MEDGRID is a real-time emergency medical coordination platform. The current local implementation includes **M0 Foundation**, **M1 Dispatch**, and **M2 Ambulance**: tenant-aware identity and authorization, audited dispatch, accepted crew missions, patient encounters, append-only vitals, versioned assessments, Critical Mode, private realtime updates, and deliberately limited offline vital capture. It does not implement hospital destination workflows, Romanian 112 integration, autonomous medical decisions, or production deployment controls.

> Development status: M2 AMBULANCE implemented for local development. MEDGRID is not production-ready, clinically validated, certified, or connected to governmental or hospital systems.

## Stack

- PHP 8.3 and Laravel 13
- PostgreSQL 17 with PostGIS 3.5
- Redis with Predis
- Laravel Reverb
- Blade, Alpine.js, Tailwind CSS, Lucide, and Vite
- PHPUnit against PostgreSQL (`medgrid_test`)

Product and engineering requirements live in `docs/MASTER_SPEC.md`, `AGENTS.md`, `docs/milestones/`, and `docs/M2-AMBULANCE.md`.

## Prerequisites

The primary local environment is Windows with Laragon. Install:

- PHP 8.3+
- Composer 2
- Node.js 22 LTS and npm
- PostgreSQL 17
- PostGIS for PostgreSQL 17 (Stack Builder is supported)
- Redis listening on `127.0.0.1:6379`

Docker is not required for local M2 development.

## PostgreSQL and PostGIS

Create a non-superuser application role and separate development/test databases. Run the extension command as `postgres` or another database administrator, then grant database access to the application role.

```sql
CREATE ROLE medgrid_app LOGIN PASSWORD '<unique-local-password>';
ALTER ROLE medgrid_app NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE DATABASE medgrid OWNER medgrid_app;
CREATE DATABASE medgrid_test OWNER medgrid_app;

\connect medgrid
CREATE EXTENSION IF NOT EXISTS postgis;
ALTER DATABASE medgrid SET timezone TO 'UTC';

\connect medgrid_test
CREATE EXTENSION IF NOT EXISTS postgis;
ALTER DATABASE medgrid_test SET timezone TO 'UTC';
```

Confirm both databases using the application role:

```sql
SELECT current_user, current_database(), postgis_version();
```

The PostGIS verification migration intentionally fails when PostgreSQL/PostGIS is unavailable. Do not replace PostgreSQL with SQLite for M0 tests.

## Installation

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
```

Edit the untracked `.env` and provide:

- the local `DB_PASSWORD` for `medgrid_app`;
- unique local `REVERB_APP_KEY` and `REVERB_APP_SECRET` values;
- a unique `MEDGRID_DEMO_PASSWORD` of at least 12 characters.

Never commit `.env`, database passwords, or Reverb secrets.

Initialize the development database only after confirming `DB_DATABASE=medgrid`:

```powershell
php artisan migrate --seed
```

For a deliberate clean local rebuild:

```powershell
php artisan migrate:fresh --seed
```

`migrate:fresh` is destructive. Never run it against an unknown or production-like database.

## Local Services

Use separate terminals:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work redis --queue=default
php artisan reverb:start --host=127.0.0.1 --port=8080
npm run dev
```

Laragon may serve the application directly at `http://medgrid.test`; keep `APP_URL` and `REVERB_ALLOWED_ORIGINS` aligned with the URL you use.

Build production frontend assets locally with:

```powershell
npm run build
```

## Synthetic Accounts

The seeder creates fictional local data only. Examples include:

- `platform.admin@medgrid.test`
- `org.admin.a@medgrid.test`
- `dispatcher.a@medgrid.test`
- `paramedic.a@medgrid.test`
- `driver.a@medgrid.test`
- `org.admin.b@medgrid.test`
- `auditor.b@medgrid.test`

All seeded accounts use the local value of `MEDGRID_DEMO_PASSWORD`. Seeder execution is blocked outside `local` and `testing` environments.

## Verification

Run the complete suite against the dedicated `medgrid_test` database:

```powershell
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --testdox
```

Other useful checks:

```powershell
php vendor/bin/pint --test
php artisan route:list --except-vendor
php artisan view:cache
npm run build
```

Public liveness is available at `/health/live` and returns only `{"status":"ok"}`. Detailed infrastructure health is permission-protected at `/admin/system/health` and `/api/v1/system/health`.

## Security Notes

- Public registration is disabled; accounts are institution-managed.
- User and membership status are enforced on protected requests and broadcast authorization.
- Organization context is resolved server-side and membership is revalidated.
- Platform and organization roles are distinct.
- Audit records and operational timeline concepts remain separate.
- Realtime channels are private and organization-authorized.
- Redis is used for cache, queues, and ephemeral infrastructure, never as the permanent source of critical records.

## Milestones

M0 establishes the foundation only. The next planned milestone is **M1 - Dispatch**, which must not begin until M0 acceptance is reviewed and explicitly approved.