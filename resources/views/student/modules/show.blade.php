@extends('layouts.app')

@section('title', $module->title.' — '.config('app.name'))

@section('content')
    <nav class="mb-4 flex flex-wrap gap-2 text-sm text-slate-600">
        <a href="{{ route('student.courses') }}" class="underline">My Courses</a>
        <span>/</span>
        <a href="{{ route('student.courses.show', $course) }}" class="underline">{{ $course->title }}</a>
    </nav>

    <h1 class="mb-2 text-xl font-semibold sm:text-2xl">{{ $module->title }}</h1>
    <p class="mb-6 whitespace-pre-wrap text-sm text-slate-700">{{ $module->description }}</p>

    <h2 class="mb-3 text-lg font-semibold">Learning units</h2>
    @if ($module->learningUnits->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No published learning units yet.</p>
    @else
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach ($module->learningUnits as $unit)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <a href="{{ route('student.units.show', [$course, $module, $unit]) }}" class="font-medium hover:underline">{{ $unit->title }}</a>
                    @if ($unit->description)
                        <p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($unit->description, 120) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endsection
