@extends('layouts.app')

@section('title', 'Add material — '.config('app.name'))

@section('content')
    <h1 class="mb-1 text-xl font-semibold">Add material</h1>
    <p class="mb-4 text-sm text-slate-600">{{ $course->title }} · {{ $module->title }} · {{ $learningUnit->title }}</p>

    <form method="POST" action="{{ route('tutor.materials.store', [$course, $module, $learningUnit]) }}" enctype="multipart/form-data" class="max-w-xl space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
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

        <div>
            <label for="content" class="mb-1 block text-sm font-medium">Rich text</label>
            <textarea id="content" name="content" rows="6" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('content') }}</textarea>
        </div>

        <div>
            <label for="file" class="mb-1 block text-sm font-medium">PDF or PowerPoint file</label>
            <input id="file" name="file" type="file" accept=".pdf,.ppt,.pptx,application/pdf,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation" class="w-full text-sm">
        </div>

        <div>
            <label for="external_url" class="mb-1 block text-sm font-medium">External URL</label>
            <input id="external_url" name="external_url" type="url" value="{{ old('external_url') }}" class="w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white">Save material</button>
    </form>
@endsection
