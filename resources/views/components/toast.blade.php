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
        <div class="flex-1 text-xs sm:text-sm font-semibold" x-text="message"></div>
        <button @click="show = false" type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" aria-label="Close notification">
            <x-heroicon-o-x-mark class="w-4 h-4" />
        </button>
    </div>
</div>
