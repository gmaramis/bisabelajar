@extends('layouts.app')

@section('title', 'Add module — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Add module</h1>
    <p class="mb-4 text-sm text-slate-600">Course: {{ $course->title }}</p>

    <form method="POST" action="{{ route('tutor.modules.store', $course) }}" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('tutor.modules.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Create module</button>
    </form>
@endsection
