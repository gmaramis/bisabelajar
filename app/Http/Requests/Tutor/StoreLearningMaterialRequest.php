<?php

namespace App\Http\Requests\Tutor;

use App\Enums\MaterialType;
use App\Models\LearningMaterial;
use App\Models\LearningUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreLearningMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $learningUnit = $this->route('learningUnit');

        return $learningUnit instanceof LearningUnit
            && ($this->user()?->can('create', [LearningMaterial::class, $learningUnit]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(MaterialType::class)],
            'content' => ['nullable', 'string', 'max:50000'],
            'external_url' => ['nullable', 'string', 'max:2048'],
            'file' => ['nullable', 'file', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = MaterialType::tryFrom((string) $this->input('type'));

            if ($type === null) {
                return;
            }

            match ($type) {
                MaterialType::RichText => $this->validateRichText($validator),
                MaterialType::Pdf => $this->validatePdf($validator),
                MaterialType::Powerpoint => $this->validatePowerpoint($validator),
                MaterialType::ExternalUrl => $this->validateExternalUrl($validator),
            };
        });
    }

    private function validateRichText(Validator $validator): void
    {
        if (! $this->filled('content')) {
            $validator->errors()->add('content', 'Rich text content is required.');
        }

        $this->rejectFileAndUrl($validator);
    }

    private function validatePdf(Validator $validator): void
    {
        $this->validateUpload($validator, ['pdf'], ['application/pdf']);
        $this->rejectContentAndUrl($validator);
    }

    private function validatePowerpoint(Validator $validator): void
    {
        $this->validateUpload(
            $validator,
            ['ppt', 'pptx'],
            [
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
        );
        $this->rejectContentAndUrl($validator);
    }

    private function validateExternalUrl(Validator $validator): void
    {
        $url = trim((string) $this->input('external_url'));

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $validator->errors()->add('external_url', 'A valid external URL is required.');

            return;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $validator->errors()->add('external_url', 'External URLs must use http or https.');
        }

        $this->rejectFileAndContent($validator);
    }

    /**
     * @param  list<string>  $extensions
     * @param  list<string>  $mimes
     */
    private function validateUpload(Validator $validator, array $extensions, array $mimes): void
    {
        $file = $this->file('file');

        if ($file === null) {
            $validator->errors()->add('file', 'A file upload is required for this material type.');

            return;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $detectedMime = (string) $file->getMimeType();

        if (! in_array($extension, $extensions, true)) {
            $validator->errors()->add('file', 'The file extension is not allowed.');
        }

        if (! in_array($detectedMime, $mimes, true)) {
            $validator->errors()->add('file', 'The file type is not allowed.');
        }

        if (in_array($extension, ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm'], true)) {
            $validator->errors()->add('file', 'Executable uploads are not allowed.');
        }
    }

    private function rejectFileAndUrl(Validator $validator): void
    {
        if ($this->hasFile('file')) {
            $validator->errors()->add('file', 'Do not upload a file for this material type.');
        }

        if ($this->filled('external_url')) {
            $validator->errors()->add('external_url', 'Do not provide an external URL for this material type.');
        }
    }

    private function rejectContentAndUrl(Validator $validator): void
    {
        if ($this->filled('external_url')) {
            $validator->errors()->add('external_url', 'Do not provide an external URL for this material type.');
        }
    }

    private function rejectFileAndContent(Validator $validator): void
    {
        if ($this->hasFile('file')) {
            $validator->errors()->add('file', 'Do not upload a file for this material type.');
        }
    }
}
