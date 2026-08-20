# ADR-001 — Laravel 13.26.1 Pilot Baseline

**Status:** ACCEPTED  
**Date:** 2026-08-20

## Context

The original planning baseline referenced Laravel 13.10.0. The actual newly-created BisaBelajar repository is running Laravel 13.26.1 with a compatible Laravel 13 Composer constraint.

The project is still at skeleton stage and has no domain implementation that depends on Laravel 13.10.0-specific behavior.

## Decision

Use **Laravel 13.26.1** as the locked framework baseline for the current BisaBelajar pilot.

## Rationale

1. The repository already uses Laravel 13.26.1.
2. It avoids an unnecessary downgrade.
3. It remains within the Laravel 13 major version selected for the project.
4. It gives the pilot a current patch/minor baseline.
5. The architecture and M1 requirements do not depend on Laravel 13.10.0 specifically.

## Consequence

All M1 documentation must reference Laravel 13.26.1.

Cursor and Hermes must not upgrade or downgrade Laravel without a new approved ADR.
