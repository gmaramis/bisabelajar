<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name'))</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-4xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ url('/') }}" class="font-semibold">{{ config('app.name') }}</a>
                <nav class="flex flex-wrap items-center gap-3 text-sm">
                    @auth
                        <a href="{{ route('profile.show') }}" class="hover:underline">Profile</a>
                        @if (auth()->user()->isStudent())
                            <a href="{{ route('student.dashboard') }}" class="hover:underline">Dashboard</a>
                            <a href="{{ route('student.courses') }}" class="hover:underline">My Courses</a>
                        @endif
                        @if (auth()->user()->isTutor())
                            <a href="{{ route('tutor.workspace') }}" class="hover:underline">Tutor workspace</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="hover:underline">Log out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="hover:underline">Log in</a>
                    @endauth
                </nav>
            </div>
        </header>
        <main class="mx-auto max-w-4xl px-4 py-8">
            @yield('content')
        </main>
    </body>
</html>
