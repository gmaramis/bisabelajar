@extends('layouts.app')

@section('title', 'Add activity — '.config('app.name'))

@section('content')
    <p class="mb-4 text-sm">
        <a href="{{ route('tutor.units.edit', [$course, $module, $learningUnit]) }}" class="underline">Back to activities</a>
    </p>
    <h1 class="mb-1 text-xl font-semibold">Add activity</h1>
    <p class="mb-4 text-sm text-slate-600">{{ $course->title }} · {{ $module->title }} · {{ $learningUnit->title }}</p>

    <form method="POST" action="{{ route('tutor.activities.store', [$course, $module, $learningUnit]) }}" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('tutor.activities.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save activity</button>
    </form>
@endsection
