<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Authentication') — {{ config('app.name', 'BisaBelajar') }}</title>

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
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased selection:bg-blue-600 selection:text-white dark:bg-slate-950 dark:text-slate-100 font-sans transition-colors duration-200 flex flex-col justify-between relative overflow-x-hidden">

    <div 
        class="fixed inset-0 pointer-events-none z-0 hidden md:block bg-right-bottom bg-no-repeat bg-cover opacity-100 dark:opacity-20 transition-opacity duration-300"
        style="background-image: url('{{ asset('images/auth-bg.webp') }}'); background-position: 100% 100%;"
    ></div>

    <x-navbar />

    <main class="relative z-10 mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-6 sm:py-12 flex-1 flex items-center justify-center">
        @yield('content')
    </main>

    <x-footer />

</body>
</html>
