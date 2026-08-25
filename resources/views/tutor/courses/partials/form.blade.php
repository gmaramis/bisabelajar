@if ($errors->any())
    <x-alert variant="danger" class="mb-4">
        {{ $errors->first() }}
    </x-alert>
@endif

<div class="space-y-4">
    <x-form-group label="Title" name="title" required>
        <x-input id="title" name="title" type="text" value="{{ old('title', $course->title ?? '') }}" required placeholder="e.g. Pemrograman Web Dasar" />
    </x-form-group>

    <x-form-group label="Slug (optional)" name="slug" help="Biarkan kosong untuk membuat slug otomatis dari judul.">
        <x-input id="slug" name="slug" type="text" value="{{ old('slug', $course->slug ?? '') }}" placeholder="e.g. pemrograman-web-dasar" />
    </x-form-group>

    <x-form-group label="Description" name="description">
        <x-textarea id="description" name="description" rows="4" placeholder="Deskripsi ringkas mengenai kursus ini...">{{ old('description', $course->description ?? '') }}</x-textarea>
    </x-form-group>

    <x-form-group label="Thumbnail reference (optional)" name="thumbnail">
        <x-input id="thumbnail" name="thumbnail" type="text" value="{{ old('thumbnail', $course->thumbnail ?? '') }}" placeholder="e.g. images/course-thumb.webp" />
    </x-form-group>

    <x-form-group label="Visibility" name="visibility" required>
        <x-select id="visibility" name="visibility">
            @foreach ($visibilities as $visibility)
                <option value="{{ $visibility->value }}" @selected(old('visibility', isset($course) ? $course->visibility->value : 'private') === $visibility->value)>
                    {{ strtoupper($visibility->value) }}
                </option>
            @endforeach
        </x-select>
    </x-form-group>
</div>
