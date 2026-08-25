@extends('layouts.app')

@section('title', 'Edit learning unit — '.$learningUnit->title.' — '.config('app.name'))

@section('content')
<div class="space-y-8">
    <x-page-header 
        title="Edit learning unit" 
        description="Course: {{ $course->title }} · Module: {{ $module->title }}"
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('tutor.workspace') }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Tutor workspace</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.courses.edit', $course) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[120px] sm:max-w-xs">{{ $course->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.modules.edit', [$course, $module]) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[120px] sm:max-w-xs">{{ $module->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <span class="text-slate-400 dark:text-slate-500 truncate">Edit learning unit</span>
        </x-slot>

        <x-slot name="badge">
            <x-badge variant="{{ $learningUnit->status->value === 'published' ? 'success' : 'warning' }}" dot>
                {{ strtoupper($learningUnit->status->value) }}
            </x-badge>
        </x-slot>

        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('tutor.modules.edit', [$course, $module]) }}" icon="arrow-left">
                Back to module
            </x-button>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">
            <x-card>
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">Unit Details</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Perbarui informasi dan deskripsi unit.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tutor.units.update', [$course, $module, $learningUnit]) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @include('tutor.learning-units.partials.form')

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <x-button variant="primary" type="submit" icon="check">Save unit</x-button>
                    </div>
                </form>
            </x-card>

            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white">Materials</h2>
                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                                {{ $learningUnit->materials->count() }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Opening material is not mastery. Progress is tracked separately.</p>
                    </div>

                    <x-button variant="primary" size="sm" href="{{ route('tutor.materials.create', [$course, $module, $learningUnit]) }}" icon="plus">
                        Add material
                    </x-button>
                </div>

                @if ($learningUnit->materials->isEmpty())
                    <x-card class="py-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 mb-3 border border-blue-100 dark:border-blue-800/60">
                                <x-heroicon-o-document-text class="w-6 h-6" />
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">No materials yet.</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm">Tambahkan materi teks, PDF, presentasi, atau tautan eksternal untuk dipelajari siswa.</p>
                            <div class="mt-4">
                                <x-button variant="primary" size="sm" href="{{ route('tutor.materials.create', [$course, $module, $learningUnit]) }}" icon="plus">Add material</x-button>
                            </div>
                        </div>
                    </x-card>
                @else
                    <div class="space-y-3">
                        @foreach ($learningUnit->materials as $index => $material)
                            <div class="rounded-xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs transition-all duration-200 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-start gap-3.5 min-w-0">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700 font-mono">
                                            {{ sprintf('%02d', $index + 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                                                    {{ $material->title }}
                                                </h3>
                                                <x-badge variant="{{ $material->status->value === 'published' ? 'success' : 'warning' }}" size="sm" dot>
                                                    {{ strtoupper($material->status->value) }}
                                                </x-badge>
                                                <x-badge variant="secondary" size="sm">
                                                    {{ strtoupper($material->type->value) }}
                                                </x-badge>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-1.5 self-end sm:self-center shrink-0">
                                        @if ($index > 0)
                                            @php
                                                $upOrder = $learningUnit->materials->pluck('id')->values()->all();
                                                [$upOrder[$index - 1], $upOrder[$index]] = [$upOrder[$index], $upOrder[$index - 1]];
                                            @endphp
                                            <form method="POST" action="{{ route('tutor.materials.reorder', [$course, $module, $learningUnit]) }}" class="inline">
                                                @csrf
                                                @foreach ($upOrder as $id)
                                                    <input type="hidden" name="order[]" value="{{ $id }}">
                                                @endforeach
                                                <button type="submit" class="p-1.5 text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-white rounded-lg transition-colors" title="Move Up">
                                                    <x-heroicon-o-chevron-up class="w-4 h-4" />
                                                    <span class="sr-only">Up</span>
                                                </button>
                                            </form>
                                        @endif

                                        @if ($material->status->value !== 'published')
                                            <form method="POST" action="{{ route('tutor.materials.publish', [$course, $module, $learningUnit, $material]) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/50 transition-colors">
                                                    Publish
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('tutor.materials.unpublish', [$course, $module, $learningUnit, $material]) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/50 transition-colors">
                                                    Unpublish
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('tutor.materials.destroy', [$course, $module, $learningUnit, $material]) }}" onsubmit="return confirm('Delete this material?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition-colors" title="Delete Material">
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                                <span class="sr-only">Delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @include('tutor.activities.partials.list')
        </div>

        <div class="lg:col-span-1 space-y-6">
            <x-card title="Ringkasan Unit">
                <x-description-list>
                    <x-description-item label="Status">
                        <x-badge variant="{{ $learningUnit->status->value === 'published' ? 'success' : 'warning' }}" dot>
                            {{ strtoupper($learningUnit->status->value) }}
                        </x-badge>
                    </x-description-item>
                    <x-description-item label="Total Materi" :value="$learningUnit->materials->count()" />
                    <x-description-item label="Total Aktivitas" :value="$learningUnit->activities->count()" />
                    <x-description-item label="Induk Modul">
                        <a href="{{ route('tutor.modules.edit', [$course, $module]) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline truncate max-w-[150px]">
                            {{ $module->title }}
                        </a>
                    </x-description-item>
                </x-description-list>
            </x-card>
        </div>
    </div>
</div>
@endsection
