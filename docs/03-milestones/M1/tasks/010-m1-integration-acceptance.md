# TASK-010 — M1 Integration & Acceptance

**Owner:** Glenn + Cursor  
**Priority:** P0  
**Status:** ACCEPTED

## Objective

Verify M1 end-to-end and prepare the milestone for acceptance.

## Tutor Test

1. Login.
2. Create Course.
3. Set title/description/visibility.
4. Create two Modules.
5. Reorder Modules.
6. Create Learning Units.
7. Reorder Learning Units.
8. Add Rich Text material.
9. Add PDF.
10. Add PowerPoint.
11. Add External URL.
12. Publish Course.

## Student Test

1. Login.
2. Access published Course.
3. Enroll.
4. Open My Courses.
5. Open Course.
6. Open Module.
7. Open Learning Unit.
8. Access each material.
9. Verify progress.
10. Complete Learning Unit.
11. Logout/login and verify progress persistence.

## Regression Checks

- Authentication
- Authorization
- Course ownership
- Material upload validation
- Enrollment uniqueness
- Progress ownership
- No Filament
- No fixed 16-meeting logic

## Acceptance

M1 is ACCEPTED only when:
- all M1 acceptance criteria pass;
- automated tests pass;
- manual end-to-end test passes;
- no critical security defect remains;
- documentation is updated;
- Git history is traceable.

## Git

Prepare M1 milestone tag after acceptance.

Suggested tag:
`v0.1.0-m1-core`
