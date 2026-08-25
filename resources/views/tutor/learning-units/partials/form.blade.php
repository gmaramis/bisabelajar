@if ($errors->any())
    <x-alert variant="danger" class="mb-4">
        {{ $errors->first() }}
    </x-alert>
@endif

<div class="space-y-4">
    <x-form-group label="Title" name="title" required>
        <x-input id="title" name="title" type="text" value="{{ old('title', $learningUnit->title ?? '') }}" required placeholder="e.g. Variabel dan Tipe Data Dasar" />
    </x-form-group>

    <x-form-group label="Slug (optional)" name="slug" help="Biarkan kosong untuk membuat slug otomatis dari judul.">
        <x-input id="slug" name="slug" type="text" value="{{ old('slug', $learningUnit->slug ?? '') }}" placeholder="e.g. variabel-dan-tipe-data-dasar" />
    </x-form-group>

    <x-form-group label="Description" name="description">
        <x-textarea id="description" name="description" rows="4" placeholder="Deskripsi ringkas unit pembelajaran ini...">{{ old('description', $learningUnit->description ?? '') }}</x-textarea>
    </x-form-group>
</div>
