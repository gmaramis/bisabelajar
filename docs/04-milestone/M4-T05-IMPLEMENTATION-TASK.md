# M4-T05 — Next Learning Action Decision

## Status

**V1 — Implementation Specification**

M4-T05 adalah tahap setelah M4-T04.

M4-T03 menentukan kondisi learner.

M4-T04 memberikan bantuan/intervention.

M4-T05 menentukan **apa yang sebaiknya dilakukan learner berikutnya**.

Prinsip sederhana:

> T03 memahami kondisi learner.  
> T04 membantu learner.  
> T05 menentukan langkah belajar berikutnya.

M4-T05 menggunakan **Deterministic Multi-Factor Rule-Based Decision**.

Tidak menggunakan ML atau LLM sebagai pengambil keputusan pada V1.

M4-T05 tidak mengimplementasikan longitudinal learning analytics.
Longitudinal analysis menjadi bagian M5.

---

# 1. Tujuan

Tujuan T05 adalah menentukan **Next Learning Action** berdasarkan kondisi learner dan apa yang sudah terjadi sebelumnya.

Contoh:

```text
Learning Evidence
        ↓
Learning State (T03)
        ↓
Intervention (T04)
        ↓
Learner Response / New Evidence
        ↓
T05 Decision Rules
        ↓
Next Learning Action
```

T05 menjawab pertanyaan:

> "Setelah apa yang baru saja terjadi, learner sebaiknya melakukan apa?"

---

# 2. Scope T05

T05 hanya bertanggung jawab atas:

- menentukan next learning action;
- menggunakan Learning State sebagai input;
- menggunakan evidence yang tersedia;
- mempertimbangkan intervention T04;
- mempertimbangkan respons learner;
- mempertimbangkan activity context;
- menghasilkan keputusan yang explainable;
- menyimpan alasan/provenance keputusan.

T05 tidak bertanggung jawab atas:

- Learning State inference;
- Adaptive Intervention;
- Learning Recommendation jangka panjang;
- longitudinal analytics;
- intervention effectiveness analysis;
- AI-generated reassessment question engine penuh;
- ML recommendation model;
- LLM sebagai decision-maker.

---

# 3. Input

T05 menggunakan beberapa faktor.

## 3.1 Learning State

Dari M4-T03:

```text
progressing
needs_support
stable
insufficient_evidence
```

---

## 3.2 Learning Evidence

Evidence berasal dari validated evidence.

T05 dapat mempertimbangkan:

- outcome;
- cognitive indicators;
- psychomotor indicators;
- observable behavioral indicators;
- evidence quality;
- evidence confidence;
- evidence history yang relevan untuk keputusan saat ini.

T05 tidak boleh membuat keputusan kuat dari evidence yang tidak valid.

---

## 3.3 Intervention T04

T05 dapat melihat intervention yang baru diberikan.

Contoh:

```text
hint
socratic_question
concept_explanation
worked_example
corrective_feedback
guided_retry
reinforcement
```

T05 tidak membuat ulang intervention.

T05 hanya menggunakan informasi tersebut sebagai salah satu faktor keputusan.

---

## 3.4 Learner Response

Jika learner sudah melakukan retry atau aktivitas berikutnya, T05 dapat menggunakan evidence baru untuk menentukan tindakan berikutnya.

Contoh:

```text
Intervention
    ↓
Retry
    ↓
Success
```

atau:

```text
Intervention
    ↓
Retry
    ↓
Failure
```

T05 harus membedakan kedua kondisi tersebut.

---

## 3.5 Activity Context

T05 dapat mempertimbangkan konteks aktivitas:

- activity;
- task;
- target capability;
- Bloom demand;
- Dave demand;
- lesson/module context;
- prerequisite/context yang tersedia.

Bloom dan Dave tetap merupakan **task demand**, bukan klaim capability learner.

---

# 4. Decision Algorithm

## 4.1 Algoritma V1

Gunakan:

> **Deterministic Multi-Factor Rule-Based Decision Algorithm**

Tidak menggunakan machine learning.

Tidak menggunakan LLM sebagai pengambil keputusan.

Input:

```text
Learning State
+
Validated Evidence
+
Previous Intervention
+
Learner Response
+
Activity Context
```

Output:

```text
Next Learning Action
+
Reason
+
Decision Rule
+
Provenance
```

---

# 5. Decision Priority

Untuk menghindari keputusan yang tidak konsisten, gunakan urutan prioritas.

```text
1. Apakah evidence cukup?
        ↓
2. Apakah learner masih needs_support?
        ↓
3. Apakah intervention sebelumnya sudah diikuti response/retry?
        ↓
4. Apakah learner menunjukkan perbaikan?
        ↓
5. Apakah weak area masih terlihat?
        ↓
6. Tentukan next learning action.
```

Rule yang lebih spesifik harus memiliki prioritas lebih tinggi daripada rule default.

---

# 6. Next Learning Action Types V1

Gunakan action yang sederhana dan jelas.

Minimal:

```text
continue
review_concept
practice_again
guided_retry
reassessment
collect_more_evidence
```

Jika codebase membutuhkan nama yang berbeda, gunakan naming convention yang konsisten.

---

# 7. Rule Utama

## 7.1 Insufficient Evidence

Jika:

```text
Learning State = insufficient_evidence
```

maka:

```text
Next Action = collect_more_evidence
```

T05 tidak boleh membuat keputusan remedial kuat berdasarkan evidence yang belum cukup.

---

## 7.2 Progressing

Jika:

```text
Learning State = progressing
+
hasil terbaru menunjukkan perbaikan
```

maka:

```text
Next Action = continue
```

Learner dapat melanjutkan aktivitas atau materi berikutnya jika konteks mendukung.

---

## 7.3 Stable

Jika:

```text
Learning State = stable
+
tidak ada failure pattern baru
```

maka:

```text
Next Action = continue
```

Jangan otomatis melakukan remedial.

---

## 7.4 Needs Support + Intervention + Retry Berhasil

Jika:

```text
needs_support
+
intervention diberikan
+
learner retry
+
retry berhasil
```

maka:

```text
Next Action = continue
```

Ini menunjukkan bahwa bantuan sebelumnya diikuti oleh outcome yang berhasil.

---

## 7.5 Needs Support + Retry Masih Gagal

Jika:

```text
needs_support
+
intervention diberikan
+
retry masih gagal
```

maka:

```text
Next Action = practice_again
```

atau:

```text
review_concept
```

Pemilihan bergantung pada jenis evidence/weak area.

---

## 7.6 Cognitive Weakness

Jika:

```text
needs_support
+
cognitive unresolved
+
retry masih gagal
```

maka:

```text
Next Action = review_concept
```

Tujuannya memperkuat pemahaman sebelum learner kembali melanjutkan.

---

## 7.7 Psychomotor Weakness

Jika:

```text
needs_support
+
psychomotor unresolved
+
retry masih gagal
```

maka:

```text
Next Action = practice_again
```

atau:

```text
guided_retry
```

Tindakan harus mengarah pada pengulangan praktik/prosedur.

---

## 7.8 Weak Area Masih Terlihat

Jika evidence menunjukkan area kemampuan tertentu masih belum berhasil dikuasai:

```text
Next Action = reassessment
```

Namun reassessment hanya berarti:

> learner perlu diuji kembali pada kemampuan yang sama.

Pembuatan soal baru oleh AI bukan bagian utama T05 V1.

---

# 8. Reassessment Boundary

T05 boleh memutuskan bahwa learner membutuhkan reassessment.

Tetapi T05 **tidak membuat soal reassessment**.

Alur:

```text
T05
    ↓
REASSESSMENT
    ↓
Reassessment mechanism
    ↓
New task/question
    ↓
New evidence
```

Untuk pengembangan berikutnya:

> AI dapat membuat soal baru yang menguji kemampuan yang sama tetapi menggunakan bentuk/pertanyaan berbeda.

Prinsip ini harus dipertahankan:

> Soal boleh berbeda, tetapi kemampuan yang ingin diuji harus tetap sama.

T05 V1 hanya membuat keputusan bahwa reassessment diperlukan.

---

# 9. Multi-Factor Decision

T05 tidak boleh hanya menggunakan satu field.

Contoh yang benar:

```text
needs_support
+
cognitive unresolved
+
socratic intervention
+
retry failure
        ↓
review_concept
```

Contoh lain:

```text
needs_support
+
guided_retry
+
retry success
        ↓
continue
```

Contoh:

```text
insufficient_evidence
+
low evidence quality
        ↓
collect_more_evidence
```

Dengan demikian keputusan T05 tetap sederhana tetapi tidak terlalu kaku.

---

# 10. Decision Explanation

Setiap keputusan harus dapat dijelaskan.

Minimal:

```text
action
reason
decision_rule
source_learning_state
source_evidence
source_intervention
```

Contoh:

```text
Action:
review_concept

Reason:
Learner remains in needs_support state and the
latest retry was unsuccessful after cognitive support.

Decision Rule:
needs_support + cognitive unresolved + retry failure
→ review_concept
```

Sistem harus dapat menjawab:

> "Mengapa learner diarahkan melakukan tindakan ini?"

---

# 11. Provenance

Decision harus dapat ditelusuri.

Minimal:

```text
NextLearningAction
        ↓
LearningState
        ↓
ValidatedEvidence
        ↓
LearningEvent
```

Jika intervention T04 menjadi faktor keputusan:

```text
NextLearningAction
        ↓
AdaptiveIntervention
        ↓
LearningState
        ↓
ValidatedEvidence
        ↓
LearningEvent
```

Provenance penting untuk kebutuhan penelitian.

---

# 12. Idempotency

Keputusan yang sama dari input yang sama tidak boleh membuat banyak record identik.

Gunakan deterministic decision key, mengikuti pola idempotency M4-T03 dan T04.

Contoh konsep:

```text
decision_key =
hash(
    learning_state_id
    +
relevant evidence ids
    +
intervention id
)
```

Detail implementasi harus mengikuti struktur codebase yang sudah ada.

---

# 13. AI Usage

M4-T05 V1 **tidak menggunakan AI/LLM sebagai decision-maker**.

Keputusan dibuat oleh deterministic rules.

Alasannya:

- mudah dijelaskan;
- mudah diuji;
- reproducible;
- menjadi baseline penelitian;
- memudahkan pengukuran efektivitas.

AI/ML dapat menjadi pengembangan penelitian berikutnya setelah tersedia data yang cukup.

---

# 14. Future AI-Assisted Recommendation

Jangan implementasikan pada T05 V1.

Roadmap penelitian dapat berkembang:

```text
M4 V1
Rule-Based Decision
        ↓
M5
Longitudinal Data
        ↓
Research Dataset
        ↓
Future Study
AI/ML-Assisted Recommendation
```

Dengan demikian rule-based system menjadi baseline yang dapat dibandingkan dengan pendekatan AI di penelitian berikutnya.

---

# 15. Relationship with T04

T04 dan T05 harus tetap terpisah.

### T04

Menjawab:

> "Bantuan apa yang diberikan sekarang?"

Contoh:

```text
hint
socratic_question
guided_retry
corrective_feedback
```

### T05

Menjawab:

> "Setelah itu, learner sebaiknya melakukan apa?"

Contoh:

```text
continue
review_concept
practice_again
guided_retry
reassessment
collect_more_evidence
```

Jangan membuat T05 mengulang fungsi T04.

---

# 16. Relationship with M5

M4-T05 hanya membuat keputusan next action.

M5 nantinya menganalisis:

- perubahan Learning State;
- learning trajectory;
- weak-area persistence;
- intervention response;
- improvement;
- contextual variation;
- apakah reassessment menghasilkan perbaikan;
- pola learner dalam jangka waktu lebih panjang.

Alur penelitian:

```text
M4
Learning State
    ↓
Intervention
    ↓
Next Learning Action
    ↓
M5
Longitudinal Analysis
```

---

# 17. Data Model

Jika diperlukan, buat model untuk menyimpan keputusan.

Contoh nama:

```text
NextLearningAction
```

Minimal data:

```text
id
learner/user reference
learning_state_id
action
reason
decision_rule
evidence references
intervention reference
activity reference
decision_key
created_at
updated_at
```

Gunakan naming convention codebase.

Jangan membuat tabel baru jika struktur existing dapat digunakan dengan aman.

---

# 18. Service

Jika belum tersedia, buat service khusus.

Contoh:

```text
NextLearningActionService
```

Tanggung jawab:

1. menerima Learning State;
2. menerima evidence/context yang relevan;
3. membaca intervention T04;
4. mengevaluasi rules;
5. memilih next action;
6. membuat explanation;
7. menyimpan provenance;
8. menjaga idempotency.

Service tidak bertanggung jawab atas:

- Learning State inference;
- Adaptive Intervention;
- AI-generated question;
- longitudinal analytics.

---

# 19. Testing

Buat test khusus M4-T05.

Minimal:

### State tests

- `insufficient_evidence` → `collect_more_evidence`
- `progressing` → `continue`
- `stable` → `continue`
- `needs_support` → decision sesuai evidence

### Intervention response

- intervention + successful retry → `continue`
- intervention + failed retry → `practice_again` / `review_concept`

### Weak area

- cognitive weakness → `review_concept`
- psychomotor weakness → `practice_again` / `guided_retry`
- persistent weak area → `reassessment`

### Explanation

- action memiliki reason
- action memiliki decision rule

### Provenance

- decision dapat ditelusuri ke Learning State
- Learning State dapat ditelusuri ke ValidatedEvidence
- jika relevan, decision dapat ditelusuri ke intervention T04

### Idempotency

- input yang sama tidak menghasilkan duplicate decision

### Scope protection

Pastikan T05 tidak:

- membuat intervention T04
- mengubah T03 inference
- membuat AI-generated question
- melakukan longitudinal analytics
- membuat M5 functionality

---

# 20. Regression Test

Setelah implementasi:

Jalankan minimal:

```text
AdaptiveInterventionTest
LearningStateInferenceTest
EvidenceValidationTest
M3ProgrammingEnvironmentTest
```

Kemudian full regression.

Expected:

```text
M4-T02 PASS
M4-T03 PASS
M4-T04 PASS
M4-T05 PASS
M3 PASS
Full regression PASS
```

Jika failure berasal dari area di luar T05:

> Jangan memperbaiki unrelated issue secara otomatis.

Laporkan terlebih dahulu.

---

# 21. Acceptance Criteria

M4-T05 dianggap selesai jika:

- [ ] Learning State T03 menjadi input T05
- [ ] Validated Evidence menjadi input T05
- [ ] intervention T04 dapat menjadi input T05
- [ ] learner response/retry dapat dipertimbangkan
- [ ] activity context dapat dipertimbangkan
- [ ] T05 menggunakan deterministic multi-factor rule-based decision
- [ ] `insufficient_evidence` dapat menghasilkan `collect_more_evidence`
- [ ] `progressing` dapat menghasilkan `continue`
- [ ] `stable` dapat menghasilkan `continue`
- [ ] successful retry dapat menghasilkan `continue`
- [ ] failed retry dapat menghasilkan `practice_again` atau `review_concept`
- [ ] cognitive weakness dapat menghasilkan `review_concept`
- [ ] psychomotor weakness dapat menghasilkan `practice_again` / `guided_retry`
- [ ] persistent weak area dapat menghasilkan `reassessment`
- [ ] T05 tidak membuat soal reassessment
- [ ] setiap decision memiliki reason
- [ ] setiap decision memiliki decision rule
- [ ] decision memiliki provenance
- [ ] decision idempotent
- [ ] tidak menggunakan ML sebagai decision-maker
- [ ] tidak menggunakan LLM sebagai decision-maker
- [ ] T02 tidak rusak
- [ ] T03 tidak rusak
- [ ] T04 tidak rusak
- [ ] tidak ada longitudinal analytics
- [ ] test T05 PASS
- [ ] regression PASS

---

# 22. Research Traceability

T05 memperkuat closed-loop NEXUS:

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
Learner continues / reviews / practices / reassesses
        ↓
New Learning Evidence
```

T05 menyediakan baseline deterministic yang dapat digunakan untuk penelitian berikutnya.

Pertanyaan penelitian masa depan dapat membandingkan:

```text
Rule-Based Recommendation
        VS
AI/ML-Assisted Recommendation
```

setelah data longitudinal tersedia.

---

# 23. Implementation Rules

Saat mengerjakan task ini:

1. Baca M4-T03 terlebih dahulu.
2. Baca hasil/implementasi M4-T04.
3. Pahami LearningState.
4. Pahami AdaptiveIntervention.
5. Pahami provenance yang sudah ada.
6. Gunakan perubahan seminimal mungkin.
7. Jangan mengubah T02.
8. Jangan mengubah T03.
9. Jangan mengubah T04 kecuali benar-benar diperlukan untuk kompatibilitas.
10. Jangan membuat AI/LLM decision engine.
11. Jangan membuat ML model.
12. Jangan membuat M5.
13. Jangan membuat reassessment question generator.
14. Jangan commit.
15. Jangan push.

Jika terdapat kebutuhan perubahan T02/T03/T04 yang tidak dapat dihindari:

> STOP dan laporkan alasannya sebelum melakukan perubahan.

---

# 24. Execution Sequence

```text
1. Inspect M4-T03
        ↓
2. Inspect M4-T04
        ↓
3. Plan T05 rules
        ↓
4. Implement action model/storage if needed
        ↓
5. Implement NextLearningActionService
        ↓
6. Implement deterministic decision rules
        ↓
7. Implement explanation + provenance
        ↓
8. Implement idempotency
        ↓
9. Add tests
        ↓
10. Run T02 regression
        ↓
11. Run T03 regression
        ↓
12. Run T04 regression
        ↓
13. Run T05 tests
        ↓
14. Run full regression
        ↓
15. Report result
        ↓
16. STOP
```

---

# 25. Final Report Format

Setelah selesai, laporkan:

## 1. Files created

Daftar file baru.

## 2. Files modified

Daftar file yang diubah.

## 3. Implementation summary

Jelaskan implementasi T05 secara singkat.

## 4. Decision rules

Tampilkan rules yang benar-benar diimplementasikan.

## 5. Next Learning Actions

Tampilkan action yang tersedia.

## 6. Explanation

Jelaskan bagaimana alasan keputusan dibuat.

## 7. Provenance

Jelaskan bagaimana decision ditelusuri ke evidence/state/intervention.

## 8. Idempotency

Jelaskan mekanismenya.

## 9. Tests

Laporkan test T05.

## 10. Regression

Laporkan:

- M4-T02
- M4-T03
- M4-T04
- M3
- full regression

## 11. Concerns

Laporkan keterbatasan.

## 12. Scope check

Pastikan:

```text
M4-T05 ONLY
No M5
No ML
No LLM decision-maker
No AI question generator
No commit
No push
```

---

# 26. STOP

Setelah M4-T05 selesai dan seluruh test/report tersedia:

**STOP.**

Jangan mulai M4-T06.

Jangan mulai M5.

Jangan commit.

Jangan push.

Tunggu review manusia terlebih dahulu.
