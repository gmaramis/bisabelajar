# TASK-008 — Student Course Experience

**Owner:** Cursor + Hermes  
**Priority:** P1  
**Status:** REVIEW

## Objective

Implement the student-facing learning flow for M1.

## Required Flow

Login → Dashboard → My Courses → Course → Module → Learning Unit → Material

## Rules

1. Student sees only accessible courses.
2. Student can open published course content.
3. Student can navigate modules and units.
4. Student can access published materials.
5. Student cannot access tutor editing functions.
6. UI is responsive.
7. Authorization is enforced server-side.

## Acceptance Criteria

- AC-008-01 Student dashboard loads.
- AC-008-02 My Courses displays active enrollments.
- AC-008-03 Course page displays modules.
- AC-008-04 Module displays learning units.
- AC-008-05 Learning Unit displays materials.
- AC-008-06 Material access works.
- AC-008-07 Server-side authorization works.
- AC-008-08 Responsive UI is usable.
- AC-008-09 Tests pass.

## Out of Scope

- Mastery
- NEXUS
- Adaptive recommendations
- Quiz
- Assignment
- Coding

## Git

`feat(TASK-008): add student course experience`
