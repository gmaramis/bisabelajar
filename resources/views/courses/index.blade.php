@extends('layouts.app')

@section('title', 'Courses — '.config('app.name', 'BisaBelajar'))

@section('content')
<div x-data="{ viewMode: 'card' }" class="space-y-8">
    <x-page-header 
        title="Courses" 
        description="Explore available public courses."
    >
        <x-slot name="actions">
            @if ($courses->isNotEmpty())
                <div class="inline-flex items-center rounded-lg border border-slate-200/80 dark:border-slate-800 bg-slate-100/80 dark:bg-slate-900 p-0.5 shadow-2xs">
                    <button 
                        type="button" 
                        @click="viewMode = 'card'" 
                        :class="viewMode === 'card' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-md transition-colors"
                        title="Tampilan Kartu (Grid)"
                    >
                        <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                        <span class="hidden sm:inline">Cards</span>
                    </button>
                    <button 
                        type="button" 
                        @click="viewMode = 'table'" 
                        :class="viewMode === 'table' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold rounded-md transition-colors"
                        title="Tampilan Tabel (List)"
                    >
                        <x-heroicon-o-table-cells class="w-4 h-4" />
                        <span class="hidden sm:inline">Table</span>
                    </button>
                </div>
            @endif
        </x-slot>
    </x-page-header>

    @if($courses->isEmpty())
        <x-card class="py-16 text-center">
            <div class="flex flex-col items-center justify-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 mb-4">
                    <x-heroicon-o-inbox class="w-7 h-7" />
                </div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">No public published courses yet.</h3>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md">Belum ada kursus publik yang dipublikasikan saat ini. Silakan cek kembali nanti.</p>
            </div>
        </x-card>
    @else
        <div x-show="viewMode === 'card'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($courses as $course)
                <div class="group flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-100 dark:border-blue-800/60">
                                <x-heroicon-o-book-open class="w-3.5 h-3.5" />
                                <span>Kursus Publik</span>
                            </span>
                        </div>

                        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors line-clamp-2 leading-snug">
                            <a href="{{ route('courses.show', $course) }}">
                                {{ $course->title }}
                            </a>
                        </h2>

                        @if ($course->description)
                            <p class="mt-2 text-xs sm:text-sm text-slate-500 dark:text-slate-400 line-clamp-3 leading-relaxed">
                                {{ $course->description }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-400 dark:text-slate-500">
                            {{ $course->modules->count() }} Modul
                        </span>
                        <x-button variant="primary" size="sm" href="{{ route('courses.show', $course) }}">
                            Lihat Kursus
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
                            <th class="px-4 py-3.5">Modul</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($courses as $course)
                            <tr class="hover:bg-slate-50/75 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-5 py-4 min-w-[240px]">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        <a href="{{ route('courses.show', $course) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            {{ $course->title }}
                                        </a>
                                    </div>
                                    @if ($course->description)
                                        <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500 line-clamp-1">
                                            {{ $course->description }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap font-medium text-slate-700 dark:text-slate-200">
                                    {{ $course->modules->count() }} Modul
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-right">
                                    <x-button variant="primary" size="sm" href="{{ route('courses.show', $course) }}">
                                        Lihat Kursus
                                    </x-button>
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
