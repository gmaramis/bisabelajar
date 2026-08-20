@extends('layouts.app')

@section('title', 'Edit activity — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Edit activity</h1>
    <p class="mb-4 text-sm text-slate-600">{{ $course->title }} · {{ $module->title }} · {{ $learningUnit->title }} · {{ strtoupper($activity->status->value) }}</p>

    <form method="POST" action="{{ route('tutor.activities.update', [$course, $module, $learningUnit, $activity]) }}" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('tutor.activities.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save activity</button>
    </form>
@endsection
