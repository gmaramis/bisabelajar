# M4-T03 — Learning State Inference

## Status

**V1 — Specification Locked**

M4-T03 adalah layer NEXUS yang pertama kali melakukan inference terhadap Learning State learner berdasarkan evidence yang sudah divalidasi oleh M4-T02.

T03 menggunakan tiga dimensi:

1. **Cognitive** — Revised Bloom Taxonomy
2. **Psychomotor** — Dave's Psychomotor Taxonomy
3. **Behavioral/Affective** — observable learning behavior indicators

T03 tidak mengimplementasikan Adaptive Intervention atau Learning Recommendation.

---

## 1. Objective

Mengubah validated learning evidence dari M4-T02 menjadi representasi Learning State yang dapat dijelaskan dan ditelusuri.

Core pipeline:

```text
Activity / Task Context
        +
Validated Evidence (M4-T02)
        ↓
Evidence Fusion
        ↓
Cognitive Analysis
+
Psychomotor Analysis
+
Observable Behavioral/Affective Analysis
        ↓
Learning State
        ↓
State Confidence
        ↓
Explanation + Provenance
```

## 2. Research Context

T03 mendukung tujuan NEXUS untuk mengidentifikasi kondisi belajar learner berdasarkan evidence pembelajaran yang multidimensi dan kontekstual.

Batas T03:

```text
Validated Evidence
        ↓
Learning State
```

T03 harus mempertahankan pemisahan antara observable learning evidence dan psychological diagnosis.

## 3. Relationship to M4-T02

M4-T02 menghasilkan `ValidatedEvidence`.

T03 menggunakan:

- evidence category
- evidence type
- observed value
- context
- evidence quality
- evidence confidence
- provenance
- validation reason

T03 tidak boleh melewati M4-T02 dan langsung menafsirkan raw `LearningEvent` jika validated evidence tersedia.

```text
LearningEvent
      ↓
M4-T02
ValidatedEvidence
      ↓
M4-T03
LearningState
```

## 4. Core Principle

### Taxonomy Demand ≠ Learner Capability

Bloom dan Dave pada activity menggambarkan **expected learning demand**, bukan otomatis kemampuan learner.

```text
Expected Task Demand
        +
Validated Observable Evidence
        ↓
Cognitive / Psychomotor Analysis
        ↓
Learning State
```

## 5. Revised Bloom Taxonomy

T03 mendukung enam level:

1. `remember`
2. `understand`
3. `apply`
4. `analyze`
5. `evaluate`
6. `create`

Nilai ini merepresentasikan **task cognitive demand**.

T03 tidak boleh menyatakan learner telah mencapai level Bloom tertentu hanya karena activity menggunakan level tersebut.

## 6. Dave's Psychomotor Taxonomy

T03 mendukung lima level:

1. `imitation`
2. `manipulation`
3. `precision`
4. `articulation`
5. `naturalization`

Nilai ini merepresentasikan **task psychomotor/skill demand**.

Dave demand pada activity tidak otomatis menjadi demonstrated learner skill.

## 7. Observable Behavioral / Affective Dimension

Dimensi affective NEXUS menggunakan observable learning behavior.

Contoh:

- persistence setelah failure
- correction setelah failure
- repeated attempts
- completion behavior
- continued engagement
- observable disengagement jika didukung evidence
- help-seeking jika event tersedia

T03 tidak boleh melakukan diagnosis psikologis/klinis.

Gunakan istilah observable seperti:

- `persistent_attempt_behavior`
- `corrective_behavior`
- `persistent_engagement`
- `reduced_activity_engagement`

hanya jika didukung evidence.

## 8. Evidence Fusion

T03 harus menggabungkan validated evidence yang relevan, bukan hanya event terakhir.

Fusion dapat mempertimbangkan:

- evidence category
- evidence type
- observed value
- evidence quality
- evidence confidence
- task repetition
- task difficulty
- execution anomaly
- Bloom task demand
- Dave task demand
- hubungan temporal
- observable behavioral patterns

Satu evidence yang lemah tidak boleh otomatis menentukan Learning State.

## 9. Evidence Quality

M4-T02 menyediakan:

- `valid`
- `uncertain`
- `context_dependent`

T03 harus mempertahankan perbedaan ini dan tidak diam-diam mengubah `uncertain` menjadi `valid`.

## 10. Evidence Confidence

M4-T02 menyediakan:

- `high`
- `medium`
- `low`

Ini adalah confidence terhadap validitas/kegunaan evidence.

T03 memiliki **state confidence** yang berbeda:

```text
Evidence Confidence
        ↓
Evidence Fusion
        ↓
State Confidence
```

## 11. Learning State V1

Prototype V1 menggunakan empat state:

| State | Arti |
|---|---|
| `progressing` | Evidence menunjukkan perkembangan atau perbaikan |
| `stable` | Performa/perilaku relatif stabil tanpa perubahan penting yang kuat |
| `needs_support` | Terdapat pola kesulitan yang cukup kuat sehingga learner mungkin membutuhkan dukungan |
| `insufficient_evidence` | Evidence belum cukup untuk membuat inference yang dapat dipertanggungjawabkan |

`needs_support` adalah Learning State, bukan intervention. Intervention menjadi tanggung jawab M4-T04.

## 12. Cognitive Analysis

Cognitive analysis mengevaluasi evidence relatif terhadap Revised Bloom demand pada activity.

Contoh evidence:

- learner mengidentifikasi penyebab failure
- learner membandingkan pendekatan
- learner menerapkan metode
- learner memperbaiki solusi
- learner menghasilkan solusi

T03 tidak boleh mengklaim learner berada pada level Bloom tertentu kecuali aturan evidence memang mendukung klaim tersebut.

## 13. Psychomotor Analysis

Psychomotor analysis mengevaluasi observable performance relatif terhadap Dave demand.

Contoh:

```text
Task:
Dave = precision

Evidence:
- implementation errors
- correction attempts
- improved execution
- successful submission
```

Dave task demand tidak otomatis menjadi learner skill level.

## 14. Behavioral / Affective Analysis

Contoh:

```text
failure
+
continued attempt
+
correction
+
successful completion
```

dapat menghasilkan:

```text
persistent_corrective_behavior
```

Tidak boleh otomatis diterjemahkan menjadi kondisi internal seperti motivated, frustrated, atau resilient.

## 15. Learning State Inference

Inference harus:

- deterministic
- explainable
- traceable
- context-aware
- evidence-based

```text
Validated Evidence
       │
       ├── Cognitive indicators
       ├── Psychomotor indicators
       └── Behavioral indicators
                │
                ▼
          Evidence Fusion
                │
                ▼
          Learning State
```

Setiap state harus memiliki rule/reason yang menjelaskan mengapa state tersebut dihasilkan.

## 16. Insufficient Evidence

Jika evidence tidak cukup:

```text
learning_state = insufficient_evidence
```

Jangan menebak.

Contoh:

- hanya satu evidence lemah
- evidence confidence rendah tanpa evidence pendukung
- context tidak lengkap
- evidence bertentangan dan belum cukup untuk menentukan state

Threshold/rule deterministic harus dijelaskan dalam implementasi.

## 17. State Confidence

T03 menggunakan:

- `high`
- `medium`
- `low`

State confidence menunjukkan kekuatan dukungan terhadap Learning State.

Pertimbangkan:

- jumlah evidence relevan
- evidence quality
- evidence confidence
- kelengkapan context
- konsistensi evidence
- kekuatan rule inference

Jangan sekadar menyalin confidence evidence terakhir.

## 18. Explanation

Setiap Learning State harus memiliki explanation.

Contoh:

```text
Learning State:
progressing

Cognitive demand:
apply

Psychomotor demand:
manipulation

Observed evidence:
- initial rejection
- corrective attempt
- subsequent accepted submission

Reason:
"Evidence indicates successful correction following an initial
performance failure within the expected task demand."
```

Explanation menjelaskan evidence dan inference, bukan diagnosis.

## 19. Provenance

Setiap Learning State harus dapat ditelusuri ke validated evidence yang digunakan.

```text
LearningState
     ↓
Inference
     ↓
ValidatedEvidence
     ↓
LearningEvent
```

Researcher harus dapat mengetahui learner, activity, timestamp, Bloom demand, Dave demand, behavioral indicators, evidence yang digunakan, quality, confidence, inference rule, state confidence, dan explanation.

## 20. Persistence

Ikuti arsitektur Laravel yang sudah ada.

Expected:

- model Learning State
- persistence hasil inference
- referensi ke validated evidence
- dukungan multiple evidence provenance

Jangan menduplikasi seluruh raw evidence payload.

## 21. Idempotency

Inference yang sama terhadap evidence set yang sama tidak boleh menghasilkan duplicate Learning State yang tidak terkendali.

Gunakan deterministic inference identity atau mekanisme setara.

Basis yang mungkin:

```text
learner
+
activity/context
+
evidence set/version
```

## 22. Out of Scope

T03 tidak boleh mengimplementasikan:

- Adaptive Intervention
- Learning Recommendation
- hints
- personalized content delivery
- intervention delivery
- intervention triggers
- real-time intervention
- Research State Manager
- complex trajectory engine
- ML training
- psychological diagnosis
- clinical diagnosis
- affective diagnosis
- statistical hypothesis testing

**Adaptive Intervention = M4-T04.**

**Learning Recommendation = M4-T05.**

## 23. M3 Compatibility

Jangan mengubah semantics:

- `code_run`
- `code_submit`
- `submission_accepted`
- `submission_rejected`

T03 menggunakan evidence yang telah diproses oleh M4-T02.

M3 tests harus tetap PASS.

## 24. Acceptance Criteria

- **T03-AC01:** Validated Evidence dapat digunakan sebagai input Learning State inference.
- **T03-AC02:** Inference menggunakan evidence fusion, bukan hanya latest event.
- **T03-AC03:** Evidence quality memengaruhi inference.
- **T03-AC04:** Evidence confidence memengaruhi inference.
- **T03-AC05:** Task context memengaruhi inference.
- **T03-AC06:** Revised Bloom task demand tersedia.
- **T03-AC07:** Dave psychomotor task demand tersedia.
- **T03-AC08:** Cognitive indicator berbeda dari task cognitive demand.
- **T03-AC09:** Psychomotor indicator berbeda dari task psychomotor demand.
- **T03-AC10:** Observable behavioral indicators tersedia.
- **T03-AC11:** Behavioral indicators tidak menjadi psychological diagnosis.
- **T03-AC12:** Learning State inference deterministic dan explainable.
- **T03-AC13:** State confidence terpisah dari evidence confidence.
- **T03-AC14:** Evidence tidak cukup menghasilkan `insufficient_evidence`.
- **T03-AC15:** Setiap state memiliki explanation.
- **T03-AC16:** Setiap state memiliki provenance ke validated evidence.
- **T03-AC17:** Repeated inference tidak menghasilkan duplicate state yang tidak terkendali.
- **T03-AC18:** Tidak ada Adaptive Intervention.
- **T03-AC19:** Tidak ada Learning Recommendation.
- **T03-AC20:** Semua behavior baru memiliki automated tests.
- **T03-AC21:** M3 regression tetap PASS.
- **T03-AC22:** M4-T02 regression tetap PASS.

## 25. Test Requirements

Minimal test:

1. enam level Revised Bloom
2. lima level Dave
3. task demand tidak otomatis menjadi learner capability
4. validated evidence sebagai input
5. evidence fusion
6. valid evidence
7. uncertain evidence
8. context-dependent evidence
9. high evidence confidence
10. medium evidence confidence
11. low evidence confidence
12. cognitive indicator
13. psychomotor indicator
14. behavioral indicator
15. tidak ada psychological diagnosis
16. insufficient evidence
17. progressing
18. stable
19. needs_support
20. state confidence
21. explanation
22. provenance
23. idempotent inference
24. M3 compatibility
25. M4-T02 compatibility
26. tidak ada intervention
27. tidak ada recommendation

Test harus memvalidasi behavior dan persisted data, bukan hanya keberadaan class.

## 26. Implementation Rules

1. Inspect M4-T01 dan M4-T02 sebelum mengubah architecture.
2. Reuse activity/task-context structure yang sudah ada.
3. Reuse `ValidatedEvidence`.
4. Jangan bypass T02.
5. Inference harus deterministic.
6. Inference harus explainable.
7. Bloom demand harus terpisah dari demonstrated learner capability.
8. Dave demand harus terpisah dari demonstrated learner skill.
9. Gunakan observable behavioral indicators untuk dimensi affective/behavioral.
10. Jangan melakukan psychological diagnosis.
11. Jangan membuat intervention engine.
12. Jangan membuat recommendation logic.
13. Jangan membuat complex trajectory engine.
14. Pertahankan M3 semantics.
15. Pertahankan M4-T02 semantics.
16. Tambahkan automated tests untuk behavior baru.
17. Jangan mengubah functionality yang tidak terkait.
18. **STOP setelah T03. Jangan mulai T04/M5.**

## 27. Research Traceability

| Komponen T03 | Relevansi penelitian |
|---|---|
| Revised Bloom | Cognitive learning/task demand |
| Dave | Psychomotor/skill learning/task demand |
| Behavioral indicators | Observable affective/learning behavior |
| Evidence fusion | Multidimensional learning analytics |
| Context-aware inference | Mengurangi unsupported state inference |
| State confidence | Kekuatan inference |
| Explanation | Interpretability |
| Provenance | Research reproducibility |
| Insufficient evidence | Mencegah unsupported inference |
| Learning State | Fondasi Adaptive Intervention |

Potential contribution:

```text
Task Demand
   +
Multidimensional Validated Evidence
   +
Context
   ↓
Cognitive–Psychomotor–Behavioral Analysis
   ↓
Learning State
   ↓
Adaptive Intervention
```

## 28. Definition of Done

- [ ] Revised Bloom model implemented
- [ ] Dave psychomotor model implemented
- [ ] Activity/task demand supported
- [ ] Cognitive analysis implemented
- [ ] Psychomotor analysis implemented
- [ ] Behavioral/affective observable indicators implemented
- [ ] Evidence fusion implemented
- [ ] Four Learning States implemented
- [ ] State confidence implemented
- [ ] Explanation implemented
- [ ] Provenance implemented
- [ ] Insufficient evidence implemented
- [ ] Idempotent inference implemented
- [ ] No psychological diagnosis
- [ ] No Adaptive Intervention
- [ ] No Recommendation
- [ ] Automated tests added
- [ ] M3 tests green
- [ ] M4-T02 tests green
- [ ] Full regression green
- [ ] Implementation summary documented
- [ ] Test results documented

## 29. Required Final Report from Cursor/Hermes

After implementation report:

1. Files changed
2. Migrations added
3. Models/controllers/services changed
4. Bloom implementation
5. Dave implementation
6. Cognitive analysis rules
7. Psychomotor analysis rules
8. Behavioral/affective indicator rules
9. Evidence fusion rules
10. Learning State rules
11. State confidence rules
12. Explanation/provenance implementation
13. Tests added/changed
14. T03 acceptance criteria PASS/FAIL
15. M3 test result
16. M4-T02 test result
17. Full regression result
18. Architectural concerns
19. Research traceability
20. **STOP after M4-T03; do not begin M4-T04 or M5**
