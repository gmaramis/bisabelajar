@extends('layouts.app')

@section('title', $material->title.' — '.config('app.name'))

@section('content')
    @if (auth()->user()?->isStudent() && $module)
        <nav class="mb-4 flex flex-wrap gap-2 text-sm text-slate-600">
            <a href="{{ route('student.courses.show', $course) }}" class="underline">{{ $course->title }}</a>
            <span>/</span>
            <a href="{{ route('student.modules.show', [$course, $module]) }}" class="underline">{{ $module->title }}</a>
            <span>/</span>
            <a href="{{ route('student.units.show', [$course, $module, $learningUnit]) }}" class="underline">{{ $learningUnit->title }}</a>
        </nav>
    @endif

    <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <h1 class="mb-2 text-xl font-semibold sm:text-2xl">{{ $material->title }}</h1>
        <p class="mb-4 text-sm text-slate-500">{{ strtoupper($material->type->value) }}</p>

        @switch($material->type)
            @case(\App\Enums\MaterialType::RichText)
                <div class="whitespace-pre-wrap text-sm text-slate-700">{{ $material->content }}</div>
                @break
            @case(\App\Enums\MaterialType::ExternalUrl)
                <a href="{{ $material->external_url }}" rel="noopener noreferrer" target="_blank" class="text-sm underline">Open external resource</a>
                @break
            @case(\App\Enums\MaterialType::Pdf)
            @case(\App\Enums\MaterialType::Powerpoint)
                <a href="{{ route('materials.download', [$course, $learningUnit, $material]) }}" class="text-sm underline">
                    {{ $material->type === \App\Enums\MaterialType::Powerpoint ? 'Download PowerPoint' : 'Open PDF' }}
                </a>
                @break
        @endswitch
    </article>
@endsection
