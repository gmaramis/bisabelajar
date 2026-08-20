@extends('layouts.app')

@section('title', 'Edit course — '.config('app.name'))

@section('content')
    <div class="mb-4 flex items-center justify-between gap-4">
        <h1 class="text-xl font-semibold">Edit course</h1>
        <p class="text-sm text-slate-500">{{ strtoupper($course->status->value) }}</p>
    </div>

    @if (session('status'))
        <p class="mb-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('tutor.courses.update', $course) }}" class="mb-6 max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('tutor.courses.partials.form')
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save changes</button>
    </form>

    <div class="flex gap-3">
        <form method="POST" action="{{ route('tutor.courses.publish', $course) }}">
            @csrf
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Publish</button>
        </form>
        <form method="POST" action="{{ route('tutor.courses.archive', $course) }}">
            @csrf
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Archive</button>
        </form>
    </div>

    <section class="mt-10">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold">Modules</h2>
            <a href="{{ route('tutor.modules.create', $course) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Add module</a>
        </div>
        <p class="mb-4 text-sm text-slate-600">Add as many modules as needed. Order is saved. Modules are not meetings.</p>

        @if ($course->modules->isEmpty())
            <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No modules yet.</p>
        @else
            <ol class="space-y-3">
                @foreach ($course->modules as $module)
                    <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-medium">{{ $module->title }}</h3>
                                <p class="text-sm text-slate-500">{{ strtoupper($module->status->value) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 text-sm">
                                <a href="{{ route('tutor.modules.edit', [$course, $module]) }}" class="underline">Edit</a>
                                <form method="POST" action="{{ route('tutor.modules.publish', [$course, $module]) }}">
                                    @csrf
                                    <button type="submit" class="underline">Publish</button>
                                </form>
                                <form method="POST" action="{{ route('tutor.modules.unpublish', [$course, $module]) }}">
                                    @csrf
                                    <button type="submit" class="underline">Unpublish</button>
                                </form>
                                <form method="POST" action="{{ route('tutor.modules.destroy', [$course, $module]) }}" onsubmit="return confirm('Delete this module?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="underline">Delete</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>

            <form method="POST" action="{{ route('tutor.modules.reorder', $course) }}" class="mt-4">
                @csrf
                @foreach ($course->modules as $module)
                    <input type="hidden" name="order[]" value="{{ $module->id }}">
                @endforeach
            </form>
        @endif
    </section>

    <section class="mt-10">
        <h2 class="mb-4 text-lg font-semibold">Enrollments</h2>
        <p class="mb-4 text-sm text-slate-600">Students enrolled in this course. Enrollment is unique per student.</p>

        @if ($course->enrollments->isEmpty())
            <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No enrollments yet.</p>
        @else
            @php
                $publishedUnits = $course->modules->flatMap->learningUnits->filter->isPublished();
            @endphp
            <ul class="space-y-3">
                @foreach ($course->enrollments as $enrollment)
                    <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="font-medium">{{ $enrollment->user->name }}</h3>
                        <p class="text-sm text-slate-500">{{ $enrollment->user->email }} · {{ strtoupper($enrollment->status->value) }}</p>
                        <p class="mt-2 text-sm text-slate-600">Progress is not mastery.</p>
                        @if ($publishedUnits->isEmpty())
                            <p class="mt-2 text-sm text-slate-500">No published units.</p>
                        @else
                            <ul class="mt-2 space-y-1 text-sm text-slate-600">
                                @foreach ($publishedUnits as $unit)
                                    <li>{{ $unit->title }} · {{ strtoupper(\App\Models\LearningProgress::statusFor($enrollment->learningProgress->firstWhere('learning_unit_id', $unit->id))->value) }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
