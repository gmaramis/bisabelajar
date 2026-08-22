# M5-01 — Research Evidence & Data Foundation

## Status

**V1 — Implementation Task**

M5-01 adalah fondasi pertama M5.

Tujuan utamanya adalah memastikan hasil dari closed-loop NEXUS pada M4 dapat digunakan kembali sebagai **research evidence** yang historis, terstruktur, dapat ditelusuri, dan siap dianalisis pada M5 berikutnya.

M5-01 **tidak melakukan analisis longitudinal** dan tidak menentukan Learning State, intervention, atau next action.

---

# 1. Baseline

M4 telah selesai:

```text
M4-T02  Evidence Validation
M4-T03  Learning State Inference
M4-T04  Adaptive Intervention
M4-T05  Next Learning Action
M4-T06  Closed-Loop Integration
```

M5 menggunakan hasil M4 sebagai input.

Prinsip:

```text
M4 = Understand + Act

M5 = Observe + Compare + Analyze + Preserve Research Evidence
```

---

# 2. Tujuan M5-01

M5-01 harus menyediakan fondasi untuk menelusuri:

```text
Learner
   ↓
Course
   ↓
Activity / Task
   ↓
Learning Event
   ↓
Validated Evidence
   ↓
Learning State
   ↓
Intervention
   ↓
Next Learning Action
   ↓
New Learning Event
   ↓
New Evidence
   ↓
New Learning State
```

Data historis tidak boleh ditimpa hanya karena learner memperoleh state baru.

---

# 3. Scope

M5-01 bertanggung jawab atas:

- research evidence foundation;
- historical preservation;
- provenance;
- hubungan antar-record M3/M4;
- chronological ordering;
- research context metadata;
- akses terhadap evidence untuk task M5 berikutnya;
- memastikan data dapat digunakan untuk longitudinal analysis.

M5-01 **tidak** bertanggung jawab atas:

- Learning State inference;
- adaptive intervention;
- next learning action;
- weak area identification;
- AI reassessment;
- improvement analysis;
- contextual comparison analysis;
- statistical analysis;
- predictive ML;
- causal inference;
- research export final.

---

# 4. Existing Data First

Sebelum membuat migration/model/service baru:

1. Inspect database schema.
2. Inspect existing migrations.
3. Inspect:
   - LearningEvent
   - ValidatedEvidence
   - LearningState
   - AdaptiveIntervention
   - NextLearningAction
   - Activity
   - Course / learning context yang tersedia.
4. Inspect relationships existing.
5. Tentukan apakah kebutuhan M5-01 sebenarnya sudah dapat dipenuhi oleh struktur existing.

**Jangan membuat tabel research baru hanya karena nama "research evidence" terdengar perlu.**

Jika existing data sudah cukup, gunakan existing structure.

Jika diperlukan struktur tambahan, jelaskan alasan dan dependency-nya terlebih dahulu.

---

# 5. Research Evidence Principle

M5 tidak boleh menciptakan evidence yang tidak memiliki sumber.

Setiap research record harus dapat ditelusuri kembali ke source.

Minimal provenance:

```text
Research Evidence
      ↓
Validated Evidence
      ↓
Learning Event
```

Untuk state/intervention/action:

```text
Learning State
      ↓
Validated Evidence
      ↓
Learning Event
```

```text
Adaptive Intervention
      ↓
Learning State
      ↓
Validated Evidence
      ↓
Learning Event
```

```text
Next Learning Action
      ↓
Learning State / Evidence
      ↓
Validated Evidence
      ↓
Learning Event
```

---

# 6. Historical Preservation

M5-01 harus mempertahankan history.

Contoh:

```text
Activity 1
needs_support
    ↓
Activity 2
needs_support
    ↓
Activity 3
progressing
    ↓
Activity 4
stable
```

Jangan mengubah record Activity 1 menjadi `stable` hanya karena state terbaru learner adalah stable.

History harus tetap tersedia.

---

# 7. Chronological Ordering

Research evidence harus dapat diurutkan berdasarkan waktu.

Minimal pertimbangkan:

- event timestamp;
- evidence timestamp;
- state creation/inference timestamp;
- intervention timestamp;
- action timestamp;
- session jika tersedia.

Jangan mengasumsikan `created_at` selalu merupakan waktu pedagogical event jika source memiliki timestamp event yang lebih tepat.

Jika terdapat perbedaan timestamp, dokumentasikan semantics yang digunakan.

---

# 8. Learner Context

Research evidence harus dapat dikaitkan dengan learner.

Minimal:

- learner identifier;
- activity;
- course;
- learning unit/module bila tersedia;
- session bila tersedia;
- timestamp.

Jangan menyalin data learner secara berlebihan.

Gunakan reference/foreign key bila sesuai.

---

# 9. Research Context

M5-01 harus mempertahankan konteks yang diperlukan untuk penelitian.

Minimal, jika tersedia dalam sistem:

- campus/institution;
- course;
- module/learning unit;
- activity;
- task;
- programming language;
- task type;
- cohort;
- session/time period.

Konteks yang belum tersedia **jangan dibuat secara fiktif**.

Jika field belum tersedia tetapi diperlukan oleh research design, laporkan sebagai gap.

---

# 10. Python vs C++ Context

Karena evaluasi penelitian akan melibatkan konteks pembelajaran berbeda:

```text
Campus A → Python
Campus B → C++
```

M5-01 harus memastikan programming language dapat ditelusuri jika memang merupakan bagian dari Activity/Course/Programming Environment.

Jangan hard-code Python atau C++.

Gunakan context yang configurable.

Tujuan M5-01 hanya menyimpan konteks tersebut.

Analisis perbandingan dilakukan pada M5-06.

---

# 11. Learning State History

M5-01 harus memastikan hasil T03 dapat digunakan sebagai historical dataset.

Setiap Learning State harus dapat diketahui:

- learner;
- activity;
- state;
- confidence;
- explanation;
- inference rule;
- evidence references;
- timestamp.

Jangan membuat ulang Learning State menggunakan algoritma baru.

Gunakan hasil T03.

---

# 12. Intervention History

Jika intervention T04 tersedia, research layer harus dapat menghubungkannya dengan:

- learner;
- Learning State;
- activity;
- intervention type;
- selection rule;
- reason;
- timestamp;
- provenance.

M5-01 tidak mengubah intervention rule.

---

# 13. Next Learning Action History

Jika T05 menghasilkan Next Learning Action, research evidence harus dapat menghubungkannya dengan:

- learner;
- Learning State;
- evidence;
- intervention bila ada;
- action type;
- decision rule;
- reason;
- timestamp.

M5-01 tidak mengubah decision rule T05.

---

# 14. Closed-Loop Trace

M5-01 harus memungkinkan satu learning cycle ditelusuri.

Target trace:

```text
LearningEvent
    ↓
ValidatedEvidence
    ↓
LearningState
    ↓
AdaptiveIntervention
    ↓
NextLearningAction
    ↓
Next Activity / LearningEvent
    ↓
New ValidatedEvidence
```

Jika `cycle_id` dari M4-T06 tersedia, manfaatkan.

Jika tidak tersedia secara persistent dan provenance existing sudah cukup, **jangan membuat cycle table hanya untuk M5-01**.

---

# 15. Data Duplication Rule

Hindari duplikasi data.

Contoh yang tidak diinginkan:

```text
validated_evidence
research_evidence_copy
research_evidence_archive
research_state_copy
```

tanpa alasan arsitektural.

Prioritas:

1. gunakan existing record;
2. gunakan relation/reference;
3. buat projection/read model hanya jika memang dibutuhkan;
4. buat snapshot/history table hanya jika historical semantics tidak dapat dipenuhi oleh existing records.

---

# 16. Research Evidence Read Model

Jika diperlukan, dapat dibuat read model/service untuk menyatukan data:

```text
Learner
+
Activity
+
ValidatedEvidence
+
LearningState
+
Intervention
+
NextLearningAction
+
Context
```

Read model tidak boleh mengubah source-of-truth.

Contoh nama konseptual:

```text
ResearchEvidenceService
```

atau

```text
ResearchEvidenceQuery
```

Gunakan naming yang konsisten dengan codebase.

Jangan membuat service baru jika existing query/relation sudah cukup.

---

# 17. Evidence Quality

M5-01 harus mempertahankan informasi quality/confidence dari M4-T02.

Research evidence tidak boleh menghapus:

- evidence quality;
- evidence confidence;
- validation status;
- context-dependent status;
- evidence type.

M5 berikutnya harus dapat membedakan evidence yang kuat dan evidence yang terbatas.

---

# 18. Behavioral Evidence

Observable behavioral indicators harus tetap dipertahankan.

Contoh:

- persistent attempt behavior;
- corrective behavior;
- persistent engagement;
- reduced activity engagement.

Tetap gunakan observable behavior.

Jangan mengubahnya menjadi:

- motivation diagnosis;
- anxiety;
- depression;
- psychological state;
- personality trait.

---

# 19. Bloom / Dave Context

Jika Activity mempunyai:

```text
bloom_demand
dave_demand
```

M5-01 harus mempertahankan informasi tersebut sebagai **task demand**.

Jangan mengubahnya menjadi learner capability.

Contoh benar:

```text
Bloom demand = Apply
```

Bukan:

```text
Learner Bloom level = Apply
```

M5-01 hanya menyediakan context tersebut untuk M5-03/M5-04 dan research analysis berikutnya.

---

# 20. Privacy / Research Identifier

M5-01 harus memisahkan kebutuhan research identifier dari data identitas bila memungkinkan.

Jika penelitian membutuhkan pseudonymized identifier:

```text
research_learner_id
```

gunakan mekanisme yang konsisten dan dapat direproduksi sesuai kebutuhan sistem.

Jangan menyimpan data pribadi tambahan hanya untuk research analytics.

Jangan mengubah existing authentication identity tanpa kebutuhan.

---

# 21. Acceptance Criteria

M5-01 dianggap berhasil jika:

- [ ] Research evidence dapat mengambil data dari M4.
- [ ] Validated Evidence dapat ditelusuri ke LearningEvent.
- [ ] Learning State dapat ditelusuri ke evidence.
- [ ] Intervention dapat ditelusuri ke Learning State/evidence.
- [ ] Next Learning Action dapat ditelusuri ke state/evidence/intervention bila relevan.
- [ ] Historical records tidak ditimpa.
- [ ] Data dapat diurutkan secara kronologis.
- [ ] Learner dapat diidentifikasi secara konsisten.
- [ ] Activity/course context dapat ditelusuri.
- [ ] Programming language dapat ditelusuri bila tersedia.
- [ ] Campus/institution context dapat ditelusuri bila tersedia.
- [ ] Evidence quality/confidence tetap tersedia.
- [ ] Bloom/Dave task demand tetap tersedia bila ada.
- [ ] Observable behavioral indicators tetap tersedia.
- [ ] Provenance dapat ditelusuri.
- [ ] Tidak ada source data yang diduplikasi tanpa alasan.
- [ ] Tidak ada perubahan semantics M3/M4.
- [ ] Tidak ada Learning State baru yang dibuat oleh M5-01.
- [ ] Tidak ada intervention baru yang dibuat oleh M5-01.
- [ ] Tidak ada next action baru yang dibuat oleh M5-01.
- [ ] Tidak ada longitudinal analysis.
- [ ] Tests PASS.

---

# 22. Testing Requirements

Buat automated tests untuk minimal:

## Test 1 — Evidence Provenance

Pastikan research evidence dapat ditelusuri:

```text
Research → ValidatedEvidence → LearningEvent
```

## Test 2 — State Provenance

Pastikan:

```text
LearningState → ValidatedEvidence
```

dapat ditelusuri.

## Test 3 — Intervention Provenance

Pastikan:

```text
Intervention → LearningState → Evidence
```

dapat ditelusuri.

## Test 4 — Action Provenance

Pastikan:

```text
NextAction → LearningState/Evidence/Intervention
```

dapat ditelusuri sesuai data yang tersedia.

## Test 5 — Historical Preservation

Buat beberapa state berurutan:

```text
needs_support
progressing
stable
```

Pastikan ketiganya tetap tersedia.

## Test 6 — Chronological Ordering

Pastikan evidence/state dapat dikembalikan dalam urutan waktu yang benar.

## Test 7 — Context

Pastikan research data dapat mempertahankan context:

```text
campus
course
programming language
activity
```

jika field tersedia.

## Test 8 — Evidence Quality

Pastikan quality/confidence tidak hilang.

## Test 9 — Bloom/Dave

Pastikan task demand tetap tersedia dan tidak berubah menjadi learner capability.

## Test 10 — No M4 Mutation

Pastikan menjalankan M5-01 tidak mengubah:

- Learning State inference;
- intervention;
- next action.

---

# 23. Regression

Setelah implementasi jalankan minimal:

```text
M5-01 tests
M4-T06 NexusClosedLoopIntegrationTest
M4-T05 NextLearningActionTest
M4-T04 AdaptiveInterventionTest
M4-T03 LearningStateInferenceTest
M4-T02 EvidenceValidationTest
M3ProgrammingEnvironmentTest
Full regression
```

Expected:

```text
M5-01 PASS
M4-T06 PASS
M4-T05 PASS
M4-T04 PASS
M4-T03 PASS
M4-T02 PASS
M3 PASS
Full regression PASS
```

Jika regression gagal:

1. identifikasi penyebab;
2. jangan memperbaiki unrelated issue;
3. perbaiki hanya masalah yang berasal dari M5-01;
4. jika membutuhkan perubahan semantics M3/M4, STOP dan laporkan.

---

# 24. Scope Fence

SELAMA M5-01:

JANGAN mengerjakan:

- M5-02 trajectory;
- M5-03 weak area detection;
- M5-04 AI reassessment;
- M5-05 improvement analysis;
- M5-06 contextual variation analysis;
- M5-07 research export;
- ML;
- LLM;
- AI question generation;
- statistical analysis;
- predictive analytics;
- causal inference;
- dashboard research;
- paper generation.

M5-01 hanya membangun **foundation**.

---

# 25. Implementation Sequence

```text
1. Read this task document
        ↓
2. Inspect existing M3/M4 schema
        ↓
3. Inspect existing relations
        ↓
4. Identify data gaps
        ↓
5. Decide whether new schema is actually required
        ↓
6. Implement minimal foundation
        ↓
7. Add tests
        ↓
8. Run M5-01 tests
        ↓
9. Run M4 regression
        ↓
10. Run M3 regression
        ↓
11. Run full regression
        ↓
12. Inspect git diff
        ↓
13. Produce final report
        ↓
14. STOP
```

---

# 26. Important Architectural Rule

**M5-01 bukan data warehouse.**

Kita sedang membangun research-ready application layer, bukan sistem big-data.

Prioritas:

```text
Traceability
+
Historical Preservation
+
Research Context
+
Evidence Integrity
```

lebih penting daripada membuat struktur database yang besar.

---

# 27. Research Traceability

M5-01 menjadi fondasi bagi:

```text
M5-02
Learning State Change
        ↓
M5-03
Weak Area
        ↓
M5-04
AI-Assisted Reassessment
        ↓
M5-05
Intervention Response + Improvement
        ↓
M5-06
Contextual Variation
        ↓
M5-07
Research Evidence Export
```

Tanpa M5-01 yang baik, hasil M5 berikutnya sulit dipertanggungjawabkan secara ilmiah.

---

# 28. Research Boundary

M5-01 tidak membuktikan bahwa:

- intervention efektif;
- learner membaik;
- AI reassessment efektif;
- Python lebih baik daripada C++;
- satu kampus lebih baik daripada kampus lain.

M5-01 hanya memastikan:

> **Data yang diperlukan untuk menguji pertanyaan-pertanyaan tersebut tersedia, historis, terstruktur, dan dapat ditelusuri.**

---

# 29. Final Report Format

Setelah selesai, laporkan:

## 1. Files created

## 2. Files modified

## 3. Existing data structures reused

## 4. New structures, jika ada

Jelaskan mengapa diperlukan.

## 5. Research evidence architecture

Tampilkan:

```text
Learner
 ↓
Activity
 ↓
LearningEvent
 ↓
ValidatedEvidence
 ↓
LearningState
 ↓
Intervention
 ↓
NextLearningAction
```

## 6. Provenance

Jelaskan bagaimana setiap record dapat ditelusuri.

## 7. Historical preservation

Jelaskan bagaimana history dipertahankan.

## 8. Context

Laporkan field context yang tersedia.

## 9. Tests

Laporkan setiap test.

## 10. Regression

Laporkan:

- T02
- T03
- T04
- T05
- T06
- M3
- Full regression

## 11. Data gaps

Laporkan field research yang belum tersedia jika ada.

## 12. Concerns / limitations

## 13. Scope verification

Pastikan:

```text
M5-01 ONLY
No M5-02
No M5-03
No M5-04
No M5-05
No M5-06
No M5-07
No ML
No LLM
No AI question generator
No longitudinal analysis
No commit
No push
```

---

# 30. STOP

Setelah M5-01 selesai:

**STOP.**

Jangan mulai M5-02.

Jangan commit.

Jangan push.

Tunggu review manusia.
