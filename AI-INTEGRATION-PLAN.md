# BisaBelajar — AI Integration Implementation Plan
**Branch:** `feature/nexus-ai-integration`  
**Tanggal:** 3 September 2026

---

## Konteks Arsitektur yang Ada

Sistem sudah memiliki pondasi ini yang harus dipertahankan:

| Komponen Existing | Lokasi | Relevansi |
|---|---|---|
| `ReassessmentCandidateGenerator` (interface) | `app/Contracts/Research/` | AI menggantikan implementasi Deterministik |
| `DeterministicReassessmentCandidateGenerator` | `app/Services/Research/Reassessment/` | Tetap ada sebagai fallback/stub |
| `AdaptiveInterventionService` | `app/Services/Research/` | Konten intervensi Socratic saat ini hardcoded → bisa di-enhance AI |
| `AiAssistedReassessmentService` | `app/Services/Research/` | Orchestrator yang sudah memanggil generator via interface |
| `AppServiceProvider` | `app/Providers/` | Binding saat ini: interface → Deterministik (akan diubah ke LLM) |
| `SocraticResponseType` enum | `app/Enums/` | Tipe respons Socratic sudah terdefinisi |
| `ReassessmentGenerationException` | `app/Exceptions/` | Exception sudah ada untuk error AI |

---

## Scope Implementasi

### Yang DILAKUKAN:
1. Integrasi LLM nyata ke generator soal reassessment (menggantikan deterministik)
2. AI-enhanced Socratic hint content pada intervensi adaptif
3. Routing multi-provider dengan failover otomatis
4. Endpoint API untuk Socratic Tutor interaktif di halaman siswa
5. UI panel chat Socratic pada halaman aktivitas coding

### Yang TIDAK DILAKUKAN (sesuai AGENTS.md):
- Tidak mengubah logic inferensi Learning State
- Tidak mengubah aturan intervention selection (tetap rule-based)
- AI tidak menjadi decision-maker untuk nilai/progress
- AI tidak menerima PII siswa
- Kode siswa tidak dikirim ke LLM sebagai executable

---

## Task Breakdown Terurut

---

### TASK 1 — `config/ai.php` & Abstraksi Provider

**Tujuan:** Buat konfigurasi terpusat dan kontrak provider AI.

**Files yang dibuat:**
```
config/ai.php
app/Contracts/Ai/AiClientInterface.php
app/Enums/AiProvider.php
```

**Detail `config/ai.php`:**
```php
return [
    'default'       => env('AI_DEFAULT_PROVIDER', 'gemini'),
    'socratic'      => env('AI_SOCRATIC_PROVIDER', 'gemini'),
    'reassessment'  => env('AI_REASSESSMENT_PROVIDER', 'groq'),
    'fast'          => env('AI_FAST_PROVIDER', 'cerebras'),
    'fallback'      => env('AI_FALLBACK_PROVIDER', 'openrouter'),
    'timeout'       => (int) env('AI_TIMEOUT', 30),
    'max_retries'   => (int) env('AI_MAX_RETRIES', 3),

    'providers' => [
        'gemini' => [
            'keys'       => [env('GEMINI_API_KEY'), env('GEMINI_API_KEY_2'), env('GEMINI_API_KEY_3')],
            'model'      => env('GEMINI_MODEL', 'gemini-3.8-flash'),
            'model_lite' => env('GEMINI_MODEL_LITE', 'gemini-3.5-flash-lite'),
            'base_url'   => env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'groq' => [
            'key'        => env('GROQ_API_KEY'),
            'model'      => env('GROQ_MODEL', 'groq/compound'),
            'model_mini' => env('GROQ_MODEL_MINI', 'groq/compound-mini'),
            'base_url'   => env('GROQ_API_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],
        'cerebras' => [
            'key'        => env('CEREBRAS_API_KEY'),
            'model'      => env('CEREBRAS_MODEL', 'gpt-oss-120b'),
            'model_fast' => env('CEREBRAS_MODEL_FAST', 'gemma-4-31b'),
            'base_url'   => env('CEREBRAS_API_BASE_URL', 'https://api.cerebras.ai/v1'),
        ],
        'openrouter' => [
            'key'        => env('OPENROUTER_API_KEY'),
            'model'      => env('OPENROUTER_MODEL', 'minimax/minimax-m3:free'),
            'model_fast' => env('OPENROUTER_MODEL_FAST', 'z-ai/glm-5.2:free'),
            'base_url'   => env('OPENROUTER_API_BASE_URL', 'https://openrouter.ai/api/v1'),
            'referrer'   => env('OPENROUTER_REFERRER', 'https://bisabelajar.com'),
        ],
    ],
];
```

---

### TASK 2 — Implementasi Client per Provider

**Tujuan:** Adapter konkret setiap provider menggunakan HTTP client Laravel (tanpa SDK eksternal).

**Files yang dibuat:**
```
app/Services/Ai/Clients/GeminiAiClient.php      ← format API khusus Google
app/Services/Ai/Clients/GroqAiClient.php         ← OpenAI-compatible
app/Services/Ai/Clients/CerebrasAiClient.php     ← OpenAI-compatible
app/Services/Ai/Clients/OpenRouterAiClient.php   ← OpenAI-compatible
```

**Catatan:** Groq, Cerebras, OpenRouter semua menggunakan format OpenAI-compatible → base class abstrak bisa dibuat untuk menghindari duplikasi.  
Gemini menggunakan endpoint `/models/{model}:generateContent` dengan format sendiri.  
Gemini melakukan round-robin di antara 3 API key.

---

### TASK 3 — AI Client Manager (Router & Failover)

**Tujuan:** Service orchestrator yang memilih provider berdasarkan tujuan dan melakukan fallback otomatis.

**Files yang dibuat:**
```
app/Services/Ai/AiClientManager.php
```

**Logika failover:**
```
Primary provider → Rate limit / timeout / error → Fallback provider → Gagal → Exception
```

**API publik:**
```php
$manager->forSocratic(): AiClientInterface     // gemini → openrouter
$manager->forReassessment(): AiClientInterface  // groq → cerebras → openrouter
$manager->forFast(): AiClientInterface          // cerebras → openrouter
```

---

### TASK 4 — Prompt Builders (Socratic Guardrails)

**Tujuan:** System prompt dan user prompt yang aman secara pedagogis.

**Files yang dibuat:**
```
app/Services/Ai/Prompts/SocraticHintPromptBuilder.php
app/Services/Ai/Prompts/ReassessmentPromptBuilder.php
```

**SocraticHintPromptBuilder:**
- System: Instruksi NEXUS sebagai Socratic tutor — larangan direct answer, gunakan pertanyaan pemantik
- User: Konteks activity (concept, learning_objective, bloom_demand) + pesan error siswa (sanitized, tanpa PII)

**ReassessmentPromptBuilder:**
- Prompt membuat soal baru dari specification yang sudah ada
- Output harus JSON sesuai kontrak `ReassessmentCandidateGenerator::generate()`
- Instruksi eksplisit: `includes_direct_answer` harus `false`

---

### TASK 5 — LLM Reassessment Generator

**Tujuan:** Implementasi nyata `ReassessmentCandidateGenerator` menggunakan LLM.

**Files yang dibuat:**
```
app/Services/Research/Reassessment/LlmReassessmentCandidateGenerator.php
```

**Alur:**
1. Terima `$specification` (sudah bebas PII dari `AiAssistedReassessmentService`)
2. Bangun prompt via `ReassessmentPromptBuilder`
3. Kirim ke `AiClientManager::forReassessment()`
4. Parse dan validasi JSON output
5. Assert `includes_direct_answer === false`
6. Return array sesuai interface
7. Gagal → `ReassessmentGenerationException`

**Perubahan di `AppServiceProvider`:** Binding interface → `LlmReassessmentCandidateGenerator`  
(`DeterministicReassessmentCandidateGenerator` tetap ada, digunakan di test environment)

---

### TASK 6 — Socratic Tutor Service & Endpoint

**Tujuan:** Endpoint API untuk Socratic hint interaktif dari halaman siswa.

**Files yang dibuat:**
```
app/Services/Ai/NexusSocraticTutorService.php
app/Http/Controllers/Student/AiTutorController.php
app/Http/Requests/Student/AiHintRequest.php
```

**Route:**
```
POST /student/activities/{activity}/ai-hint
```

**Middleware:** `auth` + `role:student` + gate `enrolled`

**Input (sanitized — tidak ada PII):**
```json
{
  "error_message": "NameError: name 'x' is not defined",
  "test_case_label": "Test 1: basic input",
  "attempt_count": 3
}
```

**TIDAK dikirim ke LLM:** nama siswa, email, user_id, kode submission lengkap.

---

### TASK 7 — UI Socratic Chat Panel

**Tujuan:** Panel hint/chat di halaman `programming.blade.php`.

**Files yang dimodifikasi:**
```
resources/views/activities/programming.blade.php
```

**Files baru:**
```
resources/views/components/ai-hint-panel.blade.php
```

**Menggunakan komponen yang sudah ada:** `<x-modal>`, `<x-button>`, `<x-alert>`, Alpine.js

**UX Flow:**
1. Tombol "💡 Minta Petunjuk NEXUS" muncul setelah siswa gagal ≥1 kali
2. Klik → modal terbuka → loading spinner → respons AI tampil
3. Disclaimer: *"NEXUS membimbing Anda berpikir, bukan memberi solusi langsung"*

---

### TASK 8 — Tests (Unit + Integration + Performance + Regression)

**Tujuan:** Menguji setiap provider AI secara individual, failover, keamanan prompt, performa nyata, dan memastikan tidak ada regresi.

---

#### 8A — Unit Test per Client (Semua 4 Provider)

Setiap client adapter diuji secara terisolasi dengan `Http::fake()`.

**Files:**
```
tests/Unit/Ai/GeminiAiClientTest.php
tests/Unit/Ai/GroqAiClientTest.php
tests/Unit/Ai/CerebrasAiClientTest.php
tests/Unit/Ai/OpenRouterAiClientTest.php
```

**Skenario per client:**
- ✅ Request dikirim ke URL & endpoint yang benar
- ✅ Header Authorization terbentuk dengan benar (format per provider)
- ✅ Respons 200 → teks diparse dan dikembalikan
- ✅ Respons 429 (rate limit) → exception `RateLimitException`
- ✅ Respons 401 (key salah) → exception `AiAuthException`
- ✅ Timeout → exception `AiTimeoutException`
- ✅ Respons JSON malformed → exception `AiResponseException`
- ✅ Gemini: round-robin di antara 3 key berjalan benar
- ✅ OpenRouter: header `HTTP-Referer` dikirim

---

#### 8B — Unit Test AiClientManager (Routing & Failover)

**File:**
```
tests/Unit/Ai/AiClientManagerTest.php
```

**Skenario:**
- ✅ `forSocratic()` → mengembalikan Gemini client
- ✅ `forReassessment()` → mengembalikan Groq client
- ✅ `forFast()` → mengembalikan Cerebras client
- ✅ Gemini rate limit → fallback ke OpenRouter
- ✅ Groq rate limit → fallback ke Cerebras → fallback ke OpenRouter
- ✅ Semua provider gagal → `AllProvidersFailedException`
- ✅ Retry count dihormati (max 3)

---

#### 8C — Unit Test Prompt Builders (Guardrails)

**File:**
```
tests/Unit/Ai/SocraticHintPromptBuilderTest.php
tests/Unit/Ai/ReassessmentPromptBuilderTest.php
```

**Skenario:**
- ✅ System prompt mengandung larangan direct answer
- ✅ User prompt mengandung concept, bloom_demand, error_message
- ✅ **PII tidak masuk prompt** (nama, email, user_id tidak ada di output prompt)
- ✅ Kode submission siswa tidak disertakan
- ✅ Prompt reassessment meminta output JSON dengan struktur yang benar
- ✅ Prompt reassessment menegaskan `includes_direct_answer: false`

---

#### 8D — Feature Test LLM Generator & Socratic Endpoint

**Files:**
```
tests/Feature/Ai/LlmReassessmentGeneratorTest.php
tests/Feature/Ai/SocraticTutorEndpointTest.php
```

**Skenario generator:**
- ✅ AI mengembalikan JSON valid → candidate dibuat
- ✅ AI mengembalikan `includes_direct_answer: true` → ditolak, exception
- ✅ AI gagal semua provider → fallback ke `DeterministicReassessmentCandidateGenerator`
- ✅ Kandidat tidak memuat PII dalam `candidate_content`

**Skenario endpoint:**
- ✅ `POST /student/activities/{activity}/ai-hint` → 200 + hint
- ✅ Siswa tidak enrolled → 403
- ✅ Siswa role Tutor → 403
- ✅ Guest (tidak login) → 401
- ✅ Activity tidak ada → 404
- ✅ AI timeout → 503 dengan pesan fallback yang ramah
- ✅ Request body tanpa `error_message` → 422 validation error

---

#### 8E — Performance Test (Real API, Opsional / Skip di CI)

> **Catatan:** Test ini memanggil API sungguhan. Dijalankan manual saja (`--group=ai-performance`), TIDAK dijalankan di CI otomatis.

**File:**
```
tests/Performance/Ai/AllProvidersPerformanceTest.php
```

**Yang diukur per provider (Gemini, Groq, Cerebras, OpenRouter):**

| Metrik | Target |
|---|---|
| Time to First Token (TTFT) | Dicatat (tidak ada batas minimum) |
| Total response time | Dicatat |
| Token throughput (t/s) | Dicatat |
| Respons valid (format benar) | ✅ Harus PASS |
| Rate limit tercapai / tidak | Dicatat |
| Kualitas respons Socratic | Manual review — apakah membimbing / memberi jawaban langsung |

**Output test:** File laporan `storage/logs/ai-performance-{tanggal}.json` berisi semua metrik.

**Cara jalankan:**
```bash
php artisan test --group=ai-performance
```

---

#### 8F — Regression Test (M1–M6 Harus Tetap PASS)

**Strategi:**
- `AppServiceProvider` di environment `testing` tetap binding ke `DeterministicReassessmentCandidateGenerator`
- Semua test M1–M6 tidak bergantung pada API key
- Jalankan full suite setelah semua task selesai:

```bash
php artisan test
```

- ✅ Seluruh test M1–M6 yang sudah ada harus tetap hijau
- ✅ Tidak ada breaking change pada `AiAssistedReassessmentService`, `AdaptiveInterventionService`, dan `NexusClosedLoopService`

---

#### Ringkasan File Test

```
tests/
├── Unit/
│   └── Ai/
│       ├── GeminiAiClientTest.php
│       ├── GroqAiClientTest.php
│       ├── CerebrasAiClientTest.php
│       ├── OpenRouterAiClientTest.php
│       ├── AiClientManagerTest.php
│       ├── SocraticHintPromptBuilderTest.php
│       └── ReassessmentPromptBuilderTest.php
├── Feature/
│   └── Ai/
│       ├── LlmReassessmentGeneratorTest.php
│       └── SocraticTutorEndpointTest.php
└── Performance/
    └── Ai/
        └── AllProvidersPerformanceTest.php   ← real API, manual only
```

**Total test files baru: 10**

---

## Urutan Eksekusi

```
TASK 1 (config + interface)
    ↓
TASK 2 (4 client adapters)
    ↓
TASK 3 (manager + failover)
    ↓
TASK 4 (prompt builders)
    ↓
TASK 5 ─────────────────── TASK 6
(LLM generator)            (Socratic endpoint)
    ↓                           ↓
         TASK 7 (UI)
              ↓
         TASK 8 (tests)
```

---

## Estimasi File

| Kategori | Jumlah |
|---|---|
| Config | 1 |
| Contracts + Enums | 2 |
| Client Adapters | 4 |
| Client Manager | 1 |
| Prompt Builders | 2 |
| LLM Generator | 1 |
| Socratic Service | 1 |
| Controller + Request | 2 |
| Blade Views | 2 (1 baru, 1 modif) |
| AppServiceProvider | 1 modif |
| Tests | 3 |
| **TOTAL** | **~20 file** |
