# M3 — Programming Learning Environment

**Project:** AI-VET / BisaBelajar  
**Module:** M3  
**Status:** Official Specification  
**Version:** 1.0  
**Date:** 2026-08-21  
**Baseline:** M1 + M2 Accepted

---

## 1. Purpose

M3 provides a generic and configurable **Programming Learning Environment** inside BisaBelajar.

M3 enables students to learn programming through a browser-based coding environment, execute code safely in isolated sandbox environments, receive execution and assessment feedback, and generate structured **observable learning data** for later analysis by M4/NEXUS.

M3 is not merely an online compiler. It is a learning environment integrated with the generic Course and Activity architecture established in M1/M2.

---

## 2. Baseline and Constraints

M3 must preserve the accepted M1/M2 baseline.

Current environment:

- Laravel 13.26.1
- PHP 8.4.x
- MySQL 8.x
- Redis 7
- Docker Engine
- Docker Compose
- GitHub repository: `gmaramis/bisabelajar`
- Development server: `/home/gmaramis/ai-vet`

M1/M2 acceptance baseline:

- 141 tests passed
- 1,067 assertions

M3 must not break existing M1/M2 functionality.

### Architectural constraints

- No Filament.
- Use the existing custom Laravel application architecture.
- Do not introduce React/Vue unless already required by the existing project.
- Application database remains MySQL 8.x.
- Student code is untrusted input.
- Student code must never execute directly on the Laravel host.
- Docker sandboxing is mandatory for code execution.
- M3 must be multi-language by architecture from the beginning.
- M4 must consume M3 observable learning data without requiring a redesign of M3.

---

## 3. M3 Objectives

M3 shall:

1. Provide a browser-based programming editor.
2. Support configurable programming activities.
3. Support multiple programming languages through execution profiles.
4. Execute student code in isolated Docker sandboxes.
5. Support run and submit workflows.
6. Support automated test cases.
7. Capture compile, runtime, timeout, and test results.
8. Persist code submissions and execution history.
9. Generate structured observable learning events.
10. Provide a clean data contract for M4/NEXUS.
11. Allow additional languages to be added without rewriting the core execution architecture.
12. Maintain M1/M2 regression compatibility.

---

## 4. Relationship with M1 and M2

M3 extends the generic learning model rather than replacing it.

```text
Course
  |
  +-- Learning Activity
        |
        +-- Reading
        +-- Quiz
        +-- Assignment
        +-- Programming Activity  <-- M3
```

Programming is an **activity type / configurable feature**.

A course that does not require programming must not be forced to use the programming subsystem.

---

## 5. High-Level Architecture

```text
Student Browser
      |
      v
Monaco Editor
      |
      | Run / Submit
      v
Laravel API / Application Layer
      |
      v
Execution Service
      |
      v
Docker Sandbox
      |
      +--> Compiler / Runtime
      |
      v
Execution Result
      |
      +----------------------+
      |                      |
      v                      v
Student Feedback       Observable Learning Events
                             |
                             v
                         M4 / NEXUS
```

### Responsibility boundaries

**Browser**
- code editing;
- language selection;
- Run;
- Submit;
- output/error/test display.

**Laravel**
- authentication/authorization;
- activity configuration;
- validation;
- submission persistence;
- execution orchestration;
- result persistence;
- learning-event persistence;
- rate limiting;
- access control;
- API contract.

**Docker Sandbox**
- compiler/runtime execution;
- isolated filesystem;
- resource limits;
- timeout enforcement;
- network isolation;
- process isolation.

**M4/NEXUS later**
- cognitive analysis;
- affective approach based on observable learning behavior;
- learning-state estimation;
- adaptive intervention;
- recommendation.

M3 does **not** perform psychological diagnosis or affective interpretation.

---

## 6. Code Editor

### 6.1 Editor

The primary M3 browser editor shall use **Monaco Editor**.

Monaco is responsible for the coding experience, not code execution. Compiler/runtime execution occurs only in the isolated Docker sandbox.

### 6.2 Capabilities

The editor should support:

- syntax highlighting;
- language-aware editing;
- code completion where supported;
- error markers where available;
- line numbers;
- indentation;
- keyboard shortcuts;
- code formatting where appropriate;
- starter code;
- code reset;
- Run;
- Submit;
- output display;
- compile/runtime error display;
- test result display.

### 6.3 Editor architecture

The editor must not directly communicate with Docker.

```text
Monaco
  |
  v
Laravel API
  |
  v
Execution Service
  |
  v
Sandbox
```

This allows the editor to be replaced later without redesigning the execution engine.

---

## 7. Programming Activity

A Programming Activity is a configurable learning activity.

A Programming Activity may define:

- title;
- instructions;
- activity-specific learning objective, when configured;
- difficulty, when configured;
- allowed language(s);
- starter code;
- editable files;
- expected behavior;
- visible test cases;
- hidden test cases;
- execution time limit;
- memory limit;
- source-code size limit;
- submission rules;
- grading/evaluation configuration.

It must integrate with the existing Course/Activity model from M2.

---

## 8. Multi-Language Architecture

M3 must not hard-code programming languages into controllers.

Use a configurable **Language Execution Profile** abstraction.

Conceptual profile:

```text
Language Execution Profile
|
+-- language identifier
+-- display name
+-- file extension
+-- source filename
+-- Docker image/runtime
+-- compile command
+-- run command
+-- execution mode
+-- timeout
+-- memory limit
+-- CPU limit
+-- network policy
+-- availability status
```

Initial language targets (architecture):

- Python
- C
- C++
- Java
- JavaScript / Node.js
- PHP
- Go / Golang
- Kotlin

A language may be fully operational, registered but pending runtime, or disabled. M3 v1 does not require all target languages to be operational simultaneously.

Adding a new language should primarily require an execution profile and sandbox runtime, not changes to core execution logic.

---

## 9. Code Execution

### Run

Run executes current code and returns structured feedback:

- execution status;
- stdout;
- stderr;
- compile error;
- runtime error;
- timeout status;
- execution duration;
- exit status.

### Submit

Submit creates a formal submission associated with the programming activity and preserves:

- user;
- course;
- activity;
- language;
- source code/version;
- timestamp;
- execution result;
- evaluation result.

---

## 10. Docker Sandbox

Student code is untrusted.

### Mandatory isolation

Student code must execute inside a dedicated Docker sandbox and never directly on the Laravel host.

### Security controls

As technically appropriate, enforce:

- CPU limits;
- memory limits;
- execution timeout;
- process/PID limits;
- isolated temporary filesystem;
- non-root execution;
- network disabled by default;
- container lifecycle isolation;
- automatic cleanup;
- restricted filesystem access;
- no access to MySQL;
- no access to Redis;
- no access to `.env`;
- no access to Laravel source;
- no access to Docker host socket.

### Docker socket

Student-facing requests must never receive arbitrary Docker commands or direct access to the Docker socket. The application must expose a controlled execution abstraction.

---

## 11. Execution Service

The execution service separates Laravel application logic from sandbox execution.

```text
Execution Request
      |
      v
Execution Service
      |
      +--> Validate profile
      +--> Create isolated workspace
      +--> Start sandbox
      +--> Execute
      +--> Capture result
      +--> Enforce limits
      +--> Cleanup
      |
      v
Execution Result
```

The exact implementation may be selected during implementation as long as these boundaries are preserved.

---

## 12. Automated Evaluation

M3 shall support automated test cases.

Each test case may contain:

- input;
- expected output;
- comparison strategy;
- visibility;
- ordering;
- execution constraints.

### Visible tests

Students may see configured test information and results.

### Hidden tests

Hidden test details must not be exposed to students.

---

## 13. Execution Result

An execution result should capture:

- execution identifier;
- submission identifier, if applicable;
- status;
- language;
- exit code;
- stdout;
- stderr;
- compile error;
- runtime error;
- timeout flag;
- execution duration;
- resource usage where available;
- test summary;
- created timestamp.

Possible statuses:

```text
queued
running
success
compile_error
runtime_error
timeout
memory_limit
resource_limit
cancelled
system_error
```

---

## 14. Submission and Evaluation Model

A formal submission is distinct from a transient Run request.

```text
Code Edit
   |
   +--> Run
   |     |
   |     +--> Execution Result
   |
   +--> Submit
         |
         +--> Submission
                |
                +--> Execution
                +--> Test Evaluation
                +--> Submission Result
```

This distinction is important for later learning analytics because repeated Run attempts and formal submissions are different observable behaviors.

---

## 15. Observable Learning Events

M3 shall produce structured events based on observable system behavior.

Initial vocabulary:

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

M3 records **what happened**, not an interpretation of why it happened.

Correct examples:

```text
student ran code 5 times
student encountered 3 compile errors
student submitted successfully after 4 attempts
```

Not M3 interpretations:

```text
student was frustrated
student was anxious
student lacked motivation
```

Such interpretation belongs to later M4 and must use the agreed cautious approach to affective inference from observable learning behavior.

---

## 16. Learning Event Contract

Conceptual structure:

```text
Learning Event
|
+-- event_id
+-- event_type
+-- user_id
+-- course_id
+-- activity_id
+-- programming_activity_id
+-- submission_id (nullable)
+-- execution_id (nullable)
+-- language
+-- timestamp
+-- metadata
```

`metadata` contains only event-relevant observable information.

---

## 17. Data Model

M3 should introduce the entities required by the architecture:

- Programming Activity
- Language Execution Profile
- Code Submission
- Code Execution
- Test Case
- Test Result
- Learning Event

Exact table/model names must follow existing M1/M2 conventions.

Before creating migrations, inspect M2 schema to avoid duplication.

Relationships should preserve links to:

- User;
- Course;
- Activity;
- Programming Activity;
- Submission;
- Execution;
- Learning Event.

---

## 18. API

M3 should expose controlled endpoints for:

- loading programming activity;
- loading starter code;
- selecting an allowed language;
- Run;
- Submit;
- retrieving execution status;
- retrieving execution result;
- retrieving submission result;
- retrieving appropriate test results.

Requirements:

- authentication;
- authorization;
- validation;
- rate limiting;
- source-code size validation;
- language validation;
- activity access validation;
- controlled error responses.

Internal Docker implementation details must not be exposed to the browser.

---

## 19. UI/UX

The programming interface should contain:

```text
+------------------------------------------------------+
| Programming Activity                                 |
| Instructions / Objective                             |
+----------------------------+-------------------------+
| Language: Python ▼         |                         |
+----------------------------+-------------------------+
|                            |                         |
|      Monaco Editor         |      Output /          |
|                            |      Feedback          |
|                            |                         |
+----------------------------+-------------------------+
| [Run] [Submit] [Reset]                              |
+------------------------------------------------------+
| Test Results / Execution Details                    |
+------------------------------------------------------+
```

Final styling must follow existing BisaBelajar UI conventions.

---

## 20. Student Workflow

```text
Open Programming Activity
        |
        v
Read Instructions
        |
        v
Select Allowed Language
        |
        v
Write/Edit Code
        |
        +------> Run
        |          |
        |          v
        |      Execute Sandbox
        |          |
        |          v
        |      View Feedback
        |          |
        |          +----> Edit Again
        |
        v
Submit
        |
        v
Automated Evaluation
        |
        v
Submission Result
```

Relevant observable events are recorded.

---

## 21. Course Configuration

Programming remains configurable per course.

A course may enable programming and configure:

```text
Allowed Languages
Starter Code
Test Cases
Execution Limits
Submission Rules
Evaluation Rules
```

A course may disable programming entirely.

---

## 22. Security Requirements

M3 must protect against:

- arbitrary code execution on host;
- filesystem access;
- network abuse;
- resource exhaustion;
- fork bombs/process abuse;
- malicious source code;
- malicious input;
- container escape;
- secret exposure;
- Docker socket abuse;
- unauthorized activity access;
- excessive execution requests.

Run/Submit operations should be rate-limited.

Source code and execution output must have appropriate size limits.

---

## 23. M3 and M4 Boundary

### M3 produces

**Observable Programming Learning Data**, such as:

- attempts;
- edits;
- executions;
- errors;
- test results;
- submissions;
- time;
- other observable patterns.

### M4/NEXUS later consumes

```text
Context Awareness
        ↓
Cognitive Analysis
        ↓
Cognitive–Affective Learning Approach
        ↓
Learning State
        ↓
Adaptive Intervention
        ↓
Learning Recommendation
```

M4 cognitive/affective logic must not be embedded in M3.

---

## 24. M3 Testing

Tests should cover:

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
- network disabled;
- host filesystem isolation;
- secret isolation;
- rate limiting.

### Learning events
- activity opened;
- run;
- submit;
- compile failure;
- runtime failure;
- timeout;
- test result;
- submission result.

### Regression

After M3:

```bash
php artisan test
```

must confirm the M1/M2 baseline remains passing.

Baseline:

```text
141 tests
1,067 assertions
```

M3 adds its own tests.

---

## 25. Multi-Language Acceptance

The architecture is accepted only if adding a new language does not require rewriting the core execution service.

| Language | Profile Required | Runtime |
|---|---:|---|
| Python | Yes | Python runtime |
| C | Yes | C compiler |
| C++ | Yes | C++ compiler |
| Java | Yes | JDK |
| JavaScript | Yes | Node.js |
| PHP | Yes | PHP runtime |
| Go | Yes | Go runtime |
| Kotlin | Yes | Kotlin/JVM runtime |

Operational status of each runtime must be documented during implementation.

---

## 26. M3 Acceptance Criteria

M3 is accepted only when:

1. Programming Activity integrates with M2.
2. Monaco Editor works in the browser.
3. Run and Submit workflows work.
4. Language selection is profile-driven.
5. At least one language executes successfully in Docker.
6. Execution is isolated from the Laravel host.
7. Compile/runtime errors are captured.
8. Timeouts/resource limits are enforced.
9. Automated test cases work.
10. Hidden tests remain protected.
11. Submissions are persisted.
12. Execution history is persisted.
13. Observable learning events are generated.
14. Learning event structure is suitable for M4.
15. M3 supports the initial multi-language architecture.
16. M1/M2 regression tests remain passing.
17. M3 tests pass.
18. Student execution cannot access MySQL, Redis, Laravel source, `.env`, or the Docker host socket.
19. No M4 cognitive/affective interpretation is embedded in M3.

---

## 27. Implementation Principles

Implementation should proceed incrementally:

### Phase A — Existing Architecture Inspection
Inspect M1/M2 course/activity models, routes, services, UI, migrations and tests.

### Phase B — M3 Domain Model
Implement programming activity and execution-profile abstractions.

### Phase C — Execution Architecture
Implement controlled execution service and Docker sandbox.

### Phase D — Persistence
Implement submissions, executions, test results and learning events.

### Phase E — API
Implement secure Run/Submit/status/result endpoints.

### Phase F — Browser Editor
Integrate Monaco Editor with existing BisaBelajar UI.

### Phase G — Evaluation
Implement visible/hidden test evaluation.

### Phase H — Learning Events
Implement the M3 observable-event contract.

### Phase I — Testing
Run M3 tests and complete M1/M2 regression testing.

---

## 28. Git and Change Management

Use a dedicated branch:

```text
feature/m3-programming-environment
```

M1/M2 baseline must remain intact.

Commits should be logical and incremental.

Do not modify M1/M2 functionality merely to simplify M3.

---

## 29. Research / AASVET Relevance

M3 provides the technical learning environment for the AI-VET research direction.

Its research relevance is not merely the presence of a code editor.

The important contribution is the connection:

```text
Programming Activity
        ↓
Observable Learning Behavior
        ↓
Structured Learning Events
        ↓
NEXUS / M4
        ↓
Learning-State Estimation
        ↓
Adaptive Learning
```

This allows the programming environment to become a source of learning evidence for AI-supported TVET learning analytics.

---

## 30. Explicit Non-Goals

M3 does NOT:

- diagnose psychological conditions;
- claim to directly measure emotions;
- infer affective state from a single event;
- implement cognitive analysis;
- implement affective analysis;
- implement learning-state inference;
- implement adaptive intervention;
- implement recommendation intelligence;
- execute student code directly on the Laravel host;
- expose Docker commands to students;
- require every course to have programming functionality.

Those responsibilities belong to later modules, particularly M4.

---

## 31. Final Architecture Summary

```text
                         BISA BELAJAR
                              |
                    Course / Learning Activity
                              |
                    +---------+---------+
                    |                   |
                 Other              Programming
               Activities            Activity
                                        |
                                        v
                               Monaco Editor
                                        |
                                  Run / Submit
                                        |
                                        v
                                  Laravel API
                                        |
                                        v
                              Execution Service
                                        |
                                        v
                              Docker Sandbox
                           /   /   |   \   \   \
                          /   /    |    \   \   \
                     Python C/C++ Java Node PHP Go Kotlin
                                        |
                                        v
                               Execution Results
                                        |
                          +-------------+-------------+
                          |                           |
                          v                           v
                    Student Feedback          Learning Events
                                                      |
                                                      v
                                                M4 / NEXUS
                                                      |
                                +---------------------+------------------+
                                |                     |                  |
                                v                     v                  v
                         Cognitive Analysis   Affective Approach   Context Awareness
                                |                     |                  |
                                +---------------------+------------------+
                                                      |
                                                      v
                                               Learning State
                                                      |
                                                      v
                                           Adaptive Intervention
                                                      |
                                                      v
                                          Learning Recommendation
```

---

## 32. Specification Status

**M3 Specification v1.0**

Status:

> **REVIEWED — READY FOR IMPLEMENTATION**

Scope and architecture are locked for M3 v1. Implementation should follow this specification without adding new M3 features.
