@extends('layouts.app')

@section('title', $learningUnit->title.' — '.config('app.name'))

@section('content')
    <nav class="mb-4 flex flex-wrap gap-2 text-sm text-slate-600">
        <a href="{{ route('student.courses.show', $course) }}" class="underline">{{ $course->title }}</a>
        <span>/</span>
        <a href="{{ route('student.modules.show', [$course, $module]) }}" class="underline">{{ $module->title }}</a>
    </nav>

    <h1 class="mb-2 text-xl font-semibold sm:text-2xl">{{ $learningUnit->title }}</h1>
    <p class="mb-6 whitespace-pre-wrap text-sm text-slate-700">{{ $learningUnit->description }}</p>

    <h2 class="mb-3 text-lg font-semibold">Materials</h2>
    @if ($learningUnit->materials->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No published materials yet.</p>
    @else
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($learningUnit->materials as $material)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <a href="{{ route('materials.show', [$course, $learningUnit, $material]) }}" class="font-medium hover:underline">{{ $material->title }}</a>
                    <p class="text-sm text-slate-500">{{ strtoupper($material->type->value) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
