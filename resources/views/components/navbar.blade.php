@props([
    'title' => 'BisaBelajar',
])

<header 
    x-data="{ mobileMenuOpen: false }"
    x-init="$watch('mobileMenuOpen', value => { document.body.style.overflow = value ? 'hidden' : 'auto'; })"
    {{ $attributes->merge(['class' => 'sticky top-0 z-50 w-full border-b border-slate-200/80 bg-white/95 backdrop-blur-md dark:border-slate-800 dark:bg-slate-950/95 transition-all duration-200 shadow-2xs']) }}
>
    <div class="mx-auto flex h-14 sm:h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 sm:gap-6 py-2 h-full">
            @if (isset($drawerButton))
                {{ $drawerButton }}
            @endif

            <a href="{{ url('/') }}" class="font-black text-lg sm:text-xl tracking-tight text-slate-900 dark:text-white shrink-0">
                {{ $title }}
            </a>

            @if (isset($navLinks))
                <nav class="hidden md:flex items-center gap-1 ml-3 sm:ml-6 h-full">
                    {{ $navLinks }}
                </nav>
            @endif
        </div>

        <div class="flex items-center h-full shrink-0">
            <div class="flex items-center gap-1.5 sm:gap-3 px-2 sm:px-4">
                <x-theme-toggle />

                @if (isset($rightActions))
                    {{ $rightActions }}
                @endif
            </div>

            @auth
                <div class="flex items-center h-full pl-2 pr-4 sm:pr-6">
                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button type="button" class="flex items-center gap-2 rounded-lg p-1.5 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 transition-colors">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white font-bold uppercase text-xs">
                                    {{ substr(auth()->user()->name ?? 'U', 0, 2) }}
                                </div>
                                <span class="hidden sm:inline-block max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                                <x-heroicon-m-chevron-down class="w-4 h-4 text-slate-400" />
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-800">
                                <p class="text-xs font-semibold text-slate-900 dark:text-white truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
                            </div>

                            @if (auth()->user()->isStudent())
                                <x-dropdown-link :href="route('student.dashboard')" icon="squares-2x2">
                                    Dashboard Belajar
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('student.courses')" icon="book-open">
                                    Kursus Saya
                                </x-dropdown-link>
                            @endif

                            @if (auth()->user()->isTutor())
                                <x-dropdown-link :href="route('tutor.workspace')" icon="briefcase">
                                    Tutor Workspace
                                </x-dropdown-link>
                            @endif

                            <x-dropdown-link :href="route('profile.show')" icon="user">
                                Profil Saya
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex items-center gap-2.5 w-full px-4 py-2 text-start text-xs sm:text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition duration-150">
                                    <x-heroicon-o-arrow-left-on-rectangle class="w-4.5 h-4.5 shrink-0 opacity-70" />
                                    <span>Keluar</span>
                                </button>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            @else
                <div class="hidden md:flex items-center h-full">
                    <span class="h-5 w-px bg-slate-200 dark:bg-slate-800 my-auto"></span>

                    <a href="{{ route('login') }}" class="flex items-center h-full px-4 sm:px-6 text-xs sm:text-sm font-semibold text-slate-700 dark:text-slate-200 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                        Login
                    </a>

                    <a 
                        href="{{ route('login') }}" 
                        class="flex h-full items-center justify-center bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold text-xs sm:text-sm px-6 sm:px-8 transition-colors select-none"
                    >
                        <span>Sign Up Free</span>
                    </a>
                </div>

                <div class="flex md:hidden items-center h-full pr-2.5 sm:pr-4 gap-1">
                    <a 
                        href="{{ route('login') }}" 
                        class="px-2.5 py-1 text-xs font-bold text-slate-700 dark:text-slate-200 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                    >
                        Login
                    </a>

                    <button 
                        type="button" 
                        @click="mobileMenuOpen = true" 
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800 transition-colors focus:outline-none"
                        aria-label="Open Navigation Menu"
                    >
                        <x-heroicon-o-bars-3 class="w-6 h-6" />
                    </button>
                </div>
            @endauth
        </div>
    </div>

    <div 
        x-show="mobileMenuOpen"
        x-transition:enter="transform transition ease-out duration-250"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed inset-0 z-100 flex h-screen w-screen flex-col overflow-hidden bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100"
        style="display: none; height: 100vh; height: 100dvh;"
    >
        <div class="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-4">
            <a href="{{ url('/') }}" @click="mobileMenuOpen = false" class="font-bold text-base tracking-tight text-slate-900 dark:text-white">
                {{ $title }}
            </a>
            
            <button 
                type="button" 
                @click="mobileMenuOpen = false"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white transition-colors"
                aria-label="Close menu"
            >
                <x-heroicon-o-x-mark class="w-5 h-5" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-2.5 custom-scrollbar">
            <a 
                href="#about" 
                @click="mobileMenuOpen = false"
                class="flex items-center justify-between rounded-xl p-3.5 text-sm font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-900 border border-slate-100 dark:border-slate-800/80 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                        <x-heroicon-o-information-circle class="w-4 h-4" />
                    </div>
                    <span>Tentang Platform</span>
                </div>
                <span class="text-xs text-slate-400 font-bold">›</span>
            </a>

            <a 
                href="#why-choose" 
                @click="mobileMenuOpen = false"
                class="flex items-center justify-between rounded-xl p-3.5 text-sm font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-900 border border-slate-100 dark:border-slate-800/80 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                        <x-heroicon-o-sparkles class="w-4 h-4" />
                    </div>
                    <span>Mengapa BisaBelajar?</span>
                </div>
                <span class="text-xs text-slate-400 font-bold">›</span>
            </a>

            <a 
                href="{{ route('login') }}" 
                @click="mobileMenuOpen = false"
                class="flex items-center justify-between rounded-xl p-3.5 text-sm font-semibold text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-900 border border-slate-100 dark:border-slate-800/80 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                        <x-heroicon-o-command-line class="w-4 h-4" />
                    </div>
                    <span>Interactive Sandbox</span>
                </div>
                <span class="text-xs text-slate-400 font-bold">›</span>
            </a>
        </div>
    </div>
</header>
