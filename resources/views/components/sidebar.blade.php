@props([
    'title' => 'BisaBelajar',
])

<div x-data="{ sidebarOpen: false }" class="relative">
    <button @click="sidebarOpen = true"
            type="button"
            class="lg:hidden fixed bottom-5 right-5 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-blue-600 text-white shadow-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 select-none"
            aria-label="Open navigation sidebar">
        <x-heroicon-o-bars-3 class="w-6 h-6" />
    </button>

    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs lg:hidden"
         x-cloak></div>

    <div x-show="sidebarOpen"
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-white border-r border-slate-200 dark:border-slate-800 dark:bg-slate-900 shadow-2xl lg:hidden"
         x-cloak>
        <div class="flex h-16 items-center justify-between px-6 border-b border-slate-100 dark:border-slate-800">
            <div class="font-bold text-slate-900 dark:text-white text-base tracking-tight">
                {{ $title }}
            </div>
            <button @click="sidebarOpen = false" type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-4">
            {{ $slot }}
        </div>
    </div>

    <aside class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 lg:border-r lg:border-slate-200/80 lg:bg-white lg:dark:border-slate-800 lg:dark:bg-slate-900 transition-colors z-30">
        <div class="flex h-16 items-center px-6 border-b border-slate-100 dark:border-slate-800">
            <a href="{{ url('/') }}" class="font-bold text-slate-900 dark:text-white text-base tracking-tight">
                {{ $title }}
            </a>
        </div>

        <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-4">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/60">
                {{ $footer }}
            </div>
        @endif
    </aside>
</div>
