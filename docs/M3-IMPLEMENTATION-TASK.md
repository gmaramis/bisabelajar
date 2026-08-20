# M3 — Implementation Task

**Project:** AI-VET / BisaBelajar  
**Module:** M3 — Programming Learning Environment  
**Task Type:** Implementation  
**Version:** 1.0  
**Status:** Ready for Implementation  
**Specification:** `docs/M3-Programming-Learning-Environment-Specification.md`

---

## 1. Source of Truth

The official M3 implementation specification is:

```text
docs/M3-Programming-Learning-Environment-Specification.md
```

Read that document completely before making implementation changes.

This task does not add scope beyond the specification.

If this task and the specification appear inconsistent, STOP and report the inconsistency before coding.

---

## 2. Baseline

The repository already contains accepted M1 and M2 functionality.

Current accepted baseline:

```text
141 tests passed
1,067 assertions
```

M1/M2 are the regression baseline.

Do not change M1/M2 behavior merely to simplify M3.

---

## 3. Branch

Implementation must use:

```text
feature/m3-programming-environment
```

Do not implement M3 directly on `main`.

The M3 specification is already committed on this branch.

---

## 4. Mission

Implement M3 exactly according to the official specification.

M3 is a generic and configurable Programming Learning Environment.

The implementation must provide:

```text
Browser
  ↓
Monaco Editor
  ↓
Laravel API
  ↓
Execution Service
  ↓
Docker Sandbox
  ↓
Compiler / Runtime
  ↓
Execution Result
  ↓
Student Feedback
  +
Observable Learning Events
  ↓
M4 / NEXUS later
```

M3 must not implement M4 cognitive/affective analysis.

---

## 5. Mandatory First Step — Audit Before Coding

Before modifying code:

1. Inspect the existing M1/M2 architecture.
2. Inspect Course and Activity models.
3. Inspect existing migrations.
4. Inspect existing routes/controllers/services.
5. Inspect existing frontend/UI conventions.
6. Inspect existing authorization patterns.
7. Inspect existing testing conventions.
8. Inspect existing queue/cache conventions.
9. Inspect existing configuration conventions.

Do not duplicate existing functionality.

Report briefly what existing structures M3 will reuse.

Do not begin implementation until the audit is complete.

---

## 6. Implementation Scope

Implement only the functionality defined in the M3 specification:

### A. Programming Activity

Integrate programming as a configurable activity type/feature within the existing M2 learning architecture.

### B. Monaco Editor

Provide the browser-based programming editor.

The editor must remain separate from execution infrastructure.

### C. Language Execution Profiles

Implement the profile-driven architecture for:

- Python
- C
- C++
- Java
- JavaScript / Node.js
- PHP
- Go / Golang
- Kotlin

The architecture must allow language runtimes to be enabled/disabled/configured without rewriting the core execution service.

M3 v1 does not require every target language to be operational simultaneously.

Clearly report which runtimes are operational.

### D. Execution Service

Implement controlled execution orchestration between Laravel and the Docker sandbox.

Do not expose Docker implementation details to students.

### E. Docker Sandbox

Student code is untrusted.

Execution must occur inside an isolated Docker sandbox.

Apply the security controls defined in the M3 specification.

Student code must not have access to:

- Laravel host filesystem;
- Laravel source;
- `.env`;
- MySQL;
- Redis;
- Docker host socket;
- unrestricted network.

### F. Run

Implement the Run workflow and persist the appropriate execution information.

Capture relevant execution results including:

- status;
- stdout;
- stderr;
- compile error;
- runtime error;
- timeout;
- exit status;
- execution duration.

### G. Submit

Implement formal code submission and associated evaluation.

### H. Automated Evaluation

Implement visible and hidden test cases according to the specification.

Hidden test details must not be exposed to students.

### I. Persistence

Implement only the required M3 domain entities and relationships.

Follow existing M1/M2 naming and architecture conventions.

Potential concepts include:

- Programming Activity
- Language Execution Profile
- Code Submission
- Code Execution
- Test Case
- Test Result
- Learning Event

Do not create duplicate concepts if equivalent existing structures can be reused.

### J. Learning Events

Implement the observable event contract defined by the specification.

Initial events:

```text
PROGRAMMING_ACTIVITY_OPENED
CODE_EDITED
CODE_RUN
CODE_SUBMITTED
COMPILE_FAILED
RUNTIME_FAILED
EXECUTION_TIMEOUT
TEST_FAILED
TEST_PASSED
SUBMISSION_ACCEPTED
SUBMISSION_REJECTED
```

Record observable facts only.

Do not infer:

- emotion;
- frustration;
- anxiety;
- motivation;
- psychological state.

Those belong to M4.

---

## 7. Security Rules

These are hard requirements.

Do not:

- execute student code directly in Laravel;
- execute student code directly on the host;
- expose arbitrary Docker commands;
- expose Docker socket to student-facing requests;
- allow unrestricted network access;
- allow access to application secrets;
- allow access to MySQL/Redis;
- allow unbounded CPU/memory/process usage.

Use appropriate:

- timeout;
- CPU limit;
- memory limit;
- process limit;
- temporary filesystem;
- network restriction;
- container cleanup;
- source/output size limits;
- authorization;
- rate limiting.

If a security requirement cannot be implemented safely, STOP and report it.

Do not weaken the requirement silently.

---

## 8. Development Order

Implement incrementally.

### Phase A
Audit existing architecture.

### Phase B
M3 domain model and configuration.

### Phase C
Language execution profile abstraction.

### Phase D
Docker execution/sandbox service.

### Phase E
Persistence for executions/submissions/results/events.

### Phase F
Secure Laravel API.

### Phase G
Monaco Editor integration.

### Phase H
Automated evaluation.

### Phase I
Learning event generation.

### Phase J
Integration and regression testing.

Do not create a giant uncontrolled implementation.

---

## 9. Testing Requirements

Add appropriate M3 tests for:

### Functional

- programming activity access;
- language selection;
- Run;
- Submit;
- execution result;
- test evaluation;
- submission persistence.

### Error handling

- compile error;
- runtime error;
- timeout;
- memory/resource limit;
- invalid language;
- invalid activity;
- oversized source code.

### Security

- unauthorized access;
- malicious input;
- sandbox isolation;
- network restriction;
- host filesystem isolation;
- secret isolation;
- rate limiting.

### Learning events

Verify generation of the specified observable events.

### Regression

Run:

```bash
php artisan test
```

M1/M2 regression must remain passing.

The original baseline is:

```text
141 tests
1,067 assertions
```

Report:

```text
M1/M2 baseline:
M3 tests:
Total:
```

Do not accept a test failure as an implementation success.

---

## 10. Database Rules

The application database remains:

```text
MySQL 8.x
```

Do not change the application database to SQLite.

SQLite may remain a PHPUnit test dependency if already configured by the repository.

Do not run destructive commands such as:

```text
migrate:fresh
migrate:refresh
db:wipe
```

against the application database.

Do not delete existing M1/M2 data.

---

## 11. Frontend Rules

Use the existing BisaBelajar frontend architecture.

Use:

```text
Monaco Editor
```

for the programming editor.

Do not introduce Filament.

Do not introduce React/Vue unless the existing project already requires it.

Do not redesign unrelated application UI.

---

## 12. Multi-Language Rules

The core execution service must be language-agnostic.

Do not create separate execution logic such as:

```text
PythonController
JavaController
GoController
```

when a profile-driven execution abstraction can be used.

The intended model is:

```text
Programming Activity
       ↓
Language Execution Profile
       ↓
Execution Service
       ↓
Docker Runtime
```

Adding a future language should not require rewriting the execution service.

---

## 13. M3 → M4 Data Boundary

M3 must generate structured observable programming-learning data.

M3 must not perform:

```text
cognitive diagnosis
affective diagnosis
emotion detection
psychological diagnosis
learning-state inference
adaptive intervention
recommendation
```

M4 will consume M3 events later.

The M3 implementation must therefore preserve sufficient structured observable data for later M4 analysis without embedding M4 logic into M3.

---

## 14. Documentation During Implementation

Update only documentation necessary to accurately describe the implemented M3 architecture.

Do not rewrite the M3 specification to hide implementation limitations.

If implementation differs from the specification:

1. STOP.
2. Report the difference.
3. Explain why.
4. Wait for approval before changing the specification or implementation direction.

---

## 15. Git Rules

Use the existing branch:

```text
feature/m3-programming-environment
```

Create logical commits.

Do not modify unrelated files.

Before every commit:

```bash
git status
```

Review changed files.

Do not commit:

- `.env`;
- secrets;
- credentials;
- private keys;
- temporary execution files;
- generated runtime artifacts;
- unrelated changes.

---

## 16. Acceptance Criteria

M3 is complete only when all applicable criteria in the official specification are satisfied.

Minimum acceptance:

1. Programming Activity integrates with M2.
2. Monaco Editor works in browser.
3. Run works.
4. Submit works.
5. Language selection is profile-driven.
6. At least one language executes successfully inside Docker.
7. Execution is isolated from the Laravel host.
8. Compile/runtime errors are captured.
9. Timeout/resource controls work.
10. Automated tests work.
11. Hidden tests are protected.
12. Submissions persist.
13. Execution history persists.
14. Observable learning events are generated.
15. Learning event contract is suitable for M4.
16. Multi-language architecture supports the eight specified target languages.
17. M1/M2 regression remains passing.
18. M3 tests pass.
19. Student execution cannot access MySQL, Redis, Laravel source, `.env`, or Docker host socket.
20. No M4 cognitive/affective interpretation is embedded in M3.

---

## 17. Final Verification

Before declaring M3 complete, run and report:

```bash
git status
php artisan test
```

Also verify:

- application boots;
- programming activity can be opened;
- Monaco editor loads;
- Run works;
- Submit works;
- sandbox execution works;
- at least one target language executes;
- automated evaluation works;
- observable learning events are persisted;
- M1/M2 regression remains passing.

---

## 18. Final Report Format

At completion, report:

### A. Architecture
What was implemented and what existing M1/M2 components were reused.

### B. Database
Migrations/models added.

### C. Services
Services/classes created.

### D. API
Endpoints added.

### E. Editor
Monaco integration status.

### F. Sandbox
Docker isolation architecture and security controls.

### G. Language Profiles
List all eight target profiles and their operational status.

### H. Learning Events
Event types implemented and persisted.

### I. Tests

```text
M1/M2:
M3:
Total:
Assertions:
```

### J. Security
Controls implemented and any remaining limitations.

### K. Limitations
Only actual limitations.

### L. Acceptance
For every M3 acceptance criterion:

```text
PASS / FAIL / BLOCKED
```

### M. Git
Branch and commit hashes.

---

## 19. STOP CONDITION

When M3 acceptance criteria are satisfied:

**STOP.**

Do not start M4.

Do not implement cognitive analysis.

Do not implement affective analysis.

Do not implement learning-state inference.

Do not implement adaptive intervention.

Do not implement recommendations.

M4 will be handled as a separate implementation task after review of M3.

---

## 20. Final Instruction to Hermes

Read:

```text
docs/M3-Programming-Learning-Environment-Specification.md
docs/M3-IMPLEMENTATION-TASK.md
```

Treat the specification as the authoritative scope.

Implement only M3.

Preserve M1/M2.

Do not add features outside scope.

Work incrementally.

Report blockers instead of making architectural assumptions.

Do not start M4.

STOP after M3 acceptance.
