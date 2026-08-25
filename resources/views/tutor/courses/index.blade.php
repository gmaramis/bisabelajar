@extends('layouts.app')

@section('title', 'Tutor workspace — '.config('app.name'))

@section('content')
<div x-data="{ viewMode: 'card' }" class="space-y-8">
    <x-page-header 
        title="Tutor workspace" 
        description="Courses you own. Structure is configurable — no fixed meeting count."
    >
        <x-slot name="actions">
            @if ($courses->isNotEmpty())
                <div class="inline-flex items-center rounded-lg border border-slate-200/80 dark:border-slate-800 bg-slate-100/80 dark:bg-slate-900 p-0.5 shadow-2xs">
                    <button 
                        type="button" 
                        dusk="cards-view-btn"
                        @click="viewMode = 'card'" 
                        :class="viewMode === 'card' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-md transition-colors cursor-pointer"
                        title="Tampilan Kartu (Grid)"
                    >
                        <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                        <span class="hidden sm:inline">Cards</span>
                    </button>
                    <button 
                        type="button" 
                        dusk="table-view-btn"
                        @click="viewMode = 'table'" 
                        :class="viewMode === 'table' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-md transition-colors cursor-pointer"
                        title="Tampilan Tabel (List)"
                    >
                        <x-heroicon-o-table-cells class="w-4 h-4" />
                        <span class="hidden sm:inline">Table</span>
                    </button>
                </div>
            @endif

            <x-button variant="primary" href="{{ route('tutor.courses.create') }}" icon="plus">
                Create course
            </x-button>
        </x-slot>
    </x-page-header>

    @if ($courses->isEmpty())
        <x-card class="py-16 text-center">
            <div class="flex flex-col items-center justify-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 mb-4 border border-blue-100 dark:border-blue-800/60 shadow-xs">
                    <x-heroicon-o-academic-cap class="w-8 h-8" />
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">You have not created any courses yet.</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md">
                    Mulai rancang dan publikasikan kursus pembelajaran AI-VET Anda untuk siswa.
                </p>
                <div class="mt-6">
                    <x-button variant="primary" href="{{ route('tutor.courses.create') }}" icon="plus">
                        Create course
                    </x-button>
                </div>
            </div>
        </x-card>
    @else
        <div x-show="viewMode === 'card'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($courses as $course)
                <div class="group flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <x-badge variant="{{ $course->status->value === 'published' ? 'success' : ($course->status->value === 'archived' ? 'gray' : 'warning') }}" size="sm" dot>
                                {{ strtoupper($course->status->value) }}
                            </x-badge>
                            <x-badge variant="secondary" size="sm">
                                {{ strtoupper($course->visibility->value) }}
                            </x-badge>
                        </div>

                        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors line-clamp-1">
                            <a href="{{ route('tutor.courses.edit', $course) }}">
                                {{ $course->title }}
                            </a>
                        </h2>

                        @if ($course->description)
                            <p class="mt-2 text-xs sm:text-sm text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                {{ $course->description }}
                            </p>
                        @endif

                        <div class="mt-4 flex items-center gap-4 text-xs font-semibold text-slate-500 dark:text-slate-400 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <div class="flex items-center gap-1.5">
                                <x-heroicon-o-folder class="w-4 h-4 text-slate-400" />
                                <span>{{ $course->modules->count() }} Modul</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <x-heroicon-o-users class="w-4 h-4 text-slate-400" />
                                <span>{{ $course->enrollments->count() }} Siswa</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <a href="{{ route('courses.show', $course) }}" target="_blank" class="text-xs font-medium text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors">
                            Preview
                        </a>
                        <x-button variant="primary" size="sm" href="{{ route('tutor.courses.edit', $course) }}">
                            Edit
                        </x-button>
                    </div>
                </div>
            @endforeach
        </div>

        <div x-show="viewMode === 'table'" class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900" style="display: none;">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-950/60 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200/80 dark:border-slate-800">
                        <tr>
                            <th class="px-5 py-3.5">Kursus</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-4 py-3.5">Visibilitas</th>
                            <th class="px-4 py-3.5">Modul</th>
                            <th class="px-4 py-3.5">Siswa</th>
                            <th class="px-4 py-3.5">Tanggal Dibuat</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($courses as $course)
                            <tr class="hover:bg-slate-50/75 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-5 py-4 min-w-[220px]">
                                    <div class="font-bold text-slate-900 dark:text-white line-clamp-1">
                                        <a href="{{ route('tutor.courses.edit', $course) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            {{ $course->title }}
                                        </a>
                                    </div>
                                    @if ($course->description)
                                        <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500 line-clamp-1">
                                            {{ $course->description }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <x-badge variant="{{ $course->status->value === 'published' ? 'success' : ($course->status->value === 'archived' ? 'gray' : 'warning') }}" size="sm" dot>
                                        {{ strtoupper($course->status->value) }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <x-badge variant="secondary" size="sm">
                                        {{ strtoupper($course->visibility->value) }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap font-medium text-slate-700 dark:text-slate-200">
                                    {{ $course->modules->count() }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap font-medium text-slate-700 dark:text-slate-200">
                                    {{ $course->enrollments->count() }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-xs text-slate-400 dark:text-slate-500">
                                    {{ $course->created_at?->format('d M Y') ?? '-' }}
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a href="{{ route('courses.show', $course) }}" target="_blank" class="px-2.5 py-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition-colors">
                                            Preview
                                        </a>
                                        <x-button variant="secondary" size="sm" href="{{ route('tutor.courses.edit', $course) }}">
                                            Edit
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($courses instanceof \Illuminate\Contracts\Pagination\Paginator && $courses->hasPages())
            <div class="pt-6 border-t border-slate-200/80 dark:border-slate-800">
                <x-pagination :paginator="$courses" />
            </div>
        @endif
    @endif
</div>
@endsection
