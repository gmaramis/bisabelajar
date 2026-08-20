# BisaBelajar — Technical Baseline

## Project Identity

- Application: **BisaBelajar**
- Repository: **bisabelajar**
- Platform/Product Architecture: **AI-VET Learning Platform**
- Pilot: **BisaBelajar — AI-VET Pilot**

## Framework

- Laravel: **13.26.1 — LOCKED for current pilot baseline**
- PHP: **8.4.x compatible environment**

## Database

- MySQL/MariaDB

SQLite may be used only for isolated automated tests where appropriate; the application development/runtime baseline is MySQL/MariaDB.

## Frontend

- Blade
- Tailwind CSS
- Alpine.js
- Vite

## UI

- Custom UI
- **No Filament**

## Development

- Git
- GitHub
- Maramisserver

## AI

- NEXUS / Hermes integration is outside M1 core implementation.

## Version Rule

Laravel 13.26.1 is locked for the current pilot baseline.

Do not upgrade or downgrade Laravel within a task unless an Architecture Decision Record (ADR) is approved.

## Environment Rule

Local development and pilot configuration must use MySQL/MariaDB as the application database baseline.

Do not design application migrations or domain logic around SQLite-specific behavior.
