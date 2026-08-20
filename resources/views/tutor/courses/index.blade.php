@extends('layouts.app')

@section('title', 'Tutor workspace — '.config('app.name'))

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Tutor workspace</h1>
            <p class="text-sm text-slate-600">Courses you own. Structure is configurable — no fixed meeting count.</p>
        </div>
        <a href="{{ route('tutor.courses.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">
            Create course
        </a>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
    @endif

    @if ($courses->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-6 text-sm text-slate-600">You have not created any courses yet.</p>
    @else
        <ul class="space-y-3">
            @foreach ($courses as $course)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="font-medium">{{ $course->title }}</h2>
                            <p class="text-sm text-slate-500">
                                {{ strtoupper($course->status->value) }} · {{ strtoupper($course->visibility->value) }}
                            </p>
                        </div>
                        <a href="{{ route('tutor.courses.edit', $course) }}" class="text-sm underline">Edit</a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
