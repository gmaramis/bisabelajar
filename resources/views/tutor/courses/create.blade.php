@extends('layouts.app')

@section('title', 'Create course — '.config('app.name'))

@section('content')
<div class="space-y-8 max-w-3xl mx-auto">
    <x-page-header 
        title="Create course" 
        description="Mulai buat kursus baru. Struktur kursus fleksibel tanpa batas pertemuan kaku."
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('tutor.workspace') }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Tutor workspace</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <span class="text-slate-400 dark:text-slate-500">Create course</span>
        </x-slot>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('tutor.courses.store') }}" class="space-y-6">
            @csrf
            @include('tutor.courses.partials.form')

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <x-button variant="outline" href="{{ route('tutor.workspace') }}">Batal</x-button>
                <x-button variant="primary" type="submit" icon="plus">Create course</x-button>
            </div>
        </form>
    </x-card>
</div>
@endsection
