@extends('layouts.app')

@section('title', 'Edit learning unit — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Edit learning unit</h1>
    <p class="mb-4 text-sm text-slate-600">{{ $course->title }} · {{ $module->title }} · {{ strtoupper($learningUnit->status->value) }}</p>

    <form method="POST" action="{{ route('tutor.units.update', [$course, $module, $learningUnit]) }}" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('tutor.learning-units.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save unit</button>
    </form>
@endsection
