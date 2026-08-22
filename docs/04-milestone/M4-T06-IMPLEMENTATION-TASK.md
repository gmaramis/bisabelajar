# M4-T06 — Closed-Loop NEXUS Integration & Validation

## Status

**V1 — Implementation Specification**

M4-T06 adalah tahap terakhir dari M4.

Tujuan utamanya bukan membuat algoritma baru, tetapi memastikan komponen M4 bekerja sebagai satu **closed-loop learning cycle**.

Prinsip:

> T02 memvalidasi evidence.  
> T03 memahami kondisi learner.  
> T04 memberikan bantuan.  
> T05 menentukan langkah berikutnya.  
> T06 memastikan semuanya terhubung dan dapat berjalan kembali melalui evidence baru.

M4-T06 **bukan M5**.

M5 tetap menjadi research-evidence and longitudinal learning analytics layer.

---

# 1. Tujuan

T06 memastikan closed-loop NEXUS berjalan secara terintegrasi:

```text
Learner Activity
      ↓
Learning Evidence
      ↓
M4-T02
Evidence Validation
      ↓
M4-T03
Learning State Inference
      ↓
M4-T04
Adaptive Intervention
      ↓
Learner Response / Retry
      ↓
M4-T05
Next Learning Action
      ↓
Next Learning Activity
      ↓
New Learning Evidence
      ↓
kembali ke T02
```

T06 menjawab:

> "Apakah seluruh mekanisme M4 dapat bekerja sebagai satu siklus pembelajaran yang konsisten?"

---

# 2. Scope T06

T06 bertanggung jawab atas:

- integrasi T02 → T03;
- integrasi T03 → T04;
- integrasi T04 → learner response;
- integrasi learner response → T05;
- integrasi T05 → next learning action/activity;
- evidence baru masuk kembali ke loop;
- provenance seluruh siklus;
- idempotency;
- integration testing;
- scope validation;
- pencegahan loop yang tidak valid atau berulang tanpa evidence baru.

T06 tidak bertanggung jawab atas:

- membuat inference algorithm baru;
- membuat intervention algorithm baru;
- membuat recommendation algorithm baru;
- longitudinal analytics;
- research analysis;
- AI-generated reassessment question engine;
- ML recommendation;
- LLM decision-maker.

---

# 3. Baseline Existing Components

T06 harus menggunakan implementasi yang sudah ada:

```text
M4-T02
Evidence Validation

M4-T03
Learning State Inference

M4-T04
Adaptive Intervention + Socratic Response

M4-T05
Next Learning Action Decision
```

Jangan menduplikasi fungsi komponen tersebut.

T06 berfungsi sebagai integration/orchestration layer dan validation layer jika memang dibutuhkan oleh arsitektur yang sudah ada.

---

# 4. Closed-Loop Definition

Closed loop minimal harus memiliki siklus:

```text
Evidence
  ↓
State
  ↓
Intervention
  ↓
Learner Response
  ↓
Next Action
  ↓
New Evidence
  ↓
State update/inference
```

Siklus dianggap valid jika:

1. evidence dapat divalidasi;
2. usable evidence dapat menghasilkan Learning State;
3. Learning State dapat menghasilkan intervention bila diperlukan;
4. learner dapat melakukan response/retry;
5. response menghasilkan evidence baru;
6. evidence baru dapat diproses kembali;
7. T05 dapat menentukan next learning action;
8. seluruh proses dapat ditelusuri.

---

# 5. Integration Flow

## 5.1 Initial Evidence

Learner menyelesaikan activity/task.

```text
Learner
  ↓
Learning Event
  ↓
Evidence
```

T02 memvalidasi evidence.

---

## 5.2 Learning State

Evidence yang usable diteruskan ke T03.

```text
Validated Evidence
       ↓
LearningStateInferenceService
       ↓
Learning State
```

T03 tetap menjadi satu-satunya komponen yang bertanggung jawab atas inference Learning State.

---

## 5.3 Intervention

Jika state membutuhkan support:

```text
Learning State
       ↓
AdaptiveInterventionService
       ↓
Adaptive Intervention
```

T04 tetap menjadi komponen yang bertanggung jawab atas intervention.

---

## 5.4 Learner Response

Learner merespons intervention.

Response dapat berupa:

- retry;
- corrected attempt;
- new task attempt;
- response terhadap Socratic question;
- aktivitas belajar berikutnya.

Response harus menghasilkan learning evidence yang dapat diproses kembali.

---

## 5.5 Next Learning Action

T05 membaca kondisi terbaru.

```text
Current State
+
Evidence
+
Intervention
+
Learner Response
+
Context
       ↓
T05
       ↓
Next Learning Action
```

---

## 5.6 Loop Restart

Next action dapat menyebabkan aktivitas baru.

Contoh:

```text
review_concept
practice_again
guided_retry
reassessment
continue
collect_more_evidence
```

Aktivitas tersebut menghasilkan evidence baru.

Evidence baru kembali ke:

```text
T02
```

dan siklus dapat berlanjut.

---

# 6. Orchestration

Jika codebase membutuhkan orchestration service, buat service dengan tanggung jawab terbatas.

Contoh:

```text
NexusClosedLoopService
```

Service tersebut dapat:

1. menerima event/evidence baru;
2. menjalankan validation;
3. mendapatkan Learning State;
4. menjalankan intervention bila diperlukan;
5. memproses response bila tersedia;
6. mendapatkan next learning action;
7. menyimpan loop/provenance context.

Service **tidak boleh mengambil alih rules** T02–T05.

Jika orchestration sudah tersedia dalam codebase, gunakan mekanisme yang sudah ada daripada membuat service baru.

---

# 7. State Transition

T06 harus memvalidasi bahwa transition masuk akal.

Contoh:

```text
needs_support
      ↓
intervention
      ↓
retry
      ↓
progressing
```

atau:

```text
needs_support
      ↓
intervention
      ↓
retry failure
      ↓
needs_support
      ↓
review/practice/reassessment
```

atau:

```text
insufficient_evidence
      ↓
collect_more_evidence
      ↓
new evidence
      ↓
Learning State
```

T06 tidak boleh memaksa learner menjadi `progressing` atau `stable`.

State tetap ditentukan oleh T03.

---

# 8. No False Loop

Closed loop tidak berarti sistem harus selalu menghasilkan intervention.

Contoh:

```text
stable
  ↓
continue
```

tidak perlu:

```text
stable
  ↓
intervention
  ↓
intervention lagi
```

Demikian juga:

```text
progressing
  ↓
continue
```

tidak boleh menghasilkan remedial intervention tanpa evidence yang mendukung.

---

# 9. No Duplicate Loop

Sistem harus mencegah pemrosesan yang sama menghasilkan:

- duplicate intervention;
- duplicate next action;
- duplicate state record yang tidak diperlukan;
- infinite retry;
- infinite intervention.

Gunakan mekanisme idempotency yang konsisten dengan T03–T05.

T06 tidak boleh membuat intervention baru hanya karena orchestration dipanggil ulang dengan input yang sama.

---

# 10. Provenance

T06 harus mempertahankan traceability.

Minimal:

```text
LearningEvent
    ↓
ValidatedEvidence
    ↓
LearningState
    ↓
AdaptiveIntervention
    ↓
LearnerResponse / New Evidence
    ↓
NextLearningAction
```

Jika tersedia, integration record dapat menyimpan:

- loop/cycle identifier;
- learner;
- activity;
- evidence references;
- learning state reference;
- intervention reference;
- next action reference;
- timestamps;
- decision context.

Tidak wajib membuat tabel baru jika existing records sudah cukup untuk menelusuri siklus.

---

# 11. Cycle Identifier

Jika diperlukan oleh arsitektur, gunakan identifier untuk satu learning cycle.

Contoh:

```text
cycle_id
```

Tujuannya untuk menghubungkan:

```text
initial evidence
→ state
→ intervention
→ response
→ next action
→ new evidence
```

Jangan menggunakan cycle identifier sebagai pengganti provenance existing.

---

# 12. Reassessment Boundary

T06 dapat menjalankan integration flow menuju:

```text
reassessment
```

Tetapi T06 **tidak membuat soal reassessment**.

Flow:

```text
T05
reassessment
      ↓
reassessment activity
      ↓
learner response
      ↓
new evidence
      ↓
T02
```

Jika di masa depan AI digunakan untuk membuat soal baru, kemampuan yang ingin diuji harus tetap sama walaupun pertanyaan/tugas berbeda.

Fitur tersebut bukan scope T06.

---

# 13. Context Preservation

T06 harus mempertahankan konteks yang dibutuhkan oleh komponen M4.

Contoh:

- learner;
- course;
- activity;
- task;
- learning event;
- intervention;
- state;
- evidence;
- action.

Jangan melakukan cross-course atau cross-cohort analytics.

---

# 14. Error Handling

T06 harus menangani kondisi ketika:

### Evidence tidak valid

```text
Evidence invalid
    ↓
jangan membuat strong Learning State
```

### Evidence tidak cukup

```text
insufficient_evidence
    ↓
collect_more_evidence
```

### Intervention gagal dibuat

Jangan membuat next action berdasarkan intervention yang tidak pernah berhasil dibuat, kecuali T05 memang dapat mengambil keputusan berdasarkan state/evidence tanpa intervention.

### Next action gagal dibuat

Jangan membuat loop palsu atau intervention baru hanya untuk memaksa loop berjalan.

Error harus dapat dilacak.

---

# 15. Atomicity

Jika orchestration melakukan beberapa operasi database dalam satu logical cycle, gunakan transaction bila sesuai.

Tujuannya:

- menghindari partial state;
- menghindari intervention tanpa state;
- menghindari action tanpa provenance;
- menjaga consistency.

Jangan menggunakan transaction secara berlebihan untuk proses yang memang harus asynchronous.

Ikuti pola arsitektur existing.

---

# 16. Testing Strategy

T06 membutuhkan integration tests.

Fokus bukan hanya unit test masing-masing service.

Harus ada test yang menjalankan alur:

```text
Evidence
→ Validation
→ Learning State
→ Intervention
→ Response/New Evidence
→ Next Action
```

---

# 17. Integration Test Scenarios

Minimal implementasikan skenario berikut.

## Scenario A — Successful Learning

```text
valid evidence
→ progressing/stable
→ no remedial intervention
→ continue
```

Expected:

- evidence valid;
- state valid;
- next action `continue`;
- tidak ada duplicate intervention.

---

## Scenario B — Learner Needs Support

```text
valid evidence
→ needs_support
→ intervention
→ learner retry
→ new evidence
→ T05
```

Expected:

- intervention dibuat;
- retry menghasilkan evidence baru;
- evidence baru diproses kembali;
- next action tersedia.

---

## Scenario C — Support Improves Outcome

```text
needs_support
→ Socratic / guided intervention
→ successful retry
→ improved evidence
→ next action continue
```

Expected:

- learner tidak tetap dipaksa remedial;
- next action dapat menjadi `continue`.

---

## Scenario D — Support Does Not Improve Outcome

```text
needs_support
→ intervention
→ failed retry
→ new evidence
→ needs_support
→ review/practice/reassessment
```

Expected:

- loop tetap berjalan;
- intervention tidak diduplikasi tanpa evidence baru;
- next action sesuai rule T05.

---

## Scenario E — Insufficient Evidence

```text
insufficient evidence
→ insufficient_evidence
→ collect_more_evidence
→ new evidence
→ re-enter loop
```

Expected:

- tidak ada strong intervention tanpa evidence;
- loop dapat dimulai kembali setelah evidence baru.

---

## Scenario F — Reassessment

```text
weak area persists
→ T05 = reassessment
→ learner completes different task
→ new evidence
→ T02
→ T03
```

Expected:

- T06 mengintegrasikan flow;
- T06 tidak membuat question generator.

---

# 18. Idempotency Tests

Test minimal:

1. proses evidence yang sama dua kali;
2. proses event yang sama dua kali;
3. orchestration dipanggil ulang;
4. pastikan tidak menghasilkan duplicate intervention/action yang tidak semestinya.

---

# 19. Regression Tests

Setelah T06 selesai, jalankan:

```text
M4-T02 EvidenceValidationTest
M4-T03 LearningStateInferenceTest
M4-T04 AdaptiveInterventionTest
M4-T05 NextLearningActionTest
M4-T06 ClosedLoopIntegrationTest
M3ProgrammingEnvironmentTest
Full regression
```

Expected:

```text
T02 PASS
T03 PASS
T04 PASS
T05 PASS
T06 PASS
M3 PASS
Full regression PASS
```

---

# 20. Acceptance Criteria

M4-T06 dianggap selesai jika:

- [ ] T02 dapat terhubung ke T03
- [ ] T03 dapat terhubung ke T04
- [ ] T04 dapat menghasilkan intervention
- [ ] learner response dapat menghasilkan evidence baru
- [ ] evidence baru dapat kembali ke T02
- [ ] T05 dapat menentukan next learning action
- [ ] closed loop dapat berjalan minimal satu siklus penuh
- [ ] successful retry dapat menghasilkan alur menuju `continue`
- [ ] failed retry dapat menghasilkan alur menuju support/review/practice/reassessment
- [ ] insufficient evidence dapat kembali meminta evidence
- [ ] reassessment dapat kembali menghasilkan new evidence
- [ ] provenance dapat ditelusuri
- [ ] duplicate processing tidak menghasilkan duplicate loop yang tidak semestinya
- [ ] tidak terjadi infinite intervention
- [ ] tidak terjadi infinite retry
- [ ] tidak terjadi infinite recommendation/action
- [ ] T02 production flow tetap aman
- [ ] T03 inference rules tetap aman
- [ ] T04 intervention rules tetap aman
- [ ] T05 decision rules tetap aman
- [ ] integration tests PASS
- [ ] full regression PASS

---

# 21. Research Traceability

T06 menyelesaikan mekanisme closed-loop NEXUS.

```text
Validated Evidence
        ↓
Learning State
        ↓
Adaptive Intervention
        ↓
Learner Response
        ↓
Next Learning Action
        ↓
New Evidence
        ↺
```

Dengan T06 selesai, M4 menyediakan mekanisme operasional:

> **Observe → Understand → Support → Act → Observe Again**

M5 kemudian dapat mengambil data dari siklus tersebut untuk penelitian longitudinal.

---

# 22. Boundary with M5

T06:

> memastikan loop berjalan.

M5:

> menganalisis apa yang terjadi setelah loop berjalan berkali-kali.

Contoh pertanyaan M5:

- apakah weak area membaik?
- apakah learner yang mendapat intervention menunjukkan improvement?
- berapa kali learner membutuhkan support?
- apakah reassessment meningkatkan hasil?
- bagaimana trajectory learner berubah?
- apakah context berbeda menghasilkan pola berbeda?

Pertanyaan tersebut **bukan scope T06**.

---

# 23. No New Intelligence Rule

T06 tidak boleh menjadi tempat untuk "membuat AI lebih pintar".

T06 hanya menghubungkan intelligence yang sudah dibangun:

```text
T03 = state inference
T04 = intervention
T05 = next action
T06 = integration
```

Jika ada kebutuhan algoritma baru, masukkan ke task/research phase yang sesuai, jangan diam-diam ditambahkan ke T06.

---

# 24. Implementation Rules

Saat mengerjakan task:

1. Baca T02.
2. Baca T03.
3. Baca T04.
4. Baca T05.
5. Pahami existing models/services.
6. Implementasikan integration dengan perubahan seminimal mungkin.
7. Jangan menduplikasi logic T02–T05.
8. Jangan mengubah inference rules T03.
9. Jangan mengubah intervention rules T04.
10. Jangan mengubah decision rules T05 kecuali diperlukan untuk integrasi dan disetujui.
11. Jangan membuat M5.
12. Jangan membuat longitudinal analytics.
13. Jangan membuat AI question generator.
14. Jangan membuat ML/LLM decision engine.
15. Jangan commit.
16. Jangan push.

Jika diperlukan perubahan pada T02/T03/T04/T05 yang lebih dari perubahan kompatibilitas kecil:

> STOP dan laporkan terlebih dahulu.

---

# 25. Suggested Implementation Sequence

```text
1. Inspect T02
        ↓
2. Inspect T03
        ↓
3. Inspect T04
        ↓
4. Inspect T05
        ↓
5. Identify existing flow
        ↓
6. Define integration boundary
        ↓
7. Implement orchestration only if needed
        ↓
8. Implement cycle/provenance tracking only if needed
        ↓
9. Add integration tests
        ↓
10. Test successful loop
        ↓
11. Test support loop
        ↓
12. Test failed retry loop
        ↓
13. Test insufficient evidence loop
        ↓
14. Test reassessment loop
        ↓
15. Test idempotency
        ↓
16. Run regression
        ↓
17. Report
        ↓
18. STOP
```

---

# 26. Final Report Format

Setelah selesai, laporkan:

## 1. Files created

Daftar file baru.

## 2. Files modified

Daftar file yang diubah.

## 3. Integration summary

Jelaskan bagaimana T02–T05 terhubung.

## 4. Closed-loop flow

Tampilkan flow aktual yang berhasil.

## 5. Cycle/provenance

Jelaskan bagaimana satu cycle dapat ditelusuri.

## 6. Idempotency

Jelaskan bagaimana duplicate processing dicegah.

## 7. Integration tests

Laporkan semua skenario.

## 8. Regression

Laporkan:

- T02
- T03
- T04
- T05
- T06
- M3
- full regression

## 9. Concerns / limitations

Laporkan keterbatasan.

## 10. Scope verification

Pastikan:

```text
M4-T06 ONLY
No M5
No longitudinal analytics
No ML
No LLM decision-maker
No AI question generator
No commit
No push
```

---

# 27. STOP

Setelah M4-T06 selesai:

**STOP.**

Jangan mulai M5.

Jangan menambahkan research analytics.

Jangan commit.

Jangan push.

Tunggu review manusia terlebih dahulu.

M4 harus dianggap selesai hanya setelah T06 direview dan dinyatakan selesai.
