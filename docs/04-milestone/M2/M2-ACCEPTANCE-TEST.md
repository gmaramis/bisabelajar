# M2 — Acceptance Test

## Tutor
1. Login.
2. Open owned Course and Learning Unit.
3. Create activities of supported types.
4. Configure activity metadata.
5. Reorder activities.
6. Publish/unpublish.
7. Verify ownership enforcement.

## Student
1. Login.
2. Enroll in published Course.
3. Open Learning Unit.
4. See published activities.
5. Open an activity.
6. Start activity.
7. Submit where supported.
8. Verify activity state persists.
9. Verify unauthorized/draft activities are inaccessible.

## Regression
- M1 automated tests remain green.
- Course, Module, Learning Unit, Material, Enrollment and Progress authorization remains secure.
- Activity completion remains distinct from mastery.
- No fixed 16-meeting logic.
- No Filament.
- No NEXUS.
- No direct code execution in Laravel.

## M2 Acceptance Criteria
- AC-M2-01 Tutor can open an owned Course and Learning Unit.
- AC-M2-02 Tutor can create multiple supported activity types.
- AC-M2-03 Tutor can configure type-aware activity metadata.
- AC-M2-04 Tutor can reorder activities.
- AC-M2-05 Tutor can publish and unpublish activities.
- AC-M2-06 Tutor can archive activities.
- AC-M2-07 Tutor cannot manage another tutor's activities.
- AC-M2-08 Student can enroll in a published Course.
- AC-M2-09 Student sees only published activities in a Learning Unit.
- AC-M2-10 Student can open, start, and submit a supported activity.
- AC-M2-11 Configured completion records activity progress without mastery.
- AC-M2-12 Activity progress persists across sessions.
- AC-M2-13 Students cannot access draft/archived activities or submit for another student.
- AC-M2-14 Activity completion remains separate from Learning Unit progress.
- AC-M2-15 No Filament, NEXUS, code execution, or fixed 16-meeting logic.
- AC-M2-16 Focused M2 integration tests and the full automated suite pass.

## M2 Exit Condition
M2 may be marked ACCEPTED only after all acceptance criteria pass.
