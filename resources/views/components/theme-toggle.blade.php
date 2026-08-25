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
        dusk="theme-toggle-btn"
        {!! $attributes->merge([
            'class' => 'flex h-10 w-10 sm:h-9 sm:w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition-colors hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 select-none cursor-pointer',
        ]) !!}
        aria-label="Toggle dark mode">
        <span x-show="!isDark" class="flex items-center justify-center">
            <x-heroicon-o-moon class="w-5 h-5 text-slate-600" />
        </span>
        <span x-show="isDark" class="flex items-center justify-center" style="display: none;">
            <x-heroicon-o-sun class="w-5 h-5 text-amber-400" />
        </span>
    </button>
</div>
