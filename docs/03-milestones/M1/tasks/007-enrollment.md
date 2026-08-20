# TASK-007 — Enrollment

**Owner:** Hermes  
**Priority:** P1  
**Status:** REVIEW

## Objective

Allow students to enroll in accessible published courses.

## Minimum Fields

- id
- user_id
- course_id
- status
- enrolled_at
- timestamps

## Status

- ACTIVE
- COMPLETED
- DROPPED

## Rules

1. Only eligible published courses can be enrolled in.
2. Enrollment is unique per student/course.
3. Student sees own enrollments.
4. Tutor sees enrollments for own courses.
5. Student cannot create enrollment for another student.

## Acceptance Criteria

- AC-007-01 Student can enroll.
- AC-007-02 Duplicate enrollment is prevented.
- AC-007-03 Student sees enrolled courses.
- AC-007-04 Tutor sees enrolled students for own courses.
- AC-007-05 Unauthorized access is denied.
- AC-007-06 Tests pass.

## Out of Scope

- Payment
- Subscription
- AI Credits
- Wallet
- Complex approval workflow

## Git

`feat(TASK-007): add course enrollment`
