# BisaBelajar — AI-VET Pilot

# M1 — Execution Plan

## Working Model

### Glenn
- Product Owner
- Academic domain expert
- Final acceptance authority
- Performs hands-on validation with Cursor

### ChatGPT
- System Architect
- Technical Lead
- Specification author
- Architecture reviewer

### Cursor
- Primary active developer while Glenn is working
- UI implementation
- Feature implementation
- Debugging
- Review
- Refactoring
- Test execution

### Hermes
- Autonomous implementation agent
- Works from approved specifications
- Can continue during periods when Glenn is unavailable
- Must stop on ambiguity or architectural conflict

## Execution Flow

```text
Specification
    ↓
TASK READY
    ↓
Implementation
    ↓
Tests
    ↓
Review
    ↓
Acceptance
    ↓
Git Commit
    ↓
Next READY Task
```

## Task Status

- BACKLOG
- READY
- IN_PROGRESS
- TESTING
- REVIEW
- ACCEPTED
- BLOCKED

## Stop Conditions

Stop and mark BLOCKED if:

1. A requirement is ambiguous.
2. Implementation requires changing an accepted architecture decision.
3. A new major dependency is required.
4. A security boundary must be changed.
5. Acceptance criteria cannot be satisfied without changing scope.
6. Existing accepted behavior would be broken.
7. Required infrastructure is unavailable.

Do not invent product requirements.

## Parallel Work Rule

Parallel work is allowed only when tasks do not modify the same architectural area or have unresolved dependencies.

Never have Cursor and Hermes implement the same task simultaneously.
