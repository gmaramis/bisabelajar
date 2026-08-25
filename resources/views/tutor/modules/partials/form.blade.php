@if ($errors->any())
    <x-alert variant="danger" class="mb-4">
        {{ $errors->first() }}
    </x-alert>
@endif

<div class="space-y-4">
    <x-form-group label="Title" name="title" required>
        <x-input id="title" name="title" type="text" value="{{ old('title', $module->title ?? '') }}" required placeholder="e.g. Pengenalan Sintaks Python" />
    </x-form-group>

    <x-form-group label="Description" name="description">
        <x-textarea id="description" name="description" rows="4" placeholder="Deskripsi ringkas modul pembelajaran ini...">{{ old('description', $module->description ?? '') }}</x-textarea>
    </x-form-group>
</div>
