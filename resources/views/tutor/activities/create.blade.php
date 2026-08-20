@extends('layouts.app')

@section('title', 'Add activity — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Add activity</h1>
    <p class="mb-4 text-sm text-slate-600">{{ $course->title }} · {{ $module->title }} · {{ $learningUnit->title }}</p>

    <form method="POST" action="{{ route('tutor.activities.store', [$course, $module, $learningUnit]) }}" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div>
            <label for="title" class="mb-1 block text-sm font-medium">Title</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <div>
            <label for="type" class="mb-1 block text-sm font-medium">Type</label>
            <select id="type" name="type" class="w-full rounded-md border border-slate-300 px-3 py-2">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ strtoupper($type->value) }}</option>
                @endforeach
            </select>
        </div>

        <p class="text-sm text-slate-600">Type-specific engines and configuration rules are not part of this foundation.</p>

        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save activity</button>
    </form>
@endsection
