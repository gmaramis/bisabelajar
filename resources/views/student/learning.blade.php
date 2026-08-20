@extends('layouts.app')

@section('title', 'My learning — '.config('app.name'))

@section('content')
    <h1 class="mb-4 text-xl font-semibold">My learning</h1>
    <p class="mb-4 text-sm text-slate-600">Courses {{ $user->name }} is enrolled in.</p>

    @if (session('status'))
        <p class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
    @endif

    @if ($enrollments->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-600">You have not enrolled in any courses yet.</p>
    @else
        <ul class="space-y-3">
            @foreach ($enrollments as $enrollment)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <a href="{{ route('courses.show', $enrollment->course) }}" class="font-medium hover:underline">{{ $enrollment->course->title }}</a>
                    <p class="text-sm text-slate-500">{{ strtoupper($enrollment->status->value) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
