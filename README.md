# Bulkstream Cargo Handling System

A focused Laravel 13 / Filament 5 technical-assessment application for a bulk-liquid terminal.

Core workflow:

```text
Vessel Discharge → Shore Storage Tank → Truck Delivery
```

Supported commodities:

- CPO — Crude Palm Oil
- CPOL — Crude Palm Olein
- PFAD — Palm Fatty Acid Distillate

## Technology Stack

- PHP 8.4.27
- Laravel 13.39.0
- Filament 5.11.0
- Livewire 4.4.8
- MySQL/MariaDB 11.4.5 (MySQL-compatible)
- Node.js v24.22.2
- npm 11.19.1
- Vite 8.3.5
- Tailwind CSS via a custom Filament theme

## Architecture

```text
Filament / Livewire
        ↓
Application Actions
        ↓
Domain Policies and Value Objects
        ↓
Eloquent / MySQL
```

Layers:

- `app/Filament/` — presentation: resources, pages, widgets, forms, tables, actions
- `app/Actions/` — application workflows and transaction boundaries
- `app/Domain/` — pure business rules, calculations, policies, value objects, exceptions
- `app/Models/` and `database/` — persistence, migrations, constraints, seeders

Filament does not directly mutate inventory. All inventory changes go through:

- `ProcessInboundDischarge`
- `ProcessTruckDispatch`

Those actions enforce domain rules, transactions, row locking, idempotency, and ledger creation.

## Core Business Rules

- Tank capacity is enforced server-side: `current + incoming <= capacity`.
- Inbound cargo applies `0.5%` transit mass loss:
  - `loss = gross × 0.005`
  - `net = gross - loss`
- Source logs are sorted by `occurred_at`, then `sequence`, then stable ID before processing.
- Late events are not silently inserted into completed discharge history.
- Inventory mutations are transactional and use `lockForUpdate()`.
- Duplicate operations are prevented using stable idempotency keys and database uniqueness constraints.
- Inventory movements are append-only and store `balance_before` and `balance_after`.
- Cargo quantities use `DECIMAL(18,3)`. PHP floating-point arithmetic is not used for authoritative cargo calculations.

Assessment assumption: each `DischargeLog` represents an individual cargo event whose gross mass contributes to total discharge gross. This is an assessment modelling assumption, not an externally verified terminal standard.

## Dashboard

The dashboard is a read-only terminal operations overview. It reports:

- cargo received and dispatched today;
- current inventory and available capacity by commodity;
- the vessel → discharge → storage → truck flow;
- a tank board with current balance, capacity and utilization;
- pending or inconsistent operations that need attention;
- the latest inventory movements.

"Today" uses the `Africa/Nairobi` day boundary. Totals only include completed
discharges (`completed_at`) and completed truck dispatches (`dispatched_at`);
draft, planned and failed records are excluded from completed mass totals.
Dashboard queries live in `App\Application\Dashboard\TerminalDashboardReadModel`
and never mutate operational records.

## Operations analytics

The read-only operations analytics page summarizes completed operations over a
selected reporting period, including:

- gross, transit loss, and net received mass
- dispatched mass
- commodity breakdowns
- tank inventory and utilization
- discharge and transit-loss analysis
- inventory reconciliation against the movement ledger

Reporting periods use `Africa/Nairobi` day boundaries. Database timestamps remain
stored in UTC.

## Setup

### Prerequisites

- PHP 8.3+ with PDO MySQL and intl extensions (required by Laravel/Filament number formatting)
- Composer
- Node.js/npm
- MySQL or MariaDB

Operational timestamps are stored in UTC and presented in `Africa/Nairobi`.
Set `TERMINAL_TIMEZONE` in `.env` to change the presentation timezone; leave
`APP_TIMEZONE` as `UTC` so database storage remains UTC.

In this development environment, the PHP CLI wrapper at `/home/jared/bin/php`
loads `intl`. Verify it before serving:

```bash
/home/jared/bin/php -m | grep intl
```

Start the application with that same PHP binary so the web process also loads
`intl`:

```bash
export PATH="/home/jared/bin:$PATH"
php artisan serve
```

### Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bulk_stream
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

Create the database, then run:

```bash
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Open:

```text
http://127.0.0.1:8000/admin
```

Default seeded development user:

```text
admin@example.com / password
```

## Testing

```bash
php artisan test
npm run build
```

The test suite covers:

- Domain calculations and policies
- State transitions
- Chronological source-log sorting
- Inbound discharge workflow
- Outbound truck dispatch workflow
- Capacity and inventory availability
- Idempotency
- Filament interface integration

The dashboard tests also cover the read model aggregates, the Nairobi reporting
day boundary, read-only behaviour, pending-work classification and Filament
rendering of the terminal overview.

Final verified results:

```text
php artisan test
# 144 tests, 546 assertions, passed

npm run build
# success

php artisan migrate:fresh --seed
# success

php artisan route:list
# 37 routes
```

## Known Limitations

- No advanced RBAC or permission matrix.
- No manual inventory adjustment mechanism.
- No external integrations, queues, or APIs.
- No historical event replay/rebuild.
- Concurrency tests use sequential locked transactions rather than true parallel requests.
- Seed data is assessment-oriented and not a production terminal history.

## Assessment Scope

This is a seven-day technical assessment, not a production terminal deployment. The implementation intentionally excludes KPA/customs/KRA integrations, M-Pesa, GPS/AIS, ERP integrations, laboratory systems, IoT sensors, multi-terminal tenancy, and production infrastructure.
