@extends('layouts.app')

@section('title', 'My learning — '.config('app.name'))

@section('content')
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-2 text-xl font-semibold">My learning</h1>
        <p class="text-sm text-slate-600">
            This is {{ $user->name }}'s learning area. Enrollments and progress will appear here in later M1 tasks.
        </p>
    </div>
@endsection
