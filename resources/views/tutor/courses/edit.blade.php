@extends('layouts.app')

@section('title', 'Edit course — '.$course->title.' — '.config('app.name'))

@section('content')
<div class="space-y-8">
    <x-page-header 
        title="Edit course" 
        description="Kelola detail kursus, susun kurikulum modul, dan pantau progres siswa."
    >
        <x-slot name="breadcrumbs">
            <a href="{{ route('tutor.workspace') }}" class="font-medium hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Tutor workspace</a>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <span class="font-medium text-slate-900 dark:text-white truncate">{{ $course->title }}</span>
            <x-heroicon-m-chevron-right class="h-4 w-4 text-slate-400 dark:text-slate-600 shrink-0" />
            <span class="text-slate-400 dark:text-slate-500">Edit course</span>
        </x-slot>

        <x-slot name="badge">
            <x-badge variant="{{ $course->status->value === 'published' ? 'success' : ($course->status->value === 'archived' ? 'gray' : 'warning') }}" dot>
                {{ strtoupper($course->status->value) }}
            </x-badge>
            <x-badge variant="secondary">
                {{ strtoupper($course->visibility->value) }}
            </x-badge>
        </x-slot>

        <x-slot name="actions">
            <a href="{{ route('courses.show', $course) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-xs">
                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                <span>Preview</span>
            </a>

            @if ($course->status->value !== 'published')
                <form method="POST" action="{{ route('tutor.courses.publish', $course) }}" class="inline">
                    @csrf
                    <x-button variant="success" size="sm" type="submit" icon="check-circle">Publish</x-button>
                </form>
            @endif

            @if ($course->status->value !== 'archived')
                <form method="POST" action="{{ route('tutor.courses.archive', $course) }}" class="inline" onsubmit="return confirm('Archive this course?')">
                    @csrf
                    <x-button variant="secondary" size="sm" type="submit" icon="archive-box">Archive</x-button>
                </form>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">
            <x-card>
                <div class="flex items-center justify-between pb-4 mb-6 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">Informasi Kursus</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pengaturan judul, deskripsi, dan visibilitas publik.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('tutor.courses.update', $course) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @include('tutor.courses.partials.form')

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <x-button variant="primary" type="submit" icon="check">Save changes</x-button>
                    </div>
                </form>
            </x-card>

            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white">Modules</h2>
                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                                {{ $course->modules->count() }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Add as many modules as needed. Order is saved. Modules are not meetings.</p>
                    </div>

                    <x-button variant="primary" size="sm" href="{{ route('tutor.modules.create', $course) }}" icon="plus">
                        Add module
                    </x-button>
                </div>

                @if ($course->modules->isEmpty())
                    <x-card class="py-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 mb-3 border border-blue-100 dark:border-blue-800/60">
                                <x-heroicon-o-book-open class="w-6 h-6" />
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">No modules yet.</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm">Mulai buat kurikulum dengan menambahkan modul pembelajaran pertama untuk kursus ini.</p>
                            <div class="mt-4">
                                <x-button variant="primary" size="sm" href="{{ route('tutor.modules.create', $course) }}" icon="plus">Add module</x-button>
                            </div>
                        </div>
                    </x-card>
                @else
                    <div class="space-y-3">
                        @foreach ($course->modules as $index => $module)
                            <div class="rounded-xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs transition-all duration-200 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-start gap-3.5 min-w-0">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700 font-mono">
                                            {{ sprintf('%02d', $index + 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                                                    <a href="{{ route('tutor.modules.edit', [$course, $module]) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                                        {{ $module->title }}
                                                    </a>
                                                </h3>
                                                <x-badge variant="{{ $module->status->value === 'published' ? 'success' : 'warning' }}" size="sm" dot>
                                                    {{ strtoupper($module->status->value) }}
                                                </x-badge>
                                            </div>
                                            <div class="mt-1 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                                                <span>{{ $module->learningUnits->count() }} learning units</span>
                                                @if ($module->description)
                                                    <span class="truncate max-w-xs sm:max-w-md">· {{ $module->description }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-1.5 self-end sm:self-center shrink-0">
                                        @if ($index > 0)
                                            @php
                                                $upOrder = $course->modules->pluck('id')->values()->all();
                                                [$upOrder[$index - 1], $upOrder[$index]] = [$upOrder[$index], $upOrder[$index - 1]];
                                            @endphp
                                            <form method="POST" action="{{ route('tutor.modules.reorder', $course) }}" class="inline">
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

                                        <a href="{{ route('tutor.modules.edit', [$course, $module]) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors">
                                            <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                                            <span>Edit</span>
                                        </a>

                                        @if ($module->status->value !== 'published')
                                            <form method="POST" action="{{ route('tutor.modules.publish', [$course, $module]) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/50 transition-colors">
                                                    Publish
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('tutor.modules.unpublish', [$course, $module]) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/50 transition-colors">
                                                    Unpublish
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('tutor.modules.destroy', [$course, $module]) }}" onsubmit="return confirm('Delete this module?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition-colors" title="Delete Module">
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

            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white">Enrollments</h2>
                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                {{ $course->enrollments->count() }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Students enrolled in this course. Enrollment is unique per student.</p>
                    </div>
                </div>

                @if ($course->enrollments->isEmpty())
                    <x-card class="py-10 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 mb-3">
                                <x-heroicon-o-user-group class="w-6 h-6" />
                            </div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">No enrollments yet.</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Belum ada siswa yang mendaftar ke kursus ini.</p>
                        </div>
                    </x-card>
                @else
                    @php
                        $publishedUnits = $course->modules->flatMap->learningUnits->filter->isPublished();
                    @endphp
                    <div class="space-y-3">
                        @foreach ($course->enrollments as $enrollment)
                            <div class="rounded-xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 font-bold text-sm">
                                            {{ strtoupper(substr($enrollment->user->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ $enrollment->user->name }}</h3>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $enrollment->user->email }}</p>
                                        </div>
                                    </div>
                                    <x-badge variant="{{ $enrollment->status->value === 'active' ? 'success' : 'secondary' }}" size="sm" dot>
                                        {{ strtoupper($enrollment->status->value) }}
                                    </x-badge>
                                </div>

                                <div class="mt-3 space-y-1.5">
                                    <p class="text-xs text-slate-500 dark:text-slate-400 italic">Progress is not mastery. Activity progress is not mastery.</p>
                                    @if ($publishedUnits->isEmpty())
                                        <p class="text-xs text-slate-500 dark:text-slate-400">No published units.</p>
                                    @else
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3 pt-2">
                                            @foreach ($publishedUnits as $unit)
                                                <div class="rounded-lg bg-slate-50 dark:bg-slate-950/60 p-2.5 border border-slate-100 dark:border-slate-800/80">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $unit->title }}</span>
                                                        <x-badge variant="gray" size="sm">
                                                            {{ strtoupper(\App\Models\LearningProgress::statusFor($enrollment->learningProgress->firstWhere('learning_unit_id', $unit->id))->value) }}
                                                        </x-badge>
                                                    </div>
                                                    @php
                                                        $publishedActivities = $unit->activities->filter->isPublished();
                                                    @endphp
                                                    @if ($publishedActivities->isNotEmpty())
                                                        <div class="mt-2 space-y-1 pl-2 border-l border-slate-200 dark:border-slate-800">
                                                            @foreach ($publishedActivities as $activity)
                                                                <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                                                                    <span class="truncate max-w-[150px]">{{ $activity->title }}</span>
                                                                    <span>{{ strtoupper(\App\Models\ActivityProgress::statusFor($enrollment->activityProgress->firstWhere('activity_id', $activity->id))->value) }}</span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="lg:col-span-1 space-y-6">
            <x-card title="Ringkasan Kursus">
                <x-description-list>
                    <x-description-item label="Status">
                        <x-badge variant="{{ $course->status->value === 'published' ? 'success' : ($course->status->value === 'archived' ? 'gray' : 'warning') }}" dot>
                            {{ strtoupper($course->status->value) }}
                        </x-badge>
                    </x-description-item>
                    <x-description-item label="Visibilitas">
                        <x-badge variant="secondary">
                            {{ strtoupper($course->visibility->value) }}
                        </x-badge>
                    </x-description-item>
                    <x-description-item label="Modul" :value="$course->modules->count()" />
                    <x-description-item label="Siswa" :value="$course->enrollments->count()" />
                    <x-description-item label="Dibuat" :value="$course->created_at?->format('d M Y') ?? '-'" />
                    <x-description-item label="Diperbarui" :value="$course->updated_at?->format('d M Y') ?? '-'" />
                </x-description-list>
            </x-card>
        </div>
    </div>
</div>
@endsection
