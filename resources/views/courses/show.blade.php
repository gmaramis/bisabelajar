@extends('layouts.app')

@section('title', $course->title.' — '.config('app.name'))

@section('content')
    <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-2 text-xl font-semibold">{{ $course->title }}</h1>
        <p class="mb-4 text-sm text-slate-500">{{ strtoupper($course->visibility->value) }}</p>
        <div class="mb-6 whitespace-pre-wrap text-sm text-slate-700">{{ $course->description }}</div>

        @if ($errors->has('course'))
            <p class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first('course') }}</p>
        @endif

        @if ($isEnrolled)
            <p class="text-sm text-slate-600">You are enrolled in this course.</p>
        @elseif ($canEnroll)
            <form method="POST" action="{{ route('enrollments.store', $course) }}">
                @csrf
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Enroll</button>
            </form>
        @endif
    </article>
@endsection
