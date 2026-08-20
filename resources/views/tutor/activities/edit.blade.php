@extends('layouts.app')

@section('title', 'Edit activity — '.config('app.name'))

@section('content')
    <p class="mb-4 text-sm">
        <a href="{{ route('tutor.units.edit', [$course, $module, $learningUnit]) }}" class="underline">Back to activities</a>
    </p>
    <h1 class="mb-1 text-xl font-semibold">Edit activity</h1>
    <p class="mb-4 text-sm text-slate-600">{{ $course->title }} · {{ $module->title }} · {{ $learningUnit->title }} · {{ strtoupper($activity->status->value) }}</p>

    <form method="POST" action="{{ route('tutor.activities.update', [$course, $module, $learningUnit, $activity]) }}" class="mb-6 max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('tutor.activities.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save activity</button>
    </form>

    <div class="flex flex-wrap gap-3">
        <form method="POST" action="{{ route('tutor.activities.publish', [$course, $module, $learningUnit, $activity]) }}">
            @csrf
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Publish</button>
        </form>
        <form method="POST" action="{{ route('tutor.activities.unpublish', [$course, $module, $learningUnit, $activity]) }}">
            @csrf
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Unpublish</button>
        </form>
        <form method="POST" action="{{ route('tutor.activities.archive', [$course, $module, $learningUnit, $activity]) }}" onsubmit="return confirm('Archive this activity?')">
            @csrf
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Archive</button>
        </form>
    </div>
@endsection
