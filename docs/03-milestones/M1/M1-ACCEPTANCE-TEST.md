# M1 — Acceptance Test

## Goal

Prove that the core learning flow works end-to-end.

## Acceptance Flow

### Tutor

1. Login as Tutor.
2. Create a Course.
3. Create at least two Modules.
4. Create Learning Units inside Modules.
5. Add at least one Rich Text material.
6. Add a PDF material.
7. Add a PowerPoint material.
8. Add an External URL material.
9. Reorder Modules and Learning Units.
10. Publish the Course.

### Student

1. Login as Student.
2. Enroll in the published Course.
3. Open My Courses.
4. Open Course.
5. Open Module.
6. Open Learning Unit.
7. Access each material type.
8. Verify progress changes from NOT_STARTED to IN_PROGRESS.
9. Complete the Learning Unit.
10. Verify progress becomes COMPLETED.

## Required Security Checks

- Student cannot edit Tutor course content.
- Tutor cannot edit another Tutor's course.
- Unauthenticated user cannot access protected course-management actions.
- Draft/private course is not exposed to unauthorized students.
- File uploads are validated.
- External URLs are stored safely.

## M1 Acceptance Criteria

- AC-M1-01 Authentication works.
- AC-M1-02 Student/Tutor authorization works.
- AC-M1-03 Tutor can create and publish a course.
- AC-M1-04 Tutor can create/reorder modules.
- AC-M1-05 Tutor can create/reorder learning units.
- AC-M1-06 Required material types work.
- AC-M1-07 Student can enroll.
- AC-M1-08 Student can consume course content.
- AC-M1-09 Progress is persisted.
- AC-M1-10 No fixed 16-meeting assumption exists.
- AC-M1-11 No Filament dependency exists.
- AC-M1-12 Automated tests pass.
- AC-M1-13 End-to-end manual test passes.
- AC-M1-14 Documentation and Git history are complete.

## M1 Exit Condition

M1 may be marked ACCEPTED only after all acceptance criteria pass.
