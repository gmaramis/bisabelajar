@if ($errors->any())
    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div>
    <label for="title" class="mb-1 block text-sm font-medium">Title</label>
    <input id="title" name="title" type="text" value="{{ old('title', $course->title ?? '') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2">
</div>

<div>
    <label for="slug" class="mb-1 block text-sm font-medium">Slug (optional)</label>
    <input id="slug" name="slug" type="text" value="{{ old('slug', $course->slug ?? '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2">
</div>

<div>
    <label for="description" class="mb-1 block text-sm font-medium">Description</label>
    <textarea id="description" name="description" rows="5" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('description', $course->description ?? '') }}</textarea>
</div>

<div>
    <label for="thumbnail" class="mb-1 block text-sm font-medium">Thumbnail reference (optional)</label>
    <input id="thumbnail" name="thumbnail" type="text" value="{{ old('thumbnail', $course->thumbnail ?? '') }}" class="w-full rounded-md border border-slate-300 px-3 py-2">
</div>

<div>
    <label for="visibility" class="mb-1 block text-sm font-medium">Visibility</label>
    <select id="visibility" name="visibility" class="w-full rounded-md border border-slate-300 px-3 py-2">
        @foreach ($visibilities as $visibility)
            <option value="{{ $visibility->value }}" @selected(old('visibility', isset($course) ? $course->visibility->value : 'private') === $visibility->value)>
                {{ strtoupper($visibility->value) }}
            </option>
        @endforeach
    </select>
</div>
