@extends('layouts.app')

@section('title', $material->title.' — '.config('app.name', 'BisaBelajar'))

@section('content')
<div class="space-y-8 max-w-4xl mx-auto">
    <x-page-header 
        :title="$material->title"
    >
        @if (auth()->user()?->isStudent() && $module)
            <x-slot name="breadcrumbs">
                <a href="{{ route('student.courses.show', $course) }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">{{ $course->title }}</a>
                <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                <a href="{{ route('student.modules.show', [$course, $module]) }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">{{ $module->title }}</a>
                <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                <a href="{{ route('student.units.show', [$course, $module, $learningUnit]) }}" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">{{ $learningUnit->title }}</a>
                <x-heroicon-m-chevron-right class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500 shrink-0" />
                <span class="font-medium text-slate-700 dark:text-slate-200 truncate">{{ $material->title }}</span>
            </x-slot>
        @endif

        <x-slot name="badge">
            <x-badge variant="info">{{ strtoupper($material->type->value) }}</x-badge>
        </x-slot>
    </x-page-header>

    <x-card>
        <div class="text-xs sm:text-sm text-slate-700 dark:text-slate-300">
            @switch($material->type)
                @case(\App\Enums\MaterialType::RichText)
                    <div class="whitespace-pre-wrap leading-relaxed">{{ $material->content }}</div>
                    @break
                @case(\App\Enums\MaterialType::ExternalUrl)
                    <a href="{{ $material->external_url }}" rel="noopener noreferrer" target="_blank" class="inline-flex items-center gap-2 font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors underline">
                        <span>Open external resource</span>
                        <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                    </a>
                    @break
                @case(\App\Enums\MaterialType::Pdf)
                @case(\App\Enums\MaterialType::Powerpoint)
                    <a href="{{ route('materials.download', [$course, $learningUnit, $material]) }}" class="inline-flex items-center gap-2 font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors underline">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                        <span>{{ $material->type === \App\Enums\MaterialType::Powerpoint ? 'Download PowerPoint' : 'Open PDF' }}</span>
                    </a>
                    @break
            @endswitch
        </div>
    </x-card>
</div>
@endsection
