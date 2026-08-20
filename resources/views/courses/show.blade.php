@extends('layouts.app')

@section('title', $course->title.' — '.config('app.name'))

@section('content')
    <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-2 text-xl font-semibold">{{ $course->title }}</h1>
        <p class="mb-4 text-sm text-slate-500">{{ strtoupper($course->visibility->value) }}</p>
        <div class="whitespace-pre-wrap text-sm text-slate-700">{{ $course->description }}</div>
    </article>
@endsection
