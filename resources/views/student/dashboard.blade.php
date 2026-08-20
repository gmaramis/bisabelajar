@extends('layouts.app')

@section('title', 'Dashboard — '.config('app.name'))

@section('content')
    <h1 class="mb-2 text-xl font-semibold sm:text-2xl">Dashboard</h1>
    <p class="mb-6 text-sm text-slate-600">Welcome back, {{ $user->name }}. Continue from My Courses.</p>

    <div class="mb-8 flex flex-col gap-3 sm:flex-row">
        <a href="{{ route('student.courses') }}" class="rounded-md bg-slate-900 px-4 py-2 text-center text-sm font-medium text-white">My Courses</a>
        <a href="{{ route('courses.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-center text-sm">Course catalog</a>
    </div>

    @if ($enrollments->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600 sm:p-6">You have no active enrollments yet.</p>
    @else
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($enrollments as $enrollment)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <a href="{{ route('student.courses.show', $enrollment->course) }}" class="font-medium hover:underline">{{ $enrollment->course->title }}</a>
                    <p class="text-sm text-slate-500">{{ strtoupper($enrollment->status->value) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
