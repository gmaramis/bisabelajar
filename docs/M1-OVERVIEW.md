# BisaBelajar — AI-VET Pilot

# M1 — Core Learning Platform

**Application:** BisaBelajar
**Platform:** AI-VET Learning Platform  
**Milestone:** M1 — Core Learning Platform  
**Status:** READY FOR IMPLEMENTATION  
**Pilot:** Fundamental Programming with Python  
**Primary implementation agent:** Cursor  
**Autonomous implementation agent:** Hermes  
**Product Owner:** Glenn  
**Architecture / Technical Lead:** ChatGPT

## 1. Objective

Build the first usable core of AI-VET:

Tutor → Course → Module → Learning Unit → Material → Publish

Student → Enrollment → Course → Learning Unit → Material → Progress

M1 must establish a reusable foundation. It must NOT hard-code university semester structure or 16 meetings.

## 2. Core Principles

1. Generic platform.
2. Course structure is configurable.
3. Course has no fixed number of meetings.
4. Learning Unit is the core atomic learning container.
5. Learning Material can originate from uploaded or external sources.
6. Progress is separate from mastery.
7. Mastery, competency, learner profile, and recommendation are future engine layers.
8. No Filament.
9. UI is custom.
10. Student code execution is not part of M1.
11. NEXUS is not implemented in M1.
12. AI must not be required for core platform operation.

## 3. M1 Scope

- Project environment verification
- Authentication
- Student and Tutor roles
- Course management
- Module management
- Learning Unit management
- Learning Material management
- Enrollment
- Student course experience
- Basic learning progress

## 4. M1 Out of Scope

- NEXUS
- Mastery Engine
- Recommendation Engine
- Competency Engine
- Code Editor
- Code Execution Sandbox
- Quiz Engine
- Assignment Engine
- Gradebook
- Payment
- AI Credits / Wallet
- Advanced analytics
- Research experiment engine

## 5. Task Sequence

TASK-001 → TASK-002 → TASK-003 → TASK-004 → TASK-005 → TASK-006 → TASK-007 → TASK-008 → TASK-009 → TASK-010

Dependencies are defined in each task. A task may only be started when its dependencies are accepted.

## 6. Definition of Done

A task is complete only when:

- implementation is complete;
- automated/manual tests required by the task pass;
- acceptance criteria pass;
- documentation is updated;
- no known critical regression remains;
- task status is updated;
- a Git commit exists.

## 7. Agent Rule

Cursor may continue to the next READY task during the active work session.

Hermes may continue through READY tasks autonomously after handoff.

No two agents may implement the same task concurrently.

If an architectural/product decision is required, mark the task BLOCKED and document the decision needed.
