@extends('layouts.app')

@section('title', 'Edit module — '.$module->title.' — '.config('app.name'))

@section('content')
<div class="space-y-8">
    <x-page-header 
        title="Edit module" 
        description="Course: {{ $course->title }}"
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('tutor.workspace') }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Tutor workspace</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <a href="{{ route('tutor.courses.edit', $course) }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate max-w-[150px] sm:max-w-xs">{{ $course->title }}</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <span class="text-slate-400 dark:text-slate-500 truncate">Edit module</span>
        </x-slot>

        <x-slot name="badge">
            <x-badge variant="{{ $module->status->value === 'published' ? 'success' : 'warning' }}" dot>
                {{ strtoupper($module->status->value) }}
            </x-badge>
        </x-slot>

        <x-slot name="actions">
            <x-button variant="outline" size="sm" href="{{ route('tutor.courses.edit', $course) }}" icon="arrow-left">
                Back to course
            </x-button>
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">
            <x-card>
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">Detail Modul</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Perbarui judul dan deskripsi modul.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tutor.modules.update', [$course, $module]) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @include('tutor.modules.partials.form')

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <x-button variant="primary" type="submit" icon="check">Save module</x-button>
                    </div>
                </form>
            </x-card>

            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white">Learning units</h2>
                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                                {{ $module->learningUnits->count() }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Units are atomic learning containers, not meetings. Add as many as needed.</p>
                    </div>

                    <x-button variant="primary" size="sm" href="{{ route('tutor.units.create', [$course, $module]) }}" icon="plus">
                        Add unit
                    </x-button>
                </div>

                @if ($module->learningUnits->isEmpty())
                    <x-card class="py-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 mb-3 border border-blue-100 dark:border-blue-800/60">
                                <x-heroicon-o-document-text class="w-6 h-6" />
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">No learning units yet.</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm">Tambahkan unit pembelajaran pertama yang memuat materi atau aktivitas latihan.</p>
                            <div class="mt-4">
                                <x-button variant="primary" size="sm" href="{{ route('tutor.units.create', [$course, $module]) }}" icon="plus">Add unit</x-button>
                            </div>
                        </div>
                    </x-card>
                @else
                    <div class="space-y-3">
                        @foreach ($module->learningUnits as $index => $unit)
                            <div class="rounded-xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs transition-all duration-200 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-start gap-3.5 min-w-0">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700 font-mono">
                                            {{ sprintf('%02d', $index + 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                                                    <a href="{{ route('tutor.units.edit', [$course, $module, $unit]) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                                        {{ $unit->title }}
                                                    </a>
                                                </h3>
                                                <x-badge variant="{{ $unit->status->value === 'published' ? 'success' : 'warning' }}" size="sm" dot>
                                                    {{ strtoupper($unit->status->value) }}
                                                </x-badge>
                                                @if ($unit->slug)
                                                    <x-badge variant="secondary" size="sm">{{ $unit->slug }}</x-badge>
                                                @endif
                                            </div>
                                            <div class="mt-1 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                                <span>{{ $unit->materials->count() }} materials · {{ $unit->activities->count() }} activities</span>
                                                @if ($unit->description)
                                                    <span class="truncate max-w-xs sm:max-w-md">· {{ $unit->description }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-1.5 self-end sm:self-center shrink-0">
                                        @if ($index > 0)
                                            @php
                                                $upOrder = $module->learningUnits->pluck('id')->values()->all();
                                                [$upOrder[$index - 1], $upOrder[$index]] = [$upOrder[$index], $upOrder[$index - 1]];
                                            @endphp
                                            <form method="POST" action="{{ route('tutor.units.reorder', [$course, $module]) }}" class="inline">
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

                                        <a href="{{ route('tutor.units.edit', [$course, $module, $unit]) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors">
                                            <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                                            <span>Edit</span>
                                        </a>

                                        @if ($unit->status->value !== 'published')
                                            <form method="POST" action="{{ route('tutor.units.publish', [$course, $module, $unit]) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/50 transition-colors">
                                                    Publish
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('tutor.units.unpublish', [$course, $module, $unit]) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/50 transition-colors">
                                                    Unpublish
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('tutor.units.destroy', [$course, $module, $unit]) }}" onsubmit="return confirm('Delete this learning unit?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition-colors" title="Delete Unit">
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
        </div>

        <div class="lg:col-span-1 space-y-6">
            <x-card title="Ringkasan Modul">
                <x-description-list>
                    <x-description-item label="Status">
                        <x-badge variant="{{ $module->status->value === 'published' ? 'success' : 'warning' }}" dot>
                            {{ strtoupper($module->status->value) }}
                        </x-badge>
                    </x-description-item>
                    <x-description-item label="Total Units" :value="$module->learningUnits->count()" />
                    <x-description-item label="Induk Kursus">
                        <a href="{{ route('tutor.courses.edit', $course) }}" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline truncate max-w-[150px]">
                            {{ $course->title }}
                        </a>
                    </x-description-item>
                </x-description-list>
            </x-card>
        </div>
    </div>
</div>
@endsection
