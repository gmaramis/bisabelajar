# M4-T04 — Adaptive Intervention + Socratic Response

## Status

**V1 — Implementation Specification**

M4-T04 adalah tahap setelah M4-T03.

M4-T03 menentukan kondisi belajar learner berdasarkan validated evidence.

M4-T04 menggunakan Learning State tersebut untuk menentukan dan memberikan respons belajar yang sesuai.

Prinsip sederhana:

> T03 menemukan kondisi learner.
> T04 memberikan bantuan yang sesuai dengan kondisi tersebut.

M4-T04 tidak mengimplementasikan Learning Recommendation secara penuh.
Learning Recommendation menjadi tanggung jawab M4-T05.

M4-T04 juga tidak mengimplementasikan longitudinal learning analytics.
Longitudinal analysis menjadi bagian M5.

---

# 1. Tujuan

M4-T04 bertujuan membuat sistem mampu memberikan intervensi pembelajaran yang sesuai berdasarkan Learning State learner.

Contoh:

```text
Learner mengerjakan aktivitas
        ↓
Validated Evidence
        ↓
Learning State
        ↓
needs_support
        ↓
Adaptive Intervention
        ↓
Bantuan yang sesuai
        ↓
Learner mencoba kembali
```

Intervensi harus membantu learner belajar kembali.

Intervensi bukan sekadar memberikan jawaban.

---

# 2. Input

T04 menggunakan output dari M4-T03.

Input utama:

- Learning State
- state value
- state confidence
- explanation
- evidence/provenance
- cognitive state
- psychomotor state
- observable behavioral state
- activity/task context

T04 tidak boleh menggunakan evidence mentah jika sudah tersedia validated evidence.

Gunakan hasil M4-T02 dan M4-T03 sebagai sumber resmi.

---

# 3. Learning State yang Digunakan

M4-T03 menghasilkan empat state utama:

```text
progressing
needs_support
stable
insufficient_evidence
```

T04 harus memberikan respons yang berbeda sesuai state.

## 3.1 progressing

Makna:

Learner menunjukkan perkembangan.

Respons V1:

- berikan penguatan singkat;
- lanjutkan pembelajaran;
- berikan tantangan ringan jika sesuai konteks.

Jangan memberikan intervensi remedial secara otomatis.

## 3.2 needs_support

Makna:

Evidence menunjukkan learner membutuhkan bantuan.

Ini adalah kondisi utama untuk adaptive intervention.

Respons dapat berupa:

- Socratic question;
- hint;
- conceptual explanation;
- worked example sederhana;
- corrective feedback;
- guided retry;
- practice suggestion.

Prioritaskan bantuan yang membuat learner mencoba kembali.

## 3.3 stable

Makna:

Evidence menunjukkan kondisi learner relatif stabil.

Respons:

- reinforcement;
- feedback singkat;
- lanjutkan aktivitas jika sesuai.

Jangan memberikan remedial intervention tanpa evidence yang mendukung.

## 3.4 insufficient_evidence

Makna:

Evidence belum cukup untuk menentukan kondisi learner.

Respons:

- jangan membuat klaim kuat;
- jangan membuat intervensi remedial berdasarkan asumsi;
- minta atau lakukan aktivitas/evidence tambahan jika mekanisme yang tersedia mendukung.

Jika belum ada mekanisme untuk mendapatkan evidence tambahan, cukup menghasilkan response yang menyatakan bahwa evidence belum cukup.

---

# 4. Prinsip Adaptive Intervention

V1 harus sederhana dan deterministic.

Jangan membuat model machine learning baru.

Jangan membuat sistem yang melakukan diagnosis psikologis.

Jangan membuat intervensi berdasarkan asumsi tentang emosi atau kondisi mental learner.

Intervensi harus berdasarkan:

```text
Learning State
+
Learning Evidence
+
Activity Context
```

---

# 5. Socratic Response

T04 harus mendukung bentuk respons Socratic sederhana.

Tujuannya:

> Membantu learner menemukan atau memperbaiki jawabannya sendiri.

Contoh:

Jika learner salah memahami kondisi `if`:

Jangan langsung:

```text
Jawaban yang benar adalah ...
```

Lebih baik:

```text
Coba perhatikan kondisi pada if.
Apa yang terjadi jika nilai variabel tersebut bernilai false?
```

Socratic response V1 dapat menggunakan beberapa tipe:

```text
clarifying_question
concept_check
guided_question
reflection_question
next_step_hint
```

Tidak perlu membuat engine Socratic yang kompleks.

---

# 6. Intervention Types V1

Gunakan jenis intervensi sederhana berikut:

```text
hint
socratic_question
concept_explanation
worked_example
corrective_feedback
guided_retry
reinforcement
```

Setiap intervention harus memiliki:

- type
- message/content
- target state
- reason
- source learning state
- provenance/reference
- created timestamp

---

# 7. Intervention Selection

V1 menggunakan rule-based selection.

Contoh aturan:

```text
needs_support
    +
cognitive weakness
    ↓
concept_explanation / socratic_question / hint
```

```text
needs_support
    +
repeated incorrect outcome
    ↓
corrective_feedback + guided_retry
```

```text
progressing
    ↓
reinforcement
```

```text
stable
    ↓
reinforcement
```

```text
insufficient_evidence
    ↓
no strong intervention
```

Rules harus tetap sederhana.

Jangan membuat scoring engine kompleks.

---

# 8. Cognitive Intervention

Jika evidence menunjukkan masalah pada proses berpikir atau pemahaman konsep, intervention dapat diarahkan pada cognitive support.

Contoh:

```text
concept_explanation
socratic_question
hint
worked_example
```

Intervention tidak boleh menyatakan learner berada pada level Bloom tertentu hanya karena activity memiliki Bloom demand tertentu.

Bloom demand tetap merupakan:

> kemampuan yang sedang ditargetkan oleh task.

Learning State tetap ditentukan berdasarkan evidence.

---

# 9. Psychomotor Intervention

Jika aktivitas membutuhkan kemampuan praktik atau prosedural, intervention dapat berupa:

```text
guided_retry
worked_example
corrective_feedback
step_hint
```

Intervention harus membantu learner memperbaiki proses pengerjaan.

Dave level merupakan task demand, bukan otomatis capability learner.

---

# 10. Behavioral / Affective Intervention

Behavioral/affective information hanya menggunakan observable learning behavior.

Contoh:

```text
persistent_attempt_behavior
corrective_behavior
persistent_engagement
reduced_activity_engagement
```

T04 boleh menggunakan informasi tersebut untuk menyesuaikan bentuk bantuan.

Contoh:

Jika learner menunjukkan persistent attempt behavior:

```text
beri hint singkat
→ biarkan learner mencoba kembali
```

Jika learner menunjukkan corrective behavior:

```text
beri feedback yang membantu learner memperbaiki kesalahan
```

Jika evidence menunjukkan reduced activity engagement:

```text
gunakan response yang singkat dan terarah
```

Jangan membuat klaim:

```text
learner malas
learner tidak termotivasi
learner cemas
learner frustrasi
```

Behavioral indicator tidak boleh diubah menjadi diagnosis psikologis.

---

# 11. Intervention Provenance

Setiap intervention harus dapat ditelusuri.

Minimal:

```text
Intervention
    ↓
LearningState
    ↓
ValidatedEvidence
    ↓
LearningEvent
```

Sistem harus dapat menjawab:

> Mengapa intervention ini diberikan?

Jawaban harus berasal dari Learning State dan evidence yang menjadi dasarnya.

Contoh:

```text
State:
needs_support

Reason:
Repeated unsuccessful outcomes were observed.

Intervention:
guided_retry

Explanation:
Learner is given another attempt with targeted guidance
based on the observed unsuccessful outcome.
```

---

# 12. Idempotency

Pemrosesan Learning State yang sama tidak boleh menghasilkan intervention duplikat tanpa alasan.

Gunakan mekanisme idempotency yang sesuai dengan pola M4-T03.

Intervention harus memiliki deterministic identity/inference key yang dapat mencegah duplicate intervention dari input yang sama.

Retry request tidak boleh menghasilkan banyak intervention identik.

---

# 13. Learner Interaction

T04 V1 harus memungkinkan learner menerima intervention dan kemudian melakukan aktivitas/retry.

Contoh alur:

```text
Activity
   ↓
Evidence
   ↓
Learning State
   ↓
needs_support
   ↓
Intervention
   ↓
Learner reads/answers
   ↓
Retry
   ↓
New Learning Evidence
```

T04 tidak perlu melakukan analisis longitudinal terhadap hasil retry.

Perubahan state dari waktu ke waktu akan dianalisis pada M5.

---

# 14. AI Usage

M4-T04 V1 tidak wajib menggunakan generative AI untuk semua intervention.

Gunakan rule-based intervention terlebih dahulu.

Tujuannya adalah membuat baseline yang:

- explainable;
- deterministic;
- testable;
- reproducible.

Jika architecture BisaBelajar sudah memiliki AI/tutor service yang relevan, integrasi boleh dilakukan hanya jika tidak memperbesar scope dan tetap memiliki fallback yang deterministic.

Jangan membuat AI bebas menentukan intervention tanpa rule atau evidence.

---

# 15. AI-Generated Question / Reassessment

**Tidak menjadi implementasi utama M4-T04 V1.**

AI-assisted generation of new questions untuk menguji weak area akan menjadi bagian dari adaptive reassessment yang dirancang setelah intervention mechanism stabil.

Prinsip yang harus dijaga untuk implementasi selanjutnya:

> AI boleh membuat soal baru, tetapi kemampuan yang ingin diuji harus tetap sama dengan target kemampuan sebelumnya.

Contoh:

```text
Original task
    ↓
mengukur kemampuan tertentu
    ↓
Learner weak
    ↓
AI membuat soal berbeda
    ↓
kemampuan yang diuji tetap sama
    ↓
learner mengerjakan kembali
    ↓
new evidence
```

Soal baru tidak boleh sekadar mengulang soal lama dengan mengganti angka.

Namun mekanisme lengkap tersebut **bukan scope utama T04 V1**.

---

# 16. No Learning Recommendation

T04 tidak membuat Learning Recommendation penuh.

T04 hanya memberikan intervention/response.

Contoh T04:

```text
Berikan hint.
Ajukan pertanyaan Socratic.
Minta learner mencoba kembali.
```

T05 nanti:

```text
Learner sebaiknya mengulang konsep X.
Learner sebaiknya melakukan latihan Y.
Learner dapat melanjutkan ke materi berikutnya.
```

Jangan mencampurkan kedua tanggung jawab tersebut.

---

# 17. No Longitudinal Analysis

T04 tidak melakukan:

- trajectory analysis;
- improvement measurement;
- weak-area longitudinal tracking;
- intervention effectiveness analysis;
- cross-course comparison;
- cross-cohort analysis.

Semua itu berada di M5.

---

# 18. Data Model

Gunakan struktur yang mengikuti pola codebase yang sudah ada.

Jika diperlukan, buat model/tabel untuk menyimpan intervention.

Minimal informasi yang dibutuhkan:

```text
id
learner/user reference
learning_state_id
intervention_type
content/message
reason
confidence/reference if applicable
provenance/reference
created_at
updated_at
```

Nama field harus mengikuti konvensi codebase.

Jangan membuat tabel tambahan jika struktur existing dapat digunakan secara aman.

---

# 19. Service

Buat service khusus untuk adaptive intervention jika belum tersedia.

Contoh nama:

```text
AdaptiveInterventionService
```

Tanggung jawab service:

1. menerima Learning State;
2. memeriksa state;
3. menentukan intervention berdasarkan rule;
4. membuat intervention;
5. menyimpan provenance;
6. menjaga idempotency.

Service tidak bertanggung jawab atas:

- Learning State inference;
- Learning Recommendation;
- longitudinal analytics.

---

# 20. Testing

Tambahkan test khusus M4-T04.

Minimal test:

### State handling

- `needs_support` menghasilkan intervention
- `progressing` menghasilkan reinforcement yang sesuai
- `stable` tidak menghasilkan remedial intervention
- `insufficient_evidence` tidak menghasilkan strong intervention

### Intervention type

- cognitive support menghasilkan tipe intervention yang sesuai
- psychomotor support menghasilkan guided retry/corrective support
- behavioral indicators dapat memengaruhi bentuk response
- behavioral indicator tidak menghasilkan psychological diagnosis

### Socratic response

- Socratic intervention menghasilkan question/hint
- tidak langsung memberikan answer sebagai default

### Provenance

- intervention dapat ditelusuri ke Learning State
- Learning State dapat ditelusuri ke ValidatedEvidence
- ValidatedEvidence dapat ditelusuri ke LearningEvent

### Idempotency

- input Learning State yang sama tidak menghasilkan duplicate intervention

### Scope protection

Pastikan T04 tidak:

- membuat Learning Recommendation
- melakukan longitudinal analysis
- mengubah T02 validation flow
- mengubah T03 inference rules tanpa alasan yang jelas

---

# 21. Regression Test

Setelah implementasi:

Jalankan minimal:

```text
LearningStateInferenceTest
EvidenceValidationTest
M3ProgrammingEnvironmentTest
```

Kemudian jalankan full regression jika memungkinkan.

Expected:

```text
M4-T02 PASS
M4-T03 PASS
M4-T04 PASS
M3 PASS
Full regression PASS
```

Jika ada failure di luar T04:

> Jangan memperbaiki unrelated issue secara otomatis.

Laporkan terlebih dahulu.

---

# 22. Acceptance Criteria

M4-T04 dianggap selesai jika:

- [ ] Learning State dari T03 dapat menjadi input T04
- [ ] `needs_support` dapat menghasilkan adaptive intervention
- [ ] `progressing` mendapat response yang sesuai
- [ ] `stable` tidak mendapat remedial intervention tanpa evidence
- [ ] `insufficient_evidence` tidak menghasilkan strong intervention
- [ ] intervention memiliki reason
- [ ] intervention memiliki provenance
- [ ] intervention dapat ditelusuri ke Learning State
- [ ] Socratic response tersedia
- [ ] intervention tidak langsung memberikan jawaban sebagai default
- [ ] cognitive support tersedia
- [ ] psychomotor support tersedia
- [ ] behavioral indicators tetap observable
- [ ] tidak ada psychological diagnosis
- [ ] intervention idempotent
- [ ] T02 tidak rusak
- [ ] T03 tidak rusak
- [ ] tidak ada Learning Recommendation di T04
- [ ] tidak ada longitudinal analytics di T04
- [ ] test T04 PASS
- [ ] regression PASS

---

# 23. Research Traceability

M4-T04 membentuk bagian penting dari closed-loop NEXUS.

Research trace:

```text
Validated Evidence
        ↓
Learning State
        ↓
Adaptive Intervention
        ↓
Learner Response
        ↓
New Learning Evidence
```

T04 memungkinkan penelitian berikutnya untuk melihat:

> Apakah intervention yang diberikan berdasarkan Learning State menghasilkan respons belajar yang berbeda?

Namun pengukuran perubahan longitudinal belum dilakukan di T04.

Itu menjadi dasar untuk M5.

---

# 24. Implementation Rules

Saat mengerjakan task ini:

1. Baca M4-T03 terlebih dahulu.
2. Pahami model `LearningState`.
3. Pahami provenance `learning_state_evidence`.
4. Jangan mengubah M4-T02 tanpa alasan kuat.
5. Jangan mengubah T03 inference logic kecuali diperlukan untuk kompatibilitas.
6. Gunakan perubahan seminimal mungkin.
7. Jangan membuat AI/LLM architecture baru.
8. Jangan membuat M5.
9. Jangan membuat Learning Recommendation.
10. Jangan commit.
11. Jangan push.

Jika terdapat kebutuhan perubahan T02/T03 yang tidak dapat dihindari:

> STOP dan laporkan alasannya sebelum melakukan perubahan.

---

# 25. Execution Sequence

Implementasi dilakukan dengan urutan:

```text
1. Inspect existing M4-T03
        ↓
2. Plan T04 implementation
        ↓
3. Implement intervention model/storage
        ↓
4. Implement AdaptiveInterventionService
        ↓
5. Implement Socratic response V1
        ↓
6. Implement provenance + idempotency
        ↓
7. Add tests
        ↓
8. Run M4-T02 regression
        ↓
9. Run M4-T03 regression
        ↓
10. Run full regression
        ↓
11. Report result
        ↓
12. STOP
```

---

# 26. Final Report Format

Setelah selesai, laporkan:

## 1. Files changed / created

Daftar file.

## 2. Implementation summary

Ringkasan singkat apa yang dibuat.

## 3. Intervention rules

Daftar rule yang benar-benar diimplementasikan.

## 4. Socratic response

Jelaskan bagaimana response Socratic V1 bekerja.

## 5. Provenance

Jelaskan bagaimana intervention ditelusuri ke evidence.

## 6. Tests

Daftar test dan hasilnya.

## 7. Regression

Laporkan hasil:

- M4-T02
- M4-T03
- M3
- full regression

## 8. Concerns

Laporkan keterbatasan atau risiko.

## 9. Scope check

Pastikan:

```text
M4-T04 only
No M4-T05
No M5
No commit
No push
```

---

# 27. STOP

Setelah M4-T04 selesai dan seluruh test/report tersedia:

**STOP.**

Jangan mulai M4-T05.

Jangan mulai M5.

Jangan commit.

Jangan push.

Tunggu review manusia terlebih dahulu.
