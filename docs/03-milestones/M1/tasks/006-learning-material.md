# TASK-006 — Learning Material Management

**Owner:** Cursor or Hermes  
**Priority:** P1  
**Status:** READY AFTER TASK-005

## Objective

Allow Tutors to attach reusable learning materials to Learning Units.

## Required Types

- RICH_TEXT
- PDF
- POWERPOINT
- EXTERNAL_URL

## Minimum Fields

- id
- learning_unit_id
- title
- type
- content/reference
- sort_order
- status
- timestamps

## Rules

1. Material belongs to one Learning Unit.
2. Tutor controls materials in owned courses.
3. PDF and PowerPoint files are validated.
4. External URLs are validated.
5. Do not build a custom PowerPoint renderer.
6. Opening material does not mean mastery.
7. Progress and mastery remain separate.
8. Architecture should allow future material types.

## Security

- Validate upload type and extension.
- Do not trust original filenames.
- Use framework-supported storage.
- Prevent executable uploads.
- Validate external URLs.

## Acceptance Criteria

- AC-006-01 Tutor can add Rich Text.
- AC-006-02 Tutor can upload PDF.
- AC-006-03 Tutor can upload PowerPoint.
- AC-006-04 Tutor can add External URL.
- AC-006-05 Material ordering works.
- AC-006-06 Authorization works.
- AC-006-07 Validation works.
- AC-006-08 Student can access published material.
- AC-006-09 Tests pass.

## Git

`feat(TASK-006): add learning materials`
