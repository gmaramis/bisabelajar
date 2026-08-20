@extends('layouts.app')

@section('title', 'Edit learning unit — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Edit learning unit</h1>
    <p class="mb-4 text-sm text-slate-600">{{ $course->title }} · {{ $module->title }} · {{ strtoupper($learningUnit->status->value) }}</p>

    <form method="POST" action="{{ route('tutor.units.update', [$course, $module, $learningUnit]) }}" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('tutor.learning-units.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save unit</button>
    </form>

    <section class="mt-10">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold">Materials</h2>
            <a href="{{ route('tutor.materials.create', [$course, $module, $learningUnit]) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Add material</a>
        </div>
        <p class="mb-4 text-sm text-slate-600">Opening material is not mastery. Progress is tracked separately.</p>

        @if (session('status'))
            <p class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        @if ($learningUnit->materials->isEmpty())
            <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No materials yet.</p>
        @else
            <ol class="space-y-3">
                @foreach ($learningUnit->materials as $index => $material)
                    <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-medium">{{ $material->title }}</h3>
                                <p class="text-sm text-slate-500">{{ strtoupper($material->type->value) }} · {{ strtoupper($material->status->value) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-sm">
                                @if ($index > 0)
                                    @php
                                        $upOrder = $learningUnit->materials->pluck('id')->values()->all();
                                        [$upOrder[$index - 1], $upOrder[$index]] = [$upOrder[$index], $upOrder[$index - 1]];
                                    @endphp
                                    <form method="POST" action="{{ route('tutor.materials.reorder', [$course, $module, $learningUnit]) }}">
                                        @csrf
                                        @foreach ($upOrder as $id)
                                            <input type="hidden" name="order[]" value="{{ $id }}">
                                        @endforeach
                                        <button type="submit" class="underline">Up</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('tutor.materials.publish', [$course, $module, $learningUnit, $material]) }}">
                                    @csrf
                                    <button type="submit" class="underline">Publish</button>
                                </form>
                                <form method="POST" action="{{ route('tutor.materials.unpublish', [$course, $module, $learningUnit, $material]) }}">
                                    @csrf
                                    <button type="submit" class="underline">Unpublish</button>
                                </form>
                                <form method="POST" action="{{ route('tutor.materials.destroy', [$course, $module, $learningUnit, $material]) }}" onsubmit="return confirm('Delete this material?')">
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

    <section class="mt-10">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold">Activities</h2>
            <a href="{{ route('tutor.activities.create', [$course, $module, $learningUnit]) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Add activity</a>
        </div>
        <p class="mb-4 text-sm text-slate-600">Activities are generic learning objects. Type engines, grading, and code execution are not enabled here.</p>

        @if ($learningUnit->activities->isEmpty())
            <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No activities yet.</p>
        @else
            <ol class="space-y-3">
                @foreach ($learningUnit->activities as $index => $activity)
                    <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-medium">{{ $activity->title }}</h3>
                                <p class="text-sm text-slate-500">{{ strtoupper($activity->type->value) }} · {{ strtoupper($activity->status->value) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-sm">
                                @if ($index > 0)
                                    @php
                                        $upOrder = $learningUnit->activities->pluck('id')->values()->all();
                                        [$upOrder[$index - 1], $upOrder[$index]] = [$upOrder[$index], $upOrder[$index - 1]];
                                    @endphp
                                    <form method="POST" action="{{ route('tutor.activities.reorder', [$course, $module, $learningUnit]) }}">
                                        @csrf
                                        @foreach ($upOrder as $id)
                                            <input type="hidden" name="order[]" value="{{ $id }}">
                                        @endforeach
                                        <button type="submit" class="underline">Up</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endsection
