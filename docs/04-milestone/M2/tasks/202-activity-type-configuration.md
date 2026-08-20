# TASK-202 — Activity Type & Configuration

**Status:** ACCEPTED

## Objective
Allow Tutor to create activities with validated, extensible type-specific configuration.

## Requirements
- Activity type selection.
- Generic configuration storage.
- Type-aware validation.
- Student-safe presentation fields.
- Extensible configuration boundary.

## Rules
Do not implement Quiz scoring, Assignment grading, Coding execution, NEXUS, or mastery.

## Acceptance Criteria
- Tutor can select type.
- Valid configuration saves.
- Invalid configuration is rejected.
- Configuration persists.
- Private tutor configuration is not exposed to students.
- Focused and full tests pass.

## Commit
`feat(TASK-202): add activity configuration`
