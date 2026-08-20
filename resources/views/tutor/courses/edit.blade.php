@extends('layouts.app')

@section('title', 'Edit course — '.config('app.name'))

@section('content')
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-xl font-semibold">Edit course</h1>
        <p class="text-sm text-slate-500">{{ strtoupper($course->status->value) }}</p>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('tutor.courses.update', $course) }}" class="mb-6 max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('tutor.courses.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save changes</button>
    </form>

    <div class="flex gap-3">
        <form method="POST" action="{{ route('tutor.courses.publish', $course) }}">
            @csrf
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Publish</button>
        </form>
        <form method="POST" action="{{ route('tutor.courses.archive', $course) }}">
            @csrf
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Archive</button>
        </form>
    </div>
@endsection
