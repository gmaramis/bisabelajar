@extends('layouts.app')

@section('title', 'Edit module — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Edit module</h1>
    <p class="mb-4 text-sm text-slate-600">Course: {{ $course->title }} · {{ strtoupper($module->status->value) }}</p>

    <form method="POST" action="{{ route('tutor.modules.update', [$course, $module]) }}" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('tutor.modules.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save module</button>
    </form>

    <section class="mt-10">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold">Learning units</h2>
            <a href="{{ route('tutor.units.create', [$course, $module]) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Add unit</a>
        </div>
        <p class="mb-4 text-sm text-slate-600">Units are atomic learning containers, not meetings. Add as many as needed.</p>

        @if (session('status'))
            <p class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        @if ($module->learningUnits->isEmpty())
            <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No learning units yet.</p>
        @else
            <ol class="space-y-3">
                @foreach ($module->learningUnits as $index => $unit)
                    <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-medium">{{ $unit->title }}</h3>
                                <p class="text-sm text-slate-500">{{ strtoupper($unit->status->value) }} · {{ $unit->slug }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-sm">
                                @if ($index > 0)
                                    @php
                                        $upOrder = $module->learningUnits->pluck('id')->values()->all();
                                        [$upOrder[$index - 1], $upOrder[$index]] = [$upOrder[$index], $upOrder[$index - 1]];
                                    @endphp
                                    <form method="POST" action="{{ route('tutor.units.reorder', [$course, $module]) }}">
                                        @csrf
                                        @foreach ($upOrder as $id)
                                            <input type="hidden" name="order[]" value="{{ $id }}">
                                        @endforeach
                                        <button type="submit" class="underline">Up</button>
                                    </form>
                                @endif
                                <a href="{{ route('tutor.units.edit', [$course, $module, $unit]) }}" class="underline">Edit</a>
                                <form method="POST" action="{{ route('tutor.units.publish', [$course, $module, $unit]) }}">
                                    @csrf
                                    <button type="submit" class="underline">Publish</button>
                                </form>
                                <form method="POST" action="{{ route('tutor.units.unpublish', [$course, $module, $unit]) }}">
                                    @csrf
                                    <button type="submit" class="underline">Unpublish</button>
                                </form>
                                <form method="POST" action="{{ route('tutor.units.destroy', [$course, $module, $unit]) }}" onsubmit="return confirm('Delete this learning unit?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="underline">Delete</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endsection
