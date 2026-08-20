# BisaBelajar

BisaBelajar is the pilot application of the **AI-VET Learning Platform**.

- Application: **BisaBelajar**
- Platform: **AI-VET Learning Platform**
- Milestone: **M2 — Activity Engine** (ACCEPTED)
- Repository: [gmaramis/bisabelajar](https://github.com/gmaramis/bisabelajar)
- Release: `v0.2.0-m2-activities`

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

Learning units belong to a module, have unique slugs per module, and are not modeled as meetings.

Materials (RICH_TEXT, PDF, POWERPOINT, EXTERNAL_URL) attach to learning units. Files are stored privately with generated names. Viewing material does not record mastery.

Generic activities attach to learning units with types LESSON, QUIZ, ASSIGNMENT, CODING_EXERCISE, DISCUSSION, PROJECT, and EXAM. Status: DRAFT, PUBLISHED, ARCHIVED. Tutors manage activities in owned courses: list, create, edit, reorder, publish, unpublish, archive, and type-aware configuration. Publishing requires the parent course, module, and learning unit to be published. Enrolled students see published activities in order, can open student-safe details, record a start state, and submit a generic payload. Start and submission are not grades or mastery. Multiple attempts are allowed only when configuration permits. Draft and archived activities are inaccessible to students. Type-aware configuration is stored as JSON: student-safe fields (instructions and type metadata) are separate from tutor-private notes, answer keys, rubrics, and expected output. Quiz scoring, assignment grading, and code execution are not enabled.

Activity progress (NOT_STARTED, IN_PROGRESS, COMPLETED) is stored per student enrollment and activity. Completion follows a configurable rule and is not Learning Unit progress or mastery.

Students enroll in accessible published courses. Enrollment is unique per student/course, with status ACTIVE, COMPLETED, or DROPPED. Payment and credits are out of scope.

Students follow Login → Dashboard → My Courses → Course → Module → Learning Unit → Material/Activity. Only active enrollments and published content are shown. Tutor editing remains inaccessible to students.

Basic unit progress (NOT_STARTED, IN_PROGRESS, COMPLETED) is stored per student enrollment. Completed is not mastered.

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

## Documentation

Start here:

- [`AGENTS.md`](AGENTS.md)
- [`docs/CURSOR-START-HERE.md`](docs/CURSOR-START-HERE.md)
- [`docs/TECHNICAL-BASELINE.md`](docs/TECHNICAL-BASELINE.md)
- [`docs/03-milestones/M1/M1-OVERVIEW.md`](docs/03-milestones/M1/M1-OVERVIEW.md)
- [`docs/04-milestone/M2/M2-OVERVIEW.md`](docs/04-milestone/M2/M2-OVERVIEW.md)

NEXUS, mastery, competency, code execution, and related engines remain outside M2.
