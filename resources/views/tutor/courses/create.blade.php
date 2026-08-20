@extends('layouts.app')

@section('title', 'Create course — '.config('app.name'))

@section('content')
    <h1 class="mb-4 text-xl font-semibold">Create course</h1>

    <form method="POST" action="{{ route('tutor.courses.store') }}" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @include('tutor.courses.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Create course</button>
    </form>
@endsection
