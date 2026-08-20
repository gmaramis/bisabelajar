# TASK-001 — Project Environment Verification

**Owner:** Cursor  
**Priority:** P0  
**Status:** REVIEW

## Objective

Verify and normalize the Laravel/MySQL project foundation prepared by the Product Owner.

## Preconditions

The Product Owner prepares:
- Laravel 13.26.1 project
- PHP runtime
- MySQL/MariaDB database
- `.env`
- Git repository

## Scope

1. Inspect Laravel installation.
2. Verify PHP/Laravel compatibility.
3. Verify MySQL/MariaDB connection.
4. Verify migrations.
5. Verify application boot.
6. Verify test framework.
7. Verify `.env.example`.
8. Verify README.
9. Verify root `AGENTS.md`.
10. Verify required documentation.
11. Add a simple health-check endpoint if missing.
12. Confirm no Filament dependency.

## Out of Scope

- Authentication UI
- Course CRUD
- Modules
- Learning Units
- NEXUS
- Activities
- Code execution

## Acceptance Criteria

- AC-001 Application boots successfully.
- AC-002 MySQL/MariaDB connection succeeds.
- AC-003 Migrations execute successfully.
- AC-004 Test suite executes successfully.
- AC-005 Health check succeeds.
- AC-006 `.env.example` exists and contains no secrets.
- AC-007 README describes BisaBelajar.
- AC-008 Root `AGENTS.md` exists.
- AC-009 M1 documentation is present.
- AC-010 Filament is not installed.
- AC-011 No secrets are committed.
- AC-012 Git repository has a baseline commit.

## Stop Conditions

Stop and mark BLOCKED if a requirement conflicts with an accepted architecture decision or if implementation requires a new major dependency or product decision.

## Deliverables

- Verified foundation
- Health check
- Tests
- Documentation updates
- Git commit

## Git

`chore(TASK-001): verify project foundation`
