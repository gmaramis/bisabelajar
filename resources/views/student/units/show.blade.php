@extends('layouts.app')

@section('title', $learningUnit->title.' — '.config('app.name'))

@section('content')
    <nav class="mb-4 flex flex-wrap gap-2 text-sm text-slate-600">
        <a href="{{ route('student.courses.show', $course) }}" class="underline">{{ $course->title }}</a>
        <span>/</span>
        <a href="{{ route('student.modules.show', [$course, $module]) }}" class="underline">{{ $module->title }}</a>
    </nav>

    <h1 class="mb-2 text-xl font-semibold sm:text-2xl">{{ $learningUnit->title }}</h1>
    <p class="mb-2 text-sm text-slate-500">{{ strtoupper($progress->status->value) }} · Completion is not mastery.</p>
    <p class="mb-6 whitespace-pre-wrap text-sm text-slate-700">{{ $learningUnit->description }}</p>

    @if (session('status'))
        <p class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
    @endif

    @if (! $progress->isCompleted())
        <form method="POST" action="{{ route('student.progress.complete', [$course, $module, $learningUnit]) }}" class="mb-6">
            @csrf
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Mark unit complete</button>
        </form>
    @endif

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

    <h2 class="mt-8 mb-3 text-lg font-semibold">Activities</h2>
    @if ($learningUnit->activities->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No published activities yet.</p>
    @else
        <ol class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($learningUnit->activities as $activity)
                @php
                    $activityStatus = \App\Models\ActivityProgress::statusFor($activityProgressById[$activity->id] ?? null);
                @endphp
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <a href="{{ route('activities.show', [$course, $learningUnit, $activity]) }}" class="font-medium hover:underline">{{ $activity->title }}</a>
                    <p class="text-sm text-slate-500">{{ strtoupper($activity->type->value) }} · {{ strtoupper($activityStatus->value) }}</p>
                </li>
            @endforeach
        </ol>
    @endif
@endsection
