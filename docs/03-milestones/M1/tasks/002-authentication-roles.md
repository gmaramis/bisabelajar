# TASK-002 — Authentication & Roles

**Owner:** Cursor  
**Priority:** P0  
**Status:** ACCEPTED

## Objective

Implement secure authentication and the minimum role model required by M1.

## Roles

- STUDENT
- TUTOR

## Scope

- Login
- Logout
- Authentication middleware
- Role assignment
- Role authorization
- Protected routes
- Basic authenticated profile access

## Authorization Rules

### Student
May access own profile, own enrollments, own learning experience, and own progress.

Must not manage tutor course content or access another student's private data.

### Tutor
May manage own courses and own course content.

Must not modify another tutor's courses.

## Acceptance Criteria

- AC-002-01 Student can authenticate.
- AC-002-02 Tutor can authenticate.
- AC-002-03 Logout works.
- AC-002-04 Protected actions reject unauthenticated users.
- AC-002-05 Student cannot access tutor management actions.
- AC-002-06 Tutor cannot modify another tutor's course resources.
- AC-002-07 Authentication tests pass.
- AC-002-08 Authorization tests pass.

## Out of Scope

- OAuth/social login
- MFA
- Passwordless login
- Complex admin panel

## Git

`feat(TASK-002): add authentication and roles`
