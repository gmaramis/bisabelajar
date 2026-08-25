@extends('layouts.app')

@section('title', 'Add material — '.$learningUnit->title.' — '.config('app.name'))

@section('content')
<div class="space-y-8 max-w-3xl mx-auto">
    <x-page-header 
        title="Add material" 
        description="Course: {{ $course->title }} · Unit: {{ $learningUnit->title }}"
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('tutor.workspace') }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Tutor workspace</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.courses.edit', $course) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[100px] sm:max-w-xs">{{ $course->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.modules.edit', [$course, $module]) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[100px] sm:max-w-xs">{{ $module->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.units.edit', [$course, $module, $learningUnit]) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[100px] sm:max-w-xs">{{ $learningUnit->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <span class="text-slate-400 dark:text-slate-500">Add material</span>
        </x-slot>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('tutor.materials.store', [$course, $module, $learningUnit]) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @if ($errors->any())
                <x-alert variant="danger" class="mb-4">
                    {{ $errors->first() }}
                </x-alert>
            @endif

            <div class="space-y-4">
                <x-form-group label="Title" name="title" required>
                    <x-input id="title" name="title" type="text" value="{{ old('title') }}" required placeholder="e.g. Panduan Lengkap Variabel Python" />
                </x-form-group>

                <x-form-group label="Type" name="type" required>
                    <x-select id="type" name="type">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ strtoupper($type->value) }}</option>
                        @endforeach
                    </x-select>
                </x-form-group>

                <x-form-group label="Rich text" name="content" help="Diperlukan untuk tipe RICH_TEXT.">
                    <x-textarea id="content" name="content" rows="6" placeholder="Isi materi teks...">{{ old('content') }}</x-textarea>
                </x-form-group>

                <x-form-group label="PDF or PowerPoint file" name="file" help="Diperlukan untuk tipe PDF atau POWERPOINT.">
                    <input id="file" name="file" type="file" accept=".pdf,.ppt,.pptx,application/pdf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-950 dark:file:text-blue-300 transition-colors">
                </x-form-group>

                <x-form-group label="External URL" name="external_url" help="Diperlukan untuk tipe EXTERNAL_URL.">
                    <x-input id="external_url" name="external_url" type="url" value="{{ old('external_url') }}" placeholder="https://..." />
                </x-form-group>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                <x-button variant="outline" href="{{ route('tutor.units.edit', [$course, $module, $learningUnit]) }}">Batal</x-button>
                <x-button variant="primary" type="submit" icon="check">Save material</x-button>
            </div>
        </form>
    </x-card>
</div>
@endsection
