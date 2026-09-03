@props([
    'hintUrl',
    'attemptCount' => 1,
])

<div
    x-data="{
        open: false,
        loading: false,
        hint: null,
        responseType: null,
        error: null,
        attemptCount: {{ (int) $attemptCount }},

        async requestHint(errorMessage, testLabel) {
            this.open = true;
            this.loading = true;
            this.hint = null;
            this.error = null;

            try {
                const resp = await fetch('{{ $hintUrl }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        error_message: errorMessage ?? null,
                        test_case_label: testLabel ?? null,
                        attempt_count: this.attemptCount,
                    }),
                });

                const data = await resp.json();

                if (resp.ok) {
                    this.hint = data.hint;
                    this.responseType = data.response_type;
                } else if (resp.status === 503) {
                    this.hint = data.hint ?? 'NEXUS sedang tidak tersedia. Coba periksa dokumentasi atau tanyakan ke tutor.';
                    this.responseType = 'fallback';
                } else {
                    this.error = data.message ?? 'Gagal mendapatkan petunjuk. Coba lagi.';
                }
            } catch (e) {
                this.error = 'Koneksi gagal. Periksa jaringan Anda dan coba lagi.';
            } finally {
                this.loading = false;
            }
        },

        labelFor(type) {
            const labels = {
                clarifying_question: 'Pertanyaan Klarifikasi',
                concept_check: 'Cek Konsep',
                guided_question: 'Pertanyaan Terpandu',
                reflection_question: 'Refleksi',
                next_step_hint: 'Petunjuk Langkah',
                fallback: 'Mode Offline',
            };
            return labels[type] ?? 'NEXUS';
        }
    }"
    x-on:nexus-hint.window="requestHint($event.detail.errorMessage, $event.detail.testLabel)"
    id="nexus-hint-panel"
>
    <div x-show="!open" class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <x-heroicon-o-sparkles class="w-5 h-5 text-sky-600 dark:text-sky-400" />
            <span class="text-xs sm:text-sm font-semibold text-slate-800 dark:text-slate-200">Perlu bimbingan untuk menyelesaikan soal ini?</span>
        </div>
        <x-button
            type="button"
            variant="secondary"
            size="sm"
            icon="light-bulb"
            @click="requestHint(window.__lastErrorMessage ?? null, window.__lastTestLabel ?? null)"
        >
            Minta Petunjuk NEXUS
        </x-button>
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="rounded-xl border border-sky-200/80 bg-sky-50/50 dark:border-sky-900/50 dark:bg-sky-950/20 overflow-hidden shadow-xs"
    >
        <div class="flex items-center justify-between px-4 py-3 border-b border-sky-100 dark:border-sky-900/40">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300">
                    <x-heroicon-o-sparkles class="w-4 h-4 text-sky-600 dark:text-sky-400" />
                    <span>NEXUS Tutor</span>
                </span>
                <x-badge variant="info" size="sm" x-show="responseType" x-text="labelFor(responseType)"></x-badge>
            </div>
            <button
                type="button"
                @click="open = false; hint = null; error = null;"
                class="rounded-lg p-1 text-slate-400 hover:bg-sky-100 hover:text-slate-600 transition-colors dark:hover:bg-slate-800 dark:hover:text-slate-200"
                aria-label="Tutup panel NEXUS"
            >
                <x-heroicon-o-x-mark class="w-4 h-4" />
            </button>
        </div>

        <div class="px-4 pt-3 pb-0">
            <p class="text-[11px] text-slate-500 dark:text-slate-400 italic">
                NEXUS membimbing Anda berpikir secara mandiri, bukan memberikan solusi langsung.
            </p>
        </div>

        <div class="px-4 py-3">
            <div x-show="loading" class="flex items-center gap-3 py-2">
                <x-heroicon-o-arrow-path class="animate-spin w-4 h-4 text-sky-600 dark:text-sky-400" />
                <span class="text-xs sm:text-sm font-medium text-sky-700 dark:text-sky-300">NEXUS sedang merumuskan petunjuk untuk Anda…</span>
            </div>

            <div x-show="hint && !loading" class="rounded-lg border border-sky-200/80 bg-white p-3.5 shadow-2xs dark:border-sky-800/60 dark:bg-slate-900">
                <p
                    x-text="hint"
                    class="text-xs sm:text-sm text-slate-800 dark:text-slate-100 leading-relaxed font-sans"
                ></p>
            </div>

            <p
                x-show="error && !loading"
                x-text="error"
                class="text-xs sm:text-sm text-rose-600 dark:text-rose-400"
            ></p>
        </div>

        <div x-show="hint && !loading" class="px-4 pb-3 flex items-center justify-between border-t border-sky-100 dark:border-sky-900/40 pt-2.5">
            <p class="text-[10px] text-slate-400 dark:text-slate-500">
                AI bersifat advisory. Keputusan akhir ada pada Anda dan tutor.
            </p>
            <x-button
                type="button"
                variant="secondary"
                size="sm"
                icon="arrow-path"
                @click="requestHint(window.__lastErrorMessage ?? null, window.__lastTestLabel ?? null)"
            >
                Petunjuk Lain
            </x-button>
        </div>
    </div>
</div>
