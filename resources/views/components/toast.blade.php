@php
    $sessionMessage = session('status') ?? session('success') ?? session('error') ?? session('warning') ?? session('info') ?? null;
    $sessionType = session('error') ? 'error' : (session('warning') ? 'warning' : (session('info') ? 'info' : 'success'));
@endphp

<div x-data="{
    show: {{ $sessionMessage ? 'true' : 'false' }},
    message: @js($sessionMessage ?? ''),
    type: @js($sessionType),
    init() {
        if (this.show) {
            setTimeout(() => { this.show = false; }, 4000);
        }
        this.$watch('show', value => {
            if (value) {
                setTimeout(() => { this.show = false; }, 4000);
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
x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
x-transition:leave="transition ease-in duration-200"
x-transition:leave-start="opacity-100 scale-100"
x-transition:leave-end="opacity-0 scale-95"
x-cloak
class="pointer-events-auto fixed top-4 right-4 sm:top-5 sm:right-5 z-50 w-auto sm:w-full sm:max-w-sm overflow-hidden rounded-xl border p-3.5 sm:p-4 shadow-2xl backdrop-blur-md"
:class="{
    'bg-white/95 border-emerald-200 text-emerald-950 dark:bg-slate-900/95 dark:border-emerald-800 dark:text-emerald-100': type === 'success',
    'bg-white/95 border-rose-200 text-rose-950 dark:bg-slate-900/95 dark:border-rose-800 dark:text-rose-100': type === 'error',
    'bg-white/95 border-amber-200 text-amber-950 dark:bg-slate-900/95 dark:border-amber-800 dark:text-amber-100': type === 'warning',
    'bg-white/95 border-blue-200 text-blue-950 dark:bg-slate-900/95 dark:border-blue-800 dark:text-blue-100': type === 'info'
}">
    <div class="flex items-start gap-3">
        <span class="shrink-0 pt-0.5">
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
        <div class="flex-1 text-xs sm:text-sm font-semibold leading-snug" x-text="message"></div>
        <button @click="show = false" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-0.5 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-label="Close notification">
            <x-heroicon-o-x-mark class="w-4 h-4" />
        </button>
    </div>
</div>
