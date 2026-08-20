@extends('layouts.app')

@section('title', 'Courses — '.config('app.name'))

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Courses</h1>
    @if ($courses->isEmpty())
        <p class="text-sm text-slate-600">No public published courses yet.</p>
    @else
        <ul class="space-y-3">
            @foreach ($courses as $course)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <a href="{{ route('courses.show', $course) }}" class="font-medium hover:underline">{{ $course->title }}</a>
                    <p class="text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($course->description, 160) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
