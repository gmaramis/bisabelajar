@if ($errors->any())
    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<div>
    <label for="title" class="mb-1 block text-sm font-medium">Title</label>
    <input id="title" name="title" type="text" value="{{ old('title', $module->title ?? '') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2">
</div>

<div>
    <label for="description" class="mb-1 block text-sm font-medium">Description</label>
    <textarea id="description" name="description" rows="5" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('description', $module->description ?? '') }}</textarea>
</div>
