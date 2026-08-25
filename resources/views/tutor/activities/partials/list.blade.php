<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2.5">
                <h2 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white">Activities</h2>
                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60">
                    {{ $learningUnit->activities->count() }}
                </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Activities are generic learning objects. Type engines, grading, and code execution are not enabled here.</p>
        </div>

        <x-button variant="primary" size="sm" href="{{ route('tutor.activities.create', [$course, $module, $learningUnit]) }}" icon="plus">
            Add activity
        </x-button>
    </div>

    @if ($learningUnit->activities->isEmpty())
        <x-card class="py-12 text-center">
            <div class="flex flex-col items-center justify-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 mb-3 border border-blue-100 dark:border-blue-800/60">
                    <x-heroicon-o-puzzle-piece class="w-6 h-6" />
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">No activities yet.</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm">Tambahkan aktivitas pembelajaran seperti kuis, tugas, diskusi, atau latihan coding.</p>
                <div class="mt-4">
                    <x-button variant="primary" size="sm" href="{{ route('tutor.activities.create', [$course, $module, $learningUnit]) }}" icon="plus">Add activity</x-button>
                </div>
            </div>
        </x-card>
    @else
        <div class="space-y-3">
            @foreach ($learningUnit->activities as $index => $activity)
                <div class="rounded-xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs transition-all duration-200 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700 font-mono">
                                {{ sprintf('%02d', $index + 1) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                                        <a href="{{ route('tutor.activities.edit', [$course, $module, $learningUnit, $activity]) }}" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                            {{ $activity->title }}
                                        </a>
                                    </h3>
                                    <x-badge variant="{{ $activity->status->value === 'published' ? 'success' : 'warning' }}" size="sm" dot>
                                        {{ strtoupper($activity->status->value) }}
                                    </x-badge>
                                    <x-badge variant="secondary" size="sm">
                                        {{ strtoupper($activity->type->value) }}
                                    </x-badge>
                                </div>
                                @if (isset($activity->configuration['instructions']))
                                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300 line-clamp-1">{{ $activity->configuration['instructions'] }}</p>
                                @endif
                                @if ($activity->tutorPrivateConfiguration() !== [])
                                    <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                        <x-heroicon-s-lock-closed class="w-3 h-3" />
                                        <span>Includes tutor-private configuration</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5 self-end sm:self-center shrink-0">
                            @if ($index > 0)
                                @php
                                    $upOrder = $learningUnit->activities->pluck('id')->values()->all();
                                    [$upOrder[$index - 1], $upOrder[$index]] = [$upOrder[$index], $upOrder[$index - 1]];
                                @endphp
                                <form method="POST" action="{{ route('tutor.activities.reorder', [$course, $module, $learningUnit]) }}" class="inline">
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

                            <a href="{{ route('tutor.activities.edit', [$course, $module, $learningUnit, $activity]) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors">
                                <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                                <span>Edit</span>
                            </a>

                            <form method="POST" action="{{ route('tutor.activities.publish', [$course, $module, $learningUnit, $activity]) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950/50 transition-colors">
                                    Publish
                                </button>
                            </form>
                            <form method="POST" action="{{ route('tutor.activities.unpublish', [$course, $module, $learningUnit, $activity]) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold text-amber-600 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/50 transition-colors">
                                    Unpublish
                                </button>
                            </form>

                            <form method="POST" action="{{ route('tutor.activities.archive', [$course, $module, $learningUnit, $activity]) }}" onsubmit="return confirm('Archive this activity?')" class="inline">
                                @csrf
                                <button type="submit" class="p-1.5 text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-white rounded-lg transition-colors" title="Archive Activity">
                                    <x-heroicon-o-archive-box class="w-4 h-4" />
                                    <span class="sr-only">Archive</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
