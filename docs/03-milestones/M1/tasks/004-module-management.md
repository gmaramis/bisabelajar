# TASK-004 — Course Module Management

**Owner:** Cursor or Hermes  
**Priority:** P1  
**Status:** READY AFTER TASK-003

## Objective

Allow Tutors to organize a Course into an arbitrary number of Modules.

## Minimum Fields

- id
- course_id
- title
- description
- sort_order
- status
- timestamps

## Rules

1. Module belongs to exactly one Course.
2. Tutor manages modules only for owned courses.
3. Module count is not fixed.
4. Ordering is persisted.
5. No meeting-number concept.
6. Module publication respects course state.

## Acceptance Criteria

- AC-004-01 Create module.
- AC-004-02 Edit module.
- AC-004-03 Delete module where safe.
- AC-004-04 Reorder modules.
- AC-004-05 Publish/unpublish module.
- AC-004-06 Authorization works.
- AC-004-07 Validation works.
- AC-004-08 Tests pass.

## Git

`feat(TASK-004): add course module management`
