# M5 — Research-Evidence & Longitudinal Learning Analytics

## Status

**V1 — Specification Locked**

M5 adalah **research-evidence and longitudinal learning analytics layer** yang menganalisis perubahan Learning State, behavioral patterns, intervention response, learning trajectory, dan contextual variation yang dihasilkan oleh closed-loop NEXUS.

Tujuan akhirnya adalah membuat BisaBelajar bukan hanya sebagai platform pembelajaran, tetapi sebagai **research-ready environment untuk program penelitian AI-VET jangka panjang**.

M5 tidak menggantikan fungsi M4.

- **M4** memahami kondisi learner dan melakukan adaptive action.
- **M5** melihat apa yang terjadi setelah proses tersebut dan menghasilkan evidence untuk analisis longitudinal dan penelitian.

---

# 1. Tujuan M5

M5 harus memungkinkan peneliti melihat:

1. apakah Learning State learner berubah;
2. bagaimana pola perilaku learner berkembang;
3. bagaimana learner merespons intervention;
4. bagaimana perjalanan belajar learner dari waktu ke waktu;
5. apakah pola berbeda berdasarkan konteks;
6. area belajar mana yang masih lemah;
7. apakah area yang lemah membaik setelah reassessment dengan task yang berbeda;
8. evidence yang dapat ditelusuri kembali ke sumbernya untuk kebutuhan penelitian.

Core loop:

```text
Learning Activity
      ↓
Learning Evidence
      ↓
M4 Learning State
      ↓
Adaptive Intervention
      ↓
Next Learning Activity
      ↓
New Evidence
      ↓
M4 New Learning State
      ↓
M5 Research Analysis
```

---

# 2. Prinsip Dasar

## 2.1 M5 tidak menentukan Learning State

Penentuan Learning State tetap merupakan tanggung jawab M4-T03.

M5 menggunakan hasil M4 sebagai input.

```text
M4 → menentukan state
M5 → menganalisis perubahan/pola state
```

## 2.2 M5 tidak menentukan intervention

M5 dapat menganalisis respons terhadap intervention, tetapi tidak mengambil alih logic adaptive intervention M4-T04.

## 2.3 M5 harus evidence-based

M5 tidak boleh membuat klaim berdasarkan asumsi yang tidak didukung evidence.

Semua hasil analisis harus dapat ditelusuri ke:

- LearningEvent;
- ValidatedEvidence;
- Learning State;
- intervention;
- activity/task;
- konteks pembelajaran.

## 2.4 Observable behavior, bukan diagnosis psikologis

Behavioral/affective analysis harus tetap menggunakan observable learning behavior.

M5 tidak boleh melakukan:

- diagnosis psikologis;
- diagnosis klinis;
- klaim tentang kondisi mental learner;
- inferensi personal yang tidak didukung evidence.

---

# 3. Fokus M5 V1

M5 V1 memiliki tujuh kemampuan utama:

1. **Research Evidence & Data Foundation**
2. **Learning State Change + Trajectory**
3. **Weak Area Identification**
4. **AI-Assisted Reassessment**
5. **Intervention Response + Improvement**
6. **Contextual Variation**
7. **Tests + Research Evidence Export**

---

# M5-01 — Research Evidence & Data Foundation

## Tujuan

Menyediakan fondasi data agar hasil M4 dapat digunakan kembali untuk analisis longitudinal dan penelitian.

## Input

Minimal:

- LearningEvent;
- ValidatedEvidence;
- Learning State dari M4;
- activity/task context;
- intervention record dari M4-T04 bila sudah tersedia;
- learner;
- course;
- module/learning unit;
- timestamp/session;
- programming language bila relevan.

## Output

M5 harus mempunyai cara yang jelas untuk menghubungkan:

```text
Learner
   ↓
Activity
   ↓
Evidence
   ↓
Learning State
   ↓
Intervention
   ↓
Next Activity
   ↓
New Evidence
   ↓
New Learning State
```

## Requirement

- Jangan menduplikasi data yang sebenarnya sudah tersedia di M3/M4 tanpa alasan.
- Gunakan foreign key/reference yang jelas.
- Provenance harus dipertahankan.
- Historical data tidak boleh hilang ketika state baru dibuat.
- Data harus dapat digunakan untuk analisis longitudinal.
- Jangan membuat data research yang tidak mempunyai sumber.

## Acceptance Criteria

- M5 dapat mengambil riwayat state learner.
- Setiap state dapat ditelusuri ke evidence/context yang mendasarinya.
- Intervention dapat dikaitkan dengan learner/activity/state yang relevan.
- Data antar-aktivitas dapat diurutkan berdasarkan waktu.
- Tidak ada perubahan semantics M3/M4.

## Out of Scope

- data warehouse besar;
- distributed analytics;
- predictive ML;
- causal inference.

---

# M5-02 — Learning State Change + Trajectory

## Tujuan

Melihat perubahan Learning State learner dari waktu ke waktu.

Contoh:

```text
Activity 1 → needs_support
Activity 2 → needs_support
Activity 3 → progressing
Activity 4 → progressing
Activity 5 → stable
```

## Analisis Minimum

M5 harus dapat mengetahui:

- state sebelumnya;
- state berikutnya;
- waktu perubahan;
- activity yang terkait;
- evidence yang mendukung;
- arah perubahan.

Contoh:

```text
needs_support → progressing
```

atau:

```text
progressing → needs_support
```

## Learning Trajectory

Trajectory V1 adalah urutan state yang teramati dari waktu ke waktu.

Tidak perlu melakukan prediksi masa depan.

## Acceptance Criteria

- History state dapat ditampilkan/diambil secara kronologis.
- Transition antar-state dapat dihitung.
- Perubahan dapat ditelusuri ke evidence.
- Tidak ada state history yang ditimpa.
- Sistem dapat membedakan state baru dari state sebelumnya.

## Out of Scope

- trajectory prediction;
- machine learning prediction;
- forecasting.

---

# M5-03 — Weak Area Identification

## Tujuan

Mengidentifikasi area belajar yang menunjukkan bukti kelemahan dan mungkin membutuhkan penguatan.

Contoh:

```text
Variables       → cukup baik
Condition       → cukup baik
Loop            → perlu diperkuat
Function        → perlu diperkuat
```

## Prinsip Penting

Satu kegagalan tidak cukup untuk menyatakan suatu area lemah.

M5 harus mempertimbangkan evidence yang tersedia, misalnya:

- performance evidence;
- repeated difficulty;
- Learning State;
- task demand;
- evidence quality;
- evidence confidence;
- corrective behavior;
- history pada area/kompetensi yang sama.

## Area yang Dapat Dianalisis

Bergantung pada task context, misalnya:

- topic;
- skill;
- competency;
- learning objective;
- cognitive demand;
- psychomotor demand.

## Bloom dan Dave

Jika task memiliki mapping:

- Revised Bloom;
- Dave Psychomotor;

M5 dapat menggunakan mapping tersebut untuk menjelaskan area yang masih membutuhkan penguatan.

Contoh:

```text
Topic: Loop
Bloom demand: Apply
Observed evidence: repeated difficulty
```

Hasil:

> Loop pada level Apply menunjukkan kebutuhan penguatan.

## Batasan

M5 tidak boleh mengatakan:

> “Learner secara psikologis lemah.”

Gunakan bahasa evidence-based:

> “Evidence menunjukkan area X membutuhkan penguatan.”

## Acceptance Criteria

- Weak area hanya muncul jika terdapat evidence yang cukup.
- Single failure tidak otomatis menjadi weak area.
- Weak area dapat ditelusuri ke evidence.
- Sistem dapat menjelaskan alasan area tersebut ditandai.
- Quality/confidence evidence tetap dihormati.

---

# M5-04 — AI-Assisted Competency-Aligned Reassessment

## Tujuan

Menggunakan AI untuk membantu membuat task reassessment baru ketika M5 menemukan area yang membutuhkan penguatan.

## Prinsip Utama

**AI membuat soal baru berdasarkan bagian yang masih lemah, tetapi tetap menjaga kemampuan yang ingin diuji.**

AI tidak boleh membuat soal secara bebas hanya berdasarkan topik.

Pipeline:

```text
Weak Area
    ↓
Target Competency
    ↓
Task Demand
    ├── Revised Bloom
    └── Dave Psychomotor
    ↓
AI Generate Candidate Task
    ↓
Validation
    ↓
Learner
```

## Contoh

Weak area:

```text
Loop
Bloom: Apply
```

Task lama:

> Menghitung jumlah angka 1–100.

Task reassessment dapat berupa:

> Menghitung jumlah bilangan genap dalam rentang tertentu.

Task berbeda, tetapi kompetensi dan demand yang ingin diuji tetap selaras.

## Aturan AI Reassessment

### 1. Kompetensi harus tetap

Jika target:

```text
Loop + Apply
```

AI tidak boleh tiba-tiba membuat:

```text
Loop + Create
```

kecuali task demand baru tersebut sengaja ditentukan oleh sistem/tutor.

### 2. Task harus berbeda

Task baru tidak boleh hanya:

- mengganti angka;
- mengganti nama variabel;
- mengganti nama objek;

sementara struktur masalah tetap sama.

Variasi harus bermakna tetapi tetap menguji kompetensi target.

### 3. Bloom dan Dave menjadi constraint

Jika tersedia, AI harus mempertahankan task demand yang sesuai:

- Revised Bloom;
- Dave Psychomotor.

### 4. AI-generated task adalah candidate

AI tidak otomatis menghasilkan task yang valid.

Urutan:

```text
AI Generate
    ↓
Task Validation
    ↓
Candidate Task
    ↓
Learner
```

### 5. Human tetap memiliki kontrol

AI membantu tutor dan mengurangi beban membuat banyak variasi latihan.

Tutor tetap dapat:

- menentukan competency;
- menentukan batasan;
- review task;
- melakukan override;
- menolak task.

Prinsip:

> **AI assists the tutor; AI does not replace pedagogical judgment.**

## Reassessment

Learner harus diuji dengan task baru yang berbeda tetapi tetap mengukur kompetensi yang relevan.

Tujuan:

> Menguji apakah learner memahami kompetensi, bukan apakah learner mengingat jawaban task sebelumnya.

## Acceptance Criteria

- Sistem dapat mengidentifikasi target weak area.
- Sistem dapat menentukan target competency/task demand dari context yang tersedia.
- AI generator menerima constraint tersebut.
- Generated task berbeda dari task sebelumnya.
- Generated task tetap aligned dengan target competency.
- Bloom/Dave constraint tidak diabaikan bila tersedia.
- Candidate task dapat divalidasi sebelum diberikan.
- Human dapat melakukan review/override.
- Task reassessment mempunyai provenance.
- Tidak ada klaim bahwa AI-generated task otomatis valid.

## Out of Scope

- AI menggantikan tutor;
- autonomous pedagogical decision tanpa constraint;
- unrestricted question generation;
- adaptive intervention engine.

---

# M5-05 — Intervention Response + Improvement

## Tujuan

Melihat apa yang terjadi setelah intervention atau reassessment.

Core sequence:

```text
Weak Area / Learning State
        ↓
Intervention / Reassessment
        ↓
Different Task
        ↓
New Evidence
        ↓
New Learning State
        ↓
Improvement Analysis
```

## Contoh

```text
BEFORE

Loop
Learning State: needs_support


        ↓

INTERVENTION / REASSESSMENT

Task baru
Kompetensi tetap Loop
Task berbeda


        ↓

AFTER

Performance membaik
Learning State: progressing
```

## Improvement

V1 menggunakan istilah:

> **Observed Improvement**

bukan langsung:

> **Causal Effect**

Karena kenaikan hasil setelah intervention belum otomatis membuktikan hubungan sebab-akibat.

## Yang Dapat Dibandingkan

- Learning State;
- performance evidence;
- behavioral evidence;
- task success/failure;
- corrective behavior;
- weak area status.

## Acceptance Criteria

- M5 dapat menghubungkan sebelum dan sesudah intervention/reassessment.
- Task sesudah dapat dipastikan berbeda dari task sebelumnya.
- Target competency dapat dibandingkan.
- Evidence sebelum dan sesudah dapat ditelusuri.
- Improvement dapat dijelaskan berdasarkan evidence.
- Tidak ada klaim causal tanpa desain penelitian yang memadai.

---

# M5-06 — Contextual Variation

## Tujuan

Melihat apakah pola learning state, behavior, intervention response, dan weak area berbeda berdasarkan konteks.

## Contoh Konteks

- kampus;
- course;
- module;
- learning unit;
- activity;
- programming language;
- task type;
- task difficulty;
- cohort;
- session/time period.

## Target Evaluasi

Prototype penelitian akan digunakan pada:

```text
Kampus A → Python
Kampus B → C++
```

Tujuan bukan untuk mengatakan Python lebih baik daripada C++.

Tujuannya:

> Melihat apakah pendekatan NEXUS dapat digunakan pada konteks pembelajaran yang berbeda.

## Acceptance Criteria

- Research evidence menyimpan konteks yang relevan.
- Data dapat difilter berdasarkan konteks.
- Learning State dapat dibandingkan berdasarkan konteks.
- Weak area dapat dibandingkan berdasarkan konteks.
- Intervention response dapat dibandingkan berdasarkan konteks.
- Konteks tidak mengubah provenance evidence.

## Out of Scope

- klaim generalisasi universal;
- causal comparison antar-kampus;
- ranking kampus;
- ranking bahasa pemrograman.

---

# M5-07 — Tests + Research Evidence Export

## Tujuan

Memastikan seluruh fungsi M5 dapat diuji dan hasilnya dapat digunakan untuk penelitian.

## Testing

Minimal harus ada automated tests untuk:

### Research Evidence

- provenance;
- chronological ordering;
- historical state preservation.

### Learning State

- state transition;
- trajectory;
- repeated state;
- insufficient evidence.

### Weak Area

- weak area dengan evidence cukup;
- single failure tidak menjadi weak area;
- evidence quality/confidence memengaruhi keputusan sesuai aturan.

### Reassessment

- task berbeda;
- competency tetap;
- Bloom/Dave constraint;
- provenance task;
- validation sebelum learner.

### Improvement

- before/after linkage;
- observed improvement;
- no unsupported causal claim.

### Context

- campus;
- programming language;
- course/activity;
- contextual comparison.

## Regression

M5 tidak boleh merusak:

- M3 Programming Environment;
- M4-T02 Evidence Validation;
- M4-T03 Learning State;
- M4-T04 Adaptive Intervention ketika sudah tersedia.

## Research Evidence Export

V1 harus menyediakan cara untuk mengekspor evidence yang dibutuhkan penelitian.

Minimal hasil export dapat memuat:

- learner identifier/pseudonymized identifier sesuai kebutuhan penelitian;
- timestamp;
- course;
- activity;
- task context;
- evidence;
- Learning State;
- state transition;
- weak area;
- intervention;
- reassessment task;
- reassessment result;
- improvement status;
- programming language;
- campus/context;
- provenance.

Export harus mempertahankan traceability.

## Acceptance Criteria

- Test suite M5 lulus.
- Existing M3/M4 tests tetap lulus.
- Research dataset dapat diekspor.
- Export dapat digunakan untuk analisis lanjutan.
- Data memiliki provenance.
- Tidak ada data research yang tidak mempunyai sumber.

---

# 4. Hubungan M4 dan M5

| Layer | Fungsi |
|---|---|
| M4-T02 | Memvalidasi evidence |
| M4-T03 | Menentukan Learning State |
| M4-T04 | Memberikan Adaptive Intervention |
| M5 | Melihat perubahan, pola, respons, trajectory, weak area, dan contextual variation |

Prinsip:

```text
M4 = Understand + Act

M5 = Observe + Compare + Analyze + Preserve Research Evidence
```

---

# 5. Closed-Loop Research Model

```text
              Learning Activity
                     ↓
              Learning Evidence
                     ↓
              M4 Evidence Validation
                     ↓
              M4 Learning State
                     ↓
             Adaptive Intervention
                     ↓
              Next Learning Task
                     ↓
               New Evidence
                     ↓
              New Learning State
                     ↓
                     M5
       ┌─────────────┼─────────────┐
       ↓             ↓             ↓
 State Change   Weak Area      Intervention
 Trajectory     Detection      Response
       │             │             │
       └─────────────┼─────────────┘
                     ↓
              AI Reassessment
                     ↓
              Different Task
                     ↓
              New Evidence
                     ↓
             Observed Improvement
```

---

# 6. Research Relevance

M5 menjadi fondasi untuk penelitian mengenai:

1. perubahan Learning State;
2. respons learner terhadap adaptive intervention;
3. weak area dan reassessment;
4. learning trajectory;
5. behavioral patterns;
6. contextual variation;
7. penggunaan AI untuk menghasilkan competency-aligned reassessment task;
8. evaluasi pada dua kampus dan dua bahasa pemrograman.

M5 mendukung tujuan penelitian besar:

> **Evidence → Learning State → Adaptive Intervention → New Evidence → Observed Change**

---

# 7. Hubungan dengan RQ

Rumusan final RQ1–RQ3 harus tetap mengikuti versi yang sudah dikunci dalam research design utama.

M5 harus menyediakan data yang memungkinkan analisis:

### RQ1

Learning evidence dan Learning State.

### RQ2

Adaptive intervention dan perubahan yang teramati setelahnya.

### RQ3

Perbedaan/pola pada konteks pembelajaran yang berbeda.

M5 tidak boleh mengubah research question hanya karena keterbatasan implementasi.

---

# 8. Hubungan dengan Paper

M5 menjadi supporting infrastructure untuk beberapa kemungkinan paper, terutama:

- Adaptive Intervention;
- Longitudinal Learning Analytics;
- Weak Area & Reassessment;
- Cross-context Evaluation.

Paper TAM/UTAUT/ISO 25010 tetap merupakan jalur evaluasi platform dan tidak boleh dicampur dengan research evidence NEXUS tanpa alasan metodologis.

---

# 9. V1 Research Boundaries

M5 V1 **TIDAK** melakukan:

- psychological diagnosis;
- clinical diagnosis;
- autonomous tutor replacement;
- unrestricted AI question generation;
- predictive learning-state modeling;
- trajectory forecasting;
- causal inference otomatis;
- automatic hypothesis testing;
- large-scale data warehouse;
- complex real-time dashboard;
- automatic publication/statistical reporting.

---

# 10. Implementation Strategy

M5 harus diimplementasikan bertahap:

```text
M5-01
Research Evidence & Data Foundation
        ↓
M5-02
Learning State Change + Trajectory
        ↓
M5-03
Weak Area Identification
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
Tests + Research Evidence Export
```

Setiap task harus:

1. membaca implementation yang sudah ada;
2. memahami dependency;
3. membuat perubahan sekecil yang diperlukan;
4. menambahkan tests;
5. menjalankan test yang relevan;
6. tidak mengubah scope task berikutnya;
7. tidak melakukan commit sebelum review.

**Hermes/Nexus tidak boleh mengerjakan seluruh M5 dalam satu task.**

---

# 11. Definition of Done — M5 V1

M5 V1 dianggap selesai apabila:

- research evidence dapat disimpan dan ditelusuri;
- Learning State history tersedia;
- state transition dapat dianalisis;
- learning trajectory dapat dianalisis;
- weak area dapat diidentifikasi berdasarkan evidence;
- AI-assisted reassessment dapat menghasilkan candidate task yang competency-aligned;
- reassessment task berbeda dari task sebelumnya;
- Bloom/Dave constraints dipertahankan bila tersedia;
- generated task dapat divalidasi;
- intervention/reassessment response dapat dianalisis;
- observed improvement dapat diidentifikasi;
- contextual variation dapat dianalisis;
- research evidence dapat diekspor;
- seluruh M5 tests lulus;
- M3/M4 regression tetap lulus;
- provenance tetap terjaga;
- tidak ada psychological/clinical diagnosis;
- tidak ada unsupported causal claim.

---

# 12. Required Final Report from Hermes/Nexus

Setelah setiap task selesai, Hermes/Nexus harus melaporkan:

## 1. Task

Task M5 yang dikerjakan.

## 2. Files Changed

Daftar file yang dibuat/diubah/dihapus.

## 3. Implementation Summary

Penjelasan singkat perubahan.

## 4. Research Logic

Bagaimana implementasi mendukung tujuan penelitian.

## 5. Tests

Test yang ditambahkan/dijalankan.

Format:

```text
Test:
Result:
```

## 6. Regression

Laporkan test M3/M4 yang dijalankan dan hasilnya.

## 7. Known Limitations

Hal yang belum diimplementasikan.

## 8. Scope Check

Pastikan tidak masuk:

- task berikutnya;
- M6;
- fitur di luar scope.

## 9. Git Status

Laporkan:

```bash
git status --short
```

**Jangan commit.**

---

# 13. Final Principle

Prinsip paling penting M5:

> **M5 tidak hanya melihat apakah learner berhasil. M5 harus membantu kita memahami perubahan learner, area yang masih lemah, respons terhadap intervention, dan apakah kemampuan tersebut membaik ketika diuji kembali dengan task yang berbeda tetapi tetap mengukur kompetensi yang sama.**

Prinsip AI reassessment:

> **AI membuat soal baru berdasarkan bagian yang masih lemah, tetapi tetap menjaga kemampuan yang ingin diuji.**

AI membantu tutor mengatasi keterbatasan waktu dan kebutuhan variasi task, tetapi **AI tidak menggantikan pertimbangan pedagogis manusia**.

---

# 14. Research Vision

M5 menjadikan BisaBelajar lebih dari sekadar learning platform:

```text
Platform Pembelajaran
        +
AI Learning Analytics
        +
Adaptive Learning
        +
Longitudinal Evidence
        +
Research Evidence
        ↓
Research-Ready AI-VET Environment
```

M5 dirancang sebagai fondasi yang dapat digunakan kembali untuk penelitian AI-VET jangka panjang, bukan hanya untuk satu eksperimen.
