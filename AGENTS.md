# BisaBelajar — AI Agent Operating Rules

## Project Identity

- Application: BisaBelajar
- Platform: AI-VET Learning Platform
- Repository: bisabelajar
- Pilot: BisaBelajar — AI-VET Pilot

## Technical Baseline

- Laravel 13.26.1
- MySQL/MariaDB
- Blade + Tailwind CSS + Alpine.js + Vite
- Custom UI
- No Filament

## Core Product Rules

1. AI-VET is generic and configurable.
2. BisaBelajar is the current pilot implementation.
3. Course has no fixed 16-meeting structure.
4. Course → Module → Learning Unit is the core learning hierarchy.
5. Progress is not mastery.
6. NEXUS is context-aware and Socratic, but outside M1.
7. AI is not the final authority for grades.
8. Student code must never execute inside the Laravel application process.

## Agent Roles

### Cursor
Primary interactive developer while the Product Owner is active. Responsible for implementation, debugging, UI, testing, refactoring, and review.

### Hermes
Autonomous implementation agent. Works only from approved task specifications and may continue through READY tasks.

### ChatGPT
Architecture, specification, technical review, and planning.

### Product Owner
Final product and acceptance decisions.

## Task Rules

- Read the applicable task specification before coding.
- Do not expand scope.
- Do not invent requirements.
- Do not remove acceptance criteria.
- Do not bypass tests.
- Do not modify an accepted architecture decision without an ADR.
- Do not implement the same task concurrently with another agent.
- If requirements are ambiguous, mark BLOCKED.

## Security Rules

- Never commit secrets.
- Enforce authorization server-side.
- Validate all user-controlled input.
- Do not trust uploaded filenames or MIME declarations.
- External URLs must be validated.
- Student code execution belongs to an isolated sandbox, never Laravel directly.

## Git Rules

- Every accepted task requires a traceable commit.
- Never commit `.env`.
- Do not rewrite accepted history without approval.
- Keep task commits identifiable.

## Documentation Rules

Update relevant documentation when architecture, behavior, or decisions change.

## Stop Conditions

Stop and mark BLOCKED if:
- requirements are ambiguous;
- an ADR is required;
- a major new dependency is required;
- security boundaries must change;
- accepted behavior would regress;
- infrastructure is unavailable;
- acceptance criteria cannot be met within scope.
