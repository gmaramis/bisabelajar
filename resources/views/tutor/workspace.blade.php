@extends('layouts.app')

@section('title', 'Tutor workspace — '.config('app.name'))

@section('content')
    <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-2 text-xl font-semibold">Tutor workspace</h1>
        <p class="text-sm text-slate-600">
            Course management for {{ $user->name }} will be added in later M1 tasks. Only this tutor may manage their own content.
        </p>
    </div>
@endsection
