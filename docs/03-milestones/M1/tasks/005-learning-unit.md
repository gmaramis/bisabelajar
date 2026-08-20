# TASK-005 — Learning Unit Management

**Owner:** Cursor or Hermes  
**Priority:** P1  
**Status:** READY AFTER TASK-004

## Objective

Implement Learning Unit as the atomic learning container inside a Module.

## Structure

Course → Module → Learning Unit

## Minimum Fields

- id
- module_id
- title
- slug
- description
- sort_order
- status
- timestamps

## Future Extension Boundary

Prepare the model so later versions can associate:
- learning objectives
- materials
- activities
- evidence
- mastery rules
- prerequisites

Do not implement those future engines in this task.

## Rules

1. Learning Unit belongs to one Module.
2. Tutor controls units in owned courses.
3. Unit order is persisted.
4. Number of units is not fixed.
5. Do not model the unit as a meeting.

## Acceptance Criteria

- AC-005-01 Create.
- AC-005-02 Edit.
- AC-005-03 Delete where safe.
- AC-005-04 Reorder.
- AC-005-05 Publish/unpublish.
- AC-005-06 Authorization.
- AC-005-07 Validation.
- AC-005-08 Tests pass.

## Git

`feat(TASK-005): add learning units`
