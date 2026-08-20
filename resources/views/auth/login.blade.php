@extends('layouts.app')

@section('title', 'Log in — '.config('app.name'))

@section('content')
    <div class="mx-auto max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-1 text-xl font-semibold">Log in</h1>
        <p class="mb-6 text-sm text-slate-600">Sign in to BisaBelajar as a student or tutor.</p>

        @if ($errors->any())
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                >
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                >
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember">
                Remember me
            </label>

            <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                Log in
            </button>
        </form>
    </div>
@endsection
