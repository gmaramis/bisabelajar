# M2 — Activity Engine

**Status:** ACCEPTED  
**Milestone:** M2 — Activity Engine  
**Release tag:** `v0.2.0-m2-activities`  
**Laravel:** 13.26.1

## Objective
Build a generic, configurable Activity Engine for BisaBelajar.

## Initial Activity Types
- LESSON
- QUIZ
- ASSIGNMENT
- CODING_EXERCISE
- DISCUSSION
- PROJECT
- EXAM

M2 builds the generic activity engine and core activity flow. Specialized engines such as code execution belong to later milestones.

## Principles
1. Activity is a generic learning object.
2. Activity is attached to a Learning Unit.
3. Activity type determines configuration/behavior boundaries.
4. Tutor controls activities in owned courses.
5. Student access requires valid enrollment and published content.
6. Activity completion is distinct from Learning Unit progress and mastery.
7. NEXUS is outside M2.
8. Code execution/browser editor is outside M2.
9. No Filament.
10. No fixed 16-meeting structure.

## M2 Deliverable
Tutor can configure activities inside Learning Units and students can discover, open, start, and submit supported generic activities through a consistent lifecycle.
