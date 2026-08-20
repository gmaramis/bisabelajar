# TASK-003 — Course Management

**Owner:** Cursor or Hermes  
**Priority:** P0  
**Status:** ACCEPTED

## Objective

Implement reusable Course management without fixed academic meeting assumptions.

## Minimum Fields

- id
- owner_id
- title
- slug
- description
- thumbnail/reference if applicable
- status
- visibility
- timestamps

## Status

- DRAFT
- PUBLISHED
- ARCHIVED

## Visibility

- PRIVATE
- UNLISTED
- PUBLIC

## Rules

1. Tutor owns the course.
2. Tutor may create, edit, publish, and archive own courses.
3. Published courses are discoverable according to visibility.
4. Course has no semester requirement.
5. Course has no fixed meeting count.
6. Do not create a `meeting_number` requirement.
7. Do not hard-code course names.

## Acceptance Criteria

- AC-003-01 Tutor can create course.
- AC-003-02 Tutor can edit own course.
- AC-003-03 Tutor can publish course.
- AC-003-04 Tutor can archive course.
- AC-003-05 Tutor cannot modify another tutor's course.
- AC-003-06 Student cannot edit course.
- AC-003-07 Slug is unique and safe.
- AC-003-08 Validation exists.
- AC-003-09 Tests pass.

## Out of Scope

- Modules
- Learning Units
- Enrollment
- Gradebook
- Course cloning

## Git

`feat(TASK-003): add course management`
