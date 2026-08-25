<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'BisaBelajar'))</title>
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
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased selection:bg-blue-600 selection:text-white dark:bg-slate-950 dark:text-slate-100 font-sans transition-colors duration-200 flex flex-col overflow-x-hidden">
    <x-navbar>
        <x-slot name="navLinks">
            @auth
                @if(auth()->user()->isStudent())
                    <a href="{{ route('student.dashboard') }}" class="relative flex items-center h-full px-3.5 text-xs sm:text-sm font-semibold transition-colors {{ request()->routeIs('student.dashboard') ? 'text-slate-900 dark:text-white font-bold after:absolute after:bottom-0 after:left-2 after:right-2 after:h-0.5 after:bg-blue-600' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('student.courses') }}" class="relative flex items-center h-full px-3.5 text-xs sm:text-sm font-semibold transition-colors {{ request()->routeIs('student.courses*') ? 'text-slate-900 dark:text-white font-bold after:absolute after:bottom-0 after:left-2 after:right-2 after:h-0.5 after:bg-blue-600' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                        My Courses
                    </a>
                @elseif(auth()->user()->isTutor())
                    <a href="{{ route('tutor.workspace') }}" class="relative flex items-center h-full px-3.5 text-xs sm:text-sm font-semibold transition-colors {{ request()->routeIs('tutor.*') ? 'text-slate-900 dark:text-white font-bold after:absolute after:bottom-0 after:left-2 after:right-2 after:h-0.5 after:bg-blue-600' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                        Tutor workspace
                    </a>
                @endif
                <a href="{{ route('courses.index') }}" class="relative flex items-center h-full px-3.5 text-xs sm:text-sm font-semibold transition-colors {{ request()->routeIs('courses.*') ? 'text-slate-900 dark:text-white font-bold after:absolute after:bottom-0 after:left-2 after:right-2 after:h-0.5 after:bg-blue-600' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                    Courses
                </a>
            @else
                <a href="{{ route('courses.index') }}" class="relative flex items-center h-full px-3.5 text-xs sm:text-sm font-semibold transition-colors {{ request()->routeIs('courses.*') ? 'text-slate-900 dark:text-white font-bold after:absolute after:bottom-0 after:left-2 after:right-2 after:h-0.5 after:bg-blue-600' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                    Courses
                </a>
            @endauth
        </x-slot>
    </x-navbar>
    <main class="flex-1 mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        @yield('content')
    </main>
    <x-footer />
    <x-toast />
    @stack('scripts')
</body>
</html>
