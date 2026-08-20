@extends('layouts.app')

@section('title', 'My Courses — '.config('app.name'))

@section('content')
    <h1 class="mb-4 text-xl font-semibold sm:text-2xl">My Courses</h1>
    <p class="mb-4 text-sm text-slate-600">Active enrollments for {{ $user->name }}.</p>

    @if (session('status'))
        <p class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
    @endif

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
