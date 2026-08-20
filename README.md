# BisaBelajar

BisaBelajar is the pilot application of the **AI-VET Learning Platform**.

- Application: **BisaBelajar**
- Platform: **AI-VET Learning Platform**
- Milestone: **M1 — Core Learning Platform**
- Repository: [gmaramis/bisabelajar](https://github.com/gmaramis/bisabelajar)

Use **AI-VET** for the generic platform architecture. Use **BisaBelajar** for this concrete pilot.

## Technical Baseline

- Laravel 13.26.1 (locked)
- PHP 8.4.x
- MySQL/MariaDB as the application database
- Blade + Tailwind CSS + Alpine.js + Vite
- Custom UI — Filament is not used

SQLite may be used only for isolated automated tests. Application development and runtime use MySQL/MariaDB.

## Authentication

Session authentication is built with Laravel's native Auth (no Breeze, Fortify, Jetstream, or Filament).

Roles: **STUDENT** and **TUTOR**. Authorization is enforced server-side via `auth` middleware, `role` middleware, and ownership gates. Tutors may only mutate their own future course content.

## Courses

Tutors create, edit, publish, and archive their own courses. Status: DRAFT, PUBLISHED, ARCHIVED. Visibility: PRIVATE, UNLISTED, PUBLIC. Courses have no semester or fixed meeting count.

Tutors add an arbitrary number of ordered modules to owned courses. Module publish requires the course to be published.

## Local Setup

1. Copy `.env.example` to `.env` and set a MySQL database (default name: `bisabelajar`).
2. Generate the application key: `php artisan key:generate`
3. Install PHP dependencies: `composer install`
4. Run migrations: `php artisan migrate`
5. Install frontend dependencies: `npm install`
6. Start the app: `composer run dev` or `php artisan serve`

## Tests

```bash
php artisan test
```

PHPUnit uses in-memory SQLite. That does not change the MySQL application baseline.

## M1 Documentation

Start here:

- [`AGENTS.md`](AGENTS.md)
- [`docs/CURSOR-START-HERE.md`](docs/CURSOR-START-HERE.md)
- [`docs/TECHNICAL-BASELINE.md`](docs/TECHNICAL-BASELINE.md)
- [`docs/03-milestones/M1/M1-OVERVIEW.md`](docs/03-milestones/M1/M1-OVERVIEW.md)

NEXUS, mastery, competency, code execution, and related engines are outside M1.
