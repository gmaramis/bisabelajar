# BisaBelajar — UI/UX Design System Specification

## 1. Overview & Design Philosophy

**BisaBelajar** adalah platform pembelajaran cerdas (*AI-VET Learning Platform*) yang dirancang untuk memberikan pengalaman belajar kejuruan dan pemrograman yang terstruktur, adaptif, dan modern.

### Prinsip Utama Desain:
1. **Mobile-First Architecture**: Desain dibangun dari layar terkecil (360px) lalu bertransisi secara mulus ke tablet (640px), laptop/desktop (1024px), hingga layar ultra-wide (1920px).
2. **Harmonis dengan Aturan 60-30-10**: Distribusi visual yang seimbang antara latar belakang dominan (60%), elemen struktur pendukung (30%), dan aksen biru primer (10%).
3. **Dual Theme (Light & Dark Mode)**: Pengalaman visual yang konsisten dan nyaman di mata untuk sesi belajar panjang, didukung arsitektur *Zero-Flash Anti-FOUC*.
4. **Ringan & Optimal (Zero-Font Overhead)**: Menggunakan **Blade Heroicons** (SVG inline server-rendered) untuk performa instan tanpa unduhan font eksternal.
5. **KISS Component Architecture (Keep It Simple, Stupid)**: Struktur komponen *flat* tanpa nested folder berlebihan, dipadukan dengan *feature-centric partials* untuk kemudahan pemeliharaan.
6. **Accessible & Touch-Friendly**: Target sentuh minimal 44x44px pada layar mobile, navigasi ramah keyboard di desktop, dan kontras tinggi (WCAG AA).
7. **No Heavy UI Frameworks**: Mengandalkan **Blade Components + Tailwind CSS v4 + Alpine.js + Blade Heroicons** tanpa dependensi framework UI yang berlebihan.
8. **Context-Aware Learning UI**: Menunjang hierarki pembelajaran: *Course → Module → Learning Unit → Activity / Material* serta integrasi editor pemrograman dan evaluasi analitik NEXUS.

---

## 2. Responsive Breakpoint Matrix (Mobile-First)

Sistem antarmuka BisaBelajar dibangun dengan paradigma **Mobile-First** murni. Seluruh utility class default berlaku untuk layar mobile terkecil, dan *media query prefix* (`sm:`, `lg:`, `2xl:`) digunakan untuk memperluas antarmuka di layar yang lebih besar.

```
+-----------------------------------------------------------------------------------------------+
|                            RESPONSIVE BREAKPOINT MATRIX                                       |
|                                                                                               |
|  [360px - Base]      -->  [640px - sm]         -->  [1024px - lg]         -->  [1920px - 2xl] |
|  Compact Mobile           Tablet Portrait /         Desktop / Laptop           Full HD /      |
|  Single Column            Large Mobile              Persistent Sidebar         Ultra-Wide     |
|  Drawer Navigation        2-Column Grid             Split IDE Workspace        Max Boundary   |
+-----------------------------------------------------------------------------------------------+
```

### 2.1. Spesifikasi Target Layar

| Breakpoint | Target Viewport | Perilaku Layout | Elemen Kunci |
| :--- | :--- | :--- | :--- |
| **Base (Default)** | **360px** (Mobile) | 1 Kolom vertikal, full-width cards, off-canvas navigation drawer. | Touch target ≥ 44px, bottom action sheet / sticky CTA bar, font dasar 14px. |
| **`sm:`** | **640px** (Tablet Portrait / Large Mobile) | 2 Kolom untuk grid metrik & modul kursus, padding horizontal lebih lega. | Card padding `p-6`, modal width scaling (`max-w-md` s.d. `max-w-lg`). |
| **`lg:`** | **1024px** (Desktop / Tablet Landscape) | 3–4 Kolom grid, sidebar permanen (*sticky*), layout split-screen aktif. | Coding IDE & compiler console tampil berdampingan (*split view*). |
| **`2xl:`** | **1920px** (Full HD / Ultra-Wide) | Batas maksimum kontainer (`max-w-7xl` / `max-w-[1800px]`), padding seimbang. | Multi-pane analitik NEXUS, workspace terisolasi tanpa distorsi layout. |

### 2.2. Konfigurasi Breakpoints di Tailwind CSS v4
```css
/* resources/css/app.css */
@theme {
    --breakpoint-xs: 360px;
    --breakpoint-sm: 640px;
    --breakpoint-md: 768px;
    --breakpoint-lg: 1024px;
    --breakpoint-xl: 1280px;
    --breakpoint-2xl: 1920px;
}
```

---

## 3. Technical UI Baseline

| Komponen | Spesifikasi & Implementasi |
| :--- | :--- |
| **Framework CSS** | Tailwind CSS v4 (`@tailwindcss/vite`, `@theme`, `@custom-variant`) |
| **Interaktivitas UI** | Alpine.js v3.x (`x-data`, `x-show`, `x-cloak`, `x-transition`) |
| **Ikonografi** | **Blade Heroicons** (`blade-ui-kit/blade-heroicons` — `<x-heroicon-o-*>` & `<x-heroicon-s-*>`) |
| **Tipografi** | `Instrument Sans` (Google Fonts) & `JetBrains Mono` / Monospace |
| **Komponen Server** | Laravel Blade Anonymous Components (`<x-card>`, `<x-button>`, dll.) |
| **Browser Testing** | **Laravel Dusk** (Headless Google Chrome & ChromeDriver) |
| **Build Tool** | Vite 6+ / 8+ dengan `@laravel/vite-plugin` |

---

## 4. Sistem Warna: Aturan 60-30-10 & Palet Biru

Sistem warna BisaBelajar menerapkan aturan proporsi visual **60-30-10** untuk menjaga keseimbangan antarmuka pengguna pada tema terang (*Light Mode*) dan tema gelap (*Dark Mode*).

```
+-------------------------------------------------------------------------------+
|                        DISTRIBUSI WARNA 60 - 30 - 10                          |
|                                                                               |
|  [60% DOMINAN]               [30% STRUKTURAL & TEKS]      [10% AKSEN UTAMA]   |
|  Canvas, Body Background,    Sidebar, Card Borders,        Primary CTA Button,|
|  Card Surface, Main Areas    Typography, Table Headers,    Active Navigation, |
|                              Subtle Neutral Panels         Focus Rings, Badge |
+-------------------------------------------------------------------------------+
```

### 4.1. Rincian Proporsi Warna

#### **60% — Warna Dominan (Canvas & Surfaces)**
Warna dasar yang mengisi sebagian besar ruang antarmuka, memberikan kenyamanan visual dan keterbacaan yang luas.
- **Light Mode**:
  - Page Canvas (`bg-slate-50` / `#f8fafc`)
  - Primary Card Surface (`bg-white` / `#ffffff`)
- **Dark Mode**:
  - Page Canvas (`bg-slate-950` / `#020617` atau `bg-zinc-950` / `#09090b`)
  - Primary Card Surface (`bg-slate-900` / `#0f172a` atau `bg-zinc-900` / `#18181b`)

#### **30% — Warna Struktural & Tipografi (Secondary / Neutral Support)**
Warna pembangun struktur antarmuka, teks konten, garis pemisah (*borders*), latar tabel, dan elemen navigasi sekunder.
- **Light Mode**:
  - Headings & Primary Text (`text-slate-900` / `#0f172a`)
  - Body & Secondary Text (`text-slate-600` / `#475569`, `text-slate-500` / `#64748b`)
  - Borders & Dividers (`border-slate-200` / `#e2e8f0`, `border-slate-100` / `#f1f5f9`)
  - Secondary Surfaces / Headers (`bg-slate-100/70` / `#f1f5f9`)
- **Dark Mode**:
  - Headings & Primary Text (`text-slate-100` / `#f1f5f9`, `text-white`)
  - Body & Secondary Text (`text-slate-400` / `#94a3b8`, `text-slate-500` / `#64748b`)
  - Borders & Dividers (`border-slate-800` / `#1e293b`)
  - Secondary Surfaces / Headers (`bg-slate-900/60` / `#0f172a`)

#### **10% — Warna Aksen Biru (Brand Accent & Action Focal Points)**
Warna berkarakter tinggi (*high-impact*) yang digunakan secara selektif untuk mengarahkan atensi pengguna ke aksi utama: tombol primer, status aktif, indikator progres, link fokus, dan elemen interaktif kunci.
- **Primary Blue Accent**:
  - Default Action / Primary Button: `bg-blue-600` (`#2563eb`) atau `bg-sky-600` (`#0284c7`)
  - Hover & Active State: `hover:bg-blue-700` (`#1d4ed8`) / `active:bg-blue-800`
  - Focus Ring: `focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500`
  - Light Mode Accent Tint: `bg-blue-50 text-blue-700 border-blue-200/60`
  - Dark Mode Accent Tint: `dark:bg-blue-950/60 dark:text-blue-400 dark:border-blue-800/60`
  - Progress / Active Dot: `bg-blue-500` (`#3b82f6`) atau `bg-sky-500` (`#0ea5e9`)

---

### 4.2. Matriks Token Warna Lengkap

| Token Kategori | Light Mode Class | Dark Mode Class | Hex Ref (Approx) | Penggunaan |
| :--- | :--- | :--- | :--- | :--- |
| **Canvas (60%)** | `bg-slate-50` | `dark:bg-slate-950` | `#f8fafc` / `#020617` | Latar belakang seluruh halaman |
| **Card Surface (60%)** | `bg-white` | `dark:bg-slate-900` | `#ffffff` / `#0f172a` | Wadah konten modul, kartu unit |
| **Header Surface (30%)** | `bg-slate-50/75` | `dark:bg-slate-950/60` | `#f8fafc` / `#020617` | Header tabel, header panel |
| **Text Primary (30%)** | `text-slate-900` | `dark:text-white` | `#0f172a` / `#ffffff` | Judul kursus, modul, heading H1-H3 |
| **Text Muted (30%)** | `text-slate-500` | `dark:text-slate-400` | `#64748b` / `#94a3b8` | Subtitle, deskripsi, meta waktu |
| **Border Line (30%)** | `border-slate-200` | `dark:border-slate-800` | `#e2e8f0` / `#1e293b` | Garis pembatas kartu & tabel |
| **Brand Accent (10%)** | `bg-blue-600` | `dark:bg-blue-600` | `#2563eb` | Tombol CTA, Simpan, Jalankan Kode |
| **Accent Glow / Link (10%)** | `text-blue-600 hover:text-blue-700` | `dark:text-blue-400 dark:hover:text-blue-300` | `#2563eb` / `#60a5fa` | Navigasi aktif, link rujukan |
| **Focus Indicator (10%)** | `ring-blue-500` | `dark:ring-blue-400` | `#3b82f6` | Focus visible pada keyboard navigation |

---

### 4.3. Warna Status Semantik (Complementary Badges & Feedback)

- **Success / Mastery Achieved**:
  - Light: `bg-emerald-50 text-emerald-700 border-emerald-200/60`
  - Dark: `dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800/60`
  - Dot: `bg-emerald-500`
- **Warning / Under Review / In-Progress**:
  - Light: `bg-amber-50 text-amber-700 border-amber-200/60`
  - Dark: `dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800/60`
  - Dot: `bg-amber-500`
- **Danger / Needs Revision / Test Failed**:
  - Light: `bg-rose-50 text-rose-700 border-rose-200/60`
  - Dark: `dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800/60`
  - Dot: `bg-rose-500`
- **Info / Cognitive / Diagnostic**:
  - Light: `bg-blue-50 text-blue-700 border-blue-200/60`
  - Dark: `dark:bg-blue-950/60 dark:text-blue-400 dark:border-blue-800/60`
  - Dot: `bg-blue-500`

---

## 5. Struktur Folder & Pendekatan Komponen KISS

Penerapan prinsip **KISS (Keep It Simple, Stupid)** membagi komponen menjadi 2 kategori yang jelas dan tanpa over-engineering:

1. **`resources/views/components/` (Flat Global Reusable)**: Komponen umum yang dipakai lintas modul.
2. **`resources/views/{domain}/{fitur}/partials/` (Feature Partials)**: Potongan view spesifik untuk satu halaman/fitur tertentu.

### 5.1. Struktur Folder Direktori Views
```text
resources/views/
├── components/                     <-- [KISS] Flat global components (Reusable)
│   ├── badge.blade.php             <-- <x-badge variant="success" dot>Mastered</x-badge>
│   ├── button.blade.php            <-- <x-button variant="primary">Simpan</x-button>
│   ├── card.blade.php              <-- <x-card title="...">...</x-card>
│   ├── checkbox.blade.php          <-- <x-checkbox name="agree" label="..." />
│   ├── form-group.blade.php        <-- <x-form-group label="..." name="...">...</x-form-group>
│   ├── input.blade.php             <-- <x-input name="title" />
│   ├── label.blade.php             <-- <x-label value="Judul" required />
│   ├── modal.blade.php             <-- <x-modal name="confirm-modal">...</x-modal>
│   ├── select.blade.php            <-- <x-select name="role">...</x-select>
│   ├── textarea.blade.php          <-- <x-textarea name="description">...</x-textarea>
│   └── toast.blade.php             <-- <x-toast />
│
├── layouts/                        <-- [KISS] Layout utama sesuai peran
│   ├── app.blade.php               <-- Layout umum / Guest / Auth
│   ├── student.blade.php           <-- Layout portal belajar (Focus Mode)
│   └── tutor.blade.php             <-- Layout tutor/admin (Sidebar Workspace)
│
├── student/                        <-- Area Mahasiswa / Siswa
│   ├── dashboard.blade.php
│   ├── courses/
│   │   ├── index.blade.php
│   │   └── show.blade.php
│   └── units/
│       ├── show.blade.php
│       └── partials/               <-- Partial khusus unit pembelajaran
│           ├── _content-material.blade.php
│           └── _content-coding.blade.php
│
├── tutor/                          <-- Area Instruktur / Tutor
│   ├── courses/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   └── edit.blade.php
│   └── activities/
│       ├── create.blade.php
│       ├── edit.blade.php
│       └── partials/               <-- Partial variasi tipe form
│           ├── _form-quiz.blade.php
│           ├── _form-coding.blade.php
│           └── _form-essay.blade.php
│
└── auth/
    └── login.blade.php
```

### 5.2. Aturan Sederhana: Masuk `components/` vs `partials/`
- **Gunakan `components/`** jika komponen tersebut dipakai di **minimal 2 fitur/modul berbeda**.
- **Gunakan `partials/`** jika blok tampilan tersebut hanya relevan dan spesifik untuk **1 fitur itu saja**.

---

## 6. Tipografi & Font Hierarchy (Mobile-to-Desktop Scaling)

Menggunakan font modern sans-serif **`Instrument Sans`** untuk kejelasan antarmuka dan **`JetBrains Mono` / Monospace** untuk lingkungan coding.

```css
@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
    --font-mono: 'JetBrains Mono', 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
```

### Skala Tipografi Responsif (Fluid Scaling):
- **Display / Hero**: `text-2xl sm:text-3xl lg:text-4xl 2xl:text-5xl font-extrabold tracking-tight text-slate-900 dark:text-white`
- **Page Title (H1)**: `text-xl sm:text-2xl lg:text-3xl font-bold tracking-tight text-slate-900 dark:text-white`
- **Section Heading (H2)**: `text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100`
- **Card Title (H3)**: `text-base sm:text-lg font-bold leading-snug text-slate-900 dark:text-white`
- **Subtitle / Secondary**: `text-xs sm:text-sm text-slate-500 dark:text-slate-400`
- **Body Regular**: `text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed`
- **Microcopy / Label**: `text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500`
- **Code / Monospace**: `font-mono text-xs sm:text-sm text-slate-800 dark:text-slate-200`

---

## 7. Theme & Dark Mode Architecture

Menerapkan pola **Zero-Flash Anti-FOUC (Flash of Unstyled Content)** untuk transisi tema yang instan tanpa kedipan warna saat browser memuat halaman.

### 7.1. Anti-FOUC Inisiasi di `<head>`
```html
<script>
    (function() {
        try {
            const savedTheme = localStorage.getItem('theme') || 'light';
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = savedTheme === 'dark' || (savedTheme === 'system' && systemDark);
            
            if (isDark) {
                document.documentElement.classList.add('dark');
                document.documentElement.style.colorScheme = 'dark';
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.colorScheme = 'light';
            }
        } catch (e) {}
    })();
</script>
```

### 7.2. CSS Dark Variant & Custom Scrollbar
```css
/* resources/css/app.css */
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../views/**/*.blade.php';

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
    --font-mono: 'JetBrains Mono', 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    --breakpoint-xs: 360px;
    --breakpoint-sm: 640px;
    --breakpoint-lg: 1024px;
    --breakpoint-2xl: 1920px;
}

@custom-variant dark (&:where(.dark, .dark *));

/* Smooth Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(148, 163, 184, 0.4);
    border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: rgba(100, 116, 139, 0.6);
}
```

### 7.3. Alpine.js Theme Switcher Component
```html
<div x-data="{
    isDark: document.documentElement.classList.contains('dark'),
    toggleTheme() {
        this.isDark = !this.isDark;
        const newTheme = this.isDark ? 'dark' : 'light';
        localStorage.setItem('theme', newTheme);
        if (this.isDark) {
            document.documentElement.classList.add('dark');
            document.documentElement.style.colorScheme = 'dark';
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.style.colorScheme = 'light';
        }
    }
}">
    <button @click="toggleTheme()" 
            type="button"
            class="flex h-10 w-10 sm:h-9 sm:w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-colors hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
            aria-label="Toggle dark mode">
        <template x-if="!isDark">
            <x-heroicon-o-moon class="w-5 h-5 text-slate-600" />
        </template>
        <template x-if="isDark">
            <x-heroicon-o-sun class="w-5 h-5 text-amber-400" />
        </template>
    </button>
</div>
```

---

## 8. Core Component Library Specification (Mobile-First)

Komponen dirancang fleksibel dari ukuran mobile 360px hingga layar 1920px.

### 8.1. Card Component (`<x-card>`)
```blade
{{-- resources/views/components/card.blade.php --}}
@props([
    'title' => null,
    'subtitle' => null,
    'variant' => 'default', // 'default', 'flat', 'accent'
    'actions' => null,
    'footer' => null,
    'headerClass' => '',
    'bodyClass' => '',
    'footerClass' => '',
])

@php
    $variants = [
        'default' => 'bg-white border border-slate-200/80 shadow-xs rounded-xl sm:rounded-2xl dark:bg-slate-900 dark:border-slate-800 dark:text-slate-100',
        'flat' => 'bg-white border border-slate-200 rounded-xl sm:rounded-2xl dark:bg-slate-900 dark:border-slate-800 dark:text-slate-100',
        'accent' => 'bg-gradient-to-br from-blue-900 to-blue-700 text-white rounded-xl sm:rounded-2xl shadow-md',
    ];
    $cardClasses = $variants[$variant] ?? $variants['default'];
@endphp

<div {{ $attributes->merge(['class' => "overflow-hidden transition-all duration-200 {$cardClasses}"]) }}>
    @if ($title || isset($header) || $actions)
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 px-4 py-3 sm:px-6 sm:py-4.5 dark:border-slate-800 {{ $headerClass }}">
            @if (isset($header))
                {{ $header }}
            @else
                <div>
                    @if ($title)
                        <h3 class="text-sm sm:text-base font-bold leading-snug text-slate-900 dark:text-white">{{ $title }}</h3>
                    @endif
                    @if ($subtitle)
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                    @endif
                </div>
            @endif

            @if ($actions || isset($actionsSlot))
                <div class="flex items-center gap-2 self-end sm:self-auto">
                    {{ $actions ?? $actionsSlot }}
                </div>
            @endif
        </div>
    @endif

    <div class="p-4 sm:p-6 {{ $bodyClass }}">
        {{ $slot }}
    </div>

    @if ($footer || isset($footerSlot))
        <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-6 sm:py-4 dark:border-slate-800 dark:bg-slate-900/60 {{ $footerClass }}">
            {{ $footer ?? $footerSlot }}
        </div>
    @endif
</div>
```

---

### 8.2. Button Component (`<x-button>`)
```blade
{{-- resources/views/components/button.blade.php --}}
@props(['type' => 'submit', 'variant' => 'primary', 'size' => 'md', 'href' => null])

@php
    $baseClasses = 'inline-flex items-center justify-center font-semibold tracking-wide transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed gap-2 active:scale-[0.98] select-none';
    
    $sizeClasses = [
        'sm' => 'min-h-[36px] rounded-md px-3 py-1.5 text-xs',
        'md' => 'min-h-[44px] sm:min-h-[40px] rounded-lg px-4 py-2 text-xs sm:text-sm uppercase tracking-wider',
        'lg' => 'min-h-[48px] rounded-xl px-5 py-2.5 text-sm sm:text-base',
    ][$size] ?? 'min-h-[44px] sm:min-h-[40px] rounded-lg px-4 py-2 text-xs sm:text-sm';

    $variants = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:bg-blue-700 focus:ring-blue-500 active:bg-blue-900 dark:bg-blue-600 dark:hover:bg-blue-500',
        'secondary' => 'bg-white border border-slate-300 text-slate-700 shadow-xs hover:bg-slate-50 focus:ring-blue-500 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700 focus:bg-rose-700 focus:ring-rose-500 active:bg-rose-900 dark:bg-rose-600 dark:hover:bg-rose-500',
        'ghost' => 'bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:ring-slate-400 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
    ];

    $classes = "{$baseClasses} {$sizeClasses} " . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
```

---

### 8.3. Badge Component (`<x-badge>`)
```blade
{{-- resources/views/components/badge.blade.php --}}
@props([
    'variant' => 'default', // primary, success, warning, danger, info, gray
    'size' => 'md',        // sm, md, lg
    'dot' => false,
])

@php
    $variantMap = [
        'primary' => 'bg-blue-50 text-blue-700 border-blue-200/60 dark:bg-blue-950/60 dark:text-blue-400 dark:border-blue-800/60',
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200/60 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border-emerald-800/60',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200/60 dark:bg-amber-950/60 dark:text-amber-400 dark:border-amber-800/60',
        'danger'  => 'bg-rose-50 text-rose-700 border-rose-200/60 dark:bg-rose-950/60 dark:text-rose-400 dark:border-rose-800/60',
        'info'    => 'bg-blue-50 text-blue-700 border-blue-200/60 dark:bg-blue-950/60 dark:text-blue-400 dark:border-blue-800/60',
        'gray'    => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        'default' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
    ];

    $dotMap = [
        'primary' => 'bg-blue-500',
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger'  => 'bg-rose-500',
        'info'    => 'bg-blue-500',
        'gray'    => 'bg-slate-400',
        'default' => 'bg-slate-400',
    ];

    $sizeMap = [
        'sm' => 'px-2 py-0.5 text-[10px]',
        'md' => 'px-2.5 py-1 text-xs',
        'lg' => 'px-3 py-1.5 text-sm',
    ];

    $classes = $variantMap[strtolower($variant)] ?? $variantMap['default'];
    $sizeClasses = $sizeMap[$size] ?? $sizeMap['md'];
    $dotColor = $dotMap[strtolower($variant)] ?? $dotMap['default'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 font-medium rounded-full border {$classes} {$sizeClasses}"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotColor }}"></span>
    @endif
    {{ $slot }}
</span>
```

---

### 8.4. Form Elements (`<x-input>`, `<x-select>`, `<x-textarea>`)
```blade
{{-- resources/views/components/input.blade.php --}}
@props(['disabled' => false, 'required' => false, 'name' => null])

<input {{ $disabled ? 'disabled' : '' }} 
    @if($name) name="{{ $name }}" @endif
    {!! $attributes->merge([
        'class' => 'block w-full appearance-none rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 sm:py-2 text-sm text-slate-900 placeholder-slate-400 transition-colors duration-200 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 ' . ($disabled ? 'bg-slate-100 dark:bg-slate-900 cursor-not-allowed' : '')
    ]) !!} 
    @if ($required) required @endif>

@if ($name)
    @error($name)
        <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror
@endif
```

---

### 8.5. Metric / Stat Card Component (Responsive 1-to-4 Columns)
```blade
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 sm:gap-6">
    <x-card class="group transition-all hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-3 sm:mb-4 flex items-center justify-between">
            <div class="flex h-11 w-11 sm:h-12 sm:w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition-transform group-hover:scale-110 dark:bg-blue-950/60 dark:text-blue-400">
                <x-heroicon-o-academic-cap class="w-5 h-5 sm:w-6 sm:h-6" />
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Live</span>
        </div>
        <div>
            <span class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-white">88.5%</span>
            <p class="mt-0.5 sm:mt-1 text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400">Mastery Index</p>
        </div>
    </x-card>
</div>
```

---

### 8.6. Modal Dialog Component (Mobile Bottom-Sheet & Centered Dialog)
```blade
{{-- resources/views/components/modal.blade.php --}}
@props([
    'name' => 'modal-' . uniqid(),
    'title' => null,
    'maxWidth' => '2xl', // sm, md, lg, xl, 2xl, 4xl
])

@php
    $maxWidthClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '4xl' => 'sm:max-w-4xl',
    ][$maxWidth] ?? 'sm:max-w-2xl';
@endphp

<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto px-3 py-4 sm:px-0 flex items-end sm:items-center justify-center">

    {{-- Backdrop --}}
    <div x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs transition-opacity"></div>

    {{-- Modal Box --}}
    <div x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
         class="relative w-full overflow-hidden rounded-t-2xl sm:rounded-2xl bg-white border border-slate-100 shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 max-h-[90vh] flex flex-col {{ $maxWidthClass }}">

        @if ($title || isset($header))
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3.5 sm:px-6 sm:py-4 dark:border-slate-800">
                <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">{{ $title ?? $header }}</h3>
                <button @click="open = false" type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors dark:hover:bg-slate-800 dark:hover:text-slate-200">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                </button>
            </div>
        @endif

        <div class="p-4 sm:p-6 overflow-y-auto">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="flex items-center justify-end gap-2 sm:gap-3 border-t border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-6 sm:py-4 dark:border-slate-800 dark:bg-slate-900/60">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
```

---

### 8.7. Global Toast Notification (`<x-toast>`)
```blade
{{-- resources/views/components/toast.blade.php --}}
<div x-data="{
    show: false,
    message: '',
    type: 'success',
    init() {
        this.$watch('show', value => {
            if (value) {
                setTimeout(() => { this.show = false; }, 3500);
            }
        });
    }
}"
@toast.window="
    show = true;
    message = $event.detail.message;
    type = $event.detail.type || 'success';
"
x-show="show"
x-transition:enter="transform ease-out duration-300 transition"
x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
x-transition:leave="transition ease-in duration-150"
x-transition:leave-start="opacity-100"
x-transition:leave-end="opacity-0"
x-cloak
class="pointer-events-auto fixed bottom-4 left-4 right-4 sm:left-auto sm:right-5 sm:bottom-5 z-50 w-auto sm:w-full sm:max-w-sm overflow-hidden rounded-xl border p-3.5 sm:p-4 shadow-xl backdrop-blur-md"
:class="{
    'bg-white/95 border-emerald-200 text-emerald-900 dark:bg-slate-900/95 dark:border-emerald-800 dark:text-emerald-200': type === 'success',
    'bg-white/95 border-rose-200 text-rose-900 dark:bg-slate-900/95 dark:border-rose-800 dark:text-rose-200': type === 'error',
    'bg-white/95 border-amber-200 text-amber-900 dark:bg-slate-900/95 dark:border-amber-800 dark:text-amber-200': type === 'warning',
    'bg-white/95 border-blue-200 text-blue-900 dark:bg-slate-900/95 dark:border-blue-800 dark:text-blue-200': type === 'info'
}">
    <div class="flex items-start gap-3">
        <span class="shrink-0">
            <template x-if="type === 'success'">
                <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-500" />
            </template>
            <template x-if="type === 'error'">
                <x-heroicon-s-x-circle class="w-5 h-5 text-rose-500" />
            </template>
            <template x-if="type === 'warning'">
                <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-amber-500" />
            </template>
            <template x-if="type === 'info'">
                <x-heroicon-s-information-circle class="w-5 h-5 text-blue-500" />
            </template>
        </span>
        <div class="flex-1 pt-0.5 text-xs font-semibold" x-text="message"></div>
        <button @click="show = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
            <x-heroicon-o-x-mark class="w-4 h-4" />
        </button>
    </div>
</div>
```

---

## 9. Layout Architecture (Mobile to Ultra-Wide)

### 9.1. Layout Matrix Across Viewports

```
+---------------------------------------------------------------------------------------------------------+
|                                    ADAPTASI LAYOUT PER VIEWPORT                                         |
|                                                                                                         |
| 1. [360px - Mobile Base]:                                                                               |
|    - Sticky Topbar (Logo + Hamburger Drawer + Dark Toggle)                                              |
|    - Single Column Content Stream (`p-4 space-y-4`)                                                     |
|    - Sticky Bottom Action Bar untuk tombol aksi krusial (Submit / Run Code)                             |
|                                                                                                         |
| 2. [640px - Tablet / sm]:                                                                               |
|    - 2-Column Grid untuk kartu modul dan metrik (`grid-cols-2 gap-4`)                                   |
|    - Drawer Menu dengan area sentuh lebih luas                                                          |
|                                                                                                         |
| 3. [1024px - Desktop / lg]:                                                                             |
|    - Sticky Fixed Sidebar kiri (250px) + Main Content kanan (`flex min-h-screen`)                       |
|    - Split-Screen IDE: Editor kiri (55%) + Sandbox Output/Console kanan (45%)                           |
|                                                                                                         |
| 4. [1920px - Ultra-Wide / 2xl]:                                                                         |
|    - Kontainer dibatasi secara terpusat (`max-w-7xl` atau `max-w-[1720px] mx-auto`)                     |
|    - 3-Pane Layout pada IDE: Modul Panduan (25%) + Code Editor (45%) + Terminal/Output (30%)            |
+---------------------------------------------------------------------------------------------------------+
```

### 9.2. Data Table Standard (Responsive Table Wrapper)
```html
<div class="overflow-hidden rounded-xl sm:rounded-2xl border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left text-xs sm:text-sm text-slate-900 dark:text-slate-100 min-w-[540px]">
            <thead class="border-b border-slate-100 bg-slate-50/75 text-[10px] sm:text-xs font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3 sm:px-6 sm:py-3.5">Judul Unit</th>
                    <th class="px-4 py-3 sm:px-6 sm:py-3.5">Tipe Aktivitas</th>
                    <th class="px-4 py-3 sm:px-6 sm:py-3.5">Status</th>
                    <th class="px-4 py-3 sm:px-6 sm:py-3.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                    <td class="px-4 py-3.5 sm:px-6 sm:py-4 font-semibold">Pengenalan Array & Pointer</td>
                    <td class="px-4 py-3.5 sm:px-6 sm:py-4 text-slate-500 dark:text-slate-400">Coding Practice</td>
                    <td class="px-4 py-3.5 sm:px-6 sm:py-4">
                        <x-badge variant="success" dot>Mastered</x-badge>
                    </td>
                    <td class="px-4 py-3.5 sm:px-6 sm:py-4 text-right">
                        <x-button variant="secondary" size="sm">Detail</x-button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

---

## 10. Panduan Penggunaan Heroicons di Blade

Gunakan prefix resmi Blade Heroicons:
- `<x-heroicon-o-*>` untuk gaya **Outline (24x24, 1.5px stroke)** — *Cocok untuk navigasi, aksi sekunder, tombol.*
- `<x-heroicon-s-*>` untuk gaya **Solid (24x24 filled)** — *Cocok untuk status aktif, badge, notifikasi toast.*
- `<x-heroicon-m-*>` untuk gaya **Mini (20x20 filled)** — *Cocok untuk indikator kecil di dalam form/input.*

---

## 11. Standar UX & Aturan Interaksi Mobile-First

1. **Touch Target Size**: Tombol dan elemen interaktif pada mobile (`< 640px`) memiliki tinggi minimum 44px (`min-h-[44px]`).
2. **Anti-Flicker (`x-cloak`)**: Selalu gunakan `[x-cloak] { display: none !important; }` pada elemen Alpine yang dimuat dinamis.
3. **State Transition**: Semua tombol dan kartu interaktif wajib memiliki `transition-all duration-150` atau `duration-200`.
4. **Zero Font Request**: Ikon SVG di-render inline di server (0 external network call).
5. **Empty States**: Menampilkan ikon `<x-heroicon-o-inbox class="w-10 h-10 sm:w-12 sm:h-12 text-slate-400" />` dengan pesan yang jelas dan satu tombol *Call-to-Action (CTA)*.
6. **Isolated Code Execution Notice**: Antarmuka eksekusi kode student harus menampilkan status sandbox secara transparan (mis. "Executing in isolated sandbox").
7. **Aksesibilitas Keyboard**: Pastikan semua elemen tombol dan input memiliki `focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-hidden`.

---

## 12. Pedoman Pengembangan & Penggunaan RTK (Rust Token Killer)

Sebelum membuat atau mengedit file antarmuka di dalam folder `resources/views/`, terapkan alur kerja berikut:

### 12.1. Pengecekan Ketersediaan RTK
Periksa apakah binary `rtk` terpasang di environment sistem:
```bash
which rtk || rtk --version
```

### 12.2. Aturan Penggunaan:
1. **Jika `rtk` Tersedia**:
   - Gunakan `rtk` untuk operasi CLI dan inspeksi (misal: `rtk git status`, `rtk git diff`, `rtk gain`, atau `rtk proxy <cmd>`).
   - Operasi ini memangkas konsumsi token developer hingga 60–90% selama pembuatan dan perbaikan komponen UI.
2. **Jika `rtk` Tidak Tersedia**:
   - Lanjutkan proses pembuatan atau penyuntingan file Blade dan styling CSS secara normal menggunakan tool bawaan tanpa mewajibkan instalasi tambahan.

---

## 13. Strategi & Standar Pengujian Antarmuka (Frontend Testing Strategy)

Platform BisaBelajar menerapkan strategi pengujian dua lapis (*two-tier testing strategy*) untuk menjamin keandalan antarmuka visual dan kelancaran interaksi pengguna:

```
+-----------------------------------------------------------------------------------------------+
|                            STRATEGI DUA LAPIS PENGUJIAN FRONTEND                              |
|                                                                                               |
|  [LEVEL 1: BLADE COMPONENT UNIT TEST]       [LEVEL 2: LARAVEL DUSK E2E BROWSER TEST]          |
|  - Eksekusi Server (PHPUnit / Feature)      - Eksekusi Browser Nyata (Headless Chrome)        |
|  - Uji Render Tag, Class Tailwind, Props    - Uji Interaktivitas Alpine.js, Klik, Form        |
|  - Verifikasi Aturan 60-30-10 & Touch Target- Uji Viewport Mobile (360px) & Screenshot Visual  |
+-----------------------------------------------------------------------------------------------+
```

### 13.1. Level 1: Blade Component Unit Test (`tests/Feature/BladeComponentTest.php`)
Pengujian unit ringan di sisi server untuk memvalidasi output HTML dan atribut Tailwind dari komponen UI Atom/Molecule secara terisolasi.

- **Cakupan Uji:**
  1. *Prop & Varian:* Memastikan prop `variant="primary|secondary|danger"` merender warna yang tepat sesuai palet 60-30-10.
  2. *Mobile Touch Target:* Memastikan kelas `min-h-[44px]` teraplikasikan pada ukuran tombol mobile.
  3. *Error & Required State:* Memastikan error validasi dan indikator wajib `*` terpasang tanpa menimbulkan *undefined variable error*.
- **Cara Menjalankan:**
  ```bash
  php artisan test --filter=BladeComponentTest
  ```

### 13.2. Level 2: Real Browser & E2E Testing (`tests/Browser/`)
Pengujian antarmuka menyeluruh di atas browser **Google Chrome sungguhan (via ChromeDriver)** dalam mode *headless*.

- **Cakupan Uji:**
  1. *Alpine.js Interactivity:* Membuka dan menutup `<x-modal>`, memvalidasi animasi transisi (`x-transition`), pengujian penutupan via tombol keyboard `ESC`, dan auto-dismiss `<x-toast>` notification.
  2. *Dark/Light Theme Switching:* Memverifikasi toggle dark mode menempelkan kelas `.dark` pada tag `<html>` dan menyimpan preferensi ke `localStorage`.
  3. *Mobile-First Viewport Simulation:* Menguji tata letak pada ukuran layar nyata:
     - **360px:** `$browser->resize(360, 800)` (Single column, drawer navigation).
     - **640px:** `$browser->resize(640, 900)` (2-Column cards).
     - **1024px:** `$browser->resize(1024, 768)` (Persistent sidebar & split IDE).
     - **1920px:** `$browser->resize(1920, 1080)` (Ultra-wide boundary container).
  4. *Visual Regression & Screenshots:* Jika terjadi kegagalan assertion UI, Dusk otomatis mengambil gambar layar ke `tests/Browser/screenshots/`.
- **Cara Menjalankan:**
  ```bash
  php artisan dusk
  ```
