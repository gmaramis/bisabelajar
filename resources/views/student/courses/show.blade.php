@extends('layouts.app')

@section('title', $course->title.' — '.config('app.name'))

@section('content')
    <nav class="mb-4 flex flex-wrap gap-2 text-sm text-slate-600">
        <a href="{{ route('student.dashboard') }}" class="underline">Dashboard</a>
        <span>/</span>
        <a href="{{ route('student.courses') }}" class="underline">My Courses</a>
    </nav>

    <h1 class="mb-2 text-xl font-semibold sm:text-2xl">{{ $course->title }}</h1>
    <p class="mb-6 whitespace-pre-wrap text-sm text-slate-700">{{ $course->description }}</p>

    <h2 class="mb-3 text-lg font-semibold">Modules</h2>
    @if ($course->modules->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No published modules yet.</p>
    @else
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($course->modules as $module)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <a href="{{ route('student.modules.show', [$course, $module]) }}" class="font-medium hover:underline">{{ $module->title }}</a>
                    @if ($module->description)
                        <p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($module->description, 120) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
