@extends('layouts.auth')

@section('title', 'Masuk')
@section('subtitle', 'Masuk ke Akun')

@section('content')
<div class="w-full flex flex-col lg:flex-row items-center justify-center gap-8 lg:gap-16">
    
    <x-card class="w-full max-w-md shadow-xs sm:shadow-md">
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                Masuk ke Akun
            </h1>
            <p class="mt-1 text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                Silakan masuk dengan kredensial akun Anda.
            </p>
        </div>

        @if ($errors->any())
            <x-alert variant="danger" class="mb-5">
                {{ $errors->first() }}
            </x-alert>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <x-form-group label="Alamat Email" name="email" required>
                <x-input
                    id="email"
                    name="email"
                    type="email"
                    :value="old('email')"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="nama@email.com"
                    icon="envelope"
                />
            </x-form-group>

            <x-form-group label="Kata Sandi" name="password" required>
                <x-input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    togglePassword
                    icon="lock-closed"
                />
            </x-form-group>

            <div class="flex items-center justify-between pt-1">
                <x-checkbox 
                    name="remember" 
                    label="Ingat saya" 
                />
            </div>

            <div class="pt-2">
                <x-button 
                    type="submit" 
                    variant="primary" 
                    class="w-full"
                >
                    Masuk
                </x-button>
            </div>
        </form>

        <x-back-link />
    </x-card>

    <div class="hidden lg:flex flex-col max-w-md space-y-4">
        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white leading-tight">
            Pembelajaran Vokasi Berbasis AI — AI-VET Pilot
        </h2>

        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 leading-relaxed font-normal">
            Akses kurikulum modular adaptif, latihan coding langsung di isolated sandbox, dan pendampingan cerdas Socratic NEXUS tanpa batas pertemuan kaku.
        </p>

        <div class="pt-2">
            <x-button 
                href="{{ url('/#about') }}" 
                variant="outline" 
                size="sm"
                icon="arrow-right"
                iconPosition="right"
            >
                Pelajari Lebih Lanjut
            </x-button>
        </div>
    </div>

</div>
@endsection
